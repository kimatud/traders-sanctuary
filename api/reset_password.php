<?php
require __DIR__ . '/config.php';
require_method('POST');
$data = read_json_body();
$email = trim($data['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 400);
}
$result = firebase_auth_request('accounts:sendOobCode', [
    'requestType' => 'PASSWORD_RESET',
    'email' => $email,
]);
// Return success even when Firebase refuses unknown emails to avoid account enumeration.
json_response(['ok' => true, 'message' => 'If that email exists, a password reset link has been sent.']);
