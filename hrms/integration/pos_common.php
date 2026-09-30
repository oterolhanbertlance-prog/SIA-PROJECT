<?php
/**
 * Shared HRMS ↔ POS rules.
 *
 * The two applications intentionally keep separate databases, but a hired
 * employee has one identity: the HRMS user is copied to POS with the same
 * username, email and password hash.  This file is used by both the HRMS
 * hiring flow and the POS fallback login.
 */

if (!function_exists('posRoleFromJobTitle')) {
    function posRoleFromJobTitle(?string $title): ?string
    {
        $title = strtolower(trim((string)$title));
        // Check the most privileged POS role first so "POS Super Admin"
        // cannot be mistaken for a generic admin or cashier posting.
        if (preg_match('/\bsuper[\s_-]*admin(istrator)?\b|\bpos[\s_-]*admin\b/', $title)) return 'super_admin';
        if (preg_match('/\bcashier\b/', $title)) return 'cashier';
        if (preg_match('/\binventory\s+staff\b|\binventory\b|\bstock(room)?\b/', $title)) return 'inventory_staff';
        return null;
    }
}

function ensurePosIntegrationSchema(PDO $pos): void
{
    // Safe for an existing localhost installation and included in the fresh
    // installer as well.  Existing customer accounts remain customer accounts.
    $pos->exec("ALTER TABLE users MODIFY role ENUM('super_admin','cashier','inventory_staff','customer') NOT NULL DEFAULT 'customer'");
    $pos->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS firstname VARCHAR(100) NULL AFTER role");
    $pos->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS lastname VARCHAR(100) NULL AFTER firstname");
    $pos->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS hrms_user_id INT NULL AFTER lastname");
    $pos->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS hrms_employee_id INT NULL AFTER hrms_user_id");
    try {
        $pos->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_hrms_user_id (hrms_user_id)");
    } catch (Throwable $e) {
        // The key may already exist on an upgraded localhost database.
    }
}

/**
 * Create or refresh the POS shadow account for an HRMS identity.
 *
 * @return int POS users.id
 */
function syncHrmsUserToPos(PDO $pos, array $hrmsUser, string $posRole, ?int $employeeId = null): int
{
    if (!in_array($posRole, ['super_admin', 'cashier', 'inventory_staff'], true)) {
        throw new InvalidArgumentException('Invalid POS role.');
    }
    ensurePosIntegrationSchema($pos);

    $username = trim((string)$hrmsUser['username']);
    $email    = strtolower(trim((string)($hrmsUser['email'] ?? '')));
    $hash     = (string)$hrmsUser['password'];
    $hrmsId   = (int)$hrmsUser['id'];
    $first    = (string)($hrmsUser['firstname'] ?? '');
    $last     = (string)($hrmsUser['lastname'] ?? '');

    $find = $pos->prepare("SELECT id FROM users WHERE hrms_user_id = ? OR username = ? OR (email = ? AND ? <> '') LIMIT 1");
    $find->execute([$hrmsId, $username, $email, $email]);
    $existing = $find->fetch();

    if ($existing) {
        $pos->prepare(
            "UPDATE users
                SET username=?, email=?, password=?, role=?, firstname=?, lastname=?,
                    hrms_user_id=?, hrms_employee_id=?, email_verified=1, is_active=1
              WHERE id=?"
        )->execute([$username, $email ?: null, $hash, $posRole, $first, $last, $hrmsId, $employeeId, $existing['id']]);
        return (int)$existing['id'];
    }

    $pos->prepare(
        "INSERT INTO users
            (username,email,email_verified,is_active,password,role,firstname,lastname,hrms_user_id,hrms_employee_id)
         VALUES (?,?,1,1,?,?,?,?,?,?)"
    )->execute([$username, $email ?: null, $hash, $posRole, $first, $last, $hrmsId, $employeeId]);
    return (int)$pos->lastInsertId();
}

function posRoleForHrmsUser(array $user): ?string
{
    if (($user['role'] ?? '') === 'admin') return 'super_admin';
    if (in_array(($user['pos_role'] ?? ''), ['super_admin', 'cashier', 'inventory_staff'], true)) {
        return $user['pos_role'];
    }
    if (($user['role'] ?? '') === 'inventory_staff') return 'inventory_staff';
    return posRoleFromJobTitle($user['pos_role'] ?? null);
}