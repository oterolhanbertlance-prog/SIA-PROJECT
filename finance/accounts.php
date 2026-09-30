<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$title = 'Chart of Accounts';
$activePage = 'accounts';
if (is_post()) {
    try {
        verify_csrf();
        $code = trim((string) ($_POST['code'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $group = (int) ($_POST['account_group_id'] ?? 0);
        if (!preg_match('/^[0-9A-Za-z-]{2,20}$/', $code) || $name === '' || $group < 1) {
            throw new InvalidArgumentException('Enter a valid account code, name, and group.');
        }
        $stmt = db_finance()->prepare('INSERT INTO accounts (code, name, account_group_id) VALUES (?, ?, ?)');
        $stmt->execute([$code, $name, $group]);
        audit('create', 'accounting', 'account', (string) db_finance()->lastInsertId(), null, ['code' => $code, 'name' => $name]);
        flash('success', 'Account added to the chart of accounts.');
    } catch (Throwable $exception) {
        flash('error', format_error($exception));
    }
    redirect('accounts.php');
}
$accounts = [];
$groups = [];
try {
    $db = db_finance();
    $accounts = $db->query(
        'SELECT a.*, g.name AS group_name, g.statement_group FROM accounts a JOIN account_groups g ON g.id=a.account_group_id ORDER BY a.code'
    )->fetchAll();
    $groups = $db->query('SELECT * FROM account_groups ORDER BY statement_group, name')->fetchAll();
} catch (Throwable $exception) {
    flash('error', format_error($exception));
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar"><div><div class="eyebrow">ACCOUNTING FOUNDATION</div><p class="toolbar-copy">Edit the Finance ledger structure without changing HRMS or POS.</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal"><span class="bi bi-plus-lg"></span> Add account</button></div>
<section class="panel-card mt-4"><div class="panel-heading"><div><div class="eyebrow">LEDGER STRUCTURE</div><h3>Chart of accounts</h3></div><span class="subtle-label"><?= number_format(count($accounts)) ?> accounts</span></div><div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Code</th><th>Account</th><th>Group</th><th>Statement</th><th>Status</th></tr></thead><tbody>
<?php foreach ($accounts as $account): ?><tr><td><strong class="account-code"><?= e($account['code']) ?></strong></td><td><?= e($account['name']) ?></td><td><?= e($account['group_name']) ?></td><td><span class="source-tag"><?= e(ucfirst($account['statement_group'])) ?></span></td><td><span class="status-badge success">Active</span></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">NEW LEDGER ACCOUNT</div><h3 class="modal-title">Add account</h3></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label class="form-label">Code</label><input class="form-control mb-3" name="code" required placeholder="e.g. 5910"><label class="form-label">Name</label><input class="form-control mb-3" name="name" required><label class="form-label">Account group</label><select class="form-select" name="account_group_id" required><option value="">Choose group</option><?php foreach ($groups as $group): ?><option value="<?= e($group['id']) ?>"><?= e($group['name']) ?></option><?php endforeach; ?></select></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save account</button></div></form></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>