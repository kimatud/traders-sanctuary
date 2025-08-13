<?php
// Set the content type to return JSON
header('Content-Type: application/json');

// Include the Composer autoloader to use the Google Cloud Firestore library
// Make sure you run 'composer require google/cloud-firestore' in your project directory
require 'vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

// --- Configuration ---

// 1. PayPal Sandbox Credentials
$paypalClientID = 'AW1n9twRQaOVyB7vp98PC9qYlb5JuvJSKb_Zjj_jUzYHJuZEiQnRkI9GlExcTji-aZ3ySe5TvqR8ipcP';
$paypalSecret = 'ENgArJ4tHrpWSf4XhUhoNIefH83U9NSnswXdfzMAEy4wWR3BMm7uTGYMaQ7gAjc6_yXC0R-KfTKY-f9B';
$paypalAPI = 'https://api.sandbox.paypal.com'; // Use 'https://api.paypal.com' for live mode

// 2. Firebase/Firestore Configuration
$firebaseProjectID = 'traders-sanctuary';
$appId = $firebaseProjectID; 

// --- Helper Functions ---

/**
 * Gets an OAuth2 access token from PayPal.
 * @param string $clientID
 * @param string $secret
 * @param string $apiBase
 * @return string|null The access token or null on failure.
 */
function get_paypal_access_token($clientID, $secret, $apiBase) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiBase . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $clientID . ':' . $secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    
    $data = json_decode($result);
    return $data->access_token ?? null;
}

/**
 * Captures the payment for a given PayPal Order ID to verify completion.
 * @param string $orderID
 * @param string $accessToken
 * @param string $apiBase
 * @return object|null The order details or null on failure.
 */
function capture_paypal_order($orderID, $accessToken, $apiBase) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiBase . '/v2/checkout/orders/' . $orderID . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    
    return json_decode($result);
}

// --- Main Script Logic ---

// Get the data sent from the client-side JavaScript
$input = json_decode(file_get_contents('php://input'), true);
$orderID = $input['orderID'] ?? null;
$uid = $input['uid'] ?? null;

if (!$orderID || !$uid) {
    echo json_encode(['success' => false, 'message' => 'Missing Order ID or User ID.']);
    exit;
}

// 1. Get Access Token
$accessToken = get_paypal_access_token($paypalClientID, $paypalSecret, $paypalAPI);
if (!$accessToken) {
    echo json_encode(['success' => false, 'message' => 'Could not authenticate with PayPal.']);
    exit;
}

// 2. Capture (Verify) Order
$captureDetails = capture_paypal_order($orderID, $accessToken, $paypalAPI);
if (!$captureDetails || !isset($captureDetails->status)) {
    echo json_encode(['success' => false, 'message' => 'Could not verify the order with PayPal.']);
    exit;
}

// 3. Check if payment status is 'COMPLETED'
if ($captureDetails->status === 'COMPLETED') {
    try {
        // 4. Update user role in Firestore
        $firestore = new FirestoreClient([
            'projectId' => $firebaseProjectID,
            // Make sure this file exists in the same directory
            'keyFilePath' => __DIR__ . '/serviceAccountKey.json' 
        ]);

        $userProfileRef = $firestore->document("artifacts/{$appId}/users/{$uid}/profile/data");
        
        // Calculate the expiry date (current time + 30 days)
        $premiumExpiresAt = (new DateTime())->modify('+30 days')->format('c');

        // Prepare the data to update the user's profile
        $updateData = [
            ['path' => 'role', 'value' => 'premium'],
            ['path' => 'premiumSince', 'value' => (new DateTime())->format('c')],
            ['path' => 'premiumExpiresAt', 'value' => $premiumExpiresAt], // <-- Saves the expiry date
            ['path' => 'paymentDetails', 'value' => [
                'method' => 'paypal',
                'orderId' => $orderID,
                'transactionDate' => (new DateTime())->format('c'),
                'amount' => $captureDetails->purchase_units[0]->payments->captures[0]->amount->value,
                'currency' => $captureDetails->purchase_units[0]->payments->captures[0]->amount->currency_code
            ]]
        ];

        // Update the document
        $userProfileRef->update($updateData);

        // 5. Send success response to the client
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database update failed. Please contact support. Error: ' . $e->getMessage()]);
    }
} else {
    // If status is not COMPLETED
    echo json_encode(['success' => false, 'message' => 'Payment was not completed. Status: ' . $captureDetails->status]);
}

?>