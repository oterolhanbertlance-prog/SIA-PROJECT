<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Reports';
$db = getDB();
include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Reports</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Reports</li></ol></nav>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#4361ee;"><i class="bi bi-people-fill"></i></div>
                <h5 class="fw-bold">Employee Report</h5>
                <p class="text-muted small">View and export a complete list of all employees with their department, position, salary, and status.</p>
                <a href="employees.php" class="btn btn-primary w-100"><i class="bi bi-eye me-1"></i>View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#06d6a0;"><i class="bi bi-cash-stack"></i></div>
                <h5 class="fw-bold">Payroll Report</h5>
                <p class="text-muted small">View payroll summary by month/year with gross pay, deductions, and net pay per employee.</p>
                <a href="payroll.php" class="btn btn-success w-100"><i class="bi bi-eye me-1"></i>View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#f77f00;"><i class="bi bi-calendar3-range-fill"></i></div>
                <h5 class="fw-bold">Leave Report</h5>
                <p class="text-muted small">View all leave requests with status, type, duration, and approval information.</p>
                <a href="leave.php" class="btn btn-warning w-100"><i class="bi bi-eye me-1"></i>View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#7209b7;"><i class="bi bi-briefcase-fill"></i></div>
                <h5 class="fw-bold">Applicant Report</h5>
                <p class="text-muted small">View all applicants by job posting, track their interview and hiring status.</p>
                <a href="applicants.php" class="btn btn-outline-primary w-100"><i class="bi bi-eye me-1"></i>View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#ef233c;"><i class="bi bi-building"></i></div>
                <h5 class="fw-bold">Department Report</h5>
                <p class="text-muted small">View headcount, average salary, and distribution by department.</p>
                <a href="department.php" class="btn btn-outline-danger w-100"><i class="bi bi-eye me-1"></i>View Report</a>
            </div>
        </div>
    </div>
    <?php if (hasRole('admin', 'hr')): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3" style="font-size:3rem;color:#3f37c9;"><i class="bi bi-journal-text"></i></div>
                <h5 class="fw-bold">Audit Logs</h5>
                <p class="text-muted small">Track all system actions, who performed them, and when they happened.</p>
                <a href="audit_logs.php" class="btn btn-outline-secondary w-100"><i class="bi bi-eye me-1"></i>View Logs</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
