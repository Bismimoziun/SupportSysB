<?php
// ============================================================
// G&G Support Portal — admin/pending_flags.php
// View and resolve / ignore flagged questions
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

$page_title = 'Flagged Questions';
$msg = '';
$msg_type = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'resolve' && $id) {
        $pdo->prepare('UPDATE flags SET status="resolved", resolved_by=? WHERE id=?')
            ->execute([$_SESSION['user_id'], $id]);
        $msg = 'Flag marked as resolved.'; $msg_type = 'success';
    }

    if ($action === 'ignore' && $id) {
        $pdo->prepare('UPDATE flags SET status="ignored", resolved_by=? WHERE id=?')
            ->execute([$_SESSION['user_id'], $id]);
        $msg = 'Flag ignored.'; $msg_type = 'warning';
    }

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM flags WHERE id=?')->execute([$id]);
        $msg = 'Flag deleted.'; $msg_type = 'success';
    }
}

$filter = $_GET['filter'] ?? 'open';

$where  = '';
$params = [];
if (in_array($filter, ['open','resolved','ignored'], true)) {
    $where  = 'WHERE f.status = ?';
    $params = [$filter];
}

$stmt = $pdo->prepare(
    "SELECT f.*, u.full_name AS raised_by_name, r.full_name AS resolved_by_name
     FROM flags f
     LEFT JOIN users u ON f.raised_by = u.id
     LEFT JOIN users r ON f.resolved_by = r.id
     $where
     ORDER BY f.id DESC"
);
$stmt->execute($params);
$flags = $stmt->fetchAll();

$counts = [
    'open'     => (int)$pdo->query('SELECT COUNT(*) FROM flags WHERE status="open"')->fetchColumn(),
    'resolved' => (int)$pdo->query('SELECT COUNT(*) FROM flags WHERE status="resolved"')->fetchColumn(),
    'ignored'  => (int)$pdo->query('SELECT COUNT(*) FROM flags WHERE status="ignored"')->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter tabs -->
<div class="tab-bar">
    <a href="?filter=open"     class="tab <?= $filter==='open'     ?'active':'' ?>">🟠 Open (<?= $counts['open'] ?>)</a>
    <a href="?filter=resolved" class="tab <?= $filter==='resolved' ?'active':'' ?>">✅ Resolved (<?= $counts['resolved'] ?>)</a>
    <a href="?filter=ignored"  class="tab <?= $filter==='ignored'  ?'active':'' ?>">🚫 Ignored (<?= $counts['ignored'] ?>)</a>
</div>

<div class="card">
    <div class="card-header"><h2>🚩 Flags — <?= ucfirst($filter) ?> (<?= count($flags) ?>)</h2></div>

    <?php if ($flags): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Question</th>
                <th>Raised By</th>
                <th>Date</th>
                <th>Handled By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($flags as $f): ?>
        <tr>
            <td><?= $f['id'] ?></td>
            <td><?= htmlspecialchars($f['question']) ?></td>
            <td><?= htmlspecialchars($f['raised_by_name'] ?? '—') ?></td>
            <td><?= isset($f['created_at']) ? date('d M Y', strtotime($f['created_at'])) : '—' ?></td>
            <td><?= htmlspecialchars($f['resolved_by_name'] ?? '—') ?></td>
            <td class="actions-cell">
                <?php if ($f['status'] === 'open'): ?>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="resolve">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success">✓ Resolve</button>
                </form>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="ignore">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">Ignore</button>
                </form>
                <!-- Shortcut: create a solution from this flag -->
                <a href="<?= BASE_URL ?>/admin/solutions.php?prefill=<?= urlencode($f['question']) ?>"
                   class="btn btn-sm btn-primary">+ Add Solution</a>
                <?php endif; ?>
                <form method="POST" style="display:inline">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty-state">No <?= $filter ?> flags. 🎉</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
