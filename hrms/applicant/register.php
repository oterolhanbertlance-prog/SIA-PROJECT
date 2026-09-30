<?php
/**
 * Applicant Self-Service Portal — Register
 * Creates an applicant_accounts record so the applicant can track their applications.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname  = sanitize($_POST['lastname']  ?? '');
    $email     = trim($_POST['email']         ?? '');
    $phone     = sanitize($_POST['phone']     ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$firstname || !$lastname || !$email || !$password) {
        $error = 'All required fields must be filled in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $db  = getDB();
        $chk = $db->prepare("SELECT id, password, status FROM applicant_accounts WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $chk->execute([$email]);
        $existing = $chk->fetch();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($existing && !empty($existing['password'])) {
            $error = 'An account with that email already has a password. Please log in instead or use the password reset option.';
        } else {
            if ($existing) {
                // The public application form creates an email-only account
                // with an empty password. Claim that record instead of
                // incorrectly telling the applicant they already registered.
                $db->prepare(
                    "UPDATE applicant_accounts
                        SET firstname=?, lastname=?, email=?, phone=?, password=?, status='active'
                      WHERE id=?"
                )->execute([$firstname, $lastname, $email, $phone, $hash, $existing['id']]);
                $newId = $existing['id'];
            } else {
                $db->prepare(
                    "INSERT INTO applicant_accounts (firstname, lastname, email, phone, password, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())"
                )->execute([$firstname, $lastname, $email, $phone, $hash]);
                $newId = $db->lastInsertId();
            }

            session_regenerate_id(true);
            $_SESSION['applicant_account_id'] = $newId;

            $redir = $_GET['redirect'] ?? '';
            if ($redir && strpos($redir, '/') !== 0) {
                header('Location: ' . SITE_URL . ltrim($redir, '/'));
            } else {
                header('Location: ' . SITE_URL . 'applicant/dashboard.php');
            }
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
    <title>Applicant Registration — <?= SITE_NAME ?></title>
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
        .wrap { width: 100%; max-width: 500px; padding: 1rem; }
        .card { border: none; border-radius: 1.25rem; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .logo {
            width: 60px; height: 60px; border-radius: 14px;
            background: linear-gradient(135deg, #1565c0, #42a5f5);
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
                <h4 class="fw-bold mb-1">Create Applicant Account</h4>
                <p class="text-muted small mb-0">Track your applications &amp; receive HR messages</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small border-0 rounded-3">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold small text-secondary">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="firstname" class="form-control"
                               value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small text-secondary">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="lastname" class="form-control"
                               value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-start-0 ps-0"
                                   placeholder="your@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Phone Number</label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="Optional"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
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
                    <div class="col-12">
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
                </div>

                <div class="d-grid mt-4 mb-3">
                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                        <i class="bi bi-person-check me-2"></i>Create Account
                    </button>
                </div>
            </form>

            <div class="text-center">
                <span class="text-muted small">Already have an account? </span>
                <a href="<?= SITE_URL ?>applicant/login.php" class="small fw-semibold text-decoration-none">Login here</a>
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
