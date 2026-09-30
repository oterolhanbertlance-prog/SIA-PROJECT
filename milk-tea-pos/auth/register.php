<?php
session_start();
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once '../config/db.php';
require_once '../config/mailer.php';
require_once '../includes/app_url.php';

if (!empty($_SESSION['user_id'])) { header('Location: ' . appUrl('index.php')); exit; }

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'customer';
    // Staff roles are issued only by HRMS recruitment. Public registration
    // can create a customer account, never a POS administrator.
    $allowed  = ['customer'];
    if (!$username || !$email || !$password) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, $allowed)) {
        $error = 'Invalid role.';
    } else {
        $db = getDB();
        $ex = $db->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $ex->execute([$username, $email]);
        if ($ex->fetch()) {
            $error = 'Username or email already taken.';
        } else {
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));
            $db->prepare('INSERT INTO users (username,email,password,role,verification_token) VALUES (?,?,?,?,?)')
               ->execute([$username, $email, $hash, $role, $token]);
            sendEmail($email, 'Verify Your Account — Boba House', "Hi $username,\n\nVerification token: $token");
            $success = 'Account created! Check your email to verify before logging in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — Boba House POS</title>
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
        <div class="login-logo"><i class="bi bi-person-plus-fill"></i></div>
        <h4 class="fw-bold mb-1">Create Account</h4>
        <p class="text-muted small mb-0">Boba House POS — New Registration</p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small border-0 rounded-3">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert alert-success py-2 small border-0 rounded-3">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
      </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" autocomplete="off" novalidate>
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">Username</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
            <input type="text" name="username" class="form-control border-start-0 ps-0"
                   placeholder="Choose a username" required
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control border-start-0 ps-0"
                   placeholder="you@example.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" name="password" class="form-control border-start-0 ps-0"
                   placeholder="At least 6 characters" required>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small text-secondary">Role</label>
          <select name="role" class="form-select">
             <option value="customer">Customer</option>
          </select>
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-action btn-primary py-2 text-white">
            <i class="bi bi-person-check me-2"></i>Create Account
          </button>
        </div>
      </form>
      <?php endif; ?>

      <div class="text-center small text-muted">
        Already have an account?
        <a href="login.php" class="text-primary fw-semibold text-decoration-none">Sign in</a>
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
