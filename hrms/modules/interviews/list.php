<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Interviews';
$db = getDB();

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$type   = sanitize($_GET['type'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15; $offset = ($page-1)*$perPage;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(a.firstname LIKE ? OR a.lastname LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
if ($status) { $where[] = "i.status=?"; $params[] = $status; }
if ($type)   { $where[] = "i.interview_type=?"; $params[] = $type; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM interviews i JOIN applicants a ON i.applicant_id=a.id $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT i.*, a.firstname, a.lastname, a.email, j.title as job_title FROM interviews i JOIN applicants a ON i.applicant_id=a.id LEFT JOIN job_postings j ON a.job_id=j.id $whereStr ORDER BY i.interview_date DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$interviews = $stmt->fetchAll();

include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Interviews</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Interviews</li></ol></nav>
    </div>
    <a href="schedule.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Schedule Interview</a>
</div>

<div class="card mb-3"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><div class="search-box w-100"><i class="bi bi-search search-icon"></i><input type="text" name="search" class="form-control" placeholder="Search applicant..." value="<?= htmlspecialchars($search) ?>"></div></div>
    <div class="col-md-2"><select name="type" class="form-select form-select-sm"><option value="">All Types</option><?php foreach(['initial','final','technical','hr'] as $t): ?><option value="<?=$t?>" <?=$type==$t?'selected':''?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach(['scheduled','completed','cancelled','rescheduled'] as $s): ?><option value="<?=$s?>" <?=$status==$s?'selected':''?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="list.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form></div></div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Applicant</th><th>Job</th><th>Type</th><th>Date & Time</th><th>Interviewer</th><th>Mode</th><th>Result</th><th>Status</th><th>Approval</th><th class="text-center">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($interviews)): ?><tr><td colspan="10" class="text-center py-4 text-muted">No interviews found.</td></tr><?php endif; ?>
            <?php foreach ($interviews as $iv): ?>
            <tr>
                <td><div class="fw-semibold small"><?= htmlspecialchars($iv['firstname'].' '.$iv['lastname']) ?></div><div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($iv['email']) ?></div></td>
                <td><small><?= htmlspecialchars($iv['job_title']??'—') ?></small></td>
                <td><?= statusBadge($iv['interview_type']) ?></td>
                <td><div class="fw-semibold small"><?= formatDateTime($iv['interview_date']) ?></div></td>
                <td><?= htmlspecialchars($iv['interviewer_name']??'—') ?></td>
                <td><?= statusBadge($iv['mode']) ?></td>
                <td><?= statusBadge($iv['result']) ?></td>
                <td><?= statusBadge($iv['status']) ?></td>
                <td><?= statusBadge($iv['approval_status'] ?? 'pending') ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="view.php?id=<?=$iv['id']?>" class="btn btn-sm btn-info py-0 px-2"><i class="bi bi-eye"></i></a>
                        <a href="schedule.php?edit=<?=$iv['id']?>" class="btn btn-sm btn-warning py-0 px-2"><i class="bi bi-pencil"></i></a>
                        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-printer"></i></button>
                        <?php if (($iv['approval_status'] ?? 'pending') === 'pending' && hasRole('admin')): ?>
                        <a href="approve.php?id=<?=$iv['id']?>&action=approved" class="btn btn-sm btn-success py-0 px-2" title="Approve &amp; forward to HR" onclick="return confirmAction(this.href, 'Approve this interview and forward to HR for final sign-off?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check"></i></a>
                        <a href="approve.php?id=<?=$iv['id']?>&action=rejected" class="btn btn-sm btn-danger py-0 px-2" title="Reject" onclick="return confirmAction(this.href, 'Reject and cancel this interview?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x"></i></a>
                        <?php elseif (in_array($iv['approval_status'] ?? 'pending', ['pending','admin_approved']) && hasRole('hr')): ?>
                        <a href="approve.php?id=<?=$iv['id']?>&action=approved" class="btn btn-sm btn-success py-0 px-2" title="Final Approve" onclick="return confirmAction(this.href, 'Give final HR approval for this interview?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check-all"></i></a>
                        <a href="approve.php?id=<?=$iv['id']?>&action=rejected" class="btn btn-sm btn-danger py-0 px-2" title="Reject" onclick="return confirmAction(this.href, 'Reject and cancel this interview?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x"></i></a>
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
