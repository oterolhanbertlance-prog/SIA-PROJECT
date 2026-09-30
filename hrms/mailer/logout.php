<?php
/**
 * HR Mailer — "Logout" returns to the HRMS dashboard.
 * It does NOT end the main staff session.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
header('Location: ' . SITE_URL . 'dashboard.php');
exit;
