<?php
require __DIR__ . '/config.php';
require_method('POST');

$VERSION = 'ADMIN_AUTH_RECOVERY_2026_05_19_DISABLED_UNLESS_SERVER_TOKEN_SET';
$data = read_json_body();
$email = strtolower(trim($data['email'] ?? ''));
$newPassword = (string)($data['password'] ?? '');
$token = (string)($data['recoveryToken'] ?? '');
$expected = getenv('ADMIN_AUTH_RECOVERY_TOKEN') ?: '';

if ($expected === '') {
    json_response([
        'ok' => false,
        'version' => $VERSION,
        'message' => 'Admin auth recovery is disabled. Set ADMIN_AUTH_RECOVERY_TOKEN on the server first, then call this endpoint.',
        'why' => 'This endpoint can recreate or reset an admin Firebase Auth account, so it must not be open publicly without a server-only token.'
    ], 403);
}
if (!hash_equals($expected, $token)) {
    json_response(['ok' => false, 'version' => $VERSION, 'message' => 'Invalid recovery token.'], 403);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_configured_admin_email($email)) {
    json_response(['ok' => false, 'version' => $VERSION, 'message' => 'Recovery is only allowed for configured admin emails.'], 400);
}
if (strlen($newPassword) < 6) {
    json_response(['ok' => false, 'version' => $VERSION, 'message' => 'Password must be at least 6 characters.'], 400);
}

// First try normal sign-up with web API. If user exists, reset password through Admin REST update.
$signup = firebase_auth_request('accounts:signUp', [
    'email' => $email,
    'password' => $newPassword,
    'returnSecureToken' => true,
]);

if ($signup['ok']) {
    $profile = ensure_user_profile($signup['data'], ['authProvider' => 'admin_recovery']);
    $uid = $signup['data']['localId'] ?? '';
    api_log($VERSION . ' CREATED_ADMIN_AUTH email=' . $email . ' uid=' . $uid);
    json_response(['ok' => true, 'version' => $VERSION, 'mode' => 'created', 'uid' => $uid, 'message' => 'Admin Firebase Auth account was recreated. You can now sign in with the new password.']);
}

$code = $signup['data']['error']['message'] ?? '';
if ($code !== 'EMAIL_EXISTS') {
    json_response(['ok' => false, 'version' => $VERSION, 'message' => 'Firebase could not create admin auth account: ' . $code, 'raw' => $signup['data'] ?? $signup], 500);
}

json_response([
    'ok' => false,
    'version' => $VERSION,
    'message' => 'The admin email already exists in Firebase Auth. Use Firebase Console password reset/change for this user, then sign in normally.',
    'firebaseCode' => $code
], 409);
