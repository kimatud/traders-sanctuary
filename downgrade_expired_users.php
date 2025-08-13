<?php
// THIS SCRIPT IS FOR YOUR DAILY CRON JOB
require 'vendor/autoload.php';
use Google\Cloud\Firestore\FirestoreClient;

// YOU MUST INVENT THIS SECRET KEY. IT'S YOUR SCRIPT'S PASSWORD.
define('CRON_SECRET', 'YourOwnInventedPasswordForCronJobHere123!');

if (!isset($_GET['secret']) || $_GET['secret'] !== CRON_SECRET) {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied');
}

$firebaseProjectID = 'traders-sanctuary';
$appId = $firebaseProjectID;

try {
    $firestore = new FirestoreClient([
        'projectId' => $firebaseProjectID,
        'keyFilePath' => __DIR__ . '/serviceAccountKey.json'
    ]);
    
    $gracePeriodCutoff = new DateTime();
    $gracePeriodCutoff->modify('-3 days');

    // NOTE: This query requires a composite index in Firestore. 
    // Firestore will give you a link to create it when you first run the script if it fails.
    $usersRef = $firestore->collection("artifacts/{$appId}/users");
    $query = $usersRef->where('profile.data.role', '==', 'premium')
                      ->where('profile.data.premiumExpiresAt', '<', $gracePeriodCutoff->format('c'));
    
    $expiredUsersSnapshot = $query->documents();
    
    $downgradeCount = 0;
    $batch = $firestore->batch();

    foreach ($expiredUsersSnapshot as $userDoc) {
        if ($userDoc->exists()) {
            $userProfileRef = $userDoc->reference()->collection('profile')->document('data');
            $batch->update($userProfileRef, [['path' => 'role', 'value' => 'member']]);
            $downgradeCount++;
        }
    }
    
    if ($downgradeCount > 0) {
        $batch->commit();
        echo "Successfully downgraded {$downgradeCount} users.";
    } else {
        echo "No expired users to downgrade.";
    }

} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    error_log('Downgrade script failed: ' . $e->getMessage());
    die('An error occurred.');
}
?>