<?php
header('Content-Type: application/json');
// In production, you might want to restrict the origin for better security
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['uid'] ?? null;

if (!$userId) {
    echo json_encode(['error' => 'User ID is missing.']);
    exit;
}

// --- Configuration ---
// Use the first API Key you provided. You can replace it with the new one if you wish.
$coinbaseApiKey = '336efa10-31b1-474a-98a4-4f26f2ba3902'; 
$coinbaseApiUrl = 'https://api.commerce.coinbase.com/charges';

// --- Prepare the Charge Data ---
$postData = json_encode([
    'name' => 'Traders Sanctuary Premium',
    'description' => '1 Month Subscription',
    'pricing_type' => 'fixed_price',
    'local_price' => [
        'amount' => '0.01', // Set your actual price here
        'currency' => 'USD'
    ],
    'redirect_url' => 'https://YOUR-WEBSITE.COM/dashboard?payment=success',
    'cancel_url' => 'https://YOUR-WEBSITE.COM/premium',
    'metadata' => [
        'user_id' => $userId
    ]
]);

// --- Use cURL to Communicate with Coinbase Commerce ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $coinbaseApiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-CC-Api-Key: ' . $coinbaseApiKey,
    'X-CC-Version: 2018-03-22'
]);

$response = curl_exec($ch);
curl_close($ch);

$responseData = json_decode($response, true);

if (isset($responseData['data']['hosted_url'])) {
    echo json_encode(['hosted_url' => $responseData['data']['hosted_url']]);
} else {
    header('HTTP/1.0 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to create Coinbase charge.', 'details' => $responseData['errors'] ?? 'Unknown error']);
}
?>