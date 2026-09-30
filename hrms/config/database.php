<?php
/**
 * HRMS Database Configuration
 * Uses PDO with prepared statements for SQL injection protection
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'hrms');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/* ── Site identity ────────────────────────────────────────────── */
define('SITE_NAME',    'Boba House — HR & Workforce Management');
define('COMPANY_NAME', 'Boba House Milk Tea');

/* ── SMTP / PHPMailer settings (editable here or via DB) ──────── */
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_SECURE',     'tls');   // 'tls' or 'ssl'
define('SMTP_AUTH',       true);
define('SMTP_USER',       'your_email@gmail.com');   // ← change
define('SMTP_PASS',       'your_app_password');       // ← use App Password for Gmail
define('SMTP_FROM_EMAIL', 'hr@hrms.local');
define('SMTP_FROM_NAME',  'Boba House Workforce');

/* ── User roles ───────────────────────────────────────────────── */
define('ROLES', [
    'admin'           => 'Admin',
    'hr'              => 'HR Officer',
    'inventory_staff' => 'Inventory Staff',
    'employee'        => 'Employee',
]);

/**
 * Auto-detect the base URL the app is being served from, so it works
 * whether it's hosted at the webserver root or in a subfolder.
 */
function detectSiteUrl(): string {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $appRoot = dirname(__DIR__);

    if (empty($docRoot)) return '/';

    $normalize = fn($p) => rtrim(str_replace('\\', '/', $p), '/');
    $docRoot   = $normalize($docRoot);
    $appRoot   = $normalize($appRoot);

    if (strpos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));
        return rtrim($base, '/') . '/';
    }
    return '/';
}

define('SITE_URL',     detectSiteUrl());
define('UPLOAD_PATH',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',   SITE_URL . 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

/**
 * Global exception handler — shows a readable message instead of blank screen.
 */
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    $msg       = htmlspecialchars($e->getMessage());
    $isMissing = stripos($msg, 'Base table or view not found') !== false
              || stripos($msg, "doesn't exist") !== false;
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>HRMS — Error</title>
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
          </head><body class="bg-light"><div class="container py-5" style="max-width:640px">
          <div class="card border-danger shadow-sm"><div class="card-body p-4">
          <h4 class="text-danger mb-3">Application Error</h4>';
    if ($isMissing) {
        echo '<div class="alert alert-warning"><strong>Missing database table.</strong><br>
              Import <code>database/hrms_complete.sql</code> into MySQL/phpMyAdmin,
              then refresh.</div>';
    } else {
        echo '<p class="text-muted small">' . $msg . '</p>';
    }
    echo '<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">← Go back</a>
          </div></div></div></body></html>';
    exit;
});


/**
 * Normalize legacy generic-company departments into the departments that
 * actually exist in the Boba House Milk Tea / POS operation.
 *
 * Canonical departments:
 *   1 Management & Administration
 *   2 Inventory & Supply
 *   3 Delivery & Logistics
 *   4 Store Operations
 *
 * Legacy corporate departments are kept only as inactive historical rows so
 * old foreign keys remain valid; active employees/contracts/jobs are remapped.
 */
function ensureBobaHouseDepartments(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $pdo->beginTransaction();

        $canonical = [
            1 => ['Management & Administration', 'Branch leadership, POS administration and workforce coordination'],
            2 => ['Inventory & Supply', 'Ingredients, stock, purchasing and inventory control'],
            3 => ['Delivery & Logistics', 'Delivery riders, dispatch and branch logistics'],
            4 => ['Store Operations', 'Cashier, counter service and day-to-day milk tea store operations'],
        ];
        $up = $pdo->prepare("UPDATE departments SET name=?, description=?, status='active' WHERE id=?");
        foreach ($canonical as $id => $d) {
            $up->execute([$d[0], $d[1], $id]);
        }

        // Hide generic corporate departments that do not belong to the milk-tea POS operation.
        $pdo->exec("UPDATE departments SET status='inactive' WHERE id NOT IN (1,2,3,4)");

        // The only workforce positions managed by this integrated POS/HRMS.
        $pdo->exec("UPDATE positions SET department_id=1, status='active' WHERE title='POS Super Admin'");
        $pdo->exec("UPDATE positions SET department_id=2, status='active' WHERE title='Inventory Staff'");
        $pdo->exec("UPDATE positions SET department_id=4, status='active' WHERE title='Cashier'");
        $pdo->exec("UPDATE positions SET status='inactive' WHERE title NOT IN ('POS Super Admin','Inventory Staff','Cashier')");

        // Keep current employees aligned with the operational department model.
        $pdo->exec("UPDATE employees e LEFT JOIN positions p ON p.id=e.position_id
                    SET e.department_id = CASE
                        WHEN p.title='POS Super Admin' THEN 1
                        WHEN p.title='Inventory Staff' THEN 2
                        WHEN p.title='Cashier' THEN 4
                        ELSE 4
                    END
                    WHERE e.status='active'");

        // Recruitment and contracts must use the same department as the POS role.
        $pdo->exec("UPDATE job_postings SET department_id=1 WHERE title='POS Super Admin'");
        $pdo->exec("UPDATE job_postings SET department_id=2 WHERE title='Inventory Staff'");
        $pdo->exec("UPDATE job_postings SET department_id=4 WHERE title='Cashier'");
        $pdo->exec("UPDATE contracts c JOIN positions p ON p.id=c.position_id
                    SET c.department_id=p.department_id
                    WHERE c.status='active' AND p.title IN ('POS Super Admin','Inventory Staff','Cashier')");

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Do not prevent login on older/partially migrated databases.
        error_log('Boba House department normalization skipped: '.$e->getMessage());
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Keep the HRMS organization model focused on Boba House Milk Tea.
            // HR is an application role/function, not a store department.
            ensureBobaHouseDepartments($pdo);
        } catch (PDOException $e) {
            die('<!DOCTYPE html><html><head><meta charset="UTF-8">
                <title>HRMS — DB Error</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
                </head><body class="bg-light"><div class="container py-5" style="max-width:580px">
                <div class="card border-danger shadow-sm"><div class="card-body p-4">
                <h4 class="text-danger">Database Connection Error</h4>
                <p>Could not connect to MySQL. Check <code>config/database.php</code> and make sure XAMPP/MySQL is running.</p>
                <p class="text-muted small">' . htmlspecialchars($e->getMessage()) . '</p>
                </div></div></div></body></html>');
        }
    }
    return $pdo;
}
