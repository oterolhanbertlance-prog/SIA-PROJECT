<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('super_admin', '../auth/login.php');

$db = getDB();

// --- 1. HANDLE REFUND PROCESS ---
if (isset($_GET['refund']) && is_numeric($_GET['refund'])) {
    $txId = (int)$_GET['refund'];
    $stmt = $db->prepare('SELECT * FROM transactions WHERE id=? AND status="completed"');
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    if ($tx) {
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE transactions SET status="refunded" WHERE id=?')->execute([$txId]);
            $details = $db->prepare('SELECT * FROM transaction_details WHERE transaction_id=?');
            $details->execute([$txId]);
            foreach ($details->fetchAll() as $d) {
                $db->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$d['quantity'], $d['product_id']]);
            }
            $db->commit();
            $_SESSION['flash_success'] = "Transaction #$txId has been refunded and stock restored.";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Refund failed: ' . $e->getMessage();
        }
    }
    header('Location: transactions.php'); exit;
}

// --- 2. NEW: HANDLE DELETE PROCESS ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $txId = (int)$_GET['delete'];
    
    $db->beginTransaction();
    try {
        // First delete dependent items from child details table
        $stmtDetails = $db->prepare('DELETE FROM transaction_details WHERE transaction_id = ?');
        $stmtDetails->execute([$txId]);
        
        // Then delete the master row record from transactions table
        $stmtTx = $db->prepare('DELETE FROM transactions WHERE id = ?');
        $stmtTx->execute([$txId]);
        
        $db->commit();
        $_SESSION['flash_success'] = "Transaction #$txId has been permanently deleted.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash_error'] = 'Delete failed: ' . $e->getMessage();
    }
    header('Location: transactions.php'); exit;
}

// --- 3. DATA PAGINATION & FILTER LOGIC ---
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status']    ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$sql    = 'SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id=t.user_id WHERE 1=1';
$params = [];
if ($search)   { $sql .= ' AND (t.id LIKE ? OR u.username LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status)   { $sql .= ' AND t.status=?';              $params[] = $status; }
if ($dateFrom) { $sql .= ' AND DATE(t.date_created)>=?'; $params[] = $dateFrom; }
if ($dateTo)   { $sql .= ' AND DATE(t.date_created)<=?'; $params[] = $dateTo; }

$countStmt = $db->prepare('SELECT COUNT(*) FROM (' . $sql . ') sub');
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$sql   .= ' ORDER BY t.date_created DESC LIMIT ? OFFSET ?';
$params[] = $perPage; $params[] = $offset;
$stmt  = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Transactions — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head><body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title"><i class="bi bi-receipt-cutoff me-2" style="color:var(--boba-amber)"></i>Transactions</h1>
    </div>
    <div class="content-area">

      <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($_SESSION['flash_success']); ?></div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?php echo htmlspecialchars($_SESSION['flash_error']); ?></div>
        <?php unset($_SESSION['flash_error']); ?>
      <?php endif; ?>

      <form method="GET" class="card card-body mb-4">
        <div class="row g-2 align-items-end">
          <div class="col-sm-3">
            <label class="form-label small fw-bold mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="#ID or clerk name" value="<?php echo htmlspecialchars($search); ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label small fw-bold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="completed" <?php if ($status==='completed') echo 'selected'; ?>>Completed</option>
              <option value="refunded"  <?php if ($status==='refunded')  echo 'selected'; ?>>Refunded</option>
            </select>
          </div>
          <div class="col-sm-2">
            <label class="form-label small fw-bold mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateFrom); ?>">
          </div>
          <div class="col-sm-2">
            <label class="form-label small fw-bold mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateTo); ?>">
          </div>
          <div class="col-sm-3 d-flex gap-2">
            <button class="btn btn-boba btn-sm">Filter</button>
            <a href="transactions.php" class="btn btn-boba-outline btn-sm">Reset</a>
          </div>
        </div>
      </form>

      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>All Transactions</span>
          <span class="text-muted" style="font-size:.8rem"><?php echo $total; ?> record(s)</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>Clerk</th><th>Total</th><th>Paid</th><th>Change</th><th>Payment</th><th>Order</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (empty($transactions)): ?>
                  <tr><td colspan="10" class="text-center text-muted py-4">No transactions found.</td></tr>
                <?php else: foreach ($transactions as $tx): ?>
                <tr>
                  <td class="fw-bold">
                    <button class="btn btn-link p-0 fw-bold" style="color:var(--boba-amber)"
                            onclick="loadDetails(<?php echo $tx['id']; ?>)"
                            data-bs-toggle="modal" data-bs-target="#detailsModal">
                      #<?php echo $tx['id']; ?>
                    </button>
                  </td>
                  <td><?php echo htmlspecialchars($tx['username'] ?? '—'); ?></td>
                  <td>₱<?php echo number_format($tx['total_amount'],2); ?></td>
                  <td>₱<?php echo number_format($tx['amount_paid'],2); ?></td>
                  <td>₱<?php echo number_format($tx['change_amount'],2); ?></td>
                  <td>
                    <span class="badge <?php echo $tx['payment_method']==='gcash' ? 'badge-gcash' : 'bg-secondary'; ?>">
                      <?php echo ucfirst($tx['payment_method']); ?>
                    </span>
                  </td>
                  <td style="font-size:.78rem">
                    <i class="bi bi-<?php echo $tx['order_type']==='delivery' ? 'truck' : 'shop'; ?> me-1"></i><?php echo ucfirst($tx['order_type']); ?>
                    <?php if ($tx['order_type']==='delivery' && $tx['delivery_address']): ?>
                      <div class="text-muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($tx['delivery_address']); ?>">
                        <?php echo htmlspecialchars($tx['delivery_address']); ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge-<?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                  <td style="font-size:.8rem"><?php echo date('M j, Y g:i A', strtotime($tx['date_created'])); ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if ($tx['status']==='completed'): ?>
                        <a href="?refund=<?php echo $tx['id']; ?>" class="btn btn-sm btn-outline-warning"
                           onclick="return confirm('Refund transaction #<?php echo $tx['id']; ?>? Stock will be restored.')">
                          <i class="bi bi-arrow-counterclockwise"></i> Refund
                        </a>
                      <?php endif; ?>
                      
                      <a href="?delete=<?php echo $tx['id']; ?>" class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Are you sure you want to permanently delete transaction #<?php echo $tx['id']; ?>? This action cannot be undone.')">
                        <i class="bi bi-trash"></i> Delete
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($pages > 1): ?>
        <div class="card-footer d-flex justify-content-center py-2">
          <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
              <li class="page-item <?php if ($p===$page) echo 'active'; ?>">
                <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><?php echo $p; ?></a>
              </li>
            <?php endfor; ?>
          </ul></nav>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:.85rem">
      <div class="modal-header"><h5 class="modal-title fw-bold" id="detailsTitle">Transaction Details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="detailsBody"><div class="text-center py-4"><div class="spinner-border text-warning"></div></div></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadDetails(id) {
  document.getElementById('detailsTitle').textContent = 'Transaction #' + id;
  document.getElementById('detailsBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';
  fetch('transaction_details_ajax.php?id=' + id)
    .then(r => r.text()).then(html => document.getElementById('detailsBody').innerHTML = html);
}
</script>
</body></html>