<?php
// includes/ticket_action.php — AJAX: take_up / resolve / unresolve / extend / sla_check
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/ticket_helpers.php';

guard_require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}

csrf_verify(); // dies on 403 if invalid

// Use MySQL's clock for all deadline comparisons to avoid PHP/MySQL timezone mismatch
$dbNow = new DateTime($pdo->query("SELECT NOW()")->fetchColumn());

$action   = $_POST['action']    ?? '';
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$userId   = $_SESSION['user_id'];
$role     = $_SESSION['role'];

if (!$ticketId) { echo json_encode(['success' => false, 'message' => 'No ticket specified.']); exit; }

$t = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
$t->execute([$ticketId]);
$t = $t->fetch(PDO::FETCH_ASSOC);
if (!$t) { echo json_encode(['success' => false, 'message' => 'Ticket not found.']); exit; }

// ---- TAKE UP ----
if ($action === 'take_up') {
    if (!in_array($role, ['admin','system_admin'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorised.']); exit;
    }
    if ($t['current_admin'] != $userId) {
        echo json_encode(['success'=>false,'message'=>'This ticket is not assigned to you.']); exit;
    }
    if ($t['status'] !== 'open') {
        echo json_encode(['success'=>false,'message'=>'Ticket is not in Open state.']); exit;
    }
    // Block take-up if the attend deadline has already passed — escalate properly
    if ($t['attend_deadline'] && $dbNow > new DateTime($t['attend_deadline'])) {
        escalateTicket($pdo, $ticketId);
        echo json_encode(['success'=>false,'message'=>'Attend deadline has passed. Ticket has been escalated.']); exit;
    }
    $pdo->prepare("UPDATE tickets SET status='in_progress', attended_at=NOW() WHERE id=?")->execute([$ticketId]);
    logTicketActivity($pdo, $ticketId, $userId, 'taken_up', $t['current_level'], $userId,
        $t['attend_deadline'], $t['resolve_deadline'], 'Admin took up the ticket.');
    echo json_encode(['success'=>true,'message'=>'Ticket is now In Progress.']); exit;
}

// ---- RESOLVE ----
if ($action === 'resolve') {
    if (!in_array($role, ['admin','system_admin'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorised.']); exit;
    }
    if ($t['current_admin'] != $userId) {
        echo json_encode(['success'=>false,'message'=>'This ticket is not assigned to you.']); exit;
    }

    $note        = trim($_POST['resolution_note'] ?? '');
    $addSolution = !empty($_POST['add_to_solution']) ? 1 : 0;
    $makePublic  = !empty($_POST['solution_public'])  ? 1 : 0;

    if ($note === '') {
        echo json_encode(['success'=>false,'message'=>'Resolution note is required.']); exit;
    }

    // Mark ticket resolved — always set resolved_at
    $pdo->prepare("
        UPDATE tickets
        SET status = 'resolved',
            resolved_at = NOW(),
            resolution_note = ?,
            add_to_solution = ?,
            solution_public = ?
        WHERE id = ?
    ")->execute([$note, $addSolution, $makePublic, $ticketId]);

    logTicketActivity($pdo, $ticketId, $userId, 'resolved',
        $t['current_level'], $userId, null, null, $note);

    // Save to solution database if requested
    if ($addSolution) {
        // requires_admin = 1  → admin-only (private)
        // requires_admin = 0  → public
        $requiresAdmin = $makePublic ? 0 : 1;
        $status        = 'approved'; // resolved by admin → auto-approved

        $pdo->prepare("
            INSERT INTO solutions
                (question, answer, category_id, submitted_by, status, requires_admin, verified_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $t['title'],
            $note,
            $t['category_id'],
            $userId,
            $status,
            $requiresAdmin,
            $userId
        ]);
    }

    echo json_encode(['success'=>true,'message'=>'Ticket resolved successfully.']); exit;
}

// ---- UNRESOLVED ----
if ($action === 'unresolve') {
    if (!in_array($role, ['admin','system_admin'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorised.']); exit;
    }
    $note = trim($_POST['resolution_note'] ?? '');
    $pdo->prepare("
        UPDATE tickets
        SET status = 'unresolved',
            resolved_at = NOW(),
            resolution_note = ?
        WHERE id = ?
    ")->execute([$note, $ticketId]);
    logTicketActivity($pdo, $ticketId, $userId, 'unresolved',
        $t['current_level'], $userId, null, null, $note);
    echo json_encode(['success'=>true,'message'=>'Ticket marked Unresolved.']); exit;
}

// ---- EXTEND ----
if ($action === 'extend') {
    if (!in_array($role, ['admin','system_admin'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorised.']); exit;
    }
    if ($t['current_admin'] != $userId) {
        echo json_encode(['success'=>false,'message'=>'This ticket is not assigned to you.']); exit;
    }
    $reasonId   = (int)($_POST['reason_id']  ?? 0);
    $remarks    = trim($_POST['remarks']      ?? '');
    $extraHours = (int)($_POST['extra_hours'] ?? 0);
    if (!$reasonId || !$remarks || $extraHours < 1) {
        echo json_encode(['success'=>false,'message'=>'All extension fields are required.']); exit;
    }
    $activeField = ($t['status'] === 'open') ? 'attend_deadline' : 'resolve_deadline';
    $oldDeadline = new DateTime($t[$activeField]);
    if ($dbNow > $oldDeadline) {
        echo json_encode(['success'=>false,'message'=>'Deadline already passed — cannot extend.']); exit;
    }
    $newDeadline = (clone $oldDeadline)->modify("+{$extraHours} hours");
    $pdo->prepare("UPDATE tickets SET {$activeField} = ? WHERE id = ?")
        ->execute([$newDeadline->format('Y-m-d H:i:s'), $ticketId]);
    $pdo->prepare("INSERT INTO ticket_extensions (ticket_id, admin_id, reason_id, remarks, extra_hours, old_deadline, new_deadline) VALUES (?,?,?,?,?,?,?)")
        ->execute([$ticketId, $userId, $reasonId, $remarks, $extraHours,
                   $oldDeadline->format('Y-m-d H:i:s'), $newDeadline->format('Y-m-d H:i:s')]);
    logTicketActivity($pdo, $ticketId, $userId, 'extended', $t['current_level'], $userId, null, null,
        "Extended by {$extraHours}h. {$remarks}");
    echo json_encode(['success'=>true,'message'=>"Deadline extended by {$extraHours} hour(s)."]); exit;
}

// ---- SLA CHECK ----
if ($action === 'sla_check') {
    if ($role !== 'system_admin') { echo json_encode(['success'=>false,'message'=>'Unauthorised.']); exit; }
    runSlaCheck($pdo);
    echo json_encode(['success'=>true,'message'=>'SLA check complete.']); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);