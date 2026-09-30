<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$pageTitle = 'Schedule Interview';
$db = getDB();
$errors = [];

// Edit mode
$editId = (int)($_GET['edit'] ?? 0);
$editIv = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM interviews WHERE id=?");
    $s->execute([$editId]);
    $editIv = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid submission.'; }
    else {
        $d = [
            'applicant_id'    => (int)($_POST['applicant_id'] ?? 0),
            'interview_type'  => sanitize($_POST['interview_type'] ?? 'initial'),
            'interview_date'  => sanitize($_POST['interview_date'] ?? ''),
            'interviewer_name'=> sanitize($_POST['interviewer_name'] ?? ''),
            'location'        => sanitize($_POST['location'] ?? ''),
            'mode'            => sanitize($_POST['mode'] ?? 'in-person'),
            'notes'           => sanitize($_POST['notes'] ?? ''),
            'status'          => 'scheduled',
        ];
        if (!$d['applicant_id']) $errors[] = 'Applicant is required.';
        if (!$d['interview_date']) $errors[] = 'Interview date is required.';

        if (empty($errors)) {
            if ($editId) {
                $db->prepare("UPDATE interviews SET applicant_id=?,interview_type=?,interview_date=?,interviewer_name=?,location=?,mode=?,notes=?,status=? WHERE id=?")
                   ->execute([$d['applicant_id'],$d['interview_type'],$d['interview_date'],$d['interviewer_name'],$d['location'],$d['mode'],$d['notes'],$d['status'],$editId]);
                // Update applicant status
                $typeMap = ['initial'=>'initial_interview','final'=>'final_interview'];
                if (isset($typeMap[$d['interview_type']])) {
                    $db->prepare("UPDATE applicants SET status=? WHERE id=?")->execute([$typeMap[$d['interview_type']],$d['applicant_id']]);
                }
                header('Location: list.php?success='.urlencode("Interview updated successfully."));
            } else {
                $db->prepare("INSERT INTO interviews (applicant_id,interview_type,interview_date,interviewer_name,location,mode,notes,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$d['applicant_id'],$d['interview_type'],$d['interview_date'],$d['interviewer_name'],$d['location'],$d['mode'],$d['notes'],'scheduled',currentUser()['id']]);
                $typeMap = ['initial'=>'initial_interview','final'=>'final_interview'];
                if (isset($typeMap[$d['interview_type']])) {
                    $db->prepare("UPDATE applicants SET status=? WHERE id=?")->execute([$typeMap[$d['interview_type']],$d['applicant_id']]);
                }
                auditLog('CREATE','Interviews',"Scheduled {$d['interview_type']} interview for applicant #{$d['applicant_id']}");
                header('Location: list.php?success='.urlencode("Interview scheduled successfully."));
            }
            exit;
        }
    }
}

$preApplicantId = (int)($_GET['applicant_id'] ?? 0);
$applicants = $db->query("SELECT a.id, CONCAT(a.firstname,' ',a.lastname,' (',IFNULL(j.title,'No job'),')') as label FROM applicants a LEFT JOIN job_postings j ON a.job_id=j.id WHERE a.status NOT IN ('hired','withdrawn') ORDER BY a.firstname")->fetchAll();
$employees  = $db->query("SELECT CONCAT(firstname,' ',lastname) as name FROM employees WHERE status='active' ORDER BY firstname")->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-calendar-plus me-2 text-primary"></i><?= $editId ? 'Edit Interview' : 'Schedule Interview' ?></h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="list.php">Interviews</a></li><li class="breadcrumb-item active"><?= $editId ? 'Edit' : 'Schedule' ?></li></ol></nav>
    </div>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<div class="card" style="max-width:700px;"><div class="card-body">
<form method="POST" data-validate>
    <?= csrfField() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Applicant <span class="text-danger">*</span></label>
            <select name="applicant_id" class="form-select" required>
                <option value="">Select Applicant</option>
                <?php foreach ($applicants as $a): ?><option value="<?=$a['id']?>" <?=($editIv['applicant_id']??$preApplicantId)==$a['id']?'selected':''?>><?= htmlspecialchars($a['label']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Interview Type <span class="text-danger">*</span></label>
            <select name="interview_type" class="form-select" required>
                <?php foreach(['initial'=>'Initial Interview','final'=>'Final Interview','technical'=>'Technical Interview','hr'=>'HR Interview'] as $v=>$l): ?>
                <option value="<?=$v?>" <?=($editIv['interview_type']??'initial')==$v?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Date & Time <span class="text-danger">*</span></label>
            <input type="datetime-local" name="interview_date" class="form-control" required value="<?= $editIv ? date('Y-m-d\TH:i', strtotime($editIv['interview_date'])) : '' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Interviewer</label>
            <input type="text" name="interviewer_name" class="form-control" list="interviewers" value="<?= htmlspecialchars($editIv['interviewer_name']??'') ?>" placeholder="Interviewer name">
            <datalist id="interviewers"><?php foreach ($employees as $e): ?><option value="<?= htmlspecialchars($e['name']) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="col-md-6">
            <label class="form-label">Mode</label>
            <select name="mode" class="form-select">
                <?php foreach(['in-person'=>'In-Person','online'=>'Online','phone'=>'Phone'] as $v=>$l): ?><option value="<?=$v?>" <?=($editIv['mode']??'in-person')==$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Location / Meeting Link</label>
            <input type="text" name="location" class="form-control" placeholder="Conference Room A / Zoom link..." value="<?= htmlspecialchars($editIv['location']??'') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($editIv['notes']??'') ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check me-1"></i><?= $editId ? 'Update Interview' : 'Schedule Interview' ?></button>
        <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
<?php include '../../includes/footer.php'; ?>
