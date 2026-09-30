<?php
/**
 * HR Mailer — Dashboard
 * Lists all applicants and allows HR to send emails directly.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
requireMailerLogin();

$db     = getDB();
$mailer = currentMailer();

// ── Stats ─────────────────────────────────────────────────────────
$totalApplicants = $db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$totalEmailsSent = 0;
try {
    $totalEmailsSent = $db->query("SELECT COUNT(*) FROM hr_emails")->fetchColumn();
} catch (Exception $e) {}

$unreadByApplicant = 0;
try {
    $unreadByApplicant = $db->query("SELECT COUNT(*) FROM hr_emails WHERE is_read = 0")->fetchColumn();
} catch (Exception $e) {}

// ── Recent sent emails ────────────────────────────────────────────
$recentEmails = [];
try {
    $recentEmails = $db->query(
        "SELECT e.*, a.firstname, a.lastname, a.email as applicant_email
         FROM hr_emails e
         LEFT JOIN applicants a ON e.applicant_id = a.id
         ORDER BY e.sent_at DESC LIMIT 10"
    )->fetchAll();
} catch (Exception $e) {}

// ── Applicants list ───────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$params = [];
$where  = [];

if ($search) {
    $where[]  = "(a.firstname LIKE ? OR a.lastname LIKE ? OR a.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status) {
    $where[]  = "a.status = ?";
    $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$applicants = $db->prepare(
    "SELECT a.*, j.title as job_title
     FROM applicants a
     LEFT JOIN job_postings j ON a.job_id = j.id
     $whereSQL
     ORDER BY a.applied_at DESC LIMIT 100"
);
$applicants->execute($params);
$applicants = $applicants->fetchAll();

$statusOptions = ['pending','initial_interview','final_interview','passed','failed','hired','withdrawn'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Mailer Dashboard — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8f9fa; }
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: .75rem 1.5rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .topbar-brand { font-weight:700; color:#2d6a4f; font-size:1.1rem; display:flex; align-items:center; gap:.5rem; }
        .stat-card { background:#fff; border-radius:12px; padding:1.25rem; border:none; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
        .badge-status { font-size:.72rem; }
        .table th { font-size:.8rem; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; font-weight:600; }
        .table td { font-size:.875rem; vertical-align:middle; }
        .btn-compose { background:linear-gradient(135deg,#2d6a4f,#74c69d); border:none; color:#fff; font-weight:600; }
        .btn-compose:hover { background:linear-gradient(135deg,#1b4332,#2d6a4f); color:#fff; }
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-brand">
        <i class="bi bi-envelope-check-fill text-success"></i>
        HR Mailer
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">
            <i class="bi bi-person me-1"></i><?= htmlspecialchars($mailer['fullname'] ?: $mailer['username']) ?>
            <span class="badge bg-primary-subtle text-primary ms-1 small"><?= ucfirst($mailer['role']) ?></span>
        </span>
        <a href="<?= SITE_URL ?>mailer/compose.php" class="btn btn-compose btn-sm">
            <i class="bi bi-pencil-square me-1"></i>Compose
        </a>
        <a href="<?= SITE_URL ?>index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-house me-1"></i>HRMS
        </a>
        <a href="<?= SITE_URL ?>dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to HRMS
        </a>
    </div>
</div>

<div class="container-fluid py-4 px-4">

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="text-muted small">Total Applicants</div>
                    <div class="fw-bold fs-4"><?= $totalApplicants ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-send-fill"></i></div>
                <div>
                    <div class="text-muted small">Emails Sent</div>
                    <div class="fw-bold fs-4"><?= $totalEmailsSent ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-envelope-open"></i></div>
                <div>
                    <div class="text-muted small">Unread by Applicants</div>
                    <div class="fw-bold fs-4"><?= $unreadByApplicant ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Applicants -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Applicants</span>
                    <a href="<?= SITE_URL ?>mailer/compose.php" class="btn btn-compose btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Compose Email
                    </a>
                </div>

                <!-- Filters -->
                <div class="card-body pb-0">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-7">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control"
                                       placeholder="Search name or email…"
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <?php foreach ($statusOptions as $s): ?>
                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Applicant</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($applicants)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No applicants found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($applicants as $app): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($app['firstname'] . ' ' . $app['lastname']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($app['email']) ?></div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($app['job_title'] ?? 'General') ?></td>
                        <td><?= statusBadge($app['status']) ?></td>
                        <td class="text-center">
                            <a href="<?= SITE_URL ?>mailer/compose.php?applicant_id=<?= $app['id'] ?>"
                               class="btn btn-sm btn-outline-success py-0 px-2">
                                <i class="bi bi-envelope me-1"></i>Email
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Recent emails sent -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 fw-semibold">
                    <i class="bi bi-send me-2 text-success"></i>Recently Sent
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentEmails)): ?>
                    <div class="text-center text-muted py-4 small">No emails sent yet.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentEmails as $em): ?>
                        <li class="list-group-item py-2 px-3">
                            <div class="fw-semibold small text-truncate" style="max-width:220px;">
                                <?= htmlspecialchars($em['subject']) ?>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                To: <?= htmlspecialchars($em['firstname'] . ' ' . $em['lastname']) ?>
                                <?php if (!$em['is_read']): ?>
                                <span class="badge bg-warning-subtle text-warning ms-1" style="font-size:.65rem;">Unread</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;"><?= formatDateTime($em['sent_at']) ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
