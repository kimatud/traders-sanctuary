<?php
require 'vendor/autoload.php';
use Kreait\Firebase\Factory;

$stkCallbackResponse = file_get_contents('php://input');
$logFile = "mpesa_log.txt";
file_put_contents($logFile, $stkCallbackResponse . "\n", FILE_APPEND);

$data = json_decode($stkCallbackResponse);

if (isset($data->Body->stkCallback->ResultCode) && $data->Body->stkCallback->ResultCode == 0) {

    $checkoutId = $data->Body->stkCallback->CheckoutRequestID;

    try {
        // --- FINAL FIX: Use the full, absolute server path to the credentials file ---
        $credentialsPath = '/home/gcxbdjfw/public_html/firebase_credentials.json';

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $firestore = $factory->createFirestore();
        $db = $firestore->database();

        // --- FINAL FIX 2: Find the user by CheckoutRequestID ---
        $paymentsRef = $db->collectionGroup('payments')->where('checkoutId', '==', $checkoutId)->documents();

        $userId = null;
        if (!$paymentsRef->isEmpty()) {
            $paymentDoc = $paymentsRef->rows()[0];
            $userRef = $paymentDoc->reference()->parent()->parent();
            $userId = $userRef->id();
        }

        if ($userId) {
            $userProfileRef = $db->collection('artifacts/traders-sanctuary/users')->document($userId)->collection('profile')->document('data');

            // Update the user's role to 'premium'
            $userProfileRef->update([
                ['path' => 'role', 'value' => 'premium'],
                ['path' => 'subscriptionStatus', 'value' => 'active']
            ]);

            // Optionally, update the payment document status
            $paymentDoc->reference()->update([
                ['path' => 'status', 'value' => 'completed']
            ]);

            file_put_contents($logFile, " - SUCCESS: User " . $userId . " updated to premium.\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, " - ERROR: Could not find user for Checkout ID " . $checkoutId . "\n", FILE_APPEND);
        }

    } catch (Exception $e) {
        file_put_contents($logFile, " - DATABASE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    $errorMessage = $data->Body->stkCallback->ResultDesc ?? 'Unknown error';
    file_put_contents($logFile, " - FAILED: " . $errorMessage . "\n", FILE_APPEND);
}
?>