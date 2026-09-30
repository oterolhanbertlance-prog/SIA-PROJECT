<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('super_admin', '../auth/login.php');

$db    = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='adjust') {
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');
    if ($amount == 0) {
        $error = 'Enter a non-zero amount.';
    } else {
        $current = (float)($db->query('SELECT balance FROM cash_fund WHERE id=1')->fetchColumn() ?: 0);
        if ($amount < 0 && abs($amount) > $current) {
            $error = 'Cannot remove more than the current balance (₱' . number_format($current,2) . ').';
        } else {
            $db->prepare('UPDATE cash_fund SET balance = balance + ? WHERE id=1')->execute([$amount]);
            $success = ($amount > 0 ? 'Added' : 'Removed') . ' ₱' . number_format(abs($amount),2)
                . ' from the change fund' . ($note ? ' (' . htmlspecialchars($note) . ')' : '') . '.';
        }
    }
}

$balance = (float)($db->query('SELECT balance FROM cash_fund WHERE id=1')->fetchColumn() ?: 0);

// Revenue split by payment method, so the admin can see actual funds collected
// (cash sitting in the drawer vs. GCash that landed in the bank/e-wallet).
$today  = date('Y-m-d');
$byMethodToday = $db->prepare(
    "SELECT payment_method, COALESCE(SUM(total_amount),0) as total
     FROM transactions WHERE DATE(date_created)=? AND status='completed' GROUP BY payment_method"
);
$byMethodToday->execute([$today]);
$byMethodToday = $byMethodToday->fetchAll(PDO::FETCH_KEY_PAIR);

$allTimeRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE status='completed'")->fetchColumn();
$allTimeCash    = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE status='completed' AND payment_method='cash'")->fetchColumn();
$allTimeGcash   = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE status='completed' AND payment_method='gcash'")->fetchColumn();

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cash Fund — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title"><i class="bi bi-cash-coin me-2" style="color:var(--boba-amber)"></i>Cash Fund &amp; Funds</h1>
    </div>
    <div class="content-area">

      <?php if ($error):   ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#fff3e0"><i class="bi bi-cash-coin" style="color:#e67e22"></i></div>
            <div>
              <div class="label">Change Fund (drawer)</div>
              <div class="value">₱<?php echo number_format($balance,2); ?></div>
              <div class="sub">Available for giving change</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#e8f5e9"><i class="bi bi-graph-up-arrow" style="color:var(--boba-success)"></i></div>
            <div>
              <div class="label">Total Funds (all-time revenue)</div>
              <div class="value">₱<?php echo number_format($allTimeRevenue,2); ?></div>
              <div class="sub">Cash + GCash combined</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#e3f2fd"><i class="bi bi-cash-stack" style="color:#1976d2"></i></div>
            <div>
              <div class="label">Cash Collected</div>
              <div class="value">₱<?php echo number_format($allTimeCash,2); ?></div>
              <div class="sub">All-time</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="icon" style="background:#f3e5f5"><i class="bi bi-phone" style="color:#8e24aa"></i></div>
            <div>
              <div class="label">GCash Collected</div>
              <div class="value">₱<?php echo number_format($allTimeGcash,2); ?></div>
              <div class="sub">All-time</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header">Top Up / Withdraw Change Fund</div>
            <div class="card-body">
              <p style="font-size:.85rem;color:var(--boba-muted)">
                The change fund is the small cash on hand used to give change at checkout.
                Cash sales automatically deduct the change given; use this form when you
                physically add more bills/coins (e.g. at the start of a shift) or take
                cash out (e.g. a bank deposit).
              </p>
              <form method="POST">
                <input type="hidden" name="action" value="adjust">
                <div class="mb-3">
                  <label class="form-label fw-bold">Amount (negative to withdraw)</label>
                  <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 1000 or -500" required>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold">Note (optional)</label>
                  <input type="text" name="note" class="form-control" placeholder="e.g. Morning shift float">
                </div>
                <button class="btn btn-boba w-100">Apply</button>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card h-100">
            <div class="card-header">Today's Collections by Payment Method</div>
            <div class="card-body">
              <div class="d-flex justify-content-between mb-2">
                <span><i class="bi bi-cash-stack me-2"></i>Cash</span>
                <span class="fw-bold">₱<?php echo number_format($byMethodToday['cash'] ?? 0, 2); ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span><i class="bi bi-phone me-2"></i>GCash</span>
                <span class="fw-bold">₱<?php echo number_format($byMethodToday['gcash'] ?? 0, 2); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
