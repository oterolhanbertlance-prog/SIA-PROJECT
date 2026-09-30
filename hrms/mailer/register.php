<?php
/**
 * HR Mailer — Register a new mailer account.
 * Only creates an account in mailer_accounts — does NOT give access to the main HRMS.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

if (mailerLoggedIn()) {
    header('Location: ' . SITE_URL . 'mailer/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname  = trim($_POST['fullname']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$fullname || !$email || !$username || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $username)) {
        $error = 'Username may only contain letters, numbers, dots, and underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check uniqueness
        $chk = $db->prepare("SELECT id FROM mailer_accounts WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $error = 'That username or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare(
                "INSERT INTO mailer_accounts (username, password, email, fullname, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())"
            )->execute([$username, $hash, $email, $fullname]);

            header('Location: ' . SITE_URL . 'mailer/login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Mailer Account — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 60%, #40916c 100%);
            display: flex; align-items: center; justify-content: center; padding: 1rem 0;
        }
        .wrap { width: 100%; max-width: 480px; padding: 1rem; }
        .card { border: none; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .logo {
            width: 56px; height: 56px; border-radius: 14px;
            background: linear-gradient(135deg, #2d6a4f, #74c69d);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #fff; margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="logo"><i class="bi bi-person-plus-fill"></i></div>
                <h4 class="fw-bold mb-1">Create Mailer Account</h4>
                <p class="text-muted small mb-0">Register to access the HR Mailer module</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 rounded-3">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Note for existing HR/admin users -->
            <div class="alert alert-info py-2 small border-0 rounded-3 mb-3" style="background:#e8f4fd;">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Already have an <strong>admin or HR</strong> account?
                You can <a href="<?= SITE_URL ?>mailer/login.php" class="fw-semibold">log in directly</a> using your existing credentials — no new account needed.
            </div>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Full Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="fullname" class="form-control border-start-0 ps-0"
                               placeholder="Your full name"
                               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control border-start-0 ps-0"
                               placeholder="your@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-at text-muted"></i></span>
                        <input type="text" name="username" class="form-control border-start-0 ps-0"
                               placeholder="Letters, numbers, dots, underscores"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                        <input type="password" name="password" id="pw1"
                               class="form-control border-start-0 border-end-0 ps-0"
                               placeholder="Min. 6 characters" required minlength="6">
                        <button type="button" class="btn btn-light border" onclick="togglePw('pw1','eye1')">
                            <i class="bi bi-eye" id="eye1"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-secondary">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill text-muted"></i></span>
                        <input type="password" name="password2" id="pw2"
                               class="form-control border-start-0 border-end-0 ps-0"
                               placeholder="Re-enter password" required>
                        <button type="button" class="btn btn-light border" onclick="togglePw('pw2','eye2')">
                            <i class="bi bi-eye" id="eye2"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-success py-2 fw-semibold">
                        <i class="bi bi-person-plus me-2"></i>Create Account
                    </button>
                </div>
            </form>

            <div class="text-center">
                <a href="<?= SITE_URL ?>mailer/login.php" class="text-muted small text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Back to Login
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
function togglePw(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (inp.type === 'password') { inp.type = 'text'; eye.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; eye.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
