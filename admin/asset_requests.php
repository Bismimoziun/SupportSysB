<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$msg = '';
$error = '';
$adminId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT) ?: 0;
    $action = $_POST['action'] ?? '';
    $remarks = asset_post_string('remarks');
    if (!in_array($action, ['approve','reject'], true) || strlen($remarks) > 4000) {
        $error = 'Invalid action or remarks are too long.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM asset_requests WHERE id=? FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request || $request['status'] !== 'pending') {
                throw new RuntimeException('Request is no longer pending.');
            }
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare(
                'UPDATE asset_requests SET status=?, remarks=?, reviewed_by=?, reviewed_at=NOW()
                 WHERE id=? AND status="pending"'
            )->execute([$newStatus, $remarks ?: null, $adminId, $requestId]);
            if ($request['request_type'] === 'return' && $action === 'reject') {
                $pdo->prepare(
                    'UPDATE asset_allocations SET return_requested_at=NULL
                     WHERE id=? AND actual_return_date IS NULL'
                )->execute([$request['allocation_id']]);
            }
            if ($request['asset_id']) {
                asset_log($pdo, (int)$request['asset_id'], $adminId, 'request_' . $newStatus,
                    ucfirst($request['request_type']) . ' request #' . $requestId . ($remarks ? ': ' . $remarks : ''));
            }
            $pdo->commit();
            $msg = 'Request ' . $newStatus . '.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update the request.';
            if (!$e instanceof RuntimeException) {
                throw $e;
            }
        }
    }
}

$requests = $pdo->query(
    'SELECT ar.*, u.full_name, u.username, a.asset_tag, a.name AS asset_name
     FROM asset_requests ar
     JOIN users u ON u.id=ar.user_id
     LEFT JOIN assets a ON a.id=ar.asset_id
     ORDER BY (ar.status="pending") DESC, ar.created_at DESC LIMIT 300'
)->fetchAll();
$page_title = 'Asset Requests';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title">Asset Requests</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_allocations.php">Allocations &amp; Returns</a></div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="card">
    <?php if (!$requests): ?><p class="empty-state">No requests have been submitted.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Type</th><th>User</th><th>Asset</th><th>Reason</th><th>Status</th><th>Remarks</th><th>Requested</th><th>Review</th></tr></thead>
        <tbody><?php foreach ($requests as $r): ?><tr>
            <td><?= htmlspecialchars(ucfirst($r['request_type'])) ?></td>
            <td><?= htmlspecialchars($r['full_name'] . ' (' . $r['username'] . ')') ?></td>
            <td><?= htmlspecialchars($r['asset_tag'] ? $r['asset_tag'] . ' — ' . $r['asset_name'] : '—') ?></td>
            <td><?= nl2br(htmlspecialchars($r['reason'])) ?></td>
            <td><?= htmlspecialchars(asset_status_label($r['status'])) ?></td>
            <td><?= nl2br(htmlspecialchars($r['remarks'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?php if ($r['status'] === 'pending'): ?>
                <form method="post">
                    <?php csrf_field(); ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <input class="form-control" name="remarks" maxlength="4000" placeholder="Review remarks">
                    <button class="btn btn-sm btn-success" name="action" value="approve" type="submit">Approve</button>
                    <button class="btn btn-sm btn-danger" name="action" value="reject" type="submit" onclick="return confirm('Reject this request?')">Reject</button>
                </form>
                <?php elseif ($r['status'] === 'approved' && $r['request_type'] === 'allocation'): ?>
                    <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/admin/asset_allocations.php?request=<?= (int)$r['id'] ?>">Allocate</a>
                <?php else: ?>—<?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
