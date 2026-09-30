<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Process Payroll';
$db = getDB();
$db->exec("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction");
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $month  = (int)$_POST['payroll_month'];
        $year   = (int)$_POST['payroll_year'];
        $empIds = $_POST['emp_ids'] ?? [];

        // Validate period — cannot process a future month beyond next month
        $currentMonth = (int)date('n');
        $currentYear  = (int)date('Y');
        $inputDate    = mktime(0,0,0,$month,1,$year);
        $nextMonth    = mktime(0,0,0,$currentMonth+1,1,$currentYear);
        if ($inputDate > $nextMonth) {
            $errors[] = 'Cannot process payroll for a future period beyond next month.';
        }

        if (!$month || !$year) $errors[] = 'Please select month and year.';
        if (empty($empIds))    $errors[] = 'Please select at least one employee.';

        if (empty($errors)) {
            $inserted = 0;
            foreach ($empIds as $empId) {
                $empId = (int)$empId;
                // Check if already exists for this period
                $chk = $db->prepare("SELECT id FROM payroll WHERE employee_id=? AND payroll_month=? AND payroll_year=?");
                $chk->execute([$empId, $month, $year]);
                if ($chk->fetch()) continue;

                $eStmt = $db->prepare("SELECT * FROM employees WHERE id=? AND status='active'");
                $eStmt->execute([$empId]);
                $e = $eStmt->fetch();
                if (!$e) continue;

                $basic      = (float)$e['basic_salary'];
                $allowances = round($basic * 0.05, 2);           // 5% transport/meal allowance
                $overtime   = max(0, (float)($_POST["overtime_$empId"] ?? 0));
                $bonuses    = max(0, (float)($_POST["bonus_$empId"]    ?? 0));
                $gross      = $basic + $allowances + $overtime + $bonuses;

                // Philippine statutory deductions (capped to avoid negatives)
                $sss = min(1350, round($basic * 0.045, 2));
                $ph  = round($basic * 0.02 / 2, 2);               // PhilHealth (employee share)
                $pi  = 100;                                        // Pag-IBIG flat

                // Simplified BIR tax (monthly gross)
                $tax = 0;
                if ($gross > 33333)      $tax = round(($gross - 33333) * 0.20, 2);
                elseif ($gross > 16667)  $tax = round(($gross - 16667) * 0.15, 2);

                // Attendance deductions: outside assigned branch/NCST geofence + late minutes.
                $aStmt=$db->prepare("SELECT COALESCE(SUM(remote_deduction),0) remote_total, COALESCE(SUM(late_minutes),0) late_minutes, COUNT(CASE WHEN status IN ('present','late') THEN 1 END) days_worked FROM attendance WHERE employee_id=? AND MONTH(date)=? AND YEAR(date)=?");
                $aStmt->execute([$empId,$month,$year]); $att=$aStmt->fetch();
                $minuteRate=$basic>0?($basic/22/8/60):0;
                $lateDed=round(((int)($att['late_minutes']??0))*$minuteRate,2);
                $attendanceDed=round((float)($att['remote_total']??0)+$lateDed,2);
                $daysWorked=(float)($att['days_worked']??0);
                $totalDed = $sss + $ph + $pi + $tax + $attendanceDed;

                // ── FIX: net pay must never be negative ──────────────
                $net = max(0, round($gross - $totalDed, 2));
                // If deductions exceeded gross, zero out excess
                if ($gross - $totalDed < 0) {
                    $totalDed = $gross; // cap deductions to gross so balance = 0
                }

                $code = generatePayrollCode($month, $year);
                $db->prepare(
                    "INSERT INTO payroll
                     (payroll_code, employee_id, payroll_month, payroll_year,
                      basic_salary, allowances, overtime_pay, bonuses, gross_pay,
                      sss_deduction, philhealth_deduction, pagibig_deduction, tax_deduction, attendance_deduction,
                      total_deductions, net_pay, days_worked, status, processed_by, processed_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'processed',?,NOW())"
                )->execute([
                    $code, $empId, $month, $year,
                    $basic, $allowances, $overtime, $bonuses, $gross,
                    $sss, $ph, $pi, $tax, $attendanceDed,
                    $totalDed, $net, $daysWorked,
                    currentUser()['id']
                ]);
                $inserted++;
            }
            auditLog('CREATE', 'Payroll', "Processed $inserted payroll records for " . monthName($month) . " $year");
            header("Location: list.php?month=$month&year=$year&success=" . urlencode("Processed $inserted payroll record(s) successfully."));
            exit;
        }
    }
}

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

// Employees not yet in this payroll period
$empStmt = $db->prepare(
    "SELECT e.*, d.name as dept_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id=d.id
     WHERE e.status='active'
       AND e.id NOT IN (SELECT employee_id FROM payroll WHERE payroll_month=? AND payroll_year=?)
     ORDER BY e.firstname"
);
$empStmt->execute([$month, $year]);
$employees = $empStmt->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-calculator me-2 text-primary"></i>Process Payroll</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="list.php">Payroll</a></li>
            <li class="breadcrumb-item active">Process</li>
        </ol></nav>
    </div>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-2"></i>
    Deductions (SSS, PhilHealth, Pag-IBIG, tax) plus attendance penalties are calculated automatically. Late minutes are prorated; attendance outside the assigned branch/NCST geofence uses the recorded remote deduction.
    If total deductions exceed gross pay, net pay is set to <strong>₱0.00</strong> (not negative).
</div>

<form method="POST" data-validate>
    <?= csrfField() ?>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-calendar3 me-2"></i>Payroll Period</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Month <span class="text-danger">*</span></label>
                    <select name="payroll_month" class="form-select" required>
                        <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?= monthName($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year <span class="text-danger">*</span></label>
                    <select name="payroll_year" class="form-select" required>
                        <?php for ($y=date('Y')+1; $y>=date('Y')-2; $y--): ?>
                        <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Select Employees</span>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll"
                       onchange="document.querySelectorAll('.emp-check').forEach(c=>c.checked=this.checked)">
                <label class="form-check-label small" for="selectAll">Select All</label>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($employees)): ?>
            <div class="p-4 text-center text-muted">All active employees already have payroll for this period.</div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Basic Salary</th>
                        <th>Overtime (₱)</th>
                        <th>Bonus (₱)</th>
                        <th class="text-muted small">Est. Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                <?php
                    // Preview calculation for UI
                    $basic      = (float)$e['basic_salary'];
                    $allowances = round($basic * 0.05, 2);
                    $gross      = $basic + $allowances;
                    $sss_p = min(1350, round($basic * 0.045, 2));
                    $ph_p  = round($basic * 0.02 / 2, 2);
                    $pi_p  = 100;
                    $tax_p = 0;
                    if ($gross > 33333)     $tax_p = round(($gross-33333)*0.20,2);
                    elseif ($gross > 16667) $tax_p = round(($gross-16667)*0.15,2);
                    $netEst = max(0, $gross - $sss_p - $ph_p - $pi_p - $tax_p);
                ?>
                <tr>
                    <td><input class="form-check-input emp-check" type="checkbox" name="emp_ids[]" value="<?=$e['id']?>"></td>
                    <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($e['firstname'].' '.$e['lastname']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($e['employee_id']) ?></div>
                    </td>
                    <td class="small"><?= htmlspecialchars($e['dept_name'] ?? '—') ?></td>
                    <td class="small"><?= money($basic) ?></td>
                    <td>
                        <input type="number" name="overtime_<?=$e['id']?>" class="form-control form-control-sm"
                               style="width:110px;" min="0" step="0.01" value="0">
                    </td>
                    <td>
                        <input type="number" name="bonus_<?=$e['id']?>" class="form-control form-control-sm"
                               style="width:110px;" min="0" step="0.01" value="0">
                    </td>
                    <td class="small fw-semibold text-success"><?= money($netEst) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($employees)): ?>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary" onclick="return swalProcessPayroll(this.form)">
                <i class="bi bi-calculator me-1"></i>Process Selected Payrolls
            </button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php $extraJs .= <<<'JS'
<script>
function swalProcessPayroll(form) {
    const checked = form.querySelectorAll('.emp-check:checked').length;
    if (!checked) {
        Swal.fire({ icon: 'warning', title: 'No employees selected', text: 'Please select at least one employee to process payroll.' });
        return false;
    }
    Swal.fire({
        title: 'Process Payroll?',
        html: 'You are about to generate payroll for <strong>' + checked + ' employee(s)</strong>. Please review before confirming.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4361ee',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-calculator"></i> Yes, Process',
        cancelButtonText: 'Review Again'
    }).then(r => { if (r.isConfirmed) form.submit(); });
    return false;
}
</script>
JS;
include '../../includes/footer.php'; ?>
