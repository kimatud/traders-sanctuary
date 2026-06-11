<?php
require __DIR__ . '/config.php';
require_method('POST');

$VERSION = 'LOGIN_SECURE_FAST_2026_05_31_V6';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$data = read_json_body();
$email = strtolower(trim($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
api_log($VERSION . ' ATTEMPT email=' . $email . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    api_log($VERSION . ' REJECTED_INVALID_INPUT email=' . $email);
    json_response(['ok' => false, 'version' => $VERSION, 'message' => 'Please enter a valid email and password.'], 200);
}

auth_rate_limit_check('login', $email, 8, 15 * 60);

$result = firebase_auth_request('accounts:signInWithPassword', [
    'email' => $email,
    'password' => $password,
    'returnSecureToken' => true,
]);

if (!$result['ok']) {
    $details = firebase_auth_error_details($result);
    $firebaseCode = $details['code'];
    $httpStatus = $details['httpStatus'];
    api_log($VERSION . ' FIREBASE_FAILED email=' . $email . ' http=' . $httpStatus . ' code=' . $firebaseCode . ' message=' . $details['message'] . ' rawPreview=' . $details['rawPreview']);

    $publicMessage = 'Invalid email or password. Please check your details and try again.';
    if (in_array($firebaseCode, ['EMAIL_NOT_FOUND', 'INVALID_PASSWORD', 'INVALID_LOGIN_CREDENTIALS'], true)) {
        $publicMessage = 'Invalid email or password. Please check your details and try again.';
    } elseif ($firebaseCode === 'USER_DISABLED') {
        $publicMessage = 'This account is disabled. Please contact support.';
    } elseif ($firebaseCode === 'TOO_MANY_ATTEMPTS_TRY_LATER') {
        $publicMessage = 'Too many attempts. Please wait a few minutes, then try again.';
    } elseif ($firebaseCode === 'OPERATION_NOT_ALLOWED') {
        $publicMessage = 'Email/password sign-in is not enabled yet. Please contact support.';
    }

    json_response([
        'ok' => false,
        'version' => $VERSION,
        'message' => $publicMessage,
        'firebaseCode' => $firebaseCode
    ], 200);
}

try {
    $profile = ensure_user_profile($result['data'], ['authProvider' => 'password']);
    $user = start_user_session($result['data'], $profile);
    auth_rate_limit_clear('login', $email);
    api_log($VERSION . ' SUCCESS email=' . $email . ' uid=' . ($user['uid'] ?? ''));
    json_response(['ok' => true, 'version' => $VERSION, 'user' => $user, 'redirect' => '/dashboard']);
} catch (Throwable $e) {
    api_log($VERSION . ' SESSION_OR_PROFILE_FAILED email=' . $email . ' error=' . $e->getMessage() . ' file=' . $e->getFile() . ':' . $e->getLine());
    json_response([
        'ok' => false,
        'version' => $VERSION,
        'message' => 'Login was accepted, but the app could not open your session. Please try again.',
        'source' => 'login_session_or_profile'
    ], 500);
}
