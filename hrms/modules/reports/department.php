<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');
$pageTitle = 'Department Report';
$db = getDB();
$stmt = $db->query("SELECT d.*, COUNT(e.id) as headcount, IFNULL(AVG(e.basic_salary),0) as avg_salary, IFNULL(SUM(e.basic_salary),0) as total_salary FROM departments d LEFT JOIN employees e ON d.id=e.department_id AND e.status='active' GROUP BY d.id ORDER BY headcount DESC");
$depts = $stmt->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header"><div><h4><i class="bi bi-building me-2 text-primary"></i>Department Report</h4></div><div class="d-flex gap-2 no-print"><button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div></div>
<div class="card"><div class="card-header fw-semibold">Department Summary</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover table-sm mb-0"><thead><tr><th>#</th><th>Department</th><th>Status</th><th>Headcount</th><th>Avg. Salary</th><th>Total Salary</th></tr></thead><tbody>
<?php $i=1; foreach ($depts as $d): ?><tr><td><?=$i++?></td><td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td><td><?= statusBadge($d['status']) ?></td><td><span class="badge bg-primary"><?= $d['headcount'] ?></span></td><td><?= money($d['avg_salary']) ?></td><td class="fw-semibold"><?= money($d['total_salary']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include '../../includes/footer.php'; ?>
