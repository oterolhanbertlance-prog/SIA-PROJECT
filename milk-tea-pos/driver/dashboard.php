<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rider_auth.php';
require_once __DIR__ . '/../includes/delivery_schema.php';
requireRiderLogin();

$db = getDB();
ensureDeliveryColumns($db);
$rider = currentRider();

// Let the rider flip their own on-duty / off-duty status.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_duty') {
    $newStatus = $_POST['new_status'] === 'off_duty' ? 'off_duty' : 'available';
    $db->prepare("UPDATE delivery_riders SET status=?, updated_at=NOW() WHERE id=?")
       ->execute([$newStatus, $rider['id']]);
    header('Location: dashboard.php'); exit;
}

$riderRow = $db->prepare('SELECT r.*, b.name AS branch_name FROM delivery_riders r LEFT JOIN branches b ON b.id=r.branch_id WHERE r.id=?');
$riderRow->execute([$rider['id']]);
$riderRow = $riderRow->fetch();

// My active/assigned deliveries (matched on rider_name — how admin assigns them today).
$mine = $db->prepare(
    "SELECT * FROM transactions
     WHERE order_type='delivery' AND rider_name = ? AND delivery_status IN ('preparing','out_for_delivery') AND (branch_id = ? OR branch_id IS NULL)
     ORDER BY date_created ASC"
);
$mine->execute([$rider['name'], $riderRow['branch_id'] ?: 0]);
$mine = $mine->fetchAll();

// Unclaimed delivery orders any available rider can pick up.
$unclaimedStmt = $db->prepare("SELECT * FROM transactions WHERE order_type='delivery' AND (rider_name IS NULL OR rider_name='') AND delivery_status='preparing' AND branch_id=? ORDER BY date_created ASC LIMIT 15");
$unclaimedStmt->execute([(int)($riderRow['branch_id'] ?? 0)]);
$unclaimed = $unclaimedStmt->fetchAll();

// Recently completed by me, for a quick history glance.
$recent = $db->prepare(
    "SELECT * FROM transactions
     WHERE order_type='delivery' AND rider_name = ? AND delivery_status='delivered'
     ORDER BY delivered_at DESC LIMIT 5"
);
$recent->execute([$rider['name']]);
$recent = $recent->fetchAll();

$statusColors = [
    'pending'          => 'warning',
    'preparing'        => 'info',
    'out_for_delivery' => 'primary',
    'delivered'        => 'success',
    'failed'           => 'danger',
];
$statusLabels = [
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'failed'           => 'Failed',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Deliveries — Boba House Driver</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body>
<div class="driver-page">
  <div class="driver-topbar">
    <span class="brand"><i class="bi bi-truck me-1"></i>Boba House — Driver</span>
    <a href="logout.php" class="text-white-50 small text-decoration-none">
      <i class="bi bi-box-arrow-left me-1"></i>Sign out
    </a>
  </div>

  <div class="driver-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="fw-bold"><?= htmlspecialchars($rider['name']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($rider['phone']) ?> · <?= htmlspecialchars($riderRow['branch_name'] ?? 'No branch assigned') ?></div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="toggle_duty">
        <?php if (($riderRow['status'] ?? '') === 'off_duty'): ?>
          <input type="hidden" name="new_status" value="available">
          <button class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-moon-stars me-1"></i>Off Duty — Tap to Go Online
          </button>
        <?php else: ?>
          <input type="hidden" name="new_status" value="off_duty">
          <button class="btn btn-sm btn-success">
            <i class="bi bi-circle-fill me-1" style="font-size:.55rem"></i>On Duty
          </button>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($mine) && empty($unclaimed)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-cup-straw" style="font-size:2.5rem"></i>
        <p class="mt-3 mb-0">No deliveries right now. Check back soon!</p>
      </div>
    <?php endif; ?>

    <?php if (!empty($mine)): ?>
      <h6 class="fw-bold mb-2"><i class="bi bi-truck me-1"></i>My Deliveries</h6>
      <?php foreach ($mine as $d): ?>
        <a href="confirm.php?id=<?= $d['id'] ?>" class="driver-delivery-item">
          <div>
            <div class="fw-semibold">
              <span class="driver-status-dot bg-<?= $statusColors[$d['delivery_status']] ?? 'secondary' ?>"></span>
              #<?= $d['id'] ?> — <?= htmlspecialchars($d['delivery_name'] ?: 'Customer') ?>
            </div>
            <div class="text-muted small text-truncate" style="max-width:280px">
              <?= htmlspecialchars($d['delivery_address'] ?? '') ?>
            </div>
          </div>
          <div class="text-end">
            <span class="badge bg-<?= $statusColors[$d['delivery_status']] ?? 'secondary' ?>">
              <?= $statusLabels[$d['delivery_status']] ?? ucfirst($d['delivery_status']) ?>
            </span>
            <div class="small text-muted mt-1">&#8369;<?= number_format($d['total_amount'], 2) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($unclaimed)): ?>
      <h6 class="fw-bold mb-2 mt-4"><i class="bi bi-inbox me-1"></i>Available Deliveries — Tap to Claim</h6>
      <?php foreach ($unclaimed as $d): ?>
        <a href="confirm.php?id=<?= $d['id'] ?>" class="driver-delivery-item">
          <div>
            <div class="fw-semibold">#<?= $d['id'] ?> — <?= htmlspecialchars($d['delivery_name'] ?: 'Customer') ?></div>
            <div class="text-muted small text-truncate" style="max-width:280px">
              <?= htmlspecialchars($d['delivery_address'] ?? '') ?>
            </div>
          </div>
          <div class="text-end">
            <span class="badge bg-secondary">Unclaimed</span>
            <div class="small text-muted mt-1">&#8369;<?= number_format($d['total_amount'], 2) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($recent)): ?>
      <h6 class="fw-bold mb-2 mt-4 text-muted"><i class="bi bi-check-circle me-1"></i>Recently Delivered</h6>
      <?php foreach ($recent as $d): ?>
        <a href="confirm.php?id=<?= $d['id'] ?>" class="driver-delivery-item" style="opacity:.7">
          <div>
            <div class="fw-semibold">#<?= $d['id'] ?> — <?= htmlspecialchars($d['delivery_name'] ?: 'Customer') ?></div>
            <div class="text-muted small"><?= $d['delivered_at'] ? date('M j, g:i A', strtotime($d['delivered_at'])) : '' ?></div>
          </div>
          <span class="badge bg-success">Delivered</span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
