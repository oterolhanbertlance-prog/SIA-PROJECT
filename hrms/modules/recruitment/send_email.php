<?php
/**
 * HR — Send Email to Applicant(s)
 * Integrated into the main HRMS — no separate mailer login needed.
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/mailer.php';
requireRole('hr','admin');

$db   = getDB();
$user = currentUser();
$msg  = '';

// Pre-fill applicant from query string (e.g. coming from view_applicant)
$preId = (int)($_GET['applicant_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Invalid form submission.</div>';
    } else {
        $sendTo       = $_POST['send_to'] ?? 'one';   // 'one' | 'status' | 'all'
        $applicantId  = (int)($_POST['applicant_id'] ?? 0);
        $statusFilter = sanitize($_POST['status_filter'] ?? '');
        $subject      = sanitize($_POST['subject'] ?? '');
        $body         = trim($_POST['body'] ?? '');
        $senderName   = sanitize($_POST['sender_name'] ?? '');
        $senderEmail  = sanitize($_POST['sender_email'] ?? '');

        $errors = [];
        if (!$subject) $errors[] = 'Subject is required.';
        if (!$body)    $errors[] = 'Message body is required.';
        if ($sendTo === 'one' && !$applicantId) $errors[] = 'Please select an applicant.';

        if (empty($errors)) {
            // Gather targets
            $targets = [];
            if ($sendTo === 'one') {
                $s = $db->prepare("SELECT * FROM applicants WHERE id = ?");
                $s->execute([$applicantId]);
                $t = $s->fetch(); if ($t) $targets = [$t];
            } elseif ($sendTo === 'status' && $statusFilter) {
                $s = $db->prepare("SELECT * FROM applicants WHERE status = ?");
                $s->execute([$statusFilter]);
                $targets = $s->fetchAll();
            } else {
                $targets = $db->query("SELECT * FROM applicants")->fetchAll();
            }

            if (empty($targets)) {
                $msg = '<div class="alert alert-warning">No applicants matched your selection.</div>';
            } else {
                $insertStmt = $db->prepare(
                    "INSERT INTO hr_emails (applicant_id, sender_name, sender_email, subject, body, sent_at, is_read)
                     VALUES (?, ?, ?, ?, ?, NOW(), 0)"
                );
                foreach ($targets as $target) {
                    $insertStmt->execute([$target['id'], $senderName, $senderEmail, $subject, $body]);
                    sendSmtpEmail($target['email'], $subject, $body);

                    // In-app notification if applicant has a portal account
                    if (!empty($target['applicant_account_id'])) {
                        try {
                            notifyApplicant(
                                $target['applicant_account_id'],
                                '📧 New Email from HR: ' . $subject,
                                substr(strip_tags($body), 0, 200),
                                'info',
                                SITE_URL . 'applicant/inbox.php'
                            );
                        } catch (Exception $e) { /* notification table may not be ready */ }
                    }
                }
                $cnt = count($targets);
                auditLog('INSERT', 'Recruitment', "HR sent email \"$subject\" to $cnt applicant(s)");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Email sent to ' . $cnt . ' applicant' . ($cnt !== 1 ? 's' : '') . '! They can read it in their Applicant Portal inbox.</div>';
                $preId = 0; // clear pre-fill after send
            }
        } else {
            $msg = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        }
    }
}

// All applicants for the dropdown
$allApplicants = $db->query(
    "SELECT a.id, a.firstname, a.lastname, a.email, a.applicant_code, a.status, j.title AS job_title
     FROM applicants a LEFT JOIN job_postings j ON a.job_id = j.id
     ORDER BY a.firstname, a.lastname"
)->fetchAll();

$statusOptions = ['pending','initial_interview','final_interview','passed','failed','hired','withdrawn'];

$hrName  = trim(($user['name'] ?? '') ?: (($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')));
$hrEmail = $user['email'] ?? '';

$pageTitle = 'Send Email to Applicant';
include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-envelope-fill me-2 text-primary"></i>Send Email to Applicant</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="applicants.php">Applicants</a></li>
            <li class="breadcrumb-item active">Send Email</li>
        </ol></nav>
    </div>
    <a href="<?= SITE_URL ?>modules/recruitment/replies.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-reply-all me-1"></i>View Applicant Replies
    </a>
</div>

<?= $msg ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold"><i class="bi bi-send me-2"></i>Compose Email</div>
            <div class="card-body p-4">
                <form method="POST" id="sendEmailForm" onsubmit="return swalSendEmail(this);">
                    <?= csrfField() ?>

                    <!-- Send To -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Send To</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_to" id="s1" value="one" checked onchange="toggleTarget(this.value)">
                                <label class="form-check-label" for="s1">One Applicant</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_to" id="s2" value="status" onchange="toggleTarget(this.value)">
                                <label class="form-check-label" for="s2">By Status</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_to" id="s3" value="all" onchange="toggleTarget(this.value)">
                                <label class="form-check-label" for="s3">All Applicants</label>
                            </div>
                        </div>
                    </div>

                    <!-- One applicant -->
                    <div id="targetOne" class="mb-3">
                        <label class="form-label fw-semibold small">Applicant <span class="text-danger">*</span></label>
                        <select name="applicant_id" class="form-select">
                            <option value="">— Select applicant —</option>
                            <?php foreach ($allApplicants as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($preId == $a['id'] || (($_POST['applicant_id'] ?? 0) == $a['id'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) ?>
                                (<?= htmlspecialchars($a['applicant_code'] ?? $a['email']) ?>)
                                — <?= htmlspecialchars($a['job_title'] ?? 'General') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- By status -->
                    <div id="targetStatus" class="mb-3 d-none">
                        <label class="form-label fw-semibold small">Status Filter</label>
                        <select name="status_filter" class="form-select">
                            <option value="">— Select status —</option>
                            <?php foreach ($statusOptions as $s): ?>
                            <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">From (Sender Name)</label>
                            <input type="text" name="sender_name" class="form-control"
                                   value="<?= htmlspecialchars($_POST['sender_name'] ?? $hrName) ?>"
                                   placeholder="HR Department">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sender Email (optional)</label>
                            <input type="email" name="sender_email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['sender_email'] ?? $hrEmail) ?>"
                                   placeholder="hr@company.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   placeholder="e.g. Interview Schedule Confirmation">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Message <span class="text-danger">*</span></label>
                            <textarea name="body" class="form-control" rows="10" required
                                      placeholder="Type your message here..."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                            <div class="form-text">Applicants can reply to this message from their portal. Replies appear in <a href="replies.php">Applicant Replies</a>.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4" id="sendEmailBtn">
                            <i class="bi bi-send me-1"></i>Send Email
                        </button>
                        <a href="applicants.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Recent Sent ────────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Recently Sent</div>
            <div class="card-body p-0">
                <?php
                $recent = $db->query(
                    "SELECT e.*, a.firstname, a.lastname
                     FROM hr_emails e JOIN applicants a ON e.applicant_id = a.id
                     ORDER BY e.sent_at DESC LIMIT 10"
                )->fetchAll();
                ?>
                <?php if (empty($recent)): ?>
                <div class="text-center text-muted py-4 small">No emails sent yet.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recent as $em): ?>
                    <li class="list-group-item py-2 px-3">
                        <div class="fw-semibold small text-truncate"><?= htmlspecialchars($em['subject']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;">
                            To: <?= htmlspecialchars($em['firstname'] . ' ' . $em['lastname']) ?>
                            <?php if (!$em['is_read']): ?>
                            <span class="badge bg-warning-subtle text-warning border ms-1" style="font-size:.65rem;">Unread</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:.7rem;"><?= formatDateTime($em['sent_at']) ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTarget(val) {
    document.getElementById('targetOne').classList.toggle('d-none',    val !== 'one');
    document.getElementById('targetStatus').classList.toggle('d-none', val !== 'status');
}
</script>
<script>
function swalSendEmail(form) {
    var radios = form.querySelectorAll('[name="target"]');
    var targetVal = 'one'; radios.forEach(function(r){ if(r.checked) targetVal = r.value; });
    var labels = {one:'one applicant', status:'applicants by status', all:'ALL applicants'};
    var subj = (form.querySelector('[name="subject"]')||{}).value || '(no subject)';
    Swal.fire({
        title: 'Send Email?',
        html: 'Sending <strong>' + subj + '</strong> to <span class="text-primary fw-semibold">' + (labels[targetVal]||'applicants') + '</span>.<br><small class="text-muted">This cannot be undone.</small>',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#4361ee', cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-send"></i> Send Now', cancelButtonText: 'Review Again'
    }).then(function(r){ if(r.isConfirmed){ document.getElementById('sendEmailBtn').disabled=true; document.getElementById('sendEmailForm').submit(); }});
    return false;
}
</script>
<?php include '../../includes/footer.php'; ?>
