<?php
session_start();
require_once '../includes/app_url.php';
require_once '../config/db.php';
require_once '../config/mailer.php';

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $db    = getDB();
    $stmt  = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user  = $stmt->fetch();
    if (!$user) {
        $error = 'No account found with that email address.';
    } else {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $db->prepare('UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?')
           ->execute([$token, $expires, $user['id']]);
        $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . appUrl('auth/reset_password.php?token=' . urlencode($token));
        sendEmail($user['email'], 'Password Reset — Boba House POS',
            '<h2>Password Reset</h2><p>Hi ' . htmlspecialchars($user['username']) . ',</p>' .
            '<p><a href="' . $link . '">' . $link . '</a> (expires in 1 hour)</p>');
        $success = 'Password reset link sent! Check the dev mail log.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — Boba House POS</title>
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
        <div class="login-logo"><i class="bi bi-key-fill"></i></div>
        <h4 class="fw-bold mb-1">Forgot Password</h4>
        <p class="text-muted small mb-0">Enter your email to receive a reset link</p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small border-0 rounded-3">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success py-2 small border-0 rounded-3">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      </div>
      <div class="d-grid mb-3">
        <a href="../mail_log.php" class="btn btn-action btn-primary py-2 text-white">
          <i class="bi bi-envelope-open-text me-2"></i>View Dev Mail Log
        </a>
      </div>
      <?php else: ?>
      <form method="POST" autocomplete="off" novalidate>
        <div class="mb-4">
          <label class="form-label fw-semibold small text-secondary">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control border-start-0 ps-0"
                   placeholder="Enter your registered email" required autofocus>
          </div>
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-action btn-primary py-2 text-white">
            <i class="bi bi-send me-2"></i>Send Reset Link
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
