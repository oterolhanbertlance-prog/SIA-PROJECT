<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
ensure_optional_modules();
$user = require_login();
require_permission('mailer.manage');
$title = 'Mailer';
$activePage = 'mailer';
$db = db_finance();

if (is_post()) {
    verify_csrf();
    $recipient = trim((string) ($_POST['recipient'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
        flash('error', 'Enter a valid recipient, subject, and message.');
    } else {
        $stmt = $db->prepare('INSERT INTO mail_queue (recipient, subject, body, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$recipient, $subject, $body, $user['id']]);
        audit('create', 'mailer', 'mail_queue', (string) $db->lastInsertId(), null, ['recipient' => $recipient, 'subject' => $subject]);
        flash('success', 'Message queued locally. Configure your XAMPP mail transport before sending externally.');
    }
    redirect('mailer.php');
}

$messages = $db->query('SELECT id, recipient, subject, status, created_at, sent_at FROM mail_queue ORDER BY id DESC LIMIT 12')->fetchAll();
$counts = ['queued' => 0, 'sent' => 0, 'failed' => 0];
foreach ($db->query('SELECT status, COUNT(*) total FROM mail_queue GROUP BY status')->fetchAll() as $row) $counts[$row['status']] = (int) $row['total'];
require __DIR__ . '/includes/header.php';
?>
<section class="hero-banner compact-banner">
    <div><div class="eyebrow">OFFLINE COMMUNICATIONS</div><h2>Mailer queue for finance operations</h2><p>Prepare payroll notices, payment reminders, and close updates locally before your mail transport is enabled.</p></div>
    <span class="offline-pill light-pill"><span class="status-dot"></span> QUEUE-FIRST</span>
</section>
<div class="summary-strip">
    <div><span>Queued</span><strong><?= number_format($counts['queued']) ?></strong></div>
    <div><span>Sent</span><strong><?= number_format($counts['sent']) ?></strong></div>
    <div><span>Failed</span><strong><?= number_format($counts['failed']) ?></strong></div>
    <div><span>Mode</span><strong>Local only</strong></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-5"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">NEW MESSAGE</div><h3>Queue an email</h3></div><span class="status-badge warning">Draft safely</span></div>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label">Recipient<input class="form-control" type="email" name="recipient" placeholder="recipient@company.com" required></label>
            <label class="form-label mt-3">Subject<input class="form-control" name="subject" placeholder="Payroll update · August 2026" required></label>
            <label class="form-label mt-3">Message<textarea class="form-control" name="body" rows="7" placeholder="Write the message to send..." required></textarea></label>
            <div class="form-note mt-3"><span class="bi bi-shield-check"></span><span>No message leaves this computer until you configure a local mail transport.</span></div>
            <button class="btn btn-primary mt-3" type="submit"><span class="bi bi-plus-lg"></span> Add to queue</button>
        </form>
    </section></div>
    <div class="col-xl-7"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">AUDITABLE OUTBOX</div><h3>Recent messages</h3></div><span class="subtle-label">Latest 12</span></div>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Recipient</th><th>Subject</th><th>Created</th><th>Status</th></tr></thead><tbody>
        <?php if (!$messages): ?><tr><td colspan="4"><div class="empty-state"><span class="bi bi-envelope"></span><strong>No messages queued</strong><small>Finance notices will appear here.</small></div></td></tr><?php endif; ?>
        <?php foreach ($messages as $message): ?><tr><td><?= e($message['recipient']) ?></td><td><strong><?= e($message['subject']) ?></strong></td><td><?= e(date_label($message['created_at'])) ?></td><td><span class="status-badge <?= $message['status'] === 'failed' ? 'danger' : ($message['status'] === 'sent' ? 'success' : 'warning') ?>"><?= e(ucfirst($message['status'])) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>