<?php
// ============================================================
// G&G Support Portal — submit_answer.php
// AJAX: user submits an answer for admin review
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$question    = trim($_POST['question']    ?? '');
$answer      = trim($_POST['answer']      ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);

if ($question === '' || $answer === '') {
    echo json_encode(['success' => false, 'message' => 'Question and answer are required.']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'INSERT INTO solutions (question, answer, category_id, submitted_by, status, requires_admin)
     VALUES (?, ?, ?, ?, "pending", 0)'
);
$stmt->execute([$question, $answer, $category_id ?: null, $user_id]);

echo json_encode(['success' => true, 'message' => 'Your answer has been submitted and is pending admin approval. Thank you!']);
