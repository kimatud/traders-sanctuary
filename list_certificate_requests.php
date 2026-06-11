<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $store = __DIR__ . '/api/_data/certificate_requests.json';
    $items = is_file($store) ? json_decode(@file_get_contents($store) ?: '[]', true) : [];
    if (!is_array($items)) $items = [];
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($status !== '') {
        $items = array_values(array_filter($items, fn($x) => strtolower((string)($x['status'] ?? 'pending')) === $status));
    }
    json_response(['success' => true, 'items' => array_values($items)]);
} catch (Throwable $e) {
    api_log('list_certificate_requests error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while loading certificate requests.', 'items' => []], 500);
}
