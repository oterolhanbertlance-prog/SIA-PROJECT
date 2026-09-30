<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Add Applicant';
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $d = [];
        foreach (['job_id','firstname','lastname','middlename','email','phone','address','gender','highest_education','school','course','years_experience','skills','cover_letter'] as $f) {
            $d[$f] = sanitize($_POST[$f] ?? '');
        }
        if (empty($d['firstname'])) $errors[] = 'First name required.';
        if (empty($d['email'])) $errors[] = 'Email required.';
        elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

        // Prevent the same person applying twice for the same job
        if (empty($errors) && $d['job_id']) {
            $dupChk = $db->prepare("SELECT id FROM applicants WHERE email=? AND job_id=?");
            $dupChk->execute([$d['email'], $d['job_id']]);
            if ($dupChk->fetch()) $errors[] = 'This applicant has already applied for the selected job.';
        }

        if (empty($errors)) {
            $code = generateApplicantCode();
            $resume = null;
            if (!empty($_FILES['resume']['name'])) {
                $up = uploadFile($_FILES['resume'], 'resumes', ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
                if ($up) $resume = basename($up);
            }
            $db->prepare("INSERT INTO applicants (applicant_code,job_id,firstname,lastname,middlename,email,phone,address,gender,highest_education,school,course,years_experience,skills,cover_letter,resume,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')")
               ->execute([$code,$d['job_id']?:null,$d['firstname'],$d['lastname'],$d['middlename'],$d['email'],$d['phone'],$d['address'],$d['gender'],$d['highest_education'],$d['school'],$d['course'],$d['years_experience']?:0,$d['skills'],$d['cover_letter']]);
            // resume fix
            $newId = $db->lastInsertId();
            if ($resume) $db->prepare("UPDATE applicants SET resume=? WHERE id=?")->execute([$resume,$newId]);
            auditLog('CREATE','Recruitment',"Added applicant: $code - {$d['firstname']} {$d['lastname']}");
            header('Location: applicants.php?success='.urlencode("Applicant $code added successfully."));
            exit;
        }
    }
}

$jobs = $db->query("SELECT id, title FROM job_postings WHERE status='open' ORDER BY title")->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header">
    <div><h4><i class="bi bi-person-plus me-2 text-primary"></i>Add Applicant</h4></div>
    <a href="applicants.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="POST" enctype="multipart/form-data" data-validate>
    <?= csrfField() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Job Applying For</label><select name="job_id" class="form-select"><option value="">Select Job</option><?php foreach ($jobs as $j): ?><option value="<?=$j['id']?>"><?= htmlspecialchars($j['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="firstname" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" name="lastname" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Middle Name</label><input type="text" name="middlename" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select</option><?php foreach(['Male','Female','Other'] as $g): ?><option value="<?=$g?>"><?=$g?></option><?php endforeach; ?></select></div>
        <div class="col-md-8"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Years of Experience</label><input type="number" name="years_experience" class="form-control" min="0" value="0"></div>
        <div class="col-md-4"><label class="form-label">Highest Education</label><select name="highest_education" class="form-select"><option value="">Select</option><?php foreach(["High School","Vocational","Bachelor's Degree","Master's Degree","Doctorate"] as $e): ?><option value="<?=$e?>"><?=$e?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">School</label><input type="text" name="school" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Course</label><input type="text" name="course" class="form-control"></div>
        <div class="col-12"><label class="form-label">Skills</label><textarea name="skills" class="form-control" rows="2"></textarea></div>
        <div class="col-12"><label class="form-label">Cover Letter</label><textarea name="cover_letter" class="form-control" rows="3"></textarea></div>
        <div class="col-md-6"><label class="form-label">Resume (PDF/DOC)</label><input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Applicant</button>
        <a href="applicants.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
<?php include '../../includes/footer.php'; ?>
