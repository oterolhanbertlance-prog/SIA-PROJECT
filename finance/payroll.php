<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$title = 'Payroll';
$activePage = 'payroll';
$payroll = [];
$grossTotal = 0.0;
$netTotal = 0.0;
try {
    $payroll = db_finance()->query(
        'SELECT p.*, e.full_name, e.department_name
         FROM payroll_snapshots p LEFT JOIN employee_snapshots e ON e.source_id=p.employee_source_id
         ORDER BY p.payroll_year DESC, p.payroll_month DESC, p.id DESC LIMIT 200'
    )->fetchAll();
    $grossTotal = array_sum(array_map(static fn(array $row): float => (float) $row['gross_pay'], $payroll));
    $netTotal = array_sum(array_map(static fn(array $row): float => (float) $row['net_pay'], $payroll));
} catch (Throwable $exception) {
    flash('error', format_error($exception));
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar"><div><div class="eyebrow">HRMS PAYROLL BRIDGE</div><p class="toolbar-copy">Payroll is posted as balanced expense and liability journals using the actual HRMS fields.</p></div><a class="btn btn-primary" href="integration.php"><span class="bi bi-arrow-repeat"></span> Sync HRMS</a></div>
<div class="summary-strip"><div><span>Payroll records</span><strong><?= number_format(count($payroll)) ?></strong></div><div><span>Gross payroll</span><strong><?= money($grossTotal) ?></strong></div><div><span>Net payable</span><strong><?= money($netTotal) ?></strong></div><div><span>Posting rule</span><strong>Expense = liabilities + net pay</strong></div></div>
<section class="panel-card mt-4"><div class="panel-heading"><div><div class="eyebrow">IMPORTED PAYROLL</div><h3>Payroll register</h3></div><span class="subtle-label">Read-only HRMS snapshot</span></div>
<div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Payroll</th><th>Employee</th><th>Period</th><th>Department</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th><th>Journal</th></tr></thead><tbody>
<?php if (!$payroll): ?><tr><td colspan="8"><div class="empty-state"><span class="bi bi-people"></span><strong>No imported payroll records.</strong><small>Run an HRMS sync from Integration when the local HRMS database is available.</small></div></td></tr><?php endif; ?>
<?php foreach ($payroll as $row): ?><tr><td><strong><?= e($row['payroll_code'] ?: str_replace('payroll:', '#', $row['source_id'])) ?></strong></td><td><?= e($row['full_name'] ?: 'Employee snapshot pending') ?></td><td><?= e(sprintf('%02d/%s', $row['payroll_month'], $row['payroll_year'])) ?></td><td><?= e($row['department_name'] ?: '—') ?></td><td class="text-end"><?= money($row['gross_pay']) ?></td><td class="text-end text-muted"><?= money($row['total_deductions']) ?></td><td class="text-end fw-semibold"><?= money($row['net_pay']) ?></td><td><?php if ($row['journal_entry_id']): ?><span class="status-badge success">Posted</span><?php else: ?><span class="status-badge warning">Pending</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>