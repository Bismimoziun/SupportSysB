<?php
// user_tickets.php — User: view own tickets + raise new ticket
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/ticket_helpers.php';

guard_require_login();

$page_title = 'My Tickets';

$stmt = $pdo->prepare("
    SELECT t.*, tl.level_name, u.full_name AS assigned_admin, c.name AS category_name
    FROM tickets t
    LEFT JOIN ticket_levels tl ON tl.id = t.current_level
    LEFT JOIN users u          ON u.id  = t.current_admin
    LEFT JOIN categories c     ON c.id  = t.category_id
    WHERE t.raised_by = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$myTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cats = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NOT NULL ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header-row">
  <h2 class="section-title">🎫 My Tickets</h2>
  <button class="btn btn-primary" onclick="openModal('raise-ticket-modal')">+ Raise New Ticket</button>
</div>

<div class="card">
  <?php if (empty($myTickets)): ?>
    <p class="empty-state">You have not raised any tickets yet. Click <strong>+ Raise New Ticket</strong> to get started.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>#ID</th>
          <th>Title</th>
          <th>Category</th>
          <th>Status</th>
          <th>Assigned To</th>
          <th>Level</th>
          <th>Raised On</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($myTickets as $tk): ?>
        <tr>
          <td><span class="text-muted">#<?= $tk['id'] ?></span></td>
          <td><strong><?= htmlspecialchars($tk['title']) ?></strong></td>
          <td><?= htmlspecialchars($tk['category_name'] ?? '—') ?></td>
          <td><?= getStatusBadge($tk['status']) ?></td>
          <td><?= htmlspecialchars($tk['assigned_admin'] ?? 'Pending') ?></td>
          <td><?= htmlspecialchars($tk['level_name'] ?? '—') ?></td>
          <td><?= date('d M Y, H:i', strtotime($tk['created_at'])) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/ticket_detail.php?id=<?= $tk['id'] ?>" class="btn btn-sm btn-secondary">View →</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ===== Raise Ticket Modal ===== -->
<div id="raise-ticket-modal" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title">Raise a New Ticket</h3>
      <button class="modal-close" onclick="closeModal('raise-ticket-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <form id="raise-ticket-form">
        <?php csrf_field(); ?>
        <div class="form-group">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Briefly describe your issue">
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">— Select Category (optional) —</option>
            <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description <span class="text-danger">*</span></label>
          <textarea name="description" class="form-control" rows="5" required placeholder="Provide as much detail as possible…"></textarea>
        </div>
        <div id="raise-ticket-msg" class="alert" style="display:none"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('raise-ticket-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary" id="raise-btn">Submit Ticket</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('raise-ticket-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('raise-btn');
  const msg = document.getElementById('raise-ticket-msg');
  btn.disabled = true;
  btn.textContent = 'Submitting…';
  msg.style.display = 'none';

  try {
    const res  = await fetch('<?= BASE_URL ?>/includes/raise_ticket.php', { method: 'POST', body: new FormData(this) });
    const data = await res.json();
    msg.style.display = 'block';
    msg.className = 'alert ' + (data.success ? 'alert-success' : 'alert-error');
    msg.textContent = data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1500);
    } else {
      btn.disabled = false;
      btn.textContent = 'Submit Ticket';
    }
  } catch (err) {
    msg.style.display = 'block';
    msg.className = 'alert alert-error';
    msg.textContent = 'Network error. Please try again.';
    btn.disabled = false;
    btn.textContent = 'Submit Ticket';
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
