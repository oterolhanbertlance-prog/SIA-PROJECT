<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole('admin');

$pageTitle = 'Finance Entries';

$db = getDB();
$msg = '';
$msgType = 'success';

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

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $type        = in_array($_POST['type'] ?? '', ['income','expense']) ? $_POST['type'] : 'expense';
            $category    = sanitize($_POST['category'] ?? 'General');
            $description = sanitize($_POST['description'] ?? '');
            $amount      = (float)($_POST['amount'] ?? 0);
            $entryDate   = $_POST['entry_date'] ?? date('Y-m-d');
            $reference   = sanitize($_POST['reference'] ?? '');

            if ($amount <= 0) throw new Exception('Amount must be greater than zero.');

            $db->prepare("INSERT INTO finance_entries (type, category, description, amount, entry_date, reference, created_by)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([$type, $category, $description, $amount, $entryDate, $reference, $_SESSION['user_id']]);
            auditLog('CREATE', 'finance', "Added $type entry: $category — " . money($amount));
            $msg = 'Finance entry added successfully.';

        } elseif ($action === 'delete') {
            $id = (int)($_POST['entry_id'] ?? 0);
            $db->prepare("DELETE FROM finance_entries WHERE id=?")->execute([$id]);
            auditLog('DELETE', 'finance', "Deleted finance entry #$id");
            $msg = 'Entry deleted.';
        }
    } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
}

// Filters
$type   = in_array($_GET['type'] ?? '', ['income','expense']) ? $_GET['type'] : '';
$month  = (int)($_GET['month'] ?? date('n'));
$year   = (int)($_GET['year'] ?? date('Y'));
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE MONTH(entry_date)=? AND YEAR(entry_date)=?";
$params = [$month, $year];
if ($type) { $where .= ' AND type=?'; $params[] = $type; }

try {
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM finance_entries $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM finance_entries $where ORDER BY entry_date DESC, id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    $sumIncome  = (float)$db->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE type='income' AND MONTH(entry_date)=? AND YEAR(entry_date)=?")->execute([$month,$year]) ? 0 : 0;
    $si = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE type='income' AND MONTH(entry_date)=? AND YEAR(entry_date)=?");
    $si->execute([$month,$year]); $sumIncome = (float)$si->fetchColumn();
    $se = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_entries WHERE type='expense' AND MONTH(entry_date)=? AND YEAR(entry_date)=?");
    $se->execute([$month,$year]); $sumExpense = (float)$se->fetchColumn();
} catch (Throwable $e) { $entries=[]; $total=0; $sumIncome=$sumExpense=0; }

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4><i class="bi bi-coin me-2"></i>Finance Entries</h4>
    <div class="d-flex gap-2">
        <a href="<?= SITE_URL ?>modules/finance/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart-line me-1"></i>Reports
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal">
            <i class="bi bi-plus-lg me-1"></i>Add Entry
        </button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss">
    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Monthly Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center h-100 border-success">
            <div class="card-body">
                <div class="fs-3 fw-bold text-success"><?= money($sumIncome) ?></div>
                <div class="text-muted small">Income — <?= monthName($month) ?> <?= $year ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100 border-danger">
            <div class="card-body">
                <div class="fs-3 fw-bold text-danger"><?= money($sumExpense) ?></div>
                <div class="text-muted small">Expenses — <?= monthName($month) ?> <?= $year ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100 border-<?= $sumIncome - $sumExpense >= 0 ? 'primary' : 'danger' ?>">
            <div class="card-body">
                <div class="fs-3 fw-bold text-<?= $sumIncome - $sumExpense >= 0 ? 'primary' : 'danger' ?>"><?= money($sumIncome - $sumExpense) ?></div>
                <div class="text-muted small">Net — <?= monthName($month) ?> <?= $year ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="month" class="form-select form-select-sm">
                    <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= monthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" min="2000" max="2100">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="income" <?= $type==='income'?'selected':'' ?>>Income</option>
                    <option value="expense" <?= $type==='expense'?'selected':'' ?>>Expense</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Reference</th><th class="text-end">Amount</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($entries)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No entries for this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?= formatDate($e['entry_date']) ?></td>
                    <td><span class="badge bg-<?= $e['type']==='income'?'success':'danger' ?>"><?= ucfirst($e['type']) ?></span></td>
                    <td><?= htmlspecialchars($e['category']) ?></td>
                    <td><?= htmlspecialchars($e['description'] ?: '—') ?></td>
                    <td><code><?= htmlspecialchars($e['reference'] ?: '—') ?></code></td>
                    <td class="text-end fw-semibold <?= $e['type']==='income'?'text-success':'text-danger' ?>"><?= money($e['amount']) ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this entry?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= paginate($total, $perPage, $page, '?' . http_build_query(['month'=>$month,'year'=>$year,'type'=>$type])) ?>

<!-- Add Entry Modal -->
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Finance Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" placeholder="e.g. Sales, Utilities" value="General">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Reference / Invoice No.</label>
                            <input type="text" name="reference" class="form-control" placeholder="e.g. INV-001">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Entry</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
