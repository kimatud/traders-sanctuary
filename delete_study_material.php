<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $payload = read_json_body();
    $id = trim((string)($payload['id'] ?? ''));
    $fileName = basename((string)($payload['fileName'] ?? ''));
    $store = __DIR__ . '/api/_data/study_materials.json';
    $items = is_file($store) ? json_decode(@file_get_contents($store) ?: '[]', true) : [];
    if (!is_array($items)) $items = [];
    $next = [];
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id || ($fileName && ($item['fileName'] ?? '') === $fileName)) continue;
        $next[] = $item;
    }
    if ($fileName !== '') {
        $path = __DIR__ . '/study materials/' . $fileName;
        if (is_file($path)) @unlink($path);
    }
    file_put_contents($store, json_encode(array_values($next), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    json_response(['success' => true, 'message' => 'Study material deleted successfully.']);
} catch (Throwable $e) {
    api_log('delete_study_material error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while deleting material.'], 500);
}
