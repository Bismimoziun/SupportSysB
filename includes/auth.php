<?php
// ============================================================
// G&G Support Portal — auth.php
// Handles POST from the login form
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sanitise.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: ' . BASE_URL . '/index.php?error=empty');
    exit;
}

// Fetch user
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: ' . BASE_URL . '/index.php?error=invalid');
    exit;
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

$_SESSION['user_id']       = $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['full_name']     = $user['full_name'];
$_SESSION['role']          = $user['role'];
$_SESSION['last_activity'] = time();

// Role-based redirect
switch ($user['role']) {
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
