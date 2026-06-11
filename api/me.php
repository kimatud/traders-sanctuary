<?php
$fast = isset($_GET['fast']) && (string)$_GET['fast'] !== '0';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($fast && is_string($authHeader) && stripos($authHeader, 'Bearer ') !== false) {
    define('TS_SKIP_SESSION_START', true);
}
require __DIR__ . '/config.php';
require_method('GET');
$user = current_user_or_null();

// Fast path for app boot: return the existing signed session or stateless Firebase bearer user immediately.
// Heavy Firestore/profile reconciliation is left to explicit dashboard data endpoints.
if ($fast) {
    if ($user) {
        $user = apply_backend_profile_guards($user, $user['email'] ?? '');
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['user'] = $user;
        }
    }
    json_response(['ok' => true, 'user' => $user, 'mode' => 'fast']);
}

if ($user && !empty($user['uid'])) {
    $idToken = current_id_token_or_null();
    $cloudProfile = $idToken ? load_user_profile($user['uid'] ?? '', $idToken) : [];
    $localProfile = load_local_user_profile($user['uid'] ?? '', $user['email'] ?? '');
    $accounts = load_user_trading_accounts($user['uid'] ?? '', $user['email'] ?? '', $idToken);
    $merged = merge_durable_user_profile(array_merge($user, $cloudProfile ?: []), $localProfile, $accounts);
    $merged = apply_backend_profile_guards($merged, $merged['email'] ?? ($user['email'] ?? ''));
    firestore_patch_session_profile($merged);
    $user = current_user_or_null();
}
json_response(['ok' => true, 'user' => $user, 'mode' => 'full']);
