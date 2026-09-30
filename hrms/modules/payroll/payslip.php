<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$db->exec("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction");

$stmt = $db->prepare("SELECT p.*, e.firstname, e.lastname, e.employee_id as emp_code, e.email, e.position_id, e.department_id, e.sss_no, e.philhealth_no, e.pagibig_no, e.tin_no, d.name as dept_name, pos.title as pos_title FROM payroll p JOIN employees e ON p.employee_id=e.id LEFT JOIN departments d ON e.department_id=d.id LEFT JOIN positions pos ON e.position_id=pos.id WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { header('Location: list.php?error=Payslip+not+found'); exit; }

// Employees can only see their own payslips
if (hasRole('employee') && currentUser()['emp_id'] != $p['employee_id']) {
    header('Location: '.SITE_URL.'dashboard.php?error=unauthorized'); exit;
}

$pageTitle = 'Payslip';
include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>Payslip</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="list.php">Payroll</a></li><li class="breadcrumb-item active">Payslip</li></ol></nav>
    </div>
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<div class="card" id="payslipPrint">
    <div class="card-body p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h5 class="fw-bold text-primary mb-1"><?= SITE_NAME ?></h5>
                <div class="text-muted small">Payroll System</div>
            </div>
            <div class="text-end">
                <h5 class="fw-bold mb-1">PAYSLIP</h5>
                <div class="badge bg-primary"><?= monthName($p['payroll_month']) ?> <?= $p['payroll_year'] ?></div>
                <div class="text-muted small mt-1"><?= htmlspecialchars($p['payroll_code']) ?></div>
            </div>
        </div>
        <hr>

        <!-- Employee Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted small" style="width:40%">Employee Name</td><td class="fw-semibold"><?= htmlspecialchars($p['firstname'].' '.$p['lastname']) ?></td></tr>
                    <tr><td class="text-muted small">Employee ID</td><td><?= htmlspecialchars($p['emp_code']) ?></td></tr>
                    <tr><td class="text-muted small">Department</td><td><?= htmlspecialchars($p['dept_name']??'—') ?></td></tr>
                    <tr><td class="text-muted small">Position</td><td><?= htmlspecialchars($p['pos_title']??'—') ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted small" style="width:40%">Pay Period</td><td><?= monthName($p['payroll_month']) ?> <?= $p['payroll_year'] ?></td></tr>
                    <tr><td class="text-muted small">Days Worked</td><td><?= $p['days_worked'] ?> days</td></tr>
                    <tr><td class="text-muted small">SSS No.</td><td><?= htmlspecialchars($p['sss_no']??'—') ?></td></tr>
                    <tr><td class="text-muted small">TIN No.</td><td><?= htmlspecialchars($p['tin_no']??'—') ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">EARNINGS</h6>
                <table class="table table-sm">
                    <tr><td>Basic Salary</td><td class="text-end"><?= money($p['basic_salary']) ?></td></tr>
                    <tr><td>Allowances</td><td class="text-end"><?= money($p['allowances']) ?></td></tr>
                    <tr><td>Overtime Pay</td><td class="text-end"><?= money($p['overtime_pay']) ?></td></tr>
                    <tr><td>Bonuses</td><td class="text-end"><?= money($p['bonuses']) ?></td></tr>
                    <tr class="table-primary fw-bold">
                        <td>GROSS PAY</td><td class="text-end"><?= money($p['gross_pay']) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">DEDUCTIONS</h6>
                <table class="table table-sm">
                    <tr><td>SSS Contribution</td><td class="text-end text-danger"><?= money($p['sss_deduction']) ?></td></tr>
                    <tr><td>PhilHealth</td><td class="text-end text-danger"><?= money($p['philhealth_deduction']) ?></td></tr>
                    <tr><td>Pag-IBIG</td><td class="text-end text-danger"><?= money($p['pagibig_deduction']) ?></td></tr>
                    <tr><td>Withholding Tax</td><td class="text-end text-danger"><?= money($p['tax_deduction']) ?></td></tr>
                    <?php if (($p['attendance_deduction'] ?? 0) > 0): ?><tr><td>Attendance (late / outside geofence)</td><td class="text-end text-danger"><?= money($p['attendance_deduction']) ?></td></tr><?php endif; ?>
                    <?php if ($p['other_deductions'] > 0): ?>
                    <tr><td>Other Deductions</td><td class="text-end text-danger"><?= money($p['other_deductions']) ?></td></tr>
                    <?php endif; ?>
                    <tr class="table-danger fw-bold">
                        <td>TOTAL DEDUCTIONS</td><td class="text-end"><?= money($p['total_deductions']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="bg-success text-white rounded-3 p-3 text-center mt-2">
            <div class="small mb-1">NET PAY</div>
            <div class="fs-3 fw-bold"><?= money($p['net_pay']) ?></div>
        </div>

        <div class="row mt-5">
            <div class="col-md-4 text-center">
                <div class="border-top pt-2 mt-5 small text-muted">Prepared by</div>
            </div>
            <div class="col-md-4 text-center">
                <div class="border-top pt-2 mt-5 small text-muted">Checked by</div>
            </div>
            <div class="col-md-4 text-center">
                <div class="border-top pt-2 mt-5 small text-muted">Received by</div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
