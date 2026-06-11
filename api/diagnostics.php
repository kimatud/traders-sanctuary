<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}
$out = [
    'ok' => true,
    'php_version' => PHP_VERSION,
    'curl_enabled' => function_exists('curl_init'),
    'allow_url_fopen' => (bool)ini_get('allow_url_fopen'),
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'google_client_configured' => GOOGLE_CLIENT_ID !== 'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_ID',
];
$test = http_request('https://oauth2.googleapis.com/token', null, 'GET');
$out['google_outbound_test'] = [
    'reachable' => $test['status'] > 0,
    'status' => $test['status'],
    'message' => $test['error']['message'] ?? ($test['data']['error'] ?? 'ok')
];
echo json_encode($out, JSON_PRETTY_PRINT);
