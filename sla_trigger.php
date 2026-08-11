<?php
// ============================================================
// sla_trigger.php
// Called silently via JS fetch on every admin page load.
// Uses a DB lock so SLA check only runs once every 5 minutes
// regardless of how many admins are using the system.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/ticket_helpers.php';

guard_require_login();
header('Content-Type: application/json');

// Only admins trigger SLA checks
if (!in_array($_SESSION['role'], ['admin', 'system_admin'])) {
    echo json_encode(['ran' => false, 'reason' => 'not_admin']);
    exit;
}

// Check when SLA was last run (stored in a simple settings table)
$last = $pdo->query("SELECT setting_value FROM sla_settings WHERE setting_key = 'last_sla_run' LIMIT 1")->fetchColumn();

// Use MySQL's NOW() so comparison is in the same timezone as stored deadlines
$nowRow      = $pdo->query("SELECT NOW() AS now")->fetch(PDO::FETCH_ASSOC);
$now         = new DateTime($nowRow['now']);
$intervalMin = 5; // run every 5 minutes

if ($last) {
    $lastRun = new DateTime($last);
    $diff    = ($now->getTimestamp() - $lastRun->getTimestamp()) / 60;
    if ($diff < $intervalMin) {
        echo json_encode(['ran' => false, 'reason' => 'too_soon', 'next_in_seconds' => round(($intervalMin * 60) - ($diff * 60))]);
        exit;
    }
}

// Update the timestamp FIRST (prevents race condition with multiple admins)
$pdo->prepare("
    INSERT INTO sla_settings (setting_key, setting_value)
    VALUES ('last_sla_run', ?)
    ON DUPLICATE KEY UPDATE setting_value = ?
")->execute([$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);

// Run the SLA check
runSlaCheck($pdo);

echo json_encode(['ran' => true, 'at' => $now->format('Y-m-d H:i:s')]);