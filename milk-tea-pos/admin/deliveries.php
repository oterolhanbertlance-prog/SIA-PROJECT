<?php
/**
 * Deliveries — Admin view: manage online/delivery orders.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/branch_sync.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/delivery_schema.php';
requireSuperAdmin();

$db   = getDB();
$branchSyncState = syncAllHrmsBranchesToPos($db);
$pageTitle = 'Deliveries';

/* ── Ensure delivery columns exist ─────────────────────────── */
ensureDeliveryColumns($db);

/* ── Active branches, for assigning a delivery to the branch it ships
       from (and so the branch's location reflects onto delivery ops) ── */
$db->exec("CREATE TABLE IF NOT EXISTS branches (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    branch_code  VARCHAR(30)  NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    city         VARCHAR(100) DEFAULT NULL,
    latitude     DECIMAL(10,7) DEFAULT NULL,
    longitude    DECIMAL(10,7) DEFAULT NULL,
    phone        VARCHAR(30)  DEFAULT NULL,
    manager_name VARCHAR(150) DEFAULT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$branches = $db->query("SELECT id, name, city, latitude, longitude, delivery_fee, delivery_eta_minutes FROM branches WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$riders = $db->query("SELECT r.id,r.name,r.branch_id,r.status,b.name branch_name FROM delivery_riders r LEFT JOIN branches b ON b.id=r.branch_id WHERE r.status IN ('available','on_delivery') ORDER BY b.name,r.name")->fetchAll(PDO::FETCH_ASSOC);

/* ── Handle POST actions ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $txId   = (int)($_POST['tx_id'] ?? 0);

    if ($action === 'update_status' && $txId) {
        $newStatus = $_POST['delivery_status'] ?? '';
        $riderId   = (int)($_POST['rider_id'] ?? 0) ?: null;
        $rider     = '';
        if ($riderId) { $rq=$db->prepare("SELECT r.name FROM delivery_riders r JOIN transactions t ON t.branch_id=r.branch_id WHERE r.id=? AND t.id=? LIMIT 1"); $rq->execute([$riderId,$txId]); $rider=(string)($rq->fetchColumn()?:''); }
        $eta       = trim($_POST['estimated_arrival'] ?? '');
        $allowed   = ['pending','preparing','out_for_delivery','delivered','failed'];

        if (in_array($newStatus, $allowed)) {
            $deliveredAt = $newStatus === 'delivered' ? date('Y-m-d H:i:s') : null;
            $db->prepare(
                "UPDATE transactions SET delivery_status=?, rider_name=?, rider_id=?, estimated_arrival=?, delivered_at=? WHERE id=? AND order_type='delivery'"
            )->execute([$newStatus, $rider ?: null, $riderId, $eta ?: null, $deliveredAt, $txId]);
        }
        header('Location: deliveries.php?success=Status+updated');
        exit;
    }

    if ($action === 'create_delivery') {
        // Manually create a delivery order
        $custName    = trim($_POST['customer_name'] ?? '');
        $custPhone   = trim($_POST['customer_phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $delivFee    = 0.0;
        $notes       = trim($_POST['notes'] ?? '');
        $riderId     = (int)($_POST['rider_id'] ?? 0) ?: null;
        $rider       = '';
        $branchId    = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;

        if ($custName && $address && $branchId) {
            $bq=$db->prepare("SELECT delivery_fee,delivery_eta_minutes FROM branches WHERE id=? AND status='active'");$bq->execute([$branchId]);$bd=$bq->fetch();if(!$bd){header('Location: deliveries.php?success='.urlencode('Invalid branch'));exit;} $delivFee=(float)$bd['delivery_fee'];
            if($riderId){$rq=$db->prepare('SELECT name FROM delivery_riders WHERE id=? AND branch_id=? LIMIT 1');$rq->execute([$riderId,$branchId]);$rider=(string)($rq->fetchColumn()?:'');if(!$rider)$riderId=null;}
            $db->prepare(
                "INSERT INTO transactions
                 (user_id, total_amount, payment_method, amount_paid, change_amount,
                  status, order_type, branch_id, delivery_address, delivery_name, delivery_phone,
                  delivery_notes, delivery_fee, delivery_status, rider_name, rider_id)
                 VALUES (?,?,?,?,?,'completed','delivery',?,?,?,?,?,?,'pending',?,?)"
            )->execute([
                $_SESSION['user_id'] ?? null,
                $delivFee, 'cash', $delivFee, 0,
                $branchId, $address, $custName, $custPhone, $notes, $delivFee, $rider ?: null, $riderId
            ]);
            header('Location: deliveries.php?success=Delivery+order+created');
            exit;
        }
    }
}

/* ── Filters ─────────────────────────────────────────────── */
$statusFilter = trim($_GET['status'] ?? '');
$branchFilter = trim($_GET['branch_id'] ?? '');
$dateFrom     = trim($_GET['from']   ?? date('Y-m-d'));
$dateTo       = trim($_GET['to']     ?? date('Y-m-d'));
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = ["t.order_type = 'delivery'", "DATE(t.date_created) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];
if ($statusFilter) { $where[] = 't.delivery_status = ?'; $params[] = $statusFilter; }
if ($branchFilter !== '') { $where[] = 't.branch_id = ?'; $params[] = (int)$branchFilter; }
$whereStr = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM transactions t $whereStr");
$total->execute($params);
$total = (int)$total->fetchColumn();

$stmt = $db->prepare("SELECT t.*, u.username, b.name AS branch_name, b.city AS branch_city
                       FROM transactions t
                       LEFT JOIN users u ON t.user_id=u.id
                       LEFT JOIN branches b ON t.branch_id=b.id
                       $whereStr ORDER BY t.date_created DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Summary ──────────────────────────────────────────────── */
$sumWhere  = ["order_type='delivery'", "DATE(date_created) BETWEEN ? AND ?"];
$sumParams = [$dateFrom, $dateTo];
if ($branchFilter !== '') { $sumWhere[] = 'branch_id = ?'; $sumParams[] = (int)$branchFilter; }
$sumStmt = $db->prepare(
    "SELECT delivery_status, COUNT(*) as cnt, SUM(total_amount) as total
     FROM transactions WHERE " . implode(' AND ', $sumWhere) . "
     GROUP BY delivery_status"
);
$sumStmt->execute($sumParams);
$statusSummary = [];
foreach ($sumStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusSummary[$row['delivery_status']] = $row;
}

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

$callerFile = __FILE__;
require_once '../includes/header.php';
?>
<div class="d-flex" style="min-height:100vh">
<?php require_once '../includes/sidebar.php'; ?>
<div id="main-content" style="flex:1;overflow-y:auto">
  <div class="topbar">
    <h5 class="page-title"><i class="bi bi-truck me-2"></i>Deliveries</h5>
    <div class="d-flex gap-2">
      <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i>New Delivery
      </button>
    </div>
  </div>
  <div class="content-area">

  <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>

  <!-- Summary row -->
  <div class="row g-3 mb-4">
  <?php foreach ($statusColors as $st => $color): ?>
  <?php $cnt = $statusSummary[$st]['cnt'] ?? 0; ?>
    <div class="col">
      <div class="card text-center py-3 border-0 shadow-sm">
        <div class="fw-bold fs-4 text-<?= $color ?>"><?= $cnt ?></div>
        <div class="small text-muted"><?= $statusLabels[$st] ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($statusLabels as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Branch</label>
          <select name="branch_id" class="form-select form-select-sm">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $branchFilter === (string)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">From</label>
          <input type="date" name="from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small">To</label>
          <input type="date" name="to" class="form-control form-control-sm" value="<?= $dateTo ?>">
        </div>
        <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
        <div class="col-auto"><a href="deliveries.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.87rem">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date & Time</th>
            <th>Branch</th>
            <th>Customer</th>
            <th>Address</th>
            <th>Rider</th>
            <th class="text-end">Amount</th>
            <th class="text-end">Delivery Fee</th>
            <th>Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($deliveries)): ?>
        <tr><td colspan="10" class="text-center py-5 text-muted">
          <i class="bi bi-truck fs-2 d-block mb-2"></i>No delivery orders for this period.
        </td></tr>
        <?php else: foreach ($deliveries as $d): ?>
        <tr>
          <td class="font-monospace text-muted small">#<?= $d['id'] ?></td>
          <td><small><?= date('M d, Y g:i A', strtotime($d['date_created'])) ?></small></td>
          <td>
            <?php if ($d['branch_name']): ?>
              <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-building me-1"></i><?= htmlspecialchars($d['branch_name']) ?></span>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($d['delivery_name'] ?: ($d['username'] ?: '—')) ?></div>
            <?php if ($d['delivery_phone']): ?>
            <div class="text-muted small"><?= htmlspecialchars($d['delivery_phone']) ?></div>
            <?php endif; ?>
          </td>
          <td style="max-width:180px">
            <div class="text-truncate" title="<?= htmlspecialchars($d['delivery_address'] ?? '') ?>">
              <?= htmlspecialchars($d['delivery_address'] ?? '—') ?>
            </div>
            <?php if ($d['delivery_notes']): ?>
            <div class="text-muted small"><?= htmlspecialchars($d['delivery_notes']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($d['rider_name'] ?: '—') ?></td>
          <td class="text-end fw-semibold">₱<?= number_format($d['total_amount'], 2) ?></td>
          <td class="text-end text-muted">₱<?= number_format($d['delivery_fee'] ?? 0, 2) ?></td>
          <td>
            <?php $sc = $statusColors[$d['delivery_status']] ?? 'secondary'; ?>
            <span class="badge bg-<?= $sc ?>"><?= $statusLabels[$d['delivery_status']] ?? ucfirst($d['delivery_status']) ?></span>
            <?php if ($d['delivered_at']): ?>
            <div class="text-muted" style="font-size:.7rem"><?= date('g:i A', strtotime($d['delivered_at'])) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                    data-bs-toggle="modal" data-bs-target="#statusModal"
                    data-id="<?= $d['id'] ?>"
                    data-status="<?= htmlspecialchars($d['delivery_status']) ?>" data-branch="<?= (int)($d['branch_id'] ?? 0) ?>" data-rider-id="<?= (int)($d['rider_id'] ?? 0) ?>"
                    data-rider="<?= htmlspecialchars($d['rider_name'] ?? '') ?>"
                    data-eta="<?= $d['estimated_arrival'] ? date('Y-m-d\TH:i', strtotime($d['estimated_arrival'])) : '' ?>">
              <i class="bi bi-pencil-square"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  </div><!-- /.content-area -->
</div><!-- /#main-content -->
</div><!-- /.d-flex -->

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="tx_id" id="statusTxId">
      <div class="modal-header"><h5 class="modal-title">Update Delivery</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Status</label>
          <select name="delivery_status" id="statusSelect" class="form-select form-select-sm">
            <?php foreach ($statusLabels as $v => $l): ?>
            <option value="<?= $v ?>"><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Rider / Courier</label>
          <select name="rider_id" id="riderInput" class="form-select form-select-sm"><option value="">Unassigned</option><?php foreach($riders as $r):?><option value="<?=$r['id']?>" data-branch="<?=$r['branch_id']?>"><?=htmlspecialchars($r['name'].' — '.($r['branch_name']??'Unassigned'))?></option><?php endforeach;?></select>
        </div>
        <div class="mb-1">
          <label class="form-label small">ETA</label>
          <input type="datetime-local" name="estimated_arrival" id="etaInput" class="form-control form-control-sm">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
      </div>
    </form>
    </div>
  </div>
</div>

<!-- Create Delivery Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
    <form method="POST">
      <input type="hidden" name="action" value="create_delivery">
      <div class="modal-header"><h5 class="modal-title">New Delivery Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label small">Customer Name *</label>
            <input type="text" name="customer_name" class="form-control form-control-sm" required placeholder="Full name">
          </div>
          <div class="col-md-5">
            <label class="form-label small">Phone</label>
            <input type="text" name="customer_phone" class="form-control form-control-sm" placeholder="09XXXXXXXXX">
          </div>
          <div class="col-12">
            <label class="form-label small">Delivery Address *</label>
            <input type="text" name="address" class="form-control form-control-sm" required placeholder="Complete address">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Ships From Branch</label>
            <select name="branch_id" id="createBranch" class="form-select form-select-sm" required onchange="filterCreateRiders()">
              <option value="">Select branch…</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?><?= $b['city'] ? ' — '.htmlspecialchars($b['city']) : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($branches)): ?>
            <div class="form-text text-warning">No active branches yet — add one on the Branches page to enable this.</div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Delivery Fee</label><div class="form-control form-control-sm bg-light">Uses the selected branch fee automatically</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Assigned Rider</label>
            <select name="rider_id" id="createRider" class="form-select form-select-sm"><option value="">Unassigned — driver can claim</option><?php foreach($riders as $r):?><option value="<?=$r['id']?>" data-branch="<?=$r['branch_id']?>"><?=htmlspecialchars($r['name'])?></option><?php endforeach;?></select>
          </div>
          <div class="col-12">
            <label class="form-label small">Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Landmark, special instructions…">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-truck me-1"></i>Create Delivery</button>
      </div>
    </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->

<!-- Live delivered-order popup -->
<div class="modal fade" id="deliveredPopup" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body text-center p-4">
        <div class="display-4 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="fw-bold">Delivery Completed</h4>
        <p class="mb-1" id="deliveredPopupText"></p>
        <div class="small text-muted" id="deliveredPopupMeta"></div>
        <button type="button" class="btn btn-success mt-3 px-4" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-target="#statusModal"]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('statusTxId').value   = this.dataset.id;
        document.getElementById('statusSelect').value = this.dataset.status;
        const rs=document.getElementById('riderInput'); const branch=this.dataset.branch; [...rs.options].forEach(o=>o.hidden=o.value && o.dataset.branch!==branch); rs.value=this.dataset.riderId||'';
        document.getElementById('etaInput').value     = this.dataset.eta;
    });
});
function filterCreateRiders(){const b=document.getElementById('createBranch').value;const r=document.getElementById('createRider');[...r.options].forEach(o=>o.hidden=o.value&&o.dataset.branch!==b);if(r.selectedOptions[0]?.hidden)r.value='';}
</script>

<script>
(() => {
  let lastSeen = '';
  let busy = false;
  const popupEl = document.getElementById('deliveredPopup');
  const popup = popupEl ? new bootstrap.Modal(popupEl) : null;

  async function checkDelivered() {
    if (busy || !popup) return;
    busy = true;
    try {
      const res = await fetch('delivery_notifications.php?after=' + encodeURIComponent(lastSeen), {cache:'no-store'});
      const data = await res.json();
      if (!data.ok) return;
      if (data.server_time) lastSeen = data.server_time;
      if (data.deliveries && data.deliveries.length) {
        const d = data.deliveries[data.deliveries.length - 1];
        document.getElementById('deliveredPopupText').textContent =
          `Order #${d.id} for ${d.delivery_name || 'Customer'} was successfully delivered.`;
        document.getElementById('deliveredPopupMeta').textContent =
          `${d.rider_name || 'Driver'} • ₱${Number(d.total_amount || 0).toFixed(2)}${d.delivery_address ? ' • ' + d.delivery_address : ''}`;
        popup.show();
      }
    } catch (e) { console.debug('Delivery notification check failed', e); }
    finally { busy = false; }
  }
  setInterval(checkDelivered, 5000);
  setTimeout(checkDelivered, 1500);
})();
</script>

</body></html>
