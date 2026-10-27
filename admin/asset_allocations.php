<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$adminId = (int)$_SESSION['user_id'];
$msg = '';
$error = '';
$requestId = filter_input(INPUT_GET, 'request', FILTER_VALIDATE_INT) ?: 0;
$approvedRequest = null;
if ($requestId) {
    $stmt = $pdo->prepare(
        "SELECT ar.*, u.full_name FROM asset_requests ar JOIN users u ON u.id=ar.user_id
         WHERE ar.id=? AND ar.request_type='allocation' AND ar.status='approved'"
    );
    $stmt->execute([$requestId]);
    $approvedRequest = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'allocate') {
            $assetId = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT) ?: 0;
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
            $postRequestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT) ?: 0;
            $expected = asset_post_string('expected_return_date');
            $notes = asset_post_string('allocation_notes');
            if (!$assetId || !$userId || !asset_valid_date($expected) || strlen($notes) > 4000) {
                throw new RuntimeException('Select a user and asset, and check the return date and notes.');
            }
            if ($postRequestId) {
                $req = $pdo->prepare(
                    "SELECT user_id FROM asset_requests
                     WHERE id=? AND request_type='allocation' AND status='approved'"
                );
                $req->execute([$postRequestId]);
                $requestUser = $req->fetchColumn();
                if (!$requestUser || (int)$requestUser !== $userId) {
                    throw new RuntimeException('The selected approved request does not match this user.');
                }
            }
            asset_allocate($pdo, $assetId, $userId, $adminId, $expected ?: null, $notes, $postRequestId ?: null);
            $msg = 'Asset allocated successfully.';
        } elseif ($action === 'return') {
            $allocationId = filter_input(INPUT_POST, 'allocation_id', FILTER_VALIDATE_INT) ?: 0;
            $condition = $_POST['return_condition'] ?? '';
            $notes = asset_post_string('return_notes');
            if (!$allocationId || !in_array($condition, ['new','good','fair','poor','damaged'], true) || strlen($notes) > 4000) {
                throw new RuntimeException('Select a valid return condition and enter notes no longer than 4,000 characters.');
            }
            asset_process_return($pdo, $allocationId, $adminId, $condition, $notes);
            $msg = 'Return processed. Assets requiring repair remain unavailable.';
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'This asset already has an active allocation, or the selected request was already used.';
        } else {
            throw $e;
        }
    }
}

$availableAssets = $pdo->query(
    "SELECT id, asset_tag, name FROM assets WHERE status='available' ORDER BY asset_tag"
)->fetchAll();
$users = $pdo->query(
    'SELECT id, full_name, username FROM users WHERE is_active=1 ORDER BY full_name'
)->fetchAll();
$active = $pdo->query(
    'SELECT aa.*, a.asset_tag, a.name AS asset_name, u.full_name, u.username,
            ar.id AS return_request_id, ar.status AS return_request_status
     FROM asset_allocations aa JOIN assets a ON a.id=aa.asset_id
     JOIN users u ON u.id=aa.user_id
     LEFT JOIN asset_requests ar ON ar.allocation_id=aa.id AND ar.request_type="return"
     WHERE aa.actual_return_date IS NULL ORDER BY aa.allocation_date DESC'
)->fetchAll();
$history = $pdo->query(
    'SELECT aa.*, a.asset_tag, a.name AS asset_name, u.full_name
     FROM asset_allocations aa JOIN assets a ON a.id=aa.asset_id
     JOIN users u ON u.id=aa.user_id
     WHERE aa.actual_return_date IS NOT NULL ORDER BY aa.actual_return_date DESC LIMIT 100'
)->fetchAll();
$page_title = 'Asset Allocations & Returns';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title">Allocations &amp; Returns</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_requests.php">Review Requests</a></div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="card"><h3><?= $approvedRequest ? 'Allocate for approved request' : 'Direct allocation' ?></h3>
    <?php if ($requestId && !$approvedRequest): ?><div class="alert alert-warning">That request is not available for allocation.</div><?php endif; ?>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="allocate">
        <input type="hidden" name="request_id" value="<?= (int)($approvedRequest['id'] ?? 0) ?>">
        <?php if ($approvedRequest): ?><p>Approved for <?= htmlspecialchars($approvedRequest['full_name']) ?>: <?= nl2br(htmlspecialchars($approvedRequest['reason'])) ?></p><?php endif; ?>
        <div class="two-col-grid">
            <label class="form-group">User<select class="form-control" name="user_id" required>
                <option value="">Select user</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($approvedRequest['user_id'] ?? 0)===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['full_name'] . ' (' . $u['username'] . ')') ?></option><?php endforeach; ?>
            </select></label>
            <label class="form-group">Available asset<select class="form-control" name="asset_id" required>
                <option value="">Select asset</option><?php foreach ($availableAssets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label class="form-group">Expected return date<input class="form-control" type="date" name="expected_return_date"></label>
            <label class="form-group">Allocation notes<textarea class="form-control" name="allocation_notes" maxlength="4000"></textarea></label>
        </div><button class="btn btn-primary" type="submit">Allocate Asset</button>
    </form>
</section>

<section class="card"><h3>Active allocations</h3>
    <?php if (!$active): ?><p class="empty-state">No active allocations.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Asset</th><th>User</th><th>Allocated</th><th>Return by</th><th>Return request</th><th>Process return</th></tr></thead>
        <tbody><?php foreach ($active as $a): ?><tr>
            <td><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['asset_name']) ?></td>
            <td><?= htmlspecialchars($a['full_name'] . ' (' . $a['username'] . ')') ?></td>
            <td><?= htmlspecialchars($a['allocation_date']) ?></td><td><?= htmlspecialchars($a['expected_return_date'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['return_request_status'] ? asset_status_label($a['return_request_status']) : '—') ?></td>
            <td><form method="post" onsubmit="return confirm('Record physical return of this asset?')">
                <?php csrf_field(); ?><input type="hidden" name="action" value="return"><input type="hidden" name="allocation_id" value="<?= (int)$a['id'] ?>">
                <select class="form-control" name="return_condition" required><?php foreach (['new','good','fair','poor','damaged'] as $c): ?><option value="<?= $c ?>"><?= htmlspecialchars(ucfirst($c)) ?></option><?php endforeach; ?></select>
                <input class="form-control" name="return_notes" maxlength="4000" placeholder="Inspection notes">
                <button class="btn btn-sm btn-primary" type="submit">Process Return</button>
            </form></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<section class="card"><h3>Allocation history</h3>
    <?php if (!$history): ?><p class="empty-state">No completed allocations.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Asset</th><th>User</th><th>Allocated</th><th>Returned</th><th>Condition</th><th>Notes</th></tr></thead><tbody>
        <?php foreach ($history as $a): ?><tr><td><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['asset_name']) ?></td><td><?= htmlspecialchars($a['full_name']) ?></td><td><?= htmlspecialchars($a['allocation_date']) ?></td><td><?= htmlspecialchars($a['actual_return_date']) ?></td><td><?= htmlspecialchars($a['return_condition'] ?? '—') ?></td><td><?= htmlspecialchars($a['return_notes'] ?? '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
