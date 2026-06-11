<?php
require __DIR__ . '/config.php';
$returnTo = safe_return_to($_GET['returnTo'] ?? '/dashboard');
api_log('GOOGLE_START_LEGACY_ALIAS_TO_V4 returnTo=' . $returnTo . ' redirectUri=' . GOOGLE_REDIRECT_URI);
if (GOOGLE_CLIENT_ID === 'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_ID' || GOOGLE_CLIENT_SECRET === 'REPLACE_WITH_GOOGLE_OAUTH_CLIENT_SECRET') {
    header('Location: ' . $returnTo . '?auth_error=' . urlencode('Google OAuth is not configured on the server yet.'));
    exit;
}
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['oauth_return_to'] = $returnTo;
$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['oauth_state'],
    'prompt' => 'select_account',
    'access_type' => 'online',
]);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
