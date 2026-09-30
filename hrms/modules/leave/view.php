<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT lr.*, e.firstname, e.lastname, e.employee_id as emp_code, lt.name as type_name, u.username as approved_by_name FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id JOIN leave_types lt ON lr.leave_type_id=lt.id LEFT JOIN users u ON lr.approved_by=u.id WHERE lr.id=?");
$stmt->execute([$id]);
$lr = $stmt->fetch();
if (!$lr) { header('Location: list.php?error=Not+found'); exit; }

$pageTitle = 'Leave Request Details';
include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-calendar3-range-fill me-2 text-primary"></i>Leave Request Details</h4></div>
    <div class="d-flex gap-2">
        <?php if (hasRole('admin') && $lr['status']==='pending'): ?>
        <a href="approve.php?id=<?=$id?>&action=approved" class="btn btn-success" onclick="return confirmAction(this.href, 'Approve this leave and forward to HR for final sign-off?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check me-1"></i>Approve &amp; Forward to HR</a>
        <a href="approve.php?id=<?=$id?>&action=rejected" class="btn btn-danger" onclick="return confirmAction(this.href, 'Reject this leave?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x me-1"></i>Reject</a>
        <?php elseif (hasRole('hr') && in_array($lr['status'], ['pending','admin_approved'])): ?>
        <a href="approve.php?id=<?=$id?>&action=approved" class="btn btn-success" onclick="return confirmAction(this.href, 'Give final HR approval for this leave?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check-all me-1"></i>Final Approve</a>
        <a href="approve.php?id=<?=$id?>&action=rejected" class="btn btn-danger" onclick="return confirmAction(this.href, 'Reject this leave?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x me-1"></i>Reject</a>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="card" style="max-width:700px;" id="leavePrint">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between mb-4">
            <div><h5 class="fw-bold text-primary mb-0"><?= SITE_NAME ?></h5><div class="text-muted small">Leave Request Form</div></div>
            <div class="text-end"><div class="badge bg-secondary"><?= htmlspecialchars($lr['leave_code']) ?></div><div class="mt-1"><?= statusBadge($lr['status']) ?></div></div>
        </div>
        <hr>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="text-muted small">Employee Name</label><p class="fw-semibold"><?= htmlspecialchars($lr['firstname'].' '.$lr['lastname']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Employee ID</label><p class="fw-semibold"><?= htmlspecialchars($lr['emp_code']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Leave Type</label><p class="fw-semibold"><?= htmlspecialchars($lr['type_name']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Total Days</label><p class="fw-semibold"><?= $lr['total_days'] ?> working day(s)</p></div>
            <div class="col-md-6"><label class="text-muted small">Start Date</label><p class="fw-semibold"><?= formatDate($lr['start_date']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">End Date</label><p class="fw-semibold"><?= formatDate($lr['end_date']) ?></p></div>
            <div class="col-12"><label class="text-muted small">Reason</label><p><?= nl2br(htmlspecialchars($lr['reason']??'—')) ?></p></div>
            <?php if ($lr['file_attachment']): ?>
            <div class="col-12"><label class="text-muted small">Attachment</label><p><a href="<?=SITE_URL?>uploads/documents/<?= htmlspecialchars($lr['file_attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip me-1"></i>View Attachment</a></p></div>
            <?php endif; ?>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-md-6"><label class="text-muted small">Filed On</label><p><?= formatDateTime($lr['created_at']) ?></p></div>
            <?php if ($lr['approved_at']): ?>
            <div class="col-md-6"><label class="text-muted small">Processed On</label><p><?= formatDateTime($lr['approved_at']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Processed By</label><p><?= htmlspecialchars($lr['approved_by_name']??'—') ?></p></div>
            <?php endif; ?>
            <?php if ($lr['remarks']): ?>
            <div class="col-12"><label class="text-muted small">Remarks</label><p><?= nl2br(htmlspecialchars($lr['remarks'])) ?></p></div>
            <?php endif; ?>
        </div>

        <div class="row mt-5">
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">Employee Signature</div></div>
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">HR Officer</div></div>
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">Approved By</div></div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
