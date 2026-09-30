<?php
session_start();
require_once '../config/db.php';
date_default_timezone_set('Asia/Manila');

$token = trim($_GET['token'] ?? '');
$error = $success = '';
$valid = false; $user = null;

if ($token) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE reset_token=?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        if (time() > strtotime($user['reset_token_expires'])) {
            $error = 'This reset link has expired. Please request a new one.';
        } else { $valid = true; }
    } else { $error = 'Invalid or unknown reset link.'; }
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? ''; $confirm = $_POST['confirm'] ?? '';
    if (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db->prepare('UPDATE users SET password=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?')
           ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
        $success = 'Password updated successfully!';
        $valid   = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #1a237e 0%, #1565c0 60%, #0288d1 100%);
      display: flex; align-items: center; justify-content: center;
    }
    .login-wrap { width: 100%; max-width: 440px; padding: 1rem; }
    .login-card { border: none; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    .login-logo {
      width: 64px; height: 64px; border-radius: 16px;
      background: linear-gradient(135deg, #1565c0, #42a5f5);
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem; color: #fff; margin: 0 auto 1rem;
      box-shadow: 0 4px 15px rgba(21,101,192,.4);
    }
    .btn-action { background: linear-gradient(135deg, #1565c0, #42a5f5); border: none; font-weight: 600; letter-spacing: .3px; }
    .btn-action:hover { background: linear-gradient(135deg, #0d47a1, #1565c0); }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="card-body p-4 p-md-5">

      <div class="text-center mb-4">
        <div class="login-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h4 class="fw-bold mb-1">Reset Password</h4>
        <p class="text-muted small mb-0">Set a new password for your account</p>
      </div>

      <?php if (!$token || (!$valid && !$success && empty($error))): ?>
      <div class="alert alert-danger py-2 small border-0 rounded-3">
        This reset link is invalid or has expired.
        <a href="forgot_password.php" class="alert-link">Request a new one</a>.
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small border-0 rounded-3">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        <?php if (str_contains($error, 'expired')): ?>
          <a href="forgot_password.php" class="alert-link">Try again</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success py-2 small border-0 rounded-3">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
      </div>
      <div class="d-grid mb-3">
        <a href="login.php" class="btn btn-action btn-primary py-2 text-white">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Now
        </a>
      </div>
      <?php endif; ?>

      <?php if ($valid): ?>
      <form method="POST" action="?token=<?= urlencode($token) ?>" autocomplete="off" novalidate>
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">New Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" name="password" class="form-control border-start-0 ps-0"
                   placeholder="At least 6 characters" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small text-secondary">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill text-muted"></i></span>
            <input type="password" name="confirm" class="form-control border-start-0 ps-0"
                   placeholder="Repeat password" required>
          </div>
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-action btn-primary py-2 text-white">
            <i class="bi bi-check-circle me-2"></i>Update Password
          </button>
        </div>
      </form>
      <?php endif; ?>

      <div class="text-center small text-muted">
        <a href="login.php" class="text-primary text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
      </div>

    </div>
  </div>
  <p class="text-center text-white-50 small mt-3 mb-0">
    &copy; <?= date('Y') ?> Boba House — Milk Tea POS System
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
