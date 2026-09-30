<?php
/**
 * Session helpers for the applicant self-service portal. Kept completely
 * separate from config/session.php (HR/admin/employee login) — an
 * applicant session and a staff session can never grant each other's
 * access.
 */
require_once __DIR__ . '/database.php';

// Always guarantee the session is started, regardless of include order.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

function applicantLoggedIn() {
    return !empty($_SESSION['applicant_account_id']);
}

function requireApplicantLogin() {
    if (!applicantLoggedIn()) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . SITE_URL . 'applicant/login.php' .
               ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    }
}

function currentApplicant() {
    static $cache = null;
    if (!applicantLoggedIn()) return null;
    if ($cache !== null) return $cache;
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM applicant_accounts WHERE id = ?");
        $stmt->execute([$_SESSION['applicant_account_id']]);
        $cache = $stmt->fetch() ?: null;
    } catch (Exception $e) {
        $cache = null;
    }
    if (!$cache) {
        unset($_SESSION['applicant_account_id']);
    }
    return $cache;
}
