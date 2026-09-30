<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$title = 'Sales';
$activePage = 'sales';
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
$sales = [];
$total = 0.0;
try {
    $stmt = db_finance()->prepare(
        'SELECT s.*, COUNT(si.id) AS item_count
         FROM sales_snapshots s LEFT JOIN sales_items si ON si.sale_snapshot_id=s.id
         WHERE DATE(s.transaction_date) BETWEEN ? AND ?
         GROUP BY s.id ORDER BY s.transaction_date DESC LIMIT 200'
    );
    $stmt->execute([$from, $to]);
    $sales = $stmt->fetchAll();
    $total = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], $sales));
} catch (Throwable $exception) {
    flash('error', format_error($exception));
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar">
    <div><div class="eyebrow">POS LEDGER</div><p class="toolbar-copy">Imported sales are never written back to Milk Tea POS.</p></div>
    <a class="btn btn-primary" href="integration.php"><span class="bi bi-arrow-repeat"></span> Sync POS</a>
</div>
<div class="summary-strip"><div><span>Selected period</span><strong><?= e(date_label($from)) ?> – <?= e(date_label($to)) ?></strong></div><div><span>Imported transactions</span><strong><?= number_format(count($sales)) ?></strong></div><div><span>Imported sales</span><strong><?= money($total) ?></strong></div><div><span>Accounting treatment</span><strong>Cash / Bank / E-wallet → Revenue</strong></div></div>
<section class="panel-card mt-4">
    <form class="filter-row" method="get"><label>From <input type="date" class="form-control" name="from" value="<?= e($from) ?>"></label><label>To <input type="date" class="form-control" name="to" value="<?= e($to) ?>"></label><button class="btn btn-outline-primary align-self-end">Apply filter</button></form>
    <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Transaction</th><th>Date</th><th>Payment</th><th>Order</th><th>Items</th><th>Status</th><th class="text-end">Amount</th></tr></thead><tbody>
    <?php if (!$sales): ?><tr><td colspan="7"><div class="empty-state"><span class="bi bi-receipt"></span><strong>No imported sales for this period.</strong><small>This is an empty register, not a system error. Open Integration, test the local POS connection, then run Sync POS.</small><a class="btn btn-sm btn-outline-primary mt-2" href="integration.php">Open POS Integration</a></div></td></tr><?php endif; ?>
    <?php foreach ($sales as $sale): ?><tr><td><strong><?= e(str_replace('transaction:', '#', $sale['source_id'])) ?></strong></td><td><?= e(date('M d, Y H:i', strtotime($sale['transaction_date']))) ?></td><td><span class="source-tag"><?= e(ucfirst($sale['payment_method'])) ?></span></td><td><?= e(ucfirst($sale['order_type'] ?: 'pickup')) ?></td><td><?= e($sale['item_count']) ?></td><td><span class="status-badge success"><?= e(ucfirst($sale['status'])) ?></span></td><td class="text-end fw-semibold"><?= money($sale['total_amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>