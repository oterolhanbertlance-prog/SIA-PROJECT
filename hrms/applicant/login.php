<?php
/**
 * Applicant Self-Service Portal — Login
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/applicant_session.php';
require_once '../includes/functions.php';

if (applicantLoggedIn()) {
    header('Location: ' . SITE_URL . 'applicant/dashboard.php');
    exit;
}

$error = '';
$info  = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $info = 'You have been logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT * FROM applicant_accounts WHERE email = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {
            session_regenerate_id(true);
            $_SESSION['applicant_account_id'] = $account['id'];

            $redir = $_GET['redirect'] ?? '';
            if ($redir && strpos($redir, '/') === 0) {
                header('Location: ' . $redir);
            } else {
                header('Location: ' . SITE_URL . 'applicant/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #42a5f5 100%);
            display: flex; align-items: center; justify-content: center; padding: 1rem 0;
        }
        .wrap { width: 100%; max-width: 440px; padding: 1rem; }
        .card { border: none; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .logo {
            width: 60px; height: 60px; border-radius: 14px;
            background: linear-gradient(135deg, #1565c0, #42a5f5);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #fff; margin: 0 auto 1rem;
        }
        .divider { display:flex; align-items:center; gap:.8rem; color:#aaa; font-size:.82rem; }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="logo"><i class="bi bi-person-circle"></i></div>
                <h4 class="fw-bold mb-1">Applicant Portal</h4>
                <p class="text-muted small mb-0">Track your applications &amp; view HR messages</p>
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

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control border-start-0 ps-0"
                               placeholder="your@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                        <input type="password" name="password" id="pwInput"
                               class="form-control border-start-0 border-end-0 ps-0"
                               placeholder="Enter password" required>
                        <button type="button" class="btn btn-light border" onclick="togglePw()">
                            <i class="bi bi-eye" id="pwEye"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </div>
            </form>

            <div class="divider my-3">don't have an account?</div>

            <div class="d-grid mb-3">
                <a href="<?= SITE_URL ?>applicant/register.php" class="btn btn-outline-primary py-2 fw-semibold">
                    <i class="bi bi-person-plus me-2"></i>Create Applicant Account
                </a>
            </div>

            <div class="text-center d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= SITE_URL ?>apply.php" class="text-muted small text-decoration-none">
                    <i class="bi bi-briefcase me-1"></i>Browse Jobs
                </a>
                <span class="text-muted small">|</span>
                <a href="<?= SITE_URL ?>index.php" class="text-muted small text-decoration-none">
                    <i class="bi bi-shield me-1"></i>Staff Login
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
