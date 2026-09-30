<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
requireLogin();

$db   = getDB();
$user = currentUser();
$empDbId = $user['emp_db_id'] ?? null;

$today = date('Y-m-d');
$todayAtt = null;
if ($empDbId) {
    $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id=? AND date=?");
    $stmt->execute([$empDbId, $today]);
    $todayAtt = $stmt->fetch() ?: null;
}

// This month stats
$monthStart = date('Y-m-01');
$monthAtt   = [];
if ($empDbId) {
    $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id=? AND date>=? AND date<=?");
    $stmt->execute([$empDbId, $monthStart, $today]);
    $monthAtt = $stmt->fetchAll();
}
$daysPresent = count(array_filter($monthAtt, fn($r) => in_array($r['status'],['present','late'])));
$daysAbsent  = count(array_filter($monthAtt, fn($r) => $r['status']==='absent'));
$totalLate   = array_sum(array_column($monthAtt, 'late_minutes'));
$salary      = (float)($user['basic_salary'] ?? 13000);
$lateDeduct  = deductionFromMinutes($salary, $totalLate);
$absDeduct   = deductionFromAbsentDays($salary, $daysAbsent);
$totalDeduct = $lateDeduct + $absDeduct;

$pageTitle = 'My Dashboard';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-house me-2"></i>Welcome, <?= htmlspecialchars($user['firstname'] ?? 'Employee') ?>!</h4>
        <p class="text-muted small mb-0"><?= htmlspecialchars($user['position'] ?? '') ?> · <?= htmlspecialchars($user['department'] ?? '') ?></p>
    </div>
    <div class="text-muted small"><?= date('l, F d, Y') ?></div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-green">
            <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-value"><?= $daysPresent ?></div>
            <div class="stat-label">Days Present (This Month)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-red">
            <div class="stat-icon"><i class="bi bi-calendar-x-fill"></i></div>
            <div class="stat-value"><?= $daysAbsent ?></div>
            <div class="stat-label">Days Absent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-orange">
            <div class="stat-icon"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-value"><?= $totalLate ?></div>
            <div class="stat-label">Total Late (min)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-purple">
            <div class="stat-icon"><i class="bi bi-dash-circle-fill"></i></div>
            <div class="stat-value">₱<?= number_format($totalDeduct, 0) ?></div>
            <div class="stat-label">Est. Deductions</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Today's Status -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold"><i class="bi bi-clock me-2 text-primary"></i>Today's Status</div>
            <div class="card-body text-center py-4">
                <?php if (!$todayAtt): ?>
                <div class="text-muted mb-3"><i class="bi bi-clock display-4"></i></div>
                <p class="fw-semibold text-danger">Not yet clocked in!</p>
                <p class="text-muted small">Work starts at 8:00 AM. Late arrivals will be deducted.</p>
                <a href="<?= SITE_URL ?>employee/timekeeping.php" class="btn btn-success btn-lg px-4">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Clock In Now
                </a>
                <?php elseif (!$todayAtt['time_out']): ?>
                <div class="mb-3">
                    <span class="badge bg-success px-3 py-2 fs-6">
                        <i class="bi bi-arrow-right-circle me-1"></i>In: <?= formatTime($todayAtt['time_in']) ?>
                    </span>
                    <?php if ($todayAtt['late_minutes'] > 0): ?>
                    <div class="mt-2"><span class="badge bg-warning text-dark"><?= $todayAtt['late_minutes'] ?> min late</span></div>
                    <?php endif; ?>
                </div>
                <a href="<?= SITE_URL ?>employee/timekeeping.php" class="btn btn-danger px-4">
                    <i class="bi bi-box-arrow-right me-2"></i>Clock Out
                </a>
                <?php else: ?>
                <div class="d-flex flex-column align-items-center gap-2">
                    <span class="badge bg-success px-3 py-2">In: <?= formatTime($todayAtt['time_in']) ?></span>
                    <span class="badge bg-danger px-3 py-2">Out: <?= formatTime($todayAtt['time_out']) ?></span>
                    <?php if ($todayAtt['late_minutes'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $todayAtt['late_minutes'] ?> min late</span>
                    <?php endif; ?>
                    <div class="text-muted small mt-2"><i class="bi bi-check-circle-fill text-success me-1"></i>Complete for today</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold"><i class="bi bi-grid me-2 text-primary"></i>Quick Links</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?= SITE_URL ?>employee/timekeeping.php" class="card border-0 bg-primary bg-opacity-10 text-decoration-none p-3 d-block rounded-3">
                            <i class="bi bi-clock fs-3 text-primary mb-2 d-block"></i>
                            <div class="fw-semibold small text-primary">Time In/Out</div>
                            <div class="text-muted" style="font-size:.75rem;">8:00 AM – 5:00 PM</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= SITE_URL ?>employee/payslip.php" class="card border-0 bg-success bg-opacity-10 text-decoration-none p-3 d-block rounded-3">
                            <i class="bi bi-receipt fs-3 text-success mb-2 d-block"></i>
                            <div class="fw-semibold small text-success">My Payslip</div>
                            <div class="text-muted" style="font-size:.75rem;">View salary &amp; deductions</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 bg-light p-3 d-block rounded-3">
                            <i class="bi bi-person fs-3 text-secondary mb-2 d-block"></i>
                            <div class="fw-semibold small">Employee ID</div>
                            <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($user['employee_id'] ?? '—') ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 bg-light p-3 d-block rounded-3">
                            <i class="bi bi-cash fs-3 text-secondary mb-2 d-block"></i>
                            <div class="fw-semibold small">Basic Salary</div>
                            <div class="text-muted" style="font-size:.75rem;">₱<?= number_format($salary, 2) ?>/mo</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
