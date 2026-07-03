<?php
// ============================================================
// G&G Support Portal — admin/dashboard.php
// Admin home: stats, pending solutions, own ticket summary
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

$page_title = 'Admin Dashboard';
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

// ── Knowledge base stats ─────────────────────────────────────
$total_solutions   = (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "approved"')->fetchColumn();
$pending_solutions = (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "pending"')->fetchColumn();
$total_categories  = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

// ── Ticket stats — scoped to this admin (system_admin sees all) ──
$ticketScope = ($role === 'system_admin') ? '' : 'AND current_admin = ' . (int)$userId;

$open_tickets       = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'       $ticketScope")->fetchColumn();
$inprogress_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'in_progress' $ticketScope")->fetchColumn();
$resolved_tickets   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'   $ticketScope")->fetchColumn();
$unattended_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'unattended' $ticketScope")->fetchColumn();

// ── My open / in-progress tickets (5 most urgent by deadline) ──
$myTicketsStmt = $pdo->prepare("
    SELECT t.id, t.title, t.status, t.attend_deadline, t.resolve_deadline,
           u.full_name AS raiser_name, tl.level_name, c.name AS category_name
    FROM tickets t
    LEFT JOIN users u          ON u.id  = t.raised_by
    LEFT JOIN ticket_levels tl ON tl.id = t.current_level
    LEFT JOIN categories c     ON c.id  = t.category_id
    WHERE t.status IN ('open','in_progress') $ticketScope
    ORDER BY COALESCE(t.attend_deadline, t.resolve_deadline) ASC
    LIMIT 5
");
$myTicketsStmt->execute();
$myTickets = $myTicketsStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Recent pending solutions ─────────────────────────────────
$pending = $pdo->query(
    'SELECT s.id, s.question, u.full_name AS submitted_by, s.created_at
     FROM solutions s
     LEFT JOIN users u ON s.submitted_by = u.id
     WHERE s.status = "pending"
     ORDER BY s.created_at DESC
     LIMIT 5'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.stats-grid-wide { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card.stat-danger  { border-top:3px solid #c62828; }
.stat-card.stat-success { border-top:3px solid #2e7d32; }
.stat-card.stat-open    { border-top:3px solid #1565c0; }
.stat-card.stat-prog    { border-top:3px solid #f57c00; }
.td-breach { color:#c62828; font-weight:600; }
.section-label { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                 color:var(--text-muted,#6c757d); margin:1.5rem 0 .6rem; }
</style>

<!-- ── Ticket stats (scoped to this admin) ───────────────── -->
<p class="section-label">🎫 <?= $role === 'system_admin' ? 'All Tickets' : 'My Tickets' ?></p>
<div class="stats-grid-wide">
    <div class="stat-card stat-open">
        <div class="stat-icon">📬</div>
        <div class="stat-number"><?= $open_tickets ?></div>
        <div class="stat-label">Open</div>
    </div>
    <div class="stat-card stat-prog">
        <div class="stat-icon">🔧</div>
        <div class="stat-number"><?= $inprogress_tickets ?></div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-icon">✅</div>
        <div class="stat-number"><?= $resolved_tickets ?></div>
        <div class="stat-label">Resolved</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-icon">⚠️</div>
        <div class="stat-number"><?= $unattended_tickets ?></div>
        <div class="stat-label">Unattended</div>
    </div>
</div>

<!-- ── Knowledge base stats ──────────────────────────────── -->
<p class="section-label">💡 Knowledge Base</p>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">💡</div>
        <div class="stat-number"><?= $total_solutions ?></div>
        <div class="stat-label">Approved Solutions</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon">⏳</div>
        <div class="stat-number"><?= $pending_solutions ?></div>
        <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-icon">📁</div>
        <div class="stat-number"><?= $total_categories ?></div>
        <div class="stat-label">Categories</div>
    </div>
</div>

<!-- Quick actions -->
<div class="quick-actions" style="margin-top:1.25rem;">
    <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-primary">🎫 My Tickets</a>
    <a href="<?= BASE_URL ?>/admin/solutions.php" class="btn btn-secondary">+ Add Solution</a>
    <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-secondary">+ Add Category</a>
    <a href="<?= BASE_URL ?>/admin/announcements.php" class="btn btn-secondary">+ Announcement</a>
</div>

<div class="two-col-grid" style="margin-top:1.5rem;">

    <!-- Urgent open tickets assigned to this admin -->
    <div class="card">
        <div class="card-header">
            <h2>🚨 <?= $role === 'system_admin' ? 'Urgent Tickets' : 'My Urgent Tickets' ?></h2>
            <a href="<?= BASE_URL ?>/admin/tickets.php?status=open" class="btn btn-sm">View all</a>
        </div>
        <?php if ($myTickets): ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Title</th><th>Status</th><th>Level</th><th>Deadline</th></tr></thead>
            <tbody>
            <?php foreach ($myTickets as $tk):
                $deadline = $tk['status'] === 'open' ? $tk['attend_deadline'] : $tk['resolve_deadline'];
                $breached = $deadline && (new DateTime() > new DateTime($deadline));
            ?>
            <tr>
                <td><span class="text-muted">#<?= $tk['id'] ?></span></td>
                <td><a href="<?= BASE_URL ?>/ticket_detail.php?id=<?= $tk['id'] ?>"><strong><?= htmlspecialchars(substr($tk['title'],0,45)) ?><?= strlen($tk['title'])>45?'…':'' ?></strong></a></td>
                <td><?php
                    $badges = ['open'=>'badge-open','in_progress'=>'badge-progress'];
                    $bc = $badges[$tk['status']] ?? 'badge-open';
                    $bl = $tk['status'] === 'open' ? 'Open' : 'In Progress';
                    echo "<span class=\"badge $bc\">$bl</span>";
                ?></td>
                <td><?= htmlspecialchars($tk['level_name'] ?? '—') ?></td>
                <td class="<?= $breached ? 'td-breach' : '' ?>">
                    <?= $deadline ? date('d M, H:i', strtotime($deadline)) : '—' ?>
                    <?= $breached ? ' ⚠️' : '' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">✅ No urgent tickets.</p>
        <?php endif; ?>
    </div>

    <!-- Pending solutions -->
    <div class="card">
        <div class="card-header">
            <h2>⏳ Pending Solutions</h2>
            <a href="<?= BASE_URL ?>/admin/solutions.php?filter=pending" class="btn btn-sm">View all</a>
        </div>
        <?php if ($pending): ?>
        <table class="data-table">
            <thead><tr><th>Question</th><th>Submitted By</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $s): ?>
            <tr>
                <td><?= htmlspecialchars(substr($s['question'], 0, 60)) ?>…</td>
                <td><?= htmlspecialchars($s['submitted_by'] ?? 'Unknown') ?></td>
                <td><a href="<?= BASE_URL ?>/admin/solutions.php?edit=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">✅ No pending solutions.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
