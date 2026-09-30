<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$title = 'Journals';
$activePage = 'journal';

if (is_post()) {
    try {
        verify_csrf();
        $date = (string) ($_POST['transaction_date'] ?? date('Y-m-d'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $debitAccount = (int) ($_POST['debit_account'] ?? 0);
        $creditAccount = (int) ($_POST['credit_account'] ?? 0);
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        if ($description === '' || $debitAccount < 1 || $creditAccount < 1 || $debitAccount === $creditAccount || $amount <= 0) {
            throw new InvalidArgumentException('Choose two different accounts and enter a positive amount.');
        }
        if (!active_period($date)) {
            $period = db_finance()->prepare('SELECT id FROM fiscal_periods WHERE start_date <= ? AND end_date >= ? LIMIT 1');
            $period->execute([$date, $date]);
            if (!$period->fetchColumn()) {
                db_finance()->prepare(
                    'INSERT INTO fiscal_periods (period_name, start_date, end_date) VALUES (?, ?, ?)'
                )->execute([date('Y', strtotime($date)), date('Y-01-01', strtotime($date)), date('Y-12-31', strtotime($date))]);
            }
        }
        $db = db_finance();
        $db->beginTransaction();
        $number = 'MAN-' . date('YmdHis') . '-' . random_int(100, 999);
        $entry = $db->prepare(
            'INSERT INTO journal_entries
             (journal_number, transaction_date, description, entry_type, status, total_debit, total_credit, created_by, posted_at)
             VALUES (?, ?, ?, "manual", "posted", ?, ?, ?, NOW())'
        );
        $entry->execute([$number, $date, $description, $amount, $amount, $user['id']]);
        $id = (int) $db->lastInsertId();
        $line = $db->prepare('INSERT INTO journal_lines (journal_entry_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)');
        $line->execute([$id, $debitAccount, $description, $amount, 0]);
        $line->execute([$id, $creditAccount, $description, 0, $amount]);
        $db->commit();
        audit('post', 'accounting', 'journal_entry', (string) $id, null, ['amount' => $amount]);
        flash('success', 'Balanced journal ' . $number . ' was posted.');
        redirect('journal.php');
    } catch (Throwable $exception) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', format_error($exception));
        redirect('journal.php');
    }
}
$accounts = [];
$journals = [];
try {
    $db = db_finance();
    $accounts = $db->query(
        "SELECT a.id, a.code, a.name, g.statement_group FROM accounts a
         JOIN account_groups g ON g.id=a.account_group_id WHERE a.is_active=1 ORDER BY a.code"
    )->fetchAll();
    $journals = $db->query(
        "SELECT je.*, u.full_name FROM journal_entries je LEFT JOIN users u ON u.id=je.created_by
         ORDER BY je.id DESC LIMIT 200"
    )->fetchAll();
} catch (Throwable $exception) {
    flash('error', format_error($exception));
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar"><div><div class="eyebrow">DOUBLE-ENTRY CONTROL</div><p class="toolbar-copy">Every posted entry is written with equal debit and credit totals.</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#journalModal"><span class="bi bi-plus-lg"></span> New journal</button></div>
<section class="panel-card mt-4"><div class="panel-heading"><div><div class="eyebrow">JOURNAL REGISTER</div><h3>Posted and draft entries</h3></div><span class="subtle-label"><?= number_format(count($journals)) ?> shown</span></div>
<div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Journal</th><th>Date</th><th>Description</th><th>Source</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th>Status</th></tr></thead><tbody>
<?php if (!$journals): ?><tr><td colspan="7"><div class="empty-state"><span class="bi bi-journal"></span><strong>No journals yet.</strong><small>Sync POS or HRMS, or create a balanced manual journal.</small></div></td></tr><?php endif; ?>
<?php foreach ($journals as $journal): ?><tr><td><strong><?= e($journal['journal_number']) ?></strong></td><td><?= e(date_label($journal['transaction_date'])) ?></td><td><?= e($journal['description']) ?></td><td><span class="source-tag"><?= e($journal['source_system'] ?: 'manual') ?></span></td><td class="text-end"><?= money($journal['total_debit']) ?></td><td class="text-end"><?= money($journal['total_credit']) ?></td><td><span class="status-badge <?= $journal['status'] === 'posted' ? 'success' : 'warning' ?>"><?= e(ucfirst($journal['status'])) ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<div class="modal fade" id="journalModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow">MANUAL ENTRY</div><h3 class="modal-title">Post a balanced journal</h3></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-md-4"><label class="form-label">Transaction date</label><input class="form-control" type="date" name="transaction_date" value="<?= e(date('Y-m-d')) ?>" required></div><div class="col-md-8"><label class="form-label">Description</label><input class="form-control" name="description" required maxlength="255"></div><div class="col-md-6"><label class="form-label">Debit account</label><select class="form-select" name="debit_account" required><option value="">Choose account</option><?php foreach ($accounts as $account): ?><option value="<?= e($account['id']) ?>"><?= e($account['code'] . ' · ' . $account['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Credit account</label><select class="form-select" name="credit_account" required><option value="">Choose account</option><?php foreach ($accounts as $account): ?><option value="<?= e($account['id']) ?>"><?= e($account['code'] . ' · ' . $account['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-5"><label class="form-label">Amount</label><div class="input-group"><span class="input-group-text">₱</span><input class="form-control" name="amount" type="number" min="0.01" step="0.01" required></div></div><div class="col-md-7"><div class="form-note h-100"><span class="bi bi-shield-check"></span><span>The application will reject any entry where total debits do not equal total credits.</span></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Post journal <span class="bi bi-check2"></span></button></div></form></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>