<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');

$pageTitle = 'Resignation Letters';
$db = getDB();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        header('Location: list.php?error=Invalid+request'); exit;
    }
    $resId   = (int)($_POST['res_id'] ?? 0);
    $action  = sanitize($_POST['action'] ?? '');
    $remarks = sanitize($_POST['hr_remarks'] ?? '');

    if ($resId && in_array($action, ['approved', 'rejected'])) {
        $resStmt = $db->prepare("SELECT rl.*, e.firstname, e.lastname, e.id as emp_id FROM resignation_letters rl JOIN employees e ON rl.employee_id=e.id WHERE rl.id=? AND rl.status IN ('pending','admin_approved')");
        $resStmt->execute([$resId]);
        $res = $resStmt->fetch();
        $cu = currentUser();

        if ($res) {
            // Two-stage flow: Admin's "approve" forwards to HR (admin_approved);
            // HR's "approve" is the final, official approval. Reject ends it at either stage.
            if ($action === 'approved' && hasRole('admin') && $res['status'] === 'pending') {
                $db->prepare(
                    "UPDATE resignation_letters SET status='admin_approved', admin_approved_by=?, admin_approved_at=NOW() WHERE id=?"
                )->execute([$cu['id'], $resId]);
                auditLog('UPDATE', 'Resignation', "Resignation #{$resId} for {$res['firstname']} {$res['lastname']} approved by Admin, forwarded to HR.");
                header('Location: list.php?success='.urlencode("Approved. Forwarded to HR for final sign-off."));
                exit;
            }

            if ($action === 'approved' && !hasRole('hr')) {
                header('Location: list.php?error='.urlencode("This request is already forwarded to HR."));
                exit;
            }

            $finalStatus = $action; // 'approved' (HR final) or 'rejected'
            $db->prepare(
                "UPDATE resignation_letters SET status=?, hr_remarks=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
            )->execute([$finalStatus, $remarks, $cu['id'], $resId]);

            // If officially approved by HR, update employee status to Resigned
            if ($finalStatus === 'approved') {
                $db->prepare("UPDATE employees SET employment_status='Resigned', status='inactive' WHERE id=?")
                   ->execute([$res['emp_id']]);

                // Also deactivate user account
                $db->prepare("UPDATE users SET status='inactive' WHERE employee_id=?")
                   ->execute([$res['emp_id']]);
            }

            // Notify the employee via notification (find their user account)
            $userStmt = $db->prepare("SELECT id FROM users WHERE employee_id=? AND status IN ('active','inactive') LIMIT 1");
            $userStmt->execute([$res['emp_id']]);
            $userRow = $userStmt->fetch();
            if ($userRow) {
                $msg = $finalStatus === 'approved'
                    ? "Your resignation has been accepted. Your last working day is confirmed as ".date('M d, Y', strtotime($res['last_working_day'])).". Thank you for your service."
                    : "Your resignation letter has been rejected. Remarks: $remarks";
                $db->prepare(
                    "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?,?,?,?,NOW())"
                )->execute([$userRow['id'], 'Resignation Letter '.ucfirst($finalStatus), $msg, $finalStatus === 'approved' ? 'success' : 'warning']);
            }

            auditLog('UPDATE', 'Resignation', "Resignation #{$resId} for {$res['firstname']} {$res['lastname']} has been {$finalStatus}.");
            header('Location: list.php?success='.urlencode("Resignation has been {$finalStatus}."));
            exit;
        }
    }
    header('Location: list.php'); exit;
}

$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where  = ['1=1']; $params = [];
if ($status) { $where[] = 'rl.status=?'; $params[] = $status; }
if ($search) {
    $where[] = '(e.firstname LIKE ? OR e.lastname LIKE ? OR e.employee_id LIKE ?)';
    $s = "%$search%"; $params = array_merge($params, [$s,$s,$s]);
}
$whereStr = 'WHERE '.implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT rl.*, e.firstname, e.lastname, e.employee_id as emp_code, d.name as dept_name,
            p.title as pos_title,
            reviewer.username as reviewer_name
     FROM resignation_letters rl
     JOIN employees e ON rl.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN positions p ON e.position_id = p.id
     LEFT JOIN users reviewer ON rl.reviewed_by = reviewer.id
     $whereStr
     ORDER BY rl.created_at DESC"
);
$stmt->execute($params);
$resignations = $stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-box-arrow-right me-2 text-danger"></i>Resignation Letters</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Resignations</li>
        </ol></nav>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-auto-dismiss"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','admin_approved','approved','rejected'] as $s): ?>
                    <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="search-box w-100">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search employee..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Filter</button>
                <a href="list.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">
        Resignation Letters
        <?php $pending = array_filter($resignations, fn($r)=>$r['status']==='pending'); ?>
        <?php if (count($pending) > 0): ?>
        <span class="badge bg-danger ms-2"><?= count($pending) ?> Pending</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Last Working Day</th>
                    <th>Reason</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($resignations)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No resignation letters found.</td></tr>
            <?php endif; ?>
            <?php foreach ($resignations as $r): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($r['emp_code']) ?></div>
                </td>
                <td class="small"><?= htmlspecialchars($r['dept_name'] ?? '—') ?></td>
                <td class="small fw-semibold <?= strtotime($r['last_working_day']) <= strtotime('+7 days') && $r['status']==='pending' ? 'text-danger' : '' ?>">
                    <?= formatDate($r['last_working_day']) ?>
                </td>
                <td class="small"><?= htmlspecialchars($r['reason']) ?></td>
                <td class="small text-muted"><?= formatDate($r['created_at']) ?></td>
                <td><?= statusBadge($r['status']) ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <button type="button" class="btn btn-sm btn-info py-0 px-2" title="View Letter"
                            data-bs-toggle="modal" data-bs-target="#viewModal"
                            data-id="<?=$r['id']?>"
                            data-name="<?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?>"
                            data-letter="<?= htmlspecialchars($r['letter_body']) ?>"
                            data-reason="<?= htmlspecialchars($r['reason']) ?>"
                            data-last="<?= htmlspecialchars(formatDate($r['last_working_day'])) ?>"
                            data-status="<?= htmlspecialchars($r['status']) ?>"
                            data-remarks="<?= htmlspecialchars($r['hr_remarks'] ?? '') ?>"
                            data-reviewer="<?= htmlspecialchars($r['reviewer_name'] ?? '') ?>"
                            data-reviewed="<?= htmlspecialchars(formatDateTime($r['reviewed_at'] ?? '')) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($r['status'] === 'pending' && hasRole('admin')): ?>
                        <button type="button" class="btn btn-sm btn-success py-0 px-2" title="Approve &amp; forward to HR"
                            data-bs-toggle="modal" data-bs-target="#approveModal"
                            data-id="<?=$r['id']?>" data-name="<?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?>"
                            data-stage="admin">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger py-0 px-2" title="Reject"
                            data-bs-toggle="modal" data-bs-target="#rejectModal"
                            data-id="<?=$r['id']?>" data-name="<?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php elseif (in_array($r['status'], ['pending','admin_approved']) && hasRole('hr')): ?>
                        <button type="button" class="btn btn-sm btn-success py-0 px-2" title="Final Approve"
                            data-bs-toggle="modal" data-bs-target="#approveModal"
                            data-id="<?=$r['id']?>" data-name="<?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?>"
                            data-stage="hr">
                            <i class="bi bi-check-all"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger py-0 px-2" title="Reject"
                            data-bs-toggle="modal" data-bs-target="#rejectModal"
                            data-id="<?=$r['id']?>" data-name="<?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-text me-2"></i>Resignation Letter — <span id="viewName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="text-muted small">Last Working Day</div><div class="fw-semibold" id="viewLast"></div></div>
                    <div class="col-md-4"><div class="text-muted small">Reason</div><div class="fw-semibold" id="viewReason"></div></div>
                    <div class="col-md-4"><div class="text-muted small">Status</div><div id="viewStatus"></div></div>
                </div>
                <div class="border rounded p-3 bg-light mb-3" style="white-space:pre-wrap;font-family:serif;font-size:.95rem;max-height:300px;overflow-y:auto;" id="viewLetter"></div>
                <div id="viewReviewInfo" style="display:none;">
                    <hr>
                    <div class="row g-2 small">
                        <div class="col-md-6"><span class="text-muted">Reviewed by:</span> <strong id="viewReviewer"></strong></div>
                        <div class="col-md-6"><span class="text-muted">Reviewed at:</span> <strong id="viewReviewed"></strong></div>
                        <div class="col-12"><span class="text-muted">Remarks:</span> <strong id="viewRemarks"></strong></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="res_id" id="approveId">
                <input type="hidden" name="action" value="approved">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-success" id="approveTitle"><i class="bi bi-check-circle me-2"></i>Accept Resignation</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Processing resignation of: <strong id="approveName"></strong></p>
                    <p class="small text-danger" id="approveNote"><strong>Note:</strong> Accepting will mark the employee as Resigned and deactivate their account.</p>
                    <label class="form-label fw-semibold small">Remarks (optional)</label>
                    <textarea name="hr_remarks" class="form-control form-control-sm" rows="3" placeholder="Thank you for your service..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm" id="approveSubmitBtn"><i class="bi bi-check me-1"></i>Accept Resignation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="res_id" id="rejectId">
                <input type="hidden" name="action" value="rejected">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Reject Resignation</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Rejecting resignation of: <strong id="rejectName"></strong></p>
                    <label class="form-label fw-semibold small">Remarks / Reason <span class="text-danger">*</span></label>
                    <textarea name="hr_remarks" class="form-control form-control-sm" rows="3" required placeholder="e.g. Please discuss with HR before formal submission..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x me-1"></i>Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
<script>
document.getElementById('viewModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('viewName').textContent   = b.dataset.name;
    document.getElementById('viewLast').textContent   = b.dataset.last;
    document.getElementById('viewReason').textContent = b.dataset.reason;
    document.getElementById('viewLetter').textContent = b.dataset.letter;
    const stColor = b.dataset.status==='approved'?'success':b.dataset.status==='rejected'?'danger':b.dataset.status==='admin_approved'?'info':'warning';
    const stLabel = b.dataset.status==='admin_approved' ? 'Admin Approved' : (b.dataset.status.charAt(0).toUpperCase()+b.dataset.status.slice(1));
    document.getElementById('viewStatus').innerHTML   = '<span class="badge bg-'+stColor+'">'+stLabel+'</span>';
    const reviewInfo = document.getElementById('viewReviewInfo');
    if (b.dataset.reviewer) {
        reviewInfo.style.display = 'block';
        document.getElementById('viewReviewer').textContent = b.dataset.reviewer;
        document.getElementById('viewReviewed').textContent = b.dataset.reviewed;
        document.getElementById('viewRemarks').textContent  = b.dataset.remarks || '—';
    } else {
        reviewInfo.style.display = 'none';
    }
});
document.getElementById('approveModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('approveId').value = b.dataset.id;
    document.getElementById('approveName').textContent = b.dataset.name;
    const isHrStage = b.dataset.stage === 'hr';
    document.getElementById('approveTitle').innerHTML = isHrStage
        ? '<i class="bi bi-check-circle me-2"></i>Final Approve Resignation'
        : '<i class="bi bi-check-circle me-2"></i>Approve &amp; Forward to HR';
    document.getElementById('approveNote').innerHTML = isHrStage
        ? '<strong>Note:</strong> This is the official HR approval. It will mark the employee as Resigned and deactivate their account.'
        : '<strong>Note:</strong> This forwards the resignation to HR for the final, official approval. The employee will not be marked as Resigned yet.';
    document.getElementById('approveSubmitBtn').innerHTML = isHrStage
        ? '<i class="bi bi-check-all me-1"></i>Final Approve'
        : '<i class="bi bi-check me-1"></i>Approve &amp; Forward to HR';
});
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('rejectId').value = b.dataset.id;
    document.getElementById('rejectName').textContent = b.dataset.name;
});
</script>
JS;
include '../../includes/footer.php';
?>
