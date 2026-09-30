<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) { header('Location: ' . SITE_URL . 'dashboard.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        require_once 'includes/functions.php';
        $db = getDB();

        $firstname = trim(htmlspecialchars($_POST['firstname'] ?? ''));
        $lastname  = trim(htmlspecialchars($_POST['lastname']  ?? ''));
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email']    ?? '');
        $phone     = trim($_POST['phone']    ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','hr']) ? $_POST['role'] : 'hr';
        $password  = $_POST['password']         ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        // Validate
        if (!$firstname || !$lastname || !$username || !$email || !$password) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $username)) {
            $error = 'Username may only contain letters, numbers, dots, and underscores.';
        } else {
            // Check duplicates
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                $error = 'That username or email is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare(
                    "INSERT INTO users (username, password, email, role, firstname, lastname, phone, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                )->execute([$username, $hash, $email, $role, $firstname, $lastname, $phone]);

                // Notify HR/admin via notification
                $notifStmt = $db->prepare(
                    "INSERT INTO notifications (user_id, title, message, type, created_at)
                     SELECT id, 'New Registration Request',
                            CONCAT(?, ' (', ?, ') has registered as ', ?, ' and is awaiting approval.'),
                            'info', NOW()
                     FROM users WHERE role IN ('admin','hr') AND status = 'active'"
                );
                $notifStmt->execute(["$firstname $lastname", $username, strtoupper($role)]);

                $success = "Registration submitted! Your account is <strong>pending approval</strong> by HR. You will be able to log in once approved.";
                // Clear form
                $_POST = [];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #1a1f3a 0%, #4361ee 100%); min-height: 100vh; font-family: 'Inter', sans-serif; }
        .login-bg-pattern {
            position: fixed; inset: 0;
            pointer-events: none;
            z-index: 0;
            background-image: radial-gradient(circle at 25% 25%, rgba(76,201,240,.15) 0%, transparent 50%),
                              radial-gradient(circle at 75% 75%, rgba(67,97,238,.2) 0%, transparent 50%);
        }
        .register-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            position: relative;
            z-index: 1;
        }
        .register-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            z-index: 1;
        }
        .register-logo {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #4361ee, #4cc9f0);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #fff;
            margin: 0 auto 1rem;
        }
        .role-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
        }
        .role-card:hover { border-color: #4361ee; background: #f0f3ff; }
        .role-card.selected { border-color: #4361ee; background: #f0f3ff; }
        .role-card input { display: none; }
        .role-icon { font-size: 1.6rem; margin-bottom: .3rem; }
    </style>
</head>
<body>
<div class="login-bg-pattern"></div>
<div class="register-wrapper">
    <div class="register-card">
        <div class="register-logo"><i class="bi bi-person-plus-fill"></i></div>
        <h4 class="text-center mb-1 fw-bold">Create Account</h4>
        <p class="text-center text-muted mb-4" style="font-size:.875rem;">Register for the HRMS Portal — HR will review your request.</p>
        <div class="alert alert-info py-2 mb-3" style="font-size:.8rem;"><i class="bi bi-info-circle-fill me-2"></i>This form is for <strong>Admin</strong> and <strong>HR</strong> staff only. Employee accounts are created by HR after you're hired through our recruitment process.</div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Back to Login</a>
        </div>
        <?php else: ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Role Selection -->
            <div class="mb-4">
                <label class="form-label fw-semibold mb-2">I am registering as <span class="text-danger">*</span></label>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="role-card <?= ($_POST['role']??'hr')==='admin'?'selected':'' ?>" onclick="selectRole(this,'admin')">
                            <input type="radio" name="role" value="admin" <?= ($_POST['role']??'hr')==='admin'?'checked':'' ?>>
                            <div class="role-icon text-danger"><i class="bi bi-shield-lock-fill"></i></div>
                            <div class="fw-semibold small">Admin</div>
                            <div style="font-size:.7rem;" class="text-muted">Full access</div>
                        </label>
                    </div>
                    <div class="col-6">
                        <label class="role-card <?= ($_POST['role']??'hr')==='hr'?'selected':'' ?>" onclick="selectRole(this,'hr')">
                            <input type="radio" name="role" value="hr" <?= ($_POST['role']??'hr')==='hr'?'checked':'' ?>>
                            <div class="role-icon text-primary"><i class="bi bi-people-fill"></i></div>
                            <div class="fw-semibold small">HR</div>
                            <div style="font-size:.7rem;" class="text-muted">HR access</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Name -->
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="firstname" class="form-control" placeholder="Juan"
                           value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="lastname" class="form-control" placeholder="Dela Cruz"
                           value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Username -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="e.g. jdelacruz"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required pattern="[a-zA-Z0-9._]+">
                </div>
                <div class="form-text">Letters, numbers, dots, underscores only.</div>
            </div>

            <!-- Email -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="juan@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Phone -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                    <input type="text" name="phone" class="form-control" placeholder="09xx-xxx-xxxx"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>

            <!-- Password -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="pw1" class="form-control"
                           placeholder="Min. 6 chars" required minlength="6">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Confirm <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" id="pw2" class="form-control"
                           placeholder="Repeat password" required>
                </div>
                <div class="col-12" id="pwMatchMsg" style="display:none;">
                    <small class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-send-fill me-2"></i>Submit Registration
            </button>
        </form>
        <?php endif; ?>

        <p class="text-center text-muted mt-3 mb-0" style="font-size:.8rem;">
            Already have an account? <a href="index.php" class="text-primary fw-semibold">Sign In</a>
        </p>
        <p class="text-center text-muted mt-2 mb-0" style="font-size:.7rem;">
            &copy; <?= date('Y') ?> HRMS — Human Resource Management System
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectRole(el, val) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
}
// Default select employee on load
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="role"]:checked');
    if (checked) checked.closest('.role-card').classList.add('selected');
});
// Password match check
const pw1 = document.getElementById('pw1');
const pw2 = document.getElementById('pw2');
const msg = document.getElementById('pwMatchMsg');
function checkMatch() {
    if (pw2 && pw2.value.length > 0) {
        msg.style.display = pw1.value !== pw2.value ? 'block' : 'none';
    }
}
if (pw1) pw1.addEventListener('input', checkMatch);
if (pw2) pw2.addEventListener('input', checkMatch);
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    if (pw1.value !== pw2.value) { e.preventDefault(); msg.style.display = 'block'; pw2.focus(); }
});
</script>
</body>
</html>
