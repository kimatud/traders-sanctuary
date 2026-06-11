<?php
require __DIR__ . '/config.php';
require_method('GET');
$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}

$started = microtime(true);
$dirs = [
    'api/_data' => API_DATA_DIR,
    'funded certificates' => dirname(__DIR__) . '/funded certificates',
    'study materials' => dirname(__DIR__) . '/study materials',
    'trade_screenshots' => dirname(__DIR__) . '/trade_screenshots',
    'announcement_files' => dirname(__DIR__) . '/announcement_files',
];
$dirStatus = [];
foreach ($dirs as $name => $path) {
    ensure_dir($path);
    $test = rtrim($path, '/') . '/.write_test_' . bin2hex(random_bytes(3));
    $writeOk = @file_put_contents($test, 'ok', LOCK_EX) !== false;
    if ($writeOk) @unlink($test);
    $dirStatus[$name] = [
        'exists' => is_dir($path),
        'writable' => is_writable($path),
        'write_test' => $writeOk,
    ];
}

json_response([
    'ok' => true,
    'version' => 'PERFORMANCE_SECURITY_CHECK_2026_05_31',
    'serverTime' => gmdate('c'),
    'elapsedMs' => round((microtime(true) - $started) * 1000, 2),
    'php' => [
        'version' => PHP_VERSION,
        'opcache' => function_exists('opcache_get_status') ? (bool)@opcache_get_status(false) : false,
        'curl' => function_exists('curl_init'),
        'allow_url_fopen' => (bool)ini_get('allow_url_fopen'),
    ],
    'session' => [
        'active' => session_status() === PHP_SESSION_ACTIVE,
        'timeoutSeconds' => SESSION_TIMEOUT_SECONDS,
        'secureCookie' => session_get_cookie_params()['secure'] ?? null,
        'httpOnly' => session_get_cookie_params()['httponly'] ?? null,
        'sameSite' => session_get_cookie_params()['samesite'] ?? null,
    ],
    'config' => [
        'firebaseWebApiKeyConfigured' => FIREBASE_WEB_API_KEY !== '',
        'googleOAuthConfigured' => GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '',
        'smtpReady' => mail_config_is_ready(),
        'cronSecretChanged' => SUBSCRIPTION_CRON_SECRET !== 'CHANGE_ME',
        'diagnosticsTokenConfigured' => ts_env('DIAGNOSTICS_TOKEN', '') !== '',
    ],
    'directories' => $dirStatus,
]);
