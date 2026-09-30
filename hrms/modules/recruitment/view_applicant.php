<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');

$id = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare(
    "SELECT a.*, j.title as job_title, b.name as branch_name, b.city as branch_city FROM applicants a
     LEFT JOIN job_postings j ON a.job_id = j.id
     LEFT JOIN branches b ON a.branch_id = b.id
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { header('Location: applicants.php?error=Not+found'); exit; }

$interviews = $db->prepare("SELECT * FROM interviews WHERE applicant_id = ? ORDER BY interview_date DESC");
$interviews->execute([$id]);
$interviews = $interviews->fetchAll();

// Count emails sent to this applicant
$emailCount = 0;
try {
    $ec = $db->prepare("SELECT COUNT(*) FROM hr_emails WHERE applicant_id = ?");
    $ec->execute([$id]);
    $emailCount = (int)$ec->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'Applicant Profile';
include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-person me-2 text-primary"></i>Applicant Profile</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>modules/recruitment/applicants.php">Applicants</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) ?></li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>modules/recruitment/send_email.php?applicant_id=<?= $id ?>"
           class="btn btn-success">
            <i class="bi bi-envelope me-1"></i>Send Email
        </a>
        <a href="<?= SITE_URL ?>modules/interviews/schedule.php?applicant_id=<?= $id ?>"
           class="btn btn-primary">
            <i class="bi bi-calendar-plus me-1"></i>Schedule Interview
        </a>
        <a href="applicants.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- ── Left: Identity Card ──────────────────────────────────────────── -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="avatar avatar-xl avatar-placeholder rounded-circle mx-auto mb-3"
                     style="font-size:2rem;width:72px;height:72px;display:flex;align-items:center;justify-content:center;background:#e9ecef;">
                    <?= strtoupper(substr($a['firstname'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) ?></h5>
                <p class="text-muted small mb-1"><?= htmlspecialchars($a['job_title'] ?? 'No job specified') ?></p>
                <?php if ($a['branch_name']): ?>
                <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($a['branch_name']) ?><?= $a['branch_city'] ? ', '.htmlspecialchars($a['branch_city']) : '' ?></p>
                <?php endif; ?>
                <?= statusBadge($a['status']) ?>

                <!-- Application Code + Email prominently displayed -->
                <div class="mt-3 p-3 bg-light rounded text-start">
                    <div class="mb-2">
                        <span class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;">Application Code</span><br>
                        <span class="fw-bold text-primary" style="font-size:1rem;letter-spacing:1px;">
                            <?= htmlspecialchars($a['applicant_code'] ?? '—') ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;">Email Address</span><br>
                        <a href="mailto:<?= htmlspecialchars($a['email']) ?>" class="fw-semibold small">
                            <?= htmlspecialchars($a['email']) ?>
                        </a>
                    </div>
                </div>

                <hr>
                <div class="text-start small">
                    <div class="mb-1"><i class="bi bi-telephone me-2 text-muted"></i><?= htmlspecialchars($a['phone'] ?? '—') ?></div>
                    <div class="mb-1"><i class="bi bi-geo-alt me-2 text-muted"></i><?= htmlspecialchars($a['address'] ?? '—') ?></div>
                    <div class="mb-1"><i class="bi bi-calendar me-2 text-muted"></i>Applied: <?= formatDate($a['applied_at']) ?></div>
                    <?php if ($a['resume']): ?>
                    <div class="mb-1"><i class="bi bi-file-pdf me-2 text-muted"></i>
                        <a href="<?= SITE_URL ?>uploads/resumes/<?= htmlspecialchars($a['resume']) ?>" target="_blank">View Resume</a>
                    </div>
                    <?php endif; ?>
                    <div><i class="bi bi-envelope-fill me-2 text-muted"></i>
                        <?= $emailCount ?> HR email<?= $emailCount != 1 ? 's' : '' ?> sent
                        <?php if ($emailCount > 0): ?>
                        · <a href="send_email.php?applicant_id=<?= $id ?>">Send another</a>
                        <?php else: ?>
                        · <a href="send_email.php?applicant_id=<?= $id ?>">Send first email</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Right: Details ───────────────────────────────────────────────── -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header fw-semibold">Background</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Education</label>
                        <span><?= htmlspecialchars($a['highest_education'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">School</label>
                        <span><?= htmlspecialchars($a['school'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Experience</label>
                        <span><?= ($a['years_experience'] ?? 0) ?> year(s)</span>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block">Skills</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($a['skills'] ?? '—')) ?></p>
                    </div>
                    <?php if ($a['cover_letter']): ?>
                    <div class="col-12">
                        <label class="text-muted small d-block">Cover Letter</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($a['cover_letter'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
                <span>Interviews</span>
                <a href="<?= SITE_URL ?>modules/interviews/schedule.php?applicant_id=<?= $id ?>"
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Schedule
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th><th>Date</th><th>Interviewer</th>
                            <th>Mode</th><th>Result</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($interviews as $iv): ?>
                    <tr>
                        <td><?= ucfirst($iv['interview_type']) ?></td>
                        <td><?= formatDateTime($iv['interview_date']) ?></td>
                        <td><?= htmlspecialchars($iv['interviewer_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($iv['mode']) ?></td>
                        <td><?= statusBadge($iv['result']) ?></td>
                        <td><?= statusBadge($iv['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($interviews)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No interviews scheduled yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
