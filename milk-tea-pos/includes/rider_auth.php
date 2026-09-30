<?php
/**
 * includes/rider_auth.php
 * Lightweight session auth for delivery riders, separate from the
 * users/auth_check.php system since riders are not `users` accounts —
 * they're rows in `delivery_riders`, identified by phone number.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function requireRiderLogin(string $redirectTo = 'login.php'): void {
    if (empty($_SESSION['rider_id'])) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

function currentRider(): array {
    return [
        'id'    => $_SESSION['rider_id']    ?? null,
        'name'  => $_SESSION['rider_name']  ?? '',
        'phone' => $_SESSION['rider_phone'] ?? '',
    ];
}
