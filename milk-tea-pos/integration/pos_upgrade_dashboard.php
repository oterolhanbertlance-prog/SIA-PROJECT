<?php
/**
 * integration/pos_upgrade_dashboard.php
 * Entry point from the HRMS sidebar "POS Link" button.
 * Routes already-logged-in POS users to their dashboard,
 * or sends unauthenticated visitors to the POS login.
 */
session_start();
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';
    header('Location: ' . match($role) {
        'super_admin', 'inventory_staff' => $base . '/admin/dashboard.php',
        'customer', 'cashier' => $base . '/customer/index.php',
        default       => $base . '/admin/dashboard.php',
    }); exit;
}
header('Location: ' . $base . '/auth/login.php?from=hrms'); exit;
