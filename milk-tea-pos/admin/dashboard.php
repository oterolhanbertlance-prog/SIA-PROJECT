<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('super_admin', '../auth/login.php');

$db = getDB();

$today     = date('Y-m-d');
$todayData = $db->prepare('SELECT COUNT(*) as orders, COALESCE(SUM(total_amount),0) as revenue FROM transactions WHERE DATE(date_created)=? AND status="completed"');
$todayData->execute([$today]);
$todayRow  = $todayData->fetch();

$month     = date('Y-m');
$monthData = $db->prepare('SELECT COUNT(*) as orders, COALESCE(SUM(total_amount),0) as revenue FROM transactions WHERE DATE_FORMAT(date_created,"%Y-%m")=? AND status="completed"');
$monthData->execute([$month]);
$monthRow  = $monthData->fetch();

$custCount = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();

$topProducts = $db->prepare(
    'SELECT p.name, SUM(td.quantity) as qty, SUM(td.subtotal) as revenue
     FROM transaction_details td
     JOIN transactions t ON t.id=td.transaction_id
     JOIN products p ON p.id=td.product_id
     WHERE DATE_FORMAT(t.date_created,"%Y-%m")=? AND t.status="completed"
     GROUP BY p.id ORDER BY qty DESC LIMIT 5'
);
$topProducts->execute([$month]);
$topProducts = $topProducts->fetchAll();

$recentTx = $db->query('SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.date_created DESC LIMIT 5')->fetchAll();
$fundBalance = (float)($db->query('SELECT balance FROM cash_fund WHERE id=1')->fetchColumn() ?: 0);

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--boba-amber)"></i>Dashboard</h1>
      <span style="font-size:.82rem;color:var(--boba-muted)"><?php echo date('F j, Y'); ?></span>
    </div>
    <div class="content-area">
      <div class="workspace-banner mb-4"><div><strong>Super Admin Workspace</strong><span>Management, sales analytics, branches, users, finance, and logistics.</span></div><i class="bi bi-shield-check"></i></div>

      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#fff3e0"><i class="bi bi-bag-fill" style="color:#e67e22"></i></div>
            <div>
              <div class="label">Today's Orders</div>
              <div class="value"><?php echo $todayRow['orders']; ?></div>
              <div class="sub"><?php echo date('M j'); ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#e8f5e9"><i class="bi bi-currency-dollar" style="color:var(--boba-success)"></i></div>
            <div>
              <div class="label">Today's Revenue</div>
              <div class="value">₱<?php echo number_format($todayRow['revenue'],2); ?></div>
              <div class="sub">Cash sales</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#e8eaf6"><i class="bi bi-calendar-month-fill" style="color:#5c6bc0"></i></div>
            <div>
              <div class="label">Monthly Revenue</div>
              <div class="value">₱<?php echo number_format($monthRow['revenue'],2); ?></div>
              <div class="sub"><?php echo date('F Y'); ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#fce4ec"><i class="bi bi-people-fill" style="color:#e91e63"></i></div>
            <div>
              <div class="label">Total Clerks</div>
              <div class="value"><?php echo $custCount; ?></div>
              <div class="sub">Active accounts</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <a href="fund.php" class="text-decoration-none">
            <div class="stat-card">
              <div class="icon" style="background:#fff8e1"><i class="bi bi-cash-coin" style="color:#f9a825"></i></div>
              <div>
                <div class="label">Change Fund (drawer)</div>
                <div class="value" style="color:var(--boba-dark)">₱<?php echo number_format($fundBalance,2); ?></div>
                <div class="sub">Manage in Cash Fund</div>
              </div>
            </div>
          </a>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-trophy-fill me-2" style="color:var(--boba-amber)"></i>Top Selling Products This Month</div>
        <div class="card-body">
          <?php if (empty($topProducts)): ?>
            <p class="text-muted text-center py-3">No sales yet this month.</p>
          <?php else:
            $maxQty = max(array_column($topProducts, 'qty'));
            foreach ($topProducts as $p): ?>
            <div class="report-bar">
              <div class="bar-label" style="width:150px;font-size:.78rem"><?php echo htmlspecialchars(mb_strimwidth($p['name'],0,22,'…')); ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?php echo $maxQty>0?round($p['qty']/$maxQty*100):0; ?>%"></div></div>
              <div class="bar-value"><?php echo $p['qty']; ?> sold</div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-receipt-cutoff me-2" style="color:var(--boba-amber)"></i>Recent Transactions</span>
          <a href="transactions.php" class="btn btn-boba-outline btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>Clerk</th><th>Total</th><th>Paid</th><th>Change</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                  <td class="fw-bold">#<?php echo $tx['id']; ?></td>
                  <td><?php echo htmlspecialchars($tx['username'] ?? '—'); ?></td>
                  <td>₱<?php echo number_format($tx['total_amount'],2); ?></td>
                  <td>₱<?php echo number_format($tx['amount_paid'],2); ?></td>
                  <td>₱<?php echo number_format($tx['change_amount'],2); ?></td>
                  <td>
                    <span class="badge <?php echo $tx['payment_method']==='gcash' ? 'badge-gcash' : 'bg-secondary'; ?>">
                      <?php echo ucfirst($tx['payment_method']); ?>
                    </span>
                  </td>
                  <td><span class="badge-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                  <td style="font-size:.8rem"><?php echo date('M j, g:i A', strtotime($tx['date_created'])); ?></td>
                </tr>
                <?php endforeach; ?>
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