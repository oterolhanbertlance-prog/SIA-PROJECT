<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireRole('admin','hr');
$pageTitle = 'Applicant Report';
$db = getDB();
$stmt = $db->query("SELECT a.*, j.title as job_title FROM applicants a LEFT JOIN job_postings j ON a.job_id=j.id ORDER BY a.applied_at DESC");
$applicants = $stmt->fetchAll();
include '../../includes/header.php';
?>
<div class="page-header"><div><h4><i class="bi bi-people me-2 text-primary"></i>Applicant Report</h4></div><div class="d-flex gap-2 no-print"><button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button><a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div></div>
<div class="card"><div class="card-header fw-semibold">All Applicants — Generated: <?= date('M d, Y') ?></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>#</th><th>Code</th><th>Name</th><th>Email</th><th>Job Applied</th><th>Education</th><th>Experience</th><th>Status</th><th>Applied</th></tr></thead><tbody>
<?php $i=1; foreach ($applicants as $a): ?><tr><td><?=$i++?></td><td><?= htmlspecialchars($a['applicant_code']??'—') ?></td><td><?= htmlspecialchars($a['firstname'].' '.$a['lastname']) ?></td><td><?= htmlspecialchars($a['email']) ?></td><td><?= htmlspecialchars($a['job_title']??'—') ?></td><td><?= htmlspecialchars($a['highest_education']??'—') ?></td><td><?= $a['years_experience'] ?> yr(s)</td><td><?= statusBadge($a['status']) ?></td><td><?= formatDate($a['applied_at']) ?></td></tr><?php endforeach; ?>
<?php if(empty($applicants)): ?><tr><td colspan="9" class="text-center text-muted py-4">No applicants found.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<?php include '../../includes/footer.php'; ?>
