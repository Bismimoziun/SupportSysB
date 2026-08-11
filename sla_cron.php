<?php
// ============================================================
// sla_cron.php
// Run this file via Windows Task Scheduler (or Linux cron)
// every 5–15 minutes to auto-escalate breached SLA tickets.
//
// Windows Task Scheduler command:
//   php C:\xampp\htdocs\gg-support\sla_cron.php
//
// Linux cron (every 10 min):
//   */10 * * * * php /var/www/html/gg-support/sla_cron.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ticket_helpers.php';

echo "[" . date('Y-m-d H:i:s') . "] Running SLA check...\n";
runSlaCheck($pdo);
echo "Done.\n";
