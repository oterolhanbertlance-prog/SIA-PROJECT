<?php
session_start();
require_once '../config/db.php';
require_once '../config/db_connect.php';
require_once '../includes/app_url.php';
require_once '../includes/customer_cart.php';
require_once '../../hrms/integration/pos_common.php';

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';
    header('Location: ' . match($role) {
        'customer' => appUrl('customer/index.php'),
        'cashier' => appUrl('staff/order_queue.php'),
        'super_admin' => appUrl('admin/dashboard.php'),
        'inventory_staff' => appUrl('admin/products.php'),
        default       => appUrl('auth/login.php'),
    }); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username=? OR email=?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // A hired HRMS identity can sign in to POS with the same credentials.
        // The first POS login repairs/creates the shadow record if needed.
        if (!$user || !password_verify($password, $user['password'])) {
            try {
                $hrmsStmt = getHrmsDB()->prepare(
                    "SELECT * FROM users WHERE (username=? OR email=?) AND status='active' LIMIT 1"
                );
                $hrmsStmt->execute([$username, $username]);
                $hrmsUser = $hrmsStmt->fetch();
                $hrmsRole = $hrmsUser ? posRoleForHrmsUser($hrmsUser) : null;
                if ($hrmsUser && $hrmsRole && password_verify($password, $hrmsUser['password'])) {
                    syncHrmsUserToPos($db, $hrmsUser, $hrmsRole, $hrmsUser['employee_id'] ? (int)$hrmsUser['employee_id'] : null);
                    $stmt->execute([$username, $username]);
                    $user = $stmt->fetch();
                }
            } catch (Throwable $e) {
                // Keep the normal POS error message when the HRMS database is offline.
            }
        }

        // Also refresh an already-existing POS shadow record when HRMS has
        // changed the linked role or profile since the last sign-in.
        if ($user && password_verify($password, $user['password'])) {
            try {
                $hrmsStmt = getHrmsDB()->prepare(
                    "SELECT * FROM users WHERE (username=? OR email=?) AND status='active' LIMIT 1"
                );
                $hrmsStmt->execute([$user['username'], $user['email'] ?? '']);
                $hrmsUser = $hrmsStmt->fetch();
                $hrmsRole = $hrmsUser ? posRoleForHrmsUser($hrmsUser) : null;
                if ($hrmsUser && $hrmsRole && hash_equals((string)$hrmsUser['password'], (string)$user['password'])) {
                    syncHrmsUserToPos($db, $hrmsUser, $hrmsRole, $hrmsUser['employee_id'] ? (int)$hrmsUser['employee_id'] : null);
                    $stmt->execute([$user['username'], $user['email'] ?? '']);
                    $user = $stmt->fetch();
                }
            } catch (Throwable $e) {
                // POS remains usable when the separate HRMS database is offline.
            }
        }

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Contact the administrator.';
            } elseif (!$user['email_verified']) {
                $error = 'Please verify your email. <a href="resend_verification.php?email='.urlencode($user['email']).'">Resend link</a>.';
            } else {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                if ($user['role'] === 'customer') {
                    // Restore this customer's saved cart and refresh current prices/availability.
                    $_SESSION['cart'] = loadCustomerCart($db, (int)$user['id']);
                } else {
                    unset($_SESSION['cart']);
                }
                header('Location: ' . match($user['role']) {
                    'customer' => appUrl('customer/index.php'),
        'cashier' => appUrl('staff/order_queue.php'),
                    'super_admin' => appUrl('admin/dashboard.php'),
                    'inventory_staff' => appUrl('admin/products.php'),
                    default       => appUrl('auth/login.php'),
                }); exit;
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
$fromHrms = ($_GET['from'] ?? '') === 'hrms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — Boba House POS</title>
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
    .btn-login { background: linear-gradient(135deg, #1565c0, #42a5f5); border: none; font-weight: 600; letter-spacing: .3px; }
    .btn-login:hover { background: linear-gradient(135deg, #0d47a1, #1565c0); }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="card-body p-4 p-md-5">

      <div class="text-center mb-4">
        <div class="login-logo">&#9749;</div>
        <h4 class="fw-bold mb-1">Boba House POS</h4>
        <p class="text-muted small mb-0">Sign in to your account</p>
      </div>

      <?php if ($fromHrms): ?>
      <div class="alert alert-info py-2 small border-0 rounded-3 mb-3">
        <i class="bi bi-link-45deg me-1"></i>
        Arrived from <strong>HRMS</strong> — sign in with your POS account to continue.
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small border-0 rounded-3">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
            <input type="text" name="username" class="form-control border-start-0 ps-0"
                   placeholder="Enter username or email" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small text-secondary">Password</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" name="password" id="pwInput"
                   class="form-control border-start-0 border-end-0 ps-0"
                   placeholder="Enter your password" required>
            <button type="button" class="btn btn-light border" onclick="togglePw()" title="Show/hide">
              <i class="bi bi-eye" id="pwEye"></i>
            </button>
          </div>
          <div class="text-end mt-1">
            <a href="forgot_password.php" class="small text-primary text-decoration-none">Forgot password?</a>
          </div>
        </div>
        <div class="d-grid mb-3">
          <button type="submit" class="btn btn-login btn-primary py-2 text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login
          </button>
        </div>
      </form>

      <div class="text-center small text-muted">
        Don't have an account?
        <a href="register.php" class="text-primary fw-semibold text-decoration-none">Register</a>
      </div>

    </div>
  </div>
  <p class="text-center text-white-50 small mt-3 mb-0">
    &copy; <?= date('Y') ?> Boba House — Milk Tea POS System
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw() {
    const inp = document.getElementById('pwInput');
    const eye = document.getElementById('pwEye');
    if (inp.type === 'password') { inp.type = 'text'; eye.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; eye.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
