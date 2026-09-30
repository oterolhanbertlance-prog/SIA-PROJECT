<?php
/**
 * Public Careers Page — no login required.
 * Browse Milk Tea POS job postings → click a job → fill in details → submit.
 *
 * This page belongs to HRMS only. It intentionally uses config/database.php
 * and never loads config/db_connect.php, which is reserved for the optional
 * Milk Tea POS integration endpoints.
 */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'config/pos_branch_sync.php';

// Session only needed for CSRF token
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db        = getDB();
$publicJobTitles = ['POS Super Admin', 'Cashier', 'Inventory Staff'];
$publicJobTitleSql = "'POS Super Admin','Cashier','Inventory Staff'";

/* ── Self-healing schema: postings/applicants can be tied to a branch;
       branches table may not exist yet on older installs ───────────── */
$db->exec("CREATE TABLE IF NOT EXISTS branches (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    branch_code  VARCHAR(30)  NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    city         VARCHAR(100) DEFAULT NULL,
    latitude     DECIMAL(10,7) DEFAULT NULL,
    longitude    DECIMAL(10,7) DEFAULT NULL,
    phone        VARCHAR(30)  DEFAULT NULL,
    manager_id   INT DEFAULT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("ALTER TABLE job_postings ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER department_id");
$db->exec("ALTER TABLE applicants   ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER job_id");
syncBranchesFromPos($db);
$db->exec("CREATE TABLE IF NOT EXISTS job_posting_branches (job_posting_id INT NOT NULL, branch_id INT NOT NULL, PRIMARY KEY(job_posting_id,branch_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("INSERT IGNORE INTO job_posting_branches(job_posting_id,branch_id) SELECT id,branch_id FROM job_postings WHERE branch_id IS NOT NULL");

$activeBranches = $db->query("SELECT id, name, city FROM branches WHERE status='active' ORDER BY name")->fetchAll();

$viewJobId = (int)($_GET['job_id'] ?? 0);
$viewBranchId = (int)($_GET['branch_id'] ?? 0);
$errors    = [];
$success   = '';
$appCode   = '';
$appEmail  = '';
$selectedJob = null;

// ── Open job postings ───────────────────────────────────────────────
// Keep this list identical to Recruitment → Job Postings. Legacy/demo rows
// (for example, 'Cashier — Cavite Branch') must never appear here unless HR
// can actually manage that same row from the Job Postings module.
$jobs = $db->query(
    "SELECT j.*, d.name AS dept_name, p.title AS position_title,
            b.name AS branch_name, b.city AS branch_city,
            pm.pos_position_label
     FROM job_postings j
     LEFT JOIN pos_job_postings_meta pm ON pm.job_posting_id = j.id
     LEFT JOIN departments d ON j.department_id = d.id
     LEFT JOIN positions p ON j.position_id = p.id
     LEFT JOIN branches b ON j.branch_id = b.id
     WHERE j.status = 'open'
       AND j.title IN ($publicJobTitleSql)
       AND (j.deadline IS NULL OR j.deadline >= CURDATE())
     ORDER BY j.created_at DESC"
)->fetchAll();

// Publish one card per branch selected by HR for each job.
$jobCards=[];
$mapStmt=$db->prepare("SELECT b.id,b.name,b.city FROM job_posting_branches jpb JOIN branches b ON b.id=jpb.branch_id WHERE jpb.job_posting_id=? AND b.status='active' ORDER BY b.name");
foreach($jobs as $j){
    $mapStmt->execute([$j['id']]); $mapped=$mapStmt->fetchAll();
    if(!$mapped && !empty($j['branch_id'])){ foreach($activeBranches as $b){if((int)$b['id']===(int)$j['branch_id']){$mapped=[$b];break;}} }
    foreach($mapped as $b){$c=$j;$c['card_branch_id']=(int)$b['id'];$c['branch_name']=$b['name'];$c['branch_city']=$b['city'];$jobCards[]=$c;}
}

// ── Load selected job details ───────────────────────────────────────
if ($viewJobId) {
    $s = $db->prepare(
        "SELECT j.*, d.name AS dept_name, p.title AS position_title,
                b.name AS branch_name, b.city AS branch_city,
                pm.pos_position_label
         FROM job_postings j
         LEFT JOIN pos_job_postings_meta pm ON pm.job_posting_id = j.id
         LEFT JOIN departments d ON j.department_id = d.id
         LEFT JOIN positions p ON j.position_id = p.id
         LEFT JOIN branches b ON j.branch_id = b.id
         WHERE j.id = ? AND j.status = 'open'
           AND j.title IN ($publicJobTitleSql)
           AND (j.deadline IS NULL OR j.deadline >= CURDATE())"
    );
    $s->execute([$viewJobId]);
    $selectedJob = $s->fetch();
    if ($selectedJob && empty($selectedJob['branch_id']) && $viewBranchId) {
        foreach($activeBranches as $b){ if((int)$b['id']===$viewBranchId){ $selectedJob['selected_branch_id']=$viewBranchId; $selectedJob['branch_name']=$b['name']; $selectedJob['branch_city']=$b['city']; break; }}
    }
    if (!$selectedJob) { $viewJobId = 0; }
}

// ── Handle application submission (no login required) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $jobId      = (int)($_POST['job_id'] ?? 0);
        // Store plain text in the database. Escape only when rendering HTML.
        $firstname  = trim($_POST['firstname']  ?? '');
        $lastname   = trim($_POST['lastname']   ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $phone      = trim($_POST['phone']    ?? '');
        $address    = trim($_POST['address']   ?? '');
        $gender     = in_array($_POST['gender'] ?? '', ['Male','Female','Other']) ? $_POST['gender'] : '';
        $education  = trim($_POST['highest_education'] ?? '');
        $school     = trim($_POST['school']  ?? '');
        $course     = trim($_POST['course']  ?? '');
        $years_exp  = max(0, (int)($_POST['years_experience'] ?? 0));
        $skills     = trim($_POST['skills']       ?? '');
        $cover      = trim($_POST['cover_letter'] ?? '');
        $branchId   = (int)($_POST['branch_id'] ?? 0) ?: null;

        // Validation
        if (!$jobId)                                            $errors[] = 'Job not specified.';
        if (!$firstname)                                        $errors[] = 'First name is required.';
        if (!$lastname)                                         $errors[] = 'Last name is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';

        // Job still open?
        $jobRow = null;
        if ($jobId && empty($errors)) {
            $chk = $db->prepare(
                "SELECT j.id, j.branch_id FROM job_postings j
                 LEFT JOIN positions p ON p.id=j.position_id
                 WHERE j.id = ? AND j.status = 'open'
                   AND j.title IN ($publicJobTitleSql)
                   AND (j.deadline IS NULL OR j.deadline >= CURDATE())"
            );
            $chk->execute([$jobId]);
            $jobRow = $chk->fetch();
            if (!$jobRow) $errors[] = 'This position is no longer accepting applications.';
        }

        // Branch: if the posting targets one specific branch, use that.
        // If it's open to any branch, the applicant must pick one.
        if ($jobRow) {
            if ($jobRow['branch_id']) {
                $branchId = (int)$jobRow['branch_id'];
            } elseif (!$branchId) {
                $errors[] = 'Please select which branch you want to apply to.';
            } else {
                $bc=$db->prepare("SELECT COUNT(*) FROM branches b WHERE b.id=? AND b.status='active' AND (EXISTS(SELECT 1 FROM job_posting_branches jpb WHERE jpb.job_posting_id=? AND jpb.branch_id=b.id) OR ?=(SELECT branch_id FROM job_postings WHERE id=?))");
                $bc->execute([$branchId,$jobId,$branchId,$jobId]);
                if(!(int)$bc->fetchColumn()) $errors[]='That branch is no longer accepting applications for this job.';
            }
        }

        // Find or create applicant_account by email
        $accountId = null;
        if (empty($errors)) {
            try {
                $accStmt = $db->prepare("SELECT id FROM applicant_accounts WHERE email = ?");
                $accStmt->execute([$email]);
                $acc = $accStmt->fetch();

                if ($acc) {
                    $accountId = $acc['id'];
                    // Refresh name/phone so we always have the latest
                    $db->prepare("UPDATE applicant_accounts SET firstname=?, lastname=?, phone=? WHERE id=?")
                       ->execute([$firstname, $lastname, $phone, $accountId]);
                } else {
                    // No password — email-only account
                    $db->prepare(
                        "INSERT INTO applicant_accounts
                         (firstname, lastname, email, phone, password, status, created_at)
                         VALUES (?, ?, ?, ?, '', 'active', NOW())"
                    )->execute([$firstname, $lastname, $email, $phone]);
                    $accountId = (int)$db->lastInsertId();
                }
            } catch (Exception $e) {
                $errors[] = 'Could not save account. Please try again.';
            }
        }

        // Duplicate application check
        if ($accountId && empty($errors)) {
            $dup = $db->prepare("SELECT id FROM applicants WHERE applicant_account_id = ? AND job_id = ? AND branch_id = ?");
            $dup->execute([$accountId, $jobId, $branchId]);
            if ($dup->fetch()) {
                $errors[] = 'You have already applied for this position at this branch with this email address.';
            }
        }

        // Upload resume
        $resume = null;
        if (empty($errors) && !empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['resume'], 'resumes', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
            if ($up) { $resume = basename($up); }
            else     { $errors[] = 'Resume upload failed — please use PDF or DOC, max 5 MB.'; }
        }

        // Insert application
        if (empty($errors)) {
            $code = generateApplicantCode();
            $db->prepare(
                "INSERT INTO applicants
                 (applicant_account_id, applicant_code, job_id, branch_id,
                  firstname, lastname, middlename, email, phone, address,
                  gender, highest_education, school, course,
                  years_experience, skills, cover_letter, resume,
                  status, applied_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())"
            )->execute([
                $accountId, $code, $jobId, $branchId,
                $firstname, $lastname, $middlename, $email, $phone, $address,
                $gender, $education, $school, $course,
                $years_exp, $skills, $cover, $resume,
            ]);

            // Notify HR / Admin
            try {
                $db->prepare(
                    "INSERT INTO notifications (user_id, title, message, type, created_at)
                     SELECT u.id,
                            'New Job Application',
                            CONCAT(?, ' (', ?, ') applied for ',
                                   (SELECT title FROM job_postings WHERE id=?), '.'),
                            'info', NOW()
                     FROM users u WHERE u.role IN ('admin','hr') AND u.status = 'active'"
                )->execute(["$firstname $lastname", $email, $jobId]);
            } catch (Exception $e) { /* non-critical */ }

            $success   = $code;
            $appCode   = $code;
            $appEmail  = $email;
            $_POST     = [];
            $selectedJob = null;
            $viewJobId   = 0;
        } else {
            // Reload job on error
            if ($jobId) {
                $s2 = $db->prepare(
                    "SELECT j.*, d.name AS dept_name
                     FROM job_postings j
                     LEFT JOIN departments d ON j.department_id = d.id
                     LEFT JOIN positions p ON j.position_id = p.id
                     WHERE j.id = ? AND j.status = 'open'
                       AND (j.deadline IS NULL OR j.deadline >= CURDATE())"
                );
                $s2->execute([$jobId]);
                $selectedJob = $s2->fetch();
                $viewJobId   = $jobId;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Openings — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'Inter', sans-serif; }
        .job-card { cursor: pointer; transition: box-shadow .2s, border-color .2s; border: 2px solid transparent; }
        .job-card:hover { border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67,97,238,.12); }
        .careers-header {
            background: linear-gradient(135deg, #4361ee 0%, #4895ef 100%);
            color: #fff; border-radius: 14px; padding: 2.2rem 2rem;
            margin-bottom: 2rem;
        }
        .status-bar {
            background: #fff; border-bottom: 1px solid #e5e7eb;
            padding: .55rem 0; margin-bottom: 1.5rem;
        }
        .badge-type { background: rgba(67,97,238,.1); color: #4361ee; font-size: .75rem; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
        .form-label { font-weight: 600; font-size: .875rem; }
        .job-card { overflow:hidden; transform:translateY(0); animation:careerCardIn .5s ease both; }
        .job-card:hover { transform:translateY(-6px); }
        .job-card .card-body { min-height:260px; }
        .job-card .card-footer { margin-top:auto; background:rgba(255,255,255,.88); border-top:1px solid #edf1f7; }
        .job-branch-row { min-height:31px; display:flex; align-items:center; margin:.45rem 0 .55rem; }
        .job-branch-row .badge { display:inline-flex; align-items:center; white-space:normal; text-align:left; line-height:1.25; padding:.45rem .62rem; }
        .careers-header { position:relative; overflow:hidden; animation:careerHeroIn .55s ease both; }
        .careers-header::after { content:''; position:absolute; width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.10); right:-70px; top:-90px; }
        .btn, .form-control, .form-select, .card { transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease, background-color .2s ease; }
        .btn:active { transform:scale(.98); }
        .form-control:focus, .form-select:focus { transform:translateY(-1px); box-shadow:0 0 0 .22rem rgba(67,97,238,.13); }
        @keyframes careerCardIn { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
        @keyframes careerHeroIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration:.01ms!important; transition-duration:.01ms!important; } }
    </style>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────────────── -->
<nav class="navbar navbar-dark" style="background:linear-gradient(90deg,#4361ee,#4895ef);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>apply.php">
            <i class="bi bi-briefcase-fill me-2"></i><?= SITE_NAME ?> — Careers
        </a>
        <a href="<?= SITE_URL ?>applicant/status.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-search me-1"></i>Check My Application
        </a>
    </div>
</nav>

<div class="container py-4">

<?php if ($success): ?>
<!-- ── Success ─────────────────────────────────────────────────────── -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-success shadow-sm text-center p-4">
            <div class="display-3 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="fw-bold mb-2">Application Submitted!</h3>
            <p class="text-muted mb-3">Your application reference code is:</p>
            <div class="alert alert-success fw-bold fs-4 letter-spacing-1 py-2"><?= htmlspecialchars($appCode) ?></div>
            <p class="text-muted small">
                Our HR team will review your application and reach out via
                <strong><?= htmlspecialchars($appEmail) ?></strong>.<br>
                Save your code — you'll need it to check your status.
            </p>
            <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
                <a href="<?= SITE_URL ?>applicant/status.php?email=<?= urlencode($appEmail) ?>"
                   class="btn btn-success">
                    <i class="bi bi-search me-1"></i>Track My Application
                </a>
                <a href="<?= SITE_URL ?>apply.php" class="btn btn-outline-primary">
                    <i class="bi bi-briefcase me-1"></i>Browse More Jobs
                </a>
            </div>
        </div>
    </div>
</div>

<?php elseif ($selectedJob): ?>
<!-- ── Application Form ────────────────────────────────────────────── -->
<div class="row">
    <!-- Sidebar: job snapshot -->
    <div class="col-lg-4 mb-4">
        <div class="card sticky-top shadow-sm" style="top:16px;">
            <div class="card-body d-flex flex-column">
                <span class="badge badge-type mb-2 align-self-start"><?= htmlspecialchars($selectedJob['job_type'] ?? 'Full-time') ?></span>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($selectedJob['title']) ?></h5>
                <?php if (!empty($selectedJob['branch_name'])): ?>
                <div class="small mb-1"><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($selectedJob['branch_name']) ?><?= $selectedJob['branch_city'] ? ', '.htmlspecialchars($selectedJob['branch_city']) : '' ?></span></div>
                <?php endif; ?>
                <?php if ($selectedJob['dept_name']): ?>
                <div class="small text-muted mb-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($selectedJob['dept_name']) ?></div>
                <?php endif; ?>
                <?php if ($selectedJob['salary_min'] || $selectedJob['salary_max']): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-cash me-1"></i>
                    ₱<?= number_format($selectedJob['salary_min'] ?? 0) ?>
                    <?php if ($selectedJob['salary_max']): ?>– ₱<?= number_format($selectedJob['salary_max']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($selectedJob['deadline'])): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-calendar me-1"></i>Apply by: <?= formatDate($selectedJob['deadline']) ?>
                    <?php if ($selectedJob['deadline'] < date('Y-m-d')): ?>
                    <span class="badge bg-danger ms-1">Closed</span>
                    <?php elseif ($selectedJob['deadline'] <= date('Y-m-d', strtotime('+7 days'))): ?>
                    <span class="badge bg-warning text-dark ms-1">Closing soon</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($selectedJob['description']): ?>
                <hr class="my-2">
                <div class="small text-muted" style="white-space:pre-wrap;"><?= htmlspecialchars($selectedJob['description']) ?></div>
                <?php endif; ?>
                <?php if ($selectedJob['requirements']): ?>
                <hr class="my-2">
                <div class="fw-semibold small mb-1">Requirements</div>
                <div class="small text-muted" style="white-space:pre-wrap;"><?= htmlspecialchars($selectedJob['requirements']) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="<?= SITE_URL ?>apply.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-arrow-left me-1"></i>Back to Jobs
                </a>
            </div>
        </div>
    </div>

    <!-- Application form -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header fw-bold">
                <i class="bi bi-person-plus me-2 text-primary"></i>Apply for: <?= htmlspecialchars($selectedJob['title']) ?>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>

                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                     No password is needed to apply. If you create an Applicant Account later
                     with this same email, the system will safely complete the email-only profile
                     created for this application.
                </div>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="apply"  value="1">
                    <input type="hidden" name="job_id" value="<?= (int)$selectedJob['id'] ?>">

                    <?php if (!empty($selectedJob['selected_branch_id'])): ?>
                    <input type="hidden" name="branch_id" value="<?= (int)$selectedJob['selected_branch_id'] ?>">
                    <div class="alert alert-primary py-2 small mb-3"><i class="bi bi-geo-alt-fill me-1"></i>Applying to <strong><?= htmlspecialchars($selectedJob['branch_name']) ?></strong><?= !empty($selectedJob['branch_city']) ? ' — '.htmlspecialchars($selectedJob['branch_city']) : '' ?></div>
                    <?php elseif (!empty($selectedJob['branch_id'])): ?>
                    <input type="hidden" name="branch_id" value="<?= (int)$selectedJob['branch_id'] ?>">
                    <?php else: ?>
                    <!-- Open posting without a branch in URL: applicant chooses which branch they want to work at -->
                    <p class="section-title mb-2"><i class="bi bi-geo-alt me-1 text-primary"></i>Preferred Branch</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Which branch are you applying to? <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select a branch…</option>
                                <?php foreach ($activeBranches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= (int)($_POST['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?><?= $b['city'] ? ' — '.htmlspecialchars($b['city']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($activeBranches)): ?>
                            <div class="form-text text-danger">No branches are open right now — please check back later.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Personal info -->
                    <p class="section-title mb-2"><i class="bi bi-person me-1 text-primary"></i>Personal Information</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middlename" class="form-control"
                                   value="<?= htmlspecialchars($_POST['middlename'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required
                                   placeholder="you@example.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <div class="form-text">This email is used to track your application.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select</option>
                                <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= (($_POST['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Home Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Education & Experience -->
                    <p class="section-title mb-2 mt-3"><i class="bi bi-mortarboard me-1 text-primary"></i>Education & Experience</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Highest Education</label>
                            <select name="highest_education" class="form-select">
                                <option value="">Select</option>
                                <?php foreach (["High School","Vocational","Bachelor's Degree","Master's Degree","Doctorate"] as $ed): ?>
                                <option value="<?= $ed ?>" <?= (($_POST['highest_education'] ?? '') === $ed) ? 'selected' : '' ?>><?= $ed ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">School / University</label>
                            <input type="text" name="school" class="form-control"
                                   value="<?= htmlspecialchars($_POST['school'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course / Degree</label>
                            <input type="text" name="course" class="form-control"
                                   value="<?= htmlspecialchars($_POST['course'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Years of Experience</label>
                            <input type="number" name="years_experience" class="form-control" min="0"
                                   value="<?= (int)($_POST['years_experience'] ?? 0) ?>">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Key Skills</label>
                            <input type="text" name="skills" class="form-control"
                                   placeholder="e.g. Customer Service, Cash Handling, Inventory…"
                                   value="<?= htmlspecialchars($_POST['skills'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Cover letter & resume -->
                    <p class="section-title mb-2 mt-3"><i class="bi bi-file-text me-1 text-primary"></i>Cover Letter & Resume</p>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Cover Letter</label>
                            <textarea name="cover_letter" class="form-control" rows="5"
                                      placeholder="Why are you a great fit for this role?"><?= htmlspecialchars($_POST['cover_letter'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Resume / CV <span class="text-muted small">(PDF or DOC, max 5 MB)</span></label>
                            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="submitBtn">
                            <i class="bi bi-send me-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Job Listing ─────────────────────────────────────────────────── -->
<div class="careers-header">
    <h2 class="fw-bold mb-1"><i class="bi bi-briefcase me-2"></i>Open Positions</h2>
    <p class="mb-0 opacity-75">Find a role that fits you — no account needed to apply.</p>
</div>

<?php if (empty($jobs)): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <div class="display-4 mb-3"><i class="bi bi-briefcase"></i></div>
    <h5>No open positions right now.</h5>
    <p class="small">Check back soon — new roles are posted regularly.</p>
</div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($jobCards as $job): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 job-card shadow-sm">
            <div class="card-body d-flex flex-column">
                <span class="badge badge-type mb-2 align-self-start"><?= htmlspecialchars($job['job_type'] ?? 'Full-time') ?></span>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($job['title']) ?></h5>
                <?php if (!empty($job['branch_name'])): ?>
                <div class="job-branch-row"><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($job['branch_name']) ?><?= $job['branch_city'] ? ', '.htmlspecialchars($job['branch_city']) : '' ?></span></div>
                <?php endif; ?>
                <?php if ($job['dept_name']): ?>
                <div class="small text-muted mb-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($job['dept_name']) ?></div>
                <?php endif; ?>
                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-cash me-1"></i>
                    ₱<?= number_format($job['salary_min'] ?? 0) ?>
                    <?php if ($job['salary_max']): ?> – ₱<?= number_format($job['salary_max']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($job['deadline']): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-calendar me-1"></i>Apply by: <?= formatDate($job['deadline']) ?>
                    <?php if ($job['deadline'] < date('Y-m-d')): ?>
                    <span class="badge bg-danger ms-1">Closed</span>
                    <?php elseif ($job['deadline'] <= date('Y-m-d', strtotime('+7 days'))): ?>
                    <span class="badge bg-warning text-dark ms-1">Closing soon</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($job['description']): ?>
                <p class="text-muted small mb-2"><?= htmlspecialchars(substr($job['description'], 0, 110)) ?><?= strlen($job['description']) > 110 ? '…' : '' ?></p>
                <?php endif; ?>
                <?php if ($job['slots']): ?>
                <div class="small text-muted"><i class="bi bi-people me-1"></i><?= $job['slots'] ?> slot<?= $job['slots'] > 1 ? 's' : '' ?> available</div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="<?= SITE_URL ?>apply.php?job_id=<?= $job['id'] ?>&branch_id=<?= (int)($job['card_branch_id'] ?? $job['branch_id'] ?? 0) ?>" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-send me-1"></i>Apply Now
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="text-center mt-5 pb-3 text-muted small">
    &copy; <?= date('Y') ?> <?= SITE_NAME ?>
    &nbsp;·&nbsp;
    <a href="<?= SITE_URL ?>applicant/status.php" class="text-muted">Check application status</a>
</div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Disable submit button after click to prevent double-submit
const submitBtn = document.getElementById('submitBtn');
if (submitBtn) {
    document.querySelector('form')?.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
    });
}
</script>
</body>
</html>
