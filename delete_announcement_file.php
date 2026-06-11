<?php
require __DIR__ . '/api/config.php';
require_method('POST');
require_admin_user();
$data = read_json_body();
$fileUrl = (string)($data['fileUrl'] ?? '');
$path = parse_url($fileUrl, PHP_URL_PATH) ?: $fileUrl;
$path = rawurldecode($path);
$path = ltrim($path, '/');
if ($path === '' || strpos($path, '..') !== false || !str_starts_with($path, 'announcement_files/')) {
    json_response(['ok' => false, 'message' => 'Invalid file path.'], 400);
}
$target = realpath(__DIR__ . '/' . $path);
$base = realpath(__DIR__ . '/announcement_files');
if (!$target || !$base || strpos($target, $base) !== 0) {
    json_response(['ok' => true, 'deleted' => false]);
}
@unlink($target);
json_response(['ok' => true, 'deleted' => !is_file($target)]);
