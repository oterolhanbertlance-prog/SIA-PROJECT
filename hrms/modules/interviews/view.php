<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT i.*, a.firstname, a.lastname, a.email, a.phone, j.title as job_title FROM interviews i JOIN applicants a ON i.applicant_id=a.id LEFT JOIN job_postings j ON a.job_id=j.id WHERE i.id=?");
$stmt->execute([$id]);
$iv = $stmt->fetch();
if (!$iv) { header('Location: list.php?error=Not+found'); exit; }

// Handle result update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])) {
    if (verifyCSRF($_POST['csrf_token']??'')) {
        $result = sanitize($_POST['result']??'');
        $notes  = sanitize($_POST['notes']??'');
        $status = $result !== 'pending' ? 'completed' : 'scheduled';
        $db->prepare("UPDATE interviews SET result=?, notes=?, status=? WHERE id=?")->execute([$result,$notes,$status,$id]);
        // Update applicant status
        $successMsg = 'Result updated';
        if ($result === 'passed') {
            $db->prepare("UPDATE applicants SET status='passed' WHERE id=?")->execute([$iv['applicant_id']]);
            $deployed = autoCreateEmployeeFromApplicant($iv['applicant_id']);
            if ($deployed) {
                $successMsg = "Applicant accepted! Employee account auto-created — Username: {$deployed['username']} / Temporary password: {$deployed['password']} (share these with the new hire).";
            }
        }
        elseif ($result === 'failed') $db->prepare("UPDATE applicants SET status='failed' WHERE id=?")->execute([$iv['applicant_id']]);
        auditLog('UPDATE','Interviews',"Interview #$id result updated to $result");
        header("Location: view.php?id=$id&success=" . urlencode($successMsg));
        exit;
    }
}

$pageTitle = 'Interview Details';
include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success alert-auto-dismiss"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<div class="page-header">
    <div><h4><i class="bi bi-calendar-check me-2 text-primary"></i>Interview Details</h4></div>
    <div class="d-flex gap-2">
        <?php if (($iv['approval_status'] ?? 'pending') === 'pending' && hasRole('admin')): ?>
        <a href="approve.php?id=<?=$id?>&action=approved" class="btn btn-success" onclick="return confirmAction(this.href, 'Approve this interview and forward to HR for final sign-off?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check me-1"></i>Approve &amp; Forward to HR</a>
        <a href="approve.php?id=<?=$id?>&action=rejected" class="btn btn-danger" onclick="return confirmAction(this.href, 'Reject and cancel this interview?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x me-1"></i>Reject</a>
        <?php elseif (in_array($iv['approval_status'] ?? 'pending', ['pending','admin_approved']) && hasRole('hr')): ?>
        <a href="approve.php?id=<?=$id?>&action=approved" class="btn btn-success" onclick="return confirmAction(this.href, 'Give final HR approval for this interview?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Approve'})"><i class="bi bi-check-all me-1"></i>Final Approve</a>
        <a href="approve.php?id=<?=$id?>&action=rejected" class="btn btn-danger" onclick="return confirmAction(this.href, 'Reject and cancel this interview?', {icon:'warning', confirmColor:'#ef233c', confirmText:'Yes, Reject'})"><i class="bi bi-x me-1"></i>Reject</a>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print Schedule</button>
        <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold">Interview Information</div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Applicant</td><td class="fw-semibold"><?= htmlspecialchars($iv['firstname'].' '.$iv['lastname']) ?></td></tr>
                    <tr><td class="text-muted">Job Position</td><td><?= htmlspecialchars($iv['job_title']??'—') ?></td></tr>
                    <tr><td class="text-muted">Interview Type</td><td><?= statusBadge($iv['interview_type']) ?></td></tr>
                    <tr><td class="text-muted">Date & Time</td><td class="fw-semibold"><?= formatDateTime($iv['interview_date']) ?></td></tr>
                    <tr><td class="text-muted">Interviewer</td><td><?= htmlspecialchars($iv['interviewer_name']??'—') ?></td></tr>
                    <tr><td class="text-muted">Mode</td><td><?= statusBadge($iv['mode']) ?></td></tr>
                    <tr><td class="text-muted">Location</td><td><?= htmlspecialchars($iv['location']??'—') ?></td></tr>
                    <tr><td class="text-muted">Status</td><td><?= statusBadge($iv['status']) ?></td></tr>
                    <tr><td class="text-muted">Result</td><td><?= statusBadge($iv['result']) ?></td></tr>
                    <tr><td class="text-muted">Approval</td><td><?= statusBadge($iv['approval_status'] ?? 'pending') ?></td></tr>
                    <?php if ($iv['notes']): ?><tr><td class="text-muted">Notes</td><td><?= nl2br(htmlspecialchars($iv['notes'])) ?></td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold">Update Interview Result</div>
            <div class="card-body">
                <form method="POST" data-validate>
                    <?= csrfField() ?>
                    <input type="hidden" name="update_result" value="1">
                    <div class="mb-3">
                        <label class="form-label">Result</label>
                        <select name="result" class="form-select">
                            <?php foreach(['pending','passed','failed','no_show','rescheduled'] as $r): ?><option value="<?=$r?>" <?=$iv['result']==$r?'selected':''?>><?= ucwords(str_replace('_',' ',$r)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes / Feedback</label>
                        <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($iv['notes']??'') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Result</button>
                </form>
            </div>
        </div>
        <?php if ($iv['result'] === 'passed'): ?>
        <div class="card mt-3 border-success">
            <div class="card-body">
                <h6 class="text-success"><i class="bi bi-check-circle me-2"></i>Applicant Passed!</h6>
                <p class="text-muted small mb-2">You can now proceed to deploy this applicant as an employee.</p>
                <a href="../deployment/deploy_applicant.php?applicant_id=<?=$iv['applicant_id']?>" class="btn btn-success btn-sm"><i class="bi bi-person-badge me-1"></i>Deploy as Employee</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
