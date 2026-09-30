<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_permission('settings.manage');

$user = current_user();
$error = null;

if (is_post()) {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($action === 'create') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $roleId = (int) ($_POST['role_id'] ?? 0);
            if ($fullName === '' || $username === '' || $roleId < 1 || strlen($password) < 10) {
                throw new RuntimeException('Complete all fields. Passwords must contain at least 10 characters.');
            }
            $stmt = db_finance()->prepare('INSERT INTO users (username, full_name, password_hash, role_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT), $roleId]);
            audit('create', 'users', 'user', (string) db_finance()->lastInsertId(), null, ['username' => $username, 'full_name' => $fullName, 'role_id' => $roleId]);
            flash('success', 'User access created successfully.');
        } elseif ($action === 'toggle' && $targetId > 0) {
            if ($targetId === (int) $user['id']) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }
            $stmt = db_finance()->prepare('UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$targetId]);
            audit('update', 'users', 'user', (string) $targetId, null, ['is_active_toggled' => true]);
            flash('success', 'User account status updated.');
        } elseif ($action === 'reset_password' && $targetId > 0) {
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 10) {
                throw new RuntimeException('New passwords must contain at least 10 characters.');
            }
            $stmt = db_finance()->prepare('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
            audit('update', 'users', 'user', (string) $targetId, null, ['password_reset' => true]);
            flash('success', 'Password reset successfully.');
        }
        redirect('users.php');
    } catch (Throwable $exception) {
        $error = format_error($exception);
    }
}

$roles = db_finance()->query('SELECT id, name, label FROM roles ORDER BY id')->fetchAll();
$users = db_finance()->query(
    'SELECT u.id, u.username, u.full_name, u.is_active, u.last_login_at, u.created_at, r.label AS role_label, r.name AS role_name
     FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.is_active DESC, u.full_name'
)->fetchAll();
$employees = [];
$employeeError = null;
try {
    $employees = db_hrms()->query(
        "SELECT e.employee_id, e.firstname, e.middlename, e.lastname, e.email, e.phone,
                e.employment_status, e.employment_type, e.date_hired, e.status,
                d.name AS department_name, p.title AS position_title
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN positions p ON p.id = e.position_id
         WHERE e.status <> 'archived'
         ORDER BY e.status = 'active' DESC, e.lastname, e.firstname"
    )->fetchAll();
} catch (Throwable) {
    $employeeError = 'HRMS is not connected. Test the HRMS connection in Integration to load the employee directory.';
}
$title = 'Admin & Employees';
$activePage = 'users';
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar">
    <div><div class="eyebrow">ACCESS CONTROL</div><h2 class="page-heading">Admin &amp; employees</h2><p class="toolbar-copy">Manage Finance accounts and assign access without changing HRMS employee records.</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><span class="bi bi-person-plus"></span> Add user</button>
</div>
<?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
<div class="callout mt-4"><span class="bi bi-info-circle"></span><div><strong>Finance users are separate from HRMS employees.</strong><p>Use this page for people who need access to the Finance control center. HRMS employee data remains read-only and is available through Payroll synchronization.</p></div></div>
<div class="panel-card mt-4">
    <div class="panel-heading"><div><div class="eyebrow">USER DIRECTORY</div><h3>Accounts and roles</h3></div><span class="subtle-label"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></span></div>
    <div class="table-responsive"><table class="table finance-table align-middle"><thead><tr><th>Person</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign in</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($users as $account): ?>
        <tr>
            <td><strong><?= e($account['full_name']) ?></strong><small class="d-block text-muted"><?= e(date_label($account['created_at'])) ?> created</small></td>
            <td><?= e($account['username']) ?></td>
            <td><span class="source-tag"><?= e($account['role_label']) ?></span></td>
            <td><span class="status-badge <?= $account['is_active'] ? 'success' : 'danger' ?>"><?= $account['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td><?= $account['last_login_at'] ? e(date_label($account['last_login_at'])) : 'Never' ?></td>
            <td class="text-end">
                <div class="user-actions">
                    <?php if ((int) $account['id'] !== (int) $user['id']): ?>
                        <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= e($account['id']) ?>"><button class="btn btn-sm btn-light" type="submit"><?= $account['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-user-id="<?= e($account['id']) ?>" data-user-name="<?= e($account['full_name']) ?>">Reset password</button>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<div class="panel-card mt-4">
    <div class="panel-heading"><div><div class="eyebrow">READ-ONLY HRMS DIRECTORY</div><h3>Employees</h3></div><span class="subtle-label"><?= count($employees) ?> employee<?= count($employees) === 1 ? '' : 's' ?></span></div>
    <?php if ($employeeError): ?>
        <div class="empty-state"><span class="bi bi-database-exclamation"></span><strong>Employee directory unavailable</strong><small><?= e($employeeError) ?></small><a class="btn btn-sm btn-outline-primary mt-2" href="integration.php">Open Integration</a></div>
    <?php elseif (!$employees): ?>
        <div class="empty-state"><span class="bi bi-people"></span><strong>No HRMS employees found</strong><small>Connect HRMS and synchronize payroll data to verify the source database.</small></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table finance-table align-middle"><thead><tr><th>Employee</th><th>Employee ID</th><th>Department</th><th>Position</th><th>Contact</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($employees as $employee): ?>
            <tr>
                <td><strong><?= e(trim((string) $employee['firstname'] . ' ' . (string) $employee['middlename'] . ' ' . (string) $employee['lastname'])) ?></strong><small class="d-block text-muted"><?= e($employee['employment_type'] ?? '') ?></small></td>
                <td><span class="account-code"><?= e($employee['employee_id']) ?></span></td>
                <td><?= e($employee['department_name'] ?? 'Unassigned') ?></td>
                <td><?= e($employee['position_title'] ?? 'Unassigned') ?></td>
                <td><?= e($employee['email'] ?: ($employee['phone'] ?: '—')) ?></td>
                <td><span class="status-badge <?= $employee['status'] === 'active' ? 'success' : 'warning' ?>"><?= e(ucfirst((string) $employee['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">NEW ACCESS</div><h3 class="modal-title">Add Finance user</h3></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div class="modal-body"><label class="form-label">Full name</label><input class="form-control mb-3" name="full_name" required><label class="form-label">Username</label><input class="form-control mb-3" name="username" required autocomplete="off"><label class="form-label">Role</label><select class="form-select mb-3" name="role_id" required><option value="">Choose a role</option><?php foreach ($roles as $role): ?><option value="<?= e($role['id']) ?>"><?= e($role['label']) ?></option><?php endforeach; ?></select><label class="form-label">Temporary password</label><input class="form-control" type="password" name="password" minlength="10" required autocomplete="new-password"><div class="form-text">Use at least 10 characters. Share temporary passwords securely and ask the user to change them through your local process.</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create user</button></div></form></div></div></div>
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">ACCOUNT SECURITY</div><h3 class="modal-title">Reset password</h3></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" id="reset-user-id"><div class="modal-body"><p class="toolbar-copy">Set a new password for <strong id="reset-user-name">this user</strong>.</p><label class="form-label">New password</label><input class="form-control" type="password" name="password" minlength="10" required autocomplete="new-password"></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reset password</button></div></form></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>