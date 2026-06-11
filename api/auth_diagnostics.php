<?php
require __DIR__ . '/config.php';
require_method('GET');

$version = 'AUTH_DIAGNOSTICS_2026_05_19_V6_REFERRER_FIXED';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
    @opcache_invalidate(__DIR__ . '/login_v4.php', true);
    @opcache_invalidate(__DIR__ . '/login.php', true);
    @opcache_invalidate(__DIR__ . '/config.php', true);
}

ensure_dir(API_DATA_DIR);
$log = API_DATA_DIR . '/auth_error.log';
$writeTestFile = API_DATA_DIR . '/auth_v4_write_test.txt';
$writeOk = @file_put_contents($writeTestFile, '[' . gmdate('c') . '] v4 diagnostics write test' . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
$tail = [];
if (is_file($log) && is_readable($log)) {
    $lines = @file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $tail = array_slice($lines, -40);
    $tail = array_map(function($line) {
        $line = preg_replace('/(key=)[^&\s]+/i', '$1[hidden]', $line);
        $line = preg_replace('/(password|idToken|refreshToken)":"[^"]+/i', '$1":"[hidden]', $line);
        return $line;
    }, $tail);
}

$identityToolkit = http_json_request(
    'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode(FIREBASE_WEB_API_KEY),
    ['idToken' => 'diagnostic-invalid-token'],
    'POST'
);
$signInProbe = http_json_request(
    'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . urlencode(FIREBASE_WEB_API_KEY),
    ['email' => 'diagnostic@example.com', 'password' => 'diagnostic-password', 'returnSecureToken' => true],
    'POST'
);
$googleTokenProbe = http_form_request('https://oauth2.googleapis.com/token', [
    'grant_type' => 'authorization_code',
    'code' => 'diagnostic-invalid-code',
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
]);

json_response([
    'ok' => true,
    'version' => $version,
    'serverTime' => gmdate('c'),
    'deployedFile' => __FILE__,
    'loginEndpointExpectedByFrontend' => '/api/login_v4.php',
    'googleStartExpectedByFrontend' => '/api/google_start_v4.php',
    'sessionActive' => session_status() === PHP_SESSION_ACTIVE,
    'sessionName' => session_name(),
    'sessionIdPrefix' => substr(session_id(), 0, 8),
    'hasSessionUser' => !empty($_SESSION['user']),
    'sessionUserEmail' => $_SESSION['user']['email'] ?? null,
    'hasIdToken' => !empty($_SESSION['idToken']),
    'firebaseProjectId' => FIREBASE_PROJECT_ID,
    'firebaseApiKeyConfigured' => FIREBASE_WEB_API_KEY !== '' && FIREBASE_WEB_API_KEY !== 'REPLACE_WITH_FIREBASE_WEB_API_KEY',
    'googleClientConfigured' => GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '' && strpos(GOOGLE_CLIENT_ID, 'REPLACE_WITH') === false,
    'googleRedirectUri' => GOOGLE_REDIRECT_URI,
    'serviceAccountPresent' => defined('FIREBASE_SERVICE_ACCOUNT_PATH') && is_file(FIREBASE_SERVICE_ACCOUNT_PATH),
    'curlAvailable' => function_exists('curl_init'),
    'allowUrlFopen' => (bool)ini_get('allow_url_fopen'),
    'apiDataDir' => API_DATA_DIR,
    'apiDataDirExists' => is_dir(API_DATA_DIR),
    'apiDataDirWritable' => is_writable(API_DATA_DIR),
    'diagnosticsWriteOk' => $writeOk,
    'authLogPath' => $log,
    'authLogExists' => is_file($log),
    'identityToolkitLookupProbe' => [
        'transportOk' => (int)($identityToolkit['status'] ?? 0) > 0,
        'httpStatus' => $identityToolkit['status'] ?? 0,
        'expectedMessageForInvalidToken' => $identityToolkit['data']['error']['message'] ?? $identityToolkit['error']['message'] ?? null,
    ],
    'identityToolkitSignInProbe' => [
        'transportOk' => (int)($signInProbe['status'] ?? 0) > 0,
        'httpStatus' => $signInProbe['status'] ?? 0,
        'expectedFirebaseCodeForFakeUser' => firebase_auth_error_details($signInProbe)['code'] ?? null,
        'rawMessage' => firebase_auth_error_details($signInProbe)['message'] ?? null,
        'rawPreview' => firebase_auth_error_details($signInProbe)['rawPreview'] ?? null,
    ],
    'googleOauthTokenEndpointProbe' => [
        'transportOk' => (int)($googleTokenProbe['status'] ?? 0) > 0,
        'httpStatus' => $googleTokenProbe['status'] ?? 0,
        'expectedErrorForInvalidCode' => $googleTokenProbe['data']['error'] ?? $googleTokenProbe['error']['message'] ?? null,
        'expectedErrorDescription' => $googleTokenProbe['data']['error_description'] ?? null,
    ],
    'lastAuthLogLines' => $tail,
    'adminAuthRecoveryEndpoint' => '/api/admin_auth_recovery.php',
    'adminAuthRecoveryEnabled' => getenv('ADMIN_AUTH_RECOVERY_TOKEN') !== '',
    'important' => 'If login fails, check lastAuthLogLines and the login response firebaseCode. INVALID_LOGIN_CREDENTIALS means Firebase Auth does not have that email/password pair.',
]);
