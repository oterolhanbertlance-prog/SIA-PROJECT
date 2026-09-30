<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Leave Management';
$db = getDB();

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$type   = (int)($_GET['type'] ?? 0);
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15; $offset = ($page-1)*$perPage;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(e.firstname LIKE ? OR e.lastname LIKE ? OR e.employee_id LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($status) { $where[] = "lr.status=?"; $params[] = $status; }
if ($type)   { $where[] = "lr.leave_type_id=?"; $params[] = $type; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id JOIN leave_types lt ON lr.leave_type_id=lt.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT lr.*, e.firstname, e.lastname, e.employee_id as emp_code, lt.name as type_name FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id JOIN leave_types lt ON lr.leave_type_id=lt.id $whereStr ORDER BY lr.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status='active' ORDER BY name")->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-calendar3-range-fill me-2 text-primary"></i>Leave Management</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Leave</li></ol></nav>
    </div>
    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Leave Request</a>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><div class="search-box w-100"><i class="bi bi-search search-icon"></i><input type="text" name="search" class="form-control" placeholder="Search employee..." value="<?= htmlspecialchars($search) ?>"></div></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach(['pending','admin_approved','approved','rejected','cancelled'] as $s): ?><option value="<?=$s?>" <?=$status==$s?'selected':''?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><select name="type" class="form-select form-select-sm"><option value="">All Types</option><?php foreach ($leaveTypes as $lt): ?><option value="<?=$lt['id']?>" <?=$type==$lt['id']?'selected':''?>><?= htmlspecialchars($lt['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="list.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Filed</th><th class="text-center">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($leaves)): ?><tr><td colspan="9" class="text-center py-4 text-muted">No leave requests found.</td></tr><?php endif; ?>
            <?php foreach ($leaves as $lr): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?= htmlspecialchars($lr['firstname'].' '.$lr['lastname']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($lr['emp_code']) ?></div>
                </td>
                <td><?= htmlspecialchars($lr['type_name']) ?></td>
                <td><?= formatDate($lr['start_date']) ?></td>
                <td><?= formatDate($lr['end_date']) ?></td>
                <td><span class="badge bg-secondary"><?= $lr['total_days'] ?></span></td>
                <td><span class="small text-muted"><?= htmlspecialchars(substr($lr['reason']??'',0,40)) ?><?= strlen($lr['reason']??'')>40?'...':'' ?></span></td>
                <td><?= statusBadge($lr['status']) ?></td>
                <td><?= formatDate($lr['created_at']) ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="view.php?id=<?=$lr['id']?>" class="btn btn-sm btn-info py-0 px-2" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($lr['status'] === 'pending' && hasRole('admin')): ?>
                        <a href="approve.php?id=<?=$lr['id']?>&action=approved" class="btn btn-sm btn-success py-0 px-2" title="Approve &amp; forward to HR" onclick="return confirmAction(this.href, 'Approve this leave and forward to HR for final sign-off?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check"></i></a>
                        <a href="approve.php?id=<?=$lr['id']?>&action=rejected" class="btn btn-sm btn-danger py-0 px-2" title="Reject" onclick="return confirmAction(this.href, 'Reject this leave?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x"></i></a>
                        <?php elseif (in_array($lr['status'], ['pending','admin_approved']) && hasRole('hr')): ?>
                        <a href="approve.php?id=<?=$lr['id']?>&action=approved" class="btn btn-sm btn-success py-0 px-2" title="Final Approve" onclick="return confirmAction(this.href, 'Give final HR approval for this leave?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check-all"></i></a>
                        <a href="approve.php?id=<?=$lr['id']?>&action=rejected" class="btn btn-sm btn-danger py-0 px-2" title="Reject" onclick="return confirmAction(this.href, 'Reject this leave?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
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
        <?= paginate($total,$perPage,$page,'list.php?'.http_build_query(['search'=>$search,'status'=>$status,'type'=>$type])) ?>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
