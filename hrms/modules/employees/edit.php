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

$pageTitle = 'Edit Employee';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $fields = ['firstname','lastname','middlename','gender','birthdate','civil_status','email','phone','address','city','province','postal_code','emergency_contact_name','emergency_contact_phone','emergency_contact_relation','department_id','position_id','employment_status','employment_type','date_hired','date_regularized','basic_salary','sss_no','philhealth_no','pagibig_no','tin_no','highest_education','school','course','grad_year','work_experience','skills'];
        $data = [];
        foreach ($fields as $f) $data[$f] = sanitize($_POST[$f] ?? '');

        if (empty($data['firstname'])) $errors[] = 'First name is required.';
        if (empty($data['email'])) $errors[] = 'Email is required.';
        $chk = $db->prepare("SELECT id FROM employees WHERE email=? AND id!=?");
        $chk->execute([$data['email'],$id]);
        if ($chk->fetch()) $errors[] = 'Email already used by another employee.';

        if (empty($errors)) {
            $profilePic = $emp['profile_picture'];
            if (!empty($_FILES['profile_picture']['name'])) {
                $uploaded = uploadFile($_FILES['profile_picture'], 'profiles', ['image/jpeg','image/png','image/gif','image/webp']);
                if ($uploaded) $profilePic = basename($uploaded);
            }

            $sql = "UPDATE employees SET firstname=?,lastname=?,middlename=?,gender=?,birthdate=?,civil_status=?,email=?,phone=?,address=?,city=?,province=?,postal_code=?,emergency_contact_name=?,emergency_contact_phone=?,emergency_contact_relation=?,department_id=?,position_id=?,employment_status=?,employment_type=?,date_hired=?,date_regularized=?,basic_salary=?,sss_no=?,philhealth_no=?,pagibig_no=?,tin_no=?,profile_picture=?,highest_education=?,school=?,course=?,grad_year=?,work_experience=?,skills=? WHERE id=?";
            $db->prepare($sql)->execute([
                $data['firstname'],$data['lastname'],$data['middlename'],$data['gender'],$data['birthdate']?:null,$data['civil_status'],
                $data['email'],$data['phone'],$data['address'],$data['city'],$data['province'],$data['postal_code'],
                $data['emergency_contact_name'],$data['emergency_contact_phone'],$data['emergency_contact_relation'],
                $data['department_id']?:null,$data['position_id']?:null,$data['employment_status'],$data['employment_type'],
                $data['date_hired']?:null,$data['date_regularized']?:null,$data['basic_salary']?:0,
                $data['sss_no'],$data['philhealth_no'],$data['pagibig_no'],$data['tin_no'],$profilePic,
                $data['highest_education'],$data['school'],$data['course'],$data['grad_year']?:null,$data['work_experience'],$data['skills'],
                $id
            ]);
            auditLog('UPDATE','Employee',"Updated employee: {$emp['employee_id']} - {$data['firstname']} {$data['lastname']}");
            header("Location: profile.php?id=$id&success=Employee+updated+successfully");
            exit;
        }
        $emp = array_merge($emp, $data);
    }
}

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$positions   = $db->query("SELECT * FROM positions WHERE status='active' ORDER BY title")->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Employee</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="list.php">Employees</a></li><li class="breadcrumb-item active">Edit</li></ol></nav>
    </div>
    <a href="profile.php?id=<?=$id?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<form method="POST" enctype="multipart/form-data" data-validate>
    <?= csrfField() ?>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-camera me-2"></i>Profile Picture</div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-4">
                <?php $picPath = $emp['profile_picture'] && $emp['profile_picture']!='default.png' ? SITE_URL.'uploads/profiles/'.$emp['profile_picture'] : SITE_URL.'assets/images/default.png'; ?>
                <img id="previewImg" src="<?= $picPath ?>" class="avatar-xl rounded-circle border" style="object-fit:cover;">
                <div>
                    <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewImage(this,'previewImg')">
                    <small class="text-muted">Leave blank to keep current picture</small>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-person me-2"></i>Personal Information</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="firstname" class="form-control" required value="<?= htmlspecialchars($emp['firstname']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" name="lastname" class="form-control" required value="<?= htmlspecialchars($emp['lastname']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middlename" class="form-control" value="<?= htmlspecialchars($emp['middlename']??'') ?>"></div>
                <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><?php foreach(['Male','Female','Other'] as $g): ?><option value="<?=$g?>" <?=$emp['gender']==$g?'selected':''?>><?=$g?></option><?php endforeach;?></select></div>
                <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($emp['birthdate']??'') ?>"></div>
                <div class="col-md-3"><label class="form-label">Civil Status</label><select name="civil_status" class="form-select"><?php foreach(['Single','Married','Widowed','Separated'] as $s): ?><option value="<?=$s?>" <?=$emp['civil_status']==$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
                <div class="col-md-3"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($emp['nationality']??'Filipino') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($emp['email']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($emp['phone']??'') ?>"></div>
                <div class="col-md-8"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($emp['city']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Province</label><input type="text" name="province" class="form-control" value="<?= htmlspecialchars($emp['province']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Postal Code</label><input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($emp['postal_code']??'') ?>"></div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-telephone-plus me-2"></i>Emergency Contact</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label">Contact Name</label><input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_name']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_phone']??'') ?>"></div>
                <div class="col-md-3"><label class="form-label">Relationship</label><input type="text" name="emergency_contact_relation" class="form-control" value="<?= htmlspecialchars($emp['emergency_contact_relation']??'') ?>"></div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-briefcase me-2"></i>Employment Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select</option><?php foreach ($departments as $d): ?><option value="<?=$d['id']?>" <?=$emp['department_id']==$d['id']?'selected':''?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">Select</option><?php foreach ($positions as $p): ?><option value="<?=$p['id']?>" <?=$emp['position_id']==$p['id']?'selected':''?>><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Employment Status</label><select name="employment_status" class="form-select"><?php foreach(['Probationary','Regular','Contractual','Part-Time','Resigned','Terminated'] as $s): ?><option value="<?=$s?>" <?=$emp['employment_status']==$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
                <div class="col-md-4"><label class="form-label">Employment Type</label><select name="employment_type" class="form-select"><?php foreach(['Full-Time','Part-Time','Contract'] as $s): ?><option value="<?=$s?>" <?=$emp['employment_type']==$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
                <div class="col-md-4"><label class="form-label">Date Hired</label><input type="date" name="date_hired" class="form-control" value="<?= htmlspecialchars($emp['date_hired']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Date Regularized</label><input type="date" name="date_regularized" class="form-control" value="<?= htmlspecialchars($emp['date_regularized']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Basic Salary (₱)</label><input type="number" name="basic_salary" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($emp['basic_salary']??'0') ?>"></div>
                <div class="col-md-4"><label class="form-label">SSS No.</label><input type="text" name="sss_no" class="form-control" value="<?= htmlspecialchars($emp['sss_no']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">PhilHealth No.</label><input type="text" name="philhealth_no" class="form-control" value="<?= htmlspecialchars($emp['philhealth_no']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Pag-IBIG No.</label><input type="text" name="pagibig_no" class="form-control" value="<?= htmlspecialchars($emp['pagibig_no']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">TIN No.</label><input type="text" name="tin_no" class="form-control" value="<?= htmlspecialchars($emp['tin_no']??'') ?>"></div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-mortarboard me-2"></i>Education & Background</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Highest Education</label><select name="highest_education" class="form-select"><option value="">Select</option><?php foreach(["High School","Vocational","Bachelor's Degree","Master's Degree","Doctorate"] as $e): ?><option value="<?=$e?>" <?=$emp['highest_education']==$e?'selected':''?>><?=$e?></option><?php endforeach;?></select></div>
                <div class="col-md-4"><label class="form-label">School</label><input type="text" name="school" class="form-control" value="<?= htmlspecialchars($emp['school']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Course</label><input type="text" name="course" class="form-control" value="<?= htmlspecialchars($emp['course']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Work Experience</label><textarea name="work_experience" class="form-control" rows="3"><?= htmlspecialchars($emp['work_experience']??'') ?></textarea></div>
                <div class="col-12"><label class="form-label">Skills</label><textarea name="skills" class="form-control" rows="2"><?= htmlspecialchars($emp['skills']??'') ?></textarea></div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Update Employee</button>
        <a href="profile.php?id=<?=$id?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
<?php include '../../includes/footer.php'; ?>
