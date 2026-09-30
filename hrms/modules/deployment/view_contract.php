<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');
$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT c.*, e.firstname, e.lastname, e.employee_id as emp_code, d.name as dept_name, p.title as pos_title FROM contracts c JOIN employees e ON c.employee_id=e.id LEFT JOIN departments d ON c.department_id=d.id LEFT JOIN positions p ON c.position_id=p.id WHERE c.id=?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: list.php?error=Not+found'); exit; }
$pageTitle = 'Contract Details';
include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-file-earmark-text me-2 text-primary"></i>Contract Details</h4></div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="card" style="max-width:750px;" id="contractPrint">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between mb-4">
            <div><h5 class="fw-bold text-primary"><?= SITE_NAME ?></h5><div class="text-muted small">Employment Contract</div></div>
            <div class="text-end"><span class="badge bg-secondary"><?= htmlspecialchars($c['contract_number']??'—') ?></span><div class="mt-1"><?= statusBadge($c['status']) ?></div></div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-md-6"><label class="text-muted small">Employee Name</label><p class="fw-semibold"><?= htmlspecialchars($c['firstname'].' '.$c['lastname']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Employee ID</label><p><?= htmlspecialchars($c['emp_code']) ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Department</label><p><?= htmlspecialchars($c['dept_name']??'—') ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Position</label><p><?= htmlspecialchars($c['pos_title']??'—') ?></p></div>
            <div class="col-md-4"><label class="text-muted small">Contract Type</label><p><?= statusBadge($c['contract_type']) ?></p></div>
            <div class="col-md-4"><label class="text-muted small">Start Date</label><p class="fw-semibold"><?= formatDate($c['start_date']) ?></p></div>
            <div class="col-md-4"><label class="text-muted small">End Date</label><p><?= $c['end_date'] ? formatDate($c['end_date']) : '<span class="text-muted">Open-ended</span>' ?></p></div>
            <div class="col-md-6"><label class="text-muted small">Salary</label><p class="fw-semibold text-success"><?= money($c['salary']) ?>/month</p></div>
            <?php if ($c['file_path']): ?>
            <div class="col-md-6"><label class="text-muted small">Contract File</label><p><a href="<?=SITE_URL?>uploads/contracts/<?= htmlspecialchars($c['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download Contract</a></p></div>
            <?php endif; ?>
            <?php if ($c['notes']): ?><div class="col-12"><label class="text-muted small">Notes</label><p><?= nl2br(htmlspecialchars($c['notes'])) ?></p></div><?php endif; ?>
        </div>
        <hr class="mt-4">
        <div class="row mt-4">
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">HR Officer Signature</div></div>
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">Employee Signature</div></div>
            <div class="col-md-4 text-center"><div class="border-top pt-2 mt-5 small text-muted">Management</div></div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
