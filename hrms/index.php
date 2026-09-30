<?php
/**
 * HRMS — Main Staff Login
 * Handles login for admin, hr, and employee roles.
 */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'config/db_connect.php';
require_once 'integration/pos_common.php';

// Already logged in → dashboard
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . 'dashboard.php');
    exit;
}

$error = '';
$info  = '';

if (isset($_GET['msg'])) {
    $map = [
        'session_expired' => 'Your session has expired. Please log in again.',
        'logged_out'      => 'You have been logged out successfully.',
        'mailer_access'   => 'Please log in as HR or Admin to access the Mailer.',
    ];
    $info = $map[$_GET['msg']] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $db   = getDB();
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
        $db->exec("ALTER TABLE users MODIFY COLUMN pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['full_name']   = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?: $user['username'];
            $_SESSION['employee_id'] = $user['employee_id'];

            // Keep the shared hierarchy aligned. HRMS Admin is the POS
            // super-admin; a hired employee is mirrored using its POS role.
            $posRole = posRoleForHrmsUser($user);
            if ($posRole) {
                try {
                    syncHrmsUserToPos(getPosDB(), $user, $posRole, $user['employee_id'] ? (int)$user['employee_id'] : null);
                } catch (Throwable $e) {
                    // HRMS login must remain available if POS is offline.
                }
            }

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            auditLog('LOGIN', 'Authentication', ucfirst($user['role']) . ' logged in');

            header('Location: ' . SITE_URL . 'dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            background: linear-gradient(135deg,#1565c0,#42a5f5);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #fff; margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(21,101,192,.4);
        }
        .btn-login { background: linear-gradient(135deg,#1565c0,#42a5f5); border: none; font-weight: 600; letter-spacing: .3px; }
        .btn-login:hover { background: linear-gradient(135deg,#0d47a1,#1565c0); }
        .divider { display: flex; align-items: center; gap: 1rem; color: #aaa; font-size: .85rem; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="card login-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-people-fill"></i></div>
                <h4 class="fw-bold mb-1">HRMS Staff Login</h4>
                <p class="text-muted small mb-0">Human Resource Management System</p>
            </div>

            <?php if ($info): ?>
            <div class="alert alert-info py-2 small border-0 rounded-3">
                <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($info) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 rounded-3">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="username" class="form-control border-start-0 ps-0"
                               placeholder="Enter username or email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                        <input type="password" name="password" id="pwInput"
                               class="form-control border-start-0 border-end-0 ps-0"
                               placeholder="Enter your password" required>
                        <button type="button" class="btn btn-light border" onclick="togglePw()" title="Show/hide password">
                            <i class="bi bi-eye" id="pwEye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-login btn-primary py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to HRMS
                    </button>
                </div>
            </form>

            <div class="divider my-3">new staff account?</div>

            <div class="d-grid mb-3">
                <a href="<?= SITE_URL ?>register.php" class="btn btn-outline-primary py-2 fw-semibold">
                    <i class="bi bi-person-plus me-2"></i>Register as Admin / HR
                </a>
            </div>


        </div>
    </div>
    <p class="text-center text-white-50 small mt-3 mb-0">
        &copy; <?= date('Y') ?> <?= SITE_NAME ?>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
