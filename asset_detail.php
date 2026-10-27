<?php
require_once __DIR__ . '/includes/asset_helpers.php';
guard_require_login();

$assetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare(
    'SELECT a.*, ac.name AS category_name, al.name AS location_name
     FROM assets a LEFT JOIN asset_categories ac ON ac.id=a.category_id
     LEFT JOIN asset_locations al ON al.id=a.location_id WHERE a.id=?'
);
$stmt->execute([$assetId]);
$asset = $stmt->fetch();
if (!$asset) {
    http_response_code(404);
    exit('Asset not found.');
}

$isAdmin = is_admin();
$userId = (int)$_SESSION['user_id'];
$ticketSql = 'SELECT id FROM tickets WHERE asset_id=?';
$ticketParams = [$assetId];
if (!$isAdmin) {
    $ticketSql .= ' AND raised_by=?';
    $ticketParams[] = $userId;
} elseif (!is_sysadmin()) {
    $ticketSql .= ' AND current_admin=?';
    $ticketParams[] = $userId;
}
$ticketStmt = $pdo->prepare($ticketSql);
$ticketStmt->execute($ticketParams);
$visibleTicketIds = array_map('intval', $ticketStmt->fetchAll(PDO::FETCH_COLUMN));
$hasAllocation = asset_owned_by_user($pdo, $assetId, $userId);
if (!$isAdmin && !$hasAllocation && !$visibleTicketIds) {
    http_response_code(403);
    exit('You do not have permission to view this asset.');
}

$allocationSql = 'SELECT aa.*, u.full_name AS user_name, allocator.full_name AS allocator_name,
                         returner.full_name AS returner_name
                  FROM asset_allocations aa JOIN users u ON u.id=aa.user_id
                  JOIN users allocator ON allocator.id=aa.allocated_by
                  LEFT JOIN users returner ON returner.id=aa.returned_by
                  WHERE aa.asset_id=?';
$allocationParams = [$assetId];
if (!$isAdmin) {
    $allocationSql .= ' AND aa.user_id=?';
    $allocationParams[] = $userId;
}
$allocationSql .= ' ORDER BY aa.allocation_date DESC';
$allocationStmt = $pdo->prepare($allocationSql);
$allocationStmt->execute($allocationParams);
$allocations = $allocationStmt->fetchAll();

$maintenanceStmt = $pdo->prepare(
    'SELECT m.*, u.full_name AS creator_name FROM asset_maintenance m
     LEFT JOIN users u ON u.id=m.created_by WHERE m.asset_id=? ORDER BY m.start_date DESC'
);
$maintenanceStmt->execute([$assetId]);
$maintenance = $maintenanceStmt->fetchAll();

$contracts = [];
$activity = [];
if (is_sysadmin()) {
    $stmt = $pdo->prepare('SELECT * FROM asset_contracts WHERE asset_id=? ORDER BY expiry_date');
    $stmt->execute([$assetId]);
    $contracts = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT aa.*, u.full_name FROM asset_activity aa LEFT JOIN users u ON u.id=aa.actor_id
         WHERE aa.asset_id=? ORDER BY aa.created_at DESC LIMIT 100'
    );
    $stmt->execute([$assetId]);
    $activity = $stmt->fetchAll();
}

$relatedTickets = [];
if ($visibleTicketIds) {
    $placeholders = implode(',', array_fill(0, count($visibleTicketIds), '?'));
    $stmt = $pdo->prepare("SELECT id,title,status,created_at FROM tickets WHERE id IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute($visibleTicketIds);
    $relatedTickets = $stmt->fetchAll();
}
$page_title = $asset['asset_tag'] . ' — ' . $asset['name'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header-row"><h2 class="section-title"><?= htmlspecialchars($asset['asset_tag'] . ' — ' . $asset['name']) ?></h2>
    <?php if ($isAdmin): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/assets.php?edit=<?= $assetId ?>">Edit Asset</a><?php endif; ?></div>
<div class="two-col-grid">
    <section class="card"><h3>Asset information</h3><dl class="detail-list">
        <dt>Status</dt><dd><?= htmlspecialchars(asset_status_label($asset['status'])) ?></dd>
        <dt>Category</dt><dd><?= htmlspecialchars($asset['category_name'] ?? '—') ?></dd>
        <dt>Brand / model</dt><dd><?= htmlspecialchars(trim(($asset['brand'] ?? '') . ' ' . ($asset['model'] ?? '')) ?: '—') ?></dd>
        <dt>Serial number</dt><dd><?= htmlspecialchars($asset['serial_number'] ?? '—') ?></dd>
        <dt>Condition</dt><dd><?= htmlspecialchars(ucfirst($asset['condition_label'])) ?></dd>
        <dt>Department</dt><dd><?= htmlspecialchars($asset['department'] ?? '—') ?></dd>
        <dt>Location</dt><dd><?= htmlspecialchars($asset['location_name'] ?? '—') ?></dd>
        <?php if ($isAdmin): ?>
            <dt>Vendor</dt><dd><?= htmlspecialchars($asset['vendor'] ?? '—') ?></dd>
            <dt>Purchase date / cost</dt><dd><?= htmlspecialchars(($asset['purchase_date'] ?? '—') . ' / ' . ($asset['purchase_cost'] ?? '—')) ?></dd>
            <dt>Warranty dates</dt><dd><?= htmlspecialchars(($asset['warranty_start'] ?? '—') . ' — ' . ($asset['warranty_expiry'] ?? '—')) ?></dd>
        <?php endif; ?>
    </dl><?php if ($asset['description']): ?><p><?= nl2br(htmlspecialchars($asset['description'])) ?></p><?php endif; ?></section>
    <section class="card"><h3>Allocation history</h3>
        <?php if (!$allocations): ?><p class="empty-state">No allocation history.</p>
        <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>User</th><th>Allocated</th><th>Expected return</th><th>Returned</th><th>Condition</th></tr></thead><tbody>
            <?php foreach ($allocations as $a): ?><tr><td><?= htmlspecialchars($isAdmin ? $a['user_name'] : 'You') ?></td><td><?= htmlspecialchars($a['allocation_date']) ?></td><td><?= htmlspecialchars($a['expected_return_date'] ?? '—') ?></td><td><?= htmlspecialchars($a['actual_return_date'] ?? 'Active') ?></td><td><?= htmlspecialchars($a['return_condition'] ?? '—') ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<section class="card"><h3>Maintenance history</h3>
    <?php if (!$maintenance): ?><p class="empty-state">No maintenance records.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Type</th><th>Issue</th><th>Dates</th><th>Status</th><th>Work</th></tr></thead><tbody>
        <?php foreach ($maintenance as $m): ?><tr><td><?= htmlspecialchars($m['maintenance_type']) ?></td><td><?= nl2br(htmlspecialchars($m['reported_issue'])) ?></td><td><?= htmlspecialchars($m['start_date'] . ($m['completion_date'] ? ' — ' . $m['completion_date'] : '')) ?></td><td><?= htmlspecialchars(asset_status_label($m['status'])) ?></td><td><?= nl2br(htmlspecialchars($m['work_performed'] ?? '—')) ?><?php if ($m['ticket_id'] && ($isAdmin || in_array((int)$m['ticket_id'], $visibleTicketIds, true))): ?><br><a href="<?= BASE_URL ?>/ticket_detail.php?id=<?= (int)$m['ticket_id'] ?>">Ticket #<?= (int)$m['ticket_id'] ?></a><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="card"><h3>Related support tickets</h3>
    <?php if (!$relatedTickets): ?><p class="empty-state">No related tickets visible to you.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Ticket</th><th>Status</th><th>Created</th></tr></thead><tbody>
        <?php foreach ($relatedTickets as $t): ?><tr><td><a href="<?= BASE_URL ?>/ticket_detail.php?id=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> — <?= htmlspecialchars($t['title']) ?></a></td><td><?= htmlspecialchars(asset_status_label($t['status'])) ?></td><td><?= htmlspecialchars($t['created_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php if ($isAdmin && $contracts): ?><section class="card"><h3>Warranty &amp; AMC</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Type</th><th>Provider</th><th>Reference</th><th>Expiry</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($contracts as $c): ?><tr><td><?= htmlspecialchars(strtoupper($c['contract_type'])) ?></td><td><?= htmlspecialchars($c['provider']) ?></td><td><?= htmlspecialchars($c['reference_number'] ?? '—') ?></td><td><?= htmlspecialchars($c['expiry_date']) ?></td><td><?= htmlspecialchars(asset_status_label($c['status'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section><?php endif; ?>
<?php if ($isAdmin): ?><section class="card"><h3>Asset audit history</h3>
    <?php if (!$activity): ?><p class="empty-state">No audit entries.</p>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Action</th><th>Actor</th><th>Details</th><th>Date</th></tr></thead><tbody><?php foreach ($activity as $e): ?><tr><td><?= htmlspecialchars(asset_status_label($e['action'])) ?></td><td><?= htmlspecialchars($e['full_name'] ?? 'System') ?></td><td><?= nl2br(htmlspecialchars($e['details'] ?? '—')) ?></td><td><?= htmlspecialchars($e['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section><?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
