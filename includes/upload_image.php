<?php
// ============================================================
// G&G Support Portal — upload_image.php
// TinyMCE image upload handler
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';
guard_require_login();
guard_require_role(['admin', 'system_admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => 'No file uploaded.']);
    exit;
}

$file      = $_FILES['file'];
$allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime      = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowed, true)) {
    echo json_encode(['error' => 'Only JPG, PNG, GIF, WEBP images are allowed.']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File size must be under 5MB.']);
    exit;
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
$dest     = __DIR__ . '/../uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Failed to save file.']);
    exit;
}

echo json_encode(['location' => BASE_URL . '/uploads/' . $filename]);
