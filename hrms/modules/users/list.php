<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'User Management';
$db = getDB();
$canManageUsers = hasRole('admin');

// HR may view this page, but only an administrator may change user accounts.
// Keep this authorization check on the server; hiding buttons is not enough.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!$canManageUsers) {
        header('Location: list.php?error=' . urlencode('Only an administrator can delete users.'));
        exit;
    }
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { header('Location: list.php?error=Invalid+request'); exit; }
    $delId = (int)$_POST['delete_id'];
    $u = currentUser();
    if ($delId === $u['id']) {
        header('Location: list.php?error=' . urlencode('You cannot delete your own account.'));
        exit;
    }

    // Keep administrator accounts protected from permanent deletion.
    $target = $db->prepare("SELECT username, role FROM users WHERE id=?");
    $target->execute([$delId]);
    $target = $target->fetch();
    if (!$target) {
        header('Location: list.php?error=' . urlencode('User not found.'));
        exit;
    }
    if ($target['role'] === 'admin') {
        header('Location: list.php?error=' . urlencode('Administrator accounts cannot be deleted.'));
        exit;
    }

    $deleted = $db->prepare("DELETE FROM users WHERE id=?");
    $deleted->execute([$delId]);
    auditLog('DELETE','Users',"Deleted user #$delId");
    header('Location: list.php?success=User+deleted');
    exit;
}

// Handle add/edit
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageUsers) {
        $errors[] = 'Only an administrator can add or edit users.';
    } elseif (!verifyCSRF($_POST['csrf_token']??'')) {
        $errors[] = 'Invalid submission.';
    }
    else {
        $username = sanitize($_POST['username']??'');
        $email    = sanitize($_POST['email']??'');
        $role     = $_POST['role'] ?? 'employee';
        $empId    = (int)($_POST['employee_id']??0) ?: null;
        $branchId = (int)($_POST['branch_id']??0) ?: null;
        $editId   = (int)($_POST['edit_id']??0);
        $status   = $_POST['status'] ?? 'active';

        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (!in_array($role, ['admin', 'hr', 'employee'], true)) $errors[] = 'Invalid role.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            $duplicate = $db->prepare(
                "SELECT id FROM users WHERE (username=? OR email=?) AND id<>?"
            );
            $duplicate->execute([$username, $email, $editId]);
            if ($duplicate->fetch()) {
                $errors[] = 'That username or email is already in use.';
            }
        }

        if (empty($errors)) {
            if ($editId) {
                $db->prepare("UPDATE users SET username=?,email=?,role=?,employee_id=?,branch_id=?,status=? WHERE id=?")
                   ->execute([$username,$email,$role,$empId,$branchId,$status,$editId]);
            } else {
                $password = $_POST['password']??'';
                if (strlen($password) < 6) { $errors[] = 'Password must be at least 6 characters.'; }
                else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $db->prepare("INSERT INTO users (username,password,email,role,employee_id,branch_id,status) VALUES (?,?,?,?,?,?,?)")
                       ->execute([$username,$hash,$email,$role,$empId,$branchId,$status]);
                    auditLog('CREATE','Users',"Created user: $username ($role)".($branchId?" — branch #$branchId":''));
                    header('Location: list.php?success='.urlencode("User $username created successfully."));
                    exit;
                }
            }
            if (empty($errors)) {
                auditLog('UPDATE','Users',"Updated user: $username ($role)");
                header('Location: list.php?success=User+updated');
                exit;
            }
        }
    }
}

$users     = $db->query(
    "SELECT u.*, e.firstname, e.lastname, eb.name AS emp_branch_name, ub.name AS user_branch_name
     FROM users u
     LEFT JOIN employees e ON u.employee_id=e.id
     LEFT JOIN branches eb ON e.branch_id = eb.id
     LEFT JOIN branches ub ON u.branch_id = ub.id
     ORDER BY u.role, u.username"
)->fetchAll();
$employees = $db->query("SELECT id, CONCAT(firstname,' ',lastname,' (',employee_id,')') as name FROM employees WHERE status='active' ORDER BY firstname")->fetchAll();

$editUser = null;
if ($canManageUsers && isset($_GET['edit'])) {
    $eu = $db->prepare("SELECT * FROM users WHERE id=?");
    $eu->execute([(int)$_GET['edit']]);
    $editUser = $eu->fetch();
}

include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success alert-auto-dismiss"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-shield-lock-fill me-2 text-primary"></i>User Management</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Users</li></ol></nav>
    </div>
    <?php if ($canManageUsers): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-plus-lg me-1"></i>Add User</button>
    <?php endif; ?>
</div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
<?php if (!$canManageUsers): ?>
<div class="alert alert-info"><i class="bi bi-eye me-2"></i>You have view-only access. Only an administrator can add, edit, reset, or delete user accounts.</div>
<?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Employee</th><th>Last Login</th><th>Status</th><th class="text-center">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?= statusBadge($u['role']) ?>
                    <?php
                        $posLabels = ['cashier'=>'Cashier','inventory_staff'=>'Inventory Staff','super_admin'=>'POS Admin'];
                        $branchName = $u['emp_branch_name'] ?? $u['user_branch_name'] ?? null;
                    ?>
                    <?php if (!empty($u['pos_role'])): ?>
                        <span class="badge bg-light text-dark border small">/ <?= htmlspecialchars($posLabels[$u['pos_role']] ?? ucfirst($u['pos_role'])) ?></span>
                    <?php endif; ?>
                    <?php if ($branchName): ?>
                        <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($branchName) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $u['firstname'] ? htmlspecialchars($u['firstname'].' '.$u['lastname']) : '<span class="text-muted">—</span>' ?></td>
                <td><small class="text-muted"><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never' ?></small></td>
                <td><?= statusBadge($u['status']) ?></td>
                <td class="text-center">
                    <?php if ($canManageUsers): ?>
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="list.php?edit=<?=$u['id']?>" class="btn btn-sm btn-warning py-0 px-2"><i class="bi bi-pencil"></i></a>
                        <a href="change_password.php?user_id=<?=$u['id']?>" class="btn btn-sm btn-info py-0 px-2"><i class="bi bi-key"></i></a>
                        <?php if ($u['id'] !== currentUser()['id']): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirmSubmit(this, 'Delete user <?= htmlspecialchars(addslashes($u['username'])) ?>? This cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted small">View only</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($canManageUsers): ?>
<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-validate>
                <?= csrfField() ?>
                <?php if ($editUser): ?><input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                <div class="modal-header"><h5 class="modal-title fw-bold"><?= $editUser ? 'Edit User' : 'Add User' ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($editUser['username']??'') ?>"></div>
                        <div class="col-12"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($editUser['email']??'') ?>"></div>
                        <?php if (!$editUser): ?>
                        <div class="col-12"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters"></div>
                        <?php endif; ?>
                        <div class="col-md-6"><label class="form-label">Role</label><select name="role" class="form-select"><option value="admin" <?=($editUser['role']??'')==='admin'?'selected':''?>>Admin</option><option value="hr" <?=($editUser['role']??'')==='hr'?'selected':''?>>HR</option><option value="employee" <?=($editUser['role']??'employee')==='employee'?'selected':''?>>Employee</option></select></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?=($editUser['status']??'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($editUser['status']??'')==='inactive'?'selected':''?>>Inactive</option></select></div>
                        <div class="col-12"><label class="form-label">Link to Employee</label><select name="employee_id" class="form-select"><option value="">None</option><?php foreach ($employees as $e): ?><option value="<?=$e['id']?>" <?=($editUser['employee_id']??'')==$e['id']?'selected':''?>><?= htmlspecialchars($e['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12">
                            <label class="form-label">Branch <span class="text-muted small">(HR/Admin — which branch they manage; leave blank for Head Office / all branches)</span></label>
                            <select name="branch_id" class="form-select">
                                <option value="">— Head Office / All Branches —</option>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?=$b['id']?>" <?=($editUser['branch_id']??'')==$b['id']?'selected':''?>><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save User</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$extraJs = $editUser ? '<script>document.addEventListener("DOMContentLoaded",()=>new bootstrap.Modal(document.getElementById("userModal")).show());</script>' : '';
include '../../includes/footer.php';
?>
