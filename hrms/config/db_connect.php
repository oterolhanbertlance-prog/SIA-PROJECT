<?php
/**
 * config/db_connect.php  — HRMS side  (NEW file — original database.php untouched)
 *
 * Loads the existing HRMS connection (getDB()) then adds a second singleton
 * for the POS database (getPosDB()), plus two read-only lookup helpers.
 *
 * Usage in any integration script:
 *   require_once __DIR__ . '/../config/db_connect.php';   // from integration/
 *   $hrms = getDB();        // existing HRMS PDO
 *   $pos  = getPosDB();     // POS PDO
 */

require_once __DIR__ . '/database.php';   // ← original HRMS database.php — untouched

// ── POS credentials ───────────────────────────────────────────────────────────
if (!defined('POS_DB_HOST')) define('POS_DB_HOST',    'localhost');
if (!defined('POS_DB_NAME')) define('POS_DB_NAME',    'milk_tea_pos');
if (!defined('POS_DB_USER')) define('POS_DB_USER',    'root');
if (!defined('POS_DB_PASS')) define('POS_DB_PASS',    '');
if (!defined('POS_DB_CHARSET')) define('POS_DB_CHARSET', 'utf8mb4');

/**
 * Singleton PDO connection to the milk_tea_pos database.
 */
function getPosDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . POS_DB_HOST
             . ";dbname=" . POS_DB_NAME
             . ";charset=" . POS_DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, POS_DB_USER, POS_DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;color:#721c24;background:#f8d7da;'
              . 'border:1px solid #f5c6cb;padding:1rem 1.5rem;margin:1rem;border-radius:.4rem">'
              . '<strong>POS Database Connection Failed.</strong><br>'
              . 'Check <code>config/db_connect.php</code> and confirm XAMPP is running with the <code>milk_tea_pos</code> database imported.<br>'
              . '<small>' . htmlspecialchars($e->getMessage()) . '</small>'
              . '</div>');
        }
    }
    return $pdo;
}

/** Find a POS user by email. Returns row array or null. */
function findPosUserByEmail(string $email): ?array {
    $st = getPosDB()->prepare(
        "SELECT id, username, email, role, is_active FROM users WHERE email = ? LIMIT 1"
    );
    $st->execute([$email]);
    return $st->fetch() ?: null;
}

/** Find a POS user by username. Returns row array or null. */
function findPosUserByUsername(string $username): ?array {
    $st = getPosDB()->prepare(
        "SELECT id, username, email, role, is_active FROM users WHERE username = ? LIMIT 1"
    );
    $st->execute([$username]);
    return $st->fetch() ?: null;
}
