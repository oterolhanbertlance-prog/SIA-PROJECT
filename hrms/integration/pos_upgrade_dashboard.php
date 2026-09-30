<?php
/**
 * integration/pos_upgrade_dashboard.php  — HRMS side  (NEW file)
 * HR/Admin control panel for the POS ↔ HRMS integration.
 * Uses the standard HRMS header/footer so Swal, Bootstrap & nav are all included.
 * Four tabs: Tag Postings | Upgrade Roles | POS Applications | History
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin', 'hr');

require_once __DIR__ . '/../config/db_connect.php';

$pageTitle = 'POS Integration';
$db        = getDB();

// ── Data queries ──────────────────────────────────────────────────────────────

// Open job postings NOT yet tagged as POS
$openJobs = $db->query(
    "SELECT jp.id, jp.title, jp.status, jp.job_type,
            d.name AS department, jp.deadline
       FROM job_postings jp
  LEFT JOIN departments d ON d.id = jp.department_id
      WHERE jp.status IN ('open','draft')
        AND jp.id NOT IN (SELECT job_posting_id FROM pos_job_postings_meta)
   ORDER BY jp.created_at DESC"
)->fetchAll();

// Already-tagged postings
$taggedJobs = $db->query(
    "SELECT jp.id, jp.title, jp.status,
            pm.pos_role_target, pm.pos_position_label, pm.created_at AS tagged_at,
            u.username AS tagged_by
       FROM pos_job_postings_meta pm
       JOIN job_postings jp ON jp.id = pm.job_posting_id
  LEFT JOIN users u ON u.id = pm.created_by
   ORDER BY pm.created_at DESC"
)->fetchAll();

// Hired applicants linked to POS-tagged postings
$hiredApplicants = $db->query(
    "SELECT a.id AS applicant_id, a.firstname, a.lastname,
            a.email AS applicant_email, a.status,
            jp.id AS job_id, jp.title AS job_title,
            pm.pos_role_target, pm.pos_position_label,
            aa.hired_username,
            (SELECT COUNT(*) FROM pos_role_upgrade_log rl
              WHERE rl.hrms_applicant_id = a.id AND rl.status = 'success') AS already_upgraded
       FROM applicants a
       JOIN job_postings jp ON jp.id = a.job_id
       JOIN pos_job_postings_meta pm ON pm.job_posting_id = jp.id
  LEFT JOIN applicant_accounts aa ON aa.id = a.applicant_account_id
      WHERE a.status = 'hired'
   ORDER BY a.updated_at DESC"
)->fetchAll();

// POS role applications (newest first, last 50)
$posApps = $db->query(
    "SELECT pra.*, jp.title AS job_title, pm.pos_position_label
       FROM pos_role_applications pra
  LEFT JOIN job_postings jp ON jp.id = pra.job_posting_id
  LEFT JOIN pos_job_postings_meta pm ON pm.job_posting_id = pra.job_posting_id
   ORDER BY pra.applied_at DESC LIMIT 50"
)->fetchAll();

// Upgrade history (last 30)
$history = $db->query(
    "SELECT rl.*, a.firstname, a.lastname
       FROM pos_role_upgrade_log rl
  LEFT JOIN applicants a ON a.id = rl.hrms_applicant_id
   ORDER BY rl.created_at DESC LIMIT 30"
)->fetchAll();

$pendingApps     = count(array_filter($posApps,         fn($r) => $r['status'] === 'pending'));
$pendingUpgrades = count(array_filter($hiredApplicants, fn($r) => !(int)$r['already_upgraded']));

// BASE path for AJAX calls
$integrationBase = SITE_URL . 'integration/';

include __DIR__ . '/../includes/header.php';
?>

<style>
.page-hero-pos { background: linear-gradient(135deg,#6f42c1,#0d6efd); color:#fff;
                 border-radius:.75rem; padding:1.25rem 1.75rem; margin-bottom:1.5rem; }
.badge-pos     { background:#6f42c1; color:#fff; }
.btn-pur       { background:#6f42c1; border:none; color:#fff; }
.btn-pur:hover { background:#5a2d91; color:#fff; }
.nav-tabs .nav-link.active { font-weight:600; }
</style>

<!-- Hero banner -->
<div class="page-hero-pos d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div>
    <h4 class="mb-0"><i class="bi bi-diagram-3-fill me-2"></i>POS ↔ HRMS Integration</h4>
    <small class="opacity-75">Tag POS job postings &amp; promote hired employees to POS roles</small>
  </div>
  <div class="text-end small">
    <a href="<?= SITE_URL ?>modules/recruitment/jobs.php" class="btn btn-sm btn-light">
      <i class="bi bi-briefcase me-1"></i>Job Postings
    </a>
  </div>
</div>

<!-- Summary badges -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body py-3">
        <div class="fs-4 fw-bold text-primary"><?= count($taggedJobs) ?></div>
        <div class="small text-muted">Tagged Postings</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body py-3">
        <div class="fs-4 fw-bold text-success"><?= count($hiredApplicants) ?></div>
        <div class="small text-muted">Hired (POS jobs)</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body py-3">
        <div class="fs-4 fw-bold text-warning"><?= $pendingApps ?></div>
        <div class="small text-muted">Pending Applications</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body py-3">
        <div class="fs-4 fw-bold text-purple" style="color:#6f42c1"><?= count($history) ?></div>
        <div class="small text-muted">Upgrade Records</div>
      </div>
    </div>
  </div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs mb-4" id="mainTabs">
  <li class="nav-item">
    <a class="nav-link active" data-bs-toggle="tab" href="#tab-tag">
      <i class="bi bi-tags-fill me-1"></i>Tag Postings
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab-upgrade">
      <i class="bi bi-arrow-up-circle-fill me-1"></i>Upgrade Roles
      <?php if ($pendingUpgrades): ?>
        <span class="badge bg-danger ms-1"><?= $pendingUpgrades ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab-applications">
      <i class="bi bi-person-lines-fill me-1"></i>POS Applications
      <?php if ($pendingApps): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $pendingApps ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab-history">
      <i class="bi bi-clock-history me-1"></i>History
    </a>
  </li>
</ul>

<div class="tab-content">

  <!-- ── TAB: TAG POSTINGS ─────────────────────────────────────────────── -->
  <div class="tab-pane fade show active" id="tab-tag">
    <div class="row g-4">
      <!-- Tag form -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-plus-circle me-1"></i>Tag a Job Posting for POS
          </div>
          <div class="card-body">
            <p class="text-muted small">
               Mark an open posting as recruiting for the <em>milk_tea_pos</em> system.
               Hired applicants from this posting receive the selected POS role while
               their HRMS account remains an <strong>Employee</strong> account.
            </p>
            <form id="formTag">
              <div class="mb-3">
                <label class="form-label fw-semibold">Job Posting <span class="text-danger">*</span></label>
                <select class="form-select" name="job_posting_id" required
                        <?= empty($openJobs) ? 'disabled' : '' ?>>
                  <option value="">— Select an open posting —</option>
                  <?php foreach ($openJobs as $j): ?>
                    <option value="<?= $j['id'] ?>">
                      #<?= $j['id'] ?> — <?= htmlspecialchars($j['title']) ?>
                      (<?= htmlspecialchars($j['department'] ?? 'No Dept') ?>)
                    </option>
                  <?php endforeach; ?>
                  <?php if (empty($openJobs)): ?>
                    <option disabled>No open postings available to tag</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">POS Role to Grant</label>
                <select class="form-select" name="pos_role_target">
                   <option value="super_admin">super_admin — full POS administration</option>
                   <option value="cashier" selected>cashier — POS counter access</option>
                   <option value="inventory_staff">inventory_staff — Ingredients and products</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Display Label</label>
                <input type="text" class="form-control" name="pos_position_label"
                        value="POS Staff" maxlength="100">
                <div class="form-text">This label is shown in notifications and history.</div>
              </div>
              <button type="submit" class="btn btn-primary w-100"
                      <?= empty($openJobs) ? 'disabled' : '' ?>>
                <i class="bi bi-tags-fill me-1"></i>Tag as POS Opening
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Already-tagged list -->
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-secondary text-white fw-semibold">
            <i class="bi bi-list-check me-1"></i>Tagged POS Postings (<?= count($taggedJobs) ?>)
          </div>
          <div class="card-body p-0">
            <?php if (empty($taggedJobs)): ?>
              <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>No postings tagged yet.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                  <thead class="table-light">
                    <tr><th>#</th><th>Title</th><th>POS Role</th><th>Label</th><th>Status</th><th>Tagged By</th></tr>
                  </thead>
                  <tbody>
                  <?php foreach ($taggedJobs as $t): ?>
                    <tr>
                      <td><?= $t['id'] ?></td>
                      <td class="fw-semibold"><?= htmlspecialchars($t['title']) ?></td>
                      <td><code><?= htmlspecialchars($t['pos_role_target']) ?></code></td>
                      <td><?= htmlspecialchars($t['pos_position_label']) ?></td>
                      <td><span class="badge <?= $t['status']==='open' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                      <td class="text-muted"><?= htmlspecialchars($t['tagged_by'] ?? '—') ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /tab-tag -->

  <!-- ── TAB: UPGRADE ROLES ──────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-upgrade">
    <div class="card border-0 shadow-sm">
      <div class="card-header text-white fw-semibold" style="background:#6f42c1">
        <i class="bi bi-arrow-up-circle-fill me-1"></i>Hired Applicants — POS Role Upgrade
        <small class="opacity-75 ms-2">HRMS role stays <code class="text-white">employee</code> — never changed</small>
      </div>
      <div class="card-body p-0">
        <?php if (empty($hiredApplicants)): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-person-check fs-2 d-block mb-2"></i>
            No hired applicants linked to POS postings yet.
            Tag a job posting first, then hire an applicant through that posting.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr><th>Applicant</th><th>Job</th><th>POS Role</th><th>HRMS Status</th><th>POS Account</th><th>Action</th></tr>
              </thead>
              <tbody>
              <?php foreach ($hiredApplicants as $h): ?>
                <?php $done = (int)$h['already_upgraded'] > 0; ?>
                <tr class="<?= $done ? 'table-success' : '' ?>">
                  <td>
                    <strong><?= htmlspecialchars($h['firstname'].' '.$h['lastname']) ?></strong><br>
                    <span class="text-muted">App #<?= $h['applicant_id'] ?></span>
                  </td>
                  <td><?= htmlspecialchars($h['job_title']) ?></td>
                  <td>
                    <span class="badge badge-pos"><?= htmlspecialchars($h['pos_position_label']) ?></span><br>
                    <code><?= htmlspecialchars($h['pos_role_target']) ?></code>
                  </td>
                  <td>
                    <span class="badge bg-success">Hired</span><br>
                    <span class="text-muted">HRMS: <code>employee</code></span>
                  </td>
                  <td>
                    <?php if ($done): ?>
                      <i class="bi bi-check-circle-fill text-success"></i> Already upgraded
                    <?php else: ?>
                      <input type="text" class="form-control form-control-sm pos-id-input"
                             placeholder="POS email or username"
                             value="<?= htmlspecialchars($h['applicant_email']) ?>"
                             data-applicant-id="<?= $h['applicant_id'] ?>"
                             data-job-id="<?= $h['job_id'] ?>">
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($done): ?>
                      <button type="button" class="btn btn-sm btn-outline-success" disabled>
                        <i class="bi bi-check2-all"></i> Done
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-pur btn-do-upgrade"
                        data-applicant-id="<?= $h['applicant_id'] ?>"
                        data-applicant-name="<?= htmlspecialchars($h['firstname'].' '.$h['lastname'], ENT_QUOTES) ?>"
                        data-job-id="<?= $h['job_id'] ?>"
                        data-pos-label="<?= htmlspecialchars($h['pos_position_label'], ENT_QUOTES) ?>"
                        data-pos-role="<?= htmlspecialchars($h['pos_role_target'], ENT_QUOTES) ?>">
                        <i class="bi bi-arrow-up-circle me-1"></i>Upgrade
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="alert alert-info m-3 small">
            <i class="bi bi-shield-lock-fill me-1"></i>
            <strong>Security:</strong> Only <code>milk_tea_pos.users.role</code> is ever written.
            HRMS roles are never changed. All actions are logged + email notifications sent.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /tab-upgrade -->

  <!-- ── TAB: POS APPLICATIONS ───────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-applications">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-warning text-dark fw-semibold">
        <i class="bi bi-person-lines-fill me-1"></i>POS Role Applications (<?= count($posApps) ?>)
      </div>
      <div class="card-body p-0">
        <?php if (empty($posApps)): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>No POS applications yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr><th>#</th><th>POS User</th><th>Applied For</th><th>Status</th><th>Date</th><th>Actions</th></tr>
              </thead>
              <tbody>
              <?php foreach ($posApps as $a): ?>
                <tr>
                  <td><?= $a['id'] ?></td>
                  <td>
                    <strong><?= htmlspecialchars($a['pos_username']) ?></strong><br>
                    <span class="text-muted"><?= htmlspecialchars($a['pos_email'] ?? '—') ?></span>
                  </td>
                  <td><?= htmlspecialchars($a['pos_position_label'] ?? $a['job_title'] ?? 'N/A') ?></td>
                  <td>
                    <?php $sc = match ($a['status']) {
                      'pending'  => 'bg-warning text-dark',
                      'approved' => 'bg-success',
                      'rejected' => 'bg-danger',
                      default    => 'bg-secondary',
                    }; ?>
                    <span class="badge <?= $sc ?>"><?= ucfirst($a['status']) ?></span>
                  </td>
                  <td><?= date('M j, Y', strtotime($a['applied_at'])) ?></td>
                  <td>
                    <?php if ($a['status'] === 'pending'): ?>
                      <button type="button" class="btn btn-sm btn-success me-1 btn-approve-app"
                        data-app-id="<?= $a['id'] ?>"
                        data-pos-username="<?= htmlspecialchars($a['pos_username'], ENT_QUOTES) ?>"
                        data-pos-email="<?= htmlspecialchars($a['pos_email'] ?? '', ENT_QUOTES) ?>"
                        data-job-id="<?= $a['job_posting_id'] ?>"
                        data-label="<?= htmlspecialchars($a['pos_position_label'] ?? '', ENT_QUOTES) ?>">
                        <i class="bi bi-check-circle"></i> Approve
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-reject-app"
                        data-app-id="<?= $a['id'] ?>">
                        <i class="bi bi-x-circle"></i> Reject
                      </button>
                    <?php else: ?>
                      <span class="text-muted">Processed</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /tab-applications -->

  <!-- ── TAB: HISTORY ────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-history">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-dark text-white fw-semibold">
        <i class="bi bi-clock-history me-1"></i>Upgrade History (last 30)
      </div>
      <div class="card-body p-0">
        <?php if (empty($history)): ?>
          <div class="p-4 text-center text-muted">
            <i class="bi bi-clock fs-2 d-block mb-2"></i>No upgrade history yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small">
              <thead class="table-light">
                <tr><th>Date</th><th>Applicant / Linked</th><th>POS User</th><th>Old</th><th>New</th><th>HRMS Role</th><th>By</th><th>Result</th></tr>
              </thead>
              <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= date('M j Y H:i', strtotime($h['created_at'])) ?></td>
                  <td>
                    <?= $h['firstname']
                        ? htmlspecialchars($h['firstname'].' '.$h['lastname'])
                        : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td><code><?= htmlspecialchars($h['pos_username'] ?? '—') ?></code></td>
                  <td><span class="badge bg-secondary"><?= htmlspecialchars($h['old_pos_role'] ?? '—') ?></span></td>
                  <td><span class="badge badge-pos"><?= htmlspecialchars($h['new_pos_role'] ?? '—') ?></span></td>
                  <td><span class="badge bg-info text-dark"><?= htmlspecialchars($h['hrms_role_unchanged'] ?? 'employee') ?></span></td>
                  <td><?= htmlspecialchars($h['performed_by_username'] ?? '—') ?></td>
                  <td>
                    <?php $sc = match ($h['status']) {
                      'success'     => 'bg-success',
                      'failed'      => 'bg-danger',
                      'rolled_back' => 'bg-warning text-dark',
                      default       => 'bg-secondary',
                    }; ?>
                    <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$h['status'])) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /tab-history -->

</div><!-- /tab-content -->

<?php
// Pass the integration base to JS
$integrationBaseJs = json_encode($integrationBase);
$extraJs = <<<JS
<script>
const INT_BASE = {$integrationBaseJs};

async function swalPost(url, fd) {
    Swal.fire({ title: 'Processing…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const r = await fetch(url, { method: 'POST', body: fd });
        return await r.json();
    } catch (err) {
        return { success: false, icon: 'error', title: 'Network Error', message: err.message };
    }
}

// ── Tag a job posting ─────────────────────────────────────────────────────────
document.getElementById('formTag').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    if (!fd.get('job_posting_id'))
        return Swal.fire({ icon: 'warning', title: 'Select a Posting', text: 'Please choose a job posting first.' });

    const c = await Swal.fire({
        icon: 'question', title: 'Tag this Posting?',
        html: `Mark as POS-targeted for <strong>\${fd.get('pos_position_label')}</strong>?`,
        showCancelButton: true, confirmButtonColor: '#6f42c1',
        confirmButtonText: '<i class="bi bi-tags-fill"></i> Yes, Tag It',
    });
    if (!c.isConfirmed) return;

    const data = await swalPost(INT_BASE + 'pos_job_post.php', fd);
    if (data.success) {
        await Swal.fire({ icon: 'success', title: data.title, html: data.message, confirmButtonText: 'Refresh' });
        location.reload();
    } else {
        Swal.fire({ icon: data.icon || 'error', title: data.title || 'Error', html: data.message });
    }
});

// Keep common job titles safe by selecting the matching role automatically;
// HR can still intentionally choose a different role before submitting.
const tagJob = document.querySelector('#formTag [name="job_posting_id"]');
const tagRole = document.querySelector('#formTag [name="pos_role_target"]');
if (tagJob && tagRole) {
    const syncRoleToTitle = () => {
        const title = tagJob.options[tagJob.selectedIndex]?.textContent.toLowerCase() || '';
        const role = /super[\s_-]*admin|pos[\s_-]*admin/.test(title)
            ? 'super_admin'
            : title.includes('inventory') || title.includes('stock')
                ? 'inventory_staff'
                : title.includes('cashier')
                    ? 'cashier'
                    : null;
        if (role) tagRole.value = role;
    };
    tagJob.addEventListener('change', syncRoleToTitle);
    syncRoleToTitle();
}

// ── Upgrade POS role ──────────────────────────────────────────────────────────
document.querySelectorAll('.btn-do-upgrade').forEach(btn => {
    btn.addEventListener('click', async function () {
        const row   = this.closest('tr');
        const inp   = row.querySelector('.pos-id-input');
        const posId = inp ? inp.value.trim() : '';
        if (!posId)
            return Swal.fire({ icon: 'warning', title: 'POS Account Required', text: 'Enter the POS email or username first.' });

        const c = await Swal.fire({
            icon: 'warning', title: '⚠️ Confirm POS Upgrade',
            html: `<table class="table table-sm table-bordered text-start small mx-auto" style="max-width:340px">
                     <tr><th>Applicant</th><td><strong>\${this.dataset.applicantName}</strong></td></tr>
                     <tr><th>POS Account</th><td><code>\${posId}</code></td></tr>
                     <tr><th>New POS Role</th><td><code>\${this.dataset.posRole}</code></td></tr>
                     <tr><th>HRMS Role</th><td class="text-success fw-bold">Stays <code>employee</code></td></tr>
                   </table>
                    <p class="text-danger small mb-0">This grants the selected POS role. Their HRMS login remains an employee account.</p>`,
            showCancelButton: true, reverseButtons: true,
            confirmButtonColor: '#6f42c1', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-arrow-up-circle"></i> Yes, Upgrade',
        });
        if (!c.isConfirmed) return;

        const fd = new FormData();
        fd.append('hrms_applicant_id', this.dataset.applicantId);
        fd.append('job_posting_id',    this.dataset.jobId);
        if (posId.includes('@')) fd.append('pos_email',    posId);
        else                     fd.append('pos_username', posId);

        const data = await swalPost(INT_BASE + 'pos_role_upgrade.php', fd);
        if (data.success) {
            await Swal.fire({ icon: 'success', title: data.title, html: data.message, confirmButtonText: 'Refresh' });
            location.reload();
        } else {
            Swal.fire({ icon: data.icon || 'error', title: data.title || 'Failed', html: data.message });
        }
    });
});

// ── Approve POS application ───────────────────────────────────────────────────
document.querySelectorAll('.btn-approve-app').forEach(btn => {
    btn.addEventListener('click', async function () {
        const c = await Swal.fire({
            icon: 'question', title: 'Approve & Upgrade?',
            html: `Approve <strong>\${this.dataset.posUsername}</strong> for <strong>\${this.dataset.label}</strong>?<br>
                   <small class="text-muted">Their POS role will be upgraded and they will receive an email.</small>`,
            showCancelButton: true, confirmButtonColor: '#198754',
            confirmButtonText: '✅ Approve & Upgrade',
        });
        if (!c.isConfirmed) return;

        let fd = new FormData();
        fd.append('action', 'approve_pos_application');
        fd.append('app_id', this.dataset.appId);
         const ap = await swalPost(INT_BASE + 'pos_application_handler.php', fd);
         if (ap.success) {
             await Swal.fire({ icon: 'success', title: 'Approved & Upgraded! 🎉', html: ap.message });
            location.reload();
        } else {
             Swal.fire({ icon: ap.icon || 'error', title: ap.title, html: ap.message });
        }
    });
});

// ── Reject POS application ────────────────────────────────────────────────────
document.querySelectorAll('.btn-reject-app').forEach(btn => {
    btn.addEventListener('click', async function () {
        const { value: reason, isConfirmed } = await Swal.fire({
            icon: 'question', title: 'Reject Application',
            input: 'textarea', inputLabel: 'Reason (optional — will be emailed to applicant)',
            inputAttributes: { maxlength: 500 },
            showCancelButton: true, confirmButtonColor: '#dc3545',
            confirmButtonText: '🚫 Reject',
        });
        if (!isConfirmed) return;

        const fd = new FormData();
        fd.append('action',   'reject_pos_application');
        fd.append('app_id',   this.dataset.appId);
        fd.append('hr_notes', reason || '');

        const data = await swalPost(INT_BASE + 'pos_application_handler.php', fd);
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Rejected', text: data.message, confirmButtonColor: '#dc3545' });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: data.title, html: data.message });
        }
    });
});
</script>
JS;

include __DIR__ . '/../includes/footer.php';
