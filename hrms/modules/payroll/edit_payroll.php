<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Edit Payroll';
$db = getDB();
$db->exec("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tax_deduction");
$errors = [];

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT p.*, e.firstname, e.lastname, e.employee_id as emp_code FROM payroll p JOIN employees e ON p.employee_id=e.id WHERE p.id=?");
$stmt->execute([$id]);
$payroll = $stmt->fetch();
if (!$payroll) { header('Location: list.php?error=Not+found'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $basic      = (float)($_POST['basic_salary'] ?? 0);
        $allowances = (float)($_POST['allowances']   ?? 0);
        $overtime   = max(0, (float)($_POST['overtime_pay'] ?? 0));
        $bonuses    = max(0, (float)($_POST['bonuses']      ?? 0));
        $sss        = max(0, (float)($_POST['sss_deduction']        ?? 0));
        $ph         = max(0, (float)($_POST['philhealth_deduction'] ?? 0));
        $pi         = max(0, (float)($_POST['pagibig_deduction']    ?? 0));
        $tax        = max(0, (float)($_POST['tax_deduction']        ?? 0));
        $attendance = max(0, (float)($_POST['attendance_deduction'] ?? ($payroll['attendance_deduction'] ?? 0)));
        $other      = max(0, (float)($_POST['other_deductions']     ?? 0));
        $days       = max(0, (float)($_POST['days_worked'] ?? 22));
        $status     = in_array($_POST['status'] ?? '', ['draft','processed','released']) ? $_POST['status'] : 'processed';
        $remarks    = sanitize($_POST['remarks'] ?? '');

        if ($basic <= 0) $errors[] = 'Basic salary must be greater than zero.';

        if (empty($errors)) {
            $gross    = $basic + $allowances + $overtime + $bonuses;
            $totalDed = $sss + $ph + $pi + $tax + $attendance + $other;
            // ── FIX: Prevent negative net pay ──
            $net      = max(0, round($gross - $totalDed, 2));
            if ($gross - $totalDed < 0) {
                $totalDed = $gross; // cap deductions
            }

            $db->prepare(
                "UPDATE payroll SET
                 basic_salary=?, allowances=?, overtime_pay=?, bonuses=?, gross_pay=?,
                 sss_deduction=?, philhealth_deduction=?, pagibig_deduction=?, tax_deduction=?, attendance_deduction=?,
                 other_deductions=?, total_deductions=?, net_pay=?, days_worked=?, status=?, remarks=?
                 WHERE id=?"
            )->execute([
                $basic, $allowances, $overtime, $bonuses, $gross,
                $sss, $ph, $pi, $tax, $attendance, $other, $totalDed, $net, $days, $status, $remarks, $id
            ]);
            auditLog('UPDATE', 'Payroll', "Edited payroll #$id for {$payroll['firstname']} {$payroll['lastname']}");
            header('Location: list.php?month='.$payroll['payroll_month'].'&year='.$payroll['payroll_year'].'&success='.urlencode("Payroll updated successfully."));
            exit;
        }
    }
}

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-pencil me-2 text-warning"></i>Edit Payroll</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="list.php">Payroll</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol></nav>
    </div>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-2"></i>
    Net Pay = Gross Pay − Total Deductions. Net pay is automatically capped at <strong>₱0.00</strong> (cannot be negative).
</div>

<div class="card" style="max-width:800px;">
    <div class="card-header fw-semibold">
        <?= htmlspecialchars($payroll['firstname'].' '.$payroll['lastname']) ?>
        &nbsp;&mdash;&nbsp;<?= monthName($payroll['payroll_month']) ?> <?= $payroll['payroll_year'] ?>
    </div>
    <div class="card-body">
        <form method="POST" data-validate id="payrollEditForm">
            <?= csrfField() ?>
            <h6 class="fw-semibold mb-3 text-muted">EARNINGS</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><label class="form-label">Basic Salary <span class="text-danger">*</span></label><input type="number" name="basic_salary" class="form-control earning-input" step="0.01" min="0.01" required value="<?= $payroll['basic_salary'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Allowances</label><input type="number" name="allowances" class="form-control earning-input" step="0.01" min="0" value="<?= $payroll['allowances'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Overtime Pay</label><input type="number" name="overtime_pay" class="form-control earning-input" step="0.01" min="0" value="<?= $payroll['overtime_pay'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Bonuses</label><input type="number" name="bonuses" class="form-control earning-input" step="0.01" min="0" value="<?= $payroll['bonuses'] ?>"></div>
            </div>

            <h6 class="fw-semibold mb-3 text-muted">DEDUCTIONS</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><label class="form-label">SSS</label><input type="number" name="sss_deduction" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['sss_deduction'] ?>"></div>
                <div class="col-md-3"><label class="form-label">PhilHealth</label><input type="number" name="philhealth_deduction" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['philhealth_deduction'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Pag-IBIG</label><input type="number" name="pagibig_deduction" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['pagibig_deduction'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Withholding Tax</label><input type="number" name="tax_deduction" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['tax_deduction'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Attendance Deduction</label><input type="number" name="attendance_deduction" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['attendance_deduction'] ?? 0 ?>"></div>
                <div class="col-md-3"><label class="form-label">Other Deductions</label><input type="number" name="other_deductions" class="form-control deduct-input" step="0.01" min="0" value="<?= $payroll['other_deductions'] ?? 0 ?>"></div>
            </div>

            <!-- Live summary -->
            <div class="card bg-light mb-4">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="text-muted small">Gross Pay</div>
                            <div class="fw-bold text-primary" id="previewGross"><?= money($payroll['gross_pay']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Total Deductions</div>
                            <div class="fw-bold text-danger" id="previewDed"><?= money($payroll['total_deductions']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Net Pay</div>
                            <div class="fw-bold text-success" id="previewNet"><?= money($payroll['net_pay']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="form-label">Days Worked</label><input type="number" name="days_worked" class="form-control" step="0.5" min="0" max="31" value="<?= $payroll['days_worked'] ?>"></div>
                <div class="col-md-3"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['draft','processed','released'] as $s): ?>
                        <option value="<?=$s?>" <?=$payroll['status']===$s?'selected':''?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control" value="<?= htmlspecialchars($payroll['remarks'] ?? '') ?>"></div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Save Changes</button>
                <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function recalc() {
    const earn = [...document.querySelectorAll('.earning-input')].reduce((s,i)=>s+Math.max(0,parseFloat(i.value)||0),0);
    const ded  = [...document.querySelectorAll('.deduct-input')].reduce((s,i)=>s+Math.max(0,parseFloat(i.value)||0),0);
    const net  = Math.max(0, earn - ded);
    const fmt  = v => '₱ ' + v.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('previewGross').textContent = fmt(earn);
    document.getElementById('previewDed').textContent   = fmt(ded);
    document.getElementById('previewNet').textContent    = fmt(net);
    document.getElementById('previewNet').style.color = net === 0 ? '#dc3545' : '#198754';
}
document.querySelectorAll('.earning-input, .deduct-input').forEach(i => i.addEventListener('input', recalc));
</script>
<?php include '../../includes/footer.php'; ?>
