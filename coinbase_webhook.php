<?php
// Include the Composer autoloader to load the Google Cloud library
require 'vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Core\Timestamp;
use DateTime;
use DateInterval;

// --- Step 1: Replace these placeholders with your actual information ---

// Find this in your Coinbase Commerce dashboard under Settings -> Webhooks
$coinbaseWebhookSecret = 'e5ef79d1-0a23-44f6-87d0-231758c04738';

// The path to the JSON file you downloaded from your Google Cloud project
$firebaseServiceAccountJsonPath = 'PATH_TO_YOUR_FIREBASE_SERVICE_ACCOUNT_JSON';

// Your Firebase Project ID (e.g., "traders-sanctuary")
$firebaseProjectId = 'YOUR_FIREBASE_PROJECT_ID';

// The App ID from your Firestore path structure (e.g., "traders-sanctuary")
$appId = 'YOUR_APP_ID';


// --- Step 2: Receive and Verify the Webhook from Coinbase (Security Check) ---

$requestBody = file_get_contents('php://input');
$requestSignature = isset($_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] : '';

if (empty($requestSignature)) {
    http_response_code(400);
    error_log('Webhook Error: Missing signature');
    exit('Signature is missing.');
}

// Compute the signature to verify the request is genuinely from Coinbase
$computedSignature = hash_hmac('sha256', $requestBody, $coinbaseWebhookSecret);

if (!hash_equals($computedSignature, $requestSignature)) {
    http_response_code(401);
    error_log('Webhook Error: Invalid signature');
    exit('Invalid signature.');
}


// --- Step 3: Process the Payment Event ---

try {
    $event = json_decode($requestBody, true);

    // Check if the event is a confirmed (successful) charge
    if (isset($event['event']['type']) && $event['event']['type'] === 'charge:confirmed') {
        
        // Extract the user's ID from the metadata you sent when creating the charge
        $userId = isset($event['event']['data']['metadata']['uid']) ? $event['event']['data']['metadata']['uid'] : null;

        if ($userId) {
            // --- Step 4: Connect to Firestore ---
            $firestore = new FirestoreClient([
                'keyFilePath' => $firebaseServiceAccountJsonPath,
                'projectId' => $firebaseProjectId,
            ]);

            // Define the path to the user's profile document
            $userProfileRef = $firestore->document("artifacts/{$appId}/users/{$userId}/profile/data");
            
            // --- Step 5: Calculate Expiration Date and Update User Role ---
            $expiryDate = new DateTime();
            $expiryDate->add(new DateInterval('P30D')); // P30D = Period of 30 Days

            $userProfileRef->set([
                'role' => 'premium',
                'premiumAccessExpiresAt' => new Timestamp($expiryDate) // Use Firestore's Timestamp object
            ], ['merge' => true]);

            error_log("User {$userId} successfully upgraded to premium.");

        } else {
            error_log('Webhook Warning: Charge confirmed but no user ID (uid) found in metadata.');
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Webhook Processing Error: ' . $e->getMessage());
    exit('An error occurred.');
}


// --- Step 6: Send a Success Response to Coinbase ---
// This tells Coinbase you have successfully received the notification.
http_response_code(200);
echo 'Webhook processed successfully.';

?>