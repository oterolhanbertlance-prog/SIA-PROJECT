<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('Your form expired. Please go back and try again.');
    }
}

function current_user(): ?array
{
    static $userLoaded = false;
    static $user = null;
    if ($userLoaded) {
        return $user;
    }
    $userLoaded = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    try {
        $stmt = db_finance()->prepare(
            'SELECT u.*, r.name AS role_name, r.label AS role_label
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch() ?: null;
    } catch (Throwable) {
        $user = null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if (!empty($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > FINANCE_SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php?expired=1');
    }
    $_SESSION['last_activity'] = time();
    return $user;
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($user['role_name'] === 'super_admin') {
        return true;
    }
    try {
        $stmt = db_finance()->prepare(
            'SELECT 1 FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
        );
        $stmt->execute([(int) $user['role_id'], $permission]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function require_permission(string $permission): void
{
    require_login();
    if (!has_permission($permission)) {
        http_response_code(403);
        exit('You are not authorized to access this area.');
    }
}

function audit(string $action, string $module, ?string $recordType = null, ?string $recordId = null, ?array $old = null, ?array $new = null): void
{
    try {
        $stmt = db_finance()->prepare(
            'INSERT INTO audit_logs (user_id, action, module, record_type, record_id, old_data, new_data, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            current_user()['id'] ?? null,
            $action,
            $module,
            $recordType,
            $recordId,
            $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {
        // Audit logging must not make the source-system sync fail.
    }
}

function money(mixed $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function date_label(?string $date): string
{
    return $date ? date('M d, Y', strtotime($date)) : '—';
}

function page_title(string $title): string
{
    return $title . ' · ' . FINANCE_APP_NAME;
}

function setting(string $key, string $fallback = ''): string
{
    try {
        $stmt = db_finance()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return (string) ($stmt->fetchColumn() ?: $fallback);
    } catch (Throwable) {
        return $fallback;
    }
}

function account_id(string $code): int
{
    $stmt = db_finance()->prepare('SELECT id FROM accounts WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    return (int) $stmt->fetchColumn();
}

function active_period(?string $date = null): ?array
{
    $date ??= date('Y-m-d');
    $stmt = db_finance()->prepare(
        'SELECT * FROM fiscal_periods WHERE start_date <= ? AND end_date >= ? AND status <> "closed" LIMIT 1'
    );
    $stmt->execute([$date, $date]);
    return $stmt->fetch() ?: null;
}

function format_error(Throwable $error): string
{
    return $error instanceof PDOException
        ? 'Database error. Confirm that the Finance database was imported and the local connection settings are correct.'
        : $error->getMessage();
}

/**
 * Adds tables introduced after the original Finance ZIP was released.
 * This keeps existing XAMPP databases usable while still shipping the full
 * definitions in database/finance.sql for clean installations.
 */

/**
 * v2 integration schema: keeps Finance linked to the existing HRMS/POS
 * identities and physical branches without modifying either source database.
 */
function ensure_connected_system_schema(): void
{
    static $ready = false;
    if ($ready) return;
    $db = db_finance();

    $db->exec("CREATE TABLE IF NOT EXISTS external_identities (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        finance_user_id INT UNSIGNED NOT NULL,
        source_system ENUM('hrms','pos') NOT NULL,
        source_user_id INT NOT NULL,
        source_username VARCHAR(120) NULL,
        source_email VARCHAR(180) NULL,
        source_role VARCHAR(80) NULL,
        source_branch_id INT NULL,
        last_synced_at DATETIME NULL,
        PRIMARY KEY(id),
        UNIQUE KEY uq_external_identity(source_system,source_user_id),
        KEY idx_external_finance_user(finance_user_id)
    ) ENGINE=InnoDB");

    $branchCols = [
        'branch_code' => "VARCHAR(40) NULL",
        'city' => "VARCHAR(120) NULL",
        'latitude' => "DECIMAL(10,7) NULL",
        'longitude' => "DECIMAL(10,7) NULL",
        'phone' => "VARCHAR(40) NULL",
        'manager_name' => "VARCHAR(150) NULL",
        'hrms_branch_id' => "INT NULL",
        'pos_branch_id' => "INT NULL",
        'updated_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    foreach($branchCols as $c=>$def){ try{$db->exec("ALTER TABLE branches ADD COLUMN `$c` $def");}catch(Throwable){} }
    foreach([
        'branch_id'=>"INT UNSIGNED NULL",
        'source_branch_id'=>"INT NULL"
    ] as $c=>$def){ try{$db->exec("ALTER TABLE employee_snapshots ADD COLUMN `$c` $def");}catch(Throwable){} }
    foreach([
        'branch_id'=>"INT UNSIGNED NULL",
        'source_branch_id'=>"INT NULL"
    ] as $c=>$def){ try{$db->exec("ALTER TABLE payroll_snapshots ADD COLUMN `$c` $def");}catch(Throwable){} }
    foreach([
        'branch_id'=>"INT UNSIGNED NULL",
        'source_branch_id'=>"INT NULL"
    ] as $c=>$def){ try{$db->exec("ALTER TABLE sales_snapshots ADD COLUMN `$c` $def");}catch(Throwable){} }
    try{$db->exec("ALTER TABLE journal_entries ADD COLUMN branch_id INT UNSIGNED NULL");}catch(Throwable){}

    foreach([
        'source_system'=>"VARCHAR(20) NULL",
        'source_user_id'=>"INT NULL",
        'source_employee_id'=>"INT NULL",
        'branch_id'=>"INT UNSIGNED NULL"
    ] as $c=>$def){ try{$db->exec("ALTER TABLE users ADD COLUMN `$c` $def");}catch(Throwable){} }

    $db->exec("INSERT IGNORE INTO permissions(name) VALUES ('integration.manage'),('branches.view')");
    $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
      SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
      WHERE r.name='super_admin'");
    $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
      SELECT r.id,p.id FROM roles r JOIN permissions p
      WHERE r.name='finance_manager' AND p.name IN
      ('dashboard.view','sales.view','sales.sync','payroll.view','payroll.sync','journal.view','reports.view','integration.manage','branches.view','gps.manage')");
    $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
      SELECT r.id,p.id FROM roles r JOIN permissions p
      WHERE r.name='finance_staff' AND p.name IN ('dashboard.view','sales.view','payroll.view','journal.view','reports.view','branches.view')");
    $db->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
      SELECT r.id,p.id FROM roles r JOIN permissions p
      WHERE r.name='viewer' AND p.name IN ('dashboard.view','sales.view','payroll.view','journal.view','reports.view','branches.view')");
    $ready = true;
}

function finance_role_id(string $roleName): int {
    $q=db_finance()->prepare("SELECT id FROM roles WHERE name=? LIMIT 1");$q->execute([$roleName]);return (int)$q->fetchColumn();
}
function finance_role_for_external(string $system,string $role): ?string {
    $role=strtolower(trim($role));
    if($system==='hrms') return match($role){'admin'=>'super_admin','hr'=>'finance_manager','employee'=>'viewer',default=>null};
    if($system==='pos') return match($role){'super_admin'=>'finance_manager','inventory_staff','cashier'=>'finance_staff',default=>null};
    return null;
}
function finance_branch_id_from_source(string $system, ?int $sourceId): ?int {
    if(!$sourceId) return null; ensure_connected_system_schema();
    $col=$system==='hrms'?'hrms_branch_id':'pos_branch_id';
    $q=db_finance()->prepare("SELECT id FROM branches WHERE `$col`=? LIMIT 1");$q->execute([$sourceId]);
    $id=(int)$q->fetchColumn(); return $id?:null;
}
function upsert_external_finance_user(string $system,array $src): ?int {
    ensure_connected_system_schema();
    $roleName=finance_role_for_external($system,(string)($src['role']??'')); if(!$roleName) return null;
    $db=db_finance(); $sourceId=(int)$src['id']; $username=trim((string)($src['username']??''));
    $email=trim((string)($src['email']??''));
    $full=trim((string)(($src['firstname']??'').' '.($src['lastname']??'')));
    if($full==='') $full=$username ?: ($email ?: strtoupper($system).' User');
    $branchSource=(int)($src['branch_id']??0); $branchId=finance_branch_id_from_source($system,$branchSource?:null);
    $q=$db->prepare("SELECT finance_user_id FROM external_identities WHERE source_system=? AND source_user_id=?");
    $q->execute([$system,$sourceId]); $fid=(int)$q->fetchColumn();
    if(!$fid && $username!==''){
        $q=$db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");$q->execute([$username]);$fid=(int)$q->fetchColumn();
    }
    $hash=(string)($src['password']??'');
    if($fid){
        $db->prepare("UPDATE users SET full_name=?,role_id=?,is_active=1,source_system=COALESCE(source_system,?),source_user_id=COALESCE(source_user_id,?),branch_id=? WHERE id=?")
           ->execute([$full,finance_role_id($roleName),$system,$sourceId,$branchId,$fid]);
    }else{
        $base=$username!==''?$username:($system.'_user_'.$sourceId);$candidate=$base;$n=2;
        while(true){$c=$db->prepare("SELECT COUNT(*) FROM users WHERE username=?");$c->execute([$candidate]);if(!(int)$c->fetchColumn())break;$candidate=$base.'_'.$system.$n++;}
        $db->prepare("INSERT INTO users(username,full_name,password_hash,role_id,is_active,source_system,source_user_id,source_employee_id,branch_id) VALUES(?,?,?,?,1,?,?,?,?,?)")
           ->execute([$candidate,$full,$hash!==''?$hash:password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),finance_role_id($roleName),$system,$sourceId,$src['employee_id']??null,$branchId]);
        $fid=(int)$db->lastInsertId();
    }
    $db->prepare("INSERT INTO external_identities(finance_user_id,source_system,source_user_id,source_username,source_email,source_role,source_branch_id,last_synced_at)
      VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE finance_user_id=VALUES(finance_user_id),source_username=VALUES(source_username),
      source_email=VALUES(source_email),source_role=VALUES(source_role),source_branch_id=VALUES(source_branch_id),last_synced_at=NOW()")
      ->execute([$fid,$system,$sourceId,$username,$email,$src['role']??null,$branchSource?:null]);
    return $fid;
}

function ensure_optional_modules(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $db = db_finance();
    $db->exec(
        "CREATE TABLE IF NOT EXISTS mail_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient VARCHAR(180) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
            sent_at DATETIME NULL,
            error_message VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_mail_queue_status (status, created_at)
        ) ENGINE=InnoDB"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS location_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id INT UNSIGNED NULL,
            label VARCHAR(150) NOT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            purpose VARCHAR(180) NOT NULL,
            logged_at DATETIME NOT NULL,
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_location_logs_date (logged_at)
        ) ENGINE=InnoDB"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS attendance_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_snapshot_id BIGINT UNSIGNED NOT NULL,
            attendance_date DATE NOT NULL,
            time_in DATETIME NULL,
            time_out DATETIME NULL,
            status ENUM('present','late','absent','on_leave','half_day') NOT NULL DEFAULT 'present',
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uq_attendance_employee_date (employee_snapshot_id, attendance_date),
            KEY idx_attendance_date_status (attendance_date, status)
        ) ENGINE=InnoDB"
    );
    foreach ([
        'geo_latitude' => 'DECIMAL(10,7) NULL',
        'geo_longitude' => 'DECIMAL(10,7) NULL',
    ] as $column => $definition) {
        try {
            $db->exec("ALTER TABLE attendance_logs ADD COLUMN {$column} {$definition}");
        } catch (Throwable) {
            // The column already exists on upgraded installations.
        }
    }
    $db->exec(
        "INSERT IGNORE INTO permissions (name) VALUES
            ('mailer.manage'), ('gps.manage'), ('attendance.manage')"
    );
    $db->exec(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r JOIN permissions p
         WHERE r.name IN ('finance_manager','accountant')
           AND p.name IN ('mailer.manage','gps.manage','attendance.manage')"
    );
    $ready = true;
}