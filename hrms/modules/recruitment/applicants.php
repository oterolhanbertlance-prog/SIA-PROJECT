<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../integration/pos_common.php';
requireRole('admin','hr');

$pageTitle = 'Applicants';
$db = getDB();

$jobId  = (int)($_GET['job_id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15; $offset = ($page-1)*$perPage;

$where = ["1=1"]; $params = [];
if ($jobId)  { $where[] = "a.job_id=?"; $params[] = $jobId; }
if ($search) { $where[] = "(a.firstname LIKE ? OR a.lastname LIKE ? OR a.email LIKE ? OR a.applicant_code LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }
if ($status) { $where[] = "a.status=?"; $params[] = $status; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM applicants a $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT a.*, j.title as job_title, b.name as branch_name FROM applicants a LEFT JOIN job_postings j ON a.job_id=j.id LEFT JOIN branches b ON a.branch_id=b.id $whereStr ORDER BY a.applied_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$applicants = $stmt->fetchAll();

$jobs = $db->query("SELECT id, title FROM job_postings ORDER BY title")->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verifyCSRF($_POST['csrf_token'] ?? '')) {
        $aId = (int)$_POST['applicant_id'];
        $newStatus = sanitize($_POST['new_status']);
        $validStatuses = ['pending','initial_interview','final_interview','passed','failed','hired','withdrawn'];
        if (in_array($newStatus, $validStatuses)) {
            $db->prepare("UPDATE applicants SET status=? WHERE id=?")->execute([$newStatus,$aId]);
            auditLog('UPDATE','Recruitment',"Applicant #$aId status updated to $newStatus");
            $successMsg = 'Status updated';
            if (in_array($newStatus, ['passed', 'hired'], true)) {
                $roleStmt = $db->prepare("SELECT a.job_id, j.title AS job_title FROM applicants a LEFT JOIN job_postings j ON j.id=a.job_id WHERE a.id=?");
                $roleStmt->execute([$aId]);
                $passedApplicant = $roleStmt->fetch() ?: [];
                $posRole = posRoleFromJobTitle($passedApplicant['job_title'] ?? '');
                try {
                    $tag = $db->prepare("SELECT pos_role_target FROM pos_job_postings_meta WHERE job_posting_id=? LIMIT 1");
                    $tag->execute([(int)($passedApplicant['job_id'] ?? 0)]);
                    $taggedRole = $tag->fetchColumn();
                    if (in_array($taggedRole, ['super_admin','cashier','inventory_staff'], true)) $posRole = $taggedRole;
                } catch (Throwable $e) {}
                if ($newStatus === 'passed' && $posRole) {
                    $successMsg = "Applicant accepted! Deploy them through the POS-linked deployment form to create an HRMS Employee account and the matching {$posRole} POS account.";
                } else {
                    $deployed = autoCreateEmployeeFromApplicant($aId);
                    if ($deployed) {
                        $credentials = !empty($deployed['new_account'])
                            ? "Username: {$deployed['username']} / Temporary password: {$deployed['password']} (share these with the new hire)."
                            : "Existing HRMS login {$deployed['username']} was retained and reconnected.";
                        $successMsg = $newStatus === 'hired'
                            ? "Applicant hired! Deployment record, contract, department, HRMS employee login, and matching POS account were created or repaired. {$credentials}"
                            : "Applicant accepted! Employee account auto-created — {$credentials}";
                    } elseif ($newStatus === 'hired') {
                        $successMsg = 'Applicant marked hired. Their existing deployment records were preserved or repaired.';
                    }
                }
            }
        }
        header('Location: applicants.php?' . http_build_query(['job_id'=>$jobId,'search'=>$search,'status'=>$status,'success'=>$successMsg ?? 'Status+updated']));
        exit;
    }
}

include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success alert-auto-dismiss"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-people me-2 text-primary"></i>Applicants</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item"><a href="jobs.php">Job Postings</a></li><li class="breadcrumb-item active">Applicants</li></ol></nav>
    </div>
    <a href="add_applicant.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Applicant</a>
</div>

<div class="card mb-3"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><div class="search-box w-100"><i class="bi bi-search search-icon"></i><input type="text" name="search" class="form-control" placeholder="Search name, email, code..." value="<?= htmlspecialchars($search) ?>"></div></div>
    <div class="col-md-3"><select name="job_id" class="form-select form-select-sm"><option value="">All Jobs</option><?php foreach ($jobs as $j): ?><option value="<?=$j['id']?>" <?=$jobId==$j['id']?'selected':''?>><?= htmlspecialchars($j['title']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach(['pending','initial_interview','final_interview','passed','failed','hired','withdrawn'] as $s): ?><option value="<?=$s?>" <?=$status==$s?'selected':''?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="applicants.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form></div></div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Applicant</th><th>Code</th><th>Job Applied</th><th>Branch</th><th>Education</th><th>Experience</th><th>Status</th><th>Applied</th><th class="text-center">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($applicants)): ?><tr><td colspan="9" class="text-center py-4 text-muted">No applicants found.</td></tr><?php endif; ?>
            <?php foreach ($applicants as $a): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?= htmlspecialchars($a['firstname'].' '.$a['lastname']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($a['email']) ?></div>
                </td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($a['applicant_code']??'—') ?></span></td>
                <td><small><?= htmlspecialchars($a['job_title']??'—') ?></small></td>
                <td><small><?= htmlspecialchars($a['branch_name']??'—') ?></small></td>
                <td><small><?= htmlspecialchars($a['highest_education']??'—') ?></small></td>
                <td><?= $a['years_experience'] ?> yr(s)</td>
                <td><?= statusBadge($a['status']) ?></td>
                <td><?= formatDate($a['applied_at']) ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="view_applicant.php?id=<?=$a['id']?>" class="btn btn-sm btn-info py-0 px-2"><i class="bi bi-eye"></i></a>
                        <button type="button" class="btn btn-sm btn-warning py-0 px-2"
                                onclick="openApplicantStatus(<?=$a['id']?>, '<?= htmlspecialchars($a['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['firstname'].' '.$a['lastname'], ENT_QUOTES) ?>')"
                                title="Update Status"><i class="bi bi-arrow-repeat"></i></button>
                        <?php if ($a['status']==='passed'): ?><a href="../deployment/deploy_applicant.php?applicant_id=<?=$a['id']?>" class="btn btn-sm btn-success py-0 px-2" title="Deploy"><i class="bi bi-person-badge"></i></a><?php endif; ?>
                        <a href="#" onclick="return confirmDelete('delete_applicant.php?id=<?=$a['id']?>','<?= htmlspecialchars($a['firstname'].' '.$a['lastname'],ENT_QUOTES) ?>')" class="btn btn-sm btn-danger py-0 px-2"><i class="bi bi-trash"></i></a>
                    </div>
                    <form method="POST" id="statusForm<?=$a['id']?>" class="d-none">
                        <?= csrfField() ?>
                        <input type="hidden" name="applicant_id" value="<?=$a['id']?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="<?= htmlspecialchars($a['status']) ?>">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?></small>
        <?= paginate($total,$perPage,$page,'applicants.php?'.http_build_query(['job_id'=>$jobId,'search'=>$search,'status'=>$status])) ?>
    </div>
    <?php endif; ?>
</div>
<script>
const applicantStatuses = {
    pending: 'Pending',
    initial_interview: 'Initial Interview',
    final_interview: 'Final Interview',
    passed: 'Passed',
    failed: 'Failed',
    hired: 'Hired',
    withdrawn: 'Withdrawn'
};

async function openApplicantStatus(id, currentStatus, applicantName) {
    if (typeof Swal === 'undefined') {
        alert('The status dialog could not load. Please refresh the page and try again.');
        return;
    }

    const result = await Swal.fire({
        title: 'Update Applicant Status',
        html: '<div class="text-start small mb-2">Applicant: <strong>' + escapeHtml(applicantName) + '</strong></div>',
        input: 'select',
        inputOptions: applicantStatuses,
        inputValue: currentStatus,
        inputPlaceholder: 'Select status',
        showCancelButton: true,
        confirmButtonText: 'Update Status',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#4361ee',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        focusConfirm: false,
        allowOutsideClick: true,
        inputValidator: value => !value ? 'Please select a status.' : undefined
    });

    if (!result.isConfirmed) return;

    const form = document.getElementById('statusForm' + id);
    if (!form) {
        Swal.fire('Error', 'The update form could not be found. Please refresh the page.', 'error');
        return;
    }
    form.querySelector('[name="new_status"]').value = result.value;

    const label = applicantStatuses[result.value] || result.value;
    const important = result.value === 'passed' || result.value === 'hired';
    const confirm = await Swal.fire({
        title: 'Confirm Status Change',
        html: 'Set <strong>' + escapeHtml(applicantName) + '</strong> to <strong>' + escapeHtml(label) + '</strong>?' +
              (important ? '<br><small class="text-success fw-semibold">Hiring/deployment integration may create or connect employee and POS records.</small>' : ''),
        icon: (result.value === 'failed' || result.value === 'withdrawn') ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Update',
        cancelButtonText: 'Cancel',
        confirmButtonColor: important ? '#2a9d8f' : '#4361ee',
        cancelButtonColor: '#6c757d'
    });

    if (confirm.isConfirmed) {
        Swal.fire({
            title: 'Updating...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });
        form.submit();
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
}
</script>
<?php include '../../includes/footer.php'; ?>
