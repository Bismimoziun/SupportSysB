<?php
// ============================================================
// G&G Support Portal — db.php
// SETUP INSTRUCTIONS:
//   1. Copy this file and rename it to db.php
//   2. Fill in your database credentials below
//   3. Never commit db.php to GitHub (it is in .gitignore)
// ============================================================

// Base URL — change depending on your setup:
//   XAMPP subfolder:  '/gg-support'
//   XAMPP root:       ''
//   Live server root: ''
define('BASE_URL', '/gg-support');

// Database settings
define('DB_HOST', 'localhost');       // XAMPP: localhost | Docker: mysql-db
define('DB_NAME', 'knowledgebase');   // your database name
define('DB_USER', 'root');            // XAMPP default: root
define('DB_PASS', '');                // XAMPP default: empty | change for live server

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check XAMPP MySQL is running and db.php settings are correct.<br>Error: '
        . htmlspecialchars($e->getMessage()));
}
