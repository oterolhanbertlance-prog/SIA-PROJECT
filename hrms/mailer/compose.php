<?php
/**
 * HR Mailer — Compose Email to Applicant(s)
 * Saves the email to hr_emails so applicants can read it in their portal inbox.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
requireMailerLogin();

$db     = getDB();
$mailer = currentMailer();

$errors   = [];
$success  = '';

// Pre-fill applicant if coming from applicants list
$preApplicantId = (int)($_GET['applicant_id'] ?? 0);
$preApplicant   = null;
if ($preApplicantId) {
    $s = $db->prepare("SELECT * FROM applicants WHERE id = ?");
    $s->execute([$preApplicantId]);
    $preApplicant = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sendTo    = $_POST['send_to'] ?? 'one';  // 'one' or 'all' or 'status'
    $applicantId = (int)($_POST['applicant_id'] ?? 0);
    $statusFilter = $_POST['status_filter'] ?? '';
    $subject   = trim($_POST['subject'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $senderName = $mailer['fullname'] ?: $mailer['username'];
    $senderEmail = $mailer['email'];

    if (!$subject) $errors[] = 'Subject is required.';
    if (!$body)    $errors[] = 'Message body is required.';
    if ($sendTo === 'one' && !$applicantId) $errors[] = 'Please select an applicant.';

    if (empty($errors)) {
        // Gather target applicants
        $targets = [];
        if ($sendTo === 'one') {
            $s = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $s->execute([$applicantId]);
            $t = $s->fetch();
            if ($t) $targets = [$t];
        } elseif ($sendTo === 'status' && $statusFilter) {
            $s = $db->prepare("SELECT * FROM applicants WHERE status = ?");
            $s->execute([$statusFilter]);
            $targets = $s->fetchAll();
        } else {
            $targets = $db->query("SELECT * FROM applicants")->fetchAll();
        }

        if (empty($targets)) {
            $errors[] = 'No applicants matched your selection.';
        } else {
            $insertStmt = $db->prepare(
                "INSERT INTO hr_emails
                 (applicant_id, sender_name, sender_email, subject, body, sent_at, is_read)
                 VALUES (?, ?, ?, ?, ?, NOW(), 0)"
            );

            foreach ($targets as $target) {
                $insertStmt->execute([
                    $target['id'],
                    $senderName,
                    $senderEmail,
                    $subject,
                    $body,
                ]);

                // Also fire an in-app notification if they have an applicant account
                if (!empty($target['applicant_account_id'])) {
                    notifyApplicant(
                        $target['applicant_account_id'],
                        '📧 New Email from HR: ' . $subject,
                        substr(strip_tags($body), 0, 200),
                        'info',
                        SITE_URL . 'applicant/inbox.php'
                    );
                }
            }

            $cnt     = count($targets);
            $success = "Email sent to $cnt applicant" . ($cnt !== 1 ? 's' : '') . " successfully.";
        }
    }
}

// All applicants for dropdown
$allApplicants = $db->query(
    "SELECT a.*, j.title as job_title FROM applicants a
     LEFT JOIN job_postings j ON a.job_id = j.id
     ORDER BY a.firstname, a.lastname"
)->fetchAll();

$statusOptions = ['pending','initial_interview','final_interview','passed','failed','hired','withdrawn'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email — HR Mailer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8f9fa; }
        .topbar { background:#fff; border-bottom:1px solid #e5e7eb; padding:.75rem 1.5rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .topbar-brand { font-weight:700; color:#2d6a4f; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .compose-card { background:#fff; border-radius:1rem; box-shadow:0 4px 20px rgba(0,0,0,.08); }
        .btn-send { background:linear-gradient(135deg,#2d6a4f,#74c69d); border:none; color:#fff; font-weight:600; }
        .btn-send:hover { background:linear-gradient(135deg,#1b4332,#2d6a4f); color:#fff; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <i class="bi bi-envelope-check-fill text-success"></i> HR Mailer
    </div>
    <div class="d-flex gap-2">
        <a href="<?= SITE_URL ?>mailer/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <a href="<?= SITE_URL ?>dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to HRMS
        </a>
    </div>
</div>

<div class="container py-4" style="max-width:760px;">
    <h4 class="fw-bold mb-4"><i class="bi bi-pencil-square me-2 text-success"></i>Compose Email</h4>

    <?php if ($success): ?>
    <div class="alert alert-success border-0 rounded-3">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <a href="<?= SITE_URL ?>mailer/dashboard.php" class="alert-link ms-2">Back to Dashboard →</a>
    </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger border-0 rounded-3 py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="compose-card p-4">
        <form method="POST">
            <!-- Sender info (read-only) -->
            <div class="mb-3 p-3 bg-light rounded-3">
                <div class="small text-muted mb-1">Sending as:</div>
                <div class="fw-semibold"><?= htmlspecialchars($mailer['fullname'] ?: $mailer['username']) ?>
                    <span class="text-muted fw-normal">&lt;<?= htmlspecialchars($mailer['email']) ?>&gt;</span>
                </div>
            </div>

            <!-- Send to -->
            <div class="mb-3">
                <label class="form-label fw-semibold small">Send To</label>
                <div class="d-flex gap-3 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_to" id="toOne"
                               value="one" <?= ($_POST['send_to'] ?? 'one') !== 'all' && ($_POST['send_to'] ?? 'one') !== 'status' ? 'checked' : '' ?>
                               onchange="toggleRecipient()">
                        <label class="form-check-label small" for="toOne">One Applicant</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_to" id="toStatus"
                               value="status" <?= ($_POST['send_to'] ?? '') === 'status' ? 'checked' : '' ?>
                               onchange="toggleRecipient()">
                        <label class="form-check-label small" for="toStatus">By Status</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_to" id="toAll"
                               value="all" <?= ($_POST['send_to'] ?? '') === 'all' ? 'checked' : '' ?>
                               onchange="toggleRecipient()">
                        <label class="form-check-label small" for="toAll">All Applicants</label>
                    </div>
                </div>

                <!-- One applicant dropdown -->
                <div id="recipientOne">
                    <select name="applicant_id" class="form-select">
                        <option value="">— Select Applicant —</option>
                        <?php foreach ($allApplicants as $ap): ?>
                        <option value="<?= $ap['id'] ?>"
                            <?= ((int)($_POST['applicant_id'] ?? $preApplicantId) === $ap['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ap['firstname'] . ' ' . $ap['lastname'] . ' — ' . $ap['email'] . ' (' . ucwords(str_replace('_',' ',$ap['status'])) . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status filter dropdown -->
                <div id="recipientStatus" style="display:none;">
                    <select name="status_filter" class="form-select">
                        <option value="">— Select Status —</option>
                        <?php foreach ($statusOptions as $s): ?>
                        <option value="<?= $s ?>" <?= ($_POST['status_filter'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', $s)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Subject -->
            <div class="mb-3">
                <label class="form-label fw-semibold small">Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control"
                       placeholder="e.g. Interview Schedule for [Position Name]"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
            </div>

            <!-- Quick templates -->
            <div class="mb-2">
                <label class="form-label fw-semibold small">Quick Templates</label>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="useTemplate('interview')">
                        <i class="bi bi-calendar-event me-1"></i>Interview Invite
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="useTemplate('passed')">
                        <i class="bi bi-check-circle me-1"></i>Passed / Accepted
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="useTemplate('regret')">
                        <i class="bi bi-x-circle me-1"></i>Regret Letter
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="useTemplate('hired')">
                        <i class="bi bi-person-check me-1"></i>Job Offer
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="useTemplate('docs')">
                        <i class="bi bi-file-earmark me-1"></i>Document Request
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="mb-4">
                <label class="form-label fw-semibold small">Message <span class="text-danger">*</span></label>
                <textarea name="body" id="bodyArea" class="form-control" rows="10"
                          placeholder="Type your message here…" required><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                <div class="form-text">Plain text only. The applicant will see this in their Inbox.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-send px-4 py-2">
                    <i class="bi bi-send me-2"></i>Send Email
                </button>
                <a href="<?= SITE_URL ?>mailer/dashboard.php" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleRecipient() {
    const val = document.querySelector('input[name="send_to"]:checked').value;
    document.getElementById('recipientOne').style.display    = val === 'one'    ? '' : 'none';
    document.getElementById('recipientStatus').style.display = val === 'status' ? '' : 'none';
}

const templates = {
    interview: {
        subject: 'Interview Invitation — [Position Name]',
        body:
`Dear [Applicant Name],

We are pleased to inform you that you have been shortlisted for the [Position Name] position at our company.

You are invited to attend an interview on:
  Date    : [Date]
  Time    : [Time]
  Venue   : [Location / Online Link]
  Mode    : [In-Person / Online]

Please confirm your attendance by replying to this email.

We look forward to speaking with you.

Best regards,
HR Department`
    },
    passed: {
        subject: 'Application Update — You Have Passed',
        body:
`Dear [Applicant Name],

Congratulations! We are pleased to inform you that you have successfully passed your interview for the [Position Name] position.

We will be in touch shortly with the next steps.

Best regards,
HR Department`
    },
    regret: {
        subject: 'Application Update — [Position Name]',
        body:
`Dear [Applicant Name],

Thank you for your interest in the [Position Name] position and for taking the time to apply.

After careful consideration, we regret to inform you that we have chosen to move forward with other candidates whose qualifications more closely match our current needs.

We encourage you to apply for future openings. Thank you again for your time.

Best regards,
HR Department`
    },
    hired: {
        subject: 'Congratulations! Job Offer — [Position Name]',
        body:
`Dear [Applicant Name],

We are delighted to offer you the position of [Position Name] with our company.

Please find the details below:
  Start Date      : [Start Date]
  Department      : [Department]
  Employment Type : [Full-Time / Probationary]
  Salary          : [Salary]

Kindly log in to your HRMS employee account to view your full contract.

Welcome to the team!

Best regards,
HR Department`
    },
    docs: {
        subject: 'Document Requirements — [Position Name]',
        body:
`Dear [Applicant Name],

Congratulations on your application progress!

To proceed further, please prepare and submit the following documents:
  1. 1 copy of valid government-issued ID
  2. Resume / Curriculum Vitae
  3. NBI Clearance
  4. SSS / PhilHealth / Pag-IBIG ID or Number
  5. TIN Number
  6. Birth Certificate (PSA copy)
  7. Transcript of Records / Diploma

You may upload your documents in your Applicant Portal under "My Documents".

If you have any questions, please reply to this email.

Best regards,
HR Department`
    }
};

function useTemplate(name) {
    const t = templates[name];
    if (!t) return;
    document.querySelector('input[name="subject"]').value = t.subject;
    document.getElementById('bodyArea').value = t.body;
}

// Run once on load to set correct visibility
toggleRecipient();
</script>
</body>
</html>
