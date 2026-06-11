<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($title === '' || $description === '' || empty($_FILES['materialFile']['tmp_name'])) {
        json_response(['success' => false, 'message' => 'Title, description and file are required.'], 400);
    }
    if (($_FILES['materialFile']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'File upload failed.'], 400);
    }
    $maxBytes = 25 * 1024 * 1024;
    if (($_FILES['materialFile']['size'] ?? 0) > $maxBytes) {
        json_response(['success' => false, 'message' => 'File is too large. Maximum size is 25MB.'], 400);
    }
    $original = basename((string)$_FILES['materialFile']['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','csv','txt','png','jpg','jpeg','webp'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Unsupported file type.'], 400);
    }
    $uploadDir = __DIR__ . '/study materials';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    $fileName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($_FILES['materialFile']['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Could not save uploaded file. Check folder permissions.'], 500);
    }
    @chmod($target, 0664);
    $store = __DIR__ . '/api/_data/study_materials.json';
    if (!is_dir(dirname($store))) @mkdir(dirname($store), 0775, true);
    $items = is_file($store) ? json_decode(@file_get_contents($store) ?: '[]', true) : [];
    if (!is_array($items)) $items = [];
    $item = [
        'id' => 'mat_' . bin2hex(random_bytes(8)),
        'title' => $title,
        'description' => $description,
        'fileName' => $fileName,
        'fileUrl' => 'study materials/' . rawurlencode($fileName),
        'originalName' => $original,
        'createdAt' => gmdate('c'),
    ];
    $items[] = $item;
    file_put_contents($store, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($store, 0664);
    json_response(['success' => true, 'message' => 'Study material uploaded successfully.', 'item' => $item]);
} catch (Throwable $e) {
    api_log('upload_study_material error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while uploading material.'], 500);
}
