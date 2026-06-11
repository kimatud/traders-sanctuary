<?php
require __DIR__ . '/config.php';
require_method('POST');
$data = read_json_body();
$email = trim($data['email'] ?? '');
$password = (string)($data['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    json_response(['ok' => false, 'message' => 'Enter a valid email and a password with at least 8 characters.'], 200);
}

auth_rate_limit_check('register', strtolower($email), 5, 30 * 60);

$result = firebase_auth_request('accounts:signUp', [
    'email' => $email,
    'password' => $password,
    'returnSecureToken' => true,
]);
if (!$result['ok']) {
    $details = firebase_auth_error_details($result);
    $code = $details['code'];
    $message = $code === 'EMAIL_EXISTS'
        ? 'That email is already registered. Please log in instead.'
        : ($code === 'OPERATION_NOT_ALLOWED' ? 'Registration is not enabled yet. Please contact support.' : 'Registration failed. Please check your details and try again.');
    json_response(['ok' => false, 'message' => $message, 'firebaseCode' => $code], 200);
}
$profile = ensure_user_profile($result['data'], [
    'authProvider' => 'password',
    'termsAgreed' => false,
]);
$user = start_user_session($result['data'], $profile);
auth_rate_limit_clear('register', strtolower($email));
json_response(['ok' => true, 'user' => $user, 'redirect' => '/agreement']);
