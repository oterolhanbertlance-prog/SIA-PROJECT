<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$db = getDB();
$cu = currentUser();

// ── Resolve the employee record for the current user ──────────────
$emp = null;
if (!empty($cu['emp_id'])) {
    $stmt = $db->prepare(
        "SELECT e.*, d.name AS dept_name, p.title AS pos_title
         FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         LEFT JOIN positions  p ON e.position_id   = p.id
         WHERE e.id = ? LIMIT 1"
    );
    $stmt->execute([$cu['emp_id']]);
    $emp = $stmt->fetch();
}

// ── Fetch the user row (for username) ─────────────────────────────
$userRow = null;
$uStmt   = $db->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$cu['id']]);
$userRow = $uStmt->fetch();

// ── Handle POST ───────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Update personal info (employees only) ─────────────────
        if ($action === 'update_info' && $emp) {
            $phone    = sanitize($_POST['phone']    ?? '');
            $address  = sanitize($_POST['address']  ?? '');
            $city     = sanitize($_POST['city']     ?? '');
            $province = sanitize($_POST['province'] ?? '');
            $postal   = sanitize($_POST['postal_code'] ?? '');
            $ecName   = sanitize($_POST['emergency_contact_name']     ?? '');
            $ecPhone  = sanitize($_POST['emergency_contact_phone']    ?? '');
            $ecRel    = sanitize($_POST['emergency_contact_relation'] ?? '');
            // Government credentials (employee self-service)
            $sssNo      = sanitize($_POST['sss_no']        ?? '');
            $philhealthNo = sanitize($_POST['philhealth_no'] ?? '');
            $pagibigNo  = sanitize($_POST['pagibig_no']    ?? '');
            $tinNo      = sanitize($_POST['tin_no']        ?? '');

            // Profile picture upload
            $profilePic = $emp['profile_picture'];
            if (!empty($_FILES['profile_picture']['name'])) {
                $uploaded = uploadFile(
                    $_FILES['profile_picture'],
                    'profiles',
                    ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
                );
                if ($uploaded) {
                    $profilePic = basename($uploaded);
                } else {
                    $errors[] = 'Profile picture upload failed. Allowed: JPG, PNG, GIF, WEBP (max 5 MB).';
                }
            }

            if (empty($errors)) {
                $db->prepare(
                    "UPDATE employees
                     SET phone=?, address=?, city=?, province=?, postal_code=?,
                         emergency_contact_name=?, emergency_contact_phone=?,
                         emergency_contact_relation=?, profile_picture=?,
                         sss_no=?, philhealth_no=?, pagibig_no=?, tin_no=?
                     WHERE id=?"
                )->execute([
                    $phone, $address, $city, $province, $postal,
                    $ecName, $ecPhone, $ecRel, $profilePic,
                    $sssNo, $philhealthNo, $pagibigNo, $tinNo,
                    $emp['id']
                ]);
                auditLog('UPDATE', 'Employees', "Employee {$emp['firstname']} {$emp['lastname']} updated their profile");
                header('Location: edit_profile.php?success=Profile+updated+successfully');
                exit;
            }

            // Re-apply posted values so the form is sticky on error
            $emp['phone']                       = $phone;
            $emp['address']                     = $address;
            $emp['city']                        = $city;
            $emp['province']                    = $province;
            $emp['postal_code']                 = $postal;
            $emp['emergency_contact_name']      = $ecName;
            $emp['emergency_contact_phone']     = $ecPhone;
            $emp['emergency_contact_relation']  = $ecRel;
            $emp['sss_no']                      = $sssNo;
            $emp['philhealth_no']               = $philhealthNo;
            $emp['pagibig_no']                  = $pagibigNo;
            $emp['tin_no']                      = $tinNo;
        }

        // ── Update username (any logged-in user) ──────────────────
        if ($action === 'update_username') {
            $newUsername = sanitize($_POST['username'] ?? '');

            if (!$newUsername) {
                $errors[] = 'Username cannot be blank.';
            } elseif (!preg_match('/^[a-zA-Z0-9_\.]{3,50}$/', $newUsername)) {
                $errors[] = 'Username may only contain letters, numbers, underscores, and dots (3-50 characters).';
            } else {
                $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$newUsername, $cu['id']]);
                if ($chk->fetch()) {
                    $errors[] = 'That username is already taken.';
                }
            }

            if (empty($errors)) {
                $db->prepare("UPDATE users SET username=? WHERE id=?")->execute([$newUsername, $cu['id']]);
                $_SESSION['username'] = $newUsername;
                auditLog('UPDATE', 'Users', "User #{$cu['id']} changed username to '$newUsername'");
                header('Location: edit_profile.php?success=Username+updated+successfully');
                exit;
            }
        }
    }
}

$pageTitle = 'Edit My Profile';
include '../../includes/header.php';

$picPath = (!empty($emp['profile_picture']) && $emp['profile_picture'] !== 'default.png')
    ? SITE_URL . 'uploads/profiles/' . $emp['profile_picture']
    : SITE_URL . 'assets/images/default.png';
?>

<!-- ── Page Header ───────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-person-gear me-2 text-primary"></i>Edit My Profile</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
                <?php if ($emp): ?>
                <li class="breadcrumb-item">
                    <a href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $emp['id'] ?>">My Profile</a>
                </li>
                <?php endif; ?>
                <li class="breadcrumb-item active">Edit Profile</li>
            </ol>
        </nav>
    </div>
    <?php if ($emp): ?>
    <a href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $emp['id'] ?>"
       class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
    </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
</div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="row g-4">

    <!-- ── Left: Avatar & quick links ───────────────────────────── -->
    <div class="col-lg-3">
        <div class="card text-center p-4">
            <img src="<?= $picPath ?>"
                 class="rounded-circle mx-auto mb-3 border"
                 style="width:100px;height:100px;object-fit:cover;"
                 onerror="this.src='<?= SITE_URL ?>assets/images/default.png'"
                 id="avatarPreview">
            <h6 class="fw-bold mb-0">
                <?php if ($emp): ?>
                    <?= htmlspecialchars(($emp['firstname'] ?? '') . ' ' . ($emp['lastname'] ?? '')) ?>
                <?php else: ?>
                    <?= htmlspecialchars($cu['name'] ?: $cu['username']) ?>
                <?php endif; ?>
            </h6>
            <?php if ($emp): ?>
            <p class="text-muted small mb-1"><?= htmlspecialchars($emp['pos_title'] ?? '') ?></p>
            <p class="text-muted small mb-1"><?= htmlspecialchars($emp['dept_name'] ?? '') ?></p>
            <?= statusBadge($emp['employment_status'] ?? 'active') ?>
            <?php endif; ?>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($cu['username']) ?>
                &middot; <span class="text-capitalize"><?= htmlspecialchars($cu['role']) ?></span>
            </p>
        </div>

        <div class="card mt-3">
            <div class="card-body p-3">
                <p class="fw-semibold small mb-2">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Quick Links
                </p>
                <div class="d-grid gap-2">
                    <?php if ($emp): ?>
                    <a href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $emp['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-person me-1"></i>View My Profile
                    </a>
                    <?php endif; ?>
                    <a href="<?= SITE_URL ?>modules/users/change_password.php"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-key me-1"></i>Change Password
                    </a>
                    <?php if (hasRole('employee')): ?>
                    <a href="<?= SITE_URL ?>modules/resignation/my_resignation.php"
                       class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Resignation Letter
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Right: Forms ──────────────────────────────────────────── -->
    <div class="col-lg-9">

        <?php if ($emp): ?>
        <!-- ── Personal & Contact Info (employees only) ─────────── -->
        <form method="POST" enctype="multipart/form-data" id="infoForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_info">

            <!-- Profile Picture -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-camera me-2"></i>Profile Picture
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4">
                        <img src="<?= $picPath ?>"
                             class="rounded-circle border"
                             style="width:80px;height:80px;object-fit:cover;"
                             id="picPreviewSmall"
                             onerror="this.src='<?= SITE_URL ?>assets/images/default.png'">
                        <div>
                            <input type="file" name="profile_picture" class="form-control"
                                   accept="image/*" onchange="previewPicture(this)">
                            <small class="text-muted">
                                Leave blank to keep current picture.
                                Allowed: JPG, PNG, GIF, WEBP &mdash; max 5 MB.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-telephone me-2"></i>Contact Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">First Name</label>
                            <input type="text" class="form-control"
                                   value="<?= htmlspecialchars($emp['firstname'] ?? '') ?>" disabled>
                            <div class="form-text">Contact HR to change your name.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Last Name</label>
                            <input type="text" class="form-control"
                                   value="<?= htmlspecialchars($emp['lastname'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email</label>
                            <input type="email" class="form-control"
                                   value="<?= htmlspecialchars($emp['email'] ?? '') ?>" disabled>
                            <div class="form-text">Contact HR to change your email.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($emp['phone'] ?? '') ?>"
                                   placeholder="e.g. 09171234567">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-geo-alt me-2"></i>Address
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Street Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= htmlspecialchars($emp['address'] ?? '') ?>"
                                   placeholder="House No., Street, Barangay">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">City / Municipality</label>
                            <input type="text" name="city" class="form-control"
                                   value="<?= htmlspecialchars($emp['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Province</label>
                            <input type="text" name="province" class="form-control"
                                   value="<?= htmlspecialchars($emp['province'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control"
                                   value="<?= htmlspecialchars($emp['postal_code'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-person-heart me-2"></i>Emergency Contact
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control"
                                   value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>"
                                   placeholder="Full name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control"
                                   value="<?= htmlspecialchars($emp['emergency_contact_phone'] ?? '') ?>"
                                   placeholder="e.g. 09181234567">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Relationship</label>
                            <input type="text" name="emergency_contact_relation" class="form-control"
                                   value="<?= htmlspecialchars($emp['emergency_contact_relation'] ?? '') ?>"
                                   placeholder="e.g. Spouse, Parent">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Government IDs -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-shield-check me-2"></i>Government Credentials
                    <small class="text-muted fw-normal ms-2">— visible to HR and Admin</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">SSS Number</label>
                            <input type="text" name="sss_no" class="form-control"
                                   value="<?= htmlspecialchars($emp['sss_no'] ?? '') ?>"
                                   placeholder="e.g. 34-1234567-8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">PhilHealth Number</label>
                            <input type="text" name="philhealth_no" class="form-control"
                                   value="<?= htmlspecialchars($emp['philhealth_no'] ?? '') ?>"
                                   placeholder="e.g. 12-345678901-2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Pag-IBIG (HDMF) Number</label>
                            <input type="text" name="pagibig_no" class="form-control"
                                   value="<?= htmlspecialchars($emp['pagibig_no'] ?? '') ?>"
                                   placeholder="e.g. 1234-5678-9012">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">TIN Number</label>
                            <input type="text" name="tin_no" class="form-control"
                                   value="<?= htmlspecialchars($emp['tin_no'] ?? '') ?>"
                                   placeholder="e.g. 123-456-789-000">
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Your government ID numbers are stored securely and are only visible to HR and Admin staff.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary px-4" id="infoSaveBtn">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
                <a href="<?= SITE_URL ?>modules/employees/profile.php?id=<?= $emp['id'] ?>"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <!-- ── Account / Username (all users) ───────────────────── -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">
                <i class="bi bi-person-badge me-2"></i>Account Settings
            </div>
            <div class="card-body">
                <form method="POST" id="usernameForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_username">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">
                                Username <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="username" class="form-control"
                                   value="<?= htmlspecialchars($userRow['username'] ?? $cu['username']) ?>"
                                   pattern="[a-zA-Z0-9_\.]{3,50}"
                                   title="3-50 characters: letters, numbers, underscores, dots"
                                   required>
                            <div class="form-text">3-50 characters: letters, numbers, underscores, and dots.</div>
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-primary" id="usernameSaveBtn">
                                <i class="bi bi-save me-1"></i>Update Username
                            </button>
                        </div>
                    </div>
                </form>
                <hr class="my-3">
                <p class="text-muted small mb-0">
                    <i class="bi bi-key me-1"></i>
                    To change your password, go to
                    <a href="<?= SITE_URL ?>modules/users/change_password.php">Change Password</a>.
                </p>
            </div>
        </div>

    </div><!-- /col-lg-9 -->
</div><!-- /row -->

<script>
function previewPicture(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var a = document.getElementById('avatarPreview');
            var s = document.getElementById('picPreviewSmall');
            if (a) a.src = e.target.result;
            if (s) s.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

var infoForm = document.getElementById('infoForm');
if (infoForm) {
    infoForm.addEventListener('submit', function () {
        var btn = document.getElementById('infoSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    });
}

var usernameForm = document.getElementById('usernameForm');
if (usernameForm) {
    usernameForm.addEventListener('submit', function () {
        var btn = document.getElementById('usernameSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
