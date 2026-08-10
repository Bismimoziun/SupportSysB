<?php
// includes/raise_ticket.php — DEBUG VERSION
// Replace your current file with this, submit the form once,
// then send me the exact error message you see.

// Catch everything — no silent failures
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error [$errno]: $errstr",
        'file'    => $errfile,
        'line'    => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Exception: " . $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);
    exit;
});

// Force JSON header immediately
header('Content-Type: application/json');

// Step 1 — can we load db.php?
$dbPath = __DIR__ . '/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['success'=>false,'message'=>'MISSING FILE: includes/db.php not found at '.$dbPath]); exit;
}
require_once $dbPath;

// Step 2 — session started? user logged in?
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'NOT LOGGED IN — session has no user_id. Session data: '.json_encode($_SESSION)]); exit;
}

// Step 3 — ticket_helpers exists?
$helpersPath = __DIR__ . '/ticket_helpers.php';
if (!file_exists($helpersPath)) {
    echo json_encode(['success'=>false,'message'=>'MISSING FILE: includes/ticket_helpers.php not found at '.$helpersPath]); exit;
}
require_once $helpersPath;

// Step 4 — correct request method?
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Wrong method: '.$_SERVER['REQUEST_METHOD']]); exit;
}

// Step 5 — CSRF check (manual so it returns JSON not 403)
$token = $_POST['csrf_token'] ?? '';
if (empty($token)) {
    echo json_encode(['success'=>false,'message'=>'CSRF token missing from POST. POST keys received: '.implode(', ', array_keys($_POST))]); exit;
}
if (!isset($_SESSION['csrf_token'])) {
    echo json_encode(['success'=>false,'message'=>'No csrf_token in session. Session keys: '.implode(', ', array_keys($_SESSION))]); exit;
}
if (!hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success'=>false,'message'=>'CSRF token mismatch. Expected: '.$_SESSION['csrf_token'].' Got: '.$token]); exit;
}

// Step 6 — validate input
$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

if (!$title || !$description) {
    echo json_encode(['success'=>false,'message'=>'Title or description empty.']); exit;
}

// Step 7 — check ticket_levels table exists and has rows
try {
    $firstLevel = $pdo->query(
    "SELECT id FROM ticket_levels WHERE is_active = 1 ORDER BY level_order ASC LIMIT 1")->fetchColumn();
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'DB error on ticket_levels: '.$e->getMessage().'. Have you run ticket_schema_fixed.sql?']); exit;
}

if (!$firstLevel) {
    echo json_encode(['success' => false, 'message' => 'No active ticket levels configured. Please contact your system administrator.']);
    exit;
}
// Step 8 — insert ticket
try {
    $pdo->prepare("INSERT INTO tickets (title, description, category_id, raised_by) VALUES (?,?,?,?)")
        ->execute([$title, $description, $categoryId, $_SESSION['user_id']]);
    $ticketId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'DB error inserting ticket: '.$e->getMessage()]); exit;
}

// Step 9 — log and assign
logTicketActivity($pdo, $ticketId, $_SESSION['user_id'], 'raised', null, null, null, null, 'Ticket raised.');
$assigned = assignTicketToLevel($pdo, $ticketId, (int)$firstLevel);

echo json_encode([
    'success'   => true,
    'message'   => 'Ticket #'.$ticketId.' raised successfully!',
    'ticket_id' => $ticketId,
    'assigned_to_admin' => $assigned
]);