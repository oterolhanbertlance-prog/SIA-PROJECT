<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/order_queue.php';
require_once '../includes/product_customization.php';
requireLogin('../auth/login.php');

$db = getDB();
ensureProductCustomizationSchema($db);
ensureOrderQueueSchema($db);
$id = (int)($_GET['id'] ?? 0);

// A customer may only view their own receipt; super_admin can view any of them.
$stmt = $db->prepare('SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?');
$stmt->execute([$id]);
$tx = $stmt->fetch();

$canView = false;
if ($tx) {
    $role = $_SESSION['role'] ?? '';
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    if ($role === 'super_admin' || (int)$tx['user_id'] === $uid || (int)($tx['processed_by'] ?? 0) === $uid) {
        $canView = true;
    } elseif ($role === 'cashier') {
        $cb = cashierBranchId($db, $uid);
        $canView = $cb && (int)($tx['branch_id'] ?? 0) === $cb;
    }
}
if (!$canView) {
    http_response_code(404);
    die('Receipt not found.');
}

$dStmt = $db->prepare('SELECT td.*, p.name as product_name FROM transaction_details td JOIN products p ON p.id=td.product_id WHERE td.transaction_id=?');
$dStmt->execute([$id]);
$details = $dStmt->fetchAll();

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Receipt #<?php echo $tx['id']; ?> — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body>
<div class="d-flex" style="min-height:100vh">
  <div class="d-print-none">
    <?php include '../includes/sidebar.php'; ?>
  </div>
  <div id="main-content">
    <div class="topbar d-print-none">
      <h1 class="page-title"><i class="bi bi-receipt me-2" style="color:var(--boba-amber)"></i>Receipt #<?php echo $tx['id']; ?></h1>
      <div class="d-flex gap-2">
        <a href="<?= ($_SESSION['role']??'')==='cashier' ? appUrl('customer/receipts.php') : appUrl('customer/receipts.php') ?>" class="btn btn-boba-outline"><i class="bi bi-arrow-left me-1"></i><?= ($_SESSION['role']??'')==='cashier' ? 'Sales Receipts' : 'My Orders' ?></a>
        <button onclick="window.print()" class="btn btn-boba"><i class="bi bi-printer me-1"></i>Print</button>
      </div>
    </div>
    <div class="content-area d-flex justify-content-center">
      <div class="receipt-card">
        <div class="text-center mb-3">
          <div class="auth-logo">&#9749; Boba House</div>
          <div style="font-size:.8rem;color:var(--boba-muted)">Official Receipt</div>
        </div>
        <hr>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Order #</span><span class="fw-bold">#<?php echo $tx['id']; ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Date</span><span><?php echo date('F j, Y g:i A', strtotime($tx['date_created'])); ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Status</span><span class="badge-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Order Type</span>
          <span><i class="bi bi-<?php echo $tx['order_type']==='delivery' ? 'truck' : 'shop'; ?> me-1"></i><?php echo ucfirst($tx['order_type']); ?></span>
        </div>
        <?php if ($tx['order_type'] === 'delivery' && $tx['delivery_address']): ?>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Deliver to</span><span class="text-end" style="max-width:60%"><?php echo htmlspecialchars($tx['delivery_address']); ?></span>
        </div>
        <?php endif; ?>
        <hr>
        <table class="table table-sm mb-2">
          <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead>
          <tbody>
            <?php foreach ($details as $d): ?>
            <tr>
              <td>
                <?php echo htmlspecialchars($d['product_name']); ?>
                <?php $d['toppings']=json_decode($d['toppings']??'[]',true)?:[];$parts=customizationSummary($d); if($parts): ?><div style="font-size:.72rem;color:var(--boba-muted)"><?php echo htmlspecialchars(implode(' • ',$parts)); ?></div><?php endif; ?>
              </td>
              <td class="text-center"><?php echo $d['quantity']; ?></td>
              <td class="text-end">₱<?php echo number_format($d['subtotal'],2); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5 mb-2">
          <span>Total</span><span>₱<?php echo number_format($tx['total_amount'],2); ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Payment Method</span>
          <span class="badge <?php echo $tx['payment_method']==='gcash' ? 'badge-gcash' : 'bg-secondary'; ?>"><?php echo ucfirst($tx['payment_method']); ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Amount Paid</span><span>₱<?php echo number_format($tx['amount_paid'],2); ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem">
          <span>Change</span><span>₱<?php echo number_format($tx['change_amount'],2); ?></span>
        </div>
        <hr>
        <div class="text-center" style="font-size:.78rem;color:var(--boba-muted)">
          Thank you for your order! &#9749;
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
