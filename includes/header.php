<?php
// ============================================================
// G&G Support Portal — header.php
// ============================================================

$_user  = current_user();
$_role  = $_user['role'];
$_name  = htmlspecialchars($_user['full_name']);
$_first = htmlspecialchars(explode(' ', $_user['full_name'])[0]); // first name only
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (!empty($_SESSION['user_id'])): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/ai_chat.css">
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
        </div>

       <!-- Greeting inside sidebar -->
<div style="display:flex;align-items:center;gap:.75rem;padding:1.25rem 1.25rem 1rem;
            border-bottom:1px solid #c4cad3d5;flex-shrink:0;">
    <span style="font-size:1.4rem;flex-shrink:0;">👋</span>
    <div>
        <div style="font-size:1rem;font-weight:700;color:#f0f0f0;letter-spacing:-.01em;white-space:nowrap;">
            <?php echo $_greeting; ?>,
        </div>
        <div style="font-size:1rem;font-weight:700;color:#ffffff;letter-spacing:-.01em;white-space:nowrap;">
            <?php echo $_first; ?>!
        </div>
    </div>
</div>

        <nav class="sidebar-nav">

            <?php if ($_role === 'system_admin'): ?>
                <a href="<?= BASE_URL ?>/admin/system_dashboard.php" class="nav-item <?= $_page === 'system_dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="nav-item <?= $_page === 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> Users
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="nav-item <?= $_page === 'categories.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📁</span> Categories
                </a>
                <a href="<?= BASE_URL ?>/admin/solutions.php" class="nav-item <?= $_page === 'solutions.php' ? 'active' : '' ?>">
                    <span class="nav-icon">💡</span> Solutions
                </a>
                <a href="<?= BASE_URL ?>/admin/announcements.php" class="nav-item <?= $_page === 'announcements.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📢</span> Announcements
                </a>
                
                <a href="<?= BASE_URL ?>/admin/tickets.php" class="nav-item <?= $_page === 'tickets.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🎫</span> Tickets
                </a>
                <a href="<?= BASE_URL ?>/admin/ticket_config.php" class="nav-item <?= $_page === 'ticket_config.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙</span> Ticket Config
                </a>

            <?php elseif ($_role === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= $_page === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="nav-item <?= $_page === 'categories.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📁</span> Categories
                </a>
                <a href="<?= BASE_URL ?>/admin/solutions.php" class="nav-item <?= $_page === 'solutions.php' ? 'active' : '' ?>">
                    <span class="nav-icon">💡</span> Solutions
                </a>
                <a href="<?= BASE_URL ?>/admin/announcements.php" class="nav-item <?= $_page === 'announcements.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📢</span> Announcements
                </a>
                
                </a>
                <a href="<?= BASE_URL ?>/admin/tickets.php" class="nav-item <?= $_page === 'tickets.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🎫</span> Tickets
                </a>

            <?php else: ?>
                <a href="<?= BASE_URL ?>/user_home.php" class="nav-item <?= $_page === 'user_home.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🔍</span> Search
                </a>
                <a href="<?= BASE_URL ?>/user_tickets.php" class="nav-item <?= $_page === 'user_tickets.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🎫</span> My Tickets
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
            <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">🚪 Logout</a>
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
