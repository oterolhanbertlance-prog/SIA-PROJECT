<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$title = 'Settings';
$activePage = 'settings';
$connections = [
    ['name' => 'Finance database', 'key' => 'finance', 'database' => connection_config('finance')['name']],
    ['name' => 'HRMS database', 'key' => 'hrms', 'database' => connection_config('hrms')['name']],
    ['name' => 'Milk Tea POS database', 'key' => 'pos', 'database' => connection_config('pos')['name']],
];
require __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar"><div><div class="eyebrow">LOCAL CONFIGURATION</div><p class="toolbar-copy">Connection names are read from <code>config/local.php</code>. Passwords never reach the browser.</p></div><span class="offline-pill"><span class="status-dot"></span> NO CLOUD SERVICES</span></div>
<div class="row g-4 mt-1">
    <div class="col-lg-7"><section class="panel-card"><div class="panel-heading"><div><div class="eyebrow">DATABASE CONNECTIONS</div><h3>Three local PDO connections</h3></div></div><?php foreach ($connections as $connection): $state = test_connection($connection['key']); ?><div class="settings-row"><div class="settings-icon"><span class="bi bi-database"></span></div><div><strong><?= e($connection['name']) ?></strong><small>127.0.0.1 / <?= e($connection['database']) ?></small></div><span class="status-badge <?= $state['ok'] ? 'success' : 'warning' ?> ms-auto"><?= e($state['ok'] ? 'Connected' : 'Check config') ?></span></div><?php endforeach; ?></section></div>
    <div class="col-lg-5"><section class="panel-card"><div class="panel-heading"><div><div class="eyebrow">SESSION & PRIVACY</div><h3>Local by design</h3></div></div><div class="privacy-list"><div><span class="bi bi-shield-lock"></span><span>PHP server session only<small>Expires after <?= e((string) (FINANCE_SESSION_TIMEOUT / 60)) ?> minutes of inactivity</small></span></div><div><span class="bi bi-cookie"></span><span>No persistent tracking cookies<small>No remember-me or analytics storage</small></span></div><div><span class="bi bi-file-earmark-lock"></span><span>Prepared statements<small>Source databases are read-only integration targets</small></span></div></div></section></div>
</div>
<section class="panel-card mt-4"><div class="panel-heading"><div><div class="eyebrow">INSTALLATION</div><h3>Backup and restore</h3></div></div><div class="callout"><span class="bi bi-info-circle"></span><div><strong>Use phpMyAdmin for the first import.</strong><p>Import <code>database/finance.sql</code> into MariaDB, then configure the three local database names in <code>config/local.php</code>. Keep the Finance database separate from HRMS and Milk Tea POS.</p></div></div><div class="settings-row"><div class="settings-icon"><span class="bi bi-download"></span></div><div><strong>Local backup</strong><small>Use phpMyAdmin’s Export function to create a dated SQL backup of finance_system.</small></div><span class="status-badge success ms-auto">Available locally</span></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>