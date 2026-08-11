<?php
// ticket_detail.php — View ticket + Take-up / Resolve / Extend
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/ticket_helpers.php';

guard_require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/user_tickets.php'); exit; }

$stmt = $pdo->prepare("
    SELECT t.*, tl.level_name, tl.attend_sla, tl.resolve_sla,
           u.full_name  AS assigned_admin_name,
           r.full_name  AS raiser_name,
           c.name       AS category_name
    FROM tickets t
    LEFT JOIN ticket_levels tl ON tl.id = t.current_level
    LEFT JOIN users u          ON u.id  = t.current_admin
    LEFT JOIN users r          ON r.id  = t.raised_by
    LEFT JOIN categories c     ON c.id  = t.category_id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$t) { header('Location: ' . BASE_URL . '/user_tickets.php'); exit; }

// Users can only see their own tickets
if ($_SESSION['role'] === 'user' && $t['raised_by'] != $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . '/user_tickets.php'); exit;
}
// Auto-escalate if SLA breached — don't wait for cron
if (in_array($t['status'], ['open', 'in_progress'])) {
    $dbNow = new DateTime($pdo->query("SELECT NOW()")->fetchColumn());
    $breached = false;

    if ($t['status'] === 'open' && $t['attend_deadline'] && $dbNow > new DateTime($t['attend_deadline'])) {
        $breached = true;
    }
    if ($t['status'] === 'in_progress' && $t['resolve_deadline'] && $dbNow > new DateTime($t['resolve_deadline'])) {
        $breached = true;
    }

    if ($breached) {
        escalateTicket($pdo, $id);
        // Re-fetch ticket so the page shows updated state
        $stmt->execute([$id]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
// ── Auto-escalate when attend deadline has expired ────────────────────────────
// Use MySQL NOW() for comparison to avoid PHP/MySQL timezone mismatch.
// Calls escalateTicket() so the ticket moves up through levels properly
// (L1 → L2 → L3) before being marked Unattended only when all levels exhausted.
$dbNowStr = $pdo->query("SELECT NOW()")->fetchColumn();
$dbNow    = new DateTime($dbNowStr);

if (
    $t['status'] === 'open' &&
    !empty($t['attend_deadline']) &&
    $dbNow > new DateTime($t['attend_deadline'])
) {
    escalateTicket($pdo, $id);
    // Re-fetch ticket so the page reflects the updated level/admin/status
    $stmt->execute([$id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
}
// ─────────────────────────────────────────────────────────────────────────────

// Activity trail — exclude escalation entries
$activity = $pdo->prepare("
    SELECT ta.*, u.full_name AS actor_name, tl.level_name
    FROM ticket_activity ta
    LEFT JOIN users u          ON u.id  = ta.actor_id
    LEFT JOIN ticket_levels tl ON tl.id = ta.level_id
    WHERE ta.ticket_id = ?
      AND ta.action != 'escalated'
    ORDER BY ta.created_at ASC
");
$activity->execute([$id]);
$trail = $activity->fetchAll(PDO::FETCH_ASSOC);

// Extensions log
$exts = $pdo->prepare("
    SELECT te.*, u.full_name AS admin_name, er.reason_text
    FROM ticket_extensions te
    LEFT JOIN users u                     ON u.id  = te.admin_id
    LEFT JOIN ticket_extension_reasons er ON er.id = te.reason_id
    WHERE te.ticket_id = ?
    ORDER BY te.created_at DESC
");
$exts->execute([$id]);
$extensions = $exts->fetchAll(PDO::FETCH_ASSOC);

// Extension reasons dropdown
$reasons = $pdo->query("SELECT id, reason_text FROM ticket_extension_reasons WHERE is_active=1 ORDER BY reason_text")
               ->fetchAll(PDO::FETCH_ASSOC);

$isAdmin    = in_array($_SESSION['role'], ['admin','system_admin']);
// system_admin can VIEW any ticket but can only act on it if it is assigned to them
$isMyTicket = ($t['current_admin'] == $_SESSION['user_id']);
$backUrl    = $isAdmin ? BASE_URL . '/admin/tickets.php' : BASE_URL . '/user_tickets.php';

$page_title = 'Ticket #' . $id;
include __DIR__ . '/includes/header.php';
?>

<style>
/* ---- Ticket detail layout ---- */
.ticket-detail-grid   { display:grid; grid-template-columns:1fr 360px; gap:1.25rem; align-items:start; margin-top:1rem; }
@media(max-width:900px){ .ticket-detail-grid { grid-template-columns:1fr; } }
.side-stack           { display:flex; flex-direction:column; gap:1rem; }

/* ---- Detail dl ---- */
.detail-dl            { display:grid; grid-template-columns:130px 1fr; gap:5px 12px; }
.detail-dl dt         { color:var(--text-muted,#6c757d); font-size:.82rem; font-weight:600; padding-top:2px; }
.detail-dl dd         { margin:0; font-size:.88rem; }

/* ---- Description box ---- */
.desc-box             { background:var(--bg-subtle,#f8f9fa); border-left:4px solid var(--primary,#1565c0);
                        border-radius:0 6px 6px 0; padding:12px 16px; margin-top:1rem; }
.desc-box.resolution  { border-left-color:#2e7d32; }
.desc-box h4          { margin:0 0 6px; font-size:.75rem; color:var(--text-muted,#6c757d); text-transform:uppercase; letter-spacing:.5px; }
.desc-box p           { margin:0; line-height:1.6; white-space:pre-wrap; }

/* ---- Activity trail ---- */
.trail                { position:relative; padding-left:22px; }
.trail::before        { content:''; position:absolute; left:6px; top:0; bottom:0; width:2px; background:#dee2e6; }
.trail-item           { position:relative; margin-bottom:14px; }
.trail-dot            { position:absolute; left:-18px; top:4px; width:10px; height:10px;
                        border-radius:50%; background:#adb5bd; border:2px solid #fff; }
.trail-body           { font-size:.83rem; line-height:1.5; }
.trail-body small     { color:var(--text-muted,#6c757d); }
.trail-raised     .trail-dot { background:#1565c0; }
.trail-assigned   .trail-dot { background:#f57c00; }
.trail-taken_up   .trail-dot { background:#00897b; }
.trail-escalated  .trail-dot { background:#e53935; }
.trail-resolved   .trail-dot { background:#43a047; }
.trail-unresolved .trail-dot { background:#d81b60; }
.trail-unattended .trail-dot { background:#8e24aa; }
.trail-extended   .trail-dot { background:#f9a825; }

/* ---- Extension rows ---- */
.ext-row  { border-left:3px solid #f9a825; padding:8px 12px; margin-bottom:8px;
            background:#fffde7; border-radius:0 5px 5px 0; font-size:.83rem; line-height:1.6; }

/* ---- Checkbox group ---- */
.check-group  { display:flex; flex-direction:column; gap:6px; margin:10px 0; font-size:.88rem; }
.check-group label { display:flex; align-items:center; gap:8px; cursor:pointer; }

/* ---- Action msg ---- */
#action-msg { margin-top:12px; }
</style>

<!-- Back + title row -->
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:.5rem">
  <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm">← Back</a>
  <h2 style="margin:0;flex:1">Ticket #<?= $id ?> — <?= htmlspecialchars($t['title']) ?></h2>
  <?= getStatusBadge($t['status']) ?>
</div>

<div class="ticket-detail-grid">

  <!-- ===== LEFT: info ===== -->
  <div class="card">
    <h3 style="margin-top:0">Ticket Information</h3>
    <dl class="detail-dl">
      <dt>Raised By</dt>    <dd><?= htmlspecialchars($t['raiser_name']) ?></dd>
      <dt>Category</dt>     <dd><?= htmlspecialchars($t['category_name'] ?? '—') ?></dd>
      <dt>Level</dt>        <dd><?= htmlspecialchars($t['level_name']    ?? '—') ?></dd>
      <dt>Assigned Admin</dt><dd><?= htmlspecialchars($t['assigned_admin_name'] ?? 'None') ?></dd>
      <dt>Attend By</dt>    <dd><?= $t['attend_deadline']  ? date('d M Y, H:i', strtotime($t['attend_deadline']))  : '—' ?></dd>
      <dt>Attended At</dt>  <dd><?= $t['attended_at']      ? date('d M Y, H:i', strtotime($t['attended_at']))      : 'Not yet' ?></dd>
      <dt>Resolve By</dt>   <dd><?= $t['resolve_deadline'] ? date('d M Y, H:i', strtotime($t['resolve_deadline'])) : '—' ?></dd>
      <dt>Resolved At</dt>  <dd><?= $t['resolved_at']      ? date('d M Y, H:i', strtotime($t['resolved_at']))      : 'Not yet' ?></dd>
      <dt>Raised At</dt>    <dd><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></dd>
    </dl>

    <div class="desc-box">
      <h4>Description</h4>
      <p><?= htmlspecialchars($t['description']) ?></p>
    </div>

    <?php if ($t['resolution_note']): ?>
    <div class="desc-box resolution">
      <h4>Resolution Note</h4>
      <p><?= htmlspecialchars($t['resolution_note']) ?></p>
    </div>
    <?php endif; ?>
  </div>

  <!-- ===== RIGHT: actions + trail ===== -->
  <div class="side-stack">

    <?php
    $attendDeadlinePassed = $t['attend_deadline'] && ($dbNow > new DateTime($t['attend_deadline']));
    ?>
    <?php if ($isAdmin && $isMyTicket && $t['status'] === 'open' && !$attendDeadlinePassed): ?>
    <!-- Take Up -->
    <div class="card">
      <h3 style="margin-top:0">Take Up Ticket</h3>
      <p style="font-size:.88rem;color:#6c757d">Accept this ticket. Your attendance time will be recorded now.</p>
      <button class="btn btn-primary" style="width:100%" onclick="doAction('take_up')">✔ Take Up</button>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && $isMyTicket && $t['status'] === 'in_progress'): ?>
    <!-- Close Ticket -->
    <div class="card">
      <h3 style="margin-top:0">Close Ticket</h3>
      <div class="form-group">
        <label class="form-label">Resolution Note <span class="text-danger">*</span></label>
        <textarea id="resolution_note" class="form-control" rows="3" placeholder="Describe what was done…"></textarea>
      </div>
      <div class="check-group">
        <label><input type="checkbox" id="add_to_solution"> Add this resolution to the Solution Base</label>
        <label><input type="checkbox" id="solution_public"> Make it public (visible to all users)</label>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary" style="flex:1" onclick="doAction('resolve')">✔ Resolve</button>
        <button class="btn btn-danger"  style="flex:1" onclick="doAction('unresolve')">✘ Unresolved</button>
      </div>
    </div>

    <!-- Time Extension -->
    <div class="card">
      <h3 style="margin-top:0">⏱ Request Extension</h3>
      <div class="form-group">
        <label class="form-label">Reason <span class="text-danger">*</span></label>
        <select id="ext_reason" class="form-control">
          <option value="">— Select Reason —</option>
          <?php foreach ($reasons as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['reason_text']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Additional Hours <span class="text-danger">*</span></label>
        <input type="number" id="ext_hours" class="form-control" min="1" max="72" value="1">
      </div>
      <div class="form-group">
        <label class="form-label">Remarks <span class="text-danger">*</span></label>
        <textarea id="ext_remarks" class="form-control" rows="2" placeholder="Explain why extension is needed…"></textarea>
      </div>
      <button class="btn btn-secondary" style="width:100%" onclick="doAction('extend')">Apply Extension</button>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && $isMyTicket && $t['status'] === 'open' && $attendDeadlinePassed): ?>
    <!-- Deadline expired — cannot take up -->
    <div class="card" style="border-left: 4px solid #e53935;">
      <h3 style="margin-top:0;color:#e53935;">⚠ Attend Deadline Expired</h3>
      <p style="font-size:.88rem;color:#6c757d;">The attend deadline for this ticket has passed. It should be marked <strong>Unattended</strong>. No further action can be taken.</p>
    </div>
    <?php endif; ?>

    <!-- Extensions log -->
    <?php if (!empty($extensions)): ?>
    <div class="card">
      <h3 style="margin-top:0">Extensions Granted</h3>
      <?php foreach ($extensions as $ex): ?>
      <div class="ext-row">
        <strong><?= htmlspecialchars($ex['admin_name']) ?></strong> extended by <strong><?= $ex['extra_hours'] ?>h</strong><br>
        Reason: <?= htmlspecialchars($ex['reason_text']) ?><br>
        Remarks: <?= htmlspecialchars($ex['remarks']) ?><br>
        <small><?= date('d M, H:i', strtotime($ex['old_deadline'])) ?> → <?= date('d M, H:i', strtotime($ex['new_deadline'])) ?></small>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Activity Trail -->
    <div class="card">
      <h3 style="margin-top:0">Activity Trail</h3>
      <?php if (empty($trail)): ?>
        <p style="font-size:.85rem;color:#9ca3af">No activity yet.</p>
      <?php else: ?>
      <div class="trail">
        <?php foreach ($trail as $ev): ?>
        <div class="trail-item trail-<?= htmlspecialchars($ev['action']) ?>">
          <span class="trail-dot"></span>
          <div class="trail-body">
            <strong><?= ucfirst(str_replace('_', ' ', $ev['action'])) ?></strong>
            <?php if ($ev['actor_name']): ?> by <?= htmlspecialchars($ev['actor_name']) ?><?php endif; ?>
            <?php if ($ev['level_name']): ?> — <?= htmlspecialchars($ev['level_name']) ?><?php endif; ?>
            <?php if ($ev['notes']): ?><br><em style="color:#6c757d"><?= htmlspecialchars($ev['notes']) ?></em><?php endif; ?>
            <br><small><?= date('d M Y, H:i', strtotime($ev['created_at'])) ?></small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /side-stack -->
</div><!-- /ticket-detail-grid -->

<script>
const TICKET_ID = <?= $id ?>;

async function doAction(action) {
  const btn = event.target;
  btn.disabled = true;
  const origText = btn.textContent;
  btn.textContent = 'Please wait…';

  const fd = new FormData();
  fd.append('csrf_token',  '<?= csrf_generate() ?>');
  fd.append('action',      action);
  fd.append('ticket_id',   TICKET_ID);

  if (action === 'resolve' || action === 'unresolve') {
    fd.append('resolution_note', document.getElementById('resolution_note').value);
    if (document.getElementById('add_to_solution').checked) fd.append('add_to_solution', '1');
    if (document.getElementById('solution_public').checked)  fd.append('solution_public',  '1');
  }
  if (action === 'extend') {
    fd.append('reason_id',   document.getElementById('ext_reason').value);
    fd.append('extra_hours', document.getElementById('ext_hours').value);
    fd.append('remarks',     document.getElementById('ext_remarks').value);
  }

  try {
    const res  = await fetch('<?= BASE_URL ?>/includes/ticket_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    const msg  = document.getElementById('action-msg');
    msg.style.display = 'block';
    msg.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
    msg.textContent = data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1500);
    } else {
      btn.disabled = false;
      btn.textContent = origText;
    }
  } catch(err) {
    document.getElementById('action-msg').style.display = 'block';
    document.getElementById('action-msg').className = 'alert alert-error';
    document.getElementById('action-msg').textContent = 'Network error. Please try again.';
    btn.disabled = false;
    btn.textContent = origText;
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>