<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/IntegrationService.php';
$user = require_login();
$title = 'Integration';
$activePage = 'integration';
$resultMessage = null;
$connectionResults = [];

function optional_connection(string $system): ?PDO
{
    try {
        return match ($system) {
            'hrms' => db_hrms(),
            'pos' => db_pos(),
            default => null,
        };
    } catch (Throwable) {
        return null;
    }
}

$hrms = optional_connection('hrms');
$pos = optional_connection('pos');
$service = new IntegrationService(db_finance(), $hrms, $pos);
if (is_post()) {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'test_hrms') {
            $connectionResults['hrms'] = test_connection('hrms');
        } elseif ($action === 'test_pos') {
            $connectionResults['pos'] = test_connection('pos');
        } elseif (in_array($action, ['sync_hrms', 'sync_pos', 'sync_everything'], true)) {
            if ($action === 'sync_hrms') {
                $result = $service->syncHrms((int) $user['id'], $_POST['from'] ?: null, $_POST['to'] ?: null);
            } elseif ($action === 'sync_pos') {
                $result = $service->syncPos((int) $user['id'], $_POST['from'] ?: null, $_POST['to'] ?: null);
            } else {
                $result = $service->syncEverything((int) $user['id'], $_POST['from'] ?: null, $_POST['to'] ?: null);
            }
            $resultMessage = json_encode($result);
            audit('sync', 'integration', 'sync', $action, null, $result);
            flash('success', 'Synchronization finished. Imported records are protected by source-system idempotency keys.');
            redirect('integration.php');
        }
    } catch (Throwable $exception) {
        flash('error', format_error($exception));
        redirect('integration.php');
    }
}
$summary = $service->summary();
$logs = [];
try {
    $logs = db_finance()->query(
        'SELECT source_system, action, started_at, finished_at, imported_count, skipped_count, failed_count, message
         FROM integration_logs ORDER BY id DESC LIMIT 10'
    )->fetchAll();
} catch (Throwable) {
}
require __DIR__ . '/includes/header.php';
?>
<section class="hero-banner compact-banner">
    <div><div class="eyebrow">LOCAL DATA BRIDGE</div><h2>Finance ↕ Local MySQL ↕ Source systems</h2><p>Finance reads HRMS and Milk Tea POS records without changing their original databases.</p></div>
    <span class="offline-pill light-pill"><span class="status-dot"></span> OFFLINE ONLY</span>
</section>
<form method="post" class="panel-card sync-toolbar mb-4">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div><div class="eyebrow">OPTIONAL DATE WINDOW</div><strong>Sync all available local records</strong></div>
    <label>From <input type="date" name="from" class="form-control form-control-sm"></label>
    <label>To <input type="date" name="to" class="form-control form-control-sm"></label>
    <button class="btn btn-primary" name="action" value="sync_everything"><span class="bi bi-arrow-repeat"></span> Sync everything</button>
</form>
<div class="row g-4">
    <div class="col-xl-6">
        <section class="integration-card">
            <div class="integration-card-head"><div class="integration-logo hrms-logo">HR</div><div><div class="eyebrow">HUMAN RESOURCES</div><h3>HRMS</h3></div><span class="status-badge <?= $summary['hrms']['connection'] === 'Connected' ? 'success' : 'danger' ?>"><?= e($summary['hrms']['connection']) ?></span></div>
            <div class="connection-line"><span class="bi bi-database"></span><span>127.0.0.1 / <?= e(connection_config('hrms')['name']) ?></span></div>
            <div class="integration-stats">
                <div><strong><?= number_format($summary['hrms']['employees_available']) ?></strong><small>Employees available</small></div>
                <div><strong><?= number_format($summary['hrms']['payroll_available']) ?></strong><small>Payroll available</small></div>
                <div><strong><?= number_format($summary['hrms']['payroll_imported']) ?></strong><small>Payroll imported</small></div>
            </div>
            <div class="sync-meta"><span>Last sync</span><strong><?= e($summary['hrms']['last_sync'] ? date('M d, Y H:i', strtotime($summary['hrms']['last_sync'])) : 'Never') ?></strong></div>
            <div class="integration-actions">
                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-secondary btn-sm" name="action" value="test_hrms">Test connection</button></form>
                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-primary btn-sm" name="action" value="sync_hrms"><span class="bi bi-arrow-repeat"></span> Sync HRMS</button></form>
            </div>
            <?php if (!empty($connectionResults['hrms'])): ?><div class="connection-result <?= $connectionResults['hrms']['ok'] ? 'is-good' : 'is-bad' ?>"><?= e($connectionResults['hrms']['message']) ?></div><?php endif; ?>
        </section>
    </div>
    <div class="col-xl-6">
        <section class="integration-card">
            <div class="integration-card-head"><div class="integration-logo pos-logo">MT</div><div><div class="eyebrow">POINT OF SALE</div><h3>Milk Tea POS</h3></div><span class="status-badge <?= $summary['pos']['connection'] === 'Connected' ? 'success' : 'danger' ?>"><?= e($summary['pos']['connection']) ?></span></div>
            <div class="connection-line"><span class="bi bi-database"></span><span>127.0.0.1 / <?= e(connection_config('pos')['name']) ?></span></div>
            <div class="integration-stats">
                <div><strong><?= number_format($summary['pos']['transactions_available']) ?></strong><small>Transactions available</small></div>
                <div><strong><?= money($summary['pos']['sales_total']) ?></strong><small>Completed sales</small></div>
                <div><strong><?= number_format($summary['pos']['transactions_imported']) ?></strong><small>Imported in Finance</small></div>
            </div>
            <div class="sync-meta"><span>Pending</span><strong><?= number_format($summary['pos']['transactions_pending']) ?> transactions</strong></div>
            <div class="integration-actions">
                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-secondary btn-sm" name="action" value="test_pos">Test connection</button></form>
                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-primary btn-sm" name="action" value="sync_pos"><span class="bi bi-arrow-repeat"></span> Sync POS</button></form>
            </div>
            <?php if (!empty($connectionResults['pos'])): ?><div class="connection-result <?= $connectionResults['pos']['ok'] ? 'is-good' : 'is-bad' ?>"><?= e($connectionResults['pos']['message']) ?></div><?php endif; ?>
        </section>
    </div>
</div>
<div class="row g-4 mt-1">
  <div class="col-lg-6"><section class="panel-card h-100"><div class="panel-heading"><div><div class="eyebrow">BRANCH MASTER</div><h3>HRMS ↔ Finance ↔ POS</h3></div></div><p>Synchronizes branch code, address, city, GPS coordinates and source IDs into one Finance branch mapping.</p><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="btn btn-outline-primary" name="action" value="sync_branches">Sync branch mappings</button></form></section></div>
  <div class="col-lg-6"><section class="panel-card h-100"><div class="panel-heading"><div><div class="eyebrow">CONNECTED ACCOUNTS</div><h3>HRMS &amp; POS identities</h3></div></div><p>Creates Finance permission shadows for active HRMS/POS staff. Their source username/password remains the credential used to sign in.</p><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="btn btn-outline-primary" name="action" value="sync_accounts">Sync connected accounts</button></form></section></div>
</div>
<section class="panel-card mt-4">
    <div class="panel-heading"><div><div class="eyebrow">AUDIT TRAIL</div><h3>Sync logs</h3></div><span class="subtle-label">Latest 10 runs</span></div>
    <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>System</th><th>Action</th><th>Started</th><th>Imported</th><th>Skipped</th><th>Failed</th><th>Message</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="7"><div class="empty-state">No synchronization runs yet.</div></td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr><td><span class="source-tag"><?= e(strtoupper($log['source_system'])) ?></span></td><td><?= e($log['action']) ?></td><td><?= e(date('M d, Y H:i', strtotime($log['started_at']))) ?></td><td class="text-success fw-semibold"><?= e($log['imported_count']) ?></td><td><?= e($log['skipped_count']) ?></td><td class="<?= $log['failed_count'] ? 'text-danger fw-semibold' : '' ?>"><?= e($log['failed_count']) ?></td><td><?= e($log['message']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>