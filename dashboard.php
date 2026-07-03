<?php
// ============================================================
//  IT Asset and Support Management System — dashboard.php
// Root-level router: redirects to correct dashboard by role
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';
guard_require_login();

$role = $_SESSION['role'] ?? '';

switch ($role) {
    case 'system_admin':
        header('Location: ' . BASE_URL . '/admin/system_dashboard.php');
        break;
    case 'admin':
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        break;
    default:
        header('Location: ' . BASE_URL . '/user_home.php');
        break;
}
exit;
