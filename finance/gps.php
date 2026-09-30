<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
ensure_optional_modules();
$user = require_login();
require_permission('gps.manage');
$title = 'GPS & Branches';
$activePage = 'gps';
$db = db_finance();

if (is_post()) {
    verify_csrf();
    $label = trim((string) ($_POST['label'] ?? ''));
    $purpose = trim((string) ($_POST['purpose'] ?? ''));
    $loggedAt = (string) ($_POST['logged_at'] ?? date('Y-m-d H:i:s'));
    if ($label === '' || $purpose === '') {
        flash('error', 'Location label and purpose are required.');
    } else {
        $stmt = $db->prepare('INSERT INTO location_logs (branch_id, label, latitude, longitude, purpose, logged_at, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            ($_POST['branch_id'] ?? '') !== '' ? (int) $_POST['branch_id'] : null,
            $label,
            ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null,
            ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null,
            $purpose, date('Y-m-d H:i:s', strtotime($loggedAt)), trim((string) ($_POST['notes'] ?? '')), $user['id']
        ]);
        audit('create', 'gps', 'location_log', (string) $db->lastInsertId(), null, ['label' => $label, 'purpose' => $purpose]);
        flash('success', 'Location log saved to the local finance database.');
    }
    redirect('gps.php');
}

$branches = $db->query('SELECT id, name, address FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll();
$logs = $db->query('SELECT l.*, b.name branch_name FROM location_logs l LEFT JOIN branches b ON b.id=l.branch_id ORDER BY l.logged_at DESC, l.id DESC LIMIT 15')->fetchAll();
$branchCount = (int) $db->query('SELECT COUNT(*) FROM branches WHERE is_active = 1')->fetchColumn();
require __DIR__ . '/includes/header.php';
?>
<section class="hero-banner compact-banner">
    <div><div class="eyebrow">LOCAL FIELD CONTROL</div><h2>GPS &amp; branch activity</h2><p>Keep a simple, auditable location register for branch visits, cash collection, deliveries, and finance checks.</p></div>
    <span class="offline-pill light-pill"><span class="status-dot"></span> DEVICE DATA ONLY</span>
</section>
<div class="summary-strip">
    <div><span>Active branches</span><strong><?= number_format($branchCount) ?></strong></div>
    <div><span>Location logs</span><strong><?= number_format(count($logs)) ?></strong></div>
    <div><span>Last capture</span><strong><?= $logs ? e(date('M d, H:i', strtotime($logs[0]['logged_at']))) : 'None' ?></strong></div>
    <div><span>Privacy</span><strong>Local database</strong></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-5"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">NEW LOCATION LOG</div><h3>Record a field activity</h3></div></div>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label">Label<input class="form-control" name="label" placeholder="Main branch cash pickup" required></label>
            <label class="form-label mt-3">Purpose<select class="form-select" name="purpose" required><option value="">Choose purpose</option><option>Cash collection</option><option>Branch audit</option><option>Delivery / receiving</option><option>Bank deposit</option><option>Other finance activity</option></select></label>
            <label class="form-label mt-3">Branch<select class="form-select" name="branch_id"><option value="">Not linked to a branch</option><?php foreach ($branches as $branch): ?><option value="<?= e($branch['id']) ?>"><?= e($branch['name']) ?></option><?php endforeach; ?></select></label>
            <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Latitude<input class="form-control" name="latitude" placeholder="14.5995"></label></div><div class="col-6"><label class="form-label">Longitude<input class="form-control" name="longitude" placeholder="120.9842"></label></div></div>
            <label class="form-label mt-3">Date &amp; time<input class="form-control" type="datetime-local" name="logged_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></label>
            <label class="form-label mt-3">Notes<textarea class="form-control" name="notes" rows="3" placeholder="Optional handover or reconciliation note"></textarea></label>
            <div class="form-note mt-3"><span class="bi bi-lock"></span><span>Coordinates are optional and stored only in your local MySQL database.</span></div>
            <button class="btn btn-primary mt-3" type="submit"><span class="bi bi-geo-alt"></span> Save location log</button>
        </form>
    </section></div>
    <div class="col-xl-7"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">ACTIVITY REGISTER</div><h3>Recent location logs</h3></div><span class="subtle-label">Latest 15</span></div>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Activity</th><th>Branch</th><th>Purpose</th><th>Coordinates</th><th>Captured</th></tr></thead><tbody>
        <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state"><span class="bi bi-geo-alt"></span><strong>No location activity yet</strong><small>Record a visit or collection above.</small></div></td></tr><?php endif; ?>
        <?php foreach ($logs as $log): ?><tr><td><strong><?= e($log['label']) ?></strong><?php if ($log['notes']): ?><small class="d-block text-muted"><?= e($log['notes']) ?></small><?php endif; ?></td><td><?= e($log['branch_name'] ?: '—') ?></td><td><?= e($log['purpose']) ?></td><td><?= $log['latitude'] !== null ? e(number_format((float) $log['latitude'], 5) . ', ' . number_format((float) $log['longitude'], 5)) : '—' ?></td><td><?= e(date('M d, Y H:i', strtotime($log['logged_at']))) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>