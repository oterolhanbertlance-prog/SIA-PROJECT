<?php
/**
 * HR — View applicant replies to HR emails.
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('hr','admin');

$db = getDB();

// Mark a reply as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $db->prepare("UPDATE applicant_replies SET is_read_hr = 1 WHERE id = ?")
       ->execute([(int)$_GET['mark_read']]);
    header('Location: replies.php');
    exit;
}

// Fetch all replies with context
try {
    $replies = $db->query(
        "SELECT r.*, e.subject AS original_subject, e.sender_name,
                a.firstname, a.lastname, a.email, a.applicant_code,
                j.title AS job_title
         FROM applicant_replies r
         JOIN hr_emails e ON r.hr_email_id = e.id
         JOIN applicants a ON r.applicant_id = a.id
         LEFT JOIN job_postings j ON a.job_id = j.id
         ORDER BY r.is_read_hr ASC, r.sent_at DESC"
    )->fetchAll();
} catch (Exception $e) {
    $replies = [];
}

$unreadCount = count(array_filter($replies, fn($r) => !$r['is_read_hr']));
$pageTitle = 'Applicant Replies';
include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-reply-all me-2 text-primary"></i>Applicant Replies
            <?php if ($unreadCount > 0): ?>
            <span class="badge bg-danger ms-2"><?= $unreadCount ?> new</span>
            <?php endif; ?>
        </h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Applicant Replies</li>
        </ol></nav>
    </div>
    <a href="<?= SITE_URL ?>modules/recruitment/send_email.php" class="btn btn-primary btn-sm">
        <i class="bi bi-envelope me-1"></i>Send New Email
    </a>
</div>

<?php if (empty($replies)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <div class="display-4 mb-3"><i class="bi bi-reply"></i></div>
        <h5>No replies yet</h5>
        <p class="small">Applicants can reply to your HR emails from their portal. Replies will appear here.</p>
        <a href="send_email.php" class="btn btn-primary btn-sm">
            <i class="bi bi-envelope me-1"></i>Send an Email
        </a>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($replies as $r): ?>
    <div class="card border-0 shadow-sm <?= !$r['is_read_hr'] ? 'border-start border-4 border-primary' : '' ?>">
        <div class="card-body py-3 px-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <?php if (!$r['is_read_hr']): ?>
                    <span class="badge bg-primary me-1" style="font-size:.65rem;">NEW</span>
                    <?php endif; ?>
                    <span class="fw-bold"><?= htmlspecialchars($r['subject']) ?></span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="text-muted small"><?= formatDateTime($r['sent_at']) ?></span>
                    <?php if (!$r['is_read_hr']): ?>
                    <a href="?mark_read=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;">
                        <i class="bi bi-check2 me-1"></i>Mark Read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="small text-muted mb-2">
                <i class="bi bi-person me-1"></i>
                <strong><?= htmlspecialchars($r['firstname'] . ' ' . $r['lastname']) ?></strong>
                &lt;<?= htmlspecialchars($r['email']) ?>&gt;
                &nbsp;·&nbsp;
                <i class="bi bi-hash me-1"></i><?= htmlspecialchars($r['applicant_code'] ?? '—') ?>
                <?php if ($r['job_title']): ?>
                &nbsp;·&nbsp;<i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($r['job_title']) ?>
                <?php endif; ?>
            </div>
            <div class="small text-muted mb-2">
                <i class="bi bi-envelope me-1"></i>In reply to: <em><?= htmlspecialchars($r['original_subject']) ?></em>
                (sent by <?= htmlspecialchars($r['sender_name']) ?>)
            </div>
            <div class="p-3 bg-light rounded small" style="white-space:pre-wrap;line-height:1.6;">
                <?= nl2br(htmlspecialchars($r['body'])) ?>
            </div>
            <div class="mt-2">
                <a href="send_email.php?applicant_id=<?= $r['applicant_id'] ?>" class="btn btn-sm btn-outline-success py-0 px-2">
                    <i class="bi bi-reply me-1"></i>Reply
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
