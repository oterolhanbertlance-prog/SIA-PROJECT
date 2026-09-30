<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole('admin');

$pageTitle = 'SMTP Settings';

$db = getDB();
$msg = '';
$msgType = 'success';

// Ensure table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS smtp_settings (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        smtp_host     VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
        smtp_port     SMALLINT     NOT NULL DEFAULT 587,
        smtp_username VARCHAR(255) NOT NULL DEFAULT '',
        smtp_password VARCHAR(255) NOT NULL DEFAULT '',
        smtp_from     VARCHAR(255) NOT NULL DEFAULT '',
        smtp_name     VARCHAR(150) NOT NULL DEFAULT 'HRMS',
        smtp_secure   ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
        smtp_auth     TINYINT(1)   NOT NULL DEFAULT 1,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default row
    $db->exec("INSERT IGNORE INTO smtp_settings (id, smtp_host, smtp_port) VALUES (1, 'smtp.gmail.com', 587)");
} catch (Throwable $e) {}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    try {
        $host     = sanitize($_POST['smtp_host'] ?? '');
        $port     = (int)($_POST['smtp_port'] ?? 587);
        $user     = sanitize($_POST['smtp_username'] ?? '');
        $pass     = $_POST['smtp_password'] ?? '';   // not sanitized — may contain special chars
        $from     = sanitize($_POST['smtp_from'] ?? '');
        $name     = sanitize($_POST['smtp_name'] ?? 'HRMS');
        $secure   = in_array($_POST['smtp_secure'] ?? 'tls', ['tls','ssl','none']) ? $_POST['smtp_secure'] : 'tls';
        $auth     = isset($_POST['smtp_auth']) ? 1 : 0;

        if (!$host) throw new Exception('SMTP host is required.');

        // Keep existing password if field left blank
        if (empty($pass)) {
            $db->prepare("UPDATE smtp_settings SET smtp_host=?,smtp_port=?,smtp_username=?,smtp_from=?,smtp_name=?,smtp_secure=?,smtp_auth=? WHERE id=1")
               ->execute([$host,$port,$user,$from,$name,$secure,$auth]);
        } else {
            $db->prepare("UPDATE smtp_settings SET smtp_host=?,smtp_port=?,smtp_username=?,smtp_password=?,smtp_from=?,smtp_name=?,smtp_secure=?,smtp_auth=? WHERE id=1")
               ->execute([$host,$port,$user,$pass,$from,$name,$secure,$auth]);
        }
        auditLog('UPDATE', 'settings', 'SMTP settings updated');
        $msg = 'SMTP settings saved successfully.';
    } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
}

// Load current settings
try {
    $settings = $db->query("SELECT * FROM smtp_settings WHERE id=1")->fetch();
} catch (Throwable $e) { $settings = []; }

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h4><i class="bi bi-gear-fill me-2"></i>SMTP Settings</h4>
    <p class="text-muted small mb-0">Configure the outgoing mail server used for sending emails to applicants and employees.</p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-auto-dismiss">
    <i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-envelope-at me-2"></i>Outgoing Mail Server (SMTP)</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" name="smtp_host" class="form-control" required
                                   value="<?= htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com') ?>"
                                   placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="smtp_port" class="form-control"
                                   value="<?= $settings['smtp_port'] ?? 587 ?>" min="1" max="65535">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control" autocomplete="off"
                                   value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                                   placeholder="your_email@gmail.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control" autocomplete="new-password"
                                   placeholder="Leave blank to keep current password">
                            <div class="form-text">For Gmail, use an <strong>App Password</strong> (not your login password).</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">From Email Address</label>
                            <input type="email" name="smtp_from" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_from'] ?? '') ?>"
                                   placeholder="hr@yourcompany.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">From Name</label>
                            <input type="text" name="smtp_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['smtp_name'] ?? 'HRMS') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Encryption</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= ($settings['smtp_secure']??'tls')==='tls'?'selected':'' ?>>TLS (Recommended)</option>
                                <option value="ssl" <?= ($settings['smtp_secure']??'')==='ssl'?'selected':'' ?>>SSL</option>
                                <option value="none" <?= ($settings['smtp_secure']??'')==='none'?'selected':'' ?>>None</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="smtp_auth" id="smtpAuth" value="1"
                                       <?= ($settings['smtp_auth'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="smtpAuth">SMTP Authentication</label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header fw-semibold text-muted"><i class="bi bi-info-circle me-2"></i>Gmail Setup Guide</div>
            <div class="card-body small text-muted">
                <ol class="mb-0">
                    <li>Go to <strong>Google Account → Security</strong></li>
                    <li>Enable <strong>2-Step Verification</strong></li>
                    <li>Go to <strong>Security → App Passwords</strong></li>
                    <li>Create a new App Password for <em>Mail / Other (HRMS)</em></li>
                    <li>Copy the 16-character password and paste it above</li>
                    <li>Use <code>smtp.gmail.com</code>, port <code>587</code>, TLS encryption</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
