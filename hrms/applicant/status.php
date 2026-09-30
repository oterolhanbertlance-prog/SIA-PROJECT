<?php
/**
 * Applicant Status Page — no login required.
 * Requires BOTH the application code (e.g. qpp-2066) AND the email
 * used when applying. Neither alone is enough — privacy-first.
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$db       = getDB();
$email    = strtolower(trim($_GET['email']    ?? $_POST['email']    ?? ''));
$appCode  = strtolower(trim($_GET['app_code'] ?? $_POST['app_code'] ?? ''));
$looked   = false;
$verified = false;           // true only when BOTH fields match a record
$account      = null;
$applications = [];
$interviews   = [];
$hrEmails     = [];

if ($email && $appCode) {
    $looked = true;

    // Step 1 — find the specific applicant record that matches BOTH email and code
    try {
        $s = $db->prepare(
            "SELECT a.*, j.title AS job_title
             FROM applicants a
             LEFT JOIN job_postings j ON a.job_id = j.id
             WHERE LOWER(a.email) = ? AND LOWER(a.applicant_code) = ?
             LIMIT 1"
        );
        $s->execute([$email, $appCode]);
        $matchedApplicant = $s->fetch();
    } catch (Exception $e) { $matchedApplicant = null; }

    if ($matchedApplicant) {
        $verified = true;
        $accId    = $matchedApplicant['applicant_account_id'] ?? null;

        // Step 2 — load the full applicant account
        if ($accId) {
            try {
                $s = $db->prepare("SELECT * FROM applicant_accounts WHERE id = ?");
                $s->execute([$accId]);
                $account = $s->fetch();
            } catch (Exception $e) {}
        }

        // Step 3 — all applications for this account
        if ($accId) {
            try {
                $s = $db->prepare(
                    "SELECT a.*, j.title AS job_title, j.job_type
                     FROM applicants a
                     LEFT JOIN job_postings j ON a.job_id = j.id
                     WHERE a.applicant_account_id = ?
                     ORDER BY a.applied_at DESC"
                );
                $s->execute([$accId]);
                $applications = $s->fetchAll();
            } catch (Exception $e) {}
        } else {
            // Account-less applicant — show only this one record
            $applications = [$matchedApplicant];
        }

        // Step 4 — interviews
        $appIds = array_column($applications, 'id');
        if ($appIds) {
            try {
                $ph = implode(',', array_fill(0, count($appIds), '?'));
                $s = $db->prepare(
                    "SELECT iv.*, a.firstname, a.job_id, j.title AS job_title
                     FROM interviews iv
                     JOIN applicants a ON iv.applicant_id = a.id
                     LEFT JOIN job_postings j ON a.job_id = j.id
                     WHERE iv.applicant_id IN ($ph)
                     ORDER BY iv.interview_date DESC"
                );
                $s->execute($appIds);
                $interviews = $s->fetchAll();
            } catch (Exception $e) {}
        }

        // Step 5 — HR emails / inbox
        if ($appIds) {
            try {
                $ph = implode(',', array_fill(0, count($appIds), '?'));
                $s = $db->prepare(
                    "SELECT e.*, j.title AS job_title
                     FROM hr_emails e
                     JOIN applicants a ON e.applicant_id = a.id
                     LEFT JOIN job_postings j ON a.job_id = j.id
                     WHERE e.applicant_id IN ($ph)
                     ORDER BY e.sent_at DESC"
                );
                $s->execute($appIds);
                $hrEmails = $s->fetchAll();
            } catch (Exception $e) {}
        }
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function statusBadgeColor($status) {
    return match(strtolower($status ?? '')) {
        'pending'              => 'warning text-dark',
        'shortlisted'          => 'info text-dark',
        'interview'            => 'primary',
        'hired'                => 'success',
        'failed','rejected'    => 'danger',
        default                => 'secondary',
    };
}
function statusLabel($status) {
    return match(strtolower($status ?? '')) {
        'pending'     => 'Under Review',
        'shortlisted' => 'Shortlisted',
        'interview'   => 'Interview Scheduled',
        'hired'       => 'Hired 🎉',
        'failed'      => 'Not Selected',
        'rejected'    => 'Not Selected',
        default       => ucfirst($status ?? '—'),
    };
}

$isHired = $account && !empty($account['hired_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Application Status — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f4f8; }
        .status-hero {
            background: linear-gradient(135deg, #4361ee 0%, #4895ef 100%);
            color: #fff; padding: 2rem 0 3.5rem;
        }
        .status-card {
            margin-top: -2.5rem;
            border: none;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(67,97,238,.13);
        }
        .form-control, .form-control:focus { border-radius: 8px; padding: .7rem 1rem; }
        .input-label { font-size: .78rem; font-weight: 600; text-transform: uppercase;
                       letter-spacing: .05em; color: #6c757d; margin-bottom: .3rem; }
        .tab-pane { padding-top: 1rem; }
        .hr-email-card { border-left: 4px solid #4361ee; }
        .hr-email-card.unread { border-left-color: #f72585; background: #fff8fe; }
        .interview-card { border-left: 4px solid #4895ef; }
        .verify-badge {
            display:inline-flex; align-items:center; gap:.4rem;
            background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7;
            border-radius:20px; padding:.25rem .8rem; font-size:.8rem; font-weight:600;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark" style="background:linear-gradient(90deg,#4361ee,#4895ef);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>apply.php">
            <i class="bi bi-briefcase-fill me-2"></i><?= SITE_NAME ?> — Careers
        </a>
        <a href="<?= SITE_URL ?>apply.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-briefcase me-1"></i>Browse Jobs
        </a>
    </div>
</nav>

<!-- Hero -->
<div class="status-hero">
    <div class="container text-center">
        <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock me-2"></i>Check Your Application</h2>
        <p class="opacity-75 mb-0">Enter your <strong>Application Code</strong> and <strong>Email</strong> to securely view your status.</p>
    </div>
</div>

<div class="container pb-5">

    <!-- ── Verification Form ──────────────────────────────────────────────── -->
    <div class="card status-card mb-4">
        <div class="card-body p-4">

            <?php if ($looked && !$verified): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-x-circle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <strong>No match found.</strong>
                    Please check that your <strong>Application Code</strong> and <strong>Email</strong>
                    are exactly as shown on your confirmation. Both fields must match your application.
                </div>
            </div>
            <?php endif; ?>

            <form method="GET" action="" class="row g-3 align-items-end">

                <!-- Application Code -->
                <div class="col-md-4">
                    <div class="input-label"><i class="bi bi-upc me-1"></i>Application Code</div>
                    <input type="text" name="app_code"
                           class="form-control font-monospace"
                           placeholder="e.g. qpp-2066"
                           value="<?= htmlspecialchars($appCode) ?>"
                           autocomplete="off" spellcheck="false" required>
                    <div class="form-text">Given when you submitted your application.</div>
                </div>

                <!-- Email -->
                <div class="col-md-4">
                    <div class="input-label"><i class="bi bi-envelope me-1"></i>Email Address</div>
                    <input type="email" name="email"
                           class="form-control"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($email) ?>"
                           required>
                    <div class="form-text">Email you used when applying.</div>
                </div>

                <!-- Submit -->
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-shield-check me-1"></i>Verify &amp; View Status
                    </button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($verified): ?>

    <!-- ── Verified header ───────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <span class="verify-badge">
            <i class="bi bi-patch-check-fill text-success"></i>
            Identity Verified
        </span>
        <span class="text-muted small">
            Showing results for
            <strong class="font-monospace"><?= htmlspecialchars(strtoupper($appCode)) ?></strong>
            &middot; <?= htmlspecialchars($email) ?>
        </span>
    </div>

    <!-- ── Hired banner ──────────────────────────────────────────────────── -->
    <?php if ($isHired): ?>
    <div class="card mb-4 border-0 shadow overflow-hidden">
        <div class="d-flex align-items-center gap-3 px-4 py-3"
             style="background:linear-gradient(135deg,#0d6efd 0%,#198754 100%);color:#fff;">
            <i class="bi bi-person-badge-fill fs-2 flex-shrink-0"></i>
            <div>
                <div class="fw-bold fs-5 mb-0">🎉 Congratulations — You've Been Hired!</div>
                <div class="small opacity-75">Your employee account is ready. Use the credentials below to log in.</div>
            </div>
        </div>
        <div class="px-4 py-3 bg-white">
            <div class="row g-3 align-items-center">
                <div class="col-sm-4">
                    <div class="text-muted small mb-1">Employee ID</div>
                    <div class="fw-bold fs-6 font-monospace"
                         id="credEmpId"><?= htmlspecialchars($account['hired_employee_id'] ?? '—') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small mb-1">Username</div>
                    <div class="fw-bold fs-6 font-monospace"
                         id="credUser"><?= htmlspecialchars($account['hired_username'] ?? '—') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small mb-1">Temporary Password</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6 font-monospace" id="credPwShow" style="display:none;">
                            <?= htmlspecialchars($account['hired_temp_password'] ?? '') ?>
                        </span>
                        <span class="fw-bold fs-6 font-monospace text-muted" id="credPwMask">••••••••</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                onclick="toggleCredPw()" title="Show / hide password">
                            <i class="bi bi-eye" id="credPwIcon"></i>
                        </button>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <a href="<?= SITE_URL ?>index.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Go to Employee Login
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyAllCreds()">
                    <i class="bi bi-clipboard me-1"></i>Copy All Credentials
                </button>
                <span class="text-warning small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Temporary password</strong> — please change it after your first login.
                </span>
            </div>
        </div>
    </div>
    <?php elseif ($account && isset($account['hired_username']) && !$isHired): ?>
    <!-- Hired but credentials not yet ready -->
    <div class="card mb-4 border-warning shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-hourglass-split fs-3 text-warning flex-shrink-0"></i>
            <div>
                <div class="fw-semibold">Application accepted — account being set up</div>
                <div class="small text-muted">HR is preparing your employee account. Your login credentials will appear here once ready.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Tabs ──────────────────────────────────────────────────────────── -->
    <ul class="nav nav-tabs mb-0" id="statusTabs">
        <li class="nav-item">
            <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-apps">
                <i class="bi bi-file-earmark-text me-1"></i>Applications
                <span class="badge bg-primary rounded-pill ms-1"><?= count($applications) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mail">
                <i class="bi bi-envelope me-1"></i>HR Messages
                <?php $unread = count(array_filter($hrEmails, fn($e) => !$e['is_read'])); ?>
                <?php if ($unread > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?= $unread ?></span>
                <?php endif; ?>
            </button>
        </li>
        <?php if (!empty($interviews)): ?>
        <li class="nav-item">
            <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-interviews">
                <i class="bi bi-camera-video me-1"></i>Interviews
                <span class="badge bg-info text-dark rounded-pill ms-1"><?= count($interviews) ?></span>
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content card border-top-0 shadow-sm rounded-bottom p-3" id="statusTabContent">

        <!-- Applications tab -->
        <div class="tab-pane fade show active" id="tab-apps">
            <?php if (empty($applications)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-2">No applications found. <a href="<?= SITE_URL ?>apply.php">Browse open jobs</a>.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference Code</th>
                            <th>Position</th>
                            <th>Applied</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <code class="fw-semibold"><?= htmlspecialchars($app['applicant_code']) ?></code>
                                <?php if (strtolower($app['applicant_code']) === $appCode): ?>
                                <span class="badge bg-success-subtle text-success ms-1" style="font-size:.65rem;">current</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($app['job_title'] ?? '—') ?></td>
                            <td class="text-muted small"><?= formatDate($app['applied_at']) ?></td>
                            <td>
                                <span class="badge bg-<?= statusBadgeColor($app['status']) ?>">
                                    <?= statusLabel($app['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- HR Messages tab -->
        <div class="tab-pane fade" id="tab-mail">
            <?php if (empty($hrEmails)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-envelope display-4"></i>
                <p class="mt-2 mb-0">No messages from HR yet. Check back after your application is reviewed.</p>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($hrEmails as $mail): ?>
                <div class="card hr-email-card <?= !$mail['is_read'] ? 'unread' : '' ?> shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
                            <div class="fw-bold">
                                <?php if (!$mail['is_read']): ?>
                                <span class="badge bg-danger me-1" style="font-size:.65rem;">NEW</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($mail['subject']) ?>
                            </div>
                            <div class="text-muted small"><?= formatDateTime($mail['sent_at']) ?></div>
                        </div>
                        <div class="small text-muted mb-2">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($mail['sender_name']) ?>
                            <?php if ($mail['job_title']): ?>
                            &middot; <span class="text-primary"><?= htmlspecialchars($mail['job_title']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-1" style="font-size:.92rem;white-space:pre-wrap;"><?= nl2br(htmlspecialchars($mail['body'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Interviews tab -->
        <?php if (!empty($interviews)): ?>
        <div class="tab-pane fade" id="tab-interviews">
            <div class="d-flex flex-column gap-3">
                <?php foreach ($interviews as $iv): ?>
                <div class="card interview-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-bold mb-1">
                                    <i class="bi bi-camera-video text-primary me-1"></i>
                                    <?= htmlspecialchars($iv['job_title'] ?? 'Interview') ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= formatDateTime($iv['interview_date']) ?>
                                </div>
                                <?php if (!empty($iv['location'])): ?>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($iv['location']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($iv['notes'])): ?>
                                <div class="small mt-2 text-secondary"><?= nl2br(htmlspecialchars($iv['notes'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php $ivStatus = $iv['status'] ?? 'scheduled'; ?>
                                <span class="badge bg-<?= $ivStatus === 'completed' ? 'success' : ($ivStatus === 'cancelled' ? 'danger' : 'primary') ?>">
                                    <?= ucfirst($ivStatus) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /tab-content -->

    <?php endif; /* verified */ ?>

    <div class="text-center mt-4 text-muted small">
        &copy; <?= date('Y') ?> <?= SITE_NAME ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-open HR Messages tab if there are unread messages
const unreadBadge = document.querySelector('[data-bs-target="#tab-mail"] .badge.bg-danger');
if (unreadBadge && !location.hash) {
    document.querySelector('[data-bs-target="#tab-mail"]')?.click();
}
if (location.hash === '#mail')       document.querySelector('[data-bs-target="#tab-mail"]')?.click();
if (location.hash === '#interviews') document.querySelector('[data-bs-target="#tab-interviews"]')?.click();

function toggleCredPw() {
    const show = document.getElementById('credPwShow');
    const mask = document.getElementById('credPwMask');
    const icon = document.getElementById('credPwIcon');
    if (!show) return;
    const visible = show.style.display !== 'none';
    show.style.display = visible ? 'none'   : 'inline';
    mask.style.display = visible ? 'inline' : 'none';
    icon.className     = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function copyAllCreds() {
    const empId = document.getElementById('credEmpId')?.textContent.trim()  ?? '';
    const user  = document.getElementById('credUser')?.textContent.trim()   ?? '';
    const pw    = document.getElementById('credPwShow')?.textContent.trim() ?? '';
    const text  = `Employee ID : ${empId}\nUsername    : ${user}\nPassword    : ${pw}\n\n⚠ Change this password after your first login.`;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied!';
        btn.classList.replace('btn-outline-secondary', 'btn-success');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.replace('btn-success', 'btn-outline-secondary'); }, 2000);
    });
}
</script>
</body>
</html>
