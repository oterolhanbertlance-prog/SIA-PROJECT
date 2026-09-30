<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/product_customization.php';
requireRole('super_admin', '../auth/login.php');

$id   = (int)($_GET['id'] ?? 0);
$db   = getDB();
ensureProductCustomizationSchema($db);
$stmt = $db->prepare('SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?');
$stmt->execute([$id]);
$tx   = $stmt->fetch();

if (!$tx) { echo '<p class="text-danger text-center py-4">Transaction not found.</p>'; exit; }

$dStmt = $db->prepare('SELECT td.*, p.name as product_name FROM transaction_details td JOIN products p ON p.id=td.product_id WHERE td.transaction_id=?');
$dStmt->execute([$id]);
$details = $dStmt->fetchAll();
?>
<div class="row mb-3">
  <div class="col-6"><strong>Clerk:</strong> <?php echo htmlspecialchars($tx['username'] ?? '—'); ?></div>
  <div class="col-6"><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($tx['date_created'])); ?></div>
  <div class="col-6 mt-2"><strong>Status:</strong> <span class="badge-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></div>
  <div class="col-6 mt-2"><strong>Payment:</strong> <?php echo ucfirst($tx['payment_method']); ?></div>
  <div class="col-6 mt-2"><strong>Order Type:</strong> <?php echo ucfirst($tx['order_type']); ?></div>
  <?php if ($tx['order_type'] === 'delivery' && $tx['delivery_address']): ?>
  <div class="col-12 mt-2"><strong>Deliver to:</strong> <?php echo htmlspecialchars($tx['delivery_address']); ?></div>
  <?php endif; ?>
</div>
<table class="table table-sm table-bordered">
  <thead class="table-light">
    <tr><th>Product / Options</th><th class="text-center">Qty</th><th class="text-end">Unit</th><th class="text-end">Subtotal</th></tr>
  </thead>
  <tbody>
    <?php foreach ($details as $d): ?>
    <tr>
      <td><?php echo htmlspecialchars($d['product_name']); ?><?php $d['toppings']=json_decode($d['toppings']??'[]',true)?:[];$parts=customizationSummary($d);if($parts):?><div class="small text-muted"><?php echo htmlspecialchars(implode(' • ',$parts));?></div><?php endif;?></td>
      <td class="text-center"><?php echo $d['quantity']; ?></td>
      <td class="text-end">₱<?php echo number_format($d['unit_price'],2); ?></td>
      <td class="text-end">₱<?php echo number_format($d['subtotal'],2); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="table-light fw-bold">
      <td colspan="3" class="text-end">Total</td>
      <td class="text-end">₱<?php echo number_format($tx['total_amount'],2); ?></td>
    </tr>
    <tr>
      <td colspan="3" class="text-end">Amount Paid</td>
      <td class="text-end">₱<?php echo number_format($tx['amount_paid'],2); ?></td>
    </tr>
    <tr>
      <td colspan="3" class="text-end text-success fw-bold">Change</td>
      <td class="text-end fw-bold text-success">₱<?php echo number_format($tx['change_amount'],2); ?></td>
    </tr>
  </tfoot>
</table>