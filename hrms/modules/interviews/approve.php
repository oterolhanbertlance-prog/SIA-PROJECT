<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

// Two-stage approval of a scheduled interview:
// Admin gives the first-level approval (forwarded to HR), HR gives the
// final, official approval. Rejection can happen at either stage and
// cancels the interview.

$id     = (int)($_GET['id'] ?? 0);
$action = sanitize($_GET['action'] ?? '');
$db     = getDB();

if (!in_array($action, ['approved','rejected'])) { header('Location: list.php?error=Invalid+action'); exit; }

$stmt = $db->prepare("SELECT * FROM interviews WHERE id=?");
$stmt->execute([$id]);
$iv = $stmt->fetch();
if (!$iv) { header('Location: list.php?error=Interview+not+found'); exit; }
if (!in_array($iv['approval_status'], ['pending','admin_approved'])) { header('Location: list.php?error=Interview+already+processed'); exit; }

$cu = currentUser();

if ($action === 'rejected') {
    $db->prepare("UPDATE interviews SET approval_status='rejected', status='cancelled' WHERE id=?")->execute([$id]);
    auditLog('UPDATE','Interviews',"Interview #{$id} rejected");
    header('Location: list.php?success='.urlencode("Interview rejected and cancelled."));
    exit;
}

// action === 'approved'
if (hasRole('hr')) {
    $db->prepare("UPDATE interviews SET approval_status='approved', hr_approved_by=?, hr_approved_at=NOW() WHERE id=?")->execute([$cu['id'], $id]);
    auditLog('UPDATE','Interviews',"Interview #{$id} officially approved by HR");
    header('Location: list.php?success='.urlencode("Interview approved."));
} elseif (hasRole('admin') && $iv['approval_status'] === 'pending') {
    $db->prepare("UPDATE interviews SET approval_status='admin_approved', admin_approved_by=?, admin_approved_at=NOW() WHERE id=?")->execute([$cu['id'], $id]);
    auditLog('UPDATE','Interviews',"Interview #{$id} approved by Admin, forwarded to HR");
    header('Location: list.php?success='.urlencode("Approved. Forwarded to HR for final sign-off."));
} else {
    header('Location: list.php?error='.urlencode("This interview is already forwarded to HR."));
}
exit;
