<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 on HTTPS
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . 'index.php?msg=session_expired');
        exit;
    }
}

function hasRole(...$roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

function requireRole(...$roles) {
    requireLogin();
    if (!hasRole(...$roles)) {
        header('Location: ' . SITE_URL . 'dashboard.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    static $user = null;
    if ($user !== null) return $user;

    $base = [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
        'emp_id'   => $_SESSION['employee_id'] ?? null,
        'name'     => $_SESSION['full_name'] ?? '',
    ];

    if (!empty($base['id'])) {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT u.firstname, u.lastname, u.email, u.pos_role, u.branch_id AS user_branch_id,
                        ub.name AS user_branch_name,
                        e.id AS emp_db_id,
                        e.employee_id,
                        e.branch_id AS emp_branch_id,
                        eb.name AS emp_branch_name,
                        COALESCE(e.basic_salary, 13000) AS basic_salary,
                        COALESCE(e.hourly_rate, 74.07)  AS hourly_rate,
                        p.title AS position,
                        d.name  AS department
                 FROM users u
                 LEFT JOIN employees e  ON u.employee_id = e.id
                 LEFT JOIN positions p  ON e.position_id  = p.id
                 LEFT JOIN departments d ON e.department_id = d.id
                 LEFT JOIN branches eb  ON e.branch_id = eb.id
                 LEFT JOIN branches ub  ON u.branch_id = ub.id
                 WHERE u.id = ?"
            );
            $stmt->execute([$base['id']]);
            $extra = $stmt->fetch() ?: [];
            $user  = array_merge($base, $extra);
        } catch (Exception $e) {
            $user = $base;
        }
    } else {
        $user = $base;
    }

    return $user;
}

/**
 * A human display label for a user's role that also reflects their POS
 * position and/or branch, e.g. "Employee — Cashier", "Employee — Inventory
 * Staff", "HR — Cavite Branch". Falls back to the plain ROLES[] label.
 */
function roleDisplayLabel(array $user): string {
    $role = $user['role'] ?? '';
    $base = ROLES[$role] ?? ucfirst($role ?: 'User');

    if ($role === 'employee' && !empty($user['pos_role'])) {
        $posLabels = ['cashier' => 'Cashier', 'inventory_staff' => 'Inventory Staff', 'super_admin' => 'POS Admin'];
        $base .= ' — ' . ($posLabels[$user['pos_role']] ?? ucfirst(str_replace('_',' ',$user['pos_role'])));
    }

    $branchName = $user['emp_branch_name'] ?? $user['user_branch_name'] ?? null;
    if ($branchName) {
        $base .= ' (' . $branchName . ')';
    } elseif (in_array($role, ['hr','admin'], true)) {
        $base .= ' (Head Office)';
    }

    return $base;
}
