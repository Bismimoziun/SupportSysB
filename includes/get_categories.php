<?php
// ============================================================
// G&G Support Portal — get_categories.php
// AJAX: returns child categories as JSON
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();

header('Content-Type: application/json');

$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE parent_id = ? ORDER BY name ASC');
$stmt->execute([$parent_id === 0 ? null : $parent_id]);
$cats = $stmt->fetchAll();

echo json_encode($cats);
