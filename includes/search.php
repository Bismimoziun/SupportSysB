<?php
// ============================================================
// G&G Support Portal — search.php
// AJAX: live solution search for user_home
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$is_admin = in_array($role, ['admin', 'system_admin'], true);

// Regular users don't see requires_admin=1 solutions
if ($is_admin) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.question, s.answer, c.name AS category,
                u.full_name AS submitted_by, s.created_at,
                v.full_name AS verified_by
         FROM solutions s
         LEFT JOIN categories c ON s.category_id = c.id
         LEFT JOIN users u ON u.id = s.submitted_by
         LEFT JOIN users v ON v.id = s.verified_by
         WHERE s.status = "approved"
           AND (s.question LIKE ? OR s.answer LIKE ?)
         ORDER BY s.id DESC
         LIMIT 10'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.question, s.answer, c.name AS category,
                u.full_name AS submitted_by, s.created_at,
                v.full_name AS verified_by
         FROM solutions s
         LEFT JOIN categories c ON s.category_id = c.id
         LEFT JOIN users u ON u.id = s.submitted_by
         LEFT JOIN users v ON v.id = s.verified_by
         WHERE s.status = "approved"
           AND s.requires_admin = 0
           AND (s.question LIKE ? OR s.answer LIKE ?)
         ORDER BY s.id DESC
         LIMIT 10'
    );
}

$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
$results = $stmt->fetchAll();

echo json_encode($results);
