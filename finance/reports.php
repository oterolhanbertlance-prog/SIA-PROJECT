<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$title = 'Reports';
$activePage = 'reports';
$report = (string) ($_GET['report'] ?? 'pnl');
$from = (string) ($_GET['from'] ?? date('Y-01-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
$rows = [];
$reportTitle = match ($report) {
    'trial' => 'Trial Balance',
    'ledger' => 'General Ledger',
    'sales' => 'Sales Report',
    'payroll' => 'Payroll Cost',
    default => 'Profit & Loss',
};
try {
    $db = db_finance();
    if ($report === 'sales') {
        $stmt = $db->prepare(
            "SELECT DATE(transaction_date) AS date_label, COUNT(*) AS transactions, SUM(total_amount) AS total
             FROM sales_snapshots WHERE DATE(transaction_date) BETWEEN ? AND ?
             GROUP BY DATE(transaction_date) ORDER BY date_label DESC"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
    } elseif ($report === 'payroll') {
        $stmt = $db->prepare(
            "SELECT CONCAT(payroll_year, '-', LPAD(payroll_month,2,'0')) AS period_label,
                    COUNT(*) AS records, SUM(gross_pay) AS gross, SUM(total_deductions) AS deductions, SUM(net_pay) AS net
             FROM payroll_snapshots WHERE DATE(CONCAT(payroll_year, '-', LPAD(payroll_month,2,'0'), '-01')) BETWEEN ? AND ?
             GROUP BY payroll_year, payroll_month ORDER BY payroll_year DESC, payroll_month DESC"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare(
            "SELECT a.code, a.name, g.statement_group, g.normal_balance,
                    COALESCE(SUM(CASE WHEN je.status='posted' THEN jl.debit ELSE 0 END),0) AS debit,
                    COALESCE(SUM(CASE WHEN je.status='posted' THEN jl.credit ELSE 0 END),0) AS credit
             FROM accounts a JOIN account_groups g ON g.id=a.account_group_id
             LEFT JOIN journal_lines jl ON jl.account_id=a.id
             LEFT JOIN journal_entries je ON je.id=jl.journal_entry_id AND je.transaction_date BETWEEN ? AND ?
             GROUP BY a.id, a.code, a.name, g.statement_group, g.normal_balance ORDER BY a.code"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $exception) {
    flash('error', format_error($exception));
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar"><div><div class="eyebrow">FINANCIAL REPORTING</div><p class="toolbar-copy">Print-friendly local reports based on posted Finance journals and imported source snapshots.</p></div><button class="btn btn-outline-primary" onclick="window.print()"><span class="bi bi-printer"></span> Print</button></div>
<section class="panel-card report-shell mt-4">
    <form class="report-tabs" method="get"><button name="report" value="pnl" class="<?= $report === 'pnl' ? 'active' : '' ?>">Profit & Loss</button><button name="report" value="trial" class="<?= $report === 'trial' ? 'active' : '' ?>">Trial Balance</button><button name="report" value="sales" class="<?= $report === 'sales' ? 'active' : '' ?>">Sales</button><button name="report" value="payroll" class="<?= $report === 'payroll' ? 'active' : '' ?>">Payroll</button><button name="report" value="ledger" class="<?= $report === 'ledger' ? 'active' : '' ?>">General Ledger</button><div class="report-dates"><input type="date" name="from" value="<?= e($from) ?>"><span>to</span><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-sm btn-primary">Run</button></div></form>
    <div class="report-heading"><div class="eyebrow">FINANCE SYSTEM</div><h2><?= e($reportTitle) ?></h2><p><?= e(date_label($from)) ?> – <?= e(date_label($to)) ?> · Generated locally</p></div>
    <?php if (in_array($report, ['pnl', 'trial', 'ledger'], true)): ?>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Code</th><th>Account</th><th>Group</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state">No posted journal activity in this date range.</div></td></tr><?php endif; ?>
        <?php foreach ($rows as $row): $balance = (float) $row['debit'] - (float) $row['credit']; ?><tr><td><strong><?= e($row['code']) ?></strong></td><td><?= e($row['name']) ?></td><td><span class="source-tag"><?= e(ucfirst($row['statement_group'])) ?></span></td><td class="text-end"><?= money($row['debit']) ?></td><td class="text-end"><?= money($row['credit']) ?></td><td class="text-end fw-semibold"><?= money(abs($balance)) ?> <?= $balance >= 0 ? 'Dr' : 'Cr' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php elseif ($report === 'sales'): ?>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Date</th><th>Transactions</th><th class="text-end">Sales total</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="3"><div class="empty-state">No imported sales in this date range.</div></td></tr><?php endif; ?><?php foreach ($rows as $row): ?><tr><td><?= e(date_label($row['date_label'])) ?></td><td><?= e($row['transactions']) ?></td><td class="text-end fw-semibold"><?= money($row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Period</th><th>Records</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net pay</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state">No imported payroll in this date range.</div></td></tr><?php endif; ?><?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['period_label']) ?></strong></td><td><?= e($row['records']) ?></td><td class="text-end"><?= money($row['gross']) ?></td><td class="text-end text-muted"><?= money($row['deductions']) ?></td><td class="text-end fw-semibold"><?= money($row['net']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>