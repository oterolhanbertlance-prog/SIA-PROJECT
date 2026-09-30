<?php
/**
 * HRMS — Central helper library
 *
 * Every PHP file does:
 *   require_once 'config/database.php';
 *   require_once 'includes/functions.php';
 *
 * This file pulls in session helpers so callers never include
 * config/session.php separately. All utility functions live here.
 */

$_fnDir = dirname(__DIR__);
require_once $_fnDir . '/config/session.php';
unset($_fnDir);


/* ══════════════════════════════════════════════════════════════════════
   INPUT SANITISATION
   ══════════════════════════════════════════════════════════════════════ */

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* ══════════════════════════════════════════════════════════════════════
   DATE / TIME FORMATTING
   ══════════════════════════════════════════════════════════════════════ */

function formatDate(?string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '—';
    try { return (new DateTime($date))->format('M j, Y'); } catch (Exception $e) { return htmlspecialchars($date); }
}

function formatDateTime(?string $datetime): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') return '—';
    try { return (new DateTime($datetime))->format('M j, Y g:i A'); } catch (Exception $e) { return htmlspecialchars($datetime); }
}


/* ══════════════════════════════════════════════════════════════════════
   CSRF PROTECTION
   ══════════════════════════════════════════════════════════════════════ */

function generateCSRF(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $stored = $_SESSION['csrf_token'] ?? '';
    return !empty($stored) && hash_equals($stored, $token);
}

/** Render hidden CSRF input. Usage in forms: <?= csrfField() ?> */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF(), ENT_QUOTES, 'UTF-8') . '">';
}


/* ══════════════════════════════════════════════════════════════════════
   FILE UPLOADS
   ══════════════════════════════════════════════════════════════════════ */

function uploadFile(array $fileInput, string $subfolder = 'uploads', ?array $allowedMimes = null): string {
    if (!isset($fileInput['error']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE  => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE=> 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension.',
        ];
        throw new RuntimeException($errors[$fileInput['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Unknown upload error.');
    }
    $maxBytes = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5 * 1024 * 1024;
    if ($fileInput['size'] > $maxBytes) throw new RuntimeException('File too large. Max ' . round($maxBytes / 1048576, 1) . ' MB.');

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileInput['tmp_name']);
    $allowed  = $allowedMimes ?? ['image/jpeg','image/png','image/gif','image/webp','application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($mimeType, $allowed, true)) throw new RuntimeException('File type not allowed: ' . htmlspecialchars($mimeType));

    $uploadRoot = defined('UPLOAD_PATH') ? UPLOAD_PATH : dirname(__DIR__) . '/uploads/';
    $destDir    = rtrim($uploadRoot, '/') . '/' . trim($subfolder, '/') . '/';
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) throw new RuntimeException('Could not create upload directory.');

    $ext      = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
    $filename = uniqid('', true) . ($ext ? '.' . $ext : '');
    if (!move_uploaded_file($fileInput['tmp_name'], $destDir . $filename)) throw new RuntimeException('Failed to save uploaded file.');

    return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
}


/* ══════════════════════════════════════════════════════════════════════
   PAGINATION
   ══════════════════════════════════════════════════════════════════════ */

function paginate(int $total, int $perPage, int $current, string $baseUrl): string {
    if ($perPage < 1) $perPage = 15;
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">';
    $html .= $current > 1
        ? '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($baseUrl) . ($current-1) . '">&laquo;</a></li>'
        : '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    $window = 2;
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || abs($p - $current) <= $window) {
            $active = $p === $current ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . htmlspecialchars($baseUrl) . $p . '">' . $p . '</a></li>';
        } elseif (abs($p - $current) === $window + 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    $html .= $current < $pages
        ? '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($baseUrl) . ($current+1) . '">&raquo;</a></li>'
        : '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    return $html . '</ul></nav>';
}


/* ══════════════════════════════════════════════════════════════════════
   UI HELPERS — BADGES, MONEY, DATES
   ══════════════════════════════════════════════════════════════════════ */

function statusBadge(string $status): string {
    $map = [
        // Employment
        'active'=>'success','Regular'=>'success','inactive'=>'secondary',
        'resigned'=>'secondary','Resigned'=>'secondary',
        'terminated'=>'danger','Terminated'=>'danger',
        'Probationary'=>'warning','Contractual'=>'info',
        // Approval flow
        'pending'=>'warning','admin_approved'=>'info','approved'=>'success',
        'rejected'=>'danger','cancelled'=>'secondary','withdrawn'=>'secondary',
        // Payroll
        'processed'=>'primary','released'=>'success','draft'=>'secondary',
        // Recruitment
        'initial_interview'=>'info','final_interview'=>'primary',
        'passed'=>'success','failed'=>'danger','hired'=>'success',
        // Interviews
        'scheduled'=>'info','completed'=>'success',
        'no_show'=>'danger','rescheduled'=>'warning',
        // Contracts
        'ongoing'=>'success','ended'=>'secondary',
        'terminated_early'=>'danger','probationary'=>'warning','regular'=>'success',
        // Users / Roles
        'admin'=>'danger','hr'=>'primary','employee'=>'secondary','inventory_staff'=>'info',
        // Job postings
        'open'=>'success','closed'=>'secondary','paused'=>'warning',
        // Attendance
        'present'=>'success','absent'=>'danger','late'=>'warning',
        'half_day'=>'info','on_leave'=>'primary',
        // Awaiting labels
        'Awaiting Admin'=>'warning','Awaiting HR'=>'info',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/** Format a number as Philippine Peso. e.g. money(12500.5) → ₱12,500.50 */
function money(float $amount): string {
    return '₱' . number_format($amount, 2);
}

/** Convert month number 1–12 to full month name. */
function monthName(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}


/* ══════════════════════════════════════════════════════════════════════
   PAYROLL / TIMEKEEPING CALCULATIONS
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Peso deduction for late/undertime minutes.
 * Rate = monthly_salary ÷ 22 working days ÷ 480 minutes per day.
 */
function deductionFromMinutes(float $monthlySalary, int $minutes): float {
    if ($minutes <= 0) return 0.0;
    return round(($monthlySalary / 22 / 480) * $minutes, 2);
}

/**
 * Peso deduction for whole absent days.
 * Rate = monthly_salary ÷ 22 working days.
 */
function deductionFromAbsentDays(float $monthlySalary, int $days): float {
    if ($days <= 0) return 0.0;
    return round(($monthlySalary / 22) * $days, 2);
}

/**
 * Minutes late relative to standard 08:00 start.
 * @param string $timeIn  'H:i:s' or 'H:i'
 */
function calcLateMinutes(string $timeIn): int {
    $start  = strtotime('08:00:00');
    $actual = strtotime($timeIn);
    if ($actual === false || $actual <= $start) return 0;
    return (int) ceil(($actual - $start) / 60);
}

/**
 * Minutes undertime relative to standard 17:00 end.
 * @param string $timeOut  'H:i:s' or 'H:i'
 */
function calcUndertimeMinutes(string $timeOut): int {
    $end    = strtotime('17:00:00');
    $actual = strtotime($timeOut);
    if ($actual === false || $actual >= $end) return 0;
    return (int) ceil(($end - $actual) / 60);
}


/* ══════════════════════════════════════════════════════════════════════
   CODE / ID GENERATORS
   ══════════════════════════════════════════════════════════════════════ */

function generateEmployeeId(): string {
    $year = date('Y');
    try {
        $last = getDB()->query("SELECT employee_id FROM employees WHERE employee_id LIKE 'EMP-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
    } catch (Throwable $e) { $seq = 1; }
    return 'EMP-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generateApplicantCode(): string {
    $year = date('Y');
    try {
        $last = getDB()->query("SELECT applicant_code FROM applicants WHERE applicant_code LIKE 'APP-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
    } catch (Throwable $e) { $seq = 1; }
    return 'APP-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generateLeaveCode(): string {
    $year = date('Y');
    try {
        $last = getDB()->query("SELECT leave_code FROM leave_requests WHERE leave_code LIKE 'LV-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
    } catch (Throwable $e) { $seq = 1; }
    return 'LV-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generatePayrollCode(int $month, int $year): string {
    $prefix = 'PR-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
    try {
        $last = getDB()->query("SELECT payroll_code FROM payroll WHERE payroll_code LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
    } catch (Throwable $e) { $seq = 1; }
    return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generateContractNumber(): string {
    $year = date('Y');
    try {
        $last = getDB()->query("SELECT contract_number FROM contracts WHERE contract_number LIKE 'CON-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq  = $last ? ((int) substr($last, -4)) + 1 : 1;
    } catch (Throwable $e) { $seq = 1; }
    return 'CON-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}


/* ══════════════════════════════════════════════════════════════════════
   IN-APP NOTIFICATIONS
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Insert an in-app notification row for an applicant portal account.
 * @param int    $applicantAccountId  applicant_accounts.id
 * @param string $type   info | success | warning | danger
 * @param string|null $link  Optional URL
 */
function notifyApplicant(int $applicantAccountId, string $title, string $message, string $type = 'info', ?string $link = null): void {
    try {
        getDB()->prepare(
            "INSERT INTO notifications (applicant_account_id, user_id, title, message, type, link)
             VALUES (?, NULL, ?, ?, ?, ?)"
        )->execute([$applicantAccountId, $title, $message, $type, $link]);
    } catch (Throwable $e) {
        // Fallback: schema may not have applicant_account_id yet
        try {
            getDB()->prepare(
                "INSERT INTO notifications (user_id, title, message, type, link)
                 VALUES (NULL, ?, ?, ?, ?)"
            )->execute([$title, $message, $type, $link]);
        } catch (Throwable $e2) { /* silent — never crash for a notification */ }
    }
}


/* ══════════════════════════════════════════════════════════════════════
   APPLICANT STATUS LABEL
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Return a human-readable label for an applicant's status, checking
 * the interviews table to distinguish "Interview Scheduled" vs "Interviewed".
 *
 * @param int    $applicantId
 * @param string $status       Raw status from applicants table
 * @param PDO    $db
 * @return string  e.g. 'Pending', 'Interview Scheduled', 'Hired', …
 */
function applicantStatusLabel(int $applicantId, string $status, PDO $db): string {
    $map = [
        'pending'           => 'Pending',
        'initial_interview' => 'Interview Scheduled',
        'final_interview'   => 'Interview Scheduled',
        'passed'            => 'Accepted',
        'hired'             => 'Hired',
        'failed'            => 'Rejected',
        'withdrawn'         => 'Withdrawn',
    ];
    if (in_array($status, ['initial_interview','final_interview'], true)) {
        try {
            $done = $db->prepare("SELECT COUNT(*) FROM interviews WHERE applicant_id=? AND status='completed'");
            $done->execute([$applicantId]);
            if ((int)$done->fetchColumn() > 0) return 'Interviewed';
        } catch (Throwable $e) {}
    }
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}


/* ══════════════════════════════════════════════════════════════════════
   AUTO-CREATE EMPLOYEE FROM APPLICANT
   ══════════════════════════════════════════════════════════════════════ */

/**
 * When an applicant is marked as passed/hired, automatically create an
 * employees row and a staff users row for them.
 *
 * @param  int        $applicantId
 * @return array|null  ['username' => …, 'password' => …, 'employee_id' => …]
 *                     or null if not found / already deployed
 */
function autoCreateEmployeeFromApplicant(int $applicantId): ?array {
    try {
        $db = getDB();
        // POS role is stored separately from the HRMS login role.
        require_once dirname(__DIR__) . '/integration/pos_common.php';
        require_once dirname(__DIR__) . '/config/db_connect.php';
        $stmt = $db->prepare(
            "SELECT a.*, j.title AS job_title,
                    COALESCE(j.department_id, p.department_id) AS resolved_department_id,
                    COALESCE(j.position_id, p.id) AS resolved_position_id,
                    p.title AS position_title,
                    j.salary_min AS job_salary_min
               FROM applicants a
               LEFT JOIN job_postings j ON j.id=a.job_id
               LEFT JOIN positions p ON p.id=j.position_id
                                      OR (j.position_id IS NULL AND p.title=j.title)
              WHERE a.id=?"
        );
        $stmt->execute([$applicantId]);
        $app = $stmt->fetch();
        if (!$app) return null;

        $posRole = null;
        if (!empty($app['job_id'])) {
            $posRole = posRoleFromJobTitle((string)($app['position_title'] ?? ''));
            if (!$posRole) $posRole = posRoleFromJobTitle((string)($app['job_title'] ?? ''));
            try {
                $tag = $db->prepare("SELECT pos_role_target FROM pos_job_postings_meta WHERE job_posting_id=? LIMIT 1");
                $tag->execute([(int)$app['job_id']]);
                $taggedRole = $tag->fetchColumn();
                if (in_array($taggedRole, ['super_admin','cashier','inventory_staff'], true)) {
                    $posRole = $taggedRole;
                }
            } catch (Throwable $e) {}
        }

        $departmentId = (int)($app['resolved_department_id'] ?? 0) ?: null;
        $positionId   = (int)($app['resolved_position_id'] ?? 0) ?: null;
        $startDate    = date('Y-m-d');
        $salary       = (float)($app['job_salary_min'] ?? 0);
        $createdBy    = (int)($_SESSION['user_id'] ?? 0) ?: null;

        // Re-running the hire action should repair missing links instead of
        // silently doing nothing. Never reuse an admin/HR account by email.
        $userStmt = $db->prepare(
            "SELECT id, username, password, role, employee_id
               FROM users
              WHERE LOWER(email)=LOWER(?)
              LIMIT 1"
        );
        $userStmt->execute([$app['email']]);
        $existingUser = $userStmt->fetch() ?: null;
        if ($existingUser && ($existingUser['role'] ?? '') !== 'employee') return null;

        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
        $db->exec("ALTER TABLE users MODIFY COLUMN pos_role ENUM('super_admin','cashier','inventory_staff') NULL AFTER role");
        $db->beginTransaction();

        // 1. Find or create the employee record. Job department/position are
        // the source of truth, including the position's department fallback.
        $empStmt = $db->prepare("SELECT * FROM employees WHERE LOWER(email)=LOWER(?) ORDER BY id DESC LIMIT 1");
        $empStmt->execute([$app['email']]);
        $employee = $empStmt->fetch() ?: null;
        if ($existingUser && !empty($existingUser['employee_id'])) {
            $linkedStmt = $db->prepare("SELECT * FROM employees WHERE id=? LIMIT 1");
            $linkedStmt->execute([(int)$existingUser['employee_id']]);
            $employee = $linkedStmt->fetch() ?: $employee;
        }

        if ($employee) {
            $empDbId = (int)$employee['id'];
            $empCode = $employee['employee_id'];
            $db->prepare(
                "UPDATE employees
                    SET firstname=?, lastname=?, phone=?, department_id=COALESCE(?, department_id),
                        position_id=COALESCE(?, position_id), basic_salary=CASE WHEN basic_salary=0 THEN ? ELSE basic_salary END,
                        employment_status='Probationary', date_hired=COALESCE(date_hired, ?), status='active'
                  WHERE id=?"
            )->execute([
                $app['firstname'], $app['lastname'], $app['phone'] ?? '',
                $departmentId, $positionId, $salary, $startDate, $empDbId,
            ]);
        } else {
            $empCode = generateEmployeeId();
            $db->prepare(
                "INSERT INTO employees
                 (employee_id, firstname, lastname, middlename, email, phone, address, gender,
                  department_id, position_id, employment_status, date_hired, basic_salary,
                  highest_education, school, course, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?, 'Probationary', ?, ?, ?, ?, ?, 'active')"
            )->execute([
                $empCode,
                $app['firstname'], $app['lastname'], $app['middlename'] ?? null,
                $app['email'], $app['phone'] ?? '', $app['address'] ?? '', $app['gender'] ?? null,
                $departmentId, $positionId, $startDate, $salary,
                $app['highest_education'] ?? '', $app['school'] ?? '', $app['course'] ?? '',
            ]);
            $empDbId = (int)$db->lastInsertId();
        }

        // 2. Find or create the HRMS employee login. The HRMS role remains
        // employee; only pos_role changes the person's POS permissions.
        $newAccount = false;
        if ($existingUser) {
            $hrmsUserId = (int)$existingUser['id'];
            $username   = $existingUser['username'];
            $password   = '';
            $hash       = $existingUser['password'];
            $db->prepare(
                "UPDATE users SET employee_id=?, role='employee', pos_role=?, status='active' WHERE id=?"
            )->execute([$empDbId, $posRole, $hrmsUserId]);
        } else {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $app['firstname'])) ?: 'employee';
            $username = $base . rand(100, 999);
            while (true) {
                $u = $db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
                $u->execute([$username]);
                if (!$u->fetch()) break;
                $username = $base . rand(100, 999);
            }
            $password = bin2hex(random_bytes(4));   // 8-char temp password
            $hash     = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare(
                "INSERT INTO users (username, email, password, role, pos_role, firstname, lastname, phone, employee_id, status)
                 VALUES (?,?,?,'employee',?,?,?,?,?, 'active')"
            )->execute([$username, $app['email'], $hash, $posRole, $app['firstname'], $app['lastname'], $app['phone'] ?? '', $empDbId]);
            $hrmsUserId = (int)$db->lastInsertId();
            $newAccount = true;
        }

        // 3. Ensure a deployment contract exists and carries the same
        // department, position, salary, and hire date.
        $contractStmt = $db->prepare(
            "SELECT id FROM contracts WHERE employee_id=? AND status IN ('active','renewed')
             ORDER BY id DESC LIMIT 1"
        );
        $contractStmt->execute([$empDbId]);
        $contractId = $contractStmt->fetchColumn();
        if ($contractId) {
            $db->prepare(
                "UPDATE contracts
                    SET department_id=COALESCE(?, department_id),
                        position_id=COALESCE(?, position_id),
                        salary=CASE WHEN salary=0 THEN ? ELSE salary END,
                        start_date=COALESCE(start_date, ?)
                  WHERE id=?"
            )->execute([$departmentId, $positionId, $salary, $startDate, $contractId]);
        } else {
            $db->prepare(
                "INSERT INTO contracts
                 (employee_id, contract_number, contract_type, start_date, department_id, position_id, salary, status, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,'active',?,?)"
            )->execute([
                $empDbId, generateContractNumber(), 'Probationary', $startDate,
                $departmentId, $positionId, $salary,
                'Created automatically when the applicant was hired.', $createdBy,
            ]);
        }

        // 4. Initialize leave balances for the deployment record.
        $leaveTypes = $db->query("SELECT id, days_allowed FROM leave_types WHERE status='active'")->fetchAll();
        $lbStmt = $db->prepare("INSERT IGNORE INTO leave_balance (employee_id,leave_type_id,year,total_days,used_days,remaining_days) VALUES (?,?,?,?,0,?)");
        foreach ($leaveTypes as $lt) $lbStmt->execute([$empDbId,$lt['id'],date('Y'),$lt['days_allowed'],$lt['days_allowed']]);

        // 5. Mark applicant as hired.
        $db->prepare("UPDATE applicants SET status='hired' WHERE id=?")->execute([$applicantId]);

        // 6. Store credentials on applicant_account if this run created them.
        try {
            $acctId = null;
            if (!empty($app['applicant_account_id'])) {
                $acctId = $app['applicant_account_id'];
            } else {
                $a = $db->prepare("SELECT id FROM applicant_accounts WHERE email=?");
                $a->execute([$app['email']]);
                $acctId = $a->fetchColumn() ?: null;
            }
            if ($acctId) {
                $db->prepare(
                    "UPDATE applicant_accounts SET hired_username=?, hired_temp_password=?, hired_employee_id=? WHERE id=?"
                )->execute([$username, $newAccount ? $password : null, $empCode, $acctId]);
            }
        } catch (Throwable $e) {}

        $db->commit();

        if ($posRole) {
            try {
                syncHrmsUserToPos(getPosDB(), [
                    'id' => $hrmsUserId,
                    'username' => $username,
                    'password' => $hash,
                    'email' => $app['email'],
                    'firstname' => $app['firstname'],
                    'lastname' => $app['lastname'],
                ], $posRole, $empDbId);
            } catch (Throwable $e) {
                error_log('[HRMS autoCreateEmployee POS sync] ' . $e->getMessage());
            }
        }
        auditLog('CREATE','Deployment',
            "Deployed applicant {$app['firstname']} {$app['lastname']} ($empCode / $username) from applicant #{$applicantId}"
            . ($posRole ? " with POS role $posRole" : ''));

        return [
            'username' => $username,
            'password' => $password,
            'employee_id' => $empCode,
            'new_account' => $newAccount,
        ];

    } catch (Throwable $e) {
        try { getDB()->rollBack(); } catch (Throwable $r) {}
        error_log('[HRMS autoCreateEmployee] ' . $e->getMessage());
        return null;
    }
}


/* ══════════════════════════════════════════════════════════════════════
   AUDIT LOGGING
   ══════════════════════════════════════════════════════════════════════ */

function auditLog(string $action, string $module, string $description): void {
    try {
        getDB()->prepare(
            "INSERT INTO audit_logs (user_id, username, action, module, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $_SESSION['user_id']  ?? null,
            $_SESSION['username'] ?? 'system',
            $action, $module, $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* never crash for logging */ }
}


/* ══════════════════════════════════════════════════════════════════════
   DASHBOARD STATS
   ══════════════════════════════════════════════════════════════════════ */

/**
 * Fetch KPI counts for the Admin / HR dashboard stat cards.
 * Each query is individually guarded so a missing table never crashes the page.
 *
 * @return array{total_employees:int, active_employees:int, new_applicants:int,
 *               interviews_today:int, on_leave:int, payroll_this_month:float}
 */
function getDashboardStats(): array {
    $stats = [
        'total_employees'    => 0,
        'active_employees'   => 0,
        'new_applicants'     => 0,
        'interviews_today'   => 0,
        'on_leave'           => 0,
        'payroll_this_month' => 0.0,
    ];
    try { $db = getDB(); } catch (Throwable $e) { return $stats; }

    $queries = [
        'total_employees'    => "SELECT COUNT(*) FROM employees",
        'active_employees'   => "SELECT COUNT(*) FROM employees WHERE status='active'",
        'new_applicants'     => "SELECT COUNT(*) FROM applicants WHERE YEAR(applied_at)=YEAR(NOW()) AND MONTH(applied_at)=MONTH(NOW())",
        'interviews_today'   => "SELECT COUNT(*) FROM interviews WHERE DATE(interview_date)=CURDATE() AND status='scheduled'",
        'on_leave'           => "SELECT COUNT(*) FROM leave_requests WHERE status='approved' AND start_date<=CURDATE() AND end_date>=CURDATE()",
        'payroll_this_month' => "SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE status='released' AND payroll_year=YEAR(NOW()) AND payroll_month=MONTH(NOW())",
    ];
    foreach ($queries as $key => $sql) {
        try { $stats[$key] = $key === 'payroll_this_month' ? (float)$db->query($sql)->fetchColumn() : (int)$db->query($sql)->fetchColumn(); }
        catch (Throwable $e) {}
    }
    return $stats;
}


/* ══════════════════════════════════════════════════════════════════════
   NOTIFICATIONS
   ══════════════════════════════════════════════════════════════════════ */

function getUnreadNotifications(int $userId): int {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function getUnreadApplicantNotifications(int $applicantAccountId): int {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE applicant_account_id=? AND is_read=0");
        $stmt->execute([$applicantAccountId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}


// ── Time & Attendance Helpers ─────────────────────────────────────────────

if (!defined('WORK_START')) define('WORK_START', '08:00:00');
if (!defined('WORK_END'))   define('WORK_END',   '17:00:00');

if (!function_exists('formatTime')) {
    function formatTime(?string $t): string {
        if (!$t) return '—';
        $ts = strtotime($t);
        return $ts !== false ? date('g:i A', $ts) : $t;
    }
}

if (!function_exists('calcLateMinutes')) {
    function calcLateMinutes(?string $timeIn): int {
        if (!$timeIn) return 0;
        $in    = strtotime(date('Y-m-d') . ' ' . $timeIn);
        $start = strtotime(date('Y-m-d') . ' ' . WORK_START);
        $diff  = ($in - $start) / 60;
        return $diff > 0 ? (int)ceil($diff) : 0;
    }
}

if (!function_exists('calcUndertimeMinutes')) {
    function calcUndertimeMinutes(?string $timeOut): int {
        if (!$timeOut) return 0;
        $out  = strtotime(date('Y-m-d') . ' ' . $timeOut);
        $end  = strtotime(date('Y-m-d') . ' ' . WORK_END);
        $diff = ($end - $out) / 60;
        return $diff > 0 ? (int)ceil($diff) : 0;
    }
}

if (!function_exists('deductionFromMinutes')) {
    function deductionFromMinutes(float $basicSalary, int $minutes): float {
        if ($minutes <= 0) return 0.0;
        $perMinute = $basicSalary / (22 * 8 * 60);
        return round($perMinute * $minutes, 2);
    }
}

if (!function_exists('deductionFromAbsentDays')) {
    function deductionFromAbsentDays(float $basicSalary, float $days): float {
        if ($days <= 0) return 0.0;
        return round(($basicSalary / 22) * $days, 2);
    }
}

// Bootstrap alert HTML helpers used in employee portal
if (!function_exists('success')) {
    function success(string $msg): string {
        return '<div class="alert alert-success border-0 rounded-3"><i class="bi bi-check-circle me-2"></i>' . $msg . '</div>';
    }
}

if (!function_exists('danger')) {
    function danger(string $msg): string {
        return '<div class="alert alert-danger border-0 rounded-3"><i class="bi bi-exclamation-triangle me-2"></i>' . $msg . '</div>';
    }
}

if (!function_exists('warning')) {
    function warning(string $msg): string {
        return '<div class="alert alert-warning border-0 rounded-3"><i class="bi bi-exclamation-circle me-2"></i>' . $msg . '</div>';
    }
}
