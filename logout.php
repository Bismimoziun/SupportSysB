<?php
// ============================================================
// IT Asset and Support Management System — logout.php (root level)
// Destroys session and redirects to login
// ============================================================

require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page with logout flag
header('Location: ' . BASE_URL . '/index.php?logout=1');
exit;
