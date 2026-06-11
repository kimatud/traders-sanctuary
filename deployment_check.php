<?php
require __DIR__ . '/api/config.php';
header('Content-Type: application/json');
$diagToken = (string)($_GET['token'] ?? '');
$expectedDiagToken = ts_env('DIAGNOSTICS_TOKEN', '');
if ($expectedDiagToken === '' || !hash_equals($expectedDiagToken, $diagToken)) {
    require_admin_user();
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$root = __DIR__;
$required = [
  'index.html',
  '.htaccess',
  'api/config.php',
  'api/login.php',
  'api/login_v4.php',
  'api/data.php',
  'api/me.php',
  'api/version.php',
  'api/auth_diagnostics.php',
  'api/auth_diagnostics_v4.php',
  'api/preupgrade_smoke_check.php',
  'list_certificates.php',
];
$files = [];
foreach ($required as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    $files[$file] = [
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? filesize($path) : 0,
    ];
}
$missing = array_values(array_filter(array_keys($files), fn($f) => !$files[$f]['exists']));
echo json_encode([
  'ok' => count($missing) === 0,
  'version' => 'DEPLOYMENT_CHECK_2026_06_07_PREUPGRADE_HARDENED',
  'message' => count($missing) === 0 ? 'All required core files are physically present in this folder.' : 'Some required files are missing from this live folder.',
  'serverTime' => gmdate('c'),
  'root' => $root,
  'documentRoot' => $_SERVER['DOCUMENT_ROOT'] ?? null,
  'missing' => $missing,
  'files' => $files,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
