<?php
// ============================================================
// G&G Support Portal — admin/solutions.php
// Add / edit / approve / reject solutions + TinyMCE
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

$page_title = 'Solution Management';
$msg = '';
$msg_type = '';
$edit_solution = null;

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // SAVE (add or update)
    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $question    = trim($_POST['question'] ?? '');
        $answer      = $_POST['answer'] ?? '';   // raw HTML from TinyMCE
        $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
        $status      = in_array($_POST['status'] ?? '', ['pending','approved','rejected']) ? $_POST['status'] : 'pending';
        $req_admin   = isset($_POST['requires_admin']) ? 1 : 0;

        if ($question === '' || $answer === '') {
            $msg = 'Question and answer are required.'; $msg_type = 'danger';
        } elseif ($id) {
            $pdo->prepare(
                'UPDATE solutions SET question=?, answer=?, category_id=?, status=?, requires_admin=?, verified_by=? WHERE id=?'
            )->execute([$question, $answer, $category_id, $status, $req_admin, $_SESSION['user_id'], $id]);
            $msg = 'Solution updated.'; $msg_type = 'success';
        } else {
            $pdo->prepare(
                'INSERT INTO solutions (question, answer, category_id, submitted_by, status, requires_admin) VALUES (?,?,?,?,?,?)'
            )->execute([$question, $answer, $category_id, $_SESSION['user_id'], $status, $req_admin]);
            $msg = 'Solution added.'; $msg_type = 'success';
        }
    }

    // QUICK APPROVE / REJECT
    if ($action === 'approve') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE solutions SET status="approved", verified_by=? WHERE id=?')
            ->execute([$_SESSION['user_id'], $id]);
        $msg = 'Solution approved.'; $msg_type = 'success';
    }
    if ($action === 'reject') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE solutions SET status="rejected", verified_by=? WHERE id=?')
            ->execute([$_SESSION['user_id'], $id]);
        $msg = 'Solution rejected.'; $msg_type = 'danger';
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM solutions WHERE id = ?')->execute([$id]);
            $msg = 'Solution deleted.'; $msg_type = 'success';
        }
    }
}

// Load solution for editing
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM solutions WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit_solution = $stmt->fetch();
}

// Filter
$filter = $_GET['filter'] ?? '';
$search = trim($_GET['search'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($filter === 'pending')  { $where .= ' AND s.status = "pending"'; }
if ($filter === 'approved') { $where .= ' AND s.status = "approved"'; }
if ($filter === 'rejected') { $where .= ' AND s.status = "rejected"'; }
if ($search !== '') {
    $where .= ' AND (s.question LIKE ? OR s.answer LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}

$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name, u.full_name AS submitted_by_name
     FROM solutions s
     LEFT JOIN categories c ON s.category_id = c.id
     LEFT JOIN users u ON s.submitted_by = u.id
     $where
     ORDER BY s.id DESC"
);
$stmt->execute($params);
$solutions = $stmt->fetchAll();

// All categories for form dropdowns
$all_cats = $pdo->query('SELECT * FROM categories ORDER BY parent_id ASC, name ASC')->fetchAll();
$top_level_cats = array_filter($all_cats, fn($c) => $c['parent_id'] === null);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Quill.js rich text editor (free, no API key needed) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Add Solution Button + Filters -->
<div class="card">
    <div class="filter-bar">
        <form method="GET" action="<?= BASE_URL ?>/admin/solutions.php" style="display:contents">
            <input type="text" name="search" class="form-control" placeholder="Search solutions..." value="<?= htmlspecialchars($search) ?>">
            <select name="filter" class="form-control" style="max-width:160px">
                <option value="">All Status</option>
                <option value="pending"  <?= $filter==='pending' ?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= BASE_URL ?>/admin/solutions.php" class="btn btn-secondary">Reset</a>
        </form>
        <button class="btn btn-success" onclick="openSolutionForm()" style="margin-left:auto">+ Add Solution</button>
            <button class="btn btn-secondary" id="ai-reindex-btn" onclick="aiReindex()" title="Re-index approved solutions into the AI knowledge base">🤖 Re-index AI</button>
    </div>
</div>

<!-- Solutions table -->
<div class="card">
    <div class="card-header"><h2>💡 Solutions (<?= count($solutions) ?>)</h2></div>
    <?php if ($solutions): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Question</th>
                <th>Category</th>
                <th>Status</th>
                <th>Admin Only</th>
                <th>Submitted By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($solutions as $s): ?>
        <tr>
            <td><?= htmlspecialchars(substr($s['question'], 0, 70)) ?><?= strlen($s['question']) > 70 ? '…' : '' ?></td>
            <td><?= htmlspecialchars($s['category_name'] ?? '—') ?></td>
            <td><span class="badge badge-status-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
            <td><?= $s['requires_admin'] ? '<span class="badge badge-danger">Yes</span>' : 'No' ?></td>
            <td><?= htmlspecialchars($s['submitted_by_name'] ?? '—') ?></td>
            <td class="actions-cell">
                <button class="btn btn-sm btn-secondary"
                    onclick='openEditForm(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>Edit</button>
                <?php if ($s['status'] === 'pending'): ?>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success">✓ Approve</button>
                </form>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">✗ Reject</button>
                </form>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline"
                    onclick='openPreview(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>👁 Preview</button>
                <button class="btn btn-sm btn-danger"
                    onclick="confirmDeleteSol(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars(substr($s['question'],0,40))) ?>')">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty-state">No solutions found.</p>
    <?php endif; ?>
</div>

<!-- Add / Edit Solution Modal -->
<div id="solutionModal" class="modal-overlay hidden">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h2 id="solutionModalTitle">➕ Add Solution</h2>
            <button class="modal-close" onclick="closeModal('solutionModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/solutions.php" id="solutionForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="solId" value="0">
            <div class="modal-body">

                <div class="form-group">
                    <label>Question *</label>
                    <input type="text" name="question" id="solQuestion" class="form-control" placeholder="Enter the question..." required>
                </div>

                <!-- Dependent dropdowns: Category → Sub-category -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select id="solCatParent" class="form-control" onchange="loadSubCats(this.value)">
                            <option value="">— Select category —</option>
                            <?php foreach ($top_level_cats as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="subCatGroup" style="display:none">
                        <label>Sub-category</label>
                        <select id="solCatSub" class="form-control" onchange="updateCategoryId(this.value)">
                            <option value="">— Select sub-category —</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="category_id" id="solCategoryId">

                <div class="form-group">
                    <label>Answer *</label>
                    <!-- Hidden field that holds the HTML content for form submit -->
                    <input type="hidden" name="answer" id="solAnswer">
                    <!-- Quill editor container -->
                    <div id="quillEditor" style="height:280px;background:#fff;border:1px solid #ced4da;border-radius:6px;font-size:14px;"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="solStatus" class="form-control">
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
                        <label class="checkbox-label">
                            <input type="checkbox" name="requires_admin" id="solRequiresAdmin" value="1">
                            Admin-only (hide from regular users)
                        </label>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('solutionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Solution</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal-overlay hidden">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h2>👁 Solution Preview</h2>
            <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <h3 id="previewQuestion" style="margin-bottom:1rem;font-size:1.1rem"></h3>
            <div id="previewAnswer" class="solution-preview-body"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('previewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Delete Solution Modal -->
<div id="deleteSolModal" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header">
            <h2>🗑️ Delete Solution</h2>
            <button class="modal-close" onclick="closeModal('deleteSolModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/solutions.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteSolId">
            <div class="modal-body">
                <p>Delete solution: <strong id="deleteSolName"></strong>?<br>This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteSolModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const allCats    = <?= json_encode(array_values($all_cats)) ?>;

// ── Quill rich text editor init ───────────────────────────────
const quill = new Quill('#quillEditor', {
    theme: 'snow',
    placeholder: 'Write the answer here…',
    modules: {
        toolbar: [
            [{ 'font': [] }, { 'size': [] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'align': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link', 'image'],
            ['clean']
        ]
    }
});

// Sync Quill HTML into the hidden input before form submit
document.getElementById('solutionForm').addEventListener('submit', function() {
    document.getElementById('solAnswer').value = quill.root.innerHTML;
});

// Helper: get/set Quill content
function quillSetContent(html) { quill.root.innerHTML = html || ''; }
function quillGetContent()     { return quill.root.innerHTML; }

// ── Open add form ─────────────────────────────────────────────
function openSolutionForm() {
    document.getElementById('solutionModalTitle').textContent = '➕ Add Solution';
    document.getElementById('solId').value          = '0';
    document.getElementById('solQuestion').value    = '';
    document.getElementById('solStatus').value      = 'approved';
    document.getElementById('solRequiresAdmin').checked = false;
    document.getElementById('solCatParent').value   = '';
    document.getElementById('solCategoryId').value  = '';
    document.getElementById('subCatGroup').style.display = 'none';
    quillSetContent('');
    openModal('solutionModal');
}

// ── Open edit form ────────────────────────────────────────────
function openEditForm(s) {
    document.getElementById('solutionModalTitle').textContent = '✏️ Edit Solution';
    document.getElementById('solId').value          = s.id;
    document.getElementById('solQuestion').value    = s.question;
    document.getElementById('solStatus').value      = s.status;
    document.getElementById('solRequiresAdmin').checked = s.requires_admin == 1;
    document.getElementById('solCategoryId').value  = s.category_id || '';

    // Set parent/sub dropdowns from category_id
    if (s.category_id) {
        const cat = allCats.find(c => c.id == s.category_id);
        if (cat) {
            if (cat.parent_id) {
                document.getElementById('solCatParent').value = cat.parent_id;
                loadSubCats(cat.parent_id, s.category_id);
            } else {
                document.getElementById('solCatParent').value = cat.id;
                document.getElementById('subCatGroup').style.display = 'none';
                updateCategoryId(cat.id);
            }
        }
    } else {
        document.getElementById('solCatParent').value = '';
        document.getElementById('subCatGroup').style.display = 'none';
    }

    quillSetContent(s.answer || '');
    openModal('solutionModal');
}

// ── Dependent dropdowns ───────────────────────────────────────
function loadSubCats(parentId, selectedId = null) {
    const children = allCats.filter(c => c.parent_id == parentId);
    const group    = document.getElementById('subCatGroup');
    const sel      = document.getElementById('solCatSub');

    if (children.length === 0) {
        group.style.display = 'none';
        updateCategoryId(parentId);
        return;
    }

    sel.innerHTML = '<option value="">— Select sub-category —</option>';
    children.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        if (selectedId && c.id == selectedId) opt.selected = true;
        sel.appendChild(opt);
    });
    group.style.display = '';
    updateCategoryId(selectedId || parentId);
}

function updateCategoryId(id) {
    document.getElementById('solCategoryId').value = id || '';
}

// ── Preview ───────────────────────────────────────────────────
function openPreview(s) {
    document.getElementById('previewQuestion').textContent = s.question;
    document.getElementById('previewAnswer').innerHTML     = s.answer;
    openModal('previewModal');
}

// ── Delete ────────────────────────────────────────────────────
function confirmDeleteSol(id, name) {
    document.getElementById('deleteSolId').value      = id;
    document.getElementById('deleteSolName').textContent = name + '…';
    openModal('deleteSolModal');
}

// ── AI Re-index ───────────────────────────────────────────────
function aiReindex() {
    const btn = document.getElementById('ai-reindex-btn');
    btn.disabled    = true;
    btn.textContent = '⏳ Indexing…';

    const body = new URLSearchParams({
        action:     'ingest',
        csrf_token: document.getElementById('ai-csrf-token')?.value ?? '',
    });

    fetch(BASE_URL + '/includes/ai_chat.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        body.toString(),
    })
    .then(r => r.json())
    .then(function (data) {
        btn.disabled    = false;
        btn.textContent = '🤖 Re-index AI';
        if (data.success) {
            alert('✅ AI re-indexed successfully!\n' + data.indexed + ' chunks from ' + data.solutions + ' solutions.');
        } else {
            alert('❌ Indexing failed: ' + (data.message ?? data.detail ?? 'Unknown error'));
        }
    })
    .catch(function () {
        btn.disabled    = false;
        btn.textContent = '🤖 Re-index AI';
        alert('❌ Could not reach the AI service. Is the Python server running?');
    });
}
</script>
<!-- CSRF token for AI admin actions -->
<input type="hidden" id="ai-csrf-token" value="<?= htmlspecialchars(csrf_generate()) ?>">

<?php require_once __DIR__ . '/../includes/footer.php'; ?>