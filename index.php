<?php
// ============================================================
// IT Asset and Support Management System — index.php (Login page)
// ============================================================

require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

// Already logged in? Redirect to correct dashboard
if (!empty($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'system_admin': header('Location: ' . BASE_URL . '/admin/system_dashboard.php'); exit;
        case 'admin':        header('Location: ' . BASE_URL . '/admin/dashboard.php');        exit;
        default:             header('Location: ' . BASE_URL . '/user_home.php');              exit;
    }
}

$error = '';

// ── Handle login POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid username or password. Please try again.';
        } else {
            // Valid — set up session
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            // Redirect by role
            switch ($user['role']) {
                case 'system_admin': header('Location: ' . BASE_URL . '/admin/system_dashboard.php'); exit;
                case 'admin':        header('Location: ' . BASE_URL . '/admin/dashboard.php');        exit;
                default:             header('Location: ' . BASE_URL . '/user_home.php');              exit;
            }
        }
    }
}

$timeout = isset($_GET['timeout']);
$logout  = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IT Asset and Support Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <span class="brand-icon-lg">⚡</span>
            <h1>IT Asset and Support Management System</h1>
            <p>Secure IT Asset & Support Portal</p>
        </div>

        <?php if ($timeout): ?>
            <div class="alert alert-warning">Your session expired due to inactivity. Please log in again.</div>
        <?php elseif ($logout): ?>
            <div class="alert alert-success">You have been logged out successfully.</div>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Form posts to itself (index.php) -->
        <form method="POST" action="<?= BASE_URL ?>/index.php" class="login-form" autocomplete="on">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Enter your username"
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>
    </div>
    <div class="login-footer">IT Asset and Support Management System &copy; <?= date('Y') ?></div>
</div>

</body>
</html>
