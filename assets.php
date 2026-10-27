<?php
require_once __DIR__ . '/includes/asset_helpers.php';
guard_require_login();

$userId = (int)$_SESSION['user_id'];
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $reason = asset_post_string('reason');

    if ($action === 'request_asset') {
        if ($reason === '' || strlen($reason) > 4000) {
            $error = 'Please provide a reason (up to 4,000 characters).';
        } else {
            $pdo->prepare(
                "INSERT INTO asset_requests (request_type, user_id, reason)
                 VALUES ('allocation', ?, ?)"
            )->execute([$userId, $reason]);
            $msg = 'Asset request submitted for administrator review.';
        }
    } elseif ($action === 'request_return') {
        $allocationId = filter_input(INPUT_POST, 'allocation_id', FILTER_VALIDATE_INT);
        if (!$allocationId || $reason === '' || strlen($reason) > 4000) {
            $error = 'Unable to submit the return request. Check the allocation and reason.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, asset_id, return_requested_at FROM asset_allocations
                     WHERE id = ? AND user_id = ? AND actual_return_date IS NULL FOR UPDATE'
                );
                $stmt->execute([$allocationId, $userId]);
                $allocation = $stmt->fetch();
                if (!$allocation || $allocation['return_requested_at']) {
                    throw new RuntimeException('This allocation is not available for a new return request.');
                }
                $pdo->prepare(
                    "INSERT INTO asset_requests (request_type, user_id, asset_id, allocation_id, reason)
                     VALUES ('return', ?, ?, ?, ?)"
                )->execute([$userId, $allocation['asset_id'], $allocation['id'], $reason]);
                $update = $pdo->prepare(
                    'UPDATE asset_allocations SET return_requested_at = NOW()
                     WHERE id = ? AND return_requested_at IS NULL'
                );
                $update->execute([$allocation['id']]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('A return request already exists for this allocation.');
                }
                asset_log($pdo, (int)$allocation['asset_id'], $userId, 'return_requested', $reason);
                $pdo->commit();
                $msg = 'Return request submitted for administrator review.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e instanceof RuntimeException) {
                    $error = $e->getMessage();
                } else {
                    throw $e;
                }
            }
        }
    }
}

$allocations = $pdo->prepare(
    'SELECT aa.*, a.asset_tag, a.name, a.brand, a.model, a.status AS asset_status,
            ar.id AS pending_return_request, ar.status AS return_request_status
     FROM asset_allocations aa
     JOIN assets a ON a.id = aa.asset_id
     LEFT JOIN asset_requests ar ON ar.allocation_id = aa.id
        AND ar.request_type = "return" AND ar.status IN ("pending","approved")
     WHERE aa.user_id = ? AND aa.actual_return_date IS NULL
     ORDER BY aa.allocation_date DESC'
);
$allocations->execute([$userId]);
$myAllocations = $allocations->fetchAll();

$requests = $pdo->prepare(
    'SELECT ar.*, a.asset_tag, a.name AS asset_name
     FROM asset_requests ar LEFT JOIN assets a ON a.id = ar.asset_id
     WHERE ar.user_id = ? ORDER BY ar.created_at DESC LIMIT 100'
);
$requests->execute([$userId]);
$myRequests = $requests->fetchAll();

$page_title = 'My Assets & Requests';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header-row">
    <h2 class="section-title">My Assets &amp; Requests</h2>
    <?php if (is_admin()): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/assets.php">Asset Administration</a>
    <?php endif; ?>
</div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="two-col-grid">
    <section class="card">
        <h3>Request an asset</h3>
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="request_asset">
            <label class="form-label" for="request-reason">What do you need, and why?</label>
            <textarea class="form-control" id="request-reason" name="reason" maxlength="4000" required rows="4"></textarea>
            <button class="btn btn-primary" type="submit">Submit Request</button>
        </form>
    </section>
    <section class="card">
        <h3>Currently allocated to you</h3>
        <?php if (!$myAllocations): ?>
            <p class="empty-state">You have no active asset allocations.</p>
        <?php else: ?>
            <div class="table-responsive"><table class="data-table">
                <thead><tr><th>Asset</th><th>Allocated</th><th>Return by</th><th></th></tr></thead>
                <tbody><?php foreach ($myAllocations as $allocation): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/asset_detail.php?id=<?= (int)$allocation['asset_id'] ?>">
                            <?= htmlspecialchars($allocation['asset_tag'] . ' — ' . $allocation['name']) ?></a></td>
                        <td><?= htmlspecialchars($allocation['allocation_date']) ?></td>
                        <td><?= htmlspecialchars($allocation['expected_return_date'] ?? '—') ?></td>
                        <td><?php if (!$allocation['pending_return_request']): ?>
                            <form method="post" onsubmit="return confirm('Submit a return request for this asset?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="request_return">
                                <input type="hidden" name="allocation_id" value="<?= (int)$allocation['id'] ?>">
                                <input type="hidden" name="reason" value="User requested return">
                                <button class="btn btn-sm btn-secondary" type="submit">Request Return</button>
                            </form>
                        <?php else: ?><span class="badge"><?= htmlspecialchars(asset_status_label($allocation['return_request_status'])) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <h3>Your request history</h3>
    <?php if (!$myRequests): ?><p class="empty-state">No requests yet.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="data-table">
            <thead><tr><th>Type</th><th>Asset</th><th>Reason</th><th>Status</th><th>Remarks</th><th>Date</th></tr></thead>
            <tbody><?php foreach ($myRequests as $request): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($request['request_type'])) ?></td>
                    <td><?= htmlspecialchars($request['asset_tag'] ? $request['asset_tag'] . ' — ' . $request['asset_name'] : '—') ?></td>
                    <td><?= nl2br(htmlspecialchars($request['reason'])) ?></td>
                    <td><?= htmlspecialchars(asset_status_label($request['status'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($request['remarks'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars($request['created_at']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
