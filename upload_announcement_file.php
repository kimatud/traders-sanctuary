<?php
require __DIR__ . '/api/config.php';
require_method('POST');
require_admin_user();

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_response(['ok' => false, 'message' => 'No upload file received.'], 400);
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'message' => 'Upload failed. Please try again.'], 400);
}

$maxBytes = 12 * 1024 * 1024;
if ((int)($file['size'] ?? 0) > $maxBytes) {
    json_response(['ok' => false, 'message' => 'File is too large. Maximum size is 12MB.'], 400);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? 'application/octet-stream');
if ($finfo) finfo_close($finfo);
if (!isset($allowed[$mime])) {
    json_response(['ok' => false, 'message' => 'Unsupported file type. Use JPG, PNG, WEBP, or PDF.'], 400);
}

$folder = preg_replace('/[^A-Za-z0-9_\- ]/', '', (string)($_POST['folder'] ?? 'announcement_files'));
$folder = trim($folder) ?: 'announcement_files';
if ($folder !== 'announcement_files') $folder = 'announcement_files';
$targetDir = __DIR__ . '/' . $folder;
ensure_dir($targetDir);

$base = pathinfo((string)$file['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $base);
$base = trim($base, '-') ?: 'file';
$filename = $base . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
$target = $targetDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    json_response(['ok' => false, 'message' => 'Could not save uploaded file. Check folder permissions.'], 500);
}
@chmod($target, 0644);

json_response([
    'ok' => true,
    'fileName' => $file['name'],
    'storedName' => $filename,
    'fileUrl' => '/' . rawurlencode($folder) . '/' . rawurlencode($filename),
]);
