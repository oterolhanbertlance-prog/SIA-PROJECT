<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');
$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM payroll WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { header('Location: list.php?error=Not+found'); exit; }
$db->prepare("DELETE FROM payroll WHERE id=?")->execute([$id]);
auditLog('DELETE','Payroll',"Deleted payroll record #{$p['payroll_code']}");
header('Location: list.php?success=Payroll+record+deleted');
exit;
