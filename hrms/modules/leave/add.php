<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$pageTitle = 'File Leave Request';
$db  = getDB();
$errors = [];
$cu  = currentUser();

// Pre-fill employee from query param (admin/hr filing for employee)
$preEmpId = (int)($_GET['emp_id'] ?? 0);
if (hasRole('employee')) $preEmpId = $cu['emp_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $empId       = hasRole('employee') ? $cu['emp_id'] : (int)($_POST['employee_id'] ?? 0);
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate   = sanitize($_POST['start_date'] ?? '');
        $endDate     = sanitize($_POST['end_date'] ?? '');
        $reason      = sanitize($_POST['reason'] ?? '');

        if (!$empId)       $errors[] = 'Employee is required.';
        if (!$leaveTypeId) $errors[] = 'Leave type is required.';

        // ── Date validation fixes ────────────────────────────────
        if (!$startDate) {
            $errors[] = 'Start date is required.';
        } elseif (!strtotime($startDate)) {
            $errors[] = 'Start date is not a valid date.';
        }

        if (!$endDate) {
            $errors[] = 'End date is required.';
        } elseif (!strtotime($endDate)) {
            $errors[] = 'End date is not a valid date.';
        }

        if ($startDate && $endDate) {
            if (strtotime($startDate) > strtotime($endDate)) {
                $errors[] = 'End date must be on or after the start date.';
            }
            // Admin/HR can file for past dates; employees cannot file past today
            if (hasRole('employee') && strtotime($startDate) < strtotime(date('Y-m-d'))) {
                $errors[] = 'You cannot file a leave request with a start date in the past.';
            }
        }

        if (empty($errors)) {
            // Calculate working days (Mon–Fri only)
            $start    = new DateTime($startDate);
            $end      = new DateTime($endDate);
            $endPlus  = clone $end;
            $endPlus->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period   = new DatePeriod($start, $interval, $endPlus);
            $days = 0;
            foreach ($period as $d) {
                if ($d->format('N') < 6) $days++;
            }

            if ($days === 0) {
                $errors[] = 'The selected dates fall entirely on weekends. Please choose working days.';
            }
        }

        if (empty($errors)) {
            // Check leave balance
            $balStmt = $db->prepare(
                "SELECT remaining_days FROM leave_balance
                 WHERE employee_id=? AND leave_type_id=? AND year=?"
            );
            $balStmt->execute([$empId, $leaveTypeId, date('Y')]);
            $bal = $balStmt->fetch();

            if ($bal !== false && $bal['remaining_days'] < $days) {
                $errors[] = "Insufficient leave balance. You have " . max(0, $bal['remaining_days']) . " day(s) remaining.";
            }

            // Check for overlapping leaves
            $overlap = $db->prepare(
                "SELECT id FROM leave_requests
                 WHERE employee_id=? AND status NOT IN ('rejected','cancelled')
                   AND start_date <= ? AND end_date >= ?"
            );
            $overlap->execute([$empId, $endDate, $startDate]);
            if ($overlap->fetch()) {
                $errors[] = 'You already have an approved or pending leave overlapping this date range.';
            }
        }

        if (empty($errors)) {
            $code = generateLeaveCode();

            // File attachment
            $filePath = null;
            if (!empty($_FILES['file_attachment']['name'])) {
                $uploaded = uploadFile($_FILES['file_attachment'], 'documents');
                if ($uploaded) $filePath = basename($uploaded);
            }

            $db->prepare(
                "INSERT INTO leave_requests
                 (leave_code, employee_id, leave_type_id, start_date, end_date, total_days, reason, file_attachment, status)
                 VALUES (?,?,?,?,?,?,?,?,'pending')"
            )->execute([$code, $empId, $leaveTypeId, $startDate, $endDate, $days, $reason, $filePath]);

            // Update balance (reserve days)
            if ($bal !== false) {
                $db->prepare(
                    "UPDATE leave_balance
                     SET used_days=used_days+?, remaining_days=remaining_days-?
                     WHERE employee_id=? AND leave_type_id=? AND year=?"
                )->execute([$days, $days, $empId, $leaveTypeId, date('Y')]);
            }

            auditLog('CREATE', 'Leave', "Filed leave request $code");
            $redirect = hasRole('employee') ? 'my_leave.php' : 'list.php';
            header("Location: $redirect?success=" . urlencode("Leave request $code filed successfully."));
            exit;
        }
    }
}

$employees  = $db->query(
    "SELECT id, CONCAT(firstname,' ',lastname) as name, employee_id FROM employees WHERE status='active' ORDER BY firstname"
)->fetchAll();
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status='active' ORDER BY name")->fetchAll();

// Min date for start: today for employees, any past date for admin/hr
$minDate = hasRole('employee') ? date('Y-m-d') : '';

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-calendar-plus me-2 text-primary"></i>File Leave Request</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= hasRole('employee')?'my_leave.php':'list.php' ?>">Leave</a></li>
            <li class="breadcrumb-item active">File Request</li>
        </ol></nav>
    </div>
    <a href="<?= hasRole('employee')?'my_leave.php':'list.php' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="leaveForm">
            <?= csrfField() ?>

            <?php if (hasRole('admin','hr')): ?>
            <div class="mb-3">
                <label class="form-label">Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-select" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?=$emp['id']?>" <?=$preEmpId==$emp['id']?'selected':''?>>
                        <?= htmlspecialchars($emp['name'].' ('.$emp['employee_id'].')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                <select name="leave_type_id" class="form-select" required id="leaveTypeSelect">
                    <option value="">Select Leave Type</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?=$lt['id']?>"
                        data-days="<?=$lt['days_allowed']?>"
                        <?=(($_POST['leave_type_id']??'')==$lt['id'])?'selected':''?>>
                        <?= htmlspecialchars($lt['name']) ?> (<?=$lt['days_allowed']?> days/yr)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" id="startDate" class="form-control" required
                           <?= $minDate ? 'min="'.$minDate.'"' : '' ?>
                           value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>"
                           onchange="updateEndMin()">
                    <?php if (hasRole('employee')): ?>
                    <small class="text-muted">Cannot be in the past.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" id="endDate" class="form-control" required
                           <?= $minDate ? 'min="'.$minDate.'"' : '' ?>
                           value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                    <small class="text-muted">Must be on or after the start date.</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3"
                          placeholder="Describe the reason for your leave..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Attachment <span class="text-muted">(optional)</span></label>
                <input type="file" name="file_attachment" class="form-control">
                <small class="text-muted">Medical certificate, etc. PDF/JPG/PNG — max 5MB</small>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-1"></i>Submit Request
                </button>
                <a href="<?= hasRole('employee')?'my_leave.php':'list.php' ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function updateEndMin() {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate');
    if (start) {
        end.min = start;
        // If current end is before start, reset it
        if (end.value && end.value < start) {
            end.value = start;
        }
    }
}

// Ensure end >= start on form submit (client-side guard)
document.getElementById('leaveForm').addEventListener('submit', function(e) {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    if (start && end && end < start) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Invalid Dates', text: 'End date must be on or after the start date.' });
        document.getElementById('endDate').focus();
    }
});

// Initialize end min from existing start value
updateEndMin();
</script>
<?php include '../../includes/footer.php'; ?>
