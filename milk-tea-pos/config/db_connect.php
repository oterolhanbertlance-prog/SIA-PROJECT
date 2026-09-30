<?php
/**
 * integration/config/db_connect.php  — POS side
 * NEW file. The original config/db.php is NOT modified.
 *
 * Pulls in the existing POS connection (getDB()) then adds a
 * second singleton for the HRMS database (getHrmsDB()).
 *
 * Usage in any integration script inside the POS project:
 *   require_once __DIR__ . '/../config/db_connect.php';
 *   $pos  = getDB();        // existing POS PDO (from db.php)
 *   $hrms = getHrmsDB();    // new HRMS PDO
 */

require_once __DIR__ . '/db.php';   // ← original POS db.php — untouched

// ── HRMS credentials ──────────────────────────────────────────────────────────
define('HRMS_DB_HOST',    'localhost');
define('HRMS_DB_NAME',    'hrms');
define('HRMS_DB_USER',    'root');
define('HRMS_DB_PASS',    '');
define('HRMS_DB_CHARSET', 'utf8mb4');

/**
 * Singleton PDO connection to the hrms database.
 * Same pattern as the existing getDB() in db.php.
 */
function getHrmsDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . HRMS_DB_HOST
             . ";dbname=" . HRMS_DB_NAME
             . ";charset=" . HRMS_DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, HRMS_DB_USER, HRMS_DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;color:#721c24;background:#f8d7da;'
              . 'border:1px solid #f5c6cb;padding:1rem 1.5rem;margin:1rem;border-radius:.4rem">'
              . '<strong>HRMS Database Connection Failed.</strong><br>'
              . 'Check <code>config/db_connect.php</code> and confirm XAMPP is running.<br>'
              . '<small>' . htmlspecialchars($e->getMessage()) . '</small>'
              . '</div>');
        }
    }
    return $pdo;
}
