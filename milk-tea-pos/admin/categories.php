<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('inventory_staff', '../auth/login.php');

$db    = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        $error = 'Category name is required.';
    } else {
        $db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
        $success = 'Category "' . htmlspecialchars($name) . '" created.';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    $id   = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        $error = 'Category name is required.';
    } else {
        $db->prepare('UPDATE categories SET name=? WHERE id=?')->execute([$name, $id]);
        $success = 'Category updated.';
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare('DELETE FROM categories WHERE id=?')->execute([(int)$_GET['delete']]);
    $success = 'Category deleted.';
}

$categories = $db->query(
    'SELECT c.*, COUNT(p.id) as product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name'
)->fetchAll();

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Categories — Boba House POS</title>
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
        <i class="bi bi-tags-fill me-2" style="color:var(--boba-amber)"></i>Categories
      </h1>
      <button class="btn btn-boba" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i>New Category
      </button>
    </div>
    <div class="content-area">
      <div class="workspace-banner mb-4"><div><strong>Inventory Staff Workspace</strong><span>Organize the product catalog categories.</span></div><i class="bi bi-tags-fill"></i></div>

      <?php if ($error):   ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

      <div class="card">
        <div class="card-header">All Categories (<?php echo count($categories); ?>)</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>#</th><th>Name</th><th class="text-center">Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($categories)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No categories yet.</td></tr>
              <?php else: foreach ($categories as $cat): ?>
              <tr>
                <td><?php echo $cat['id']; ?></td>
                <td class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                <td class="text-center">
                  <span class="badge bg-warning text-dark"><?php echo $cat['product_count']; ?></span>
                </td>
                <td>
                  <button class="btn btn-sm btn-boba-outline"
                          onclick="editCategory(<?php echo $cat['id']; ?>, <?php echo htmlspecialchars(json_encode($cat['name'])); ?>)"
                          data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil"></i> Edit
                  </button>
                  <a href="?delete=<?php echo $cat['id']; ?>"
                     class="btn btn-sm btn-outline-danger ms-1"
                     onclick="return confirm('Delete this category? Products will become uncategorized.')">
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
        <h5 class="modal-title fw-bold">New Category</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <label class="form-label fw-bold">Category Name</label>
          <input type="text" name="name" class="form-control"
                 placeholder="e.g. Milk Tea" required autofocus>
        </div>
        <div class="modal-footer">
          <button class="btn btn-boba-outline" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-boba">Create</button>
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
        <h5 class="modal-title fw-bold">Edit Category</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <label class="form-label fw-bold">Category Name</label>
          <input type="text" name="name" id="editName" class="form-control" required>
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
function editCategory(id, name) {
  document.getElementById('editId').value   = id;
  document.getElementById('editName').value = name;
}
</script>
</body></html>