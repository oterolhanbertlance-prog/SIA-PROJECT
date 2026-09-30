<?php
/**
 * Time In / Time Out — for Employees, HR, and Admin
 * Work schedule: 08:00 – 17:00 (8 AM – 5 PM)
 * Location check: GPS must be within COMPANY_RADIUS_M metres of the office.
 * If clocked in/out from outside the office a full-day salary deduction is applied.
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
require_once '../config/pos_attendance_sync.php';
requireLogin();

if (!hasRole('employee', 'hr', 'admin')) {
    http_response_code(403); die('Access denied.');
}

// ── Company location ────────────────────────────────────────────────────────
// Each branch has its own GPS location — pulled from the branches table
// (set per-branch in Settings → Branches) instead of one hardcoded office.
$db = getDB();
$user    = currentUser();
$userId  = (int)$user['id'];
$empDbId = $user['emp_db_id'] ?? null;

$branchRow = null;
if ($empDbId) {
    $bq = $db->prepare("SELECT b.* FROM employees e JOIN branches b ON b.id = e.branch_id WHERE e.id = ?");
    $bq->execute([$empDbId]);
    $branchRow = $bq->fetch();
}
if (!$branchRow) {
    // HR/Admin without an employee record (or no branch assigned): use their
    // own assigned branch, or fall back to the Head Office branch (id 1).
    $bq = $db->prepare(
        "SELECT b.* FROM users u JOIN branches b ON b.id = u.branch_id WHERE u.id = ?"
    );
    $bq->execute([$userId]);
    $branchRow = $bq->fetch();
}
if (!$branchRow) {
    $branchRow = $db->query("SELECT * FROM branches WHERE status='active' ORDER BY id LIMIT 1")->fetch();
}

define('COMPANY_LAT',      $branchRow['latitude']  ?? 14.329444);
define('COMPANY_LON',      $branchRow['longitude'] ?? 120.936806);
define('COMPANY_RADIUS_M', 100);        // metres
define('COMPANY_NAME_LOC', $branchRow['name'] ?? 'Head Office');

/**
 * Haversine formula — distance in metres between two lat/lon points.
 */
function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R  = 6371000;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a  = sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function isOnSite(float $lat, float $lon): bool {
    return haversineDistance($lat, $lon, COMPANY_LAT, COMPANY_LON) <= COMPANY_RADIUS_M;
}

// ── DB / User setup ─────────────────────────────────────────────────────────
// ($db, $user, $userId, $empDbId already set above while resolving the branch)
$today   = date('Y-m-d');

// Silently add GPS columns if they don't exist yet (safe to run every page load)
$db->exec("
    ALTER TABLE attendance
    ADD COLUMN IF NOT EXISTS time_in_lat      DECIMAL(10,7)  NULL,
    ADD COLUMN IF NOT EXISTS time_in_lon      DECIMAL(10,7)  NULL,
    ADD COLUMN IF NOT EXISTS time_out_lat     DECIMAL(10,7)  NULL,
    ADD COLUMN IF NOT EXISTS time_out_lon     DECIMAL(10,7)  NULL,
    ADD COLUMN IF NOT EXISTS time_in_distance DECIMAL(10,2)  NULL COMMENT 'metres from office at clock-in',
    ADD COLUMN IF NOT EXISTS time_out_distance DECIMAL(10,2) NULL COMMENT 'metres from office at clock-out',
    ADD COLUMN IF NOT EXISTS location_status  VARCHAR(20)    NOT NULL DEFAULT 'unknown',
    ADD COLUMN IF NOT EXISTS remote_deduction DECIMAL(10,2)  NOT NULL DEFAULT 0
");

// ── Helper: fetch today's record ────────────────────────────────────────────
function getTodayRecord($db, $empDbId, $userId, $today) {
    if ($empDbId) {
        $s = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $s->execute([$empDbId, $today]);
    } else {
        $s = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND employee_id IS NULL AND date = ?");
        $s->execute([$userId, $today]);
    }
    return $s->fetch() ?: null;
}

$todayRecord = getTodayRecord($db, $empDbId, $userId, $today);
$msg = '';

// ── Handle time-in ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'time_in') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $msg = danger('Invalid form submission.');
    } elseif ($todayRecord && $todayRecord['time_in']) {
        $msg = warning('You have already clocked in today.');
    } else {
        $timeIn      = date('H:i:s');
        $lateMinutes = calcLateMinutes($timeIn);
        $status      = $lateMinutes > 0 ? 'late' : 'present';

        // ── GPS ──
        $lat      = isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lon      = isset($_POST['lon']) && is_numeric($_POST['lon']) ? (float)$_POST['lon'] : null;
        $dist     = null;
        $locStat  = 'unknown';
        $remoteDed = 0.0;

        if ($lat !== null && $lon !== null) {
            $dist    = round(haversineDistance($lat, $lon, COMPANY_LAT, COMPANY_LON), 2);
            $locStat = $dist <= COMPANY_RADIUS_M ? 'on_site' : 'remote';
            if ($locStat === 'remote') {
                $salary    = (float)($user['basic_salary'] ?? 0);
                $remoteDed = $salary > 0 ? deductionFromAbsentDays($salary, 1) : 0.0;
            }
        }

        if ($todayRecord) {
            $db->prepare(
                "UPDATE attendance
                 SET time_in=?, late_minutes=?, status=?,
                     time_in_lat=?, time_in_lon=?, time_in_distance=?,
                     location_status=?, remote_deduction=?
                 WHERE id=?"
            )->execute([$timeIn, $lateMinutes, $status,
                        $lat, $lon, $dist, $locStat, $remoteDed,
                        $todayRecord['id']]);
        } elseif ($empDbId) {
            $db->prepare(
                "INSERT INTO attendance
                     (user_id, employee_id, date, time_in, late_minutes, status,
                      time_in_lat, time_in_lon, time_in_distance, location_status, remote_deduction)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                     time_in=VALUES(time_in), late_minutes=VALUES(late_minutes), status=VALUES(status),
                     time_in_lat=VALUES(time_in_lat), time_in_lon=VALUES(time_in_lon),
                     time_in_distance=VALUES(time_in_distance),
                     location_status=VALUES(location_status), remote_deduction=VALUES(remote_deduction)"
            )->execute([$userId, $empDbId, $today, $timeIn, $lateMinutes, $status,
                        $lat, $lon, $dist, $locStat, $remoteDed]);
        } else {
            $db->prepare(
                "INSERT INTO attendance
                     (user_id, employee_id, date, time_in, late_minutes, status,
                      time_in_lat, time_in_lon, time_in_distance, location_status, remote_deduction)
                 VALUES (?,NULL,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                     time_in=VALUES(time_in), late_minutes=VALUES(late_minutes), status=VALUES(status),
                     time_in_lat=VALUES(time_in_lat), time_in_lon=VALUES(time_in_lon),
                     time_in_distance=VALUES(time_in_distance),
                     location_status=VALUES(location_status), remote_deduction=VALUES(remote_deduction)"
            )->execute([$userId, $today, $timeIn, $lateMinutes, $status,
                        $lat, $lon, $dist, $locStat, $remoteDed]);
        }

        $todayRecord = getTodayRecord($db, $empDbId, $userId, $today);
        if ($todayRecord) mirrorHrmsAttendanceToPos($db, $todayRecord);

        $locMsg = '';
        if ($locStat === 'on_site') {
            $locMsg = ' &nbsp;<span class="badge bg-success"><i class="bi bi-geo-alt-fill me-1"></i>On-Site (' . number_format($dist,0) . ' m)</span>';
        } elseif ($locStat === 'remote') {
            $locMsg = ' &nbsp;<span class="badge bg-danger"><i class="bi bi-geo-alt me-1"></i>Outside Office (' . number_format($dist,0) . ' m) — Deduction applied</span>';
        } else {
            $locMsg = ' &nbsp;<span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i>Location unknown</span>';
        }

        $msg = $lateMinutes > 0
            ? warning("Clocked in at " . formatTime($timeIn) . " — you are <strong>{$lateMinutes} minute(s) late</strong>." . $locMsg)
            : success("Clocked in at " . formatTime($timeIn) . " — on time! ✓" . $locMsg);
    }
}

// ── Handle time-out ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'time_out') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $msg = danger('Invalid form submission.');
    } elseif (!$todayRecord || !$todayRecord['time_in']) {
        $msg = danger('You must clock in before clocking out.');
    } elseif ($todayRecord['time_out']) {
        $msg = warning('You have already clocked out today.');
    } else {
        $timeOut          = date('H:i:s');
        $undertimeMinutes = calcUndertimeMinutes($timeOut);

        // ── GPS ──
        $lat     = isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lon     = isset($_POST['lon']) && is_numeric($_POST['lon']) ? (float)$_POST['lon'] : null;
        $dist    = null;
        $locStat = $todayRecord['location_status'] ?? 'unknown';  // keep existing status

        if ($lat !== null && $lon !== null) {
            $dist       = round(haversineDistance($lat, $lon, COMPANY_LAT, COMPANY_LON), 2);
            $outLocStat = $dist <= COMPANY_RADIUS_M ? 'on_site' : 'remote';
            // If either clock-in or clock-out is remote → mark remote
            if ($locStat !== 'remote') $locStat = $outLocStat;
        }

        $hoursWorked = max(0, round((strtotime($today . ' ' . $timeOut) - strtotime($today . ' ' . $todayRecord['time_in'])) / 3600, 2));
        $remoteDed = (float)($todayRecord['remote_deduction'] ?? 0);
        if ($locStat === 'remote' && $remoteDed <= 0) {
            $salary = (float)($user['basic_salary'] ?? 0);
            $remoteDed = $salary > 0 ? deductionFromAbsentDays($salary, 1) : 0.0;
        }
        $db->prepare(
            "UPDATE attendance
             SET time_out=?, hours_worked=?, undertime_minutes=?,
                 time_out_lat=?, time_out_lon=?, time_out_distance=?,
                 location_status=?, remote_deduction=?
             WHERE id=?"
        )->execute([$timeOut, $hoursWorked, $undertimeMinutes, $lat, $lon, $dist, $locStat, $remoteDed, $todayRecord['id']]);

        $todayRecord = getTodayRecord($db, $empDbId, $userId, $today);
        if ($todayRecord) mirrorHrmsAttendanceToPos($db, $todayRecord);

        $locMsg = '';
        if ($dist !== null) {
            $tag     = $dist <= COMPANY_RADIUS_M ? 'bg-success' : 'bg-danger';
            $icon    = $dist <= COMPANY_RADIUS_M ? 'bi-geo-alt-fill' : 'bi-geo-alt';
            $label   = $dist <= COMPANY_RADIUS_M ? 'On-Site' : 'Outside Office';
            $locMsg  = " &nbsp;<span class=\"badge {$tag}\"><i class=\"bi {$icon} me-1\"></i>{$label} (" . number_format($dist,0) . " m)</span>";
        }

        $msg = $undertimeMinutes > 0
            ? warning("Clocked out at " . formatTime($timeOut) . " — you left <strong>{$undertimeMinutes} minute(s) early</strong>." . $locMsg)
            : success("Clocked out at " . formatTime($timeOut) . " — great work today! ✓" . $locMsg);
    }
}

// ── Attendance history (last 30 days) ───────────────────────────────────────
if ($empDbId) {
    $hStmt = $db->prepare(
        "SELECT * FROM attendance WHERE employee_id = ? AND date <= ? ORDER BY date DESC LIMIT 30"
    );
    $hStmt->execute([$empDbId, $today]);
} else {
    $hStmt = $db->prepare(
        "SELECT * FROM attendance WHERE user_id = ? AND employee_id IS NULL AND date <= ? ORDER BY date DESC LIMIT 30"
    );
    $hStmt->execute([$userId, $today]);
}
$history = $hStmt->fetchAll();

$salary       = (float)($user['basic_salary'] ?? 0);
$totalPresent = count(array_filter($history, fn($r) => in_array($r['status'], ['present','late'])));
$totalLate    = count(array_filter($history, fn($r) => $r['status'] === 'late'));
$totalAbsent  = count(array_filter($history, fn($r) => $r['status'] === 'absent'));

$pageTitle = 'Time In / Out';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-clock-fill me-2 text-primary"></i>Time In / Out</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="<?= SITE_URL ?><?= hasRole('employee') ? 'employee/dashboard.php' : 'dashboard.php' ?>">
                    <?= hasRole('employee') ? 'My Dashboard' : 'Dashboard' ?>
                </a>
            </li>
            <li class="breadcrumb-item active">Time In / Out</li>
        </ol></nav>
    </div>
    <!-- Location indicator -->
    <div id="locationBadge" class="badge bg-secondary px-3 py-2" style="font-size:.78rem;">
        <i class="bi bi-geo-alt me-1"></i><span id="locationText">Detecting location…</span>
    </div>
</div>

<?= $msg ?>

<!-- GPS denied warning (shown by JS if needed) -->
<div id="gpsWarning" class="alert alert-warning d-none">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Location access denied.</strong> Your attendance will be recorded but location cannot be verified.
    Clock-ins without a verifiable location may be subject to manual review.
</div>

<div class="row g-4 mb-4">

    <!-- ── Clock Card ───────────────────────────────────────────────────── -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 text-center py-4">
            <div class="card-body">
                <p class="text-muted small mb-1 text-uppercase fw-semibold">Current Time</p>
                <div id="live-clock" class="display-4 fw-bold text-primary mb-2"
                     style="font-variant-numeric:tabular-nums;letter-spacing:2px;">--:--:-- --</div>
                <p class="text-muted small mb-0"><?= date('l, F j, Y') ?></p>

                <hr class="my-3">

                <?php if (!$todayRecord || !$todayRecord['time_in']): ?>
                <!-- Not yet clocked in -->
                <form method="POST" id="timeInForm" onsubmit="return false;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="time_in">
                    <input type="hidden" name="lat" id="inLat" value="">
                    <input type="hidden" name="lon" id="inLon" value="">
                    <button type="button" id="clockInBtn" class="btn btn-success btn-lg px-5 mt-2"
                            onclick="handleClock('in')">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Clock In
                    </button>
                    <p class="text-muted small mt-2 mb-0">Work starts at <strong>08:00 AM</strong></p>
                </form>

                <?php elseif (!$todayRecord['time_out']): ?>
                <!-- Clocked in, not yet out -->
                <div class="mb-3">
                    <span class="badge px-3 py-2 border <?= ($todayRecord['location_status'] ?? 'unknown') === 'remote' ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle' ?>"
                          style="font-size:.85rem;">
                        <i class="bi bi-check-circle me-1"></i>
                        Clocked in at <strong><?= formatTime($todayRecord['time_in']) ?></strong>
                        <?php if (($todayRecord['late_minutes'] ?? 0) > 0): ?>
                        &nbsp;·&nbsp;<span class="text-warning"><?= $todayRecord['late_minutes'] ?> min late</span>
                        <?php endif; ?>
                    </span>
                    <?php
                    $inLoc = $todayRecord['location_status'] ?? 'unknown';
                    $inDist = $todayRecord['time_in_distance'] ?? null;
                    ?>
                    <div class="mt-2">
                        <?php if ($inLoc === 'on_site'): ?>
                        <span class="badge bg-success"><i class="bi bi-geo-alt-fill me-1"></i>On-Site<?= $inDist !== null ? ' (' . number_format($inDist,0) . ' m)' : '' ?></span>
                        <?php elseif ($inLoc === 'remote'): ?>
                        <span class="badge bg-danger"><i class="bi bi-geo-alt me-1"></i>Outside Office<?= $inDist !== null ? ' (' . number_format($inDist,0) . ' m)' : '' ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary"><i class="bi bi-question-circle me-1"></i>Location unknown</span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" id="timeOutForm" onsubmit="return false;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="time_out">
                    <input type="hidden" name="lat" id="outLat" value="">
                    <input type="hidden" name="lon" id="outLon" value="">
                    <button type="button" id="clockOutBtn" class="btn btn-danger btn-lg px-5"
                            onclick="handleClock('out')">
                        <i class="bi bi-box-arrow-right me-2"></i>Clock Out
                    </button>
                    <p class="text-muted small mt-2 mb-0">Work ends at <strong>05:00 PM</strong></p>
                </form>

                <?php else: ?>
                <!-- Done for today -->
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check2-circle me-2"></i>
                    Attendance complete for today.<br>
                    <small>
                        In: <strong><?= formatTime($todayRecord['time_in']) ?></strong>
                        &nbsp;·&nbsp;
                        Out: <strong><?= formatTime($todayRecord['time_out']) ?></strong>
                    </small><br>
                    <?php
                    $finLoc = $todayRecord['location_status'] ?? 'unknown';
                    if ($finLoc === 'on_site'): ?>
                    <span class="badge bg-success mt-1"><i class="bi bi-geo-alt-fill me-1"></i>On-Site</span>
                    <?php elseif ($finLoc === 'remote'): ?>
                    <span class="badge bg-danger mt-1"><i class="bi bi-geo-alt me-1"></i>Outside Office — Deduction applied</span>
                    <?php else: ?>
                    <span class="badge bg-secondary mt-1"><i class="bi bi-question-circle me-1"></i>Location unknown</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Summary Card ─────────────────────────────────────────────────── -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold"><i class="bi bi-calendar-day me-2"></i>Last 30 Days</div>
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="display-6 fw-bold text-success"><?= $totalPresent ?></div>
                        <div class="small text-muted">Present</div>
                    </div>
                    <div class="col-4">
                        <div class="display-6 fw-bold text-warning"><?= $totalLate ?></div>
                        <div class="small text-muted">Late</div>
                    </div>
                    <div class="col-4">
                        <div class="display-6 fw-bold text-danger"><?= $totalAbsent ?></div>
                        <div class="small text-muted">Absent</div>
                    </div>
                </div>
                <hr>
                <div class="small text-muted mb-2">
                    <i class="bi bi-geo-alt-fill text-primary me-1"></i>
                    <strong>Office location:</strong> <?= COMPANY_NAME_LOC ?><br>
                    <i class="bi bi-rulers me-1"></i>Allowed radius: <?= COMPANY_RADIUS_M ?> metres<br>
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>Clocking in from <strong>outside the office</strong> applies a full-day salary deduction.
                </div>
                <hr>
                <p class="small text-muted mb-0">
                    <i class="bi bi-clock me-1"></i>Work schedule: <strong>8:00 AM – 5:00 PM</strong>, Mon–Fri.<br>
                    Late arrivals and early departures result in proportional deductions.
                </p>
                <?php if (!$empDbId): ?>
                <div class="alert alert-info mt-3 mb-0 small">
                    <i class="bi bi-person-gear me-1"></i>
                    Clocked in as <strong><?= htmlspecialchars($user['username']) ?></strong>
                    (<?= ucfirst($user['role'] ?? 'admin') ?>). Tracked by user account.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Attendance History ────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-table me-2"></i>Attendance History (Last 30 Days)</span>
        <?php if ($empDbId): ?>
        <a href="<?= SITE_URL ?>employee/payslip.php" class="btn btn-sm btn-outline-primary py-0">
            <i class="bi bi-receipt me-1"></i>View Payslips
        </a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.84rem;">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Location</th>
                    <th>Late (min)</th>
                    <th>Undertime (min)</th>
                    <?php if ($salary > 0): ?><th>Deduction</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($history)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No attendance records found.</td></tr>
            <?php else: foreach ($history as $row):
                $rLate  = (int)($row['late_minutes'] ?? 0);
                $rUt    = (int)($row['undertime_minutes'] ?? 0);
                $rRemDed = (float)($row['remote_deduction'] ?? 0);
                $rDed   = 0.0;
                if ($salary > 0) {
                    if ($row['status'] === 'absent') {
                        $rDed = deductionFromAbsentDays($salary, 1);
                    } else {
                        if ($rLate > 0) $rDed += deductionFromMinutes($salary, $rLate);
                        if ($rUt > 0)   $rDed += deductionFromMinutes($salary, $rUt);
                        if ($rRemDed > 0) $rDed += $rRemDed;
                    }
                }
                $locStat = $row['location_status'] ?? 'unknown';
                $inDist  = $row['time_in_distance']  ?? null;
                $outDist = $row['time_out_distance'] ?? null;
            ?>
            <tr>
                <td class="fw-semibold small"><?= formatDate($row['date']) ?></td>
                <td>
                    <?php $st = $row['status']; ?>
                    <?php if ($st==='present'): ?>  <span class="badge bg-success">Present</span>
                    <?php elseif ($st==='late'): ?>  <span class="badge bg-warning text-dark">Late</span>
                    <?php elseif ($st==='absent'): ?><span class="badge bg-danger">Absent</span>
                    <?php elseif ($st==='half_day'):?><span class="badge bg-info text-dark">Half Day</span>
                    <?php elseif ($st==='leave'): ?> <span class="badge bg-primary">On Leave</span>
                    <?php else: ?>                   <span class="badge bg-secondary"><?= ucfirst($st) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= formatTime($row['time_in']) ?>
                    <?php if ($inDist !== null): ?>
                    <br><small class="text-muted"><?= number_format($inDist,0) ?> m</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= formatTime($row['time_out']) ?>
                    <?php if ($outDist !== null): ?>
                    <br><small class="text-muted"><?= number_format($outDist,0) ?> m</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($locStat === 'on_site'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-geo-alt-fill me-1"></i>On-Site
                    </span>
                    <?php elseif ($locStat === 'remote'): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="bi bi-geo-alt me-1"></i>Outside
                    </span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">
                        <i class="bi bi-question-circle me-1"></i>Unknown
                    </span>
                    <?php endif; ?>
                </td>
                <td class="<?= $rLate>0?'text-warning fw-semibold':'text-muted' ?>"><?= $rLate>0?$rLate.' min':'—' ?></td>
                <td class="<?= $rUt>0?'text-warning fw-semibold':'text-muted' ?>"><?= $rUt>0?$rUt.' min':'—' ?></td>
                <?php if ($salary > 0): ?>
                <td>
                    <?php if ($rDed > 0): ?>
                    <span class="text-danger fw-semibold">₱<?= number_format($rDed,2) ?></span>
                    <?php if ($rRemDed > 0): ?>
                    <br><small class="text-danger" title="Remote location penalty">+₱<?= number_format($rRemDed,2) ?> remote</small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Live clock ────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    const h12 = now.getHours() % 12 || 12;
    const m   = String(now.getMinutes()).padStart(2,'0');
    const s   = String(now.getSeconds()).padStart(2,'0');
    const ap  = now.getHours() >= 12 ? 'PM' : 'AM';
    document.getElementById('live-clock').textContent =
        String(h12).padStart(2,'0') + ':' + m + ':' + s + ' ' + ap;
}
updateClock();
setInterval(updateClock, 1000);

// ── Company coords ────────────────────────────────────────────────────────────
const COMPANY_LAT    = <?= COMPANY_LAT ?>;
const COMPANY_LON    = <?= COMPANY_LON ?>;
const COMPANY_RADIUS = <?= COMPANY_RADIUS_M ?>;
const COMPANY_NAME   = '<?= COMPANY_NAME_LOC ?>';

// ── Haversine in JS ───────────────────────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
    const R  = 6371000;
    const p1 = lat1 * Math.PI / 180, p2 = lat2 * Math.PI / 180;
    const dp = (lat2 - lat1) * Math.PI / 180;
    const dl = (lon2 - lon1) * Math.PI / 180;
    const a  = Math.sin(dp/2)**2 + Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Cached position ───────────────────────────────────────────────────────────
let cachedLat = null, cachedLon = null, cachedDist = null;

function updateLocationBadge(lat, lon) {
    const dist   = Math.round(haversine(lat, lon, COMPANY_LAT, COMPANY_LON));
    const badge  = document.getElementById('locationBadge');
    const text   = document.getElementById('locationText');
    cachedLat = lat; cachedLon = lon; cachedDist = dist;
    if (dist <= COMPANY_RADIUS) {
        badge.className = 'badge bg-success px-3 py-2';
        text.textContent = `On-Site — ${COMPANY_NAME} (${dist} m)`;
    } else {
        badge.className = 'badge bg-danger px-3 py-2';
        text.textContent = `Outside Office — ${dist} m from ${COMPANY_NAME}`;
    }
}

function getLocation(callback) {
    if (!navigator.geolocation) {
        document.getElementById('gpsWarning').classList.remove('d-none');
        callback(null, null);
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            updateLocationBadge(pos.coords.latitude, pos.coords.longitude);
            callback(pos.coords.latitude, pos.coords.longitude);
        },
        err => {
            document.getElementById('gpsWarning').classList.remove('d-none');
            document.getElementById('locationBadge').className = 'badge bg-warning text-dark px-3 py-2';
            document.getElementById('locationText').textContent = 'Location unavailable';
            callback(null, null);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// Eagerly fetch location on page load for the badge
getLocation(() => {});

// ── Clock button handlers ────────────────────────────────────────────────────
function handleClock(type) {
    const btn = document.getElementById(type === 'in' ? 'clockInBtn' : 'clockOutBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Getting location…';

    getLocation((lat, lon) => {
        const dist = (lat !== null && lon !== null)
            ? Math.round(haversine(lat, lon, COMPANY_LAT, COMPANY_LON))
            : null;
        const onSite = dist !== null && dist <= COMPANY_RADIUS;
        const unknown = dist === null;

        let locHtml = '';
        let iconColor = '';
        let titleText = type === 'in' ? 'Clock In?' : 'Clock Out?';

        if (unknown) {
            locHtml = `<div class="alert alert-warning text-start small mt-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Location unavailable.</strong> Your attendance will be recorded without location verification.
            </div>`;
            iconColor = 'question';
        } else if (onSite) {
            locHtml = `<div class="alert alert-success text-start small mt-2">
                <i class="bi bi-geo-alt-fill me-1"></i>
                <strong>You are on-site</strong> at ${COMPANY_NAME} (${dist} m away). No location deduction.
            </div>`;
            iconColor = type === 'in' ? 'question' : 'warning';
        } else {
            locHtml = `<div class="alert alert-danger text-start small mt-2">
                <i class="bi bi-exclamation-octagon me-1"></i>
                <strong>You are ${dist} m from ${COMPANY_NAME}</strong> — outside the allowed ${COMPANY_RADIUS} m radius.<br>
                <strong>A full-day salary deduction will be applied.</strong>
            </div>`;
            iconColor = 'warning';
            titleText = `⚠️ ${type === 'in' ? 'Clock In' : 'Clock Out'} from Outside Office?`;
        }

        Swal.fire({
            title: titleText,
            html: `<p class="text-muted small mb-1">Your time will be recorded now.</p>${locHtml}`,
            icon: iconColor,
            showCancelButton: true,
            confirmButtonColor: type === 'in' ? '#2a9d8f' : '#ef233c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: type === 'in'
                ? '<i class="bi bi-box-arrow-in-right"></i> Yes, Clock In'
                : '<i class="bi bi-box-arrow-right"></i> Yes, Clock Out',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                const form = document.getElementById(type === 'in' ? 'timeInForm' : 'timeOutForm');
                if (lat !== null) {
                    form.querySelector('[name="lat"]').value = lat;
                    form.querySelector('[name="lon"]').value = lon;
                }
                form.submit();
            } else {
                btn.disabled = false;
                btn.innerHTML = type === 'in'
                    ? '<i class="bi bi-box-arrow-in-right me-2"></i>Clock In'
                    : '<i class="bi bi-box-arrow-right me-2"></i>Clock Out';
            }
        });
    });
}
</script>
<?php include '../includes/footer.php'; ?>
