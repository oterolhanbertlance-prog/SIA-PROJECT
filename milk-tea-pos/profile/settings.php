<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';

requireLogin('../auth/login.php');

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $db->prepare('SELECT id, username, email, password, role, email_verified, is_active FROM users WHERE id=? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !(int)$user['is_active']) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . appUrl('auth/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '') {
        $error = 'Username and email are required.';
    } elseif (strlen($username) < 3 || strlen($username) > 100) {
        $error = 'Username must be between 3 and 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $duplicate = $db->prepare('SELECT id FROM users WHERE (username=? OR email=?) AND id<>? LIMIT 1');
        $duplicate->execute([$username, $email, $userId]);

        if ($duplicate->fetch()) {
            $error = 'That username or email is already in use.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif ($newPassword !== '' && !password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            if ($newPassword !== '') {
                $update = $db->prepare('UPDATE users SET username=?, email=?, password=? WHERE id=?');
                $update->execute([$username, $email, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $update = $db->prepare('UPDATE users SET username=?, email=? WHERE id=?');
                $update->execute([$username, $email, $userId]);
            }

            $_SESSION['username'] = $username;
            $user['username'] = $username;
            $user['email'] = $email;
            $success = 'Profile settings saved successfully.';
        }
    }
}

$root = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profile Settings — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title">
        <i class="bi bi-person-gear me-2" style="color:var(--boba-amber)"></i>Profile Settings
      </h1>
    </div>
    <div class="content-area">
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-xl-8">
          <div class="card">
            <div class="card-header">
              <i class="bi bi-person-vcard me-2" style="color:var(--boba-amber)"></i>Account Information
            </div>
            <div class="card-body">
              <form method="POST" autocomplete="off">
                <div class="mb-3">
                  <label for="username" class="form-label fw-semibold">Username</label>
                  <input id="username" type="text" name="username" class="form-control"
                         value="<?= htmlspecialchars($user['username']) ?>" required maxlength="100">
                </div>
                <div class="mb-3">
                  <label for="email" class="form-label fw-semibold">Email Address</label>
                  <input id="email" type="email" name="email" class="form-control"
                         value="<?= htmlspecialchars($user['email'] ?? '') ?>" required maxlength="255">
                  <div class="form-text">
                    <?php if ((int)$user['email_verified']): ?>
                      <i class="bi bi-check-circle-fill text-success me-1"></i>Email verified
                    <?php else: ?>
                      <i class="bi bi-exclamation-circle text-warning me-1"></i>Email not verified
                    <?php endif; ?>
                  </div>
                </div>
                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Change Password <span class="text-muted fw-normal">(optional)</span></h6>
                <div class="mb-3">
                  <label for="current_password" class="form-label fw-semibold">Current Password</label>
                  <input id="current_password" type="password" name="current_password" class="form-control"
                         autocomplete="current-password">
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <input id="new_password" type="password" name="new_password" class="form-control"
                           autocomplete="new-password" minlength="6">
                  </div>
                  <div class="col-md-6">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                    <input id="confirm_password" type="password" name="confirm_password" class="form-control"
                           autocomplete="new-password" minlength="6">
                  </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                  <button type="submit" class="btn btn-boba">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                  </button>
                  <a href="<?= $root ?>index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Account Details</div>
            <div class="card-body">
              <div class="mb-3">
                <div class="text-muted small">Role</div>
                <div class="fw-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))) ?></div>
              </div>
              <div>
                <div class="text-muted small">Account Status</div>
                <span class="badge bg-success">Active</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>