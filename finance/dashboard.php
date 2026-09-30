<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$title = 'Dashboard';
$activePage = 'dashboard';
$dbError = null;
$metrics = [
    'today_sales' => 0, 'monthly_sales' => 0, 'gross_profit' => 0, 'net_income' => 0,
    'cash' => 0, 'bank' => 0, 'ar' => 0, 'ap' => 0, 'payroll' => 0, 'inventory' => 0,
];
$recentJournals = [];
$salesTrend = [];
try {
    $db = db_finance();
    $metrics['today_sales'] = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_snapshots WHERE DATE(transaction_date) = CURDATE()")->fetchColumn();
    $metrics['monthly_sales'] = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_snapshots WHERE YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=MONTH(CURDATE())")->fetchColumn();
    $balances = $db->query(
        "SELECT a.code, g.statement_group, g.normal_balance,
                COALESCE(SUM(jl.debit - jl.credit), 0) AS balance
         FROM accounts a JOIN account_groups g ON g.id=a.account_group_id
         LEFT JOIN journal_lines jl ON jl.account_id=a.id
         LEFT JOIN journal_entries je ON je.id=jl.journal_entry_id AND je.status='posted'
         GROUP BY a.id, a.code, g.statement_group, g.normal_balance"
    )->fetchAll();
    $byCode = [];
    foreach ($balances as $balance) {
        $value = (float) $balance['balance'];
        $byCode[$balance['code']] = $balance['normal_balance'] === 'credit' ? -$value : $value;
    }
    $metrics['cash'] = $byCode['1000'] ?? 0;
    $metrics['bank'] = $byCode['1010'] ?? 0;
    $metrics['ar'] = $byCode['1100'] ?? 0;
    $metrics['ap'] = abs($byCode['2000'] ?? 0);
    $metrics['payroll'] = (float) $db->query("SELECT COALESCE(SUM(gross_pay),0) FROM payroll_snapshots WHERE payroll_year=YEAR(CURDATE())")->fetchColumn();
    $metrics['inventory'] = $byCode['1200'] ?? 0;
    $revenue = abs($byCode['4000'] ?? 0);
    $cogs = abs($byCode['5000'] ?? 0);
    $operating = 0;
    foreach ($balances as $balance) {
        if ($balance['statement_group'] === 'expense') {
            $operating += abs((float) $balance['balance']);
        }
    }
    $metrics['gross_profit'] = $revenue - $cogs;
    $metrics['net_income'] = $revenue - $operating;
    $recentJournals = $db->query(
        "SELECT journal_number, transaction_date, description, total_debit, status, source_system
         FROM journal_entries ORDER BY id DESC LIMIT 6"
    )->fetchAll();
    $salesTrend = $db->query(
        "SELECT DATE(transaction_date) AS day, SUM(total_amount) AS total
         FROM sales_snapshots WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(transaction_date) ORDER BY day"
    )->fetchAll();
} catch (Throwable $exception) {
    $dbError = format_error($exception);
}
require __DIR__ . '/includes/header.php';
?>
<?php if ($dbError): ?><div class="alert alert-danger"><?= e($dbError) ?></div><?php endif; ?>
<section class="hero-banner">
    <div>
        <div class="eyebrow"><?= e(strtoupper(date('l'))) ?> · <?= e(date('M d, Y')) ?></div>
        <h2>Good morning, <?= e(explode(' ', trim((string) $user['full_name']))[0]) ?>.</h2>
        <p>Your local financial picture, reconciled from Finance, HRMS, and Milk Tea POS.</p>
    </div>
    <a class="btn btn-light" href="integration.php"><span class="bi bi-arrow-repeat"></span> Run sync</a>
</section>
<div class="metric-grid">
    <?php foreach ([
        ['Today’s sales', $metrics['today_sales'], 'accent-blue', 'bi-graph-up-arrow'],
        ['Monthly sales', $metrics['monthly_sales'], 'accent-cyan', 'bi-calendar3'],
        ['Gross profit', $metrics['gross_profit'], 'accent-green', 'bi-stars'],
        ['Net income', $metrics['net_income'], 'accent-purple', 'bi-wallet2'],
        ['Cash', $metrics['cash'], 'accent-amber', 'bi-cash-stack'],
        ['Bank', $metrics['bank'], 'accent-blue', 'bi-bank'],
        ['Accounts receivable', $metrics['ar'], 'accent-cyan', 'bi-arrow-down-left'],
        ['Accounts payable', $metrics['ap'], 'accent-red', 'bi-arrow-up-right'],
    ] as [$label, $value, $color, $icon]): ?>
        <div class="metric-card <?= e($color) ?>">
            <div class="metric-top"><span><?= e($label) ?></span><span class="metric-icon"><span class="bi <?= e($icon) ?>"></span></span></div>
            <strong><?= money($value) ?></strong>
            <small>Posted Finance balance</small>
        </div>
    <?php endforeach; ?>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <section class="panel-card chart-panel">
            <div class="panel-heading"><div><div class="eyebrow">PERFORMANCE</div><h3>Sales trend</h3></div><span class="subtle-label">Last 7 days</span></div>
            <div class="mini-chart" data-chart='<?= e(json_encode($salesTrend)) ?>'>
                <?php if (!$salesTrend): ?><div class="empty-state">Sync POS to see the local sales trend.</div><?php endif; ?>
                <?php foreach ($salesTrend as $point): ?><div class="chart-bar-wrap"><div class="chart-value"><?= money($point['total']) ?></div><div class="chart-bar" style="height:<?= max(8, min(100, ((float) $point['total'] / max(1, max(array_column($salesTrend, 'total')))) * 100)) ?>%"></div><span><?= e(date('D', strtotime($point['day']))) ?></span></div><?php endforeach; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel-card h-100">
            <div class="panel-heading"><div><div class="eyebrow">CONTROL CHECK</div><h3>Close-ready snapshot</h3></div><span class="status-badge success">Local</span></div>
            <div class="check-list">
                <div><span class="check-dot done"><span class="bi bi-check"></span></span><span>Balanced journals enforced</span></div>
                <div><span class="check-dot done"><span class="bi bi-check"></span></span><span>Source data remains read-only</span></div>
                <div><span class="check-dot"><span class="bi bi-dash"></span></span><span>Bank reconciliation</span><small>Not configured</small></div>
                <div><span class="check-dot"><span class="bi bi-dash"></span></span><span>Inventory costing</span><small>Manual setup</small></div>
            </div>
            <a href="reports.php" class="panel-link">Open reports <span class="bi bi-arrow-up-right"></span></a>
        </section>
    </div>
</div>
<section class="panel-card mt-4">
    <div class="panel-heading"><div><div class="eyebrow">AUDITABLE ACTIVITY</div><h3>Recent journals</h3></div><a class="btn btn-sm btn-outline-primary" href="journal.php">View all</a></div>
    <div class="table-responsive">
        <table class="table align-middle finance-table"><thead><tr><th>Journal</th><th>Date</th><th>Description</th><th>Source</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
        <?php if (!$recentJournals): ?><tr><td colspan="6"><div class="empty-state">No journals yet. Run an HRMS or POS sync to create the first balanced entries.</div></td></tr><?php endif; ?>
        <?php foreach ($recentJournals as $journal): ?><tr><td><strong><?= e($journal['journal_number']) ?></strong></td><td><?= e(date_label($journal['transaction_date'])) ?></td><td><?= e($journal['description']) ?></td><td><span class="source-tag"><?= e($journal['source_system'] ?: 'manual') ?></span></td><td class="text-end"><?= money($journal['total_debit']) ?></td><td><span class="status-badge success"><?= e(ucfirst($journal['status'])) ?></span></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>