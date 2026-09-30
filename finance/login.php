<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect('dashboard.php');
}
$error = null;
if (is_post()) {
    try {
        verify_csrf();
        if (!login_user(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
            throw new RuntimeException('Invalid credentials or temporarily locked account.');
        }
        redirect('dashboard.php');
    } catch (Throwable $exception) {
        $error = format_error($exception);
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Finance</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=20260819">
    <link rel="stylesheet" href="assets/icons/bootstrap-icons.css?v=20260819">
    <link rel="stylesheet" href="assets/css/finance.css?v=20260819">
    <script>
        (function () {
            try {
                if (localStorage.getItem('finance-theme') === 'dark') document.documentElement.dataset.theme = 'dark';
            } catch (error) {}
        }());
    </script>
</head>
<body class="auth-page">
<div class="auth-panel">
    <div class="auth-topline">
        <div class="auth-brand"><div class="brand-mark">₱</div><div><strong>Finance</strong><small>LOCAL CONTROL CENTER</small></div></div>
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><span class="bi bi-moon-stars"></span></button>
    </div>
    <div class="eyebrow">LOCALHOST ACCESS</div>
    <h1>Welcome back.</h1>
    <p class="auth-subtitle">Sign in with a Finance account or an active connected HRMS/POS staff account. Finance keeps source credentials and branch assignments linked.</p>
    <?php if (isset($_GET['expired'])): ?><div class="alert alert-warning">Your session expired. Please sign in again.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (finance_user_count() === 0): ?>
        <div class="alert alert-info">Complete the <a href="install.php">first-time setup</a> before signing in.</div>
    <?php else: ?>
        <form method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label" for="username">Username</label>
            <input class="form-control mb-3" id="username" name="username" required autofocus autocomplete="username">
            <label class="form-label" for="password">Password</label>
            <input class="form-control mb-4" id="password" name="password" type="password" required autocomplete="current-password">
            <button class="btn btn-primary w-100" type="submit">Sign in <span class="bi bi-arrow-right"></span></button>
        </form>
    <?php endif; ?>
    <a class="auth-back-link" href="index.php"><span class="bi bi-arrow-left"></span> Back to Finance home</a>
    <p class="auth-footnote">LOCALHOST / OFFLINE MODE · No remember-me cookies</p>
</div>
<script src="assets/js/finance.js?v=20260819"></script>
</body>
</html>