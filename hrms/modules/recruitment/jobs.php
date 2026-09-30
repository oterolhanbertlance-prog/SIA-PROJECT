<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../config/pos_branch_sync.php';
requireRole('admin','hr');

$pageTitle = 'Job Postings';
$db = getDB();
$posJobTitles = ['POS Super Admin', 'Cashier', 'Inventory Staff'];
$posTitlePlaceholders = implode(',', array_fill(0, count($posJobTitles), '?'));

/* ── Self-healing schema: job postings can target one branch, or be
       left open so applicants pick from every available branch ───── */
$db->exec("ALTER TABLE job_postings ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER department_id");
$db->exec("CREATE TABLE IF NOT EXISTS branches (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    branch_code  VARCHAR(30)  NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    city         VARCHAR(100) DEFAULT NULL,
    latitude     DECIMAL(10,7) DEFAULT NULL,
    longitude    DECIMAL(10,7) DEFAULT NULL,
    phone        VARCHAR(30)  DEFAULT NULL,
    manager_id   INT DEFAULT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
syncBranchesFromPos($db);
$activeBranches = $db->query("SELECT id, name, city FROM branches WHERE status='active' ORDER BY name")->fetchAll();
$db->exec("CREATE TABLE IF NOT EXISTS job_posting_branches (job_posting_id INT NOT NULL, branch_id INT NOT NULL, PRIMARY KEY(job_posting_id,branch_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// Backfill existing single-branch postings into the multi-branch map.
$db->exec("INSERT IGNORE INTO job_posting_branches(job_posting_id,branch_id) SELECT id,branch_id FROM job_postings WHERE branch_id IS NOT NULL");

// Handle POST-based status change and delete (CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { header('Location: jobs.php?error=Invalid+request'); exit; }
    $jobAction = sanitize($_POST['job_action']);
    $jobId     = (int)($_POST['job_id'] ?? 0);
    $allowed   = ['open','closed','draft','filled'];
    if (in_array($jobAction, $allowed) && $jobId) {
        $db->prepare("UPDATE job_postings SET status=? WHERE id=?")->execute([$jobAction, $jobId]);
        auditLog('UPDATE','Recruitment',"Job posting #$jobId status changed to $jobAction");
        header('Location: jobs.php?success='.urlencode("Job status updated to $jobAction."));
        exit;
    }
    if ($jobAction === 'delete' && $jobId) {
        $db->prepare("DELETE FROM job_postings WHERE id=?")->execute([$jobId]);
        auditLog('DELETE','Recruitment',"Job posting #$jobId deleted");
        header('Location: jobs.php?success='.urlencode("Job posting deleted."));
        exit;
    }
}

// POST: Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { header('Location: jobs.php?error=Invalid+submission'); exit; }
    $data = [
        'title'       => trim($_POST['title'] ?? ''),
        'department_id'=> (int)($_POST['department_id'] ?? 0) ?: null,
        'branch_ids'  => array_values(array_unique(array_filter(array_map('intval', $_POST['branch_ids'] ?? [])))),
        'branch_id'   => null,
        'position_id' => null,
        'description' => sanitize($_POST['description'] ?? ''),
        'requirements'=> sanitize($_POST['requirements'] ?? ''),
        'slots'       => (int)($_POST['slots'] ?? 1),
        'job_type'    => sanitize($_POST['job_type'] ?? 'Full-Time'),
        'status'      => sanitize($_POST['status'] ?? 'draft'),
        'deadline'    => sanitize($_POST['deadline'] ?? '') ?: null,
        'salary_min'  => (float)($_POST['salary_min'] ?? 0) ?: null,
        'salary_max'  => (float)($_POST['salary_max'] ?? 0) ?: null,
        'posted_by'   => currentUser()['id'],
    ];
    if (empty($data['branch_ids'])) { header('Location: jobs.php?error='.urlencode('Select at least one branch for this job.')); exit; }
    $data['branch_id'] = count($data['branch_ids']) === 1 ? $data['branch_ids'][0] : null;
    if (!in_array($data['title'], $posJobTitles, true)) {
        header('Location: jobs.php?error='.urlencode('Only POS Super Admin, Cashier, and Inventory Staff job postings are allowed.'));
        exit;
    }
    // POS job titles map to one canonical position. Keep both the posting
    // department and position linked so deployment and contracts inherit the
    // same department automatically.
    $posStmt = $db->prepare("SELECT id, department_id FROM positions WHERE title=? AND status='active' LIMIT 1");
    $posStmt->execute([$data['title']]);
    $posPosition = $posStmt->fetch();
    if ($posPosition) {
        $data['position_id'] = (int)$posPosition['id'];
        // POS role determines its operational Boba House department.
        // This prevents Cashier/Inventory/Admin jobs from being assigned to
        // unrelated legacy corporate departments.
        $data['department_id'] = (int)$posPosition['department_id'];
    }
    $editId = (int)($_POST['edit_id'] ?? 0);
    if ($editId) {
        $db->prepare("UPDATE job_postings SET title=?,department_id=?,branch_id=?,position_id=?,description=?,requirements=?,slots=?,job_type=?,status=?,deadline=?,salary_min=?,salary_max=? WHERE id=?")
           ->execute([
               $data['title'], $data['department_id'], $data['branch_id'], $data['position_id'],
               $data['description'], $data['requirements'], $data['slots'],
               $data['job_type'], $data['status'], $data['deadline'],
               $data['salary_min'], $data['salary_max'], $editId,
           ]);
        $savedJobId=$editId;
    } else {
        $db->prepare("INSERT INTO job_postings (title,department_id,branch_id,position_id,description,requirements,slots,job_type,status,deadline,salary_min,salary_max,posted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $data['title'], $data['department_id'], $data['branch_id'], $data['position_id'],
               $data['description'], $data['requirements'], $data['slots'],
               $data['job_type'], $data['status'], $data['deadline'],
               $data['salary_min'], $data['salary_max'], $data['posted_by'],
           ]);
        $savedJobId=(int)$db->lastInsertId();
    }
    $db->prepare('DELETE FROM job_posting_branches WHERE job_posting_id=?')->execute([$savedJobId]);
    $insB=$db->prepare('INSERT INTO job_posting_branches(job_posting_id,branch_id) VALUES (?,?)'); foreach($data['branch_ids'] as $bid){$insB->execute([$savedJobId,$bid]);}
    auditLog('CREATE','Recruitment',"Job posting: {$data['title']}");
    header('Location: jobs.php?success='.urlencode("Job posting saved successfully."));
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$where  = ["j.title IN ($posTitlePlaceholders)"]; $params = $posJobTitles;
if ($search) { $where[] = "j.title LIKE ?"; $params[] = "%$search%"; }
if ($status) { $where[] = "j.status=?"; $params[] = $status; }
$whereStr = 'WHERE '.implode(' AND ',$where);

$stmt = $db->prepare("SELECT j.*, d.name as dept_name, b.name as branch_name, b.city as branch_city, (SELECT GROUP_CONCAT(br.name ORDER BY br.name SEPARATOR ', ') FROM job_posting_branches jpb JOIN branches br ON br.id=jpb.branch_id WHERE jpb.job_posting_id=j.id) AS branch_names, (SELECT COUNT(*) FROM applicants a WHERE a.job_id=j.id) as applicant_count FROM job_postings j LEFT JOIN departments d ON j.department_id=d.id LEFT JOIN branches b ON j.branch_id=b.id $whereStr ORDER BY j.created_at DESC");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

// Edit mode
$editJob = null;
if (isset($_GET['edit'])) {
    $e = $db->prepare("SELECT * FROM job_postings WHERE id=? AND title IN ($posTitlePlaceholders)");
    $e->execute(array_merge([(int)$_GET['edit']], $posJobTitles));
    $editJob = $e->fetch();
}
$editBranchIds=[]; if($editJob){$eb=$db->prepare('SELECT branch_id FROM job_posting_branches WHERE job_posting_id=?');$eb->execute([$editJob['id']]);$editBranchIds=array_map('intval',array_column($eb->fetchAll(),'branch_id')); if(!$editBranchIds && !empty($editJob['branch_id']))$editBranchIds=[(int)$editJob['branch_id']];}

// ── POS Integration: get set of job IDs already tagged as POS ─────────────────
$posTaggedIds = [];
try {
    $posTaggedIds = array_column(
        $db->query("SELECT job_posting_id FROM pos_job_postings_meta")->fetchAll(),
        'job_posting_id'
    );
} catch (Throwable $e) { /* migration not run yet — silently skip */ }

// Integration base path for JS fetch calls
$intBase = SITE_URL . 'integration/';

include '../../includes/header.php';
?>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
<div class="page-header">
    <div>
        <h4><i class="bi bi-briefcase-fill me-2 text-primary"></i>Job Postings</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=SITE_URL?>dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Job Postings</li></ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= SITE_URL ?>integration/pos_upgrade_dashboard.php" class="btn btn-outline-purple" style="border-color:#6f42c1;color:#6f42c1;">
            <i class="bi bi-diagram-3-fill me-1"></i>POS Dashboard
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jobModal">
            <i class="bi bi-plus-lg me-1"></i>New Job Posting
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><div class="search-box w-100"><i class="bi bi-search search-icon"></i><input type="text" name="search" class="form-control" placeholder="Search job title..." value="<?= htmlspecialchars($search) ?>"></div></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach(['draft','open','closed','filled'] as $s): ?><option value="<?=$s?>" <?=$status==$s?'selected':''?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a href="jobs.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</form></div></div>

<!-- Job Cards -->
<div class="row g-3">
<?php foreach ($jobs as $j): ?>
<?php $isPosTagged = in_array($j['id'], $posTaggedIds); ?>
<div class="col-md-6 col-xl-4">
    <div class="card h-100 <?= $isPosTagged ? 'border-purple' : '' ?>" style="<?= $isPosTagged ? 'border-color:#6f42c1!important;' : '' ?>">
        <?php if ($isPosTagged): ?>
        <div class="card-header py-1 px-3" style="background:#6f42c1;color:#fff;font-size:.75rem;">
            <i class="bi bi-diagram-3-fill me-1"></i>POS-Targeted Opening
        </div>
        <?php endif; ?>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($j['title']) ?></h6>
                <?= statusBadge($j['status']) ?>
            </div>
            <div class="text-muted small mb-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($j['dept_name']??'—') ?> &nbsp; <i class="bi bi-clock me-1"></i><?= htmlspecialchars($j['job_type']) ?></div>
            <div class="small mb-2">
                <?php if ($j['branch_name']): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($j['branch_name']) ?><?= $j['branch_city'] ? ', '.htmlspecialchars($j['branch_city']) : '' ?></span>
                <?php else: ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-signpost-split-fill me-1"></i>Open to any branch</span>
                <?php endif; ?>
            </div>
            <div class="text-muted small mb-2"><i class="bi bi-people me-1"></i><?= $j['slots'] ?> slot(s) &nbsp; <i class="bi bi-person-check me-1"></i><?= $j['applicant_count'] ?> applicant(s)</div>
            <?php if ($j['salary_min'] || $j['salary_max']): ?>
            <div class="text-muted small mb-2"><i class="bi bi-cash me-1"></i><?= money($j['salary_min']) ?> – <?= money($j['salary_max']) ?></div>
            <?php endif; ?>
            <?php if ($j['deadline']): ?><div class="text-muted small"><i class="bi bi-calendar-x me-1"></i>Deadline: <?= formatDate($j['deadline']) ?></div><?php endif; ?>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap gap-1">
            <a href="applicants.php?job_id=<?=$j['id']?>" class="btn btn-sm btn-info"><i class="bi bi-people me-1"></i><?= $j['applicant_count'] ?></a>
            <a href="jobs.php?edit=<?=$j['id']?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
            <?php if ($j['status']==='draft'): ?>
            <form method="POST" class="d-inline" onsubmit="return confirmSubmit(this, 'Publish this job posting so applicants can apply?', {icon:'question', confirmColor:'#2a9d8f', confirmText:'Yes, Publish'})"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>"><input type="hidden" name="job_action" value="open"><input type="hidden" name="job_id" value="<?=$j['id']?>"><button type="submit" class="btn btn-sm btn-success">Open</button></form>
            <?php endif; ?>
            <?php if ($j['status']==='open'): ?>
            <form method="POST" class="d-inline" onsubmit="return confirmSubmit(this, 'Close this job posting? It will no longer accept new applications.', {icon:'warning', confirmColor:'#6c757d', confirmText:'Yes, Close It'})"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>"><input type="hidden" name="job_action" value="closed"><input type="hidden" name="job_id" value="<?=$j['id']?>"><button type="submit" class="btn btn-sm btn-secondary">Close</button></form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirmSubmit(this, 'Delete job posting \'<?= htmlspecialchars(addslashes($j['title'])) ?>\'?')"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>"><input type="hidden" name="job_action" value="delete"><input type="hidden" name="job_id" value="<?=$j['id']?>"><button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>

            <!-- ── POS Staff Button ──────────────────────────────────────── -->
            <button type="button" class="btn btn-sm btn-pos-staff"
                    style="background:#6f42c1;color:#fff;border:none;"
                    data-job-id="<?= $j['id'] ?>"
                    data-job-title="<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>"
                    data-pos-tagged="<?= $isPosTagged ? '1' : '0' ?>"
                    title="<?= $isPosTagged ? 'Assign this hired applicant to the POS role' : 'Tag this job for POS & add staff' ?>">
                <i class="bi bi-person-plus-fill me-1"></i><?= $isPosTagged ? 'Add POS Staff' : 'Tag &amp; Add POS Staff' ?>
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($jobs)): ?><div class="col-12"><div class="card py-5 text-center text-muted"><i class="bi bi-briefcase fs-2 d-block mb-2"></i>No job postings found.</div></div><?php endif; ?>
</div>

<!-- Job Modal -->
<div class="modal fade" id="jobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" data-validate>
                <?= csrfField() ?>
                <?php if ($editJob): ?><input type="hidden" name="edit_id" value="<?= $editJob['id'] ?>"><?php endif; ?>
                <div class="modal-header"><h5 class="modal-title fw-bold"><?= $editJob ? 'Edit Job Posting' : 'New Job Posting' ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Job Title <span class="text-danger">*</span></label><select name="title" class="form-select" required><option value="">Select POS role</option><?php foreach($posJobTitles as $title): ?><option value="<?=htmlspecialchars($title)?>" <?=($editJob['title']??'')===$title?'selected':''?>><?=htmlspecialchars($title)?></option><?php endforeach; ?></select><div class="form-text">The hired person keeps the HRMS <strong>Employee</strong> role; this title controls their POS access.</div></div>
                        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">Select</option><?php foreach ($departments as $d): ?><option value="<?=$d['id']?>" <?=($editJob['department_id']??'')==$d['id']?'selected':''?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6">
                            <label class="form-label">Hiring Branches <span class="text-danger">*</span></label>
                            <select name="branch_ids[]" class="form-select" multiple size="<?= min(6,max(3,count($activeBranches))) ?>" required>
                                <?php foreach ($activeBranches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= in_array((int)$b['id'],$editBranchIds,true)?'selected':'' ?>><?= htmlspecialchars($b['name']) ?><?= $b['city'] ? ' — '.htmlspecialchars($b['city']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple branches. The public Job Board will show one card per selected branch.</div>
                        </div>
                        <div class="col-md-4"><label class="form-label">Job Type</label><select name="job_type" class="form-select"><?php foreach(['Full-Time','Part-Time','Contract','Internship'] as $t): ?><option value="<?=$t?>" <?=($editJob['job_type']??'')==$t?'selected':''?>><?=$t?></option><?php endforeach; ?></select></div>
                        <div class="col-md-2"><label class="form-label">Slots</label><input type="number" name="slots" class="form-control" min="1" value="<?= htmlspecialchars($editJob['slots']??'1') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Min Salary</label><input type="number" name="salary_min" class="form-control" step="0.01" value="<?= htmlspecialchars($editJob['salary_min']??'') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Max Salary</label><input type="number" name="salary_max" class="form-control" step="0.01" value="<?= htmlspecialchars($editJob['salary_max']??'') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach(['draft','open','closed'] as $s): ?><option value="<?=$s?>" <?=($editJob['status']??'draft')==$s?'selected':''?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Deadline</label><input type="date" name="deadline" class="form-control" value="<?= htmlspecialchars($editJob['deadline']??'') ?>"></div>
                        <div class="col-12"><label class="form-label">Job Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editJob['description']??'') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Requirements</label><textarea name="requirements" class="form-control" rows="3"><?= htmlspecialchars($editJob['requirements']??'') ?></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Job Posting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$intBaseJs = json_encode($intBase);
$extraJs = ($editJob ? '<script>document.addEventListener("DOMContentLoaded",()=>new bootstrap.Modal(document.getElementById("jobModal")).show());</script>' : '')
. <<<JS
<script>
const POS_INT_BASE = {$intBaseJs};

document.querySelectorAll('.btn-pos-staff').forEach(btn => {
    btn.addEventListener('click', async function () {
        const jobId    = this.dataset.jobId;
        const jobTitle = this.dataset.jobTitle;
        const tagged   = this.dataset.posTagged === '1';
        const titleLower = jobTitle.toLowerCase();
        const roleLabel = /super[\s_-]*admin|pos[\s_-]*admin/.test(titleLower)
            ? 'super_admin'
            : titleLower.includes('inventory') || titleLower.includes('stock')
                ? 'inventory_staff'
                : 'cashier';

        // Step 1: get POS identifier
        const { value: posId, isConfirmed } = await Swal.fire({
            icon: 'question',
            title: tagged
                ? '🎯 Add Staff to POS'
                : '🏷️ Tag Job & Add POS Staff',
            html: tagged
                ? `<p class="mb-2">Enter the POS email or username of the staff member to assign to the <strong>\${roleLabel}</strong> POS role.</p>
                   <p class="text-muted small mb-0">⚠️ Their <strong>HRMS role stays employee</strong> — they will NOT become an HRMS admin.</p>`
                : `<p class="mb-2">This job posting is not yet POS-tagged. It will be tagged automatically, then the POS role will be assigned.</p>
                   <p class="text-muted small mb-0">⚠️ Their <strong>HRMS role stays employee</strong> — they will NOT become an HRMS admin.</p>`,
            input: 'text',
            inputLabel: 'POS Email or Username',
            inputPlaceholder: 'e.g. juan@example.com or juan123',
            inputAttributes: { autocomplete: 'off' },
            showCancelButton: true,
            confirmButtonColor: '#6f42c1',
            confirmButtonText: '<i class="bi bi-person-plus-fill"></i> Add as POS Staff',
            cancelButtonText: 'Cancel',
            preConfirm: (val) => {
                if (!val || val.trim().length < 2) {
                    Swal.showValidationMessage('Please enter a POS email or username.');
                    return false;
                }
                return val.trim();
            }
        });
        if (!isConfirmed || !posId) return;

        // Step 2: confirm the action
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Confirm POS Staff Assignment',
            html: `<table class="table table-sm table-bordered text-start small mx-auto" style="max-width:340px">
                     <tr><th>Job Posting</th><td>\${jobTitle}</td></tr>
                     <tr><th>POS Account</th><td><code>\${posId}</code></td></tr>
                     <tr><th>POS Role</th><td><strong>\${roleLabel}</strong></td></tr>
                     <tr><th>HRMS Role</th><td class="text-success fw-bold">stays <code>employee</code> ✅</td></tr>
                   </table>
                   <p class="text-muted small">An email notification will be sent to the staff member.</p>`,
            showCancelButton: true, reverseButtons: true,
            confirmButtonColor: '#6f42c1',
            confirmButtonText: '✅ Yes, Add as POS Staff',
        });
        if (!confirm.isConfirmed) return;

        // Step 3: send to pos_quick_staff.php
        Swal.fire({ title: 'Processing…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('job_id', jobId);
        if (posId.includes('@')) fd.append('pos_email',    posId);
        else                     fd.append('pos_username', posId);
        fd.append('pos_label', 'POS Staff');

        let data;
        try {
            const r = await fetch(POS_INT_BASE + 'pos_quick_staff.php', { method: 'POST', body: fd });
            data = await r.json();
        } catch (err) {
            return Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
        }

        if (data.success) {
            await Swal.fire({ icon: 'success', title: data.title, html: data.message, confirmButtonText: 'OK' });
            location.reload();
        } else {
            Swal.fire({ icon: data.icon || 'error', title: data.title || 'Failed', html: data.message });
        }
    });
});
</script>
JS;

include '../../includes/footer.php';
?>
