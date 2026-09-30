<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'Employees';
$db = getDB();

$search = sanitize($_GET['search'] ?? '');
$dept   = (int)($_GET['dept'] ?? 0);
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where = ["e.status != 'archived'"];
$params = [];
if ($search) {
    $where[] = "(e.firstname LIKE ? OR e.lastname LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($dept) { $where[] = "e.department_id = ?"; $params[] = $dept; }
if ($status) { $where[] = "e.employment_status = ?"; $params[] = $status; }

$whereStr = 'WHERE ' . implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM employees e $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title FROM employees e LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN positions p ON e.position_id = p.id $whereStr ORDER BY e.firstname, e.lastname LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-person-lines-fill me-2 text-primary"></i>Employees</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Employees</li></ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Employee</a>
        <a href="../reports/employees.php" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <div class="search-box w-100">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search name, ID, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="dept" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?=$d['id']?>" <?= $dept==$d['id']?'selected':''?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['Regular','Probationary','Contractual','Part-Time','Resigned','Terminated'] as $s): ?>
                    <option value="<?=$s?>" <?= $status==$s?'selected':''?>><?=$s?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="list.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="empTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>ID</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Employment Status</th>
                    <th>Date Hired</th>
                    <th>Basic Salary</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($employees)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-people fs-2 d-block mb-2"></i>No employees found.</td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($emp['profile_picture'] && $emp['profile_picture'] !== 'default.png' && file_exists(UPLOAD_PATH . 'profiles/' . $emp['profile_picture'])): ?>
                        <img src="<?=SITE_URL?>uploads/profiles/<?= htmlspecialchars($emp['profile_picture']) ?>" class="avatar avatar-sm">
                        <?php else: ?>
                        <div class="avatar avatar-sm avatar-placeholder rounded-circle"><?= strtoupper(substr($emp['firstname'],0,1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?></div>
                            <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($emp['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($emp['employee_id']) ?></span></td>
                <td><?= htmlspecialchars($emp['dept_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($emp['pos_title'] ?? '—') ?></td>
                <td><?= statusBadge($emp['employment_status']) ?></td>
                <td><?= formatDate($emp['date_hired']) ?></td>
                <td><?= money($emp['basic_salary']) ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="profile.php?id=<?=$emp['id']?>" class="btn btn-xs btn-sm btn-info py-0 px-2" title="View Profile"><i class="bi bi-eye"></i></a>
                        <a href="edit.php?id=<?=$emp['id']?>" class="btn btn-xs btn-sm btn-warning py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="#" onclick="confirmDelete('delete.php?id=<?=$emp['id']?>','<?= htmlspecialchars($emp['firstname'].' '.$emp['lastname'],ENT_QUOTES) ?>')" class="btn btn-xs btn-sm btn-danger py-0 px-2" title="Delete"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> employees</small>
        <?= paginate($total, $perPage, $page, 'list.php?' . http_build_query(['search'=>$search,'dept'=>$dept,'status'=>$status])) ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
