<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('employee');

$pageTitle = 'My Payslips';
$empId = currentUser()['emp_id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM payroll WHERE employee_id=? ORDER BY payroll_year DESC, payroll_month DESC");
$stmt->execute([$empId]);
$payslips = $stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header">
    <h4><i class="bi bi-receipt me-2 text-primary"></i>My Payslips</h4>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Period</th><th>Basic Salary</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payslips as $p): ?>
            <tr>
                <td><?= monthName($p['payroll_month']) ?> <?= $p['payroll_year'] ?></td>
                <td><?= money($p['basic_salary']) ?></td>
                <td><?= money($p['gross_pay']) ?></td>
                <td class="text-danger"><?= money($p['total_deductions']) ?></td>
                <td class="fw-semibold text-success"><?= money($p['net_pay']) ?></td>
                <td><?= statusBadge($p['status']) ?></td>
                <td><a href="payslip.php?id=<?=$p['id']?>" class="btn btn-sm btn-outline-primary py-0 px-2">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payslips)): ?><tr><td colspan="7" class="text-center text-muted py-4">No payslips found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
