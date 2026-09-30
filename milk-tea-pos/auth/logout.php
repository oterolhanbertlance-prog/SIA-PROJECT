<?php
session_start();
require_once __DIR__ . '/../includes/app_url.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_cart.php';

// Logging out must not erase a customer's shopping cart.
if (($_SESSION['role'] ?? '') === 'customer' && !empty($_SESSION['user_id'])) {
    try { saveCustomerCart(getDB(), (int)$_SESSION['user_id'], $_SESSION['cart'] ?? []); } catch (Throwable $e) { error_log('Cart save on logout failed: '.$e->getMessage()); }
}
// Expire the session cookie in the browser so it is not re-sent on the next request.
// Without this, the stale PHPSESSID cookie persists even after session_destroy().
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_unset();
session_destroy();
// Redirect to login (same directory — logout.php lives in auth/)
header('Location: ' . appUrl('auth/login.php'));
exit;
