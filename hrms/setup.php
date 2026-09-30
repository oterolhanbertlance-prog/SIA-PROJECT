<?php
/**
 * HRMS Setup / Password Reset Script
 * Run this ONCE after installation to verify or reset default passwords.
 * DELETE or rename this file after setup is complete.
 */

// Direct access only - no session needed
if (php_sapi_name() === 'cli') {
    die("Run this script via browser: http://localhost/hrms/setup.php\n");
}

$dbHost = 'localhost';
$dbName = 'hrms';
$dbUser = 'root';
$dbPass = '';

$message = '';
$success = false;

if (isset($_POST['run_setup'])) {
    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Generate fresh bcrypt hashes
        $hashes = [
            1 => ['username' => 'admin',    'password' => 'admin123'],
            2 => ['username' => 'hr',       'password' => 'hr123'],
            3 => ['username' => 'employee', 'password' => 'employee123'],
        ];

        foreach ($hashes as $id => $u) {
            $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 10]);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$hash, $id]);
        }

        $message = 'Passwords reset successfully! Default credentials:<br>admin / admin123 · hr / hr123 · employee / employee123';
        $success = true;
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage() . '<br>Check your database settings at the top of this file.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HRMS Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:500px;margin-top:80px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white fw-bold">HRMS — Setup &amp; Password Reset</div>
        <div class="card-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><?= $message ?></div>
            <?php endif; ?>
            <?php if (!$success): ?>
            <p class="text-muted small">This script will reset the default login passwords in the database to their factory values. Use this if you cannot log in after importing <code>hrms.sql</code>.</p>
            <form method="POST">
                <button type="submit" name="run_setup" class="btn btn-primary w-100">🔄 Reset Default Passwords</button>
            </form>
            <?php else: ?>
            <p class="text-muted small">⚠️ <strong>Delete or rename <code>setup.php</code></strong> after setup is complete to prevent unauthorized password resets.</p>
            <a href="index.php" class="btn btn-success w-100">→ Go to Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
