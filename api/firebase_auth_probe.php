<?php
require __DIR__ . '/config.php';
require_method('GET');

// Hardened for production: this probe exposes Firebase transport details.
// Access is allowed only for an admin session or a server-side DIAGNOSTICS_TOKEN.
$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}

$VERSION = 'FIREBASE_AUTH_CONNECTIVITY_PROBE_2026_06_07_LOCKED';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$probe = firebase_auth_request('accounts:signInWithPassword', [
    'email' => 'diagnostic@example.com',
    'password' => 'diagnostic-password',
    'returnSecureToken' => true,
]);
$details = firebase_auth_error_details($probe);

json_response([
    'ok' => true,
    'version' => $VERSION,
    'serverTime' => gmdate('c'),
    'projectId' => FIREBASE_PROJECT_ID,
    'apiKeyConfigured' => FIREBASE_WEB_API_KEY !== '' && FIREBASE_WEB_API_KEY !== 'REPLACE_WITH_FIREBASE_WEB_API_KEY',
    'transportStatus' => $details['httpStatus'],
    'expectedCodeForFakeCredentials' => $details['code'],
    'expectedRawMessage' => $details['message'],
    'rawPreview' => $details['rawPreview'],
    'interpretation' => 'For fake credentials, Firebase should return INVALID_LOGIN_CREDENTIALS, EMAIL_NOT_FOUND, or INVALID_PASSWORD. If it returns API_KEY_HTTP_REFERRER_BLOCKED_OR_IDENTITY_TOOLKIT_FORBIDDEN, the Firebase Web API key restriction or Identity Toolkit API access must be corrected in Google Cloud/Firebase.'
]);
