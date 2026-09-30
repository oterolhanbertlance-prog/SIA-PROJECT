<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('employee');

$pageTitle = 'My Resignation';
$db  = getDB();
$cu  = currentUser();
$empId = $cu['emp_id'];

$errors  = [];
$success = '';

// Fetch my existing resignation (if any)
$stmt = $db->prepare("SELECT * FROM resignation_letters WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$empId]);
$existing = $stmt->fetch();

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } elseif ($existing && in_array($existing['status'], ['pending','admin_approved','approved'])) {
        $errors[] = 'You already have a resignation letter on file.';
    } else {
        $lastDay = sanitize($_POST['last_working_day'] ?? '');
        $reason  = sanitize($_POST['reason'] ?? '');
        $letter  = sanitize($_POST['letter_body'] ?? '');

        if (!$lastDay) $errors[] = 'Last working day is required.';
        if ($lastDay && $lastDay <= date('Y-m-d')) $errors[] = 'Last working day must be a future date.';
        if (!$reason)  $errors[] = 'Reason is required.';
        if (!$letter)  $errors[] = 'Resignation letter body is required.';

        if (empty($errors)) {
            $db->prepare(
                "INSERT INTO resignation_letters
                 (employee_id, last_working_day, reason, letter_body, status, created_at)
                 VALUES (?, ?, ?, ?, 'pending', NOW())"
            )->execute([$empId, $lastDay, $reason, $letter]);

            // Notify admin/hr
            $db->prepare(
                "INSERT INTO notifications (user_id, title, message, type, created_at)
                 SELECT u.id,
                        'New Resignation Letter',
                        CONCAT((SELECT CONCAT(firstname,' ',lastname) FROM employees WHERE id=?), ' has submitted a resignation letter. Last working day: ', ?),
                        'warning', NOW()
                 FROM users u WHERE u.role IN ('admin','hr') AND u.status = 'active'"
            )->execute([$empId, $lastDay]);

            auditLog('CREATE', 'Resignation', "Employee ID $empId submitted a resignation letter.");
            $success = 'Your resignation letter has been submitted and is awaiting review by HR.';

            // Refresh
            $stmt = $db->prepare("SELECT * FROM resignation_letters WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$empId]);
            $existing = $stmt->fetch();
        }
    }
}

// My employee info
$empInfo = $db->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title FROM employees e LEFT JOIN departments d ON e.department_id=d.id LEFT JOIN positions p ON e.position_id=p.id WHERE e.id=?");
$empInfo->execute([$empId]);
$empInfo = $empInfo->fetch();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-box-arrow-right me-2 text-danger"></i>My Resignation</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Resignation</li>
        </ol></nav>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if ($existing): ?>
<!-- Show existing resignation -->
<div class="card mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-text me-2"></i>Resignation Letter — <?= formatDate($existing['created_at']) ?></span>
        <?= statusBadge($existing['status']) ?>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="text-muted small">Last Working Day</div>
                <div class="fw-semibold"><?= formatDate($existing['last_working_day']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Reason</div>
                <div class="fw-semibold"><?= htmlspecialchars($existing['reason']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Submitted</div>
                <div class="fw-semibold"><?= formatDateTime($existing['created_at']) ?></div>
            </div>
        </div>
        <div class="border rounded p-3 bg-light" style="white-space:pre-wrap; font-family:serif; font-size:.95rem;">
            <?= nl2br(htmlspecialchars($existing['letter_body'])) ?>
        </div>
        <?php if ($existing['status'] === 'approved'): ?>
        <div class="alert alert-success mt-3 mb-0">
            <i class="bi bi-check-circle me-2"></i>Your resignation has been <strong>accepted</strong> by HR/Admin.
            <?php if ($existing['hr_remarks']): ?>
            <br><em>Remarks: <?= htmlspecialchars($existing['hr_remarks']) ?></em>
            <?php endif; ?>
        </div>
        <?php elseif ($existing['status'] === 'rejected'): ?>
        <div class="alert alert-danger mt-3 mb-0">
            <i class="bi bi-x-circle me-2"></i>Your resignation was <strong>rejected</strong>.
            <?php if ($existing['hr_remarks']): ?>
            <br><em>Remarks: <?= htmlspecialchars($existing['hr_remarks']) ?></em>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mt-3 mb-0">
            <i class="bi bi-hourglass-split me-2"></i>Your resignation letter is <strong>under review</strong> by HR.
        </div>
        <?php endif; ?>

        <?php if ($existing['status'] === 'rejected'): ?>
        <!-- Allow resubmission after rejection -->
        <div class="mt-3">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('newResignationForm').style.display='block';this.style.display='none';">
                <i class="bi bi-pencil me-1"></i>Submit New Resignation Letter
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- New resignation form (only if no pending/approved) -->
<?php if (!$existing || $existing['status'] === 'rejected'): ?>
<div id="newResignationForm" <?= ($existing && $existing['status'] === 'rejected') ? 'style="display:none;"' : '' ?>>
<div class="card" style="max-width:750px;">
    <div class="card-header fw-semibold"><i class="bi bi-pencil-square me-2"></i>Submit Resignation Letter</div>
    <div class="card-body">
        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Please review our company policy before submitting. A resignation letter is a formal document and cannot be undone easily.
            Standard notice period is <strong>30 days</strong> from submission.
        </div>
        <form method="POST" data-validate>
            <?= csrfField() ?>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Last Working Day <span class="text-danger">*</span></label>
                    <input type="date" name="last_working_day" class="form-control" required
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                           value="<?= htmlspecialchars($_POST['last_working_day'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                    <small class="text-muted">Must be at least 30 days from today (company policy).</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Primary Reason <span class="text-danger">*</span></label>
                    <select name="reason" class="form-select" required>
                        <option value="">Select reason</option>
                        <option value="Personal reasons">Personal reasons</option>
                        <option value="Career advancement">Career advancement</option>
                        <option value="Relocation">Relocation</option>
                        <option value="Health reasons">Health reasons</option>
                        <option value="Family reasons">Family reasons</option>
                        <option value="Returning to school">Returning to school</option>
                        <option value="Better opportunity">Better opportunity</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Resignation Letter <span class="text-danger">*</span></label>
                <textarea name="letter_body" class="form-control" rows="12" required
                          placeholder="Dear [Manager/HR],&#10;&#10;I am writing to formally notify you of my resignation from my position as <?= htmlspecialchars($empInfo['pos_title'] ?? 'Employee') ?> at <?= SITE_NAME ?>...&#10;&#10;My last working day will be [date].&#10;&#10;Thank you for the opportunities...&#10;&#10;Sincerely,&#10;<?= htmlspecialchars($empInfo['firstname'].' '.$empInfo['lastname']) ?>"><?= htmlspecialchars($_POST['letter_body'] ?? '') ?></textarea>
                <small class="text-muted">Write a professional resignation letter. Include your last working day, reasons, and appreciation.</small>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-send me-1"></i>Submit Resignation Letter
                </button>
                <a href="<?=SITE_URL?>dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
