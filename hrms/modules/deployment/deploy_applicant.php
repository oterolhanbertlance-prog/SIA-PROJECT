<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../config/db_connect.php';
require_once '../../integration/pos_common.php';
require_once '../../includes/mailer.php';
requireRole('admin','hr');

$pageTitle = 'Deploy Applicant';
$db = getDB();
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
    $db->exec("ALTER TABLE users MODIFY COLUMN pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
$errors = [];

$applicantId = (int)($_GET['applicant_id'] ?? 0);
$applicant = null;
if ($applicantId) {
    $stmt = $db->prepare("SELECT a.*, j.title as job_title,
                                 COALESCE(j.department_id, p.department_id) AS department_id,
                                 COALESCE(j.position_id, p.id) AS position_id,
                                 p.title AS position_title
                          FROM applicants a
                          LEFT JOIN job_postings j ON a.job_id=j.id
                           LEFT JOIN positions p ON p.id=j.position_id
                                                  OR (j.position_id IS NULL AND p.title=j.title)
                          WHERE a.id=?");
    $stmt->execute([$applicantId]);
    $applicant = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token']??'')) { $errors[] = 'Invalid submission.'; }
    else {
        $appId    = (int)($_POST['applicant_id']??0);
        $deptId   = (int)($_POST['department_id']??0) ?: null;
        $posId    = (int)($_POST['position_id']??0) ?: null;
        $startDate= sanitize($_POST['start_date']??'');
        $salary   = (float)($_POST['salary']??0);
        $empStatus= sanitize($_POST['employment_status']??'Probationary');
        $contractType = sanitize($_POST['contract_type']??'Probationary');
        $endDate  = sanitize($_POST['end_date']??'') ?: null;
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $appStmt = $db->prepare(
            "SELECT a.*, j.title AS job_title, j.branch_id AS job_branch_id,
                    COALESCE(j.department_id, p.department_id) AS job_department_id,
                    COALESCE(j.position_id, p.id) AS job_position_id,
                    p.title AS position_title
               FROM applicants a
               LEFT JOIN job_postings j ON j.id=a.job_id
               LEFT JOIN positions p ON p.id=j.position_id
                                      OR (j.position_id IS NULL AND p.title=j.title)
              WHERE a.id=?"
        );
        $appStmt->execute([$appId]);
        $app = $appStmt->fetch();

        $posTitle = is_array($app) ? ($app['position_title'] ?? ($app['job_title'] ?? '')) : '';
        $posRole = posRoleFromJobTitle($posTitle);
        // The integration tag is the source of truth when HR explicitly
        // assigned a POS role. This prevents a stale/mismatched position
        // title from silently turning Inventory Staff into Cashier.
        if ($app && !empty($app['job_id'])) {
            try {
                $tag = $db->prepare("SELECT pos_role_target FROM pos_job_postings_meta WHERE job_posting_id=? LIMIT 1");
                $tag->execute([(int)$app['job_id']]);
                $taggedRole = $tag->fetchColumn();
                if (in_array($taggedRole, ['super_admin','cashier','inventory_staff'], true)) {
                    $posRole = $taggedRole;
                }
            } catch (Throwable $e) {}
        }
        if (!$app) { $errors[] = 'Applicant not found.'; }
        elseif (!$posRole) { $errors[] = 'Only POS Super Admin, Cashier, and Inventory Staff applicants can be hired into the POS-linked HRMS.'; }
        if ($app && $posRole) {
            // Never allow a manipulated form to change the role attached to
            // the application that HR is approving.
            $deptId = (int)($app['job_department_id'] ?? $app['department_id'] ?? $deptId) ?: null;
            $posId  = (int)($app['job_position_id'] ?? $app['position_id'] ?? $posId) ?: null;
        }
        if (!$username || !preg_match('/^[a-zA-Z0-9._]+$/', $username)) { $errors[] = 'A valid username (letters, numbers, dots, underscores) is required to create the employee login.'; }
        if (!$password || strlen($password) < 6) { $errors[] = 'A password of at least 6 characters is required to create the employee login.'; }
        if ($username) {
            $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) { $errors[] = 'That username is already taken. Choose another.'; }
        }

        if (empty($errors)) {
            // Create employee — assigned to the branch the job posting was for.
            $empId = generateEmployeeId();
            $branchId = (int)($app['job_branch_id'] ?? $app['branch_id'] ?? 0) ?: null;
            $db->prepare("INSERT INTO employees (employee_id,firstname,lastname,middlename,email,phone,address,gender,department_id,position_id,branch_id,employment_status,date_hired,basic_salary,highest_education,school,course,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')")
               ->execute([$empId,$app['firstname'],$app['lastname'],$app['middlename'],$app['email'],$app['phone'],$app['address'],$app['gender'],$deptId,$posId,$branchId,$empStatus,$startDate,$salary,$app['highest_education'],$app['school'],$app['course']]);
            $newEmpDbId = $db->lastInsertId();

            // Initialize leave balances
            $leaveTypes = $db->query("SELECT id, days_allowed FROM leave_types WHERE status='active'")->fetchAll();
            $lbStmt = $db->prepare("INSERT IGNORE INTO leave_balance (employee_id,leave_type_id,year,total_days,used_days,remaining_days) VALUES (?,?,?,?,0,?)");
            foreach ($leaveTypes as $lt) $lbStmt->execute([$newEmpDbId,$lt['id'],date('Y'),$lt['days_allowed'],$lt['days_allowed']]);

            // Create contract
            $conNum = generateContractNumber();
            $conFile = null;
            if (!empty($_FILES['contract_file']['name'])) {
                $up = uploadFile($_FILES['contract_file'],'contracts',['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
                if ($up) $conFile = basename($up);
            }
            $db->prepare("INSERT INTO contracts (employee_id,contract_number,contract_type,start_date,end_date,department_id,position_id,salary,file_path,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,'active',?)")
               ->execute([$newEmpDbId,$conNum,$contractType,$startDate,$endDate,$deptId,$posId,$salary,$conFile,currentUser()['id']]);

            // Create the employee's login account, already approved since HR vetted them through the hiring pipeline
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare(
                "INSERT INTO users (username,password,email,role,pos_role,firstname,lastname,phone,employee_id,status,approved_by,approved_at,created_at)
                 VALUES (?,?,?,'employee',?,?,?,?,?, 'active', ?, NOW(), NOW())"
            )->execute([$username,$hash,$app['email'],$posRole,$app['firstname'],$app['lastname'],$app['phone'],$newEmpDbId,currentUser()['id']]);
            $newHrmsUserId = (int)$db->lastInsertId();

            // A hire is also a POS user.  The same bcrypt hash gives the
            // employee one login identity across both applications.
            $pos = getPosDB();
            $posUserId = syncHrmsUserToPos($pos, [
                'id' => $newHrmsUserId, 'username' => $username, 'password' => $hash,
                'email' => $app['email'], 'firstname' => $app['firstname'], 'lastname' => $app['lastname'],
            ], $posRole, (int)$newEmpDbId);

            // Tag the POS account with the same branch (both branches tables share ids).
            if ($branchId) {
                try { $pos->prepare("UPDATE users SET branch_id=? WHERE id=?")->execute([$branchId, $posUserId]); }
                catch (Throwable $e) {}
            }

            // Update applicant status to hired
            $db->prepare("UPDATE applicants SET status='hired' WHERE id=?")->execute([$appId]);

            // If the applicant registered through the self-service portal, store the
            // plain-text credentials in their applicant account so the portal can display them,
            // and send them a notification with the login details.
            if (!empty($app['applicant_account_id'])) {
                $db->prepare(
                    "UPDATE applicant_accounts
                     SET hired_username = ?, hired_temp_password = ?, hired_employee_id = ?
                     WHERE id = ?"
                )->execute([$username, $password, $empId, $app['applicant_account_id']]);

                notifyApplicant(
                    $app['applicant_account_id'],
                    "🎉 Congratulations — You're Hired!",
                    "Your application has been approved and your HRMS employee account is ready.\n\n"
                    . "  • Employee ID : $empId\n"
                    . "  • Username    : $username\n"
                    . "  • Password    : $password (temporary — please change after first login)\n\n"
                    . "Log in at: " . SITE_URL . "index.php\n\n"
                    . "Welcome to the team!",
                    'success'
                );
            }

            sendSmtpEmail($app['email'], 'Welcome to Boba House — POS access is ready',
                "Welcome {$app['firstname']} {$app['lastname']}!\n\n"
                . "Your HRMS and POS accounts are ready.\n"
                . "Employee ID: {$empId}\nUsername: {$username}\nTemporary password: {$password}\n"
                . "POS role: {$posRole}\n\nPlease change your password after your first login.");
            auditLog('CREATE','Deployment',"Deployed applicant {$app['firstname']} {$app['lastname']} as employee $empId and POS {$posRole} user");
            header("Location: ../employees/profile.php?id=$newEmpDbId&success=".urlencode("Employee $empId created with HRMS + POS access. Login: $username / $password — share with the new hire."));
            exit;
        }
    }
}

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$positions   = $db->query("SELECT * FROM positions WHERE status='active' AND title IN ('POS Super Admin','Cashier','Inventory Staff') ORDER BY FIELD(title,'POS Super Admin','Cashier','Inventory Staff')")->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-person-badge me-2 text-primary"></i>Deploy Applicant as Employee</h4></div>
    <a href="../recruitment/applicants.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<?php if ($applicant): ?>
<div class="alert alert-info"><i class="bi bi-person-check me-2"></i>Deploying: <strong><?= htmlspecialchars($applicant['firstname'].' '.$applicant['lastname']) ?></strong> — <?= htmlspecialchars($applicant['job_title']??'General') ?></div>
<?php endif; ?>

<div class="card"><div class="card-body">
<form method="POST" enctype="multipart/form-data" data-validate>
    <?= csrfField() ?>
    <input type="hidden" name="applicant_id" value="<?= $applicantId ?>">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select</option><?php foreach ($departments as $d): ?><option value="<?=$d['id']?>" <?=($applicant['department_id']??'')==$d['id']?'selected':''?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">Select</option><?php foreach ($positions as $p): ?><option value="<?=$p['id']?>" <?=($applicant['position_id']??'')==$p['id']?'selected':''?>><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Employment Status</label><select name="employment_status" class="form-select"><option value="Probationary">Probationary</option><option value="Regular">Regular</option><option value="Contractual">Contractual</option></select></div>
        <div class="col-md-4"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-4"><label class="form-label">Basic Salary (₱) <span class="text-danger">*</span></label><input type="number" name="salary" class="form-control" required min="0" step="0.01"></div>
        <div class="col-md-4"><label class="form-label">Contract Type</label><select name="contract_type" class="form-select"><?php foreach(['Probationary','Regular','Contractual','Fixed-Term'] as $ct): ?><option value="<?=$ct?>"><?=$ct?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Contract End Date <small class="text-muted">(if applicable)</small></label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">Upload Signed Contract (PDF/DOC)</label><input type="file" name="contract_file" class="form-control" accept=".pdf,.doc,.docx"></div>
    </div>
    <hr class="my-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-key me-2 text-primary"></i>Create Employee Login</h6>
    <p class="text-muted small mb-3">This creates their HRMS account as an <strong>employee</strong> and a linked POS account using the approved POS role. Share these credentials with them after deployment.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required pattern="[a-zA-Z0-9._]+"
                   value="<?= $applicant ? htmlspecialchars(strtolower($applicant['firstname'][0] . $applicant['lastname'])) : '' ?>">
            <div class="form-text">Letters, numbers, dots, underscores only.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
            <input type="text" name="password" class="form-control" required minlength="6"
                   value="<?= substr(bin2hex(random_bytes(4)), 0, 8) ?>">
            <div class="form-text">Auto-generated — feel free to change it.</div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success px-4"><i class="bi bi-person-badge me-1"></i>Deploy as Employee</button>
        <a href="../recruitment/applicants.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
<?php include '../../includes/footer.php'; ?>
