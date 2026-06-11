<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $uploaded = $_FILES['certificate'] ?? $_FILES['certificate_file'] ?? null;
    if (!$uploaded && !empty($_FILES) && is_array($_FILES)) {
        foreach ($_FILES as $candidate) {
            if (is_array($candidate) && !empty($candidate['tmp_name'])) { $uploaded = $candidate; break; }
        }
    }
    if (!$uploaded || empty($uploaded['tmp_name'])) {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax = ini_get('post_max_size') ?: 'server limit';
        $message = $contentLength > 0
            ? 'The certificate image did not reach the server. Please choose a smaller image or try again. Server post limit: ' . $postMax . '.'
            : 'Please choose a certificate image.';
        json_response(['success' => false, 'message' => $message, 'receivedFiles' => array_keys($_FILES ?? [])], 400);
    }
    if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Certificate upload failed.'], 400);
    }
    $maxBytes = 10 * 1024 * 1024;
    if (($uploaded['size'] ?? 0) > $maxBytes) {
        json_response(['success' => false, 'message' => 'Image is too large. Maximum size is 10MB.'], 400);
    }
    $original = basename((string)$uploaded['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','webp','gif'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Only image certificates are allowed.'], 400);
    }
    $dir = __DIR__ . '/funded certificates';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    $fileName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = $dir . '/' . $fileName;
    if (!move_uploaded_file($uploaded['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Could not save certificate. Check folder permissions.'], 500);
    }
    @chmod($target, 0664);
    $jsonFile = __DIR__ . '/certificates.json';
    $list = is_file($jsonFile) ? json_decode(@file_get_contents($jsonFile) ?: '[]', true) : [];
    if (!is_array($list)) $list = [];
    array_unshift($list, $fileName);
    $list = array_values(array_unique($list));
    file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($jsonFile, 0664);
    json_response(['success' => true, 'message' => 'Certificate uploaded successfully.', 'fileName' => $fileName]);
} catch (Throwable $e) {
    api_log('upload_certificate error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while uploading certificate.'], 500);
}
