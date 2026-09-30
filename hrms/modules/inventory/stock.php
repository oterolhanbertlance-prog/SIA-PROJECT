<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole('admin', 'hr', 'inventory_staff');

$pageTitle = 'Stock Management';

// ── Handle form actions ────────────────────────────────────────────
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $db = getDB();

    try {
        if ($action === 'add') {
            $name     = sanitize($_POST['name'] ?? '');
            $sku      = sanitize($_POST['sku'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $qty      = (int)($_POST['quantity'] ?? 0);
            $unit     = sanitize($_POST['unit'] ?? 'pcs');
            $reorder  = (int)($_POST['reorder_level'] ?? 0);
            $cost     = (float)($_POST['cost_price'] ?? 0);
            $notes    = sanitize($_POST['notes'] ?? '');

            if (!$name) throw new Exception('Product name is required.');

            $stmt = $db->prepare("INSERT INTO inventory_items (name, sku, category, quantity, unit, reorder_level, cost_price, notes, created_by)
                                  VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $sku, $category, $qty, $unit, $reorder, $cost, $notes, $_SESSION['user_id']]);
            auditLog('CREATE', 'inventory', "Added stock item: $name");
            $msg = 'Item added successfully.';

        } elseif ($action === 'adjust') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $adjust = (int)($_POST['adjustment'] ?? 0);
            $reason = sanitize($_POST['reason'] ?? '');

            if (!$itemId) throw new Exception('Invalid item.');
            $db->prepare("UPDATE inventory_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
               ->execute([$adjust, $itemId]);
            $db->prepare("INSERT INTO inventory_transactions (item_id, adjustment, reason, created_by) VALUES (?,?,?,?)")
               ->execute([$itemId, $adjust, $reason, $_SESSION['user_id']]);
            auditLog('UPDATE', 'inventory', "Adjusted stock for item #$itemId by $adjust. Reason: $reason");
            $msg = 'Stock adjusted successfully.';

        } elseif ($action === 'delete') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            if (!$itemId) throw new Exception('Invalid item.');
            $db->prepare("DELETE FROM inventory_items WHERE id = ?")->execute([$itemId]);
            auditLog('DELETE', 'inventory', "Deleted stock item #$itemId");
            $msg = 'Item deleted.';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'danger';
    }
}

// ── Fetch data ─────────────────────────────────────────────────────
$db = getDB();
$search   = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// Ensure tables exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_items (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(150) NOT NULL,
        sku          VARCHAR(60)  DEFAULT NULL,
        category     VARCHAR(100) DEFAULT 'General',
        quantity     INT          NOT NULL DEFAULT 0,
        unit         VARCHAR(30)  NOT NULL DEFAULT 'pcs',
        reorder_level INT         NOT NULL DEFAULT 0,
        cost_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes        TEXT         DEFAULT NULL,
        created_by   INT          DEFAULT NULL,
        created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        item_id      INT NOT NULL,
        adjustment   INT NOT NULL DEFAULT 0,
        reason       VARCHAR(255) DEFAULT NULL,
        created_by   INT DEFAULT NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Build query
$where  = 'WHERE 1=1';
$params = [];
if ($search) { $where .= ' AND (i.name LIKE ? OR i.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($category) { $where .= ' AND i.category = ?'; $params[] = $category; }

try {
    $total = (int)$db->prepare("SELECT COUNT(*) FROM inventory_items i $where")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM inventory_items i $where")->execute($params) : 0;
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM inventory_items i $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM inventory_items i $where ORDER BY i.name ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $catStmt = $db->query("SELECT DISTINCT category FROM inventory_items ORDER BY category");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $items = [];
    $total = 0;
    $categories = [];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-boxes me-2"></i>Stock Management</h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
        <i class="bi bi-plus-lg me-1"></i>Add Item
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss">
    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<?php
try {
    $db2 = getDB();
    $totalItems  = (int)$db2->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
    $lowStock    = (int)$db2->query("SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level AND reorder_level > 0")->fetchColumn();
    $outOfStock  = (int)$db2->query("SELECT COUNT(*) FROM inventory_items WHERE quantity = 0")->fetchColumn();
    $totalValue  = (float)$db2->query("SELECT COALESCE(SUM(quantity * cost_price),0) FROM inventory_items")->fetchColumn();
} catch (Throwable $e) { $totalItems=$lowStock=$outOfStock=0; $totalValue=0; }
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary"><?= $totalItems ?></div>
                <div class="text-muted small">Total Items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning"><?= $lowStock ?></div>
                <div class="text-muted small">Low Stock</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger"><?= $outOfStock ?></div>
                <div class="text-muted small">Out of Stock</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success"><?= money($totalValue) ?></div>
                <div class="text-muted small">Total Value</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or SKU…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Items Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="text-center">Quantity</th>
                        <th>Unit</th>
                        <th class="text-center">Reorder Lvl</th>
                        <th>Cost Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No items found. <a href="#" data-bs-toggle="modal" data-bs-target="#addItemModal">Add one now.</a></td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <?php
                    $stockStatus = 'success';
                    $stockLabel  = 'In Stock';
                    if ($item['quantity'] == 0) { $stockStatus = 'danger'; $stockLabel = 'Out of Stock'; }
                    elseif ($item['reorder_level'] > 0 && $item['quantity'] <= $item['reorder_level']) { $stockStatus = 'warning'; $stockLabel = 'Low Stock'; }
                ?>
                <tr class="<?= $item['quantity'] == 0 ? 'table-danger' : ($item['reorder_level'] > 0 && $item['quantity'] <= $item['reorder_level'] ? 'table-warning' : '') ?>">
                    <td><strong><?= htmlspecialchars($item['name']) ?></strong>
                        <?php if ($item['notes']): ?><br><small class="text-muted"><?= htmlspecialchars(substr($item['notes'],0,50)) ?></small><?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($item['sku'] ?: '—') ?></code></td>
                    <td><?= htmlspecialchars($item['category'] ?: '—') ?></td>
                    <td class="text-center fw-bold"><?= $item['quantity'] ?></td>
                    <td><?= htmlspecialchars($item['unit']) ?></td>
                    <td class="text-center"><?= $item['reorder_level'] ?></td>
                    <td><?= money($item['cost_price']) ?></td>
                    <td><span class="badge bg-<?= $stockStatus ?>"><?= $stockLabel ?></span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" title="Adjust Stock"
                            onclick="openAdjust(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['quantity'] ?>)">
                            <i class="bi bi-plus-slash-minus"></i>
                        </button>
                        <?php if (hasRole('admin','hr')): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= paginate($total, $perPage, $page, '?' . http_build_query(['search'=>$search,'category'=>$category])) ?>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Add Stock Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SKU / Code</label>
                            <input type="text" name="sku" class="form-control" placeholder="e.g. ITM-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g. Raw Materials" list="catList">
                            <datalist id="catList">
                                <?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Unit</label>
                            <input type="text" name="unit" class="form-control" value="pcs" list="unitList">
                            <datalist id="unitList"><option>pcs<option>kg<option>liters<option>boxes<option>bags<option>bottles</datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cost Price (₱)</label>
                            <input type="number" name="cost_price" class="form-control" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" name="item_id" id="adjustItemId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-slash-minus me-2"></i>Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-1" id="adjustItemName"></p>
                    <p class="text-muted small">Current quantity: <strong id="adjustCurrentQty"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Adjustment (+ to add, − to reduce)</label>
                        <input type="number" name="adjustment" id="adjustQty" class="form-control" required placeholder="e.g. +10 or -5">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Received delivery, spoilage…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjust(id, name, qty) {
    document.getElementById('adjustItemId').value = id;
    document.getElementById('adjustItemName').textContent = name;
    document.getElementById('adjustCurrentQty').textContent = qty;
    document.getElementById('adjustQty').value = '';
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
