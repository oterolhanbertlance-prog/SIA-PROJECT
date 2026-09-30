<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'Deployment Records';
$db = getDB();

// ── Filters ─────────────────────────────────────────────────────────
$search  = sanitize($_GET['search'] ?? '');
$dept    = (int)($_GET['dept'] ?? 0);
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// ── Query ────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(e.firstname LIKE ? OR e.lastname LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($dept) {
    $where[]  = "e.department_id = ?";
    $params[] = $dept;
}
if ($status) {
    $where[]  = "e.employment_status = ?";
    $params[] = $status;
}

$whereSQL = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM employees e WHERE $whereSQL")
                  ->execute($params) ? $db->prepare("SELECT COUNT(*) FROM employees e WHERE $whereSQL")
                  ->execute($params) : 0;

// Re-run for actual count
$cntStmt = $db->prepare("SELECT COUNT(*) FROM employees e WHERE $whereSQL");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

$offset = ($page - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT e.id, e.employee_id, e.firstname, e.lastname, e.email, e.phone,
            e.employment_status, e.date_hired, e.basic_salary, e.status AS emp_status,
            d.name AS department, p.title AS position,
            u.username AS login_username, u.created_at AS account_created
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN positions   p ON e.position_id   = p.id
     LEFT JOIN users       u ON u.employee_id   = e.id AND u.role = 'employee'
     WHERE $whereSQL
     ORDER BY e.date_hired DESC, e.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-person-badge-fill me-2 text-primary"></i>Deployment Records</h4>
        <p class="text-muted mb-0 small">All applicants who have been deployed as employees</p>
    </div>
    <a href="deploy_applicant.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Deploy Applicant
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Name, employee ID, email…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Department</label>
                <select name="dept" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $dept === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Employment Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['Probationary','Regular','Contractual','Part-Time','Resigned','Terminated'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <?php if ($search || $dept || $status): ?>
                <a href="list.php" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Stats row -->
<?php
$stats = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(employment_status = 'Regular')      AS regular,
        SUM(employment_status = 'Probationary') AS probationary,
        SUM(status = 'active')                  AS active
     FROM employees"
)->fetch();
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary text-white text-center py-3">
            <div class="fs-3 fw-bold"><?= $stats['total'] ?></div>
            <div class="small opacity-75">Total Deployed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success text-white text-center py-3">
            <div class="fs-3 fw-bold"><?= $stats['active'] ?></div>
            <div class="small opacity-75">Active Employees</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-info text-white text-center py-3">
            <div class="fs-3 fw-bold"><?= $stats['regular'] ?></div>
            <div class="small opacity-75">Regular</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-warning text-dark text-center py-3">
            <div class="fs-3 fw-bold"><?= $stats['probationary'] ?></div>
            <div class="small opacity-75">Probationary</div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold">
            <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
            <?= $search || $dept || $status ? '(filtered)' : '' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department / Position</th>
                    <th>Date Hired</th>
                    <th>Status</th>
                    <th>Login Account</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                        No deployment records found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <span class="badge bg-primary font-monospace">
                            <?= htmlspecialchars($emp['employee_id']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($emp['email']) ?></div>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($emp['department'] ?? '—') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($emp['position'] ?? '—') ?></div>
                    </td>
                    <td class="small"><?= formatDate($emp['date_hired']) ?></td>
                    <td><?= statusBadge($emp['employment_status']) ?></td>
                    <td>
                        <?php if ($emp['login_username']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($emp['login_username']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bi bi-x-circle me-1"></i>No account
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="../employees/profile.php?id=<?= $emp['id'] ?>"
                           class="btn btn-sm btn-outline-primary" title="View Profile">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="../employees/edit.php?id=<?= $emp['id'] ?>"
                           class="btn btn-sm btn-outline-secondary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="add_contract.php?employee_id=<?= $emp['id'] ?>"
                           class="btn btn-sm btn-outline-info" title="Add Contract">
                            <i class="bi bi-file-earmark-plus"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
        <?= paginate($total, $perPage, $page, 'list.php?' . http_build_query(array_filter(['search'=>$search,'dept'=>$dept,'status'=>$status])) . '&page=') ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
