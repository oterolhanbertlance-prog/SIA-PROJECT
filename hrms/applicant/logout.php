<?php
/**
 * Applicant Portal — Logout
 */
require_once '../config/database.php';
require_once '../config/session.php';          // ensures session_start()
require_once '../config/applicant_session.php';

unset($_SESSION['applicant_account_id']);
header('Location: ' . SITE_URL . 'applicant/login.php?msg=logged_out');
exit;
