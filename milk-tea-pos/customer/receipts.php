<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/order_queue.php';
requireAnyRole(['customer','cashier'], '../auth/login.php');

$db = getDB();
ensureOrderQueueSchema($db);
$isCashier = (($_SESSION['role'] ?? '') === 'cashier');

if ($isCashier) {
    // Cashier receipts are sales processed by this cashier account, not customer order history.
    $stmt = $db->prepare(
        "SELECT t.*, b.name AS branch_name, cu.username AS customer_name
           FROM transactions t
           LEFT JOIN branches b ON b.id=t.branch_id
           LEFT JOIN users cu ON cu.id=t.user_id
          WHERE (t.processed_by=? OR (t.user_id=? AND cu.role='cashier'))
          ORDER BY t.date_created DESC"
    );    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $orders = $stmt->fetchAll();

    $todaySales = 0.0; $todayCount = 0; $cashTotal = 0.0; $gcashTotal = 0.0;
    $today = date('Y-m-d');
    foreach ($orders as $o) {
        if (substr((string)$o['date_created'],0,10) === $today) {
            $todaySales += (float)$o['total_amount'];
            $todayCount++;
        }
        if (($o['payment_method'] ?? '') === 'cash') $cashTotal += (float)$o['total_amount'];
        if (($o['payment_method'] ?? '') === 'gcash') $gcashTotal += (float)$o['total_amount'];
    }
} else {
    $stmt = $db->prepare('SELECT t.*, b.name AS branch_name FROM transactions t LEFT JOIN branches b ON b.id=t.branch_id WHERE t.user_id=? ORDER BY t.date_created DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
}
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $isCashier ? 'Sales Receipts' : 'My Orders'; ?> — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title"><i class="bi bi-receipt me-2" style="color:var(--primary)"></i><?php echo $isCashier ? 'Sales Receipts' : 'My Orders'; ?></h1>
      <?php if ($isCashier): ?><a href="<?= appUrl('staff/order_queue.php') ?>" class="btn btn-boba-outline"><i class="bi bi-inboxes me-1"></i>Customer Order Queue</a><?php endif; ?>
    </div>
    <div class="content-area">
      <?php if ($isCashier): ?>
      <div class="workspace-banner mb-4">
        <div><strong>Cashier Sales</strong><span>Receipts for transactions processed using your cashier account.</span></div>
        <i class="bi bi-receipt-cutoff"></i>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="stat-card compact-stat"><div class="label">Today's Sales</div><div class="value">₱<?php echo number_format($todaySales,2); ?></div><div class="sub"><?php echo $todayCount; ?> transaction(s)</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card compact-stat"><div class="label">All Receipts</div><div class="value"><?php echo count($orders); ?></div><div class="sub">Processed by you</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card compact-stat"><div class="label">Cash</div><div class="value">₱<?php echo number_format($cashTotal,2); ?></div><div class="sub">Recorded sales</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat-card compact-stat"><div class="label">GCash</div><div class="value">₱<?php echo number_format($gcashTotal,2); ?></div><div class="sub">Recorded sales</div></div></div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><?php echo $isCashier ? 'Processed Transactions' : 'Order History'; ?> (<?php echo count($orders); ?>)</span>
          <?php if ($isCashier): ?><span class="small text-muted">Newest first</span><?php endif; ?>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr><th>#</th><th>Date</th><?php if($isCashier): ?><th>Customer</th><th>Branch</th><?php endif; ?><th>Total</th><th>Payment</th><th>Type</th><th>Status</th><th></th></tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr><td colspan="<?php echo $isCashier ? 9 : 7; ?>" class="text-center text-muted py-5">
                    <i class="bi bi-receipt" style="font-size:2rem"></i>
                    <div class="mt-2"><?php echo $isCashier ? 'No customer orders have been completed by this cashier account yet.' : "You haven't placed any orders yet."; ?></div>
                    <?php if ($isCashier): ?><a href="<?= appUrl('staff/order_queue.php') ?>" class="btn btn-sm btn-primary mt-3">Open Customer Order Queue</a><?php endif; ?>
                  </td></tr>
                <?php else: foreach ($orders as $o): ?>
                <tr>
                  <td class="fw-bold">#<?php echo (int)$o['id']; ?></td>
                  <td style="font-size:.85rem;white-space:nowrap"><?php echo date('M j, Y g:i A', strtotime($o['date_created'])); ?></td>
                  <?php if($isCashier): ?><td><?php echo htmlspecialchars($o['customer_name'] ?: 'Walk-in'); ?></td><td><?php echo htmlspecialchars($o['branch_name'] ?: 'Unassigned'); ?></td><?php endif; ?>
                  <td class="fw-semibold">₱<?php echo number_format((float)$o['total_amount'],2); ?></td>
                  <td><span class="badge <?php echo $o['payment_method']==='gcash' ? 'badge-gcash' : 'bg-secondary'; ?>"><?php echo ucfirst($o['payment_method']); ?></span></td>
                  <td style="font-size:.8rem"><i class="bi bi-<?php echo $o['order_type']==='delivery' ? 'truck' : 'shop'; ?> me-1"></i><?php echo ucfirst($o['order_type']); ?></td>
                  <td><span class="badge-<?php echo htmlspecialchars($o['status']); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                  <td><a href="receipt.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-sm btn-boba-outline"><i class="bi bi-receipt"></i> View</a></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
