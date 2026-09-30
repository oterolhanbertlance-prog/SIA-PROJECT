<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$error = null;
$created = false;
try {
    if (finance_user_count() > 0) {
        redirect('login.php');
    }
    if (is_post()) {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Use 3–80 letters, numbers, dots, dashes, or underscores for the username.');
        }
        if ($fullName === '' || strlen($fullName) > 150) {
            throw new InvalidArgumentException('Enter the administrator’s full name.');
        }
        if (strlen($password) < 10 || $password !== $confirm) {
            throw new InvalidArgumentException('Passwords must match and contain at least 10 characters.');
        }
        $role = db_finance()->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1")->fetchColumn();
        if (!$role) {
            throw new RuntimeException('The Finance schema is incomplete. Re-import database/finance.sql.');
        }
        $stmt = db_finance()->prepare(
            'INSERT INTO users (username, full_name, password_hash, role_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $fullName, password_hash($password, PASSWORD_DEFAULT), $role]);
        audit('create', 'auth', 'user', (string) db_finance()->lastInsertId(), null, ['username' => $username]);
        $created = true;
    }
} catch (Throwable $exception) {
    $error = format_error($exception);
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>First-time setup · Finance</title>
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
    <?php if ($created): ?>
        <div class="auth-icon success-icon"><span class="bi bi-check"></span></div>
        <div class="eyebrow">SETUP COMPLETE</div>
        <h1>Administrator ready.</h1>
        <p class="auth-subtitle">Your account is ready. Sign in to begin connecting Finance to the local HRMS and Milk Tea POS databases.</p>
        <a class="btn btn-primary w-100" href="login.php">Continue to sign in <span class="bi bi-arrow-right"></span></a>
    <?php else: ?>
        <div class="eyebrow">FIRST-TIME SETUP</div>
        <h1>Create your administrator</h1>
        <p class="auth-subtitle">This is stored only in the local Finance database. There is no cloud account or tracking.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label class="form-label" for="full_name">Full name</label>
            <input class="form-control mb-3" id="full_name" name="full_name" required autocomplete="name">
            <label class="form-label" for="username">Username</label>
            <input class="form-control mb-3" id="username" name="username" required autocomplete="username">
            <label class="form-label" for="password">Password</label>
            <input class="form-control mb-3" id="password" name="password" type="password" minlength="10" required autocomplete="new-password">
            <label class="form-label" for="password_confirm">Confirm password</label>
            <input class="form-control mb-4" id="password_confirm" name="password_confirm" type="password" minlength="10" required autocomplete="new-password">
            <button class="btn btn-primary w-100" type="submit">Create administrator <span class="bi bi-arrow-right"></span></button>
        </form>
    <?php endif; ?>
    <a class="auth-back-link" href="index.php"><span class="bi bi-arrow-left"></span> Back to Finance home</a>
    <p class="auth-footnote">LOCALHOST / OFFLINE MODE · Session-only access</p>
</div>
<script src="assets/js/finance.js?v=20260819"></script>
</body>
</html>