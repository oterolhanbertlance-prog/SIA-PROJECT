<?php
/**
 * integration/pos_apply.php  — POS side  (NEW file)
 * Lets a logged-in POS customer browse open POS-targeted job openings
 * and submit a role-upgrade application to the HRMS system.
 *
 * Zero existing POS files are modified.
 * Requires an active POS session (any role).
 */

session_start();
require_once '../includes/app_url.php';

// ── POS session guard ─────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: ' . appUrl('auth/login.php?redirect=integration/pos_apply.php'));
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
// getDB()     → milk_tea_pos (from original db.php via db_connect.php)
// getHrmsDB() → hrms (new)

$posUserId = (int)$_SESSION['user_id'];

// ── Current POS user ──────────────────────────────────────────────────────────
$me = getDB()->prepare(
    "SELECT id, username, email, role, is_active FROM users WHERE id = ? LIMIT 1"
);
$me->execute([$posUserId]);
$posUser = $me->fetch();

if (!$posUser || !$posUser['is_active']) {
    header('Location: ' . appUrl('auth/login.php?error=inactive'));
    exit;
}

$alreadyAdmin = ($posUser['role'] === 'super_admin');

// ── Open POS-targeted job postings from HRMS ──────────────────────────────────
$openPostings = [];
$hrmsError    = null;
$existingApps = [];

try {
    $hdb = getHrmsDB();

    $pj = $hdb->prepare(
        "SELECT jp.id, jp.title, jp.description, jp.requirements, jp.job_type,
                jp.deadline, jp.salary_min, jp.salary_max,
                pm.pos_position_label, pm.pos_role_target,
                d.name AS department
           FROM job_postings jp
           JOIN pos_job_postings_meta pm ON pm.job_posting_id = jp.id
      LEFT JOIN departments d ON d.id = jp.department_id
          WHERE jp.status = 'open'
            AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())
       ORDER BY jp.created_at DESC"
    );
    $pj->execute();
    $openPostings = $pj->fetchAll();

    // Check which of these the user already applied for
    if (!empty($openPostings)) {
        $ids = implode(',', array_column($openPostings, 'id'));
        $ea  = $hdb->prepare(
            "SELECT job_posting_id, status FROM pos_role_applications
              WHERE pos_user_id = ? AND job_posting_id IN ({$ids})"
        );
        $ea->execute([$posUserId]);
        foreach ($ea->fetchAll() as $row) {
            $existingApps[$row['job_posting_id']] = $row['status'];
        }
    }
} catch (PDOException $e) {
    $openPostings = [];
    $hrmsError    = 'Could not load job postings from HRMS. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for POS Admin Role</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body       { background:#f5f7fa; font-family:'Segoe UI',sans-serif; }
  .hero      { background:linear-gradient(135deg,#6f42c1,#0d6efd); color:#fff;
               border-radius:.75rem; padding:2rem; margin-bottom:2rem; }
  .card-job  { border:none; border-radius:.75rem;
               box-shadow:0 2px 12px rgba(0,0,0,.08);
               transition:box-shadow .2s; }
  .card-job:hover { box-shadow:0 4px 20px rgba(0,0,0,.14); }
  .badge-pos { background:#6f42c1; color:#fff; }
</style>
</head>
<body>
<div class="container py-5" style="max-width:860px">

  <div class="hero">
    <h3 class="mb-1">
      <i class="bi bi-arrow-up-circle-fill me-2"></i>Apply for POS Admin Role
    </h3>
    <p class="mb-0 opacity-75">
      Logged in as: <strong><?= htmlspecialchars($posUser['username']) ?></strong>
      &nbsp;|&nbsp;
      Current POS Role: <code class="text-warning"><?= $posUser['role'] ?></code>
    </p>
  </div>

  <?php if ($alreadyAdmin): ?>
    <div class="alert alert-success d-flex align-items-center gap-3">
      <i class="bi bi-shield-check fs-3"></i>
      <div>
       <strong>You are already a POS Super Admin.</strong><br>
        You have full access to the POS system. No application needed.
      </div>
    </div>

  <?php elseif ($hrmsError): ?>
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= htmlspecialchars($hrmsError) ?>
    </div>

  <?php elseif (empty($openPostings)): ?>
    <div class="alert alert-info text-center py-4">
      <i class="bi bi-inbox fs-2 d-block mb-2"></i>
      <strong>No open POS role positions at the moment.</strong><br>
      Check back later or contact the HR department.
    </div>

  <?php else: ?>
    <p class="text-muted mb-4">
       The positions below are open for POS employees to apply.<br>
       <small>Your HRMS account remains an <strong>Employee</strong> account — only your POS role is updated upon approval.</small>
    </p>
    <div class="row g-4">
      <?php foreach ($openPostings as $jp): ?>
        <?php
          $appStatus = $existingApps[$jp['id']] ?? null;
          $applied   = in_array($appStatus, ['pending','approved'], true);
          $rejected  = in_array($appStatus, ['rejected','cancelled'], true);
        ?>
        <div class="col-md-6">
          <div class="card card-job h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title mb-0"><?= htmlspecialchars($jp['title']) ?></h5>
                <span class="badge badge-pos"><?= htmlspecialchars($jp['pos_position_label']) ?></span>
              </div>
              <p class="text-muted small mb-1">
                <i class="bi bi-building me-1"></i><?= htmlspecialchars($jp['department'] ?? 'General') ?>
                &nbsp;|&nbsp;
                <i class="bi bi-clock me-1"></i><?= htmlspecialchars($jp['job_type']) ?>
              </p>
              <?php if ($jp['deadline']): ?>
                <p class="text-muted small mb-2">
                  <i class="bi bi-calendar-event me-1"></i>
                  Deadline: <?= date('M j, Y', strtotime($jp['deadline'])) ?>
                </p>
              <?php endif; ?>
              <?php if ($jp['description']): ?>
                <p class="small mb-2">
                  <?= nl2br(htmlspecialchars(mb_strimwidth($jp['description'], 0, 120, '…'))) ?>
                </p>
              <?php endif; ?>
              <?php if ($jp['requirements']): ?>
                <p class="small text-muted mb-3">
                  <strong>Requirements:</strong>
                  <?= htmlspecialchars(mb_strimwidth($jp['requirements'], 0, 100, '…')) ?>
                </p>
              <?php endif; ?>
              <div class="mt-auto pt-2">
                <?php if ($applied): ?>
                  <span class="badge bg-success fs-6 w-100 py-2">
                    <i class="bi bi-check-circle me-1"></i>
                    Applied — <?= ucfirst($appStatus) ?>
                  </span>
                <?php elseif ($rejected): ?>
                  <span class="badge bg-danger fs-6 w-100 py-2">
                    <i class="bi bi-x-circle me-1"></i> <?= ucfirst($appStatus) ?>
                  </span>
                <?php else: ?>
                  <button class="btn btn-primary w-100 btn-apply"
                    data-job-id="<?= $jp['id'] ?>"
                    data-job-title="<?= htmlspecialchars($jp['title'], ENT_QUOTES) ?>"
                    data-pos-label="<?= htmlspecialchars($jp['pos_position_label'], ENT_QUOTES) ?>">
                    <i class="bi bi-send me-1"></i> Apply Now
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a href="<?= htmlspecialchars(appUrl('home.php')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to POS
    </a>
  </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const POS_USER_ID = <?= $posUserId ?>;

document.querySelectorAll('.btn-apply').forEach(function (btn) {
  btn.addEventListener('click', async function () {
    const jobId    = this.dataset.jobId;
    const jobTitle = this.dataset.jobTitle;
    const posLabel = this.dataset.posLabel;

    // Prompt for optional cover note
    const { value: coverNote, isConfirmed } = await Swal.fire({
      title: '📋 Apply for ' + posLabel,
      html: '<p class="text-muted small mb-3">Position: <strong>' + jobTitle + '</strong></p>'
          + '<textarea id="coverNoteInput" class="form-control" rows="4" maxlength="500"'
          + ' placeholder="Optional: introduce yourself or explain why you\'re applying…"></textarea>',
      showCancelButton: true,
      confirmButtonColor: '#6f42c1',
      confirmButtonText: '<i class="bi bi-send"></i> Submit Application',
      cancelButtonText: 'Cancel',
      preConfirm: () => document.getElementById('coverNoteInput').value,
    });
    if (!isConfirmed) return;

    Swal.fire({ title: 'Submitting…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const fd = new FormData();
    fd.append('action',         'submit_pos_application');
    fd.append('pos_user_id',    POS_USER_ID);
    fd.append('job_posting_id', jobId);
    fd.append('cover_note',     coverNote || '');

    try {
      const resp = await fetch('/hrms/integration/pos_application_handler.php',
                               { method: 'POST', body: fd });
      const data = await resp.json();
      if (data.success) {
        await Swal.fire({
          icon: 'success', title: data.title, html: data.message,
          confirmButtonColor: '#198754', confirmButtonText: 'Got it!',
        });
        location.reload();
      } else {
        Swal.fire({ icon: data.icon || 'error', title: data.title || 'Error', html: data.message });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
    }
  });
});
</script>
</body>
</html>
