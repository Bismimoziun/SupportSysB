<?php
// ============================================================
//  G&G Support Portal— auth_guard.php
// Session check + role guard + 30-min inactivity timeout
// Include at the TOP of every protected page.
// Usage: require_once __DIR__ . '/../includes/auth_guard.php';
//        guard_require_login();
//        guard_require_role(['admin','system_admin']);
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/sanitise.php';

// Start session securely (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true on HTTPS live server
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── 30-minute inactivity timeout ────────────────────────────
function guard_check_timeout(): void {
    $timeout = 1800; // 30 minutes in seconds
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ── Redirect to login if not authenticated ───────────────────
function guard_require_login(): void {
    guard_check_timeout();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// ── Redirect with 403 if role not allowed ────────────────────
function guard_require_role(array $allowed_roles): void {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

// ── Helper: get current user info from session ───────────────
function current_user(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'full_name' => $_SESSION['full_name'] ?? '',
        'username'  => $_SESSION['username']  ?? '',
        'role'      => $_SESSION['role']      ?? '',
    ];
}

function is_admin(): bool {
    return in_array($_SESSION['role'] ?? '', ['admin', 'system_admin'], true);
}

function is_sysadmin(): bool {
    return ($_SESSION['role'] ?? '') === 'system_admin';
}
