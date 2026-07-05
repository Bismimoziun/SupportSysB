<?php
// ============================================================
// G&G Support Portal — admin/categories.php
// Add / rename / delete categories (tree structure)
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

$page_title = 'Categories';
$msg = '';
$msg_type = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ADD
    if ($action === 'add') {
        $name      = trim($_POST['name'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($name === '') {
            $msg = 'Category name is required.'; $msg_type = 'danger';
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, parent_id, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$name, $parent_id, $_SESSION['user_id']]);
            $msg = 'Category added successfully.'; $msg_type = 'success';
        }
    }

    // RENAME
    if ($action === 'rename') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
            $msg = 'Category renamed.'; $msg_type = 'success';
        }
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Check for children
            $kids = (int)$pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?')->execute([$id]) && 1;
            $kids = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id = $id")->fetchColumn();
            $sols = (int)$pdo->query("SELECT COUNT(*) FROM solutions WHERE category_id = $id")->fetchColumn();
            if ($kids > 0 || $sols > 0) {
                $msg = 'Cannot delete: category has sub-categories or solutions linked to it.'; $msg_type = 'danger';
            } else {
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                $msg = 'Category deleted.'; $msg_type = 'success';
            }
        }
    }
}

// ── Fetch all categories for tree ───────────────────────────
$all_cats = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();

// Build tree
function build_tree(array $cats, ?int $parent_id = null): array {
    $tree = [];
    foreach ($cats as $cat) {
        $pid = $cat['parent_id'] === null ? null : (int)$cat['parent_id'];
        if ($pid === $parent_id) {
            $cat['children'] = build_tree($cats, (int)$cat['id']);
            $tree[] = $cat;
        }
    }
    return $tree;
}

function render_tree(array $nodes, int $depth = 0): void {
    foreach ($nodes as $node) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        $prefix = $depth > 0 ? '└─ ' : '';
        echo '<tr>';
        echo '<td>' . $indent . $prefix . htmlspecialchars($node['name']) . '</td>';
        echo '<td>' . ($node['parent_id'] ? 'Sub-category' : 'Top-level') . '</td>';
        echo '<td>';
        echo '<button class="btn btn-sm btn-secondary" onclick="openRename(' . $node['id'] . ', \'' . addslashes(htmlspecialchars($node['name'])) . '\')">Rename</button> ';
        echo '<button class="btn btn-sm btn-danger" onclick="confirmDelete(' . $node['id'] . ', \'' . addslashes(htmlspecialchars($node['name'])) . '\')">Delete</button>';
        echo '</td>';
        echo '</tr>';
        if (!empty($node['children'])) {
            render_tree($node['children'], $depth + 1);
        }
    }
}

$tree = build_tree($all_cats);
$top_level = array_filter($all_cats, fn($c) => $c['parent_id'] === null);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="two-col-grid">

    <!-- Add category form -->
    <div class="card">
        <div class="card-header"><h2>➕ Add Category</h2></div>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. HR Queries" required>
            </div>
            <div class="form-group">
                <label>Parent Category <span class="text-muted">(leave blank for top-level)</span></label>
                <select name="parent_id" class="form-control">
                    <option value="">— Top-level category —</option>
                    <?php foreach ($all_cats as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
    </div>

    <!-- Category tree -->
    <div class="card">
        <div class="card-header"><h2>📁 Category Tree</h2></div>
        <?php if ($all_cats): ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Level</th><th>Actions</th></tr></thead>
            <tbody>
            <?php render_tree($tree); ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="empty-state">No categories yet. Add one!</p>
        <?php endif; ?>
    </div>

</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>✏️ Rename Category</h2>
            <button class="modal-close" onclick="closeModal('renameModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" id="renameId">
            <div class="modal-body">
                <div class="form-group">
                    <label>New Name</label>
                    <input type="text" name="name" id="renameName" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('renameModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>🗑️ Delete Category</h2>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteName"></strong>?<br>
                This will fail if the category has sub-categories or solutions linked to it.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRename(id, name) {
    document.getElementById('renameId').value = id;
    document.getElementById('renameName').value = name;
    openModal('renameModal');
}
function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    openModal('deleteModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
