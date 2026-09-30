<?php
session_start();
require_once '../config/db.php';

$token = trim($_GET['token'] ?? '');
$msg = ''; $ok = false;

if (!$token) {
    $msg = 'Invalid verification link.';
} else {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE verification_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        $msg = 'This verification link is invalid or has already been used.';
    } elseif ($user['email_verified']) {
        $msg = 'Your email is already verified.'; $ok = true;
    } else {
        $db->prepare('UPDATE users SET email_verified=1, verification_token=NULL WHERE id=?')
           ->execute([$user['id']]);
        $msg = 'Email verified! Your account is now active.'; $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify Email — Boba House POS</title>
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
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="card-body p-4 p-md-5 text-center">

      <div class="login-logo mx-auto"><i class="bi bi-envelope-check-fill"></i></div>
      <h4 class="fw-bold mb-1">Email Verification</h4>
      <p class="text-muted small mb-4">Boba House POS</p>

      <div class="mb-3">
        <?php if ($ok): ?>
          <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <?php else: ?>
          <i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i>
        <?php endif; ?>
      </div>

      <p class="mb-4"><?= htmlspecialchars($msg) ?></p>

      <?php if ($ok): ?>
      <div class="d-grid mb-3">
        <a href="login.php" class="btn btn-primary py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Now
        </a>
      </div>
      <?php else: ?>
      <div class="d-grid mb-3">
        <a href="resend_verification.php" class="btn btn-outline-primary py-2 fw-semibold">
          <i class="bi bi-envelope me-2"></i>Resend Verification Email
        </a>
      </div>
      <?php endif; ?>

      <div class="small text-muted">
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
