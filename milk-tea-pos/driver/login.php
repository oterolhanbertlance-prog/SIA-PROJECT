<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Already signed in? go straight to the dashboard.
if (!empty($_SESSION['rider_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    if (!$phone) {
        $error = 'Please enter your phone number.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM delivery_riders WHERE phone = ?');
        $stmt->execute([$phone]);
        $rider = $stmt->fetch();

        if (!$rider) {
            $error = 'No rider account found with that phone number. Ask the branch admin to add you under Logistics / Routes.';
        } elseif ($rider['status'] === 'off_duty') {
            // Off-duty riders can still log in — just flip them to available automatically.
            $db->prepare("UPDATE delivery_riders SET status='available', updated_at=NOW() WHERE id=?")->execute([$rider['id']]);
        }

        if ($rider) {
            $_SESSION['rider_id']    = $rider['id'];
            $_SESSION['rider_name']  = $rider['name'];
            $_SESSION['rider_phone'] = $rider['phone'];
            header('Location: dashboard.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Login — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body>
<div class="driver-page d-flex align-items-center">
  <div class="w-100">
    <div class="driver-topbar">
      <span class="brand"><i class="bi bi-truck me-1"></i>Boba House — Driver</span>
      <a href="../index.php" class="text-white-50 small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Back to site
      </a>
    </div>

    <div class="driver-card" style="max-width:420px">
      <div class="text-center mb-4">
        <div class="login-logo mx-auto" style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#1565c0,#42a5f5);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff">
          <i class="bi bi-bicycle"></i>
        </div>
        <h5 class="fw-bold mt-3 mb-1">Rider Sign In</h5>
        <p class="text-muted small mb-0">Enter your registered phone number to see your deliveries.</p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold small text-secondary">Phone Number</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-phone"></i></span>
            <input type="text" name="phone" class="form-control border-start-0 ps-0"
                   placeholder="09XXXXXXXXX" required autofocus
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-boba w-100 py-2">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <p class="text-center text-muted small mt-3 mb-0">
        Not registered yet? Ask the branch admin to add you under <strong>Logistics / Routes</strong>.
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
