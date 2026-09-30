<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body>
<!-- Applicant portal nav -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="<?= SITE_URL ?>apply.php">
            <i class="bi bi-briefcase-fill me-2"></i><?= SITE_NAME ?> — Careers
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#applicantNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="applicantNav">
            <div class="ms-auto d-flex align-items-center gap-2 flex-wrap mt-2 mt-lg-0">
                <?php
                // Show inbox with unread count if logged in
                if (applicantLoggedIn()):
                    $unreadMailCount = 0;
                    try {
                        $navDb = getDB();
                        $navApplicant = currentApplicant();
                        if ($navApplicant) {
                            // Get applicant IDs for this account
                            $navStmt = $navDb->prepare("SELECT id FROM applicants WHERE applicant_account_id = ?");
                            $navStmt->execute([$navApplicant['id']]);
                            $navIds = array_column($navStmt->fetchAll(), 'id');
                            if (!empty($navIds)) {
                                $ph = implode(',', array_fill(0, count($navIds), '?'));
                                $unreadMailStmt = $navDb->prepare("SELECT COUNT(*) FROM hr_emails WHERE applicant_id IN ($ph) AND is_read = 0");
                                $unreadMailStmt->execute($navIds);
                                $unreadMailCount = (int)$unreadMailStmt->fetchColumn();
                            }
                        }
                    } catch (Exception $e) {}
                ?>
                <a href="<?= SITE_URL ?>applicant/dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person-lines-fill me-1"></i>My Applications
                </a>
                <a href="<?= SITE_URL ?>applicant/inbox.php" class="btn btn-outline-<?= $unreadMailCount > 0 ? 'danger' : 'secondary' ?> btn-sm position-relative">
                    <i class="bi bi-inbox me-1"></i>Inbox
                    <?php if ($unreadMailCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          style="font-size:.6rem;"><?= $unreadMailCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= SITE_URL ?>applicant/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
                <?php else: ?>
                <a href="<?= SITE_URL ?>applicant/login.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
                <a href="<?= SITE_URL ?>applicant/register.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Register
                </a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>apply.php" class="btn btn-success btn-sm">
                    <i class="bi bi-briefcase me-1"></i>View Jobs
                </a>
                <a href="<?= SITE_URL ?>index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield me-1"></i>Staff Login
                </a>
            </div>
        </div>
    </div>
</nav>
<div class="container py-4">
