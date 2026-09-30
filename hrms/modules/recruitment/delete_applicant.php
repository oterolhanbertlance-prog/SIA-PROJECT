<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');
$id = (int)($_GET['id']??0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM applicants WHERE id=?");
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { header('Location: applicants.php?error=Not+found'); exit; }
$db->prepare("DELETE FROM applicants WHERE id=?")->execute([$id]);
auditLog('DELETE','Recruitment',"Deleted applicant: {$a['applicant_code']} {$a['firstname']} {$a['lastname']}");
header('Location: applicants.php?success=Applicant+deleted');
exit;
