<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$user = current_user();
$title = $title ?? FINANCE_APP_NAME;
$activePage = $activePage ?? '';
$successMessage = flash('success');
$errorMessage = flash('error');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(page_title($title)) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=20260819">
    <link rel="stylesheet" href="assets/icons/bootstrap-icons.css?v=20260930-v6">
    <link rel="stylesheet" href="assets/css/finance.css?v=20260930-v6">
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('finance-theme');
                if (savedTheme === 'dark') document.documentElement.dataset.theme = 'dark';
            } catch (error) {}
        }());
    </script>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div>
                <div class="eyebrow">FINANCE CONTROL CENTER</div>
                <h1 class="page-heading"><?= e($title) ?></h1>
            </div>
            <div class="topbar-actions">
                <span class="offline-pill"><span class="status-dot"></span> LOCALHOST / OFFLINE MODE</span>
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode">
                        <span class="bi bi-moon-stars"></span>
                    </button>
                <div class="user-chip profile-menu">
                    <span class="avatar"><?= e(strtoupper(substr((string) $user['full_name'], 0, 1))) ?></span>
                    <div class="d-none d-md-block">
                        <strong><?= e($user['full_name']) ?></strong>
                        <small><?= e($user['role_label'] ?? $user['role_name']) ?></small>
                    </div>
                    <div class="profile-actions">
                        <a href="settings.php"><span class="bi bi-person-circle"></span> Profile</a>
                        <a href="logout.php"><span class="bi bi-box-arrow-right"></span> Log out</a>
                    </div>
                </div>
                <a class="topbar-logout" href="logout.php" title="Log out"><span class="bi bi-box-arrow-right"></span><span class="d-none d-lg-inline">Log out</span></a>
            </div>
        </header>
        <div class="content-wrap">
            <?php if ($successMessage): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($successMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($errorMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>