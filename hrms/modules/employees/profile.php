<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$cu = currentUser();

$stmt = $db->prepare(
    "SELECT e.*, d.name as dept_name, p.title as pos_title
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     LEFT JOIN positions p ON e.position_id=p.id
     WHERE e.id=?"
);
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) { header('Location: list.php?error=Employee+not+found'); exit; }

// Access control: employees can only see their own profile
if (hasRole('employee') && (int)$cu['emp_id'] !== $id) {
    header('Location: ' . SITE_URL . 'dashboard.php?error=unauthorized'); exit;
}

// ── Handle employee self-service profile update ────────────
$updateMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_self') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $updateMsg = '<div class="alert alert-danger">Invalid form submission. Please try again.</div>';
    } elseif (!hasRole('employee') || (int)$cu['emp_id'] !== $id) {
        $updateMsg = '<div class="alert alert-danger">Unauthorized.</div>';
    } else {
        $newUsername = sanitize($_POST['username'] ?? '');
        $errors = [];

        if (!$newUsername) {
            $errors[] = 'Username cannot be blank.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\.]{3,50}$/', $newUsername)) {
            $errors[] = 'Username may only contain letters, numbers, underscores, and dots (3–50 characters).';
        } else {
            // Check uniqueness (excluding the current user).
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$newUsername, $cu['id']]);
            if ($chk->fetch()) $errors[] = 'That username is already taken.';
        }

        if (empty($errors)) {
            $db->prepare("UPDATE users SET username=? WHERE id=?")->execute([$newUsername, $cu['id']]);
            $_SESSION['username'] = $newUsername;
            auditLog('UPDATE', 'Employees', "Employee {$emp['firstname']} {$emp['lastname']} changed their username to '$newUsername'");
            $updateMsg = '<div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i>Username updated successfully.</div>';
        } else {
            $updateMsg = '<div class="alert alert-danger">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
        }
    }
}

$pageTitle = htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']);

// Get leave balances
$leaveBalances = $db->prepare(
    "SELECT lb.*, lt.name FROM leave_balance lb
     JOIN leave_types lt ON lb.leave_type_id=lt.id
     WHERE lb.employee_id=? AND lb.year=?"
);
$leaveBalances->execute([$id, date('Y')]);
$leaveBalances = $leaveBalances->fetchAll();

// Get documents
$docs = $db->prepare("SELECT * FROM documents WHERE employee_id=? ORDER BY created_at DESC");
$docs->execute([$id]);
$docs = $docs->fetchAll();

// Get recent payslips
$payslips = $db->prepare(
    "SELECT * FROM payroll WHERE employee_id=? ORDER BY payroll_year DESC, payroll_month DESC LIMIT 6"
);
$payslips->execute([$id]);
$payslips = $payslips->fetchAll();

// Get recent leaves
$leaves = $db->prepare(
    "SELECT lr.*, lt.name as type_name
     FROM leave_requests lr
     JOIN leave_types lt ON lr.leave_type_id=lt.id
     WHERE lr.employee_id=? ORDER BY lr.created_at DESC LIMIT 5"
);
$leaves->execute([$id]);
$leaves = $leaves->fetchAll();

// Fetch the user record for this employee (for username display/edit).
$userRow = null;
if ($cu['emp_id'] == $id || hasRole('admin', 'hr')) {
    $uStmt = $db->prepare("SELECT id, username FROM users WHERE employee_id=? LIMIT 1");
    $uStmt->execute([$id]);
    $userRow = $uStmt->fetch();
}

include '../../includes/header.php';

$picPath = $emp['profile_picture'] && $emp['profile_picture'] !== 'default.png'
    ? SITE_URL . 'uploads/profiles/' . $emp['profile_picture']
    : SITE_URL . 'assets/images/default.png';
?>

<?= $updateMsg ?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-person-circle me-2 text-primary"></i>Employee Profile</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
                <?php if (hasRole('admin','hr')): ?>
                <li class="breadcrumb-item"><a href="list.php">Employees</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($emp['firstname'].' '.$emp['lastname']) ?></li>
            </ol>
        </nav>
    </div>
    <?php if (hasRole('admin','hr')): ?>
    <div class="d-flex gap-2">
        <a href="edit.php?id=<?= $id ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Left Column: Profile Card -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="profile-cover"></div>
            <div class="profile-avatar-wrapper mb-3">
                <img src="<?= $picPath ?>" class="avatar avatar-xl rounded-circle" style="object-fit:cover;"
                     onerror="this.src='<?= SITE_URL ?>assets/images/default.png'">
            </div>
            <div class="card-body pt-0">
                <h5 class="fw-bold"><?= htmlspecialchars($emp['firstname'].' '.$emp['lastname']) ?></h5>
                <p class="text-muted mb-1"><?= htmlspecialchars($emp['pos_title'] ?? '—') ?></p>
                <p class="mb-2"><?= statusBadge($emp['employment_status']) ?></p>
                <div class="small text-muted mb-1"><i class="bi bi-building me-2"></i><?= htmlspecialchars($emp['dept_name'] ?? '—') ?></div>
                <div class="small text-muted mb-1"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($emp['email']) ?></div>
                <div class="small text-muted mb-1"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($emp['phone'] ?? '—') ?></div>
                <div class="small text-muted mb-3"><i class="bi bi-calendar3 me-2"></i>Hired: <?= formatDate($emp['date_hired']) ?></div>
                <hr>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Employee ID</span>
                    <span class="fw-semibold badge bg-primary"><?= htmlspecialchars($emp['employee_id']) ?></span>
                </div>
                <?php if (hasRole('admin','hr')): ?>
                <div class="d-flex justify-content-between small mt-2">
                    <span class="text-muted">Basic Salary</span>
                    <span class="fw-semibold"><?= money($emp['basic_salary']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave Balances -->
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-calendar-range me-2"></i>Leave Balance <?= date('Y') ?></div>
            <div class="card-body">
                <?php foreach ($leaveBalances as $lb): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small><?= htmlspecialchars($lb['name']) ?></small>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="progress" style="width:80px;height:6px;">
                            <div class="progress-bar bg-primary"
                                 style="width:<?= $lb['total_days'] > 0 ? ($lb['remaining_days']/$lb['total_days']*100) : 0 ?>%"></div>
                        </div>
                        <small class="fw-semibold"><?= $lb['remaining_days'] ?>/<?= $lb['total_days'] ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($leaveBalances)): ?>
                <p class="text-muted small mb-0">No leave balance found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gov't Numbers -->
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-shield-check me-2"></i>Government IDs</div>
            <div class="card-body">
                <div class="small mb-2"><span class="text-muted">SSS:</span> <span class="fw-semibold"><?= htmlspecialchars($emp['sss_no']??'—') ?></span></div>
                <div class="small mb-2"><span class="text-muted">PhilHealth:</span> <span class="fw-semibold"><?= htmlspecialchars($emp['philhealth_no']??'—') ?></span></div>
                <div class="small mb-2"><span class="text-muted">Pag-IBIG:</span> <span class="fw-semibold"><?= htmlspecialchars($emp['pagibig_no']??'—') ?></span></div>
                <div class="small"><span class="text-muted">TIN:</span> <span class="fw-semibold"><?= htmlspecialchars($emp['tin_no']??'—') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Right Column: Tabs -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs border-0" id="profileTabs">
                    <li class="nav-item"><a class="nav-link active px-4 py-3" data-bs-toggle="tab" href="#personal">Personal</a></li>
                    <li class="nav-item"><a class="nav-link px-4 py-3" data-bs-toggle="tab" href="#education">Education</a></li>
                    <li class="nav-item"><a class="nav-link px-4 py-3" data-bs-toggle="tab" href="#payslips">Payslips</a></li>
                    <li class="nav-item"><a class="nav-link px-4 py-3" data-bs-toggle="tab" href="#leaves">Leaves</a></li>
                    <!-- Documents tab: visible to admin/hr AND the employee themselves -->
                    <li class="nav-item"><a class="nav-link px-4 py-3" data-bs-toggle="tab" href="#documents">Documents</a></li>
                    <?php if (hasRole('employee') && $cu['emp_id'] == $id): ?>
                    <li class="nav-item"><a class="nav-link px-4 py-3" data-bs-toggle="tab" href="#settings">Settings</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-body tab-content">
                <!-- Personal Tab -->
                <div class="tab-pane fade show active" id="personal">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="text-muted small">Gender</label><p class="fw-semibold"><?= htmlspecialchars($emp['gender']??'—') ?></p></div>
                        <div class="col-md-6"><label class="text-muted small">Birthdate</label><p class="fw-semibold"><?= formatDate($emp['birthdate']) ?></p></div>
                        <div class="col-md-6"><label class="text-muted small">Civil Status</label><p class="fw-semibold"><?= htmlspecialchars($emp['civil_status']??'—') ?></p></div>
                        <div class="col-md-6"><label class="text-muted small">Nationality</label><p class="fw-semibold"><?= htmlspecialchars($emp['nationality']??'—') ?></p></div>
                        <div class="col-12"><hr class="my-1"><h6 class="fw-semibold mt-2">Address</h6></div>
                        <div class="col-12"><p class="mb-1"><?= htmlspecialchars($emp['address']??'—') ?>, <?= htmlspecialchars($emp['city']??'') ?>, <?= htmlspecialchars($emp['province']??'') ?> <?= htmlspecialchars($emp['postal_code']??'') ?></p></div>
                        <div class="col-12"><hr class="my-1"><h6 class="fw-semibold mt-2">Emergency Contact</h6></div>
                        <div class="col-md-4"><label class="text-muted small">Name</label><p class="fw-semibold"><?= htmlspecialchars($emp['emergency_contact_name']??'—') ?></p></div>
                        <div class="col-md-4"><label class="text-muted small">Phone</label><p class="fw-semibold"><?= htmlspecialchars($emp['emergency_contact_phone']??'—') ?></p></div>
                        <div class="col-md-4"><label class="text-muted small">Relationship</label><p class="fw-semibold"><?= htmlspecialchars($emp['emergency_contact_relation']??'—') ?></p></div>
                    </div>
                </div>

                <!-- Education Tab -->
                <div class="tab-pane fade" id="education">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="text-muted small">Highest Education</label><p class="fw-semibold"><?= htmlspecialchars($emp['highest_education']??'—') ?></p></div>
                        <div class="col-md-4"><label class="text-muted small">School</label><p class="fw-semibold"><?= htmlspecialchars($emp['school']??'—') ?></p></div>
                        <div class="col-md-4"><label class="text-muted small">Course</label><p class="fw-semibold"><?= htmlspecialchars($emp['course']??'—') ?></p></div>
                        <div class="col-12"><label class="text-muted small">Work Experience</label><p><?= nl2br(htmlspecialchars($emp['work_experience']??'—')) ?></p></div>
                        <div class="col-12"><label class="text-muted small">Skills</label><p><?= nl2br(htmlspecialchars($emp['skills']??'—')) ?></p></div>
                    </div>
                </div>

                <!-- Payslips Tab -->
                <div class="tab-pane fade" id="payslips">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Month/Year</th><th>Basic Salary</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($payslips as $ps): ?>
                        <tr>
                            <td><?= monthName($ps['payroll_month']) ?> <?= $ps['payroll_year'] ?></td>
                            <td><?= money($ps['basic_salary']) ?></td>
                            <td><?= money($ps['gross_pay']) ?></td>
                            <td class="text-danger"><?= money($ps['total_deductions']) ?></td>
                            <td class="fw-semibold text-success"><?= money($ps['net_pay']) ?></td>
                            <td><?= statusBadge($ps['status']) ?></td>
                            <td><a href="../payroll/payslip.php?id=<?= $ps['id'] ?>" class="btn btn-xs btn-sm btn-outline-primary py-0 px-2">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payslips)): ?><tr><td colspan="7" class="text-center text-muted">No payslips found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Leaves Tab -->
                <div class="tab-pane fade" id="leaves">
                    <div class="mb-2">
                        <a href="../leave/add.php?emp_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>File Leave</a>
                    </div>
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($leaves as $lr): ?>
                        <tr><td><?= htmlspecialchars($lr['type_name']) ?></td><td><?= formatDate($lr['start_date']) ?></td><td><?= formatDate($lr['end_date']) ?></td><td><?= $lr['total_days'] ?></td><td><?= statusBadge($lr['status']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($leaves)): ?><tr><td colspan="5" class="text-center text-muted">No leave requests.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Documents Tab (admin/hr + employee self) -->
                <div class="tab-pane fade" id="documents">
                    <?php
                    // Show upload form for admin/hr OR for the employee viewing their own profile.
                    $canUpload = hasRole('admin','hr') || (hasRole('employee') && (int)$cu['emp_id'] === $id);
                    ?>
                    <?php if ($canUpload): ?>
                    <form method="POST" enctype="multipart/form-data" action="upload_doc.php" class="mb-3" id="docUploadForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="employee_id" value="<?= $id ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Document Name <span class="text-danger">*</span></label>
                                <input type="text" name="document_name" class="form-control form-control-sm"
                                       placeholder="e.g. NBI Clearance, Resume" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">File <span class="text-danger">*</span></label>
                                <input type="file" name="document" class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            </div>
                            <div class="col-auto">
                                <button type="submit" id="docUploadBtn" class="btn btn-sm btn-primary">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </button>
                            </div>
                        </div>
                        <p class="text-muted small mt-1 mb-0"><i class="bi bi-info-circle me-1"></i>Accepted: PDF, JPG, PNG, DOC, DOCX — max 5 MB</p>
                    </form>
                    <?php endif; ?>
                    <table class="table table-sm">
                        <thead><tr><th>Document</th><th>Uploaded</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($docs as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['document_name']) ?></td>
                            <td><?= formatDate($doc['created_at']) ?></td>
                            <td><a href="<?= SITE_URL ?>uploads/documents/<?= htmlspecialchars($doc['file_path']) ?>"
                                   target="_blank" class="btn btn-xs btn-sm btn-outline-primary py-0 px-2">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($docs)): ?><tr><td colspan="3" class="text-center text-muted py-3">No documents uploaded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (hasRole('employee') && (int)$cu['emp_id'] === $id): ?>
                <!-- Settings Tab — employee self-service -->
                <div class="tab-pane fade" id="settings">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-person-gear me-2 text-primary"></i>Account Settings</h6>
                    <form method="POST" id="selfUpdateForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_self">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control"
                                       value="<?= htmlspecialchars($userRow['username'] ?? $cu['username']) ?>"
                                       pattern="[a-zA-Z0-9_\.]{3,50}"
                                       title="3–50 characters: letters, numbers, underscores, dots"
                                       required>
                                <div class="form-text">3–50 characters: letters, numbers, underscores, dots.</div>
                            </div>
                        </div>
                        <button type="submit" id="selfSaveBtn" class="btn btn-primary btn-sm mt-3">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </form>
                    <hr>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-key me-1"></i>
                        To change your password, use
                        <a href="<?= SITE_URL ?>modules/users/change_password.php">Change Password</a>.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Auto-open the documents tab if redirected there.
$openTab = '';
if (strpos($_SERVER['REQUEST_URI'] ?? '', '#documents') !== false || isset($_GET['success']) || isset($_GET['error'])) {
    if (!empty($docs) || isset($_GET['success'])) $openTab = 'documents';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Re-open the active tab from URL hash after page load (e.g. after upload redirect).
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`[href="${hash}"]`);
        if (tab) new bootstrap.Tab(tab).show();
    }

    <?php if (isset($_GET['success'])): ?>
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: <?= json_encode(htmlspecialchars($_GET['success'])) ?>,
        showConfirmButton: false, timer: 3500, timerProgressBar: true
    });
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    Swal.fire({
        toast: true, position: 'top-end', icon: 'error',
        title: <?= json_encode(htmlspecialchars($_GET['error'])) ?>,
        showConfirmButton: false, timer: 4000, timerProgressBar: true
    });
    <?php endif; ?>
});

// Disable upload button on submit to prevent double-uploads.
const docForm = document.getElementById('docUploadForm');
if (docForm) {
    docForm.addEventListener('submit', function () {
        const btn = document.getElementById('docUploadBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Uploading…';
    });
}

// Disable save button on submit.
const selfForm = document.getElementById('selfUpdateForm');
if (selfForm) {
    selfForm.addEventListener('submit', function () {
        const btn = document.getElementById('selfSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
