<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $payload = read_json_body();
    $fileName = basename((string)($payload['fileName'] ?? ''));
    if ($fileName === '') json_response(['success' => false, 'message' => 'Missing certificate filename.'], 400);
    $path = __DIR__ . '/funded certificates/' . $fileName;
    if (is_file($path)) @unlink($path);
    $jsonFile = __DIR__ . '/certificates.json';
    $list = is_file($jsonFile) ? json_decode(@file_get_contents($jsonFile) ?: '[]', true) : [];
    if (!is_array($list)) $list = [];
    $list = array_values(array_filter($list, fn($x) => basename((string)$x) !== $fileName));
    file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    json_response(['success' => true, 'message' => 'Certificate deleted successfully.']);
} catch (Throwable $e) {
    api_log('delete_certificate error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while deleting certificate.'], 500);
}
