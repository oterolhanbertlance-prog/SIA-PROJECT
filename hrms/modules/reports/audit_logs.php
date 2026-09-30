<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'Audit Logs';
$db = getDB();

$search = sanitize($_GET['search'] ?? '');
$module = sanitize($_GET['module'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset = ($page-1)*$perPage;

$where  = ["1=1"]; $params = [];
if ($search) { $where[] = "(al.action LIKE ? OR al.description LIKE ? OR al.username LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($module) { $where[] = "al.module=?"; $params[] = $module; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT al.* FROM audit_logs al $whereStr ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-journal-text me-2 text-primary"></i>Audit Logs</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="index.php">Reports</a></li><li class="breadcrumb-item active">Audit Logs</li></ol></nav>
    </div>
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary no-print"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<div class="card mb-3 no-print"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><div class="search-box w-100"><i class="bi bi-search search-icon"></i><input type="text" name="search" class="form-control" placeholder="Search action, description, user..." value="<?= htmlspecialchars($search) ?>"></div></div>
    <div class="col-md-3"><select name="module" class="form-select form-select-sm"><option value="">All Modules</option><?php foreach ($modules as $m): ?><option value="<?=$m?>" <?=$module==$m?'selected':''?>><?=$m?></option><?php endforeach; ?></select></div>
    <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="audit_logs.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form></div></div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?><tr><td colspan="6" class="text-center text-muted py-4">No audit logs found.</td></tr><?php endif; ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><small><?= formatDateTime($log['created_at']) ?></small></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($log['username']??'System') ?></span></td>
                <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($log['action']) ?></span></td>
                <td><?= htmlspecialchars($log['module']??'—') ?></td>
                <td><small><?= htmlspecialchars(substr($log['description']??'',0,80)) ?></small></td>
                <td><small class="text-muted"><?= htmlspecialchars($log['ip_address']??'—') ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?> logs</small>
        <?= paginate($total,$perPage,$page,'audit_logs.php?'.http_build_query(['search'=>$search,'module'=>$module])) ?>
    </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
