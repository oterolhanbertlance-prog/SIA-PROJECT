<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    auditLog('LOGOUT', 'Authentication', 'User logged out');
}

// Clear session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ' . SITE_URL . 'index.php?msg=logged_out');
exit;
