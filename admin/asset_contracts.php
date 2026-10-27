<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$adminId = (int)$_SESSION['user_id'];
$msg = '';
$error = '';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editStmt = $pdo->prepare('SELECT * FROM asset_contracts WHERE id=?');
$editStmt->execute([$editId]);
$edit = $editStmt->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save_contract') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $assetId = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT) ?: 0;
        $type = $_POST['contract_type'] ?? '';
        $provider = asset_post_string('provider');
        $reference = asset_post_string('reference_number');
        $start = asset_post_string('start_date');
        $expiry = asset_post_string('expiry_date');
        $renewal = asset_post_string('renewal_date');
        $costText = asset_post_string('cost');
        $cost = $costText === '' ? null : filter_var($costText, FILTER_VALIDATE_FLOAT);
        $status = $_POST['status'] ?? 'active';
        if (!$assetId || !in_array($type, ['warranty','amc'], true) || $provider === '' ||
            strlen($provider) > 160 || strlen($reference) > 120 ||
            !asset_valid_date($start) || !asset_valid_date($expiry) || !asset_valid_date($renewal) ||
            $start === '' || $expiry === '' || $expiry < $start ||
            ($renewal !== '' && $renewal < $start) ||
            ($costText !== '' && ($cost === false || $cost < 0)) ||
            !in_array($status, ['active','expired','cancelled'], true)) {
            $error = 'Check the asset, type, provider, contract dates, status, and non-negative cost.';
        } else {
            $values = [
                $assetId, $type, $provider, $reference ?: null, $start, $expiry,
                $renewal ?: null, $cost, $status, asset_post_string('remarks') ?: null
            ];
            if ($id) {
                $pdo->prepare(
                    'UPDATE asset_contracts SET asset_id=?, contract_type=?, provider=?, reference_number=?,
                     start_date=?, expiry_date=?, renewal_date=?, cost=?, status=?, remarks=? WHERE id=?'
                )->execute(array_merge($values, [$id]));
                asset_log($pdo, $assetId, $adminId, 'contract_updated', 'Contract #' . $id . ' updated.');
                $msg = 'Contract updated.';
            } else {
                $pdo->prepare(
                    'INSERT INTO asset_contracts
                     (asset_id, contract_type, provider, reference_number, start_date, expiry_date,
                      renewal_date, cost, status, remarks, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute(array_merge($values, [$adminId]));
                asset_log($pdo, $assetId, $adminId, 'contract_created', ucfirst($type) . ' contract added.');
                $msg = 'Contract added.';
            }
        }
    } elseif ($action === 'save_warning_days' && is_sysadmin()) {
        $days = filter_input(INPUT_POST, 'warning_days', FILTER_VALIDATE_INT);
        if ($days === false || $days === null || $days < 1 || $days > 365) {
            $error = 'Warning period must be between 1 and 365 days.';
        } else {
            $pdo->prepare(
                "INSERT INTO asset_settings (setting_key, setting_value, updated_by)
                 VALUES ('contract_warning_days', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)"
            )->execute([(string)$days, $adminId]);
            $msg = 'Expiry warning period saved.';
        }
    }
}

$assets = $pdo->query("SELECT id,asset_tag,name FROM assets WHERE status<>'disposed' ORDER BY asset_tag")->fetchAll();
$contracts = $pdo->query(
    'SELECT c.*, a.asset_tag, a.name AS asset_name
     FROM asset_contracts c JOIN assets a ON a.id=c.asset_id
     ORDER BY c.expiry_date ASC LIMIT 300'
)->fetchAll();
$warningDays = (int)$pdo->query(
    "SELECT setting_value FROM asset_settings WHERE setting_key='contract_warning_days'"
)->fetchColumn();
$page_title = 'Warranty & AMC';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title">Warranty &amp; AMC Tracking</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_reports.php">Asset Dashboard</a></div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (is_sysadmin()): ?><section class="card"><h3>Expiry warnings</h3>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="save_warning_days">
        <label class="form-label">Show contracts expiring within (days)</label>
        <input class="form-control" type="number" min="1" max="365" name="warning_days" value="<?= $warningDays ?>" required>
        <button class="btn btn-secondary" type="submit">Save Warning Period</button></form>
</section><?php endif; ?>
<section class="card"><h3><?= $edit ? 'Edit Contract' : 'Add Warranty or AMC' ?></h3>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="save_contract"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="two-col-grid">
            <label class="form-group">Asset<select class="form-control" name="asset_id" required><option value="">Select asset</option><?php foreach ($assets as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)($edit['asset_id'] ?? 0)===(int)$a['id']?'selected':'' ?>><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['name']) ?></option><?php endforeach; ?></select></label>
            <label class="form-group">Type<select class="form-control" name="contract_type"><?php foreach (['warranty','amc'] as $type): ?><option value="<?= $type ?>" <?= ($edit['contract_type'] ?? 'warranty')===$type?'selected':'' ?>><?= strtoupper($type) ?></option><?php endforeach; ?></select></label>
            <label class="form-group">Provider<input class="form-control" name="provider" maxlength="160" required value="<?= htmlspecialchars($edit['provider'] ?? '') ?>"></label>
            <label class="form-group">Contract/reference number<input class="form-control" name="reference_number" maxlength="120" value="<?= htmlspecialchars($edit['reference_number'] ?? '') ?>"></label>
            <label class="form-group">Start date<input class="form-control" type="date" name="start_date" required value="<?= htmlspecialchars($edit['start_date'] ?? '') ?>"></label>
            <label class="form-group">Expiry date<input class="form-control" type="date" name="expiry_date" required value="<?= htmlspecialchars($edit['expiry_date'] ?? '') ?>"></label>
            <label class="form-group">Renewal date<input class="form-control" type="date" name="renewal_date" value="<?= htmlspecialchars($edit['renewal_date'] ?? '') ?>"></label>
            <label class="form-group">Cost<input class="form-control" type="number" min="0" step="0.01" name="cost" value="<?= htmlspecialchars((string)($edit['cost'] ?? '')) ?>"></label>
            <label class="form-group">Status<select class="form-control" name="status"><?php foreach (['active','expired','cancelled'] as $s): ?><option value="<?= $s ?>" <?= ($edit['status'] ?? 'active')===$s?'selected':'' ?>><?= htmlspecialchars(ucfirst($s)) ?></option><?php endforeach; ?></select></label>
        </div><label class="form-group">Remarks<textarea class="form-control" name="remarks" maxlength="4000"><?= htmlspecialchars($edit['remarks'] ?? '') ?></textarea></label>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save Contract' : 'Add Contract' ?></button>
        <?php if ($edit): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_contracts.php">Cancel</a><?php endif; ?>
    </form>
</section>
<section class="card"><h3>Contracts</h3>
    <?php if (!$contracts): ?><p class="empty-state">No warranty or AMC records.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Asset</th><th>Type</th><th>Provider</th><th>Start</th><th>Expiry</th><th>Renewal</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($contracts as $c): ?><tr><td><?= htmlspecialchars($c['asset_tag'] . ' — ' . $c['asset_name']) ?></td><td><?= htmlspecialchars(strtoupper($c['contract_type'])) ?></td><td><?= htmlspecialchars($c['provider']) ?></td><td><?= htmlspecialchars($c['start_date']) ?></td><td><?= htmlspecialchars($c['expiry_date']) ?></td><td><?= htmlspecialchars($c['renewal_date'] ?? '—') ?></td><td><?= htmlspecialchars(asset_status_label($c['status'])) ?></td><td><a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$c['id'] ?>">Edit</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
