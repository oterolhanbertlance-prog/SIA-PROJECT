<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM employees WHERE id=?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) { header('Location: list.php?error=Employee+not+found'); exit; }

// Soft delete (archive)
$db->prepare("UPDATE employees SET status='archived', employment_status='Resigned' WHERE id=?")->execute([$id]);
auditLog('DELETE', 'Employee', "Archived employee: {$emp['employee_id']} - {$emp['firstname']} {$emp['lastname']}");

header('Location: list.php?success=' . urlencode("Employee {$emp['firstname']} {$emp['lastname']} archived successfully."));
exit;
