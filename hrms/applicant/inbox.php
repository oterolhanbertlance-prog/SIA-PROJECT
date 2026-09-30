<?php
/**
 * Applicant Inbox — HR emails + reply feature.
 */
require_once '../config/database.php';
require_once '../config/applicant_session.php';
require_once '../includes/functions.php';
requireApplicantLogin();

$db        = getDB();
$applicant = currentApplicant();

// Fetch all applicant records linked to this account
$myApplicantIds = [];
$stmtIds = $db->prepare("SELECT id FROM applicants WHERE applicant_account_id = ?");
$stmtIds->execute([$applicant['id']]);
foreach ($stmtIds->fetchAll() as $r) {
    $myApplicantIds[] = (int)$r['id'];
}

// ── Handle reply submission ──────────────────────────────────────────────
$replyMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $replyMsg = '<div class="alert alert-danger">Invalid form submission. Please try again.</div>';
    } else {
        $hrEmailId = (int)($_POST['hr_email_id'] ?? 0);
        $replyBody = trim($_POST['reply_body'] ?? '');
        $replySubj = trim($_POST['reply_subject'] ?? '');

        // Validate the email belongs to this applicant
        $valid = false;
        if (!empty($myApplicantIds) && $hrEmailId) {
            $pl  = implode(',', array_fill(0, count($myApplicantIds), '?'));
            $chk = $db->prepare("SELECT id, applicant_id FROM hr_emails WHERE id = ? AND applicant_id IN ($pl)");
            $chk->execute(array_merge([$hrEmailId], $myApplicantIds));
            $valid = $chk->fetch();
        }

        if (!$valid) {
            $replyMsg = '<div class="alert alert-danger">Invalid reply target.</div>';
        } elseif (!$replyBody) {
            $replyMsg = '<div class="alert alert-danger">Reply body cannot be empty.</div>';
        } else {
            try {
                $db->prepare(
                    "INSERT INTO applicant_replies (hr_email_id, applicant_id, subject, body, is_read_hr)
                     VALUES (?, ?, ?, ?, 0)"
                )->execute([
                    $hrEmailId,
                    $valid['applicant_id'],
                    $replySubj ?: 'Re: ' . ($_POST['original_subject'] ?? ''),
                    $replyBody,
                ]);
                $replyMsg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Your reply has been sent to HR.</div>';
            } catch (Exception $e) {
                $replyMsg = '<div class="alert alert-warning">Reply saved but could not be recorded. Please run the latest migration SQL. Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// ── Load emails ──────────────────────────────────────────────────────────
$emails = [];
if (!empty($myApplicantIds)) {
    $pl   = implode(',', array_fill(0, count($myApplicantIds), '?'));
    $stmt = $db->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM applicant_replies r WHERE r.hr_email_id = e.id) AS reply_count
         FROM hr_emails e
         WHERE e.applicant_id IN ($pl)
         ORDER BY e.sent_at DESC"
    );
    $stmt->execute($myApplicantIds);
    $emails = $stmt->fetchAll();

    // Mark all as read
    $db->prepare("UPDATE hr_emails SET is_read = 1 WHERE applicant_id IN ($pl)")->execute($myApplicantIds);
}

// Fallback: try matching by email address
if (empty($emails) && $applicant['email']) {
    $stmt = $db->prepare(
        "SELECT e.*, 0 AS reply_count FROM hr_emails e
         JOIN applicants a ON e.applicant_id = a.id
         WHERE a.email = ? ORDER BY e.sent_at DESC"
    );
    $stmt->execute([$applicant['email']]);
    $emails = $stmt->fetchAll();
}

// ── Open individual email ────────────────────────────────────────────────
$openEmail    = null;
$emailReplies = [];
if (isset($_GET['id'])) {
    $emailId = (int)$_GET['id'];
    foreach ($emails as $em) {
        if ((int)$em['id'] === $emailId) {
            $openEmail = $em;
            try {
                $rStmt = $db->prepare("SELECT * FROM applicant_replies WHERE hr_email_id = ? ORDER BY sent_at ASC");
                $rStmt->execute([$emailId]);
                $emailReplies = $rStmt->fetchAll();
            } catch (Exception $e) { $emailReplies = []; }
            break;
        }
    }
}

$unreadCount = count(array_filter($emails, fn($e) => !$e['is_read']));
$pageTitle   = 'My Inbox';
include '../includes/applicant_header.php';
?>

<?= $replyMsg ?>

<div class="alert alert-light border d-flex align-items-center justify-content-between py-2 mb-3">
    <span class="text-muted small"><i class="bi bi-arrow-clockwise me-1"></i>Auto-refreshes in <strong id="inbox-countdown">30</strong>s</span>
    <a href="<?= SITE_URL ?>applicant/inbox.php" class="btn btn-sm btn-outline-primary py-0">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh Now
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-inbox me-2 text-primary"></i>My Inbox
        <?php if ($unreadCount > 0): ?>
        <span class="badge bg-danger ms-2" style="font-size:.7rem;"><?= $unreadCount ?> unread</span>
        <?php endif; ?>
    </h4>
    <a href="<?= SITE_URL ?>applicant/dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php if ($openEmail): ?>
<!-- ── Email detail view ────────────────────────────────────────────────── -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($openEmail['subject']) ?></h5>
            <div class="text-muted small mt-1">
                <i class="bi bi-person-fill me-1"></i>From: <strong><?= htmlspecialchars($openEmail['sender_name']) ?></strong>
                <?php if ($openEmail['sender_email']): ?>
                &lt;<?= htmlspecialchars($openEmail['sender_email']) ?>&gt;
                <?php endif; ?>
                &nbsp;&middot;&nbsp;
                <i class="bi bi-clock me-1"></i><?= formatDateTime($openEmail['sent_at']) ?>
            </div>
        </div>
        <a href="<?= SITE_URL ?>applicant/inbox.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>All Emails
        </a>
    </div>
    <div class="card-body p-4">
        <pre style="white-space:pre-wrap;font-family:'Inter',sans-serif;font-size:.9rem;line-height:1.7;"><?= htmlspecialchars($openEmail['body']) ?></pre>
    </div>
</div>

<!-- ── Previous replies in this thread ─────────────────────────────────── -->
<?php if (!empty($emailReplies)): ?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header fw-semibold small"><i class="bi bi-chat-left-text me-2"></i>Your Previous Replies</div>
    <div class="card-body p-0">
        <?php foreach ($emailReplies as $rep): ?>
        <div class="px-4 py-3 border-bottom">
            <div class="d-flex justify-content-between text-muted small mb-1">
                <span><i class="bi bi-person-fill me-1 text-success"></i><strong>You</strong> replied</span>
                <span><?= formatDateTime($rep['sent_at']) ?></span>
            </div>
            <div class="bg-success-subtle rounded p-3 small" style="white-space:pre-wrap;line-height:1.6;">
                <?= nl2br(htmlspecialchars($rep['body'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Reply form ──────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold small"><i class="bi bi-reply me-2"></i>Reply to HR</div>
    <div class="card-body">
        <form method="POST" id="replyForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="hr_email_id" value="<?= $openEmail['id'] ?>">
            <input type="hidden" name="original_subject" value="<?= htmlspecialchars($openEmail['subject']) ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Subject</label>
                <input type="text" name="reply_subject" class="form-control"
                       value="Re: <?= htmlspecialchars($openEmail['subject']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Your Message <span class="text-danger">*</span></label>
                <textarea name="reply_body" class="form-control" rows="6" required
                          placeholder="Type your reply here..."></textarea>
            </div>
            <button type="submit" id="replyBtn" class="btn btn-success">
                <i class="bi bi-send me-1"></i>Send Reply
            </button>
            <a href="<?= SITE_URL ?>applicant/inbox.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php elseif (empty($emails)): ?>
<!-- ── Empty state ──────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-3 mb-3 text-muted"><i class="bi bi-inbox"></i></div>
        <h5 class="text-muted fw-semibold">No emails yet</h5>
        <p class="text-muted small">HR will send you important updates here — interview schedules, job offers, and more.</p>
        <a href="<?= SITE_URL ?>applicant/dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-briefcase me-1"></i>View My Applications
        </a>
    </div>
</div>

<?php else: ?>
<!-- ── Email list ────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="list-group list-group-flush rounded-3">
            <?php foreach ($emails as $em): ?>
            <a href="<?= SITE_URL ?>applicant/inbox.php?id=<?= $em['id'] ?>"
               class="list-group-item list-group-item-action py-3 px-4 <?= !$em['is_read'] ? 'bg-primary-subtle' : '' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1 me-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?php if (!$em['is_read']): ?>
                            <span class="badge bg-primary rounded-pill" style="font-size:.65rem;min-width:8px;min-height:8px;padding:4px;">&nbsp;</span>
                            <?php endif; ?>
                            <span class="fw-semibold <?= !$em['is_read'] ? '' : 'text-secondary' ?>" style="font-size:.925rem;">
                                <?= htmlspecialchars($em['subject']) ?>
                            </span>
                            <?php if (!empty($em['reply_count']) && $em['reply_count'] > 0): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.68rem;">
                                <i class="bi bi-reply me-1"></i><?= $em['reply_count'] ?> repl<?= $em['reply_count'] == 1 ? 'y' : 'ies' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small text-truncate" style="max-width:500px;">
                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($em['sender_name']) ?>
                            &nbsp;·&nbsp;
                            <?= htmlspecialchars(substr(strip_tags($em['body']), 0, 100)) ?>…
                        </div>
                    </div>
                    <div class="text-muted small text-nowrap ms-2">
                        <?= formatDateTime($em['sent_at']) ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    var t = 30;
    var el = document.getElementById('inbox-countdown');
    function tick() {
        if (el) el.textContent = t;
        if (t <= 0) { location.reload(); return; }
        t--;
        setTimeout(tick, 1000);
    }
    tick();
})();

var replyForm = document.getElementById('replyForm');
if (replyForm) {
    replyForm.addEventListener('submit', function() {
        var btn = document.getElementById('replyBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
        }
    });
}
</script>
<?php include '../includes/applicant_footer.php'; ?>
