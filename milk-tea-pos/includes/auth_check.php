<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/app_url.php';

function posSafeRedirect(string $redirectTo): string {
    if (preg_match('#^https?://#i', $redirectTo) || str_starts_with($redirectTo, '/')) return $redirectTo;
    if (str_contains($redirectTo, 'auth/login.php')) return appUrl('auth/login.php');
    return appUrl(ltrim(preg_replace('#^(\.\./)+#', '', $redirectTo), '/'));
}

function requireLogin(string $redirectTo = '../auth/login.php'): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . posSafeRedirect($redirectTo));
        exit;
    }
}

function requireRole(string $role, string $redirectTo = '../auth/login.php'): void {
    requireLogin($redirectTo);
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . posSafeRedirect($redirectTo));
        exit;
    }
}

function requireAnyRole(array $roles, string $redirectTo = '../auth/login.php'): void {
    requireLogin($redirectTo);
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . posSafeRedirect($redirectTo));
        exit;
    }
}

/** Alias: super_admin or inventory_staff can access admin-level pages */
function requireAdmin(): void {
    requireAnyRole(['super_admin', 'inventory_staff'], '../auth/login.php');
}

/** Only super_admin — for pages inventory_staff must NOT access */
function requireSuperAdmin(): void {
    requireRole('super_admin', '../auth/login.php');
}

function currentPosUser(): array {
    return [
        'id'       => $_SESSION['user_id']  ?? null,
        'username' => $_SESSION['username'] ?? 'Guest',
        'role'     => $_SESSION['role']     ?? '',
    ];
}
