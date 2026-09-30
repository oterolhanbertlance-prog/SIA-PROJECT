<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'Registration Approvals';
$db = getDB();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        header('Location: approvals.php?error=Invalid+request'); exit;
    }

    $userId  = (int)($_POST['user_id']  ?? 0);
    $action  = sanitize($_POST['action'] ?? '');
    $reason  = sanitize($_POST['reason'] ?? '');

    if ($userId && in_array($action, ['approve','reject'])) {
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status IN ('pending','admin_approved')");
        $userStmt->execute([$userId]);
        $pendingUser = $userStmt->fetch();
        $cu = currentUser();

        if ($pendingUser) {
            if ($action === 'approve') {
                if (hasRole('hr')) {
                    // HR gives the final, official approval — activates the account
                    $db->prepare(
                        "UPDATE users SET status = 'active', approved_by = ?, approved_at = NOW() WHERE id = ?"
                    )->execute([$cu['id'], $userId]);

                    auditLog('UPDATE', 'Users',
                        "Officially approved registration for {$pendingUser['firstname']} {$pendingUser['lastname']} (@{$pendingUser['username']}) as {$pendingUser['role']}");

                    header('Location: approvals.php?success=' . urlencode("{$pendingUser['firstname']} {$pendingUser['lastname']} has been approved and can now log in."));
                } elseif (hasRole('admin') && $pendingUser['status'] === 'pending') {
                    // Admin's first-level approval — forwards to HR for final sign-off
                    $db->prepare(
                        "UPDATE users SET status = 'admin_approved', admin_approved_by = ?, admin_approved_at = NOW() WHERE id = ?"
                    )->execute([$cu['id'], $userId]);

                    auditLog('UPDATE', 'Users',
                        "Registration for {$pendingUser['firstname']} {$pendingUser['lastname']} (@{$pendingUser['username']}) approved by Admin, forwarded to HR");

                    header('Location: approvals.php?success=' . urlencode("Approved. Forwarded to HR for final sign-off."));
                } else {
                    header('Location: approvals.php?error=' . urlencode("This request is already forwarded to HR."));
                }
            } else {
                $db->prepare(
                    "UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?"
                )->execute([$reason, $cu['id'], $userId]);

                auditLog('UPDATE', 'Users',
                    "Rejected registration for {$pendingUser['firstname']} {$pendingUser['lastname']} (@{$pendingUser['username']}). Reason: $reason");

                header('Location: approvals.php?success=' . urlencode("{$pendingUser['firstname']} {$pendingUser['lastname']}'s registration has been rejected."));
            }
            exit;
        }
    }
    header('Location: approvals.php');
    exit;
}

// Fetch pending registrations (awaiting Admin's first review, or awaiting HR's final sign-off)
$pending = $db->query(
    "SELECT * FROM users WHERE status IN ('pending','admin_approved') ORDER BY created_at ASC"
)->fetchAll();

// Fetch recently processed (last 10)
$recent = $db->query(
    "SELECT u.*, approver.username as approver_name
     FROM users u
     LEFT JOIN users approver ON u.approved_by = approver.id
     WHERE u.status IN ('active','rejected') AND u.approved_at IS NOT NULL
     ORDER BY u.approved_at DESC LIMIT 10"
)->fetchAll();

include '../../includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['success']) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-auto-dismiss">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-person-check-fill me-2 text-primary"></i>Registration Approvals</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Approvals</li>
            </ol>
        </nav>
    </div>
    <?php if (count($pending) > 0): ?>
    <span class="badge bg-danger fs-6"><?= count($pending) ?> Pending</span>
    <?php endif; ?>
</div>

<!-- Pending Registrations -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hourglass-split text-warning"></i>
        <span class="fw-semibold">Pending Requests</span>
        <span class="badge bg-warning text-dark ms-auto"><?= count($pending) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pending)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-check2-all fs-2 d-block mb-2 text-success"></i>
            No pending registration requests. All caught up!
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Requested Role</th>
                        <th>Registered At</th>
                        <th>Stage</th>
                        <th class="text-center" style="min-width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                                 style="width:38px;height:38px;font-size:.9rem;background:linear-gradient(135deg,#4361ee,#4cc9f0);flex-shrink:0;">
                                <?= htmlspecialchars(strtoupper(substr($u['firstname'] ?? $u['username'], 0, 1))) ?>
                            </div>
                            <div>
                                <div class="fw-semibold small">
                                    <?= htmlspecialchars(trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''))) ?: '—' ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                    <td>
                        <?php
                        $roleColors = ['admin'=>'danger','hr'=>'primary','employee'=>'success'];
                        $rc = $roleColors[$u['role']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $rc ?>-subtle text-<?= $rc ?> border border-<?= $rc ?>-subtle px-2 py-1">
                            <i class="bi bi-<?= $u['role']==='admin'?'shield-lock':($u['role']==='hr'?'people':'person-badge') ?> me-1"></i>
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </td>
                    <td><small class="text-muted"><?= formatDateTime($u['created_at']) ?></small></td>
                    <td><?= statusBadge($u['status'] === 'pending' ? 'Awaiting Admin' : 'Awaiting HR') ?></td>
                    <td class="text-center">
                        <div class="d-flex gap-2 justify-content-center">
                        <?php if ($u['status'] === 'admin_approved' && hasRole('admin') && !hasRole('hr')): ?>
                            <span class="text-muted small">Forwarded to HR</span>
                        <?php else: ?>
                            <!-- Approve (Admin's first-level, or HR's final approval) -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirmSubmit(this, '<?= $u['status']==='admin_approved' ? 'Give final HR approval for' : 'Approve' ?> <?= htmlspecialchars(addslashes(trim(($u['firstname']??'').' '.($u['lastname']??'')))) ?> as <?= strtoupper($u['role']) ?>?', {icon:'question', confirmColor:'#2a9d8f'})">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action"  value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-3">
                                    <i class="bi bi-check-lg me-1"></i><?= ($u['status']==='admin_approved' || hasRole('hr')) ? 'Final Approve' : 'Approve' ?>
                                </button>
                            </form>
                            <!-- Reject -->
                            <button type="button" class="btn btn-danger btn-sm px-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal"
                                    data-userid="<?= $u['id'] ?>"
                                    data-name="<?= htmlspecialchars(trim(($u['firstname']??'').' '.($u['lastname']??'')), ENT_QUOTES) ?>">
                                <i class="bi bi-x-lg me-1"></i>Reject
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recently Processed -->
<div class="card">
    <div class="card-header fw-semibold">
        <i class="bi bi-clock-history me-2 text-secondary"></i>Recently Processed
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent)): ?>
        <div class="text-center text-muted py-4">No processed registrations yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Rejection Reason</th><th>Processed By</th><th>At</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $u): ?>
                <tr>
                    <td><?= htmlspecialchars(trim(($u['firstname']??'').' '.($u['lastname']??''))) ?: htmlspecialchars($u['username']) ?></td>
                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><?= statusBadge($u['role']) ?></td>
                    <td><?= statusBadge($u['status']) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($u['rejection_reason'] ?? '—') ?></small></td>
                    <td><small><?= htmlspecialchars($u['approver_name'] ?? '—') ?></small></td>
                    <td><small class="text-muted"><?= formatDateTime($u['approved_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" id="rejectUserId">
                <input type="hidden" name="action"  value="reject">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Reject Registration</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Rejecting: <strong id="rejectName"></strong></p>
                    <label class="form-label fw-semibold small">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control form-control-sm" rows="3"
                              required placeholder="e.g. Not a current employee, duplicate account..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('rejectUserId').value = btn.dataset.userid;
    document.getElementById('rejectName').textContent = btn.dataset.name;
});
</script>
JS;
include '../../includes/footer.php';
?>
