<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
ensure_optional_modules();
$user = require_login();
require_permission('attendance.manage');
$title = 'Attendance';
$activePage = 'attendance';
$db = db_finance();
$campus = 'NCST Main Campus · Dasmariñas, Cavite';
$selectedDate = (string) ($_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

if (is_post()) {
    verify_csrf();
    $employeeId = (int) ($_POST['employee_snapshot_id'] ?? 0);
    $action = (string) ($_POST['attendance_action'] ?? '');
    $time = (string) ($_POST['attendance_time'] ?? date('Y-m-d\TH:i'));
    $status = in_array($_POST['status'] ?? 'present', ['present', 'late', 'on_leave', 'half_day'], true) ? (string) $_POST['status'] : 'present';
    if ($employeeId < 1 || !in_array($action, ['time_in', 'time_out', 'mark_status'], true)) {
        flash('error', 'Choose an employee and a valid attendance action.');
    } else {
        $when = date('Y-m-d H:i:s', strtotime($time));
        if ($action === 'time_in') {
            $stmt = $db->prepare('INSERT INTO attendance_logs (employee_snapshot_id, attendance_date, time_in, geo_latitude, geo_longitude, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), geo_latitude = VALUES(geo_latitude), geo_longitude = VALUES(geo_longitude), status = VALUES(status), notes = VALUES(notes), created_by = VALUES(created_by)');
            $stmt->execute([$employeeId, $selectedDate, $when, ($_POST['geo_latitude'] ?? '') !== '' ? (float) $_POST['geo_latitude'] : null, ($_POST['geo_longitude'] ?? '') !== '' ? (float) $_POST['geo_longitude'] : null, $status, trim((string) ($_POST['notes'] ?? '')), $user['id']]);
            flash('success', 'Time-in recorded for the selected employee.');
        } elseif ($action === 'time_out') {
            $stmt = $db->prepare('UPDATE attendance_logs SET time_out = ?, geo_latitude = ?, geo_longitude = ?, notes = ?, created_by = ? WHERE employee_snapshot_id = ? AND attendance_date = ?');
            $stmt->execute([$when, ($_POST['geo_latitude'] ?? '') !== '' ? (float) $_POST['geo_latitude'] : null, ($_POST['geo_longitude'] ?? '') !== '' ? (float) $_POST['geo_longitude'] : null, trim((string) ($_POST['notes'] ?? '')), $user['id'], $employeeId, $selectedDate]);
            if ($stmt->rowCount() === 0) flash('error', 'Time-in must be recorded before time-out.');
            else flash('success', 'Time-out recorded for the selected employee.');
        } else {
            $stmt = $db->prepare('INSERT INTO attendance_logs (employee_snapshot_id, attendance_date, status, notes, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), created_by = VALUES(created_by)');
            $stmt->execute([$employeeId, $selectedDate, $status, trim((string) ($_POST['notes'] ?? '')), $user['id']]);
            flash('success', 'Attendance status updated.');
        }
        audit('attendance', 'attendance', 'employee', (string) $employeeId, null, ['date' => $selectedDate, 'action' => $action]);
    }
    redirect('attendance.php?date=' . urlencode($selectedDate));
}

$employees = $db->query("SELECT id, source_id, employee_code, full_name, department_name, position_title FROM employee_snapshots WHERE LOWER(COALESCE(employment_status, 'active')) NOT IN ('inactive','archived','terminated') ORDER BY full_name")->fetchAll();
$stmt = $db->prepare('SELECT a.*, e.full_name, e.employee_code, e.department_name FROM attendance_logs a JOIN employee_snapshots e ON e.id=a.employee_snapshot_id WHERE a.attendance_date = ? ORDER BY e.full_name');
$stmt->execute([$selectedDate]);
$records = $stmt->fetchAll();
$recorded = [];
foreach ($records as $record) $recorded[(int) $record['employee_snapshot_id']] = $record;
$present = count(array_filter($records, static fn(array $row): bool => in_array($row['status'], ['present', 'late', 'half_day'], true)));
$absent = count($employees) - count($records);
$open = count(array_filter($records, static fn(array $row): bool => $row['time_in'] && !$row['time_out']));
require __DIR__ . '/includes/header.php';
?>
<section class="hero-banner compact-banner">
    <div><div class="eyebrow">PEOPLE OPERATIONS · <?= e(strtoupper(date('l', strtotime($selectedDate)))) ?></div><h2>Attendance · <?= e($campus) ?></h2><p>Record time-in and time-out locally, then review who is present, on leave, or absent for the day.</p></div>
    <span class="offline-pill light-pill"><span class="status-dot"></span> LOCAL TIME · ASIA/MANILA</span>
</section>
<form method="get" class="panel-card sync-toolbar mb-4">
    <div><div class="eyebrow">ATTENDANCE DATE</div><strong>Daily timekeeping register</strong></div>
    <label>Date<input type="date" name="date" class="form-control form-control-sm" value="<?= e($selectedDate) ?>"></label>
    <button class="btn btn-primary" type="submit"><span class="bi bi-calendar2-check"></span> View day</button>
</form>
<div class="summary-strip">
    <div><span>Employees</span><strong><?= number_format(count($employees)) ?></strong></div>
    <div><span>Present / late</span><strong><?= number_format($present) ?></strong></div>
    <div><span>Open time-outs</span><strong><?= number_format($open) ?></strong></div>
    <div><span>Absent / unmarked</span><strong><?= number_format(max(0, $absent)) ?></strong></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-5"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">QUICK TIMEKEEPING</div><h3>Record employee attendance</h3></div></div>
        <?php if (!$employees): ?><div class="empty-state"><span class="bi bi-people"></span><strong>No employee snapshots found</strong><small>Run HRMS sync first to load the employee list.</small><a href="integration.php" class="btn btn-sm btn-outline-primary mt-2">Open HRMS integration</a></div><?php else: ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>"><input type="hidden" name="geo_latitude" value=""><input type="hidden" name="geo_longitude" value=""><input type="hidden" name="location_status" value="">
            <label class="form-label">Employee<select class="form-select" name="employee_snapshot_id" required><option value="">Choose employee</option><?php foreach ($employees as $employee): ?><option value="<?= e($employee['id']) ?>"><?= e($employee['full_name'] . ' · ' . ($employee['employee_code'] ?: 'No ID')) ?></option><?php endforeach; ?></select></label>
            <label class="form-label mt-3">Date &amp; time<input class="form-control" type="datetime-local" name="attendance_time" value="<?= e(date('Y-m-d\TH:i')) ?>"></label>
            <label class="form-label mt-3">Status<select class="form-select" name="status"><option value="present">Present</option><option value="late">Late</option><option value="half_day">Half day</option><option value="on_leave">On leave</option></select></label>
            <label class="form-label mt-3">Note<textarea class="form-control" name="notes" rows="2" placeholder="Optional note"></textarea></label>
            <div class="form-note mt-3"><span class="bi bi-geo-alt"></span><span class="location-status">Location will be captured automatically when you press Time in or Time out.</span></div>
            <div class="d-flex gap-2 flex-wrap mt-3"><button class="btn btn-primary" name="attendance_action" value="time_in"><span class="bi bi-box-arrow-in-right"></span> Time in</button><button class="btn btn-outline-primary" name="attendance_action" value="time_out"><span class="bi bi-box-arrow-right"></span> Time out</button><button class="btn btn-light" name="attendance_action" value="mark_status">Save status</button></div>
        </form>
        <?php endif; ?>
    </section></div>
    <div class="col-xl-7"><section class="panel-card h-100">
        <div class="panel-heading"><div><div class="eyebrow">DAILY REGISTER</div><h3>Present and recorded staff</h3></div><span class="subtle-label"><?= e(date_label($selectedDate)) ?></span></div>
        <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Time in</th><th>Time out</th></tr></thead><tbody>
        <?php foreach ($records as $record): ?><tr><td><strong><?= e($record['full_name']) ?></strong><small class="d-block text-muted"><?= e($record['employee_code'] ?: 'No employee ID') ?></small></td><td><?= e($record['department_name'] ?: 'Unassigned') ?></td><td><span class="status-badge <?= $record['status'] === 'on_leave' ? 'warning' : 'success' ?>"><?= e(ucwords(str_replace('_', ' ', $record['status']))) ?></span></td><td><?= $record['time_in'] ? e(date('H:i', strtotime($record['time_in']))) : '—' ?></td><td><?= $record['time_out'] ? e(date('H:i', strtotime($record['time_out']))) : '<span class="text-warning">Open</span>' ?></td></tr><?php endforeach; ?>
        <?php if (!$records): ?><tr><td colspan="5"><div class="empty-state"><span class="bi bi-calendar-x"></span><strong>No attendance recorded</strong><small>Employees will appear in the absent list below until a status or time-in is saved.</small></div></td></tr><?php endif; ?>
        </tbody></table></div>
    </section></div>
</div>
<section class="panel-card mt-4">
    <div class="panel-heading"><div><div class="eyebrow">FOLLOW-UP LIST</div><h3>Absent / unmarked employees</h3></div><span class="status-badge danger"><?= number_format(max(0, $absent)) ?> to review</span></div>
    <div class="table-responsive"><table class="table align-middle finance-table"><thead><tr><th>Employee</th><th>Employee ID</th><th>Department</th><th>Suggested action</th></tr></thead><tbody>
    <?php $hasAbsent = false; foreach ($employees as $employee): if (isset($recorded[(int) $employee['id']])) continue; $hasAbsent = true; ?><tr><td><strong><?= e($employee['full_name']) ?></strong></td><td><?= e($employee['employee_code'] ?: '—') ?></td><td><?= e($employee['department_name'] ?: 'Unassigned') ?></td><td><span class="status-badge danger">Unmarked · verify absence</span></td></tr><?php endforeach; ?>
    <?php if (!$hasAbsent): ?><tr><td colspan="4"><div class="empty-state"><span class="bi bi-check2-circle"></span><strong>No unmarked employees</strong><small>Everyone has an attendance record for this date.</small></div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<script>
(function () {
    var form = document.querySelector('form[method="post"]');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;
        var action = submitter ? submitter.value : '';
        if (!['time_in', 'time_out'].includes(action) || form.dataset.locationReady === '1') return;
        if (!navigator.geolocation) {
            form.dataset.locationReady = '1';
            return;
        }
        event.preventDefault();
        var status = form.querySelector('.location-status');
        if (status) status.textContent = 'Requesting device location…';
        navigator.geolocation.getCurrentPosition(function (position) {
            form.querySelector('[name="geo_latitude"]').value = position.coords.latitude;
            form.querySelector('[name="geo_longitude"]').value = position.coords.longitude;
            form.dataset.locationReady = '1';
            if (status) status.textContent = 'Location captured. Saving attendance…';
            form.requestSubmit(submitter);
        }, function () {
            if (status) status.textContent = 'Location permission was not granted. Attendance was not saved.';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
    });
}());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>