<?php
// ============================================================
// G&G Support Portal — header.php
// ============================================================

$_user  = current_user();
$_role  = $_user['role'];
$_name  = htmlspecialchars($_user['full_name']);
$_page  = basename($_SERVER['PHP_SELF']);

// Greeting based on time
$hour = (int)date('H');
if ($hour < 12)      $_greeting = 'Good morning';
elseif ($hour < 16)  $_greeting = 'Good afternoon';
else                 $_greeting = 'Good evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'G&G Support Portal') ?> — G&G Support</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') . '-' . filesize(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tickets.css?v=<?= filemtime(__DIR__ . '/../assets/css/tickets.css') . '-' . filesize(__DIR__ . '/../assets/css/tickets.css') ?>">
    <?php if (!empty($_SESSION['user_id'])): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/ai_chat.css?v=<?= filemtime(__DIR__ . '/../assets/css/ai_chat.css') . '-' . filesize(__DIR__ . '/../assets/css/ai_chat.css') ?>">
    <?php endif; ?>
</head>
<body>

<div class="layout">

    <!-- ── Sidebar overlay (mobile) ──────────────────────── -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ── Sidebar ───────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-brand">
            <span class="brand-icon">⚡</span>
            <span class="brand-text">G&G Support</span>
            <button class="sidebar-collapse" id="sidebarCollapse" type="button"
                    aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar">
                <span aria-hidden="true">⬅️</span>
            </button>
        </div>

    <div class="sidebar-greeting">
        <span class="greeting-wave" aria-hidden="true">👋</span>
        <div>
            <div class="greeting-text"><?= htmlspecialchars($_greeting) ?>,</div>
            <div class="greeting-name"><?= $_name ?>!</div>
        </div>
    </div>

    <nav class="sidebar-nav">

            <?php if ($_role === 'system_admin'): ?>
                <a href="<?= BASE_URL ?>/admin/system_dashboard.php" class="nav-item <?= $_page === 'system_dashboard.php' ? 'active' : '' ?>" title="Dashboard" aria-label="Dashboard">
                    <span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="nav-item <?= $_page === 'users.php' ? 'active' : '' ?>" title="Users" aria-label="Users">
                    <span class="nav-icon">👥</span><span class="nav-label">Users</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="nav-item <?= $_page === 'categories.php' ? 'active' : '' ?>" title="Categories" aria-label="Categories">
                    <span class="nav-icon">📁</span><span class="nav-label">Categories</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/solutions.php" class="nav-item <?= $_page === 'solutions.php' ? 'active' : '' ?>" title="Solutions" aria-label="Solutions">
                    <span class="nav-icon">💡</span><span class="nav-label">Solutions</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/announcements.php" class="nav-item <?= $_page === 'announcements.php' ? 'active' : '' ?>" title="Announcements" aria-label="Announcements">
                    <span class="nav-icon">📢</span><span class="nav-label">Announcements</span>
                </a>
                
                <a href="<?= BASE_URL ?>/admin/tickets.php" class="nav-item <?= $_page === 'tickets.php' ? 'active' : '' ?>" title="Tickets" aria-label="Tickets">
                    <span class="nav-icon">🎫</span><span class="nav-label">Tickets</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/ticket_config.php" class="nav-item <?= $_page === 'ticket_config.php' ? 'active' : '' ?>" title="Ticket Configuration" aria-label="Ticket Configuration">
                    <span class="nav-icon">⚙️</span><span class="nav-label">Ticket Config</span>
                </a>

            <?php elseif ($_role === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= $_page === 'dashboard.php' ? 'active' : '' ?>" title="Dashboard" aria-label="Dashboard">
                    <span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="nav-item <?= $_page === 'categories.php' ? 'active' : '' ?>" title="Categories" aria-label="Categories">
                    <span class="nav-icon">📁</span><span class="nav-label">Categories</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/solutions.php" class="nav-item <?= $_page === 'solutions.php' ? 'active' : '' ?>" title="Solutions" aria-label="Solutions">
                    <span class="nav-icon">💡</span><span class="nav-label">Solutions</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/announcements.php" class="nav-item <?= $_page === 'announcements.php' ? 'active' : '' ?>" title="Announcements" aria-label="Announcements">
                    <span class="nav-icon">📢</span><span class="nav-label">Announcements</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/tickets.php" class="nav-item <?= $_page === 'tickets.php' ? 'active' : '' ?>" title="Tickets" aria-label="Tickets">
                    <span class="nav-icon">🎫</span><span class="nav-label">Tickets</span>
                </a>

            <?php else: ?>
                <a href="<?= BASE_URL ?>/user_home.php" class="nav-item <?= $_page === 'user_home.php' ? 'active' : '' ?>" title="Search" aria-label="Search">
                    <span class="nav-icon">🔍</span><span class="nav-label">Search</span>
                </a>
                <a href="<?= BASE_URL ?>/user_tickets.php" class="nav-item <?= $_page === 'user_tickets.php' ? 'active' : '' ?>" title="My Tickets" aria-label="My Tickets">
                    <span class="nav-icon">🎫</span><span class="nav-label">My Tickets</span>
                </a>
            <?php endif; ?>

        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_name, 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= $_name ?></div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $_role)) ?></div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Logout"><span aria-hidden="true">🚪</span><span class="btn-logout-label">Logout</span></a>
        </div>
    </aside>

    <!-- ── Main content wrapper ──────────────────────────── -->
    <main class="main-content">
        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">☰</button>
            <h1 class="page-heading"><?= htmlspecialchars($page_title ?? '') ?></h1>
            <div class="topbar-right">
                <span class="topbar-user">👤 <?= $_name ?></span>
            </div>
        </div>
        <div class="content-body">

<?php if (in_array($_role, ["admin", "system_admin"])): ?>
<script>
// Silently trigger SLA check in background — server throttles to once per 5 min.
fetch("<?= BASE_URL ?>/sla_trigger.php", { method: "GET", credentials: "same-origin" }).catch(function(){});
</script>
<?php endif; ?>
