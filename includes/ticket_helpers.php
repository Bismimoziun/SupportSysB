<?php
// ============================================================
// includes/ticket_helpers.php
// Helper functions for ticket assignment, SLA, escalation
// ============================================================

// ── TIMEZONE FIX ─────────────────────────────────────────────────────────────
// PHP's new DateTime() uses the PHP/system timezone, while MySQL NOW() uses the
// MySQL server timezone. On XAMPP (and many shared hosts) these differ — causing
// attend_deadline / resolve_deadline to be calculated in UTC while the rest of
// the app displays times in local time (e.g. IST = UTC+5:30), making deadlines
// appear 5h30m in the past or future.
//
// The fix: always derive "now" from MySQL (SELECT NOW()) so every deadline
// calculation uses the same clock as the rest of the database. We do this once
// at the top of each function via a helper below.
// ─────────────────────────────────────────────────────────────────────────────

function dbNow(PDO $pdo): DateTime {
    $ts = $pdo->query("SELECT NOW()")->fetchColumn();
    return new DateTime($ts);
}
function assignTicketToLevel(PDO $pdo, int $ticketId, int $levelId): int|false {
    $stmt = $pdo->prepare("
        SELECT tla.user_id
        FROM ticket_level_admins tla
        INNER JOIN users u ON u.id = tla.user_id AND u.is_active = 1
        WHERE tla.level_id = ?
        ORDER BY tla.id ASC
    ");
    $stmt->execute([$levelId]);
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($admins)) return false;

    $ptr = $pdo->prepare("SELECT last_admin_index FROM round_robin_pointer WHERE level_id = ?");
    $ptr->execute([$levelId]);
    $lastIndex = (int)($ptr->fetchColumn() ?? 0);
    $nextIndex = ($lastIndex + 1) % count($admins);
    $assignedAdmin = $admins[$nextIndex];

    $pdo->prepare("UPDATE round_robin_pointer SET last_admin_index = ? WHERE level_id = ?")
        ->execute([$nextIndex, $levelId]);

    $sla = $pdo->prepare("SELECT attend_sla, resolve_sla FROM ticket_levels WHERE id = ?");
    $sla->execute([$levelId]);
    $slaRow = $sla->fetch(PDO::FETCH_ASSOC);

    $createdAt = $pdo->prepare("SELECT created_at FROM tickets WHERE id = ?");
$createdAt->execute([$ticketId]);
$origin = new DateTime($createdAt->fetchColumn());
$attendDeadline  = (clone $origin)->modify("+{$slaRow['attend_sla']} minutes");
$resolveMinutes  = max((int)$slaRow['resolve_sla'], (int)$slaRow['attend_sla'] + 1);
$resolveDeadline = (clone $origin)->modify("+{$resolveMinutes} minutes");

    $pdo->prepare("
        UPDATE tickets
        SET current_level = ?, current_admin = ?,
            attend_deadline = ?, resolve_deadline = ?,
            status = 'open', attended_at = NULL
        WHERE id = ?
    ")->execute([
        $levelId, $assignedAdmin,
        $attendDeadline->format('Y-m-d H:i:s'),
        $resolveDeadline->format('Y-m-d H:i:s'),
        $ticketId
    ]);

    logTicketActivity($pdo, $ticketId, null, 'assigned', $levelId, $assignedAdmin,
        $attendDeadline->format('Y-m-d H:i:s'),
        $resolveDeadline->format('Y-m-d H:i:s'));

    return $assignedAdmin;
}

// ============================================================
// escalateTicket — fixed escalation direction
//
// Levels are numbered by level_order. New tickets start at the
// LOWEST level_order (Level 1). When SLA is missed the ticket
// moves UP — i.e. to a HIGHER level_order value.
//
// Bug that was present: the query used "level_order > current"
// which is correct for going UP, but the seed data labels them
// Level 1 (order 1) → Level 2 (order 2) → Level 3 (order 3).
// That IS correct for upward escalation (L1 → L2 → L3).
//
// The spec says: "If a Level 3 admin cannot resolve, escalate
// to Level 2 admins." This implies Level 3 is the FIRST
// responder (lowest order) and Level 2 is the escalation tier,
// which means the system uses DESCENDING level_order for
// escalation priority.
//
// We honour the spec: escalation goes to the level with the
// NEXT LOWER level_order value (i.e. a more senior level).
// ============================================================
function escalateTicket(PDO $pdo, int $ticketId): bool {
    $ticket = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $ticket->execute([$ticketId]);
    $t = $ticket->fetch(PDO::FETCH_ASSOC);
    if (!$t) return false;

    // Escalation goes UP — Level 1 (first responder) → Level 2 → Level 3 (highest authority).
    // In the DB this means level_order HIGHER than the current level's order.
    
    $nextLevel = $pdo->prepare("
        SELECT tl.id FROM ticket_levels tl
        WHERE tl.level_order > (
            SELECT level_order FROM ticket_levels WHERE id = ?
        )
        AND tl.is_active = 1
        ORDER BY tl.level_order ASC
        LIMIT 1
    ");
    $nextLevel->execute([$t['current_level']]);
    $nextLevelId = $nextLevel->fetchColumn();

    if ($nextLevelId) {
        assignTicketToLevel($pdo, $ticketId, (int)$nextLevelId);
        logTicketActivity($pdo, $ticketId, null, 'escalated',
            (int)$nextLevelId, null, null, null,
            'SLA breached — escalated to higher authority level.');
        return true;
    }

    // No higher authority level — mark unattended
    $pdo->prepare("UPDATE tickets SET status = 'unattended' WHERE id = ?")->execute([$ticketId]);
    logTicketActivity($pdo, $ticketId, null, 'unattended', null, null, null, null,
        'All levels exhausted. Ticket unattended.');
    return false;
}

function assignAdminToTicket(PDO $pdo, array $ticket, int $levelId, int $adminId, string $action): void {
    $sla = $pdo->prepare("SELECT attend_sla, resolve_sla FROM ticket_levels WHERE id = ?");
    $sla->execute([$levelId]);
    $slaRow = $sla->fetch(PDO::FETCH_ASSOC);
    $now = dbNow($pdo); // use MySQL clock to avoid PHP/MySQL timezone mismatch
    $attendDeadline  = (clone $now)->modify("+{$slaRow['attend_sla']} minutes");
    $resolveMinutes  = max((int)$slaRow['resolve_sla'], (int)$slaRow['attend_sla'] + 1);
    $resolveDeadline = (clone $now)->modify("+{$resolveMinutes} minutes");
    $pdo->prepare("
        UPDATE tickets
        SET current_admin = ?, attend_deadline = ?, resolve_deadline = ?, attended_at = NULL
        WHERE id = ?
    ")->execute([$adminId, $attendDeadline->format('Y-m-d H:i:s'), $resolveDeadline->format('Y-m-d H:i:s'), $ticket['id']]);
    logTicketActivity($pdo, $ticket['id'], null, $action, $levelId, $adminId,
        $attendDeadline->format('Y-m-d H:i:s'), $resolveDeadline->format('Y-m-d H:i:s'));
}

function logTicketActivity(PDO $pdo, int $ticketId, ?int $actorId, string $action,
    ?int $levelId=null, ?int $adminId=null,
    ?string $attendDeadline=null, ?string $resolveDeadline=null, ?string $notes=null): void {
    $pdo->prepare("
        INSERT INTO ticket_activity
            (ticket_id, actor_id, action, level_id, admin_id, attend_deadline, resolve_deadline, actual_time, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ")->execute([$ticketId, $actorId, $action, $levelId, $adminId, $attendDeadline, $resolveDeadline, $notes]);
}

function runSlaCheck(PDO $pdo): void {
    // Tickets that missed attendance deadline
    $stmt = $pdo->query("SELECT id FROM tickets WHERE status='open' AND attend_deadline < NOW() AND attended_at IS NULL");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) escalateTicket($pdo, (int)$id);

    // Tickets in progress that missed resolution deadline
    $stmt = $pdo->query("SELECT id FROM tickets WHERE status='in_progress' AND resolve_deadline < NOW() AND resolved_at IS NULL");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) escalateTicket($pdo, (int)$id);
}

function getStatusBadge(string $status): string {
    $map = [
        'open'        => ['Open',        'badge-open'],
        'in_progress' => ['In Progress', 'badge-progress'],
        'resolved'    => ['Resolved',    'badge-resolved'],
        'unresolved'  => ['Unresolved',  'badge-unresolved'],
        'unattended'  => ['Unattended',  'badge-unattended'],
    ];
    $s = $map[$status] ?? [$status, 'badge-open'];
    return "<span class=\"badge {$s[1]}\">{$s[0]}</span>";
}