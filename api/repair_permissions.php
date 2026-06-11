<?php
require_once __DIR__ . '/config.php';
require_admin_user();
$dir = __DIR__ . '/_data';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
@chmod($dir, 0775);
$files = [
    'manual_payments.json' => '[]',
    'profiles.json' => '{}',
    'premium_overrides.json' => '{}',
    'subscriptions.json' => '[]',
    'trading_notes.json' => '[]',
    'announcements.json' => '[]',
    'testimonials.json' => '[]',
];
foreach ($files as $file => $default) {
    $path = $dir . '/' . $file;
    if (!file_exists($path)) @file_put_contents($path, $default, LOCK_EX);
    @chmod($path, 0664);
}
$ht = $dir . '/.htaccess';
if (!file_exists($ht)) @file_put_contents($ht, "Require all denied
", LOCK_EX);
@chmod($ht, 0644);
json_response(['ok'=>true,'message'=>'Storage permissions repair attempted.','dir_writable'=>is_writable($dir)]);
