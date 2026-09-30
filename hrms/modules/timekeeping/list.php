<?php
/**
 * HR / Admin — Timekeeping Overview
 * View all employee attendance for a given date. Edit time-in/out.
 * Auto-marks absent for weekdays where no clock-in exists.
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('hr','admin');

$db   = getDB();
$user = currentUser();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$filterEmp    = (int)($_GET['emp'] ?? 0);

// ── Auto-mark absent employees who didn't clock in for the selected date ──
if ($selectedDate <= date('Y-m-d')) {
    $allEmpStmt = $db->query("SELECT id FROM employees WHERE status = 'active'");
    foreach ($allEmpStmt->fetchAll() as $emp) {
        $exists = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
        $exists->execute([$emp['id'], $selectedDate]);
        if (!$exists->fetch()) {
            $dow = date('N', strtotime($selectedDate));
            if ($dow <= 5) {
                $db->prepare(
                    "INSERT IGNORE INTO attendance (employee_id, date, status, late_minutes)
                     VALUES (?, ?, 'absent', 0)"
                )->execute([$emp['id'], $selectedDate]);
            }
        }
    }
}

// Fetch attendance for selected date
$query = "
    SELECT a.*, COALESCE(e.firstname, 'POS') AS firstname,
           COALESCE(e.lastname, 'Staff') AS lastname,
           COALESCE(e.employee_id, CONCAT('POS-', a.pos_user_id)) AS emp_code,
           p.title AS position, d.name AS department, COALESCE(e.basic_salary, 0) AS basic_salary
    FROM attendance a
    LEFT JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.date = ?
";
$params = [$selectedDate];
if ($filterEmp) {
    $query .= " AND a.employee_id = ?";
    $params[] = $filterEmp;
}
$query .= " ORDER BY e.lastname, e.firstname";
$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

$empList = $db->query(
    "SELECT id, firstname, lastname FROM employees WHERE status='active' ORDER BY lastname"
)->fetchAll();

$totalPresent = count(array_filter($records, fn($r) => in_array($r['status'],['present','late'])));
$totalLate    = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$totalAbsent  = count(array_filter($records, fn($r) => $r['status'] === 'absent'));

$pageTitle = 'Timekeeping';
include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-clock-history me-2 text-primary"></i>Timekeeping</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Timekeeping</li>
        </ol></nav>
    </div>
    <a href="<?= SITE_URL ?>modules/payroll/list.php" class="btn btn-primary btn-sm">
        <i class="bi bi-cash-coin me-1"></i>Generate Payroll
    </a>
</div>

<!-- ── Summary Cards ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-bold text-success"><?= $totalPresent ?></div>
            <div class="small text-muted">Present</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-bold text-warning"><?= $totalLate ?></div>
            <div class="small text-muted">Late</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-bold text-danger"><?= $totalAbsent ?></div>
            <div class="small text-muted">Absent</div>
        </div>
    </div>
</div>

<!-- ── Filters ────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Date</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($selectedDate) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Employee</label>
                <select name="emp" class="form-select form-select-sm">
                    <option value="0">All Employees</option>
                    <?php foreach ($empList as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filterEmp == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['lastname'] . ', ' . $e['firstname']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="list.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Attendance Table ────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Attendance — <?= date('F j, Y', strtotime($selectedDate)) ?></span>
        <span class="badge bg-secondary"><?= count($records) ?> record<?= count($records) != 1 ? 's' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.84rem;">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Late (min)</th>
                    <th>Undertime (min)</th>
                    <th>Deduction</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">No records for this date.</td></tr>
            <?php else: foreach ($records as $r):
                $rLate = (int)($r['late_minutes'] ?? 0);
                $rUt   = (int)($r['undertime_minutes'] ?? 0);
                $ded   = 0;
                if ($r['status'] === 'absent') {
                    $ded = deductionFromAbsentDays((float)$r['basic_salary'], 1);
                } else {
                    if ($rLate > 0) $ded += deductionFromMinutes((float)$r['basic_salary'], $rLate);
                    if ($rUt > 0)   $ded += deductionFromMinutes((float)$r['basic_salary'], $rUt);
                }
            ?>
            <tr>
                <td>
                    <div class="fw-semibold"><?= htmlspecialchars($r['lastname'] . ', ' . $r['firstname']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($r['department'] ?? '—') ?></div>
                </td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['emp_code'] ?? '—') ?></span></td>
                <td>
                    <?php if ($r['status'] === 'present'): ?>
                    <span class="badge bg-success">Present</span>
                    <?php elseif ($r['status'] === 'late'): ?>
                    <span class="badge bg-warning text-dark">Late</span>
                    <?php elseif ($r['status'] === 'absent'): ?>
                    <span class="badge bg-danger">Absent</span>
                    <?php elseif ($r['status'] === 'half_day'): ?>
                    <span class="badge bg-info text-dark">Half Day</span>
                    <?php elseif ($r['status'] === 'leave'): ?>
                    <span class="badge bg-primary">On Leave</span>
                    <?php elseif ($r['status'] === 'holiday'): ?>
                    <span class="badge bg-secondary">Holiday</span>
                    <?php else: ?>
                    <span class="badge bg-secondary"><?= ucfirst($r['status']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= formatTime($r['time_in']) ?></td>
                <td><?= formatTime($r['time_out']) ?></td>
                <td class="<?= $rLate > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= $rLate > 0 ? $rLate . ' min' : '—' ?></td>
                <td class="<?= $rUt > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= $rUt > 0 ? $rUt . ' min' : '—' ?></td>
                <td class="<?= $ded > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= $ded > 0 ? '₱' . number_format($ded, 2) : '—' ?>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                            onclick="editAttendance(<?= $r['id'] ?>,'<?= $r['time_in'] ?? '' ?>','<?= $r['time_out'] ?? '' ?>','<?= $r['status'] ?>','<?= htmlspecialchars($r['firstname'].' '.$r['lastname'], ENT_QUOTES) ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Edit Modal ──────────────────────────────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold small"><i class="bi bi-pencil me-2"></i>Edit Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= SITE_URL ?>modules/timekeeping/attendance_update.php">
                <?= csrfField() ?>
                <input type="hidden" name="attendance_id" id="editAttId">
                <input type="hidden" name="redirect_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <div class="modal-body">
                    <p class="fw-semibold" id="editEmpName"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Time In</label>
                            <input type="time" name="time_in" id="editTimeIn" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Time Out</label>
                            <input type="time" name="time_out" id="editTimeOut" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="absent">Absent</option>
                                <option value="half_day">Half Day</option>
                                <option value="holiday">Holiday</option>
                                <option value="leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmSubmit(this.form, 'Save attendance changes for this employee?', {icon:'question',confirmColor:'#4361ee',confirmText:'Save Changes'})">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAttendance(id, timeIn, timeOut, status, name) {
    document.getElementById('editAttId').value    = id;
    document.getElementById('editEmpName').textContent = name;
    document.getElementById('editTimeIn').value   = timeIn ? timeIn.substring(0,5) : '';
    document.getElementById('editTimeOut').value  = timeOut ? timeOut.substring(0,5) : '';
    document.getElementById('editStatus').value   = status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php include '../../includes/footer.php'; ?>
