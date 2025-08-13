<?php
// Include Firestore library
require 'vendor/autoload.php';
use Google\Cloud\Firestore\FirestoreClient;

// --- Configuration ---
// This is the "Shared secret" from your Coinbase Commerce Webhook settings.
$webhookSharedSecret = 'e5ef79d1-0a23-44f6-87d0-231758c04738'; 
$firebaseProjectID = 'traders-sanctuary';
$appId = $firebaseProjectID;

// --- Security Check: Verify the Webhook Signature ---
$requestBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'];

$computedSignature = hash_hmac('sha256', $requestBody, $webhookSharedSecret);

// This check is critical for security. It ensures the request is from Coinbase.
if (!hash_equals($computedSignature, $signatureHeader)) {
    http_response_code(403);
    die('Invalid signature');
}

// --- Process the Validated Webhook ---
$event = json_decode($requestBody, true);

// We only grant premium access when the charge is confirmed.
if (isset($event['event']['type']) && $event['event']['type'] === 'charge:confirmed') {
    
    // Get the user ID from the metadata we sent when creating the charge
    $userId = $event['event']['data']['metadata']['user_id'] ?? null;

    if ($userId) {
        try {
            $firestore = new FirestoreClient([
                'projectId' => $firebaseProjectID,
                'keyFilePath' => __DIR__ . '/serviceAccountKey.json'
            ]);

            $userProfileRef = $firestore->document("artifacts/{$appId}/users/{$userId}/profile/data");
            
            // Set the expiry date for 30 days from now
            $premiumExpiresAt = (new DateTime())->modify('+30 days')->format('c');
            
            // Update the user's document in Firestore
            $userProfileRef->update([
                ['path' => 'role', 'value' => 'premium'],
                ['path' => 'premiumExpiresAt', 'value' => $premiumExpiresAt],
                ['path' => 'premiumSince', 'value' => (new DateTime())->format('c')]
            ]);
            
            // Respond to Coinbase to let them know we received the webhook successfully
            http_response_code(200);
            echo 'Webhook processed successfully.';

        } catch (Exception $e) {
            // If there's a database error, log it and respond with an error
            http_response_code(500);
            error_log('Firestore update failed: ' . $e->getMessage());
            echo 'Database update failed.';
        }
    } else {
        // This would happen if the user_id wasn't passed correctly when creating the charge
        http_response_code(400); 
        echo 'User ID missing from metadata.';
    }
} else {
    // For other events like charge:created or charge:failed, we don't need to do anything
    http_response_code(200);
    echo 'Webhook received but no action taken.';
}
?>