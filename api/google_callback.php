<?php
require __DIR__ . '/config.php';

try {
    $returnTo = safe_return_to($_SESSION['oauth_return_to'] ?? '/dashboard');

    if (empty($_GET['state']) || !hash_equals($_SESSION['oauth_state'] ?? '', $_GET['state'])) {
        redirect_with_auth_error('Invalid Google sign-in session. Please try again.', '/');
    }

    if (!empty($_GET['error'])) {
        redirect_with_auth_error('Google sign-in failed: ' . (string)$_GET['error'], '/');
    }

    if (empty($_GET['code'])) {
        redirect_with_auth_error('Google sign-in was cancelled or failed.', '/');
    }

    $tokenResponse = http_form_request('https://oauth2.googleapis.com/token', [
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);

    if (!$tokenResponse['ok'] || empty($tokenResponse['data']['id_token'])) {
        $msg = $tokenResponse['data']['error_description'] ?? $tokenResponse['data']['error'] ?? $tokenResponse['error']['message'] ?? 'Could not exchange Google authorization code.';
        api_log('Google token exchange failed: ' . $msg);
        redirect_with_auth_error('Could not complete Google sign-in. Please try again.', '/');
    }

    $idToken = $tokenResponse['data']['id_token'] ?? '';
    $accessToken = $tokenResponse['data']['access_token'] ?? '';

    $firebaseResult = ['ok' => false, 'data' => ['error' => ['message' => 'MISSING_GOOGLE_TOKEN']]];

    // Firebase accepts a Google ID token for accounts:signInWithIdp, but some OAuth
    // client/provider combinations return an ID token Firebase rejects while the
    // access token still works. Try ID token first, then access token as a safe fallback.
    if ($idToken !== '') {
        $firebaseResult = firebase_auth_request('accounts:signInWithIdp', [
            'postBody' => 'id_token=' . rawurlencode($idToken) . '&providerId=google.com',
            'requestUri' => SITE_BASE_URL,
            'returnIdpCredential' => true,
            'returnSecureToken' => true,
        ]);
    }

    if (!$firebaseResult['ok'] && $accessToken !== '') {
        api_log('Firebase Google ID token sign-in failed; retrying with access token: ' . json_encode($firebaseResult['data'] ?? $firebaseResult));
        $firebaseResult = firebase_auth_request('accounts:signInWithIdp', [
            'postBody' => 'access_token=' . rawurlencode($accessToken) . '&providerId=google.com',
            'requestUri' => SITE_BASE_URL,
            'returnIdpCredential' => true,
            'returnSecureToken' => true,
        ]);
    }

    if (!$firebaseResult['ok']) {
        api_log('Firebase Google sign-in failed after fallback: ' . json_encode($firebaseResult['data'] ?? $firebaseResult));
        redirect_with_auth_error(firebase_error_message($firebaseResult, 'Could not complete Firebase Google sign-in.'), '/');
    }

    $authData = $firebaseResult['data'];
    $profile = ensure_user_profile($authData, [
        'authProvider' => 'google.com',
        'displayName' => $authData['displayName'] ?? '',
        'photoURL' => $authData['photoUrl'] ?? '',
    ]);

    start_user_session($authData, $profile);
    unset($_SESSION['oauth_state'], $_SESSION['oauth_return_to']);
    header('Location: ' . $returnTo);
    exit;
} catch (Throwable $e) {
    api_log('Fatal Google callback error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    redirect_with_auth_error('Server error while completing Google sign-in. Please contact support.', '/');
}
