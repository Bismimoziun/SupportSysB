<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$userId = (int)$_SESSION['user_id'];
$msg = '';
$error = '';
$edit = null;
$statuses = ['available', 'retired', 'disposed'];
$conditions = ['new', 'good', 'fair', 'poor', 'damaged'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_asset') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $name = asset_post_string('name');
        $categoryRaw = asset_post_string('category_id');
        $locationRaw = asset_post_string('location_id');
        $categoryId = $categoryRaw === '' ? null : filter_var($categoryRaw, FILTER_VALIDATE_INT);
        $locationId = $locationRaw === '' ? null : filter_var($locationRaw, FILTER_VALIDATE_INT);
        $serial = asset_post_string('serial_number');
        $purchaseDate = asset_post_string('purchase_date');
        $warrantyStart = asset_post_string('warranty_start');
        $warrantyExpiry = asset_post_string('warranty_expiry');
        $costText = asset_post_string('purchase_cost');
        $status = asset_post_string('status') ?: 'available';
        $condition = asset_post_string('condition_label') ?: 'good';
        $cost = $costText === '' ? null : filter_var($costText, FILTER_VALIDATE_FLOAT);

        $categoryValid = $categoryId === null;
        if ($categoryId !== null && $categoryId !== false && $categoryId > 0) {
            $checkCategory = $pdo->prepare('SELECT id FROM asset_categories WHERE id=?');
            $checkCategory->execute([$categoryId]);
            $categoryValid = (bool)$checkCategory->fetchColumn();
        }
        $locationValid = $locationId === null;
        if ($locationId !== null && $locationId !== false && $locationId > 0) {
            $checkLocation = $pdo->prepare('SELECT id FROM asset_locations WHERE id=?');
            $checkLocation->execute([$locationId]);
            $locationValid = (bool)$checkLocation->fetchColumn();
        }
        if ($name === '' || strlen($name) > 160 || !$categoryValid || !$locationValid ||
            !in_array($status, $statuses, true) ||
            !in_array($condition, $conditions, true) ||
            !asset_valid_date($purchaseDate) || !asset_valid_date($warrantyStart) ||
            !asset_valid_date($warrantyExpiry) || ($costText !== '' && ($cost === false || $cost < 0)) ||
            ($warrantyStart && $warrantyExpiry && $warrantyExpiry < $warrantyStart)) {
            $error = 'Check the required name, status, condition, dates, and non-negative purchase cost.';
        } elseif ($id > 0) {
            try {
            $stmt = $pdo->prepare(
                'UPDATE assets SET name=?, category_id=?, brand=?, model=?, serial_number=?,
                    description=?, department=?, location_id=?, vendor=?, purchase_date=?,
                        purchase_cost=?, warranty_start=?, warranty_expiry=?, condition_label=?
                 WHERE id=?'
            );
            $stmt->execute([
                $name, $categoryId, asset_post_string('brand') ?: null,
                asset_post_string('model') ?: null, $serial ?: null,
                asset_post_string('description') ?: null, asset_post_string('department') ?: null,
                $locationId, asset_post_string('vendor') ?: null, $purchaseDate ?: null,
                $cost, $warrantyStart ?: null, $warrantyExpiry ?: null, $condition, $id
            ]);
            asset_log($pdo, $id, $userId, 'updated', 'Asset details updated.');
            $msg = 'Asset updated.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'The serial number is already assigned to another asset.';
                } else {
                    throw $e;
                }
            }
        } else {
            try {
                $pdo->beginTransaction();
                $tempTag = 'TEMP-' . bin2hex(random_bytes(12));
                $stmt = $pdo->prepare(
                    'INSERT INTO assets
                        (asset_tag, name, category_id, brand, model, serial_number, description,
                         department, location_id, vendor, purchase_date, purchase_cost,
                         warranty_start, warranty_expiry, status, condition_label, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $tempTag, $name, $categoryId, asset_post_string('brand') ?: null,
                    asset_post_string('model') ?: null, $serial ?: null,
                    asset_post_string('description') ?: null, asset_post_string('department') ?: null,
                    $locationId, asset_post_string('vendor') ?: null, $purchaseDate ?: null,
                    $cost, $warrantyStart ?: null, $warrantyExpiry ?: null, $status, $condition, $userId
                ]);
                $newId = (int)$pdo->lastInsertId();
                $assetTag = 'GG-' . date('Y') . '-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE assets SET asset_tag=? WHERE id=?')->execute([$assetTag, $newId]);
                asset_log($pdo, $newId, $userId, 'created', 'Asset added to inventory.');
                $pdo->commit();
                $msg = 'Asset created with tag ' . $assetTag . '.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    $error = 'The serial number is already assigned to another asset.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'save_category' && is_sysadmin()) {
        $name = asset_post_string('name');
        if ($name === '' || strlen($name) > 100) {
            $error = 'Category name is required and must be at most 100 characters.';
        } else {
            try {
                $pdo->prepare('INSERT INTO asset_categories (name, created_by) VALUES (?, ?)')
                    ->execute([$name, $userId]);
                $msg = 'Category added.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'That category already exists.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif ($action === 'save_location' && is_sysadmin()) {
        $name = asset_post_string('name');
        $department = asset_post_string('department');
        if ($name === '' || strlen($name) > 120 || strlen($department) > 120) {
            $error = 'Location name is required; names and departments must be at most 120 characters.';
        } else {
            try {
                $pdo->prepare('INSERT INTO asset_locations (name, department, created_by) VALUES (?, ?, ?)')
                    ->execute([$name, $department ?: null, $userId]);
                $msg = 'Location added.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'That location and department already exist.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif (in_array($action, ['toggle_category', 'toggle_location'], true) && is_sysadmin()) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $table = $action === 'toggle_category' ? 'asset_categories' : 'asset_locations';
        $stmt = $pdo->prepare("UPDATE $table SET is_active=NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        $msg = 'Asset configuration updated.';
    } elseif ($action === 'change_lifecycle') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        $target = $_POST['target_status'] ?? '';
        if (!$id || !in_array($target, ['retired','disposed'], true)) {
            $error = 'Choose a valid lifecycle change.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT status FROM assets WHERE id=? FOR UPDATE');
                $stmt->execute([$id]);
                $current = $stmt->fetchColumn();
                if (!$current || !in_array($current, ['available','retired'], true) ||
                    ($target === 'retired' && $current !== 'available')) {
                    throw new RuntimeException('This asset cannot make that lifecycle transition.');
                }
                $check = $pdo->prepare(
                    'SELECT
                       (SELECT COUNT(*) FROM asset_allocations WHERE asset_id=? AND actual_return_date IS NULL) +
                       (SELECT COUNT(*) FROM asset_maintenance WHERE asset_id=? AND status IN ("open","in_progress"))'
                );
                $check->execute([$id, $id]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('Return the asset and clear active maintenance before retiring or disposing it.');
                }
                $pdo->prepare('UPDATE assets SET status=? WHERE id=?')->execute([$target, $id]);
                asset_log($pdo, $id, $userId, $target, 'Lifecycle status changed to ' . $target . '.');
                $pdo->commit();
                $msg = 'Asset status changed to ' . asset_status_label($target) . '.';
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

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
    $stmt = $pdo->prepare('SELECT * FROM assets WHERE id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterCategory = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$filterLocation = filter_input(INPUT_GET, 'location', FILTER_VALIDATE_INT) ?: 0;
$allowedSort = ['asset_tag', 'name', 'status', 'created_at'];
$sort = $_GET['sort'] ?? 'created_at';
$sort = in_array($sort, $allowedSort, true) ? $sort : 'created_at';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 25;
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(a.asset_tag LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if (in_array($filterStatus, ['available','allocated','under_maintenance','retired','disposed'], true)) {
    $where[] = 'a.status=?';
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = 'a.category_id=?';
    $params[] = $filterCategory;
}
if ($filterLocation) {
    $where[] = 'a.location_id=?';
    $params[] = $filterLocation;
}
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM assets a WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $pages);
$stmt = $pdo->prepare(
    "SELECT a.*, ac.name AS category_name, al.name AS location_name,
            u.full_name AS allocated_to
     FROM assets a
     LEFT JOIN asset_categories ac ON ac.id=a.category_id
     LEFT JOIN asset_locations al ON al.id=a.location_id
     LEFT JOIN asset_allocations aa ON aa.asset_id=a.id AND aa.actual_return_date IS NULL
     LEFT JOIN users u ON u.id=aa.user_id
     WHERE $whereSql ORDER BY a.$sort DESC LIMIT $pageSize OFFSET " . (($page - 1) * $pageSize)
);
$stmt->execute($params);
$assets = $stmt->fetchAll();
$categoryStmt = $pdo->prepare(
    'SELECT id,name FROM asset_categories WHERE is_active=1 OR id=? ORDER BY name'
);
$categoryStmt->execute([(int)($edit['category_id'] ?? 0)]);
$categories = $categoryStmt->fetchAll();
$locationStmt = $pdo->prepare(
    'SELECT id,name,department FROM asset_locations WHERE is_active=1 OR id=? ORDER BY name'
);
$locationStmt->execute([(int)($edit['location_id'] ?? 0)]);
$locations = $locationStmt->fetchAll();
$allCategories = $pdo->query('SELECT id,name,is_active FROM asset_categories ORDER BY name')->fetchAll();
$allLocations = $pdo->query('SELECT id,name,department,is_active FROM asset_locations ORDER BY name')->fetchAll();

$page_title = 'Asset Inventory';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-row"><h2 class="section-title">Asset Inventory</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_reports.php">Asset Dashboard &amp; Reports</a></div>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <h3><?= $edit ? 'Edit Asset' : 'Add Asset' ?></h3>
    <form method="post">
        <?php csrf_field(); ?><input type="hidden" name="action" value="save_asset">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="two-col-grid">
            <label class="form-group">Asset name *
                <input class="form-control" name="name" maxlength="160" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>"></label>
            <label class="form-group">Category
                <select class="form-control" name="category_id"><option value="">— Select —</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['category_id'] ?? 0)===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select></label>
            <label class="form-group">Brand<input class="form-control" name="brand" maxlength="100" value="<?= htmlspecialchars($edit['brand'] ?? '') ?>"></label>
            <label class="form-group">Model<input class="form-control" name="model" maxlength="120" value="<?= htmlspecialchars($edit['model'] ?? '') ?>"></label>
            <label class="form-group">Serial number<input class="form-control" name="serial_number" maxlength="160" value="<?= htmlspecialchars($edit['serial_number'] ?? '') ?>"></label>
            <label class="form-group">Department<input class="form-control" name="department" maxlength="120" value="<?= htmlspecialchars($edit['department'] ?? '') ?>"></label>
            <label class="form-group">Location
                <select class="form-control" name="location_id"><option value="">— Select —</option>
                    <?php foreach ($locations as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)($edit['location_id'] ?? 0)===(int)$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name'] . ($l['department'] ? ' — ' . $l['department'] : '')) ?></option><?php endforeach; ?>
                </select></label>
            <label class="form-group">Vendor<input class="form-control" name="vendor" maxlength="160" value="<?= htmlspecialchars($edit['vendor'] ?? '') ?>"></label>
            <label class="form-group">Purchase date<input class="form-control" type="date" name="purchase_date" value="<?= htmlspecialchars($edit['purchase_date'] ?? '') ?>"></label>
            <label class="form-group">Purchase cost<input class="form-control" type="number" step="0.01" min="0" name="purchase_cost" value="<?= htmlspecialchars((string)($edit['purchase_cost'] ?? '')) ?>"></label>
            <label class="form-group">Warranty starts<input class="form-control" type="date" name="warranty_start" value="<?= htmlspecialchars($edit['warranty_start'] ?? '') ?>"></label>
            <label class="form-group">Warranty expires<input class="form-control" type="date" name="warranty_expiry" value="<?= htmlspecialchars($edit['warranty_expiry'] ?? '') ?>"></label>
            <label class="form-group">Condition<select class="form-control" name="condition_label"><?php foreach ($conditions as $c): ?><option value="<?= $c ?>" <?= ($edit['condition_label'] ?? 'good')===$c?'selected':'' ?>><?= htmlspecialchars(ucfirst($c)) ?></option><?php endforeach; ?></select></label>
            <?php if (!$edit): ?><label class="form-group">Lifecycle status<select class="form-control" name="status"><?php foreach ($statuses as $s): ?><option value="<?= $s ?>"><?= htmlspecialchars(asset_status_label($s)) ?></option><?php endforeach; ?></select></label>
            <?php else: ?><div class="form-group"><span class="form-label">Lifecycle status</span><p><?= htmlspecialchars(asset_status_label($edit['status'])) ?> (updated through allocation and maintenance workflows)</p></div><?php endif; ?>
        </div>
        <label class="form-group">Description<textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea></label>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save Changes' : 'Add Asset' ?></button>
        <?php if ($edit): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/assets.php">Cancel edit</a><?php endif; ?>
    </form>
</div>

<?php if (is_sysadmin()): ?><div class="two-col-grid">
    <section class="card"><h3>Configure categories</h3>
        <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="save_category">
            <input class="form-control" name="name" maxlength="100" required placeholder="e.g. Laptop">
            <button class="btn btn-secondary" type="submit">Add Category</button>
        </form>
        <?php foreach ($allCategories as $c): ?><form method="post" class="inline-form">
            <?php csrf_field(); ?><input type="hidden" name="action" value="toggle_category"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <span><?= htmlspecialchars($c['name']) ?></span><button class="btn btn-sm btn-secondary" type="submit"><?= $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form><?php endforeach; ?>
    </section>
    <section class="card"><h3>Configure locations</h3>
        <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="save_location">
            <input class="form-control" name="name" maxlength="120" required placeholder="Location">
            <input class="form-control" name="department" maxlength="120" placeholder="Department (optional)">
            <button class="btn btn-secondary" type="submit">Add Location</button>
        </form>
        <?php foreach ($allLocations as $l): ?><form method="post" class="inline-form">
            <?php csrf_field(); ?><input type="hidden" name="action" value="toggle_location"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <span><?= htmlspecialchars($l['name'] . ($l['department'] ? ' — ' . $l['department'] : '')) ?></span><button class="btn btn-sm btn-secondary" type="submit"><?= $l['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form><?php endforeach; ?>
    </section>
</div><?php endif; ?>

<section class="card">
    <h3>Inventory (<?= $total ?>)</h3>
    <form method="get" class="filter-bar">
        <input class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tag, name, serial, brand or model">
        <select class="form-control" name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filterCategory===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="status"><option value="">All statuses</option><?php foreach (['available','allocated','under_maintenance','retired','disposed'] as $s): ?><option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= htmlspecialchars(asset_status_label($s)) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="location"><option value="">All locations</option><?php foreach ($locations as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $filterLocation===(int)$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="sort"><?php foreach ($allowedSort as $s): ?><option value="<?= $s ?>" <?= $sort===$s?'selected':'' ?>>Sort: <?= htmlspecialchars(asset_status_label($s)) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>
    <?php if (!$assets): ?><p class="empty-state">No assets match your filters.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Tag</th><th>Name</th><th>Category</th><th>Status</th><th>Location</th><th>Allocated to</th><th></th></tr></thead>
        <tbody><?php foreach ($assets as $a): ?><tr>
            <td><?= htmlspecialchars($a['asset_tag']) ?></td>
            <td><?= htmlspecialchars($a['name']) ?></td>
            <td><?= htmlspecialchars($a['category_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(asset_status_label($a['status'])) ?></td>
            <td><?= htmlspecialchars($a['location_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['allocated_to'] ?? '—') ?></td>
            <td><a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>/asset_detail.php?id=<?= (int)$a['id'] ?>">Details</a>
                <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$a['id'] ?>">Edit</a>
                <?php if ($a['status'] === 'available' || $a['status'] === 'retired'): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Change this asset lifecycle status?')">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="change_lifecycle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="target_status" value="<?= $a['status'] === 'available' ? 'retired' : 'disposed' ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><?= $a['status'] === 'available' ? 'Retire' : 'Dispose' ?></button>
                    </form>
                <?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <nav class="filter-chips" aria-label="Inventory pages"><?php for ($p=1;$p<=$pages;$p++): ?><a class="chip <?= $p===$page?'active':'' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page'=>$p]))) ?>"><?= $p ?></a><?php endfor; ?></nav>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
