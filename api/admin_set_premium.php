<?php
require __DIR__ . '/config.php';
require_method('POST');
require_admin_user();
$data = read_json_body();
$uid = trim((string)($data['uid'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$days = (int)($data['days'] ?? PREMIUM_DAYS);
if ($uid === '' && $email === '') json_response(['ok' => false, 'message' => 'Provide a user ID or email.'], 400);
if ($days <= 0) $days = PREMIUM_DAYS;
$expires = gmdate('c', time() + ($days * 86400));
save_local_user_profile($uid, $email, [
    'uid' => $uid,
    'email' => $email,
    'role' => 'premium',
    'premiumActivatedAt' => gmdate('c'),
    'premiumExpiresAt' => $expires,
    'premiumPaymentId' => 'manual_admin_' . bin2hex(random_bytes(5)),
]);
$idToken = current_id_token_or_null();
if ($uid && $idToken) {
    $r = firestore_patch_document(firestore_profile_path($uid), [
        'role' => 'premium',
        'premiumActivatedAt' => gmdate('c'),
        'premiumExpiresAt' => $expires,
    ], $idToken);
    if (!$r['ok']) api_log('Non-blocking manual admin premium Firestore update failed: ' . json_encode($r['data'] ?? $r));
}
json_response(['ok' => true, 'premiumExpiresAt' => $expires]);
