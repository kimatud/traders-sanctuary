<?php
require __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
if (!hash_equals(SUBSCRIPTION_CRON_SECRET, (string)$token)) {
    json_response(['ok' => false, 'message' => 'Unauthorized cron request.'], 403);
}

$store = local_profile_store_read();
$seen = [];
$now = time();
$reminderWindow = PREMIUM_EXPIRY_REMINDER_DAYS * 86400;
$stats = [
    'checked' => 0,
    'reminders_sent' => 0,
    'expired_emails_sent' => 0,
    'downgraded' => 0,
    'email_failures' => 0,
];

foreach ($store as $key => $profile) {
    if (!is_array($profile)) continue;
    $uid = (string)($profile['uid'] ?? '');
    $email = normalize_email_key($profile['email'] ?? '');
    $unique = $uid !== '' ? 'uid:' . $uid : 'email:' . $email;
    if ($email === '' || isset($seen[$unique])) continue;
    $seen[$unique] = true;
    $stats['checked']++;

    if (($profile['role'] ?? '') !== 'premium' || empty($profile['premiumExpiresAt'])) continue;
    $expiryTs = strtotime((string)$profile['premiumExpiresAt']);
    if ($expiryTs === false) continue;

    if ($expiryTs <= $now) {
        if (empty($profile['expiryEmailSentAt'])) {
            $result = send_premium_expired_email($profile);
            if ($result['ok'] ?? false) $stats['expired_emails_sent']++; else $stats['email_failures']++;
        }
        $profile['role'] = 'member';
        $profile['premiumExpiredAt'] = gmdate('c', $now);
        if (empty($profile['expiryEmailSentAt'])) $profile['expiryEmailSentAt'] = gmdate('c', $now);
        save_local_user_profile($uid, $email, $profile);
        $stats['downgraded']++;
        continue;
    }

    $secondsLeft = $expiryTs - $now;
    if ($secondsLeft <= $reminderWindow && empty($profile['premiumExpiryReminderSentAt'])) {
        $daysLeft = max(1, (int)ceil($secondsLeft / 86400));
        $result = send_premium_expiry_reminder_email($profile, $daysLeft);
        if ($result['ok'] ?? false) {
            $stats['reminders_sent']++;
            $profile['premiumExpiryReminderSentAt'] = gmdate('c', $now);
            save_local_user_profile($uid, $email, $profile);
        } else {
            $stats['email_failures']++;
            api_log('Premium reminder email failed for ' . $email . ': ' . json_encode($result));
        }
    }
}

json_response(['ok' => true, 'stats' => $stats]);
