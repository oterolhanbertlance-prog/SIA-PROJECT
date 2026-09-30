<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('inventory_staff', '../auth/login.php');

$db    = getDB();
$error = $success = '';
// Repair legacy/stale availability flags so restocked ingredients become usable again.
$db->exec("UPDATE ingredients SET is_available = IF(stock_quantity > 0,1,0)");

/* ── Self-healing schema: add stock tracking columns if missing ───── */
$db->exec("ALTER TABLE ingredients ADD COLUMN IF NOT EXISTS stock_quantity DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unit");
$db->exec("ALTER TABLE ingredients ADD COLUMN IF NOT EXISTS low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 5 AFTER stock_quantity");

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create') {
    $name      = trim($_POST['name'] ?? '');
    $unit      = trim($_POST['unit'] ?? 'pcs');
    $stock     = max(0, (float)($_POST['stock_quantity'] ?? 0));
    $threshold = max(0, (float)($_POST['low_stock_threshold'] ?? 5));
    if (!$name) {
        $error = 'Ingredient name is required.';
    } else {
        $db->prepare('INSERT INTO ingredients (name, unit, stock_quantity, low_stock_threshold, is_available) VALUES (?,?,?,?,1)')
           ->execute([$name, $unit, $stock, $threshold]);
        $success = 'Ingredient "' . htmlspecialchars($name) . '" added.';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    $id        = (int)$_POST['id'];
    $name      = trim($_POST['name'] ?? '');
    $unit      = trim($_POST['unit'] ?? 'pcs');
    $stock     = max(0, (float)($_POST['stock_quantity'] ?? 0));
    $threshold = max(0, (float)($_POST['low_stock_threshold'] ?? 5));
    if (!$name) {
        $error = 'Ingredient name is required.';
    } else {
        $db->prepare('UPDATE ingredients SET name=?, unit=?, stock_quantity=?, low_stock_threshold=?, is_available=IF(? > 0,1,0) WHERE id=?')
           ->execute([$name, $unit, $stock, $threshold, $stock, $id]);
        $success = 'Ingredient updated.';
    }
}

// Quick +/- stock adjustment straight from the table (e.g. restock delivery arrives, or usage during the shift)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='adjust_stock') {
    $id    = (int)$_POST['id'];
    $delta = (float)($_POST['delta'] ?? 0);
    $db->prepare('UPDATE ingredients SET stock_quantity = GREATEST(0, stock_quantity + ?) WHERE id=?')->execute([$delta, $id]);
    // Auto-flip availability based on stock level so the POS menu stays accurate.
    $db->prepare("UPDATE ingredients SET is_available = IF(stock_quantity > 0, 1, 0) WHERE id=?")->execute([$id]);
    $success = 'Stock updated.';
}

// Toggle availability on/off (e.g. mark "Strawberry Syrup" unavailable when it runs out).
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='toggle') {
    $id = (int)$_POST['id'];
    $db->prepare('UPDATE ingredients SET is_available = NOT is_available WHERE id=?')->execute([$id]);
    $success = 'Availability updated.';
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare('DELETE FROM ingredients WHERE id=?')->execute([(int)$_GET['delete']]);
    $success = 'Ingredient removed.';
}

$ingredients = $db->query('SELECT * FROM ingredients ORDER BY name')->fetchAll();
$lowStockCount = 0;
foreach ($ingredients as $ing) {
    if ((float)$ing['stock_quantity'] <= (float)$ing['low_stock_threshold']) { $lowStockCount++; }
}
$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ingredients — Boba House POS</title>
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
        <i class="bi bi-basket-fill me-2" style="color:var(--boba-amber)"></i>Ingredients
      </h1>
      <button class="btn btn-boba" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i>Add Ingredient
      </button>
    </div>
    <div class="content-area">
      <div class="workspace-banner mb-4"><div><strong>Inventory Staff Workspace</strong><span>Manage ingredient quantities and low-stock levels.</span></div><i class="bi bi-basket-fill"></i></div>

      <?php if ($error):   ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
      <?php if ($lowStockCount > 0): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $lowStockCount; ?> ingredient<?php echo $lowStockCount>1?'s':''; ?> at or below the low-stock threshold.</div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">Ingredients (<?php echo count($ingredients); ?>)</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Name</th><th>Unit</th><th>Stock</th><th>Availability</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($ingredients)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No ingredients yet.</td></tr>
              <?php else: foreach ($ingredients as $ing): ?>
              <?php $isLow = (float)$ing['stock_quantity'] <= (float)$ing['low_stock_threshold']; ?>
              <tr>
                <td class="fw-bold"><?php echo htmlspecialchars($ing['name']); ?></td>
                <td><?php echo htmlspecialchars($ing['unit']); ?></td>
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <span class="badge <?php echo $ing['stock_quantity'] <= 0 ? 'bg-danger' : ($isLow ? 'bg-warning text-dark' : 'bg-success-subtle text-success-emphasis'); ?>">
                      <?php echo $isLow ? '<i class="bi bi-exclamation-triangle-fill me-1"></i>' : ''; ?><?php echo rtrim(rtrim(number_format($ing['stock_quantity'],2),'0'),'.'); ?> <?php echo htmlspecialchars($ing['unit']); ?>
                    </span>
                    <form method="POST" class="d-inline" onsubmit="return promptDelta(this,-1)">
                      <input type="hidden" name="action" value="adjust_stock">
                      <input type="hidden" name="id" value="<?php echo $ing['id']; ?>">
                      <input type="hidden" name="delta" value="-1" class="delta-input">
                      <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Use stock">−</button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return promptDelta(this,1)">
                      <input type="hidden" name="action" value="adjust_stock">
                      <input type="hidden" name="id" value="<?php echo $ing['id']; ?>">
                      <input type="hidden" name="delta" value="1" class="delta-input">
                      <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Restock">+</button>
                    </form>
                  </div>
                </td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $ing['id']; ?>">
                    <button type="submit" class="btn btn-sm <?php echo $ing['is_available'] ? 'btn-success' : 'btn-outline-danger'; ?>">
                      <i class="bi bi-<?php echo $ing['is_available'] ? 'check-circle-fill' : 'x-circle-fill'; ?> me-1"></i>
                      <?php echo $ing['is_available'] ? 'Available' : 'Unavailable'; ?>
                    </button>
                  </form>
                </td>
                <td>
                  <button class="btn btn-sm btn-boba-outline"
                          onclick='editIngredient(<?php echo json_encode($ing); ?>)'
                          data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <a href="?delete=<?php echo $ing['id']; ?>"
                     class="btn btn-sm btn-outline-danger ms-1"
                     onclick="return confirm('Remove this ingredient?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:.85rem">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Ingredient</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Tapioca Pearls" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Unit</label>
            <input type="text" name="unit" class="form-control" placeholder="kg / liters / pcs" value="pcs">
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-bold">Starting Stock</label>
              <input type="number" name="stock_quantity" class="form-control" step="0.01" min="0" value="0">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Low Stock Alert At</label>
              <input type="number" name="low_stock_threshold" class="form-control" step="0.01" min="0" value="5">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-boba-outline" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-boba">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:.85rem">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Ingredient</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Name</label>
            <input type="text" name="name" id="editName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Unit</label>
            <input type="text" name="unit" id="editUnit" class="form-control">
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-bold">Stock</label>
              <input type="number" name="stock_quantity" id="editStockQty" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Low Stock Alert At</label>
              <input type="number" name="low_stock_threshold" id="editThreshold" class="form-control" step="0.01" min="0">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-boba-outline" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-boba">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editIngredient(ing) {
  document.getElementById('editId').value        = ing.id;
  document.getElementById('editName').value      = ing.name;
  document.getElementById('editUnit').value      = ing.unit;
  document.getElementById('editStockQty').value  = ing.stock_quantity;
  document.getElementById('editThreshold').value = ing.low_stock_threshold;
}

// The +/- buttons ask how much to use/restock instead of always stepping by 1.
function promptDelta(form, sign) {
  const label = sign > 0 ? 'Add how much stock?' : 'Use/remove how much stock?';
  const amount = prompt(label, '1');
  if (amount === null) return false;
  const num = parseFloat(amount);
  if (isNaN(num) || num <= 0) { alert('Enter a positive number.'); return false; }
  form.querySelector('.delta-input').value = sign > 0 ? num : -num;
  return true;
}
</script>
</body></html>
