<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Payroll Report';
$db = getDB();

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

$stmt = $db->prepare("SELECT p.*, e.firstname, e.lastname, e.employee_id as emp_code, d.name as dept_name FROM payroll p JOIN employees e ON p.employee_id=e.id LEFT JOIN departments d ON e.department_id=d.id WHERE p.payroll_month=? AND p.payroll_year=? ORDER BY e.firstname");
$stmt->execute([$month,$year]);
$payrolls = $stmt->fetchAll();

$totalGross = array_sum(array_column($payrolls,'gross_pay'));
$totalDed   = array_sum(array_column($payrolls,'total_deductions'));
$totalNet   = array_sum(array_column($payrolls,'net_pay'));

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-cash-stack me-2 text-primary"></i>Payroll Report</h4>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="card mb-3 no-print"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><select name="month" class="form-select form-select-sm"><?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$month?'selected':''?>><?= monthName($m) ?></option><?php endfor; ?></select></div>
    <div class="col-md-2"><select name="year" class="form-select form-select-sm"><?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?></select></div>
    <div class="col-auto"><button class="btn btn-primary btn-sm">Generate</button></div>
</form></div></div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card text-center p-3"><div class="text-muted small">Total Gross Pay</div><div class="fw-bold fs-5 text-primary"><?= money($totalGross) ?></div></div></div>
    <div class="col-md-4"><div class="card text-center p-3"><div class="text-muted small">Total Deductions</div><div class="fw-bold fs-5 text-danger"><?= money($totalDed) ?></div></div></div>
    <div class="col-md-4"><div class="card text-center p-3"><div class="text-muted small">Total Net Pay</div><div class="fw-bold fs-5 text-success"><?= money($totalNet) ?></div></div></div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Payroll — <?= monthName($month) ?> <?= $year ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>#</th><th>Employee</th><th>ID</th><th>Department</th><th>Basic Salary</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
            <tbody>
            <?php $i=1; foreach ($payrolls as $p): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($p['firstname'].' '.$p['lastname']) ?></td>
                <td><?= htmlspecialchars($p['emp_code']) ?></td>
                <td><?= htmlspecialchars($p['dept_name']??'—') ?></td>
                <td><?= money($p['basic_salary']) ?></td>
                <td><?= money($p['gross_pay']) ?></td>
                <td class="text-danger"><?= money($p['total_deductions']) ?></td>
                <td class="fw-semibold text-success"><?= money($p['net_pay']) ?></td>
                <td><?= statusBadge($p['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payrolls)): ?><tr><td colspan="9" class="text-center text-muted py-4">No payroll records for this period.</td></tr><?php endif; ?>
            <tr class="fw-bold table-secondary"><td colspan="4">TOTAL</td><td>—</td><td><?= money($totalGross) ?></td><td class="text-danger"><?= money($totalDed) ?></td><td class="text-success"><?= money($totalNet) ?></td><td></td></tr>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
