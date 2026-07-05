<?php
// ============================================================
// G&G Support Portal — admin/system_dashboard.php
// System Admin home: full stats + users + pending + flags
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['system_admin']);

$page_title = 'System Dashboard';

$total_solutions   = (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "approved"')->fetchColumn();
$pending_solutions = (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "pending"')->fetchColumn();

$total_users       = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
$total_categories  = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$total_announcements = (int)$pdo->query('SELECT COUNT(*) FROM announcements WHERE is_active = 1')->fetchColumn();

// Recent pending solutions
$pending = $pdo->query(
    'SELECT s.id, s.question, u.full_name AS submitted_by
     FROM solutions s
     LEFT JOIN users u ON s.submitted_by = u.id
     WHERE s.status = "pending"
     ORDER BY s.id DESC LIMIT 5'
)->fetchAll();

// Recent open flags
$flags = $pdo->query(
    'SELECT f.id, f.question, u.full_name AS raised_by
     FROM flags f
     LEFT JOIN users u ON f.raised_by = u.id
     WHERE f.status = "open"
     ORDER BY f.id DESC LIMIT 5'
)->fetchAll();

// Recent users
$users = $pdo->query(
    'SELECT id, full_name, username, role, created_at
     FROM users
     ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats grid -->
<div class="stats-grid stats-grid-6">
    <div class="stat-card">
        <div class="stat-icon">💡</div>
        <div class="stat-number"><?= $total_solutions ?></div>
        <div class="stat-label">Solutions</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon">⏳</div>
        <div class="stat-number"><?= $pending_solutions ?></div>
        <div class="stat-label">Pending solutions</div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">👥</div>
        <div class="stat-number"><?= $total_users ?></div>
        <div class="stat-label">Active Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📁</div>
        <div class="stat-number"><?= $total_categories ?></div>
        <div class="stat-label">Categories</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-icon">📢</div>
        <div class="stat-number"><?= $total_announcements ?></div>
        <div class="stat-label">Announcements</div>
    </div>
</div>

<!-- Quick actions -->
<div class="quick-actions">
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-primary">+ Add User</a>
    <a href="<?= BASE_URL ?>/admin/solutions.php" class="btn btn-primary">+ Add Solution</a>
    <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-secondary">+ Category</a>
    <a href="<?= BASE_URL ?>/admin/announcements.php" class="btn btn-secondary">+ Announcement</a>
    
</div>

<div class="two-col-grid">

    <!-- Pending solutions -->
    <div class="card">
        <div class="card-header">
            <h2>⏳ Pending Solutions</h2>
            <a href="<?= BASE_URL ?>/admin/solutions.php?filter=pending" class="btn btn-sm">All</a>
        </div>
        <?php if ($pending): ?>
        <table class="data-table">
            <thead><tr><th>Question</th><th>By</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $s): ?>
            <tr>
                <td><?= htmlspecialchars(substr($s['question'], 0, 50)) ?>…</td>
                <td><?= htmlspecialchars($s['submitted_by'] ?? '—') ?></td>
                <td><a href="<?= BASE_URL ?>/admin/solutions.php?edit=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">✅ No pending solutions.</p>
        <?php endif; ?>
    </div>

    <!-- Open flags -->
    

    <!-- Recent users -->
    <div class="card">
        <div class="card-header">
            <h2>👥 Recent Users</h2>
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm">All</a>
        </div>
        <?php if ($users): ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Username</th><th>Role</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
