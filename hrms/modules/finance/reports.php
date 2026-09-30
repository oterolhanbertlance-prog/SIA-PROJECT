<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole('admin');

$pageTitle = 'Finance Reports';

$db = getDB();
$year = (int)($_GET['year'] ?? date('Y'));

// Ensure table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS finance_entries (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        type         ENUM('income','expense') NOT NULL,
        category     VARCHAR(100) NOT NULL DEFAULT 'General',
        description  TEXT DEFAULT NULL,
        amount       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        entry_date   DATE NOT NULL,
        reference    VARCHAR(100) DEFAULT NULL,
        attachment   VARCHAR(255) DEFAULT NULL,
        branch_id    INT DEFAULT NULL,
        created_by   INT DEFAULT NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

// Monthly breakdown
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    try {
        $si = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE type='income' AND YEAR(entry_date)=? AND MONTH(entry_date)=?");
        $si->execute([$year,$m]); $income = (float)$si->fetchColumn();
        $se = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE type='expense' AND YEAR(entry_date)=? AND MONTH(entry_date)=?");
        $se->execute([$year,$m]); $expense = (float)$se->fetchColumn();
        $monthlyData[$m] = ['income'=>$income,'expense'=>$expense,'net'=>$income-$expense];
    } catch (Throwable $e) { $monthlyData[$m] = ['income'=>0,'expense'=>0,'net'=>0]; }
}

// Year totals
$yearIncome  = array_sum(array_column($monthlyData, 'income'));
$yearExpense = array_sum(array_column($monthlyData, 'expense'));
$yearNet     = $yearIncome - $yearExpense;

// By category
try {
    $byCategory = $db->prepare("SELECT category, type, COALESCE(SUM(amount),0) AS total FROM finance_entries WHERE YEAR(entry_date)=? GROUP BY category, type ORDER BY type, total DESC");
    $byCategory->execute([$year]); $byCategory = $byCategory->fetchAll();
} catch (Throwable $e) { $byCategory = []; }

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-bar-chart-line-fill me-2"></i>Finance Reports</h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2">
            <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" min="2000" max="2100" style="width:90px;">
            <button class="btn btn-sm btn-primary">Go</button>
        </form>
        <a href="<?= SITE_URL ?>modules/finance/list.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list me-1"></i>Entries
        </a>
    </div>
</div>

<!-- Year Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center border-success h-100">
            <div class="card-body">
                <div class="fs-3 fw-bold text-success"><?= money($yearIncome) ?></div>
                <div class="text-muted small">Total Income <?= $year ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-danger h-100">
            <div class="card-body">
                <div class="fs-3 fw-bold text-danger"><?= money($yearExpense) ?></div>
                <div class="text-muted small">Total Expenses <?= $year ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-<?= $yearNet >= 0 ? 'primary' : 'danger' ?> h-100">
            <div class="card-body">
                <div class="fs-3 fw-bold text-<?= $yearNet >= 0 ? 'primary' : 'danger' ?>"><?= money($yearNet) ?></div>
                <div class="text-muted small">Net Profit <?= $year ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Table -->
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-table me-2"></i>Monthly Breakdown — <?= $year ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Month</th><th class="text-end text-success">Income</th><th class="text-end text-danger">Expenses</th><th class="text-end">Net</th></tr>
                </thead>
                <tbody>
                <?php foreach ($monthlyData as $m => $row): ?>
                <tr>
                    <td><?= monthName($m) ?> <?= $year ?></td>
                    <td class="text-end text-success"><?= money($row['income']) ?></td>
                    <td class="text-end text-danger"><?= money($row['expense']) ?></td>
                    <td class="text-end fw-semibold <?= $row['net'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= money($row['net']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold">
                    <tr class="table-active">
                        <td>Total</td>
                        <td class="text-end text-success"><?= money($yearIncome) ?></td>
                        <td class="text-end text-danger"><?= money($yearExpense) ?></td>
                        <td class="text-end <?= $yearNet >= 0 ? 'text-primary' : 'text-danger' ?>"><?= money($yearNet) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- By Category -->
<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-tag me-2"></i>By Category — <?= $year ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Category</th><th>Type</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php if (empty($byCategory)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No data for <?= $year ?>.</td></tr>
                <?php endif; ?>
                <?php foreach ($byCategory as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><span class="badge bg-<?= $row['type']==='income'?'success':'danger' ?>"><?= ucfirst($row['type']) ?></span></td>
                    <td class="text-end fw-semibold <?= $row['type']==='income'?'text-success':'text-danger' ?>"><?= money($row['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
