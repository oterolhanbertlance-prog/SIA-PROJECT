<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole('admin', 'hr');

$pageTitle = 'Inventory Reports';

$db = getDB();

// Ensure tables exist before querying
try {
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_items (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(150) NOT NULL,
        sku          VARCHAR(60)  DEFAULT NULL,
        category     VARCHAR(100) DEFAULT 'General',
        quantity     INT          NOT NULL DEFAULT 0,
        unit         VARCHAR(30)  NOT NULL DEFAULT 'pcs',
        reorder_level INT         NOT NULL DEFAULT 0,
        cost_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes        TEXT         DEFAULT NULL,
        created_by   INT          DEFAULT NULL,
        created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        item_id      INT NOT NULL,
        adjustment   INT NOT NULL DEFAULT 0,
        reason       VARCHAR(255) DEFAULT NULL,
        created_by   INT DEFAULT NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Summary stats
try {
    $totalItems   = (int)$db->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
    $totalValue   = (float)$db->query("SELECT COALESCE(SUM(quantity * cost_price),0) FROM inventory_items")->fetchColumn();
    $lowStock     = (int)$db->query("SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level AND reorder_level > 0")->fetchColumn();
    $outOfStock   = (int)$db->query("SELECT COUNT(*) FROM inventory_items WHERE quantity = 0")->fetchColumn();
} catch (Throwable $e) { $totalItems=$lowStock=$outOfStock=0; $totalValue=0; }

// By category
try {
    $byCategory = $db->query("SELECT category, COUNT(*) AS items, SUM(quantity) AS total_qty, SUM(quantity*cost_price) AS total_value
                              FROM inventory_items GROUP BY category ORDER BY total_value DESC")->fetchAll();
} catch (Throwable $e) { $byCategory = []; }

// Low stock items
try {
    $lowItems = $db->query("SELECT * FROM inventory_items WHERE quantity <= reorder_level AND reorder_level > 0 ORDER BY quantity ASC LIMIT 20")->fetchAll();
} catch (Throwable $e) { $lowItems = []; }

// Recent transactions
try {
    $recentTx = $db->query("SELECT t.*, i.name AS item_name, u.username FROM inventory_transactions t
        LEFT JOIN inventory_items i ON i.id = t.item_id
        LEFT JOIN users u ON u.id = t.created_by
        ORDER BY t.created_at DESC LIMIT 30")->fetchAll();
} catch (Throwable $e) { $recentTx = []; }

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h4><i class="bi bi-graph-up me-2"></i>Inventory Reports</h4>
    <a href="<?= SITE_URL ?>modules/inventory/stock.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Stock
    </a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary"><?= $totalItems ?></div>
                <div class="text-muted small">Total Items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success"><?= money($totalValue) ?></div>
                <div class="text-muted small">Total Stock Value</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning"><?= $lowStock ?></div>
                <div class="text-muted small">Low Stock Items</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger"><?= $outOfStock ?></div>
                <div class="text-muted small">Out of Stock</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- By Category -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-tag me-2"></i>Stock by Category</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Category</th><th class="text-center">Items</th><th class="text-end">Total Qty</th><th class="text-end">Value</th></tr></thead>
                        <tbody>
                        <?php if (empty($byCategory)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No data yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($byCategory as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['category'] ?: 'Uncategorized') ?></td>
                            <td class="text-center"><?= $row['items'] ?></td>
                            <td class="text-end"><?= number_format($row['total_qty']) ?></td>
                            <td class="text-end"><?= money($row['total_value']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alerts</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-center">Reorder At</th></tr></thead>
                        <tbody>
                        <?php if (empty($lowItems)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3"><i class="bi bi-check-circle text-success me-1"></i>No low-stock alerts.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($lowItems as $item): ?>
                        <tr class="<?= $item['quantity'] == 0 ? 'table-danger' : 'table-warning' ?>">
                            <td><?= htmlspecialchars($item['name']) ?>
                                <?php if ($item['sku']): ?><br><code class="small"><?= htmlspecialchars($item['sku']) ?></code><?php endif; ?>
                            </td>
                            <td class="text-center fw-bold"><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                            <td class="text-center"><?= $item['reorder_level'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Stock Adjustments</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Item</th><th class="text-center">Adjustment</th><th>Reason</th><th>By</th></tr></thead>
                        <tbody>
                        <?php if (empty($recentTx)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No transactions recorded yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentTx as $tx): ?>
                        <tr>
                            <td><small><?= formatDateTime($tx['created_at']) ?></small></td>
                            <td><?= htmlspecialchars($tx['item_name'] ?? '—') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $tx['adjustment'] >= 0 ? 'success' : 'danger' ?>">
                                    <?= $tx['adjustment'] >= 0 ? '+' : '' ?><?= $tx['adjustment'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($tx['reason'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($tx['username'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
