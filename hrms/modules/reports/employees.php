<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Employee Report';
$db = getDB();

$dept   = (int)($_GET['dept'] ?? 0);
$status = sanitize($_GET['status'] ?? '');
$where  = ["e.status != 'archived'"]; $params = [];
if ($dept)   { $where[] = "e.department_id=?"; $params[] = $dept; }
if ($status) { $where[] = "e.employment_status=?"; $params[] = $status; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$stmt = $db->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title FROM employees e LEFT JOIN departments d ON e.department_id=d.id LEFT JOIN positions p ON e.position_id=p.id $whereStr ORDER BY d.name, e.firstname");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

// Summary
$totalSalary = array_sum(array_column($employees,'basic_salary'));
include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-people-fill me-2 text-primary"></i>Employee Report</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="index.php">Reports</a></li><li class="breadcrumb-item active">Employees</li></ol></nav>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card mb-3 no-print"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><select name="dept" class="form-select form-select-sm"><option value="">All Departments</option><?php foreach ($departments as $d): ?><option value="<?=$d['id']?>" <?=$dept==$d['id']?'selected':''?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach(['Regular','Probationary','Contractual','Part-Time'] as $s): ?><option value="<?=$s?>" <?=$status==$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
    <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="employees.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form></div></div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-center p-3"><div class="text-muted small">Total Employees</div><div class="fw-bold fs-4"><?= count($employees) ?></div></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><div class="text-muted small">Total Basic Salary</div><div class="fw-bold fs-5 text-primary"><?= money($totalSalary) ?></div></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><div class="text-muted small">Average Salary</div><div class="fw-bold fs-5 text-success"><?= money(count($employees) ? $totalSalary/count($employees) : 0) ?></div></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><div class="text-muted small">Departments</div><div class="fw-bold fs-4"><?= count(array_unique(array_column($employees,'department_id'))) ?></div></div></div>
</div>

<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between">
        <span>Employee List — Generated: <?= date('M d, Y h:i A') ?></span>
        <span><?= count($employees) ?> records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Employee ID</th><th>Name</th><th>Department</th><th>Position</th><th>Status</th><th>Date Hired</th><th>Basic Salary</th></tr></thead>
            <tbody>
            <?php $i=1; foreach ($employees as $emp): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($emp['employee_id']) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($emp['firstname'].' '.$emp['lastname']) ?></td>
                <td><?= htmlspecialchars($emp['dept_name']??'—') ?></td>
                <td><?= htmlspecialchars($emp['pos_title']??'—') ?></td>
                <td><?= statusBadge($emp['employment_status']) ?></td>
                <td><?= formatDate($emp['date_hired']) ?></td>
                <td><?= money($emp['basic_salary']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?><tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
