<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    require_admin_user();
    $payload = read_json_body();
    $id = trim((string)($payload['id'] ?? ''));
    $decision = strtolower(trim((string)($payload['decision'] ?? '')));
    if ($id === '' || !in_array($decision, ['approve','reject'], true)) {
        json_response(['success' => false, 'message' => 'Invalid certificate review request.'], 400);
    }
    $store = __DIR__ . '/api/_data/certificate_requests.json';
    $items = is_file($store) ? json_decode(@file_get_contents($store) ?: '[]', true) : [];
    if (!is_array($items)) $items = [];
    $found = null;
    foreach ($items as $i => $item) {
        if ((string)($item['id'] ?? '') === $id) { $found = $i; break; }
    }
    if ($found === null) json_response(['success' => false, 'message' => 'Certificate request not found.'], 404);
    $item = $items[$found];
    if (($item['status'] ?? 'pending') !== 'pending') {
        json_response(['success' => false, 'message' => 'This certificate request was already reviewed.'], 409);
    }

    $pendingName = basename((string)($item['fileName'] ?? ''));
    $pendingPath = __DIR__ . '/funded certificates/pending/' . $pendingName;
    if ($decision === 'approve') {
        if (!is_file($pendingPath)) json_response(['success' => false, 'message' => 'Pending certificate file is missing.'], 404);
        $ext = strtolower(pathinfo($pendingName, PATHINFO_EXTENSION));
        $finalBase = preg_replace('/^pending_[^_]+_/', '', pathinfo($pendingName, PATHINFO_FILENAME));
        $finalBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $finalBase ?: 'funded_certificate');
        $finalName = $finalBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $finalDir = __DIR__ . '/funded certificates';
        if (!is_dir($finalDir)) @mkdir($finalDir, 0775, true);
        if (!@rename($pendingPath, $finalDir . '/' . $finalName)) {
            if (!@copy($pendingPath, $finalDir . '/' . $finalName) || !@unlink($pendingPath)) {
                json_response(['success' => false, 'message' => 'Could not publish certificate. Check folder permissions.'], 500);
            }
        }
        @chmod($finalDir . '/' . $finalName, 0664);
        $jsonFile = __DIR__ . '/certificates.json';
        $list = is_file($jsonFile) ? json_decode(@file_get_contents($jsonFile) ?: '[]', true) : [];
        if (!is_array($list)) $list = [];
        array_unshift($list, $finalName);
        $list = array_values(array_unique(array_filter($list)));
        file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $items[$found]['status'] = 'approved';
        $items[$found]['approvedFileName'] = $finalName;
        $items[$found]['approvedUrl'] = 'funded certificates/' . rawurlencode($finalName);
        $message = 'Certificate approved and published.';
    } else {
        if (is_file($pendingPath)) @unlink($pendingPath);
        $items[$found]['status'] = 'rejected';
        $message = 'Certificate request rejected.';
    }
    $items[$found]['updatedAt'] = date('c');
    file_put_contents($store, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    api_log('review_certificate_request error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while reviewing certificate.'], 500);
}
