<?php
require_once __DIR__ . '/../includes/asset_helpers.php';
guard_require_login();
asset_require_admin();

$warningDays = (int)$pdo->query(
    "SELECT setting_value FROM asset_settings WHERE setting_key='contract_warning_days'"
)->fetchColumn();
$counts = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status='available') AS available,
        SUM(status='allocated') AS allocated,
        SUM(status='under_maintenance') AS under_maintenance,
        SUM(status IN ('retired','disposed')) AS retired_disposed
     FROM assets"
)->fetch();
$pendingRequests = (int)$pdo->query(
    "SELECT COUNT(*) FROM asset_requests WHERE status='pending'"
)->fetchColumn();
$expiring = $pdo->prepare(
    "SELECT a.id AS asset_id, a.asset_tag, a.name, 'warranty' AS contract_type,
            COALESCE(a.vendor, 'Asset warranty') AS provider, a.warranty_expiry AS expiry_date
     FROM assets a
     WHERE a.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
     UNION ALL
     SELECT a.id, a.asset_tag, a.name, c.contract_type, c.provider, c.expiry_date
     FROM asset_contracts c JOIN assets a ON a.id=c.asset_id
     WHERE c.status='active' AND c.expiry_date BETWEEN CURDATE()
       AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
       AND NOT (c.contract_type='warranty' AND a.warranty_expiry IS NOT NULL)
     ORDER BY expiry_date ASC"
);
$expiring->execute([$warningDays, $warningDays]);
$expiringContracts = $expiring->fetchAll();
$expired = $pdo->query(
    "SELECT a.id AS asset_id, a.asset_tag, a.name, 'warranty' AS contract_type,
            COALESCE(a.vendor, 'Asset warranty') AS provider, a.warranty_expiry AS expiry_date
     FROM assets a WHERE a.warranty_expiry < CURDATE()
     UNION ALL
     SELECT a.id, a.asset_tag, a.name, c.contract_type, c.provider, c.expiry_date
     FROM asset_contracts c JOIN assets a ON a.id=c.asset_id
     WHERE c.status='active' AND c.expiry_date < CURDATE()
       AND NOT (c.contract_type='warranty' AND a.warranty_expiry IS NOT NULL)
     ORDER BY expiry_date DESC LIMIT 100"
)->fetchAll();
$recentAllocations = $pdo->query(
    'SELECT aa.*, a.asset_tag, a.name AS asset_name, u.full_name
     FROM asset_allocations aa JOIN assets a ON a.id=aa.asset_id
     JOIN users u ON u.id=aa.user_id ORDER BY aa.allocation_date DESC LIMIT 10'
)->fetchAll();
$recentRequests = $pdo->query(
    'SELECT ar.*, u.full_name FROM asset_requests ar JOIN users u ON u.id=ar.user_id
     WHERE ar.status="pending" ORDER BY ar.created_at DESC LIMIT 10'
)->fetchAll();
$page_title = 'Asset Dashboard & Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title">Asset Dashboard &amp; Reports</h2>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/assets.php">Inventory</a></div>
<div class="stats-grid stats-grid-6">
    <?php foreach ([
        ['📦', 'Total assets', $counts['total'] ?? 0, ''],
        ['✅', 'Available', $counts['available'] ?? 0, 'stat-success'],
        ['👤', 'Allocated', $counts['allocated'] ?? 0, 'stat-info'],
        ['🔧', 'Under maintenance', $counts['under_maintenance'] ?? 0, 'stat-warning'],
        ['⏳', 'Pending requests', $pendingRequests, 'stat-warning'],
        ['📥', 'Retired / disposed', $counts['retired_disposed'] ?? 0, '']
    ] as [$icon, $label, $number, $class]): ?>
        <div class="stat-card <?= $class ?>"><div class="stat-icon"><?= $icon ?></div><div class="stat-number"><?= (int)$number ?></div><div class="stat-label"><?= htmlspecialchars($label) ?></div></div>
    <?php endforeach; ?>
</div>
<section class="card"><h3>Expired warranties and AMCs</h3>
    <?php if (!$expired): ?><p class="empty-state">No expired contracts are marked active.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Asset</th><th>Contract</th><th>Provider</th><th>Expired</th></tr></thead><tbody>
        <?php foreach ($expired as $c): ?><tr><td><a href="<?= BASE_URL ?>/asset_detail.php?id=<?= (int)$c['asset_id'] ?>"><?= htmlspecialchars($c['asset_tag'] . ' — ' . $c['name']) ?></a></td><td><?= htmlspecialchars(strtoupper($c['contract_type'])) ?></td><td><?= htmlspecialchars($c['provider']) ?></td><td><?= htmlspecialchars($c['expiry_date']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<div class="quick-actions">
    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/asset_requests.php">Review Requests</a>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_allocations.php">Allocations &amp; Returns</a>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_maintenance.php">Maintenance</a>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/asset_contracts.php">Warranty &amp; AMC</a>
</div>
<div class="two-col-grid">
    <section class="card"><h3>Contracts expiring within <?= $warningDays ?> days</h3>
        <?php if (!$expiringContracts): ?><p class="empty-state">No contracts are expiring in this warning period.</p>
        <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Asset</th><th>Contract</th><th>Provider</th><th>Expiry</th></tr></thead><tbody>
            <?php foreach ($expiringContracts as $c): ?><tr><td><a href="<?= BASE_URL ?>/asset_detail.php?id=<?= (int)$c['asset_id'] ?>"><?= htmlspecialchars($c['asset_tag'] . ' — ' . $c['name']) ?></a></td><td><?= htmlspecialchars(strtoupper($c['contract_type'])) ?></td><td><?= htmlspecialchars($c['provider']) ?></td><td><?= htmlspecialchars($c['expiry_date']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <section class="card"><h3>Recent allocations</h3>
        <?php if (!$recentAllocations): ?><p class="empty-state">No allocations have been recorded.</p>
        <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Asset</th><th>User</th><th>Allocated</th><th>Returned</th></tr></thead><tbody>
            <?php foreach ($recentAllocations as $a): ?><tr><td><?= htmlspecialchars($a['asset_tag'] . ' — ' . $a['asset_name']) ?></td><td><?= htmlspecialchars($a['full_name']) ?></td><td><?= htmlspecialchars($a['allocation_date']) ?></td><td><?= htmlspecialchars($a['actual_return_date'] ?? 'Active') ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<section class="card"><h3>Recent requests</h3>
    <?php if (!$recentRequests): ?><p class="empty-state">No requests have been submitted.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>User</th><th>Type</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
        <?php foreach ($recentRequests as $r): ?><tr><td><?= htmlspecialchars($r['full_name']) ?></td><td><?= htmlspecialchars(ucfirst($r['request_type'])) ?></td><td><?= htmlspecialchars(asset_status_label($r['status'])) ?></td><td><?= htmlspecialchars($r['created_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
