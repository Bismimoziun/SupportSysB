<?php
// ============================================================
// G&G Support Portal — admin/users.php
// Add / edit / delete / deactivate users (system_admin only)
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['system_admin']);

$page_title = 'User Management';
$msg = '';
$msg_type = '';
$edit_user = null;

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ADD USER
    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = $_POST['role']           ?? 'user';
        $password  = $_POST['password']       ?? '';

        if (!$full_name || !$username || !$password) {
            $msg = 'Full name, username, and password are required.'; $msg_type = 'danger';
        } else {
            // Check username unique
            $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $msg = 'Username already exists.'; $msg_type = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('INSERT INTO users (full_name, username, email, password, role, is_active) VALUES (?,?,?,?,?,1)')
                    ->execute([$full_name, $username, $email, $hash, $role]);
                $newUserId = (int)$pdo->lastInsertId();
                // If creating an admin, assign to selected level immediately
                if (in_array($role, ['admin','system_admin'])) {
                    $levelId = (int)($_POST['level_id'] ?? 0);
                    if ($levelId) {
                        // Remove any existing assignment for this user+level, then insert
                        $pdo->prepare('DELETE FROM ticket_level_admins WHERE user_id = ? AND level_id = ?')->execute([$newUserId, $levelId]);
                        $pdo->prepare('INSERT INTO ticket_level_admins (level_id, user_id) VALUES (?,?)')->execute([$levelId, $newUserId]);
                    }
                }
                $msg = 'User created successfully.'; $msg_type = 'success';
            }
        }
    }

    // EDIT USER
    if ($action === 'edit') {
        $id        = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = $_POST['role']           ?? 'user';
        $password  = $_POST['password']       ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $full_name) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET full_name=?, email=?, role=?, is_active=?, password=? WHERE id=?')
                    ->execute([$full_name, $email, $role, $is_active, $hash, $id]);
            } else {
                $pdo->prepare('UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?')
                    ->execute([$full_name, $email, $role, $is_active, $id]);
            }
            $msg = 'User updated.'; $msg_type = 'success';
        }
    }

    // DELETE USER
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== (int)$_SESSION['user_id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $msg = 'User deleted.'; $msg_type = 'success';
        } else {
            $msg = 'You cannot delete your own account.'; $msg_type = 'danger';
        }
    }
}

// Load user for editing
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($filter_role !== '') {
    $where .= ' AND role = ?';
    $params[] = $filter_role;
}

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY full_name ASC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$ticketLevels = $pdo->query("SELECT id, level_name FROM ticket_levels ORDER BY level_order ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Search & filter bar -->
<div class="card">
    <form method="GET" action="<?= BASE_URL ?>/admin/users.php" class="filter-bar">
        <input type="text" name="search" class="form-control" placeholder="Search name, username, email..." value="<?= htmlspecialchars($search) ?>">
        <select name="role" class="form-control" style="max-width:180px">
            <option value="">All Roles</option>
            <option value="system_admin" <?= $filter_role==='system_admin'?'selected':'' ?>>System Admin</option>
            <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
            <option value="user" <?= $filter_role==='user'?'selected':'' ?>>User</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Reset</a>
        <button type="button" class="btn btn-success" onclick="openModal('addUserModal')" style="margin-left:auto">+ Add User</button>
    </form>
</div>

<!-- Users table -->
<div class="card">
    <div class="card-header"><h2>👥 Users (<?= count($users) ?>)</h2></div>
    <?php if ($users): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr class="<?= !$u['is_active'] ? 'row-inactive' : '' ?>">
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
            <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
            <td><span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
                <button class="btn btn-sm btn-secondary"
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">Edit</button>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <button class="btn btn-sm btn-danger"
                    onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['full_name'])) ?>')">Delete</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty-state">No users found.</p>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>➕ Add New User</h2>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" id="addUserRole" onchange="toggleLevelField()">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="system_admin">System Admin</option>
                    </select>
                </div>
                <div class="form-group" id="addUserLevelGroup" style="display:none">
                    <label>Assign Level <span style="color:var(--text-muted);font-weight:400">(required for admins)</span></label>
                    <select name="level_id" class="form-control" id="addUserLevel">
                        <option value="">— Select Level —</option>
                        <?php foreach ($ticketLevels as $lvl): ?>
                        <option value="<?= $lvl['id'] ?>"><?= htmlspecialchars($lvl['level_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>✏️ Edit User</h2>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label>New Password <span class="text-muted">(leave blank to keep current)</span></label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="editRole" class="form-control">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="system_admin">System Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="editIsActive" value="1"> Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>🗑️ Delete User</h2>
            <button class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteUserId">
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong id="deleteUserName"></strong>?<br>This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteUserModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLevelField() {
    var role = document.getElementById('addUserRole').value;
    var grp  = document.getElementById('addUserLevelGroup');
    grp.style.display = (role === 'admin' || role === 'system_admin') ? 'block' : 'none';
}
function openEditModal(user) {
    document.getElementById('editUserId').value    = user.id;
    document.getElementById('editFullName').value  = user.full_name;
    document.getElementById('editEmail').value     = user.email || '';
    document.getElementById('editRole').value      = user.role;
    document.getElementById('editIsActive').checked = user.is_active == 1;
    openModal('editUserModal');
}
function confirmDeleteUser(id, name) {
    document.getElementById('deleteUserId').value      = id;
    document.getElementById('deleteUserName').textContent = name;
    openModal('deleteUserModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
