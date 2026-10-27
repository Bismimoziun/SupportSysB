<?php
require_once __DIR__ . '/auth_guard.php';

function asset_require_admin(): void {
    guard_require_role(['admin', 'system_admin']);
}

function asset_log(PDO $pdo, int $assetId, ?int $actorId, string $action, ?string $details = null): void {
    $stmt = $pdo->prepare(
        'INSERT INTO asset_activity (asset_id, actor_id, action, details) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$assetId, $actorId, $action, $details]);
}

function asset_valid_date(mixed $value): bool {
    if ($value === null || $value === '') {
        return true;
    }
    if (!is_string($value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function asset_status_label(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

function asset_post_string(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function asset_owned_by_user(PDO $pdo, int $assetId, int $userId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM asset_allocations
         WHERE asset_id = ? AND user_id = ? AND actual_return_date IS NULL LIMIT 1"
    );
    $stmt->execute([$assetId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function asset_allocate(PDO $pdo, int $assetId, int $userId, int $adminId, ?string $expectedReturn, string $notes, ?int $requestId = null): void {
    $pdo->beginTransaction();
    try {
        $assetStmt = $pdo->prepare('SELECT status FROM assets WHERE id = ? FOR UPDATE');
        $assetStmt->execute([$assetId]);
        $status = $assetStmt->fetchColumn();
        if ($status !== 'available') {
            throw new RuntimeException('This asset is not available for allocation.');
        }

        $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
        $userStmt->execute([$userId]);
        if (!$userStmt->fetchColumn()) {
            throw new RuntimeException('Select an active user.');
        }

        $pdo->prepare(
            'INSERT INTO asset_allocations
                (asset_id, user_id, request_id, expected_return_date, allocated_by, allocation_notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$assetId, $userId, $requestId, $expectedReturn ?: null, $adminId, $notes ?: null]);
        $allocationId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE assets SET status = 'allocated' WHERE id = ?")->execute([$assetId]);
        if ($requestId !== null) {
            $requestStmt = $pdo->prepare(
                "UPDATE asset_requests SET status = 'completed', allocation_id = ?,
                    reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND request_type = 'allocation' AND status = 'approved'"
            );
            $requestStmt->execute([$allocationId, $adminId, $requestId]);
            if ($requestStmt->rowCount() !== 1) {
                throw new RuntimeException('The approved request is no longer available for allocation.');
            }
        }
        asset_log($pdo, $assetId, $adminId, 'allocated', 'Allocated to user #' . $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function asset_process_return(PDO $pdo, int $allocationId, int $adminId, string $condition, string $notes): void {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT aa.asset_id, aa.user_id, aa.actual_return_date, ar.id AS return_request_id
             FROM asset_allocations aa
             LEFT JOIN asset_requests ar ON ar.allocation_id = aa.id
                AND ar.request_type = "return" AND ar.status IN ("pending","approved")
             WHERE aa.id = ? FOR UPDATE'
        );
        $stmt->execute([$allocationId]);
        $allocation = $stmt->fetch();
        if (!$allocation || $allocation['actual_return_date'] !== null) {
            throw new RuntimeException('This allocation has already been returned or does not exist.');
        }

        $assetStmt = $pdo->prepare('SELECT status FROM assets WHERE id = ? FOR UPDATE');
        $assetStmt->execute([$allocation['asset_id']]);
        $assetStatus = $assetStmt->fetchColumn();
        if (!$assetStatus) {
            throw new RuntimeException('The linked asset no longer exists.');
        }

        $pdo->prepare(
            'UPDATE asset_allocations
             SET actual_return_date = NOW(), return_condition = ?, return_notes = ?, returned_by = ?
             WHERE id = ? AND actual_return_date IS NULL'
        )->execute([$condition, $notes ?: null, $adminId, $allocationId]);

        $maintenanceStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM asset_maintenance
             WHERE asset_id=? AND status IN ('open','in_progress')"
        );
        $maintenanceStmt->execute([$allocation['asset_id']]);
        $needsRepair = in_array($condition, ['poor', 'damaged'], true) ||
            (int)$maintenanceStmt->fetchColumn() > 0;
        $nextStatus = $needsRepair ? 'under_maintenance' : 'available';
        $pdo->prepare('UPDATE assets SET status = ? WHERE id = ?')->execute([$nextStatus, $allocation['asset_id']]);
        if ($allocation['return_request_id']) {
            $pdo->prepare(
                "UPDATE asset_requests SET status = 'completed', reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND status IN ('pending','approved')"
            )->execute([$adminId, $allocation['return_request_id']]);
        }
        asset_log($pdo, (int)$allocation['asset_id'], $adminId, 'returned', 'Condition: ' . $condition);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
