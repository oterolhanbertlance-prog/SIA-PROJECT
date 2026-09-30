<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Payroll';
$db = getDB();

$month  = (int)($_GET['month'] ?? date('n'));
$year   = (int)($_GET['year']  ?? date('Y'));
$search = sanitize($_GET['search'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page-1)*$perPage;

$where  = ["p.payroll_month=?","p.payroll_year=?"];
$params = [$month,$year];
if ($search) {
    $where[] = "(e.firstname LIKE ? OR e.lastname LIKE ? OR e.employee_id LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params,[$s,$s,$s]);
}
$whereStr = 'WHERE '.implode(' AND ',$where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM payroll p JOIN employees e ON p.employee_id=e.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT p.*, e.firstname, e.lastname, e.employee_id as emp_code, d.name as dept_name FROM payroll p JOIN employees e ON p.employee_id=e.id LEFT JOIN departments d ON e.department_id=d.id $whereStr ORDER BY e.firstname LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$payrolls = $stmt->fetchAll();

// Summary
$sumStmt = $db->prepare("SELECT SUM(gross_pay) as gross, SUM(total_deductions) as deductions, SUM(net_pay) as net, COUNT(*) as cnt FROM payroll p JOIN employees e ON p.employee_id=e.id $whereStr");
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-cash-stack me-2 text-primary"></i>Payroll</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Payroll</li></ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="process.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Process Payroll</a>
        <a href="../reports/payroll.php?month=<?=$month?>&year=<?=$year?>" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Employees</div>
            <div class="fw-bold fs-4"><?= $summary['cnt'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Gross Pay</div>
            <div class="fw-bold fs-5 text-primary"><?= money($summary['gross']??0) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Total Deductions</div>
            <div class="fw-bold fs-5 text-danger"><?= money($summary['deductions']??0) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3">
            <div class="text-muted small">Net Pay</div>
            <div class="fw-bold fs-5 text-success"><?= money($summary['net']??0) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="month" class="form-select form-select-sm">
                    <?php for ($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$m==$month?'selected':''?>><?= monthName($m) ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select form-select-sm">
                    <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="search-box w-100">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search employee..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="list.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Payroll — <?= monthName($month) ?> <?= $year ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th>ID</th><th>Department</th><th>Basic Salary</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($payrolls)): ?><tr><td colspan="9" class="text-center py-4 text-muted">No payroll records for this period.</td></tr><?php endif; ?>
            <?php foreach ($payrolls as $p): ?>
            <tr>
                <td class="fw-semibold small"><?= htmlspecialchars($p['firstname'].' '.$p['lastname']) ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($p['emp_code']) ?></span></td>
                <td><?= htmlspecialchars($p['dept_name']??'—') ?></td>
                <td><?= money($p['basic_salary']) ?></td>
                <td><?= money($p['gross_pay']) ?></td>
                <td class="text-danger"><?= money($p['total_deductions']) ?></td>
                <td class="fw-semibold text-success"><?= money($p['net_pay']) ?></td>
                <td><?= statusBadge($p['status']) ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="payslip.php?id=<?=$p['id']?>" class="btn btn-sm btn-info py-0 px-2" title="Payslip"><i class="bi bi-receipt"></i></a>
                        <a href="edit_payroll.php?id=<?=$p['id']?>" class="btn btn-sm btn-warning py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="#" onclick="confirmDelete('delete_payroll.php?id=<?=$p['id']?>','payroll record')" class="btn btn-sm btn-danger py-0 px-2" title="Delete"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?></small>
        <?= paginate($total,$perPage,$page,'list.php?'.http_build_query(['month'=>$month,'year'=>$year,'search'=>$search])) ?>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
