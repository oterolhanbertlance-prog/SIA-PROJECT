<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$pageTitle = 'My Leave';
$empId = currentUser()['emp_id'];
$db = getDB();

if (isset($_GET['success'])): ?>
<?php endif; ?>
<?php

$stmt = $db->prepare("SELECT lr.*, lt.name as type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id=lt.id WHERE lr.employee_id=? ORDER BY lr.created_at DESC");
$stmt->execute([$empId]);
$leaves = $stmt->fetchAll();

$balances = $db->prepare("SELECT lb.*, lt.name FROM leave_balance lb JOIN leave_types lt ON lb.leave_type_id=lt.id WHERE lb.employee_id=? AND lb.year=?");
$balances->execute([$empId, date('Y')]);
$balances = $balances->fetchAll();

include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>
<div class="page-header">
    <div><h4><i class="bi bi-calendar3-range-fill me-2 text-primary"></i>My Leave</h4></div>
    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>File Leave</a>
</div>

<!-- Balances -->
<div class="row g-3 mb-4">
<?php foreach ($balances as $b): ?>
<div class="col-md-4 col-sm-6">
    <div class="card text-center p-3">
        <div class="text-muted small mb-1"><?= htmlspecialchars($b['name']) ?></div>
        <div class="fs-4 fw-bold text-primary"><?= $b['remaining_days'] ?></div>
        <div class="text-muted" style="font-size:.75rem;"><?= $b['used_days'] ?> used / <?= $b['total_days'] ?> total</div>
        <div class="progress mt-2" style="height:4px;">
            <div class="progress-bar bg-primary" style="width:<?= $b['total_days']>0?($b['remaining_days']/$b['total_days']*100):0 ?>%"></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-list-ul me-2"></i>Leave History</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($leaves as $lr): ?>
            <tr>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($lr['leave_code']) ?></span></td>
                <td><?= htmlspecialchars($lr['type_name']) ?></td>
                <td><?= formatDate($lr['start_date']) ?></td>
                <td><?= formatDate($lr['end_date']) ?></td>
                <td><?= $lr['total_days'] ?></td>
                <td><span class="text-muted small"><?= htmlspecialchars(substr($lr['reason']??'',0,40)) ?></span></td>
                <td><?= statusBadge($lr['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($leaves)): ?><tr><td colspan="7" class="text-center text-muted py-4">No leave requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
