<?php
session_start();
require_once '../config/db.php';
require_once '../includes/product_customization.php';
require_once __DIR__ . '/../includes/category_filter.php';

// Dynamically determine the base folder path (e.g., /boba-house) to prevent cross-directory 404s
$base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');

// LOCAL AUTH CHECK: Replaces the missing auth_check.php file safely
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'inventory_staff') {
    header('Location: ' . $base_dir . '/auth/login.php');
    exit;
}

$db    = getDB();
ensureProductCustomizationSchema($db);
$error = '';
$success = '';

// Use Session Flash messages to prevent form re-submission loops
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

function handleImageUpload(): ?string {
    if (empty($_FILES['image']['name'])) return null;
    $allowed  = ['jpg','jpeg','png','gif','webp'];
    $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;
    $filename = uniqid('img_') . '.' . $ext;
    $dest     = __DIR__ . '/../assets/uploads/' . $filename;
    
    if (!is_dir(__DIR__ . '/../assets/uploads/')) {
        mkdir(__DIR__ . '/../assets/uploads/', 0755, true);
    }
    
    move_uploaded_file($_FILES['image']['tmp_name'], $dest);
    return $filename;
}

function saveProductIngredients(PDO $db, int $productId, array $ingredientIds): void {
    $db->prepare('DELETE FROM product_ingredients WHERE product_id=?')->execute([$productId]);
    $stmt = $db->prepare('INSERT INTO product_ingredients (product_id, ingredient_id) VALUES (?,?)');
    foreach (array_unique(array_map('intval', $ingredientIds)) as $ingId) {
        if ($ingId > 0) $stmt->execute([$productId, $ingId]);
    }
}

// POST: Create Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name        = trim($_POST['name']     ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $catId       = ($_POST['category_id'] !== '') ? (int)$_POST['category_id'] : null;
    $imageUrl    = handleImageUpload();
    $ingredients = $_POST['ingredients'] ?? [];
    $stock       = max(0, (int)($_POST['stock'] ?? 0));
    $allowSize=(int)!empty($_POST['allow_size']); $allowSugar=(int)!empty($_POST['allow_sugar']); $allowIce=(int)!empty($_POST['allow_ice']); $allowToppings=(int)!empty($_POST['allow_toppings']);
    $sizeOptions=trim($_POST['size_options']??''); $toppingOptions=trim($_POST['topping_options']??'');

    if (!$name || $price <= 0) {
        $_SESSION['flash_error'] = 'Name and a positive price are required.';
    } else {
        $db->prepare('INSERT INTO products (category_id,name,price,stock,image_url,allow_size,allow_sugar,allow_ice,allow_toppings,size_options,topping_options) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([$catId,$name,$price,$stock,$imageUrl,$allowSize,$allowSugar,$allowIce,$allowToppings,$sizeOptions?:null,$toppingOptions?:null]);
        saveProductIngredients($db, (int)$db->lastInsertId(), $ingredients);
        $_SESSION['flash_success'] = 'Product "' . htmlspecialchars($name) . '" created.';
    }
    header('Location: products.php');
    exit;
}

// POST: Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id          = (int)$_POST['id'];
    $name        = trim($_POST['name']  ?? '');
    $price       = (float)$_POST['price'];
    $catId       = ($_POST['category_id'] !== '') ? (int)$_POST['category_id'] : null;
    $imageUrl    = handleImageUpload();
    $ingredients = $_POST['ingredients'] ?? [];
    $stock       = max(0, (int)($_POST['stock'] ?? 0));
    $allowSize=(int)!empty($_POST['allow_size']); $allowSugar=(int)!empty($_POST['allow_sugar']); $allowIce=(int)!empty($_POST['allow_ice']); $allowToppings=(int)!empty($_POST['allow_toppings']);
    $sizeOptions=trim($_POST['size_options']??''); $toppingOptions=trim($_POST['topping_options']??'');

    if (!$name || $price <= 0) {
        $_SESSION['flash_error'] = 'Name and a positive price are required.';
    } else {
        if ($imageUrl) {
            $db->prepare('UPDATE products SET category_id=?,name=?,price=?,stock=?,image_url=?,allow_size=?,allow_sugar=?,allow_ice=?,allow_toppings=?,size_options=?,topping_options=? WHERE id=?')
               ->execute([$catId,$name,$price,$stock,$imageUrl,$allowSize,$allowSugar,$allowIce,$allowToppings,$sizeOptions?:null,$toppingOptions?:null,$id]);
        } else {
            $db->prepare('UPDATE products SET category_id=?,name=?,price=?,stock=?,allow_size=?,allow_sugar=?,allow_ice=?,allow_toppings=?,size_options=?,topping_options=? WHERE id=?')
               ->execute([$catId,$name,$price,$stock,$allowSize,$allowSugar,$allowIce,$allowToppings,$sizeOptions?:null,$toppingOptions?:null,$id]);
        }
        saveProductIngredients($db, $id, $ingredients);
        $_SESSION['flash_success'] = 'Product updated.';
    }
    header('Location: products.php');
    exit;
}

// POST: Quick stock adjustment (+/- from the table, e.g. after restocking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_stock') {
    $id    = (int)$_POST['id'];
    $delta = (int)($_POST['delta'] ?? 0);
    $db->prepare('UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id=?')->execute([$delta, $id]);
    $_SESSION['flash_success'] = 'Stock updated.';
    header('Location: products.php');
    exit;
}

// GET: Delete Product
if (isset($_GET['delete'])) {
    $db->prepare('DELETE FROM products WHERE id=?')->execute([(int)$_GET['delete']]);
    $_SESSION['flash_success'] = 'Product deleted.';
    header('Location: products.php');
    exit;
}

// Data Fetching
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$catFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = 'SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE 1=1';
$params = [];
if ($catFilter > 0) { $sql .= ' AND p.category_id = ?'; $params[] = $catFilter; }
if ($search) { $sql .= ' AND p.name LIKE ?'; $params[] = "%$search%"; }
$sql .= ' ORDER BY p.name';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$ingredients = $db->query('SELECT * FROM ingredients ORDER BY name')->fetchAll();

$linksStmt = $db->query('SELECT product_id, ingredient_id FROM product_ingredients');
$productIngredientMap = [];
foreach ($linksStmt->fetchAll() as $row) {
    $productIngredientMap[$row['product_id']][] = (int)$row['ingredient_id'];
}

$unavailableIds = $db->query(
    "SELECT DISTINCT pi.product_id FROM product_ingredients pi
     JOIN ingredients i ON i.id = pi.ingredient_id
     WHERE COALESCE(i.stock_quantity,0) <= 0"
)->fetchAll(PDO::FETCH_COLUMN);

$root = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Products — Boba House POS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body class="pos-role-<?php echo htmlspecialchars($_SESSION['role'] ?? 'guest'); ?>">
<div class="d-flex" style="min-height:100vh">
  <?php include '../includes/sidebar.php'; ?>
  <div id="main-content">
    <div class="topbar">
      <h1 class="page-title">
        <i class="bi bi-box-seam-fill me-2" style="color:var(--boba-amber)"></i>Products
      </h1>
      <button class="btn btn-boba" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i>Add Product
      </button>
    </div>
    <div class="content-area">
      <div class="workspace-banner mb-4"><div><strong>Inventory Staff Workspace</strong><span>Manage products and product stock.</span></div><i class="bi bi-box-seam-fill"></i></div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="GET" class="d-flex gap-2 flex-wrap mb-4">
        <input type="text" name="search" class="form-control" style="max-width:220px"
               placeholder="Search products…"
               value="<?php echo htmlspecialchars($search); ?>">
        <select name="category" class="form-select" style="max-width:180px">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php if ($catFilter===(int)$c['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-boba-outline">Filter</button>
        <a href="products.php" class="btn btn-outline-secondary">Reset</a>
      </form>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th>Ingredients</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($products)): ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">No products found.</td>
                  </tr>
                <?php else: foreach ($products as $p): ?>
                <tr>
                  <td>
                    <?php if ($p['image_url']): ?>
                      <img src="../assets/uploads/<?php echo htmlspecialchars($p['image_url']); ?>"
                           style="width:44px;height:44px;object-fit:cover;border-radius:.5rem">
                    <?php else: ?>
                      <div style="width:44px;height:44px;border-radius:.5rem;background:var(--boba-cream);display:flex;align-items:center;justify-content:center;font-size:1.4rem">
                        ☕
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></td>
                  <td>
                    <span class="badge" style="background:var(--boba-cream);color:var(--boba-dark)">
                      <?php echo htmlspecialchars($p['category_name'] ?? '—'); ?>
                    </span>
                  </td>
                  <td style="color:var(--boba-amber);font-weight:800">
                    ₱<?php echo number_format($p['price'],2); ?>
                  </td>
                  <td>
                    <?php $stockVal = (int)($p['stock'] ?? 0); ?>
                    <div class="d-flex align-items-center gap-1">
                      <span class="badge <?php echo $stockVal <= 0 ? 'bg-danger' : ($stockVal < 10 ? 'bg-warning text-dark' : 'bg-boba-outline'); ?>"
                            style="<?php echo $stockVal < 10 ? '' : 'background:var(--boba-cream);color:var(--boba-dark)'; ?>">
                        <?php if ($stockVal <= 0): ?><i class="bi bi-exclamation-octagon-fill me-1"></i><?php elseif ($stockVal < 10): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                        <?php echo $stockVal; ?> pcs
                      </span>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <input type="hidden" name="delta" value="-1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Remove 1" <?php echo $stockVal<=0?'disabled':''; ?>>−</button>
                      </form>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <input type="hidden" name="delta" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Add 1">+</button>
                      </form>
                    </div>
                  </td>
                  <td>
                    <?php if ($stockVal <= 0 || in_array($p['id'], $unavailableIds)): ?>
                      <span class="badge bg-danger">
                        <i class="bi bi-x-circle-fill me-1"></i>Unavailable
                      </span>
                    <?php else: ?>
                      <span class="badge bg-success">
                        <i class="bi bi-check-circle-fill me-1"></i>Available
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (in_array($p['id'], $unavailableIds)): ?>
                      <span class="badge bg-danger" title="A required ingredient has zero stock">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Ingredient Out of Stock
                      </span>
                    <?php elseif (!empty($productIngredientMap[$p['id']])): ?>
                      <span class="badge bg-success"><?php echo count($productIngredientMap[$p['id']]); ?> linked</span>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:.78rem">None set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-boba-outline"
                            onclick="editProduct(<?php echo htmlspecialchars(json_encode($p + ['ingredient_ids' => $productIngredientMap[$p['id']] ?? []])); ?>)"
                            data-bs-toggle="modal" data-bs-target="#editModal">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <a href="?delete=<?php echo $p['id']; ?>"
                       class="btn btn-sm btn-outline-danger ms-1"
                       onclick="return confirm('Delete this product?')">
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
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:.85rem">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add New Product</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-bold">Product Name *</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Category</label>
              <select name="category_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo $c['id']; ?>">
                    <?php echo htmlspecialchars($c['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Price (₱) *</label>
              <input type="number" name="price" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Starting Stock (pcs)</label>
              <input type="number" name="stock" class="form-control" step="1" min="0" value="0">
              <div class="form-text">How many are ready to sell right now.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Product Image</label>
              <input type="file" name="image" class="form-control" accept="image/*">
            </div>

            <div class="col-12"><hr><label class="form-label fw-bold">Customer Customization</label><div class="form-text mb-2">Turn on only the choices this product really needs. A milk tea can have sugar/toppings turned off.</div><div class="row g-2">
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_size" value="1" id="createAllowSize"><label class="form-check-label" for="createAllowSize">Size</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_sugar" value="1" id="createAllowSugar"><label class="form-check-label" for="createAllowSugar">Sugar Level</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_ice" value="1" id="createAllowIce"><label class="form-check-label" for="createAllowIce">Ice Level</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_toppings" value="1" id="createAllowToppings"><label class="form-check-label" for="createAllowToppings">Toppings</label></div></div>
            </div></div>
            <div class="col-md-6"><label class="form-label fw-bold">Size Options</label><textarea name="size_options" class="form-control" rows="3" placeholder="Regular|0&#10;Large|20"></textarea><div class="form-text">One per line: Name|additional price</div></div>
            <div class="col-md-6"><label class="form-label fw-bold">Topping Options</label><textarea name="topping_options" class="form-control" rows="3" placeholder="Pearls|15&#10;Nata de Coco|15"></textarea><div class="form-text">One per line: Name|additional price</div></div>

            <div class="col-12">
              <label class="form-label fw-bold">Required Ingredients</label>
              <div class="form-text mb-1">If any of these become unavailable, this product is hidden from customers automatically.</div>
              <div class="border rounded p-2" style="max-height:160px;overflow-y:auto">
                <?php if (empty($ingredients)): ?>
                  <span class="text-muted" style="font-size:.85rem">No ingredients yet — add some on the Ingredients page.</span>
                <?php else: foreach ($ingredients as $ing): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ingredients[]"
                           value="<?php echo $ing['id']; ?>" id="create_ing_<?php echo $ing['id']; ?>">
                    <label class="form-check-label" for="create_ing_<?php echo $ing['id']; ?>">
                      <?php echo htmlspecialchars($ing['name']); ?>
                      <span class="text-muted" style="font-size:.78rem">(<?php echo htmlspecialchars($ing['unit']); ?>)</span>
                    </label>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-boba-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-boba">Add Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:.85rem">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Product</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-bold">Product Name *</label>
              <input type="text" name="name" id="editName" class="form-control" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Category</label>
              <select name="category_id" id="editCatId" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo $c['id']; ?>">
                    <?php echo htmlspecialchars($c['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Price (₱) *</label>
              <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Stock (pcs)</label>
              <input type="number" name="stock" id="editStock" class="form-control" step="1" min="0">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold">Replace Image (optional)</label>
              <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="col-12"><hr><label class="form-label fw-bold">Customer Customization</label><div class="form-text mb-2">Only enabled choices appear on the customer menu. Turn off Sugar or Toppings for products that do not need them.</div><div class="row g-2">
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_size" value="1" id="editAllowSize"><label class="form-check-label" for="editAllowSize">Size</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_sugar" value="1" id="editAllowSugar"><label class="form-check-label" for="editAllowSugar">Sugar Level</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_ice" value="1" id="editAllowIce"><label class="form-check-label" for="editAllowIce">Ice Level</label></div></div>
              <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_toppings" value="1" id="editAllowToppings"><label class="form-check-label" for="editAllowToppings">Toppings</label></div></div>
            </div></div>
            <div class="col-md-6"><label class="form-label fw-bold">Size Options</label><textarea name="size_options" id="editSizeOptions" class="form-control" rows="3"></textarea><div class="form-text">One per line: Name|additional price</div></div>
            <div class="col-md-6"><label class="form-label fw-bold">Topping Options</label><textarea name="topping_options" id="editToppingOptions" class="form-control" rows="3"></textarea><div class="form-text">One per line: Name|additional price</div></div>
            <div class="col-12">
              <label class="form-label fw-bold">Required Ingredients</label>
              <div class="form-text mb-1">If any of these become unavailable, this product is hidden from customers automatically.</div>
              <div class="border rounded p-2" id="editIngredientsBox" style="max-height:160px;overflow-y:auto">
                <?php if (empty($ingredients)): ?>
                  <span class="text-muted" style="font-size:.85rem">No ingredients yet — add some on the Ingredients page.</span>
                <?php else: foreach ($ingredients as $ing): ?>
                  <div class="form-check">
                    <input class="form-check-input edit-ing-checkbox" type="checkbox" name="ingredients[]"
                           value="<?php echo $ing['id']; ?>" id="edit_ing_<?php echo $ing['id']; ?>">
                    <label class="form-check-label" for="edit_ing_<?php echo $ing['id']; ?>">
                      <?php echo htmlspecialchars($ing['name']); ?>
                      <span class="text-muted" style="font-size:.78rem">(<?php echo htmlspecialchars($ing['unit']); ?>)</span>
                    </label>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-boba-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-boba">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editProduct(p) {
  document.getElementById('editId').value    = p.id;
  document.getElementById('editName').value  = p.name;
  document.getElementById('editPrice').value = p.price;
  document.getElementById('editStock').value = p.stock || 0;
  document.getElementById('editCatId').value = p.category_id || '';
  document.getElementById('editAllowSize').checked = !!Number(p.allow_size||0);
  document.getElementById('editAllowSugar').checked = !!Number(p.allow_sugar||0);
  document.getElementById('editAllowIce').checked = !!Number(p.allow_ice||0);
  document.getElementById('editAllowToppings').checked = !!Number(p.allow_toppings||0);
  document.getElementById('editSizeOptions').value = p.size_options || '';
  document.getElementById('editToppingOptions').value = p.topping_options || '';

  var selectedIds = (p.ingredient_ids || []).map(String);
  document.querySelectorAll('.edit-ing-checkbox').forEach(function(cb) {
    cb.checked = selectedIds.indexOf(cb.value) !== -1;
  });
}
</script>
</body>
</html>