<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$pageTitle = 'Change Password';
$db = getDB();
$errors = [];
$success = '';

$cu = currentUser();
// Only administrators may reset another user's password. Other roles may
// still change their own password from this page.
$targetUserId = hasRole('admin') && isset($_GET['user_id']) ? (int)$_GET['user_id'] : $cu['id'];

$targetStmt = $db->prepare("SELECT * FROM users WHERE id=?");
$targetStmt->execute([$targetUserId]);
$targetUser = $targetStmt->fetch();
if (!$targetUser) { header('Location: ' . SITE_URL . 'dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token']??'')) { $errors[] = 'Invalid submission.'; }
    else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // If an administrator is resetting someone else's password, skip
        // current-password verification.
        $isAdminReset = hasRole('admin') && $targetUserId !== $cu['id'];

        if (!$isAdminReset && !password_verify($current, $targetUser['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($new !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$targetUserId]);
            auditLog('UPDATE','Users',"Password changed for user: {$targetUser['username']}");
            $success = 'Password changed successfully!';
        }
    }
}

include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-key me-2 text-primary"></i>Change Password</h4></div>
    <a href="<?= hasRole('admin') ? 'list.php' : SITE_URL.'dashboard.php' ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="max-width:450px;">
    <div class="card-body">
        <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        <p class="text-muted small mb-3">Changing password for: <strong><?= htmlspecialchars($targetUser['username']) ?></strong></p>
        <form method="POST" data-validate>
            <?= csrfField() ?>
            <?php if (!(hasRole('admin') && $targetUserId !== $cu['id'])): ?>
            <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <?php endif; ?>
            <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6" placeholder="At least 6 characters"></div>
            <div class="mb-4"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-shield-check me-1"></i>Change Password</button>
        </form>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
