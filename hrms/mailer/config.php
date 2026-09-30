<?php
/**
 * HR Mailer — Session & Auth helpers
 *
 * The mailer is only accessible to HR/Admin staff who are already
 * logged into the main HRMS system. There is no separate mailer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';  // ensures session_start()

/* ── Auth helpers ────────────────────────────────────────────────── */

function mailerLoggedIn(): bool {
    return !empty($_SESSION['user_id'])
        && in_array($_SESSION['role'] ?? '', ['admin', 'hr']);
}

function requireMailerLogin(): void {
    if (!mailerLoggedIn()) {
        header('Location: ' . SITE_URL . 'index.php?msg=mailer_access');
        exit;
    }
}

/**
 * Returns mailer info from the active staff session.
 */
function currentMailer(): array {
    return [
        'id'       => 'U' . ($_SESSION['user_id'] ?? ''),
        'username' => $_SESSION['username']  ?? '',
        'fullname' => $_SESSION['full_name'] ?? '',
        'email'    => '', // looked up from DB where needed
        'source'   => 'users',
        'role'     => $_SESSION['role'] ?? 'hr',
    ];
}

/**
 * "Logging out" of the mailer just returns to the HRMS dashboard —
 * it does NOT end the main staff session.
 */
function mailerLogout(): void {
    header('Location: ' . SITE_URL . 'dashboard.php');
    exit;
}
