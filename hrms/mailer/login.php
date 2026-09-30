<?php
/**
 * HR Mailer — No separate login.
 * Access the mailer from your HR/Admin account inside HRMS.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// If already logged in as HR/Admin, go straight to mailer dashboard
if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'hr'])) {
    header('Location: ' . SITE_URL . 'mailer/dashboard.php');
    exit;
}

// Everyone else → staff login
header('Location: ' . SITE_URL . 'index.php?msg=mailer_access');
exit;
