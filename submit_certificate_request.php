<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
try {
    $user = require_current_user();
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
            : 'Please choose your certificate image.';
        json_response(['success' => false, 'message' => $message, 'receivedFiles' => array_keys($_FILES ?? [])], 400);
    }
    if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Certificate upload failed. Please try again.'], 400);
    }
    $maxBytes = 10 * 1024 * 1024;
    if (($uploaded['size'] ?? 0) > $maxBytes) {
        json_response(['success' => false, 'message' => 'Image is too large. Maximum size is 10MB.'], 400);
    }
    $original = basename((string)$uploaded['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','webp','gif'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Only image certificate files are allowed.'], 400);
    }

    $pendingDir = __DIR__ . '/funded certificates/pending';
    if (!is_dir($pendingDir) && !@mkdir($pendingDir, 0775, true)) {
        json_response(['success' => false, 'message' => 'Could not prepare upload folder.'], 500);
    }

    $uid = (string)($user['uid'] ?? 'member');
    $email = (string)($user['email'] ?? '');
    $name = trim((string)($user['displayName'] ?? '')) ?: ($email ?: 'Member');
    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    $fileName = 'pending_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $uid) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safeBase . '.' . $ext;
    $target = $pendingDir . '/' . $fileName;
    if (!move_uploaded_file($uploaded['tmp_name'], $target)) {
        json_response(['success' => false, 'message' => 'Could not save certificate. Check folder permissions.'], 500);
    }
    @chmod($target, 0664);

    $store = __DIR__ . '/api/_data/certificate_requests.json';
    if (!is_dir(dirname($store))) @mkdir(dirname($store), 0775, true);
    $items = is_file($store) ? json_decode(@file_get_contents($store) ?: '[]', true) : [];
    if (!is_array($items)) $items = [];
    $request = [
        'id' => 'cert_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
        'uid' => $uid,
        'email' => $email,
        'displayName' => $name,
        'originalName' => $original,
        'fileName' => $fileName,
        'fileUrl' => 'funded certificates/pending/' . rawurlencode($fileName),
        'status' => 'pending',
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];
    array_unshift($items, $request);
    file_put_contents($store, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($store, 0664);

    $lines = [
        'A funded certificate has been submitted for review.',
        'Member: ' . $name,
        'Email: ' . ($email ?: 'Not provided'),
        'Original file: ' . $original,
        'Please open the admin dashboard and approve or reject it from Manage Funded Certificates.'
    ];
    $emailResult = app_send_email(MAIL_ADMIN_EMAIL, 'Certificate submitted for approval — Traders Sanctuary', implode("\n\n", $lines), premium_email_html('Certificate submitted for approval', $lines));

    json_response(['success' => true, 'message' => 'Certificate submitted successfully. Admin will review and approve it before it appears in the gallery.', 'emailSent' => (bool)($emailResult['ok'] ?? false)]);
} catch (Throwable $e) {
    api_log('submit_certificate_request error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while submitting certificate.'], 500);
}
