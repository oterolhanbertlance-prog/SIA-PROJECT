<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('super_admin', '../auth/login.php');

$db   = getDB();
$type = $_GET['type'] ?? 'daily';

switch ($type) {
    case 'monthly':
        $rows = $db->query(
            'SELECT DATE_FORMAT(date_created,"%Y-%m") as period,
                    COUNT(*) as orders, SUM(total_amount) as revenue
             FROM transactions WHERE status="completed"
             GROUP BY period ORDER BY period DESC LIMIT 12'
        )->fetchAll();
        $periodLabel = 'Month';
        break;
    case 'yearly':
        $rows = $db->query(
            'SELECT YEAR(date_created) as period,
                    COUNT(*) as orders, SUM(total_amount) as revenue
             FROM transactions WHERE status="completed"
             GROUP BY period ORDER BY period DESC LIMIT 5'
        )->fetchAll();
        $periodLabel = 'Year';
        break;
    default:
        $type = 'daily';
        $rows = $db->query(
            'SELECT DATE(date_created) as period,
                    COUNT(*) as orders, SUM(total_amount) as revenue
             FROM transactions WHERE status="completed"
             GROUP BY period ORDER BY period DESC LIMIT 30'
        )->fetchAll();
        $periodLabel = 'Date';
        break;
}

$maxRevenue = ($rows && max(array_column($rows,'revenue')) > 0)
              ? max(array_column($rows,'revenue')) : 1;
$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reports — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title">
        <i class="bi bi-bar-chart-line-fill me-2" style="color:var(--boba-amber)"></i>Sales Reports
      </h1>
    </div>
    <div class="content-area">

      <div class="category-tabs mb-4">
        <a href="?type=daily"
           class="category-tab <?php if ($type==='daily') echo 'active'; ?>">
          <i class="bi bi-calendar-day me-1"></i>Daily
        </a>
        <a href="?type=monthly"
           class="category-tab <?php if ($type==='monthly') echo 'active'; ?>">
          <i class="bi bi-calendar-month me-1"></i>Monthly
        </a>
        <a href="?type=yearly"
           class="category-tab <?php if ($type==='yearly') echo 'active'; ?>">
          <i class="bi bi-calendar me-1"></i>Yearly
        </a>
      </div>

      <?php if (empty($rows)): ?>
        <div class="card card-body text-center py-5 text-muted">
          <i class="bi bi-bar-chart" style="font-size:3rem"></i>
          <p class="mt-3">No data available yet. Complete some orders first.</p>
        </div>

      <?php else:
        $totalOrders  = array_sum(array_column($rows,'orders'));
        $totalRevenue = array_sum(array_column($rows,'revenue'));
        $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
      ?>

      <div class="row g-3 mb-4">
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="icon" style="background:#fff3e0">
              <i class="bi bi-bag-fill" style="color:#e67e22"></i>
            </div>
            <div>
              <div class="label">Total Orders</div>
              <div class="value"><?php echo $totalOrders; ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="icon" style="background:#e8f5e9">
              <i class="bi bi-cash-coin" style="color:var(--boba-success)"></i>
            </div>
            <div>
              <div class="label">Total Revenue</div>
              <div class="value">&#8369;<?php echo number_format($totalRevenue,2); ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="stat-card">
            <div class="icon" style="background:#e8eaf6">
              <i class="bi bi-calculator-fill" style="color:#5c6bc0"></i>
            </div>
            <div>
              <div class="label">Avg Order Value</div>
              <div class="value">&#8369;<?php echo number_format($avgOrder,2); ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Revenue Chart</div>
        <div class="card-body">
          <?php foreach ($rows as $r): ?>
            <div class="report-bar">
              <div class="bar-label" style="width:100px">
                <?php echo htmlspecialchars($r['period']); ?>
              </div>
              <div class="bar-track">
                <div class="bar-fill"
                     style="width:<?php echo round($r['revenue']/$maxRevenue*100); ?>%">
                </div>
              </div>
              <div class="bar-value" style="width:130px;padding-left:.5rem;text-align:left">
                &#8369;<?php echo number_format($r['revenue'],2); ?>
              </div>
              <div style="font-size:.75rem;color:var(--boba-muted);width:65px;text-align:right">
                <?php echo $r['orders']; ?> orders
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?php echo ucfirst($type); ?> Breakdown</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th><?php echo $periodLabel; ?></th>
                  <th class="text-center">Orders</th>
                  <th class="text-end">Revenue</th>
                  <th class="text-end">Avg Order</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['period']); ?></td>
                  <td class="text-center"><?php echo $r['orders']; ?></td>
                  <td class="text-end">&#8369;<?php echo number_format($r['revenue'],2); ?></td>
                  <td class="text-end">&#8369;<?php echo number_format($r['revenue']/$r['orders'],2); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="fw-bold table-light">
                <tr>
                  <td>Total</td>
                  <td class="text-center"><?php echo $totalOrders; ?></td>
                  <td class="text-end">&#8369;<?php echo number_format($totalRevenue,2); ?></td>
                  <td class="text-end">&#8369;<?php echo number_format($avgOrder,2); ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>