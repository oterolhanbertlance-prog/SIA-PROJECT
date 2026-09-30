<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'New Contract';
$db = getDB();
$errors = [];
$selectedEmployeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$selectedEmployee = null;

if ($selectedEmployeeId) {
    $empLookup = $db->prepare(
        "SELECT id, department_id, position_id, basic_salary
           FROM employees
          WHERE id=? AND status='active'
          LIMIT 1"
    );
    $empLookup->execute([$selectedEmployeeId]);
    $selectedEmployee = $empLookup->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token']??'')) { $errors[] = 'Invalid submission.'; }
    else {
        $empId   = (int)($_POST['employee_id']??0);
        $deptId  = (int)($_POST['department_id']??0) ?: null;
        $posId   = (int)($_POST['position_id']??0) ?: null;
        $start   = sanitize($_POST['start_date']??'');
        $end     = sanitize($_POST['end_date']??'') ?: null;
        $salary  = (float)($_POST['salary']??0);
        $ctype   = sanitize($_POST['contract_type']??'Probationary');
        $notes   = sanitize($_POST['notes']??'');

        if (!$empId || !$start) { $errors[] = 'Employee and start date are required.'; }

        // When a contract is opened from a deployment record, keep its
        // department, position, and salary aligned with that employee unless
        // HR explicitly supplied a different value.
        if ($empId) {
            $empLookup = $db->prepare(
                "SELECT department_id, position_id, basic_salary
                   FROM employees WHERE id=? AND status='active' LIMIT 1"
            );
            $empLookup->execute([$empId]);
            $employeeDefaults = $empLookup->fetch() ?: null;
            if (!$employeeDefaults) {
                $errors[] = 'The selected employee could not be found.';
            } else {
                if (!$deptId) $deptId = (int)($employeeDefaults['department_id'] ?? 0) ?: null;
                if (!$posId)  $posId  = (int)($employeeDefaults['position_id'] ?? 0) ?: null;
                if ($salary <= 0) $salary = (float)($employeeDefaults['basic_salary'] ?? 0);
            }
        }

        if (empty($errors)) {
            $conNum = generateContractNumber();
            $conFile = null;
            if (!empty($_FILES['contract_file']['name'])) {
                $up = uploadFile($_FILES['contract_file'],'contracts');
                if ($up) $conFile = basename($up);
            }
            $db->prepare("INSERT INTO contracts (employee_id,contract_number,contract_type,start_date,end_date,department_id,position_id,salary,file_path,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,'active',?,?)")
               ->execute([$empId,$conNum,$ctype,$start,$end,$deptId,$posId,$salary,$conFile,$notes,currentUser()['id']]);
            auditLog('CREATE','Deployment',"New contract $conNum created");
            header('Location: list.php?success='.urlencode("Contract $conNum created."));
            exit;
        }
    }
}

$employees   = $db->query("SELECT id, CONCAT(firstname,' ',lastname,' (',employee_id,')') as name FROM employees WHERE status='active' ORDER BY firstname")->fetchAll();
$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$positions   = $db->query("SELECT * FROM positions WHERE status='active' ORDER BY title")->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-file-earmark-plus me-2 text-primary"></i>New Contract</h4></div>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" enctype="multipart/form-data" data-validate>
    <?= csrfField() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Employee <span class="text-danger">*</span></label><select name="employee_id" class="form-select" required><option value="">Select Employee</option><?php foreach ($employees as $e): ?><option value="<?=$e['id']?>" <?= $selectedEmployeeId === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Contract Type</label><select name="contract_type" class="form-select"><?php foreach(['Probationary','Regular','Contractual','Fixed-Term'] as $ct): ?><option value="<?=$ct?>"><?=$ct?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Salary (₱)</label><input type="number" name="salary" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($_POST['salary'] ?? ($selectedEmployee['basic_salary'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select</option><?php foreach ($departments as $d): ?><option value="<?=$d['id']?>" <?= ((int)($_POST['department_id'] ?? ($selectedEmployee['department_id'] ?? 0)) === (int)$d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">Select</option><?php foreach ($positions as $p): ?><option value="<?=$p['id']?>" <?= ((int)($_POST['position_id'] ?? ($selectedEmployee['position_id'] ?? 0)) === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-4"><label class="form-label">End Date <small class="text-muted">(if fixed-term)</small></label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">Upload Contract File</label><input type="file" name="contract_file" class="form-control" accept=".pdf,.doc,.docx"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Create Contract</button>
        <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
<?php include '../../includes/footer.php'; ?>
