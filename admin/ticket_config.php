<?php
// admin/ticket_config.php — System Admin: configure levels, SLA, admins, extension reasons
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';

guard_require_login();
guard_require_role(['system_admin']);

$page_title = 'Ticket Configuration';
$msg = $err = '';

// ---- POST handlers ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // uses real csrf.php function — dies on invalid token

    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_level') {
        $name       = trim($_POST['level_name']  ?? '');
        $order      = (int)($_POST['level_order'] ?? 1);
        $attendSla  = (int)($_POST['attend_sla']  ?? 60);
        $resolveSla = (int)($_POST['resolve_sla'] ?? 120);
        $levelId    = (int)($_POST['level_id']    ?? 0);
        if (!$name) {
            $err = 'Level name is required.';
        } elseif ($resolveSla <= $attendSla) {
            $err = 'Resolve SLA must be greater than Attend SLA (e.g. Attend: 60 min, Resolve: 120 min).';
        } elseif ($levelId) {
            $pdo->prepare("UPDATE ticket_levels SET level_name=?, level_order=?, attend_sla=?, resolve_sla=? WHERE id=?")
                ->execute([$name, $order, $attendSla, $resolveSla, $levelId]);
            $msg = 'Level updated.';
        } else {
            $pdo->prepare("INSERT INTO ticket_levels (level_name, level_order, attend_sla, resolve_sla, created_by) VALUES (?,?,?,?,?)")
                ->execute([$name, $order, $attendSla, $resolveSla, $_SESSION['user_id']]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT IGNORE INTO round_robin_pointer (level_id, last_admin_index) VALUES (?,0)")->execute([$newId]);
            $msg = 'Level added.';
        }
    }

    if ($action === 'delete_level') {
        $pdo->prepare("DELETE FROM ticket_levels WHERE id=?")->execute([(int)($_POST['level_id'] ?? 0)]);
        $msg = 'Level deleted.';
    }

    if ($action === 'toggle_level') {
        $levelId = (int)($_POST['level_id'] ?? 0);
        $pdo->prepare("UPDATE ticket_levels SET is_active = NOT is_active WHERE id = ?")
            ->execute([$levelId]);
        $msg = 'Level status updated.';
    }

    if ($action === 'assign_admin') {
        $levelId = (int)($_POST['level_id'] ?? 0);
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($levelId && $adminId) {
            $pdo->prepare("INSERT IGNORE INTO ticket_level_admins (level_id, user_id) VALUES (?,?)")->execute([$levelId, $adminId]);
            $msg = 'Admin assigned.';
        } else { $err = 'Please select an admin.'; }
    }

    if ($action === 'remove_admin') {
        $pdo->prepare("DELETE FROM ticket_level_admins WHERE level_id=? AND user_id=?")
            ->execute([(int)($_POST['level_id'] ?? 0), (int)($_POST['admin_id'] ?? 0)]);
        $msg = 'Admin removed.';
    }

    if ($action === 'save_reason') {
        $text       = trim($_POST['reason_text'] ?? '');
        $timeLimit  = max(0.5, (float)($_POST['time_limit_hours'] ?? 1.0));
        $reasonId   = (int)($_POST['reason_id']  ?? 0);
        $active     = isset($_POST['is_active'])  ? 1 : 0;
        if (!$text) { $err = 'Reason text is required.'; }
        elseif ($reasonId) {
            $pdo->prepare("UPDATE ticket_extension_reasons SET reason_text=?, time_limit_hours=?, is_active=? WHERE id=?")->execute([$text, $timeLimit, $active, $reasonId]);
            $msg = 'Reason updated.';
        } else {
            $pdo->prepare("INSERT INTO ticket_extension_reasons (reason_text, time_limit_hours, is_active, created_by) VALUES (?,?,?,?)")->execute([$text, $timeLimit, 1, $_SESSION['user_id']]);
            $msg = 'Reason added.';
        }
    }

    if ($action === 'delete_reason') {
        $pdo->prepare("DELETE FROM ticket_extension_reasons WHERE id=?")->execute([(int)($_POST['reason_id'] ?? 0)]);
        $msg = 'Reason deleted.';
    }
}

// ---- Fetch data ----
$levels  = $pdo->query("SELECT * FROM ticket_levels ORDER BY level_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$reasons = $pdo->query("SELECT * FROM ticket_extension_reasons ORDER BY reason_text")->fetchAll(PDO::FETCH_ASSOC);
$admins  = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin','system_admin') AND is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$levelAdmins = [];
foreach ($pdo->query("SELECT tla.*, u.full_name FROM ticket_level_admins tla INNER JOIN users u ON u.id=tla.user_id")->fetchAll() as $row) {
    $levelAdmins[$row['level_id']][] = $row;
}

$csrf = csrf_generate(); // real function from csrf.php
include __DIR__ . '/../includes/header.php';
?>

<style>
.tc-header-row  { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
.tc-header-row h2 { margin:0; }
.level-block    { border:1px solid #e9ecef; border-radius:8px; padding:14px 18px; margin-bottom:14px; background:#fafafa; }
.level-top      { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; }
.level-meta     { font-size:.78rem; color:#6c757d; margin-top:3px; }
.level-btns     { display:flex; gap:6px; flex-shrink:0; }
.level-admins   { display:flex; align-items:center; flex-wrap:wrap; gap:8px; padding-top:8px; border-top:1px solid #eee; }
.admin-chip     { display:inline-flex; align-items:center; gap:4px; background:#dbeafe; color:#1d4ed8;
                  border-radius:14px; padding:3px 10px 3px 12px; font-size:.82rem; }
.chip-x         { background:none; border:none; color:#1d4ed8; font-size:1.1rem; cursor:pointer; padding:0 2px; line-height:1; }
.chip-x:hover   { color:#dc2626; }
.assign-row     { display:inline-flex; gap:6px; align-items:center; }
.assign-row select { padding:4px 8px; font-size:.82rem; border:1px solid #ced4da; border-radius:6px; }
.lbl-sm         { font-size:.78rem; color:#6c757d; font-weight:600; flex-shrink:0; }
.form-row3      { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.level-block.disabled { opacity:.6; background:#f8f8f8; border-color:#ddd; }
.btn-warning    { background:#f59e0b; color:#fff; border:none; }
.btn-warning:hover { background:#d97706; }
.btn-success-sm { background:#16a34a; color:#fff; border:none; }
.btn-success-sm:hover { background:#15803d; }
.disabled-badge { display:inline-flex; align-items:center; gap:4px; background:#fee2e2;
                  color:#dc2626; border-radius:12px; padding:2px 10px; font-size:.75rem; font-weight:600; margin-left:8px; }
/* Modal uses classes from style.css + hidden class toggled by main.js */
</style>

<div class="tc-header-row">
  <h2 class="section-title">⚙ Ticket Configuration</h2>
  <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-secondary btn-sm">← Back to Tickets</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ===== LEVELS ===== -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="tc-header-row">
    <h3 style="margin:0">Admin Levels &amp; SLA</h3>
    <button class="btn btn-primary btn-sm" onclick="openLevelModal(0,'',1,60,120)">+ Add Level</button>
  </div>

  <?php if (empty($levels)): ?>
    <p class="empty-state">No levels configured. Add your first level above.</p>
  <?php else: ?>
    <?php foreach ($levels as $lv): ?>
    <div class="level-block<?= $lv['is_active'] ? '' : ' disabled' ?>">
      <div class="level-top">
        <div>
          <strong><?= htmlspecialchars($lv['level_name']) ?></strong>
          <?php if (!$lv['is_active']): ?>
            <span class="disabled-badge">⊘ Disabled</span>
          <?php endif; ?>
          <div class="level-meta">
            Order: <?= $lv['level_order'] ?> &nbsp;|&nbsp;
            Attend SLA: <strong><?= $lv['attend_sla'] ?> min</strong> &nbsp;|&nbsp;
            Resolve SLA: <strong><?= $lv['resolve_sla'] ?> min</strong>
            <?php if (!$lv['is_active']): ?>
              &nbsp;|&nbsp;<span style="color:#dc2626">Tickets will skip this level</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="level-btns">
          <button class="btn btn-secondary btn-sm"
            onclick="openLevelModal(<?= $lv['id'] ?>,'<?= addslashes($lv['level_name']) ?>',<?= $lv['level_order'] ?>,<?= $lv['attend_sla'] ?>,<?= $lv['resolve_sla'] ?>)">
            Edit
          </button>
          <form method="post" style="display:inline"
            onsubmit="return confirm('<?= $lv['is_active'] ? 'Disable this level? New tickets will skip it.' : 'Enable this level? Tickets will be assigned here again.' ?>')">
            <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="toggle_level">
            <input type="hidden" name="level_id"    value="<?= $lv['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $lv['is_active'] ? 'btn-warning' : 'btn-success-sm' ?>">
              <?= $lv['is_active'] ? '⊘ Disable' : '✓ Enable' ?>
            </button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this level and all its admin assignments?')">
            <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="delete_level">
            <input type="hidden" name="level_id"    value="<?= $lv['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
          </form>
        </div>
      </div>

      <div class="level-admins">
        <span class="lbl-sm">Admins:</span>

        <?php if (!empty($levelAdmins[$lv['id']])): ?>
          <?php foreach ($levelAdmins[$lv['id']] as $la): ?>
          <span class="admin-chip">
            <?= htmlspecialchars($la['full_name']) ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
              <input type="hidden" name="form_action" value="remove_admin">
              <input type="hidden" name="level_id"    value="<?= $lv['id'] ?>">
              <input type="hidden" name="admin_id"    value="<?= $la['user_id'] ?>">
              <button type="submit" class="chip-x" title="Remove">×</button>
            </form>
          </span>
          <?php endforeach; ?>
        <?php else: ?>
          <span style="font-size:.82rem;color:#9ca3af">No admins assigned yet</span>
        <?php endif; ?>

        <form method="post" class="assign-row">
          <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
          <input type="hidden" name="form_action" value="assign_admin">
          <input type="hidden" name="level_id"    value="<?= $lv['id'] ?>">
          <select name="admin_id" required>
            <option value="">+ Assign Admin</option>
            <?php foreach ($admins as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Add</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ===== EXTENSION REASONS ===== -->
<div class="card">
  <div class="tc-header-row">
    <h3 style="margin:0">Time Extension Reasons</h3>
    <button class="btn btn-primary btn-sm" onclick="openReasonModal(0,'',1.0,true)">+ Add Reason</button>
  </div>

  <?php if (empty($reasons)): ?>
    <p class="empty-state">No extension reasons yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Reason</th><th>Time Limit</th><th>Active</th><th style="width:130px"></th></tr>
      </thead>
      <tbody>
      <?php foreach ($reasons as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['reason_text']) ?></td>
        <td><?= number_format((float)($r['time_limit_hours'] ?? 1), 1) ?> hr<?= ($r['time_limit_hours'] ?? 1) != 1 ? 's' : '' ?></td>
        <td>
          <?php if ($r['is_active']): ?>
            <span class="badge badge-success">Yes</span>
          <?php else: ?>
            <span class="badge badge-danger">No</span>
          <?php endif; ?>
        </td>
        <td>
          <button class="btn btn-secondary btn-sm"
            onclick="openReasonModal(<?= $r['id'] ?>,'<?= addslashes($r['reason_text']) ?>',<?= (float)($r['time_limit_hours'] ?? 1) ?>,<?= $r['is_active'] ? 'true' : 'false' ?>)">
            Edit
          </button>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this reason?')">
            <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
            <input type="hidden" name="form_action" value="delete_reason">
            <input type="hidden" name="reason_id"   value="<?= $r['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ===== Add/Edit Level Modal ===== -->
<div id="level-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="level-modal-title">Add Level</h3>
      <button class="modal-close" onclick="closeModal('level-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
        <input type="hidden" name="form_action" value="save_level">
        <input type="hidden" name="level_id"    id="lv_id" value="0">
        <div class="form-group">
          <label class="form-label">Level Name</label>
          <input type="text" name="level_name" id="lv_name" class="form-control" required placeholder="e.g. Level 1">
        </div>
        <div class="form-row3">
          <div class="form-group">
            <label class="form-label">Display Order</label>
            <input type="number" name="level_order" id="lv_order" class="form-control" min="1" value="1">
          </div>
          <div class="form-group">
            <label class="form-label">Attend SLA (min)</label>
            <input type="number" name="attend_sla" id="lv_attend" class="form-control" min="1" value="60">
          </div>
          <div class="form-group">
            <label class="form-label">Resolve SLA (min)</label>
            <input type="number" name="resolve_sla" id="lv_resolve" class="form-control" min="1" value="120">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('level-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Level</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Add/Edit Reason Modal ===== -->
<div id="reason-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title" id="reason-modal-title">Add Extension Reason</h3>
      <button class="modal-close" onclick="closeModal('reason-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= $csrf ?>">
        <input type="hidden" name="form_action" value="save_reason">
        <input type="hidden" name="reason_id"   id="rs_id" value="0">
        <div class="form-group">
          <label class="form-label">Reason Text</label>
          <input type="text" name="reason_text" id="rs_text" class="form-control" required placeholder="e.g. OEM Support Required">
        </div>
        <div class="form-group">
          <label class="form-label">Time Limit (hours)</label>
          <input type="number" name="time_limit_hours" id="rs_time" class="form-control" min="0.5" max="72" step="0.5" value="1" required placeholder="e.g. 2">
          <small style="color:var(--text-muted)">How many extra hours this reason grants (min 0.5)</small>
        </div>
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
            <input type="checkbox" name="is_active" id="rs_active">
            Active (appears in dropdown for admins)
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('reason-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Reason</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openLevelModal(id, name, order, attend, resolve) {
  document.getElementById('lv_id').value      = id;
  document.getElementById('lv_name').value    = name;
  document.getElementById('lv_order').value   = order;
  document.getElementById('lv_attend').value  = attend;
  document.getElementById('lv_resolve').value = resolve;
  document.getElementById('level-modal-title').textContent = id ? 'Edit Level' : 'Add Level';
  openModal('level-modal');
}
function openReasonModal(id, text, timeLimit, active) {
  document.getElementById('rs_id').value       = id;
  document.getElementById('rs_text').value     = text;
  document.getElementById('rs_time').value     = timeLimit || 1.0;
  document.getElementById('rs_active').checked = active;
  document.getElementById('reason-modal-title').textContent = id ? 'Edit Reason' : 'Add Extension Reason';
  openModal('reason-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>