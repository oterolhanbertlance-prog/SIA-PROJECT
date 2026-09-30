<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');
$pageTitle = 'Leave Report';
$db = getDB();
$year = (int)($_GET['year'] ?? date('Y'));
$stmt = $db->prepare("SELECT lr.*, e.firstname, e.lastname, e.employee_id as emp_code, lt.name as type_name FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id JOIN leave_types lt ON lr.leave_type_id=lt.id WHERE YEAR(lr.created_at)=? ORDER BY lr.created_at DESC");
$stmt->execute([$year]);
$leaves = $stmt->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header"><div><h4><i class="bi bi-calendar3-range-fill me-2 text-primary"></i>Leave Report</h4></div><div class="d-flex gap-2 no-print"><button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div></div>
<div class="card mb-3 no-print"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end"><div class="col-md-2"><select name="year" class="form-select form-select-sm"><?php for($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?></select></div><div class="col-auto"><button class="btn btn-primary btn-sm">Generate</button></div></form></div></div>
<div class="card"><div class="card-header fw-semibold">Leave Requests — <?= $year ?></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>#</th><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Filed</th></tr></thead><tbody>
<?php $i=1; foreach ($leaves as $lr): ?><tr><td><?=$i++?></td><td><?= htmlspecialchars($lr['firstname'].' '.$lr['lastname']) ?> <small class="text-muted">(<?= htmlspecialchars($lr['emp_code']) ?>)</small></td><td><?= htmlspecialchars($lr['type_name']) ?></td><td><?= formatDate($lr['start_date']) ?></td><td><?= formatDate($lr['end_date']) ?></td><td><?= $lr['total_days'] ?></td><td><?= statusBadge($lr['status']) ?></td><td><?= formatDate($lr['created_at']) ?></td></tr><?php endforeach; ?>
<?php if(empty($leaves)): ?><tr><td colspan="8" class="text-center text-muted py-4">No leave records.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<?php include '../../includes/footer.php'; ?>
