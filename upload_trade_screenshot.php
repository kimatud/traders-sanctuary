<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_current_user();
    if (empty($_FILES['screenshot']['tmp_name'])) {
        json_response(['success' => false, 'message' => 'Screenshot file is required.'], 400);
    }
    $file = $_FILES['screenshot'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Screenshot upload failed.'], 400);
    }
    $maxBytes = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        json_response(['success' => false, 'message' => 'Screenshot is too large. Maximum size is 8MB.'], 400);
    }

    $original = basename((string)($file['name'] ?? 'screenshot'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Unsupported screenshot type. Use PNG, JPG, JPEG, or WEBP.'], 400);
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? (string)finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    $allowedMime = ['image/png', 'image/jpeg', 'image/webp'];
    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        json_response(['success' => false, 'message' => 'Invalid screenshot image.'], 400);
    }

    $uploadDir = __DIR__ . '/trade_screenshots';
    ensure_dir($uploadDir);
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    $fileName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Could not save screenshot. Check folder permissions.'], 500);
    }
    @chmod($target, 0664);

    $url = 'trade_screenshots/' . rawurlencode($fileName);
    json_response(['success' => true, 'ok' => true, 'message' => 'Screenshot uploaded successfully.', 'filePath' => $url, 'url' => $url, 'path' => $url]);
} catch (Throwable $e) {
    api_log('upload_trade_screenshot error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while uploading screenshot.'], 500);
}
