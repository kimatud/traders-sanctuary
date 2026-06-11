<?php
require __DIR__ . '/config.php';
require_method('GET');
$user = require_current_user();
$paymentsFile = __DIR__ . '/_data/manual_payments.json';
$payments = [];
if (is_file($paymentsFile)) {
    $decoded = json_decode(file_get_contents($paymentsFile) ?: '[]', true);
    if (is_array($decoded)) $payments = $decoded;
}
$mine = array_values(array_filter($payments, function($p) use ($user) {
    return ($p['userId'] ?? '') === ($user['uid'] ?? '') || strtolower($p['email'] ?? '') === strtolower($user['email'] ?? '');
}));
usort($mine, fn($a, $b) => strtotime($b['createdAt'] ?? '') <=> strtotime($a['createdAt'] ?? ''));
json_response([
    'ok' => true,
    'role' => $user['role'] ?? 'member',
    'premiumExpiresAt' => $user['premiumExpiresAt'] ?? null,
    'latestPayment' => $mine[0] ?? null,
    'payments' => $mine,
]);
