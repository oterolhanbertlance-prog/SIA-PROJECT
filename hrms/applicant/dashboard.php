<?php
require_once '../config/database.php';
require_once '../config/applicant_session.php';
require_once '../includes/functions.php';
requireApplicantLogin();

$db = getDB();
$applicant = currentApplicant();

// ── Handle profile update ───────────────────────────────────────────
$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $profileMsg = '<div class="alert alert-danger">Invalid form submission. Please try again.</div>';
    } else {
        $newPhone = sanitize($_POST['phone'] ?? '');
        $db->prepare("UPDATE applicant_accounts SET phone = ? WHERE id = ?")->execute([$newPhone, $applicant['id']]);
        $profileMsg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Profile updated successfully.</div>';
        $stmtRefresh = $db->prepare("SELECT * FROM applicant_accounts WHERE id = ?");
        $stmtRefresh->execute([$applicant['id']]);
        $applicant = $stmtRefresh->fetch();
    }
}

// ── Handle document upload ──────────────────────────────────────────
$docMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_doc') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $docMsg = '<div class="alert alert-danger">Invalid form submission. Please try again.</div>';
    } elseif (empty($_FILES['document']['name'])) {
        $docMsg = '<div class="alert alert-danger">Please select a file to upload.</div>';
    } else {
        $docName = sanitize($_POST['document_name'] ?? '');
        if (!$docName) {
            $docMsg = '<div class="alert alert-danger">Please enter a document name.</div>';
        } else {
            $allowedTypes = ['application/pdf','image/jpeg','image/png',
                             'application/msword',
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $path = uploadFile($_FILES['document'], 'applicant_docs', $allowedTypes);
            if ($path) {
                try {
                    $db->prepare(
                        "INSERT INTO applicant_documents (applicant_account_id, document_name, file_path, file_size, created_at)
                         VALUES (?, ?, ?, ?, NOW())"
                    )->execute([$applicant['id'], $docName, basename($path), $_FILES['document']['size']]);
                    $docMsg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Document uploaded successfully.</div>';
                } catch (Exception $e) {
                    $docMsg = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Document saved but could not be recorded. Please contact HR.</div>';
                }
            } else {
                $docMsg = '<div class="alert alert-danger">Upload failed. Allowed: PDF, JPG, PNG, DOC, DOCX (max 5 MB).</div>';
            }
        }
    }
}

// ── Applications ────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT a.*, j.title as job_title, j.job_type
     FROM applicants a LEFT JOIN job_postings j ON a.job_id = j.id
     WHERE a.applicant_account_id = ?
     ORDER BY a.applied_at DESC"
);
$stmt->execute([$applicant['id']]);
$applications = $stmt->fetchAll();

// ── Mark all notifications as read on dashboard open ───────────────
$db->prepare("UPDATE notifications SET is_read = 1 WHERE applicant_account_id = ?")->execute([$applicant['id']]);

// ── Fetch uploaded documents ────────────────────────────────────────
$myDocs = [];
try {
    $dStmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_account_id = ? ORDER BY created_at DESC");
    $dStmt->execute([$applicant['id']]);
    $myDocs = $dStmt->fetchAll();
} catch (Exception $e) { /* table may not exist yet */ }

// ── Hired credentials (columns added by migration_v6) ──────────────
// Fall back gracefully if the columns don't exist yet.
$hiredUsername    = null;
$hiredPassword    = null;
$hiredEmployeeId  = null;
try {
    $credStmt = $db->prepare(
        "SELECT hired_username, hired_temp_password, hired_employee_id
         FROM applicant_accounts WHERE id = ?"
    );
    $credStmt->execute([$applicant['id']]);
    $creds = $credStmt->fetch();
    if ($creds && !empty($creds['hired_username'])) {
        $hiredUsername   = $creds['hired_username'];
        $hiredPassword   = $creds['hired_temp_password'];
        $hiredEmployeeId = $creds['hired_employee_id'];
    }
} catch (Exception $e) { /* migration not yet run */ }

$pageTitle = 'My Applications';
include '../includes/applicant_header.php';
?>

<!-- ── Hired Credentials Banner ──────────────────────────────────── -->
<?php if ($hiredUsername): ?>
<div class="card border-0 mb-4 shadow-sm" style="background:linear-gradient(135deg,#1a7f3c,#28a745);color:#fff;">
    <div class="card-body py-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div style="font-size:2.5rem;line-height:1;">🎉</div>
            <div>
                <h4 class="fw-bold mb-0">Congratulations — You're Hired!</h4>
                <p class="mb-0 opacity-75 small">Your HRMS employee account is ready. Use the credentials below to log in.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="rounded-3 p-3" style="background:rgba(255,255,255,.15);">
                    <div class="small opacity-75 mb-1"><i class="bi bi-person-badge me-1"></i>Employee ID</div>
                    <div class="fw-bold fs-5" id="credEmpId"><?= htmlspecialchars($hiredEmployeeId ?? '—') ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="rounded-3 p-3" style="background:rgba(255,255,255,.15);">
                    <div class="small opacity-75 mb-1"><i class="bi bi-person me-1"></i>Username</div>
                    <div class="fw-bold fs-5" id="credUsername"><?= htmlspecialchars($hiredUsername) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="rounded-3 p-3 position-relative" style="background:rgba(255,255,255,.15);">
                    <div class="small opacity-75 mb-1"><i class="bi bi-key me-1"></i>Password</div>
                    <div class="fw-bold fs-5 d-flex align-items-center gap-2">
                        <span id="credPwDisplay">••••••••</span>
                        <button type="button" class="btn btn-sm p-0 border-0"
                                style="color:#fff;opacity:.8;"
                                onclick="togglePassword()" title="Show/hide password">
                            <i class="bi bi-eye" id="pwEyeIcon"></i>
                        </button>
                    </div>
                    <span id="credPwHidden" style="display:none;"><?= htmlspecialchars($hiredPassword ?? '') ?></span>
                </div>
            </div>

        </div>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-light text-success fw-semibold" onclick="copyCredentials()">
                <i class="bi bi-clipboard me-1"></i>Copy All Credentials
            </button>
            <div class="alert alert-warning d-inline-flex align-items-center py-1 px-3 mb-0 rounded-pill small" style="color:#856404;">
                <i class="bi bi-shield-exclamation me-1"></i>
                This is a <strong class="mx-1">temporary password</strong> — please change it after your first login.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Page Header ───────────────────────────────────────────────── -->
<div class="apply-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-person-lines-fill me-2"></i>Welcome, <?= htmlspecialchars($applicant['firstname']) ?></h2>
        <p class="mb-0 opacity-75">Track the status of every application you've submitted.</p>
    </div>

</div>

<!-- ── My Applications ───────────────────────────────────────────── -->
<?php if (empty($applications)): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <div class="display-4 mb-3"><i class="bi bi-inbox"></i></div>
    <h5>You haven't applied to any jobs yet.</h5>

</div></div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-briefcase me-2 text-primary"></i>My Applications</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Application Code</th>
                    <th>Position</th>
                    <th>Applied On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
            <?php
                $label = applicantStatusLabel($app['id'], $app['status'], $db);
                $badgeMap = [
                    'Pending'             => ['color' => 'warning',   'icon' => 'hourglass-split'],
                    'Interview Scheduled' => ['color' => 'info',      'icon' => 'calendar-event'],
                    'Interviewed'         => ['color' => 'primary',   'icon' => 'chat-square-text'],
                    'Accepted'            => ['color' => 'success',   'icon' => 'check-circle'],
                    'Hired'               => ['color' => 'success',   'icon' => 'person-check-fill'],
                    'Rejected'            => ['color' => 'danger',    'icon' => 'x-circle'],
                    'Withdrawn'           => ['color' => 'secondary', 'icon' => 'dash-circle'],
                ];
                $badge     = $badgeMap[$label] ?? ['color' => 'secondary', 'icon' => 'circle'];
                $isInterview = in_array($label, ['Interview Scheduled', 'Interviewed']);
                $isHired     = $label === 'Hired';
            ?>
            <tr <?= $isHired ? 'class="table-success"' : '' ?>>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($app['applicant_code'] ?? '—') ?></span></td>
                <td><?= htmlspecialchars($app['job_title'] ?? 'General Application') ?></td>
                <td><?= formatDate($app['applied_at']) ?></td>
                <td>
                    <?php if ($isInterview): ?>
                        <span class="badge bg-<?= $badge['color'] ?> interview-status-badge"
                              onclick="showInterviewNotice()" title="Click for details" style="cursor:pointer;">
                            <i class="bi bi-<?= $badge['icon'] ?> me-1"></i><?= htmlspecialchars($label) ?>
                            <i class="bi bi-info-circle ms-1"></i>
                        </span>
                    <?php elseif ($isHired): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-person-check-fill me-1"></i>Hired
                        </span>
                        <?php if ($hiredUsername): ?>
                        <button type="button" class="btn btn-xs btn-sm btn-outline-success py-0 px-2 ms-1"
                                onclick="scrollToCreds()" title="View your login credentials">
                            <i class="bi bi-key me-1"></i>View Credentials
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-<?= $badge['color'] ?>">
                            <i class="bi bi-<?= $badge['icon'] ?> me-1"></i><?= htmlspecialchars($label) ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── My Documents Upload ───────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-file-earmark-arrow-up me-2 text-success"></i>My Documents &amp; Requirements</div>
    <div class="card-body">
        <?= $docMsg ?>
        <form method="POST" enctype="multipart/form-data" id="docUploadForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_doc">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Document Name <span class="text-danger">*</span></label>
                    <input type="text" name="document_name" class="form-control form-control-sm"
                           placeholder="e.g. NBI Clearance, Resume, SSS ID" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">File <span class="text-danger">*</span></label>
                    <input type="file" name="document" class="form-control form-control-sm"
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" id="docUploadBtn" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Accepted: PDF, JPG, PNG, DOC, DOCX — max 5 MB</p>
        </form>

        <?php if (!empty($myDocs)): ?>
        <hr>
        <table class="table table-sm table-hover mt-2">
            <thead><tr><th>Document</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($myDocs as $doc): ?>
            <tr>
                <td><?= htmlspecialchars($doc['document_name']) ?></td>
                <td><?= formatDate($doc['created_at']) ?></td>
                <td>
                    <a href="<?= SITE_URL ?>uploads/applicant_docs/<?= htmlspecialchars($doc['file_path']) ?>"
                       target="_blank" class="btn btn-xs btn-sm btn-outline-primary py-0 px-2">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Profile Settings ──────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-person-gear me-2 text-secondary"></i>My Profile</div>
    <div class="card-body">
        <?= $profileMsg ?>
        <form method="POST" id="profileForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">First Name</label>
                    <input type="text" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($applicant['firstname']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Last Name</label>
                    <input type="text" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($applicant['lastname']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email</label>
                    <input type="email" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($applicant['email']) ?>" disabled>
                    <div class="form-text">Contact HR to change your email address.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="phone" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($applicant['phone'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" id="profileSaveBtn" class="btn btn-primary btn-sm mt-3">
                <i class="bi bi-save me-1"></i>Save Changes
            </button>
        </form>
    </div>
</div>

<script>
// ── Interview SweetAlert ────────────────────────────────────────────
function showInterviewNotice() {
    Swal.fire({
        icon: 'info',
        title: 'Interview Status',
        text: 'Please wait for the HR department to notify you if you have passed your interview.',
        confirmButtonColor: '#4361ee',
        confirmButtonText: 'Got it'
    });
}

// ── Scroll to hired credentials banner ─────────────────────────────
function scrollToCreds() {
    const banner = document.querySelector('.card.border-0.shadow-sm');
    if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Show / hide temp password ───────────────────────────────────────
function togglePassword() {
    const display = document.getElementById('credPwDisplay');
    const hidden  = document.getElementById('credPwHidden');
    const icon    = document.getElementById('pwEyeIcon');
    if (!display || !hidden) return;
    if (display.textContent === '••••••••') {
        display.textContent = hidden.textContent;
        icon.className = 'bi bi-eye-slash';
    } else {
        display.textContent = '••••••••';
        icon.className = 'bi bi-eye';
    }
}

// ── Copy credentials to clipboard ──────────────────────────────────
function copyCredentials() {
    const empId    = document.getElementById('credEmpId')?.textContent.trim()    ?? '';
    const username = document.getElementById('credUsername')?.textContent.trim() ?? '';
    const password = document.getElementById('credPwHidden')?.textContent.trim() ?? '';
    const text =
        'HRMS Employee Login Credentials\n' +
        '--------------------------------\n' +
        'Employee ID : ' + empId    + '\n' +
        'Username    : ' + username + '\n' +
        'Password    : ' + password + '\n\n' +
        '⚠ This is a temporary password. Please change it after your first login.';

    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Credentials copied to clipboard!',
            showConfirmButton: false, timer: 2500, timerProgressBar: true
        });
    }).catch(() => {
        Swal.fire({ icon: 'info', title: 'Copy manually', html:
            '<pre class="text-start small bg-light p-3 rounded">' + text.replace(/</g,'&lt;') + '</pre>'
        });
    });
}

<?php if ($hiredUsername): ?>
// Show a one-time SweetAlert on first view of the hired credentials.
if (!sessionStorage.getItem('hiredBannerShown')) {
    sessionStorage.setItem('hiredBannerShown', '1');
    Swal.fire({
        icon: 'success',
        title: '🎉 You\'re Hired!',
        html:
            '<p>Your HRMS employee account has been created.</p>' +
            '<table class="table table-sm text-start mt-2">' +
            '<tr><th>Employee ID</th><td><strong><?= htmlspecialchars($hiredEmployeeId ?? '—') ?></strong></td></tr>' +
            '<tr><th>Username</th><td><strong><?= htmlspecialchars($hiredUsername) ?></strong></td></tr>' +
            '<tr><th>Password</th><td><strong><?= htmlspecialchars($hiredPassword ?? '') ?></strong></td></tr>' +
            '</table>' +
            '<p class="text-muted small mb-0">⚠ Change this password after your first login.</p>',
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Got It!'
    });
}
<?php endif; ?>

// ── Disable buttons on submit ───────────────────────────────────────
document.getElementById('docUploadForm').addEventListener('submit', function () {
    const btn = document.getElementById('docUploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Uploading…';
});

document.getElementById('profileForm').addEventListener('submit', function () {
    const btn = document.getElementById('profileSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';
});
</script>

<?php include '../includes/applicant_footer.php'; ?>
