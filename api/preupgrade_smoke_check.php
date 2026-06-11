<?php
require __DIR__ . '/config.php';
require_method('GET');

$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}

$started = microtime(true);
$root = dirname(__DIR__);
$coreFiles = [
    'index.html', '.htaccess', 'firestore.rules',
    'api/config.php', 'api/data.php', 'api/session_from_id_token.php',
    'api/login.php', 'api/login_v4.php', 'api/me.php', 'api/logout.php',
    'api/preupgrade_smoke_check.php',
];
$fileStatus = [];
foreach ($coreFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    $fileStatus[$file] = [
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? filesize($path) : 0,
    ];
}

$directories = [
    'api/_data' => API_DATA_DIR,
    'study materials' => $root . '/study materials',
    'funded certificates' => $root . '/funded certificates',
    'funded certificates/pending' => $root . '/funded certificates/pending',
    'trade_screenshots' => $root . '/trade_screenshots',
    'announcement_files' => $root . '/announcement_files',
];
$dirStatus = [];
foreach ($directories as $label => $path) {
    ensure_dir($path);
    $probe = rtrim($path, '/') . '/.smoke_' . bin2hex(random_bytes(4));
    $writeOk = @file_put_contents($probe, 'ok', LOCK_EX) !== false;
    if ($writeOk) @unlink($probe);
    $dirStatus[$label] = [
        'exists' => is_dir($path),
        'writable' => is_writable($path),
        'writeTest' => $writeOk,
    ];
}

$index = @file_get_contents($root . '/index.html') ?: '';
$inlineBundleLikelyPresent = strpos($index, 'type="module"') !== false && strlen($index) > 500000;
$tailwindCdnPresent = strpos($index, 'cdn.tailwindcss.com') !== false;
$dangerousPublicFiles = [
    'api/env.local.php' => is_file(__DIR__ . '/env.local.php'),
    'api/_data/auth_error.log' => is_file(API_DATA_DIR . '/auth_error.log'),
];

$missing = array_keys(array_filter($fileStatus, fn($s) => empty($s['exists']) || empty($s['readable'])));
$unwritable = array_keys(array_filter($dirStatus, fn($s) => empty($s['writeTest'])));

json_response([
    'ok' => count($missing) === 0 && count($unwritable) === 0,
    'version' => 'PREUPGRADE_SMOKE_CHECK_2026_06_07',
    'serverTime' => gmdate('c'),
    'elapsedMs' => round((microtime(true) - $started) * 1000, 2),
    'summary' => [
        'missingOrUnreadableCoreFiles' => $missing,
        'unwritableDirectories' => $unwritable,
        'inlineIndexBundleLikelyPresent' => $inlineBundleLikelyPresent,
        'tailwindCdnPresent' => $tailwindCdnPresent,
        'diagnosticsTokenConfigured' => ts_env('DIAGNOSTICS_TOKEN', '') !== '',
        'smtpReady' => mail_config_is_ready(),
        'firebaseApiKeyConfigured' => FIREBASE_WEB_API_KEY !== '',
        'googleOAuthConfigured' => GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '',
    ],
    'files' => $fileStatus,
    'directories' => $dirStatus,
    'privateFilePresence' => $dangerousPublicFiles,
]);
