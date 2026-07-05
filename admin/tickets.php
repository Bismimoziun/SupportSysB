<?php
// admin/tickets.php — Admin: view and manage tickets
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';

guard_require_login();
guard_require_role(['admin','system_admin']);

// SLA check runs via sla_cron.php only — not on page load

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$page_title = 'Ticket Management';

$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, ['all','open','in_progress','resolved','unresolved','unattended'])) {
    $filterStatus = 'all';
}

$whereRole   = ($role === 'system_admin') ? '' : 'AND t.current_admin = ' . (int)$userId;
$whereStatus = ($filterStatus !== 'all')  ? "AND t.status = " . $pdo->quote($filterStatus) : '';

$tickets = $pdo->query("
    SELECT t.*, tl.level_name,
           u.full_name AS raiser_name,
           a.full_name AS assigned_admin_name,
           c.name      AS category_name
    FROM tickets t
    LEFT JOIN ticket_levels tl ON tl.id = t.current_level
    LEFT JOIN users u          ON u.id  = t.raised_by
    LEFT JOIN users a          ON a.id  = t.current_admin
    LEFT JOIN categories c     ON c.id  = t.category_id
    WHERE 1=1 {$whereRole} {$whereStatus}
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Status counts
$statsWhere = ($role !== 'system_admin') ? "WHERE current_admin = {$userId}" : '';
$stats = [];
foreach ($pdo->query("SELECT status, COUNT(*) AS cnt FROM tickets {$statsWhere} GROUP BY status")->fetchAll() as $row) {
    $stats[$row['status']] = $row['cnt'];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filter-chips        { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1.25rem; }
.chip                { display:inline-flex; align-items:center; gap:5px; padding:5px 14px;
                       border-radius:20px; background:#f1f3f5; color:#495057; font-size:.83rem;
                       text-decoration:none; border:2px solid transparent; transition:all .15s; }
.chip:hover          { background:#e9ecef; }
.chip.active         { background:#1565c0; color:#fff; border-color:#1565c0; }
.chip-count          { background:rgba(0,0,0,.12); border-radius:10px; padding:0 6px; font-size:.73rem; }
.chip.active .chip-count { background:rgba(255,255,255,.25); }
.row-breach          { background:#fff5f5 !important; }
.td-breach           { color:#c62828; font-weight:600; }
.page-header-row     { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
</style>

<div class="page-header-row">
  <h2 class="section-title">🎫 Ticket Management</h2>
  <?php if ($role === 'system_admin'): ?>
  <a href="<?= BASE_URL ?>/admin/ticket_config.php" class="btn btn-secondary btn-sm">⚙ Configure Levels &amp; SLA</a>
  <?php endif; ?>
</div>

<!-- Status filter chips -->
<div class="filter-chips">
  <?php
  $chips = ['all'=>'All','open'=>'Open','in_progress'=>'In Progress',
            'resolved'=>'Resolved','unresolved'=>'Unresolved','unattended'=>'Unattended'];
  foreach ($chips as $val => $label):
    $cls = ($filterStatus === $val) ? 'chip active' : 'chip';
  ?>
  <a href="?status=<?= $val ?>" class="<?= $cls ?>">
    <?= $label ?>
    <?php if ($val !== 'all' && isset($stats[$val])): ?>
      <span class="chip-count"><?= $stats[$val] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($tickets)): ?>
    <p class="empty-state">No tickets match this filter.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Raised By</th>
          <th>Category</th>
          <th>Status</th>
          <th>Level</th>
          <?php if ($role === 'system_admin'): ?><th>Assigned To</th><?php endif; ?>
          <th>Attend By</th>
          <th>Resolve By</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tickets as $tk):
        $attendBreached  = $tk['attend_deadline']  && !$tk['attended_at'] && (new DateTime() > new DateTime($tk['attend_deadline']));
        $resolveBreached = $tk['resolve_deadline'] && !$tk['resolved_at'] && (new DateTime() > new DateTime($tk['resolve_deadline']));
      ?>
        <tr class="<?= ($attendBreached || $resolveBreached) ? 'row-breach' : '' ?>">
          <td><span class="text-muted">#<?= $tk['id'] ?></span></td>
          <td><strong><?= htmlspecialchars($tk['title']) ?></strong></td>
          <td><?= htmlspecialchars($tk['raiser_name']) ?></td>
          <td><?= htmlspecialchars($tk['category_name'] ?? '—') ?></td>
          <td><?= getStatusBadge($tk['status']) ?></td>
          <td><?= htmlspecialchars($tk['level_name'] ?? '—') ?></td>
          <?php if ($role === 'system_admin'): ?>
          <td><?= htmlspecialchars($tk['assigned_admin_name'] ?? 'None') ?></td>
          <?php endif; ?>
          <td class="<?= $attendBreached  ? 'td-breach' : '' ?>">
            <?= $tk['attend_deadline']  ? date('d M, H:i', strtotime($tk['attend_deadline']))  : '—' ?>
          </td>
          <td class="<?= $resolveBreached ? 'td-breach' : '' ?>">
            <?= $tk['resolve_deadline'] ? date('d M, H:i', strtotime($tk['resolve_deadline'])) : '—' ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/ticket_detail.php?id=<?= $tk['id'] ?>" class="btn btn-primary btn-sm">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
