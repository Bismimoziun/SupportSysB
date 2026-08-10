<?php
// ============================================================
// G&G Support Portal — get_stats.php
// AJAX: returns dashboard stat counts as JSON
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();

header('Content-Type: application/json');

$stats = [
    'total_solutions'   => (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "approved"')->fetchColumn(),
    'pending_solutions' => (int)$pdo->query('SELECT COUNT(*) FROM solutions WHERE status = "pending"')->fetchColumn(),
    'open_flags'        => (int)$pdo->query('SELECT COUNT(*) FROM flags WHERE status = "open"')->fetchColumn(),
    'total_users'       => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    'total_categories'  => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
];

echo json_encode($stats);
