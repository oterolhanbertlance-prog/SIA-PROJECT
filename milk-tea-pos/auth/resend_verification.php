<?php
session_start();
require_once '../config/db.php';
require_once '../config/mailer.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND email_verified = 0 AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $db->prepare('UPDATE users SET verification_token = ? WHERE id = ?')
               ->execute([$token, $user['id']]);
            sendVerificationEmail($email, $token);
        }

        // Always show the same message so you can't probe which emails exist
        $success = 'If that email is registered and unverified, a new verification link has been sent. Check your MailHog inbox at http://localhost:8025';
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Resend Verification — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">&#9749; Boba House</div>
    <p class="text-center text-muted mb-4" style="font-size:.85rem;">Resend verification email</p>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i><?= $success ?>
      </div>
      <div class="text-center mt-3">
        <a href="login.php" class="btn btn-boba">Back to Login</a>
      </div>
    <?php else: ?>
      <form method="POST">
        <div class="mb-4">
          <label class="form-label fw-bold">Your Email Address</label>
          <div class="input-group">
            <span class="input-group-text"
                  style="background:var(--boba-cream);border-color:var(--boba-border)">
              <i class="bi bi-envelope"></i>
            </span>
            <input type="email" name="email" class="form-control"
                   placeholder="Enter the email you registered with"
                   required autofocus
                   value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
          </div>
          <div class="form-text" style="color:var(--boba-muted)">
            We will send a new verification link to this address.
          </div>
        </div>
        <button type="submit" class="btn btn-boba w-100 py-2 fs-6">
          <i class="bi bi-send me-1"></i>Send Verification Link
        </button>
      </form>
    <?php endif; ?>

    <p class="text-center mt-4 mb-0" style="font-size:.85rem;color:var(--boba-muted)">
      <a href="login.php" style="color:var(--boba-amber)">
        <i class="bi bi-arrow-left me-1"></i>Back to login
      </a>
    </p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>