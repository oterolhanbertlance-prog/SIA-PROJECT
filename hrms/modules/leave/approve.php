<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$id     = (int)($_GET['id'] ?? 0);
$action = sanitize($_GET['action'] ?? '');
$db     = getDB();

if (!in_array($action,['approved','rejected'])) { header('Location: list.php?error=Invalid+action'); exit; }

$stmt = $db->prepare("SELECT * FROM leave_requests WHERE id=?");
$stmt->execute([$id]);
$lr = $stmt->fetch();
if (!$lr) { header('Location: list.php?error=Leave+request+not+found'); exit; }
if (!in_array($lr['status'], ['pending','admin_approved'])) { header('Location: list.php?error=Leave+already+processed'); exit; }

$cu = currentUser();

if ($action === 'rejected') {
    $db->prepare("UPDATE leave_requests SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$cu['id'], $id]);
    // Restore the balance
    $db->prepare("UPDATE leave_balance SET used_days=used_days-?, remaining_days=remaining_days+? WHERE employee_id=? AND leave_type_id=? AND year=?")
       ->execute([$lr['total_days'], $lr['total_days'], $lr['employee_id'], $lr['leave_type_id'], date('Y', strtotime($lr['start_date']))]);
    auditLog('UPDATE','Leave',"Leave request #{$lr['leave_code']} rejected");
    header('Location: list.php?success='.urlencode("Leave request rejected."));
    exit;
}

// action === 'approved'
if (hasRole('hr')) {
    // HR gives the final, official approval (can finalize a pending or admin-approved request)
    $db->prepare("UPDATE leave_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$cu['id'], $id]);
    auditLog('UPDATE','Leave',"Leave request #{$lr['leave_code']} officially approved by HR");
    header('Location: list.php?success='.urlencode("Leave request approved."));
} elseif (hasRole('admin') && $lr['status'] === 'pending') {
    // Admin recommends approval; forwarded to HR for the official sign-off
    $db->prepare("UPDATE leave_requests SET status='admin_approved', admin_approved_by=?, admin_approved_at=NOW() WHERE id=?")->execute([$cu['id'], $id]);
    auditLog('UPDATE','Leave',"Leave request #{$lr['leave_code']} approved by Admin, forwarded to HR");
    header('Location: list.php?success='.urlencode("Approved. Forwarded to HR for final sign-off."));
} else {
    header('Location: list.php?error=This+request+is+already+forwarded+to+HR');
}
exit;
