<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$adminId = (int)$_SESSION['user_id'];
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $assetId = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT) ?: 0;
        $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: null;
        $type = asset_post_string('maintenance_type');
        $issue = asset_post_string('reported_issue');
        $start = asset_post_string('start_date');
        $costText = asset_post_string('repair_cost');
        $cost = $costText === '' ? null : filter_var($costText, FILTER_VALIDATE_FLOAT);
        if (!$assetId || $type === '' || strlen($type) > 100 || $issue === '' ||
            !asset_valid_date($start) || $start === '' ||
            ($costText !== '' && ($cost === false || $cost < 0))) {
            $error = 'Provide an asset, maintenance type, issue, valid start date, and non-negative cost.';
        } else {
            $pdo->beginTransaction();
            try {
                $assetStmt = $pdo->prepare('SELECT status FROM assets WHERE id=? FOR UPDATE');
                $assetStmt->execute([$assetId]);
                $assetStatus = $assetStmt->fetchColumn();
                if (!$assetStatus) {
                    throw new RuntimeException('Asset not found.');
                }
                if (!in_array($assetStatus, ['available','under_maintenance'], true)) {
                    throw new RuntimeException('Only available assets can be placed under maintenance.');
                }
                if (!is_sysadmin() && $ticketId) {
                    $ticketStmt = $pdo->prepare('SELECT id FROM tickets WHERE id=? AND current_admin=?');
                    $ticketStmt->execute([$ticketId, $adminId]);
                    if (!$ticketStmt->fetchColumn()) {
                        throw new RuntimeException('You can only link a ticket assigned to you.');
                    }
                }
                $pdo->prepare(
                    "INSERT INTO asset_maintenance
                        (asset_id, ticket_id, maintenance_type, reported_issue, start_date,
                         technician, repair_cost, work_performed, status, remarks, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)"
                )->execute([
                    $assetId, $ticketId ?: null, $type, $issue, $start,
                    asset_post_string('technician') ?: null, $cost,
                    asset_post_string('work_performed') ?: null,
                    asset_post_string('remarks') ?: null, $adminId
                ]);
                $pdo->prepare("UPDATE assets SET status='under_maintenance' WHERE id=?")->execute([$assetId]);
                asset_log($pdo, $assetId, $adminId, 'maintenance_started', $type . ': ' . $issue);
                $pdo->commit();
                $msg = 'Maintenance record created; the asset is unavailable until cleared.';
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
    } elseif ($action === 'update') {
        $id = filter_input(INPUT_POST, 'maintenance_id', FILTER_VALIDATE_INT) ?: 0;
        $status = $_POST['status'] ?? '';
        $completion = asset_post_string('completion_date');
        $work = asset_post_string('work_performed');
        $remarks = asset_post_string('remarks');
        if (!$id || !in_array($status, ['open','in_progress','completed','cancelled'], true) ||
            !asset_valid_date($completion) || strlen($work) > 4000 || strlen($remarks) > 4000 ||
            ($status === 'completed' && $completion === '')) {
            $error = 'Choose a valid maintenance status and provide a completion date when completing work.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT asset_id FROM asset_maintenance WHERE id=? FOR UPDATE');
                $stmt->execute([$id]);
                $assetId = $stmt->fetchColumn();
                if (!$assetId) {
                    throw new RuntimeException('Maintenance record not found.');
                }
                $pdo->prepare(
                    'UPDATE asset_maintenance SET status=?, completion_date=?, work_performed=?, remarks=? WHERE id=?'
                )->execute([$status, $status === 'completed' ? $completion : null, $work ?: null, $remarks ?: null, $id]);
                if ($status === 'completed' || $status === 'cancelled') {
                    $open = $pdo->prepare(
                        "SELECT COUNT(*) FROM asset_maintenance WHERE asset_id=? AND id<>? AND status IN ('open','in_progress')"
                    );
                    $open->execute([$assetId, $id]);
                    $activeAllocation = $pdo->prepare(
                        'SELECT COUNT(*) FROM asset_allocations WHERE asset_id=? AND actual_return_date IS NULL'
                    );
                    $activeAllocation->execute([$assetId]);
                    if ((int)$open->fetchColumn() === 0 && (int)$activeAllocation->fetchColumn() === 0) {
                        $pdo->prepare("UPDATE assets SET status='available' WHERE id=? AND status='under_maintenance'")
                            ->execute([$assetId]);
                    }
                } else {
                    $pdo->prepare("UPDATE assets SET status='under_maintenance' WHERE id=?")->execute([$assetId]);
                }
                asset_log($pdo, (int)$assetId, $adminId, 'maintenance_' . $status, $remarks ?: null);
                $pdo->commit();
                $msg = 'Maintenance record updated.';
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

$assets = $pdo->query(
    "SELECT id,asset_tag,name FROM assets WHERE status IN ('available','under_maintenance') ORDER BY asset_tag"
)->fetchAll();
$ticketSql = 'SELECT id,title FROM tickets';
$ticketParams = [];
if (!is_sysadmin()) {
    $ticketSql .= ' WHERE current_admin=?';
    $ticketParams[] = $adminId;
}
$ticketSql .= ' ORDER BY created_at DESC LIMIT 200';
$ticketStmt = $pdo->prepare($ticketSql);
$ticketStmt->execute($ticketParams);
$tickets = $ticketStmt->fetchAll();
$records = $pdo->query(
    'SELECT m.*, a.asset_tag, a.name AS asset_name
     FROM asset_maintenance m JOIN assets a ON a.id=m.asset_id
     ORDER BY m.created_at DESC LIMIT 200'
)->fetchAll();
$page_title = 'Asset Maintenance';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title">Asset Maintenance</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/assets.php">Inventory</a></div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="card"><h3>Record maintenance</h3>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="create">
        <div class="two-col-grid">
            <label class="form-group">Asset<select class="form-control" name="asset_id" required><option value="">Select asset</option><?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['name']) ?></option><?php endforeach; ?></select></label>
            <label class="form-group">Related ticket (optional)<select class="form-control" name="ticket_id"><option value="">None</option><?php foreach ($tickets as $t): ?><option value="<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> — <?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?></select></label>
            <label class="form-group">Maintenance type<input class="form-control" name="maintenance_type" maxlength="100" required placeholder="Repair, inspection, upgrade"></label>
            <label class="form-group">Start date<input class="form-control" type="date" name="start_date" required></label>
            <label class="form-group">Technician<input class="form-control" name="technician" maxlength="160"></label>
            <label class="form-group">Repair cost<input class="form-control" type="number" step="0.01" min="0" name="repair_cost"></label>
        </div>
        <label class="form-group">Reported issue<textarea class="form-control" name="reported_issue" required maxlength="4000"></textarea></label>
        <label class="form-group">Work performed<textarea class="form-control" name="work_performed" maxlength="4000"></textarea></label>
        <label class="form-group">Remarks<textarea class="form-control" name="remarks" maxlength="4000"></textarea></label>
        <button class="btn btn-primary" type="submit">Create Record</button>
    </form>
</section>
<section class="card"><h3>Maintenance history</h3>
    <?php if (!$records): ?><p class="empty-state">No maintenance records.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Asset</th><th>Type / issue</th><th>Dates</th><th>Ticket</th><th>Status</th><th>Work &amp; update</th></tr></thead>
        <tbody><?php foreach ($records as $m): ?><tr>
            <td><a href="<?= BASE_URL ?>/asset_detail.php?id=<?= (int)$m['asset_id'] ?>"><?= htmlspecialchars($m['asset_tag'] . ' — ' . $m['asset_name']) ?></a></td>
            <td><?= htmlspecialchars($m['maintenance_type']) ?><br><?= nl2br(htmlspecialchars($m['reported_issue'])) ?></td>
            <td><?= htmlspecialchars($m['start_date'] . ($m['completion_date'] ? ' — ' . $m['completion_date'] : '')) ?></td>
            <td><?= $m['ticket_id'] ? '<a href="' . BASE_URL . '/ticket_detail.php?id=' . (int)$m['ticket_id'] . '">#' . (int)$m['ticket_id'] . '</a>' : '—' ?></td>
            <td><?= htmlspecialchars(asset_status_label($m['status'])) ?></td>
            <td><form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="maintenance_id" value="<?= (int)$m['id'] ?>">
                <select class="form-control" name="status"><?php foreach (['open','in_progress','completed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $m['status']===$s?'selected':'' ?>><?= htmlspecialchars(asset_status_label($s)) ?></option><?php endforeach; ?></select>
                <label>Completion date<input class="form-control" type="date" name="completion_date" value="<?= htmlspecialchars($m['completion_date'] ?? '') ?>"></label>
                <textarea class="form-control" name="work_performed" maxlength="4000" placeholder="Work performed"><?= htmlspecialchars($m['work_performed'] ?? '') ?></textarea>
                <textarea class="form-control" name="remarks" maxlength="4000" placeholder="Remarks"><?= htmlspecialchars($m['remarks'] ?? '') ?></textarea>
                <button class="btn btn-sm btn-primary" type="submit">Save</button>
            </form></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
