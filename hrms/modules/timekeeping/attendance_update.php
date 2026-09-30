<?php
/**
 * HR — Update attendance record (POST handler)
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../config/pos_attendance_sync.php';
requireRole('hr','admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'modules/timekeeping/list.php');
    exit;
}
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token.');
}

$db           = getDB();
$attendanceId = (int)($_POST['attendance_id'] ?? 0);
$timeIn       = trim($_POST['time_in'] ?? '');
$timeOut      = trim($_POST['time_out'] ?? '');
$status       = trim($_POST['status'] ?? 'present');
$redirectDate = $_POST['redirect_date'] ?? date('Y-m-d');

$rec = $db->prepare("SELECT * FROM attendance WHERE id = ?");
$rec->execute([$attendanceId]);
$att = $rec->fetch();
if (!$att) {
    header('Location: ' . SITE_URL . 'modules/timekeeping/list.php?date=' . urlencode($redirectDate));
    exit;
}

// Recalculate late/undertime
$lateMin = $timeIn  ? calcLateMinutes($timeIn . ':00')   : 0;
$utMin   = $timeOut ? calcUndertimeMinutes($timeOut . ':00') : 0;
if ($status === 'absent') { $lateMin = 0; $utMin = 0; }

$db->prepare(
    "UPDATE attendance SET time_in=?, time_out=?, status=?, late_minutes=?, undertime_minutes=? WHERE id=?"
)->execute([
    $timeIn  ? $timeIn . ':00'  : null,
    $timeOut ? $timeOut . ':00' : null,
    $status,
    $lateMin,
    $utMin,
    $attendanceId,
]);

$fresh=$db->prepare('SELECT * FROM attendance WHERE id=?'); $fresh->execute([$attendanceId]); if($fr=$fresh->fetch()) mirrorHrmsAttendanceToPos($db,$fr);

auditLog('UPDATE', 'Timekeeping', "Attendance #$attendanceId updated — status: $status");

header('Location: ' . SITE_URL . 'modules/timekeeping/list.php?date=' . urlencode($redirectDate) . '&success=Attendance+updated');
exit;
