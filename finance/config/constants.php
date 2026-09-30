<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');
define('FINANCE_ROOT', dirname(__DIR__));
define('FINANCE_APP_NAME', 'Finance');
define('FINANCE_SESSION_TIMEOUT', 1800);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) FINANCE_SESSION_TIMEOUT);
    session_name('FINANCE_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}