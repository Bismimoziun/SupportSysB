<?php
// ============================================================
// G&G Support Portal — admin/announcements.php
// Add / edit / delete / reorder announcements
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

$page_title = 'Announcements';
$msg = '';
$msg_type = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // SAVE (add or edit)
    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = trim($_POST['title']    ?? '');
        $content   = trim($_POST['content']  ?? '');
        $priority  = (int)($_POST['priority'] ?? 10);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            $msg = 'Title is required.'; $msg_type = 'danger';
        } elseif ($id) {
            $pdo->prepare('UPDATE announcements SET title=?, content=?, priority=?, is_active=? WHERE id=?')
                ->execute([$title, $content, $priority, $is_active, $id]);
            $msg = 'Announcement updated.'; $msg_type = 'success';
        } else {
            $pdo->prepare('INSERT INTO announcements (title, content, priority, is_active, created_by) VALUES (?,?,?,?,?)')
                ->execute([$title, $content, $priority, $is_active, $_SESSION['user_id']]);
            $msg = 'Announcement added.'; $msg_type = 'success';
        }
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
            $msg = 'Announcement deleted.'; $msg_type = 'success';
        }
    }

    // TOGGLE ACTIVE
    if ($action === 'toggle') {
        $id  = (int)($_POST['id']     ?? 0);
        $val = (int)($_POST['active'] ?? 0);
        $pdo->prepare('UPDATE announcements SET is_active = ? WHERE id = ?')->execute([$val, $id]);
        $msg = 'Status updated.'; $msg_type = 'success';
    }
}

$announcements = $pdo->query(
    'SELECT a.*, u.full_name AS created_by_name
     FROM announcements a
     LEFT JOIN users u ON a.created_by = u.id
     ORDER BY a.priority ASC, a.id DESC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.5rem">
    <button class="btn btn-success" onclick="openAnnForm()">+ Add Announcement</button>
</div>

<div class="card">
    <div class="card-header">
        <h2>📢 Announcements</h2>
        <span class="text-muted">Lower priority number = shown first. Top 3 active are shown to users.</span>
    </div>

    <?php if ($announcements): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Priority</th>
                <th>Title</th>
                <th>Content</th>
                <th>Active</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($announcements as $a): ?>
        <tr class="<?= !$a['is_active'] ? 'row-inactive' : '' ?>">
            <td><span class="priority-badge"><?= (int)$a['priority'] ?></span></td>
            <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
            <td><?= htmlspecialchars(strip_tags(substr($a['content'], 0, 80))) ?>…</td>
            <td>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="active" value="<?= $a['is_active'] ? 0 : 1 ?>">
                    <button type="submit" class="toggle-btn <?= $a['is_active'] ? 'toggle-on' : 'toggle-off' ?>">
                        <?= $a['is_active'] ? '● On' : '○ Off' ?>
                    </button>
                </form>
            </td>
            <td><?= htmlspecialchars($a['created_by_name'] ?? '—') ?></td>
            <td>
                <button class="btn btn-sm btn-secondary"
                    onclick='openEditAnn(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'>Edit</button>
                <button class="btn btn-sm btn-danger"
                    onclick="confirmDeleteAnn(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['title'])) ?>')">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty-state">No announcements yet.</p>
    <?php endif; ?>
</div>

<!-- Add / Edit Modal -->
<div id="annModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="annModalTitle">➕ Add Announcement</h2>
            <button class="modal-close" onclick="closeModal('annModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/announcements.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="annId" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="annTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="annContent" class="form-control" rows="4" placeholder="Announcement body..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Priority <span class="text-muted">(1 = highest, shown first)</span></label>
                        <input type="number" name="priority" id="annPriority" class="form-control" value="10" min="1" max="999">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="annActive" value="1" checked> Active (visible to users)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('annModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteAnnModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>🗑️ Delete Announcement</h2>
            <button class="modal-close" onclick="closeModal('deleteAnnModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/announcements.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteAnnId">
            <div class="modal-body">
                <p>Delete announcement: <strong id="deleteAnnName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteAnnModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAnnForm() {
    document.getElementById('annModalTitle').textContent = '➕ Add Announcement';
    document.getElementById('annId').value       = '0';
    document.getElementById('annTitle').value    = '';
    document.getElementById('annContent').value  = '';
    document.getElementById('annPriority').value = '10';
    document.getElementById('annActive').checked = true;
    openModal('annModal');
}
function openEditAnn(a) {
    document.getElementById('annModalTitle').textContent = '✏️ Edit Announcement';
    document.getElementById('annId').value       = a.id;
    document.getElementById('annTitle').value    = a.title;
    document.getElementById('annContent').value  = a.content;
    document.getElementById('annPriority').value = a.priority;
    document.getElementById('annActive').checked = a.is_active == 1;
    openModal('annModal');
}
function confirmDeleteAnn(id, name) {
    document.getElementById('deleteAnnId').value      = id;
    document.getElementById('deleteAnnName').textContent = name;
    openModal('deleteAnnModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
