<?php
require_once __DIR__ . '/../includes/branch_sync.php';
session_start();
require_once '../config/db.php';
require_once '../includes/auth_check.php';
requireRole('super_admin', '../auth/login.php');

$db    = getDB();
$branchSyncState = syncAllHrmsBranchesToPos($db);
$db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER role");
$db->exec("CREATE TABLE IF NOT EXISTS branches (id INT AUTO_INCREMENT PRIMARY KEY, branch_code VARCHAR(30) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(100) NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$error = $success = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='assign_branch') {
    $targetId=(int)($_POST['user_id']??0); $branchId=(int)($_POST['branch_id']??0)?:null;
    $db->prepare('UPDATE users SET branch_id=? WHERE id=?')->execute([$branchId,$targetId]);
    $success='User branch assignment updated.';
}
$branchOptions=$db->query("SELECT id,name FROM branches WHERE status='active' ORDER BY name")->fetchAll();

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $targetId = (int)$_GET['toggle'];
    if ($targetId === (int)$_SESSION['user_id']) {
        $error = 'You cannot deactivate your own account.';
    } else {
        $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id=?')
           ->execute([$targetId]);
        $success = 'User status updated.';
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $targetId = (int)$_GET['delete'];
    if ($targetId === (int)$_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$targetId]);
        $success = 'User deleted successfully.';
    }
}

$search     = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$sql        = 'SELECT * FROM users WHERE 1=1';
$params     = [];

if ($search) {
    $sql .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter) {
    $sql .= ' AND role=?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$root = '../';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>User Management — Boba House POS</title>
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
        <i class="bi bi-people-fill me-2" style="color:var(--boba-amber)"></i>User Management
      </h1>
    </div>
    <div class="content-area">

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>

      <form method="GET" class="d-flex gap-2 flex-wrap mb-4">
        <input type="text" name="search" class="form-control" style="max-width:240px"
               placeholder="Search username or email…"
               value="<?php echo htmlspecialchars($search); ?>">
        <select name="role" class="form-select" style="max-width:200px">
          <option value="">All Roles</option>
          <option value="super_admin"
            <?php if ($roleFilter==='super_admin') echo 'selected'; ?>>
            Super Admin
          </option>
          <option value="customer"
            <?php if ($roleFilter==='customer') echo 'selected'; ?>>
             Customer
          </option>
          <option value="cashier"
            <?php if ($roleFilter==='cashier') echo 'selected'; ?>>
            Cashier
          </option>
          <option value="inventory_staff"
            <?php if ($roleFilter==='inventory_staff') echo 'selected'; ?>>
            Inventory Staff
          </option>
        </select>
        <button class="btn btn-boba-outline">Filter</button>
        <a href="users.php" class="btn btn-outline-secondary">Reset</a>
      </form>

      <div class="card">
        <div class="card-header">
          All Users (<?php echo count($users); ?>)
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Role</th><th>Branch</th>
                  <th class="text-center">Verified</th>
                  <th class="text-center">Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">No users found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users as $u):
                    $isSelf = ($u['id'] == $_SESSION['user_id']);
                  ?>
                  <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td class="fw-bold">
                      <?php echo htmlspecialchars($u['username']); ?>
                      <?php if ($isSelf): ?>
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem">You</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem">
                      <?php echo htmlspecialchars($u['email'] ?? '—'); ?>
                    </td>
                    <td>
                      <?php
                        if ($u['role'] === 'super_admin') {
                            $bc = 'bg-danger';
                        } else {
                            $bc = 'bg-info text-dark';
                        }
                      ?>
                      <span class="badge <?php echo $bc; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $u['role'])); ?>
                      </span>
                    </td>
                    <td>
                      <?php if (in_array($u['role'], ['super_admin','cashier','inventory_staff'], true)): ?>
                      <form method="post" class="d-flex gap-1"><input type="hidden" name="action" value="assign_branch"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All / Unassigned</option><?php foreach($branchOptions as $bo):?><option value="<?=$bo['id']?>" <?=((int)($u['branch_id']??0)===(int)$bo['id'])?'selected':''?>><?=htmlspecialchars($bo['name'])?></option><?php endforeach;?></select></form>
                      <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                      <?php if ($u['email_verified']): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                      <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <span class="badge <?php echo $u['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?>
                      </span>
                    </td>
                    <td>
                      <?php if (!$isSelf): ?>
                        <a href="?toggle=<?php echo $u['id']; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>"
                           class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                           onclick="return confirm('<?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?> this user?')">
                          <i class="bi bi-<?php echo $u['is_active'] ? 'slash-circle' : 'check-circle'; ?>"></i>
                          <?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?>
                        </a>
                        <a href="?delete=<?php echo $u['id']; ?>"
                           class="btn btn-sm btn-outline-danger ms-1"
                           onclick="return confirm('Permanently delete this user? This cannot be undone.')">
                          <i class="bi bi-trash"></i> Delete
                        </a>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:.8rem">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
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