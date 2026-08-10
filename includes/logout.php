<?php
// ============================================================
// G&G Support Portal — logout.php
// ============================================================

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/index.php?logout=1');
exit;
