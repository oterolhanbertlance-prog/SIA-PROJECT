<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin', 'hr');
$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM contracts WHERE id=?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: list.php?error=Not+found'); exit; }
$db->prepare("DELETE FROM contracts WHERE id=?")->execute([$id]);
auditLog('DELETE','Deployment',"Deleted contract: {$c['contract_number']}");
header('Location: list.php?success=Contract+deleted');
exit;
