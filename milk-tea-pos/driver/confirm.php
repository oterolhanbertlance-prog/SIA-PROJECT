<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rider_auth.php';
require_once __DIR__ . '/../includes/delivery_schema.php';
requireRiderLogin();

$db = getDB();
ensureDeliveryColumns($db);

$rider = currentRider();
$riderStmt = $db->prepare('SELECT id, name, phone, branch_id FROM delivery_riders WHERE id=? LIMIT 1');
$riderStmt->execute([(int)$rider['id']]);
$riderRecord = $riderStmt->fetch();
if (!$riderRecord) {
    unset($_SESSION['rider_id'], $_SESSION['rider_name'], $_SESSION['rider_phone']);
    header('Location: login.php');
    exit;
}
$rider['id'] = (int)$riderRecord['id'];
$rider['name'] = $riderRecord['name'];
$rider['phone'] = $riderRecord['phone'];
$id    = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM transactions WHERE id=? AND order_type='delivery'");
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx) {
    http_response_code(404);
    die('Delivery order not found.');
}

if (empty($riderRecord['branch_id']) || (int)($tx['branch_id'] ?? 0) !== (int)$riderRecord['branch_id']) {
    http_response_code(403);
    die('This delivery belongs to a different branch.');
}

$error = $notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        // Claim this delivery: assign it to me and put it on the road.
        $claim = $db->prepare(
            "UPDATE transactions
             SET rider_name=?, rider_id=?, delivery_status='out_for_delivery'
             WHERE id=? AND order_type='delivery'
               AND (rider_name IS NULL OR TRIM(rider_name)='')
               AND delivery_status IN ('pending','preparing')"
        );
        $claim->execute([$rider['name'], $rider['id'], $id]);
        if ($claim->rowCount() > 0) {
            $db->prepare("UPDATE delivery_riders SET status='on_delivery', updated_at=NOW() WHERE id=?")
               ->execute([$rider['id']]);
            header('Location: confirm.php?id=' . $id); exit;
        }
        $error = 'This delivery was already claimed or is no longer available.';
    }

    if ($action === 'confirm_delivered') {
        $complete = $db->prepare(
            "UPDATE transactions
             SET delivery_status='delivered', delivered_at=NOW()
             WHERE id=? AND order_type='delivery'
               AND LOWER(TRIM(rider_name))=LOWER(TRIM(?))
               AND delivery_status='out_for_delivery'"
        );
        $complete->execute([$id, $rider['name']]);
        if ($complete->rowCount() > 0) {
            $db->prepare("UPDATE delivery_riders SET status='available', updated_at=NOW() WHERE id=?")
               ->execute([$rider['id']]);
            header('Location: confirm.php?id=' . $id . '&done=1'); exit;
        }
        $error = 'This delivery could not be marked as delivered. Refresh and try again.';
    }

    if ($action === 'mark_failed') {
        $reason = trim($_POST['fail_reason'] ?? '');
        $failed = $db->prepare(
            "UPDATE transactions
             SET delivery_status='failed',
                 delivery_notes=CONCAT_WS(' | ', NULLIF(TRIM(delivery_notes), ''), CONCAT('Failed: ', ?))
             WHERE id=? AND order_type='delivery'
               AND LOWER(TRIM(rider_name))=LOWER(TRIM(?))
               AND delivery_status='out_for_delivery'"
        );
        $failed->execute([$reason ?: 'No reason given', $id, $rider['name']]);
        if ($failed->rowCount() > 0) {
            $db->prepare("UPDATE delivery_riders SET status='available', updated_at=NOW() WHERE id=?")
               ->execute([$rider['id']]);
            header('Location: confirm.php?id=' . $id); exit;
        }
        $error = 'This delivery could not be marked as failed. Refresh and try again.';
    }
}

// Re-fetch fresh state after any action above.
$stmt->execute([$id]);
$tx = $stmt->fetch();

$dStmt = $db->prepare(
    'SELECT td.*, p.name AS product_name FROM transaction_details td
     JOIN products p ON p.id = td.product_id WHERE td.transaction_id=?'
);
$dStmt->execute([$id]);
$items = $dStmt->fetchAll();

$isMine        = $tx['rider_name'] !== null
    && strcasecmp(trim($tx['rider_name']), trim($rider['name'])) === 0;
$isUnclaimed   = empty($tx['rider_name']);
$justDelivered = isset($_GET['done']);

$statusLabels = [
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'failed'           => 'Failed',
];
$statusColors = [
    'pending'          => 'warning',
    'preparing'        => 'info',
    'out_for_delivery' => 'primary',
    'delivered'        => 'success',
    'failed'           => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delivery #<?= $tx['id'] ?> — Boba House Driver</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body>
<div class="driver-page">
  <div class="driver-topbar">
    <a href="dashboard.php" class="text-white text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i><span class="brand">Delivery #<?= $tx['id'] ?></span>
    </a>
    <a href="logout.php" class="text-white-50 small text-decoration-none">
      <i class="bi bi-box-arrow-left me-1"></i>Sign out
    </a>
  </div>

  <div class="driver-card">

    <?php if ($justDelivered || $tx['delivery_status'] === 'delivered'): ?>
      <div class="text-center py-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h5 class="fw-bold mt-3 mb-1">Delivery Confirmed</h5>
        <p class="text-muted small mb-0">
          Order #<?= $tx['id'] ?> was marked delivered
          <?= $tx['delivered_at'] ? 'at ' . date('g:i A, M j', strtotime($tx['delivered_at'])) : '' ?>.
        </p>
      </div>
      <hr>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div class="fw-bold fs-5"><?= htmlspecialchars($tx['delivery_name'] ?: 'Customer') ?></div>
        <?php if ($tx['delivery_phone']): ?>
          <a href="tel:<?= htmlspecialchars($tx['delivery_phone']) ?>" class="text-decoration-none small">
            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($tx['delivery_phone']) ?>
          </a>
        <?php endif; ?>
      </div>
      <span class="badge bg-<?= $statusColors[$tx['delivery_status']] ?? 'secondary' ?> fs-6">
        <?= $statusLabels[$tx['delivery_status']] ?? ucfirst($tx['delivery_status']) ?>
      </span>
    </div>

    <div class="mb-3">
      <div class="text-muted small fw-bold text-uppercase" style="font-size:.7rem">Deliver To</div>
      <div><?= htmlspecialchars($tx['delivery_address'] ?? '—') ?></div>
      <?php if ($tx['delivery_notes']): ?>
        <div class="text-muted small mt-1"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($tx['delivery_notes']) ?></div>
      <?php endif; ?>
    </div>

    <table class="table table-sm mb-3">
      <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['product_name']) ?></td>
          <td class="text-center"><?= $it['quantity'] ?></td>
          <td class="text-end">&#8369;<?= number_format($it['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold">
          <td colspan="2" class="text-end">Total (<?= ucfirst($tx['payment_method']) ?>)</td>
          <td class="text-end">&#8369;<?= number_format($tx['total_amount'], 2) ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if ($tx['payment_method'] === 'cash'): ?>
      <div class="alert alert-warning py-2 small mb-3">
        <i class="bi bi-cash-coin me-1"></i>Collect &#8369;<?= number_format($tx['total_amount'], 2) ?> cash on delivery.
      </div>
    <?php else: ?>
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-phone me-1"></i>Already paid via GCash — no cash to collect.
      </div>
    <?php endif; ?>

    <!-- ── ACTIONS ─────────────────────────────────── -->
    <?php if ($tx['delivery_status'] === 'delivered'): ?>
      <a href="dashboard.php" class="btn btn-boba-outline w-100">Back to My Deliveries</a>

    <?php elseif ($tx['delivery_status'] === 'failed'): ?>
      <div class="alert alert-danger py-2 small">This delivery was marked as failed.</div>
      <a href="dashboard.php" class="btn btn-boba-outline w-100">Back to My Deliveries</a>

    <?php elseif ($isUnclaimed): ?>
      <form method="POST">
        <input type="hidden" name="action" value="accept">
        <button type="submit" class="driver-confirm-btn">
          <i class="bi bi-hand-thumbs-up-fill me-1"></i>Accept &amp; Start Delivery
        </button>
      </form>

    <?php elseif ($isMine && $tx['delivery_status'] === 'out_for_delivery'): ?>
      <form method="POST" class="mb-2">
        <input type="hidden" name="action" value="confirm_delivered">
        <button type="submit" class="driver-confirm-btn"
                onclick="return confirm('Confirm this order was delivered to the customer?')">
          <i class="bi bi-check-circle-fill me-1"></i>Confirm Delivered
        </button>
      </form>
      <button class="btn btn-outline-danger w-100 btn-sm" type="button"
              data-bs-toggle="collapse" data-bs-target="#failBox">
        <i class="bi bi-x-circle me-1"></i>Couldn't Deliver?
      </button>
      <div class="collapse mt-2" id="failBox">
        <form method="POST">
          <input type="hidden" name="action" value="mark_failed">
          <textarea name="fail_reason" class="form-control form-control-sm mb-2"
                    placeholder="Reason (e.g. customer not reachable)" rows="2"></textarea>
          <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                  onclick="return confirm('Mark this delivery as failed?')">
            Mark as Failed
          </button>
        </form>
      </div>

    <?php elseif ($isMine && $tx['delivery_status'] === 'preparing'): ?>
      <div class="alert alert-info py-2 small">Still being prepared at the counter — check back shortly.</div>
      <a href="dashboard.php" class="btn btn-boba-outline w-100">Back to My Deliveries</a>

    <?php else: ?>
      <div class="alert alert-secondary py-2 small">This delivery is assigned to another rider.</div>
      <a href="dashboard.php" class="btn btn-boba-outline w-100">Back to My Deliveries</a>
    <?php endif; ?>

  </div>
</div>

<?php if ($justDelivered): ?>
<div class="modal fade" id="driverDeliveredModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body text-center p-4">
        <div class="display-3 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="fw-bold">Delivery Successful!</h4>
        <p class="text-muted mb-0">Order #<?= (int)$tx['id'] ?> has been marked as delivered.</p>
        <button type="button" class="btn btn-success mt-3 px-4" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($justDelivered): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('driverDeliveredModal');
  if (el) new bootstrap.Modal(el).show();
});
</script>
<?php endif; ?>

</body>
</html>
