<?php
/**
 * Employee — My Payslip
 * Shows latest payslip with all deductions (late, absent, no time-in).
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
requireLogin();

$db   = getDB();
$user = currentUser();
$empDbId = $user['emp_db_id'] ?? null;

// Select period (default: current month)
$selectedMonth = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $selectedMonth);
$periodFrom = "$yr-$mo-01";
$periodTo   = date("Y-m-t", strtotime($periodFrom));

$salary      = (float)($user['basic_salary'] ?? 13000);
$hourlyRate  = (float)($user['hourly_rate']  ?? 74.07);

$attendance = [];
if ($empDbId) {
    $stmt = $db->prepare(
        "SELECT * FROM attendance WHERE employee_id=? AND date>=? AND date<=? ORDER BY date"
    );
    $stmt->execute([$empDbId, $periodFrom, $periodTo]);
    $attendance = $stmt->fetchAll();
}

// Calculate payslip values
$workingDays   = 0;
$daysPresent   = 0;
$daysAbsent    = 0;
$totalLateMin  = 0;
$totalUTMin    = 0;
$noTimeinDays  = 0;  // days marked absent due to no time-in

// Count working days in the period (Mon–Fri)
$d = strtotime($periodFrom);
while ($d <= strtotime($periodTo)) {
    if (date('N', $d) <= 5) $workingDays++;
    $d += 86400;
}

// Map attendance by date
$attByDate = [];
foreach ($attendance as $r) {
    $attByDate[$r['date']] = $r;
}

// Walk through each working day
$d = strtotime($periodFrom);
while ($d <= strtotime($periodTo) && $d <= strtotime(date('Y-m-d'))) {
    if (date('N', $d) <= 5) {
        $dateStr = date('Y-m-d', $d);
        $rec = $attByDate[$dateStr] ?? null;
        if (!$rec) {
            $daysAbsent++;
            $noTimeinDays++;
        } elseif ($rec['status'] === 'absent') {
            $daysAbsent++;
            if (!$rec['time_in']) $noTimeinDays++;
        } else {
            $daysPresent++;
            $totalLateMin += (int)$rec['late_minutes'];
            $totalUTMin   += (int)($rec['undertime_minutes'] ?? 0);
        }
    }
    $d += 86400;
}

// Deduction calculations
$dailyRate      = $salary / 22;
$absentDeduct   = deductionFromAbsentDays($salary, $daysAbsent);
$lateDeduct     = deductionFromMinutes($salary, $totalLateMin);
$utDeduct       = deductionFromMinutes($salary, $totalUTMin);
$noTimeinDeduct = deductionFromAbsentDays($salary, $noTimeinDays); // same as absent deduction

// Avoid double-counting no-time-in inside absent
$totalDeductions = $absentDeduct + $lateDeduct + $utDeduct;

$grossPay = $salary;
$netPay   = max(0, $grossPay - $totalDeductions);

// Generate available months list
$months = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m', strtotime("-$i months"));
    $months[] = $m;
}

$pageTitle = 'My Payslip';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-receipt me-2"></i>My Payslip</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>employee/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Payslip</li>
        </ol></nav>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<!-- Period Selector -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Pay Period</label>
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($months as $m): ?>
                    <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
                        <?= date('F Y', strtotime($m . '-01')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Payslip -->
<div class="card border-0 shadow-sm" id="payslip-card">
    <!-- Header -->
    <div class="card-body p-4" style="background:linear-gradient(135deg,#4361ee,#4895ef);color:#fff;border-radius:14px 14px 0 0;">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1"><?= SITE_NAME ?></h4>
                <div class="opacity-75 small">Official Payslip</div>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5"><?= date('F Y', strtotime($periodFrom)) ?></div>
                <div class="opacity-75 small"><?= formatDate($periodFrom) ?> – <?= formatDate($periodTo) ?></div>
            </div>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Employee Info -->
        <div class="row g-3 mb-4 pb-3 border-bottom">
            <div class="col-md-6">
                <div class="text-muted small mb-1">Employee Name</div>
                <div class="fw-bold"><?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Employee ID</div>
                <div class="fw-semibold font-monospace"><?= htmlspecialchars($user['employee_id'] ?? '—') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Position</div>
                <div class="fw-semibold"><?= htmlspecialchars($user['position'] ?? '—') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small mb-1">Department</div>
                <div class="fw-semibold"><?= htmlspecialchars($user['department'] ?? '—') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Daily Rate</div>
                <div class="fw-semibold">₱<?= number_format($dailyRate, 2) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Hourly Rate</div>
                <div class="fw-semibold">₱<?= number_format($hourlyRate, 2) ?></div>
            </div>
        </div>

        <!-- Attendance Summary -->
        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar3 me-2"></i>Attendance Summary</h6>
        <div class="row g-2 mb-4">
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#f0f4ff;">
                    <div class="fw-bold text-primary"><?= $workingDays ?></div>
                    <div class="text-muted" style="font-size:.7rem;">Working Days</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#f0fff4;">
                    <div class="fw-bold text-success"><?= $daysPresent ?></div>
                    <div class="text-muted" style="font-size:.7rem;">Days Present</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#fff5f5;">
                    <div class="fw-bold text-danger"><?= $daysAbsent ?></div>
                    <div class="text-muted" style="font-size:.7rem;">Days Absent</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#fffde7;">
                    <div class="fw-bold text-warning"><?= $totalLateMin ?></div>
                    <div class="text-muted" style="font-size:.7rem;">Late (min)</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#fff3e0;">
                    <div class="fw-bold" style="color:#e65100;"><?= $totalUTMin ?></div>
                    <div class="text-muted" style="font-size:.7rem;">Undertime (min)</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="p-2 rounded-3 text-center" style="background:#fce4ec;">
                    <div class="fw-bold text-danger"><?= $noTimeinDays ?></div>
                    <div class="text-muted" style="font-size:.7rem;">No Time-In</div>
                </div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-plus-circle me-2"></i>Earnings</h6>
                <table class="table table-sm table-borderless">
                    <tbody>
                        <tr>
                            <td class="text-muted small">Basic Salary</td>
                            <td class="text-end fw-semibold">₱<?= number_format($grossPay, 2) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Gross Pay</td>
                            <td class="text-end fw-bold text-success">₱<?= number_format($grossPay, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold text-danger mb-3"><i class="bi bi-dash-circle me-2"></i>Deductions</h6>
                <table class="table table-sm table-borderless">
                    <tbody>
                        <tr>
                            <td class="text-muted small">
                                Absent/No Time-In (<?= $daysAbsent ?> day<?= $daysAbsent != 1 ? 's' : '' ?>)
                                <div style="font-size:.72rem;" class="text-danger">Employees who do not use Time In/Out are marked absent. No exceptions.</div>
                            </td>
                            <td class="text-end text-danger fw-semibold">₱<?= number_format($absentDeduct, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Late Deduction (<?= $totalLateMin ?> min)</td>
                            <td class="text-end text-danger fw-semibold">₱<?= number_format($lateDeduct, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Undertime Deduction (<?= $totalUTMin ?> min)</td>
                            <td class="text-end text-danger fw-semibold">₱<?= number_format($utDeduct, 2) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Deductions</td>
                            <td class="text-end fw-bold text-danger">₱<?= number_format($totalDeductions, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Net Pay Banner -->
        <div class="mt-3 p-4 rounded-3 text-center" style="background:linear-gradient(135deg,#1a7f3c,#28a745);color:#fff;">
            <div class="small opacity-75 mb-1">NET PAY — <?= date('F Y', strtotime($periodFrom)) ?></div>
            <div class="fw-bold" style="font-size:2.2rem;">₱<?= number_format($netPay, 2) ?></div>
            <div class="small opacity-75 mt-1">
                Gross ₱<?= number_format($grossPay,2) ?> − Deductions ₱<?= number_format($totalDeductions,2) ?>
            </div>
        </div>

        <!-- Daily Attendance Detail -->
        <h6 class="fw-bold mt-4 mb-3"><i class="bi bi-list-ul me-2"></i>Daily Attendance Detail</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>In</th><th>Out</th><th>Late</th><th>Undertime</th><th>Deduction</th></tr></thead>
                <tbody>
                <?php
                $d = strtotime($periodFrom);
                while ($d <= strtotime($periodTo)):
                    if (date('N', $d) > 5) { $d += 86400; continue; }
                    $ds  = date('Y-m-d', $d);
                    $rec = $attByDate[$ds] ?? null;
                    $lm  = (int)($rec['late_minutes'] ?? 0);
                    $um  = (int)($rec['undertime_minutes'] ?? 0);
                    if (!$rec) {
                        $rowDed = deductionFromAbsentDays($salary, 1);
                    } elseif ($rec['status'] === 'absent') {
                        $rowDed = deductionFromAbsentDays($salary, 1);
                    } else {
                        $rowDed = deductionFromMinutes($salary, $lm + $um);
                    }
                ?>
                <tr <?= $rowDed > 0 ? 'class="table-danger"' : '' ?>>
                    <td class="small"><?= formatDate($ds) ?></td>
                    <td class="small text-muted"><?= date('D', $d) ?></td>
                    <td>
                        <?php if (!$rec): ?>
                        <span class="badge bg-danger" style="font-size:.65rem;">Absent (no record)</span>
                        <?php elseif ($rec['status']==='present'): ?>
                        <span class="badge bg-success" style="font-size:.65rem;">Present</span>
                        <?php elseif ($rec['status']==='late'): ?>
                        <span class="badge bg-warning text-dark" style="font-size:.65rem;">Late</span>
                        <?php elseif ($rec['status']==='absent'): ?>
                        <span class="badge bg-danger" style="font-size:.65rem;">Absent</span>
                        <?php else: ?>
                        <span class="badge bg-secondary" style="font-size:.65rem;"><?= ucfirst($rec['status'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= $rec ? formatTime($rec['time_in']) : '—' ?></td>
                    <td class="small"><?= $rec ? formatTime($rec['time_out']) : '—' ?></td>
                    <td class="small <?= $lm > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= $lm > 0 ? $lm . ' min' : '—' ?></td>
                    <td class="small <?= $um > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= $um > 0 ? $um . ' min' : '—' ?></td>
                    <td class="small <?= $rowDed > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $rowDed > 0 ? '₱' . number_format($rowDed,2) : '—' ?></td>
                </tr>
                <?php $d += 86400; endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="text-muted small mt-3 text-center">
            <i class="bi bi-info-circle me-1"></i>
            Employees who do not use Time In/Out are automatically marked <strong>absent</strong> for that day — a full day's salary is deducted. No exceptions.
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .no-print, .btn, .breadcrumb { display: none !important; }
    .main-content { margin: 0 !important; }
    #payslip-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
