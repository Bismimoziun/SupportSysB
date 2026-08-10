<?php
// ============================================================
// G&G Support Portal — submit_flag.php
// AJAX: flag a question that has no answer
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$question = trim($_POST['question'] ?? '');
if ($question === '') {
    echo json_encode(['success' => false, 'message' => 'Question cannot be empty.']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('INSERT INTO flags (question, raised_by, status) VALUES (?, ?, "open")');
$stmt->execute([$question, $user_id]);

echo json_encode(['success' => true, 'message' => 'Your question has been flagged for admin review. Thank you!']);
