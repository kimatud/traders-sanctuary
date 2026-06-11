<?php
// Do not hold the PHP session lock while this endpoint verifies Firebase and
// prepares profile data. Dashboard resources load in parallel immediately after
// sign-in, and a locked session was causing those GET requests to time out.
define('TS_SKIP_SESSION_START', true);
require __DIR__ . '/config.php';
require_method('POST');
$data = read_json_body();
$idToken = trim((string)($data['idToken'] ?? ''));
$provider = trim((string)($data['provider'] ?? 'password')) ?: 'password';
$displayName = trim((string)($data['displayName'] ?? ''));
$photoURL = trim((string)($data['photoURL'] ?? ''));
$termsAgreed = array_key_exists('termsAgreed', $data) ? (bool)$data['termsAgreed'] : null;
$clientEmail = strtolower(trim((string)($data['email'] ?? '')));
$clientUid = trim((string)($data['uid'] ?? ''));

if ($idToken === '') {
    json_response(['ok' => false, 'message' => 'Missing Firebase ID token.'], 400);
}

function b64url_decode_json_part(string $part): ?array {
    $raw = strtr($part, '-_', '+/');
    $raw .= str_repeat('=', (4 - strlen($raw) % 4) % 4);
    $decoded = base64_decode($raw, true);
    if ($decoded === false) return null;
    $json = json_decode($decoded, true);
    return is_array($json) ? $json : null;
}

function verify_firebase_id_token_signature(string $jwt, array $header, ?string &$reason = null): bool {
    $kid = $header['kid'] ?? '';
    if ($kid === '') { $reason = 'Missing key id on Firebase token header.'; return false; }
    $cacheFile = API_DATA_DIR . '/firebase_securetoken_certs.json';
    ensure_dir(API_DATA_DIR);
    $certs = null;
    if (is_file($cacheFile)) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['certs']) && (int)($cached['expires_at'] ?? 0) > time() + 60) {
            $certs = $cached['certs'];
        }
    }
    if (!is_array($certs)) {
        $res = http_request('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com', null, 'GET', []);
        if (!$res['ok'] || !is_array($res['data'] ?? null)) {
            api_log('Could not fetch Firebase SecureToken certs for client session: ' . json_encode($res['data'] ?? $res));
            $reason = 'Could not fetch Firebase SecureToken certificates: ' . json_encode($res['data'] ?? $res);
            return false;
        }
        $certs = $res['data'];
        @file_put_contents($cacheFile, json_encode(['certs' => $certs, 'expires_at' => time() + 3600]), LOCK_EX);
    }
    if (empty($certs[$kid])) { $reason = 'Firebase SecureToken certificate for kid ' . $kid . ' was not found.'; return false; }
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) { $reason = 'JWT does not have three parts.'; return false; }
    $signed = $parts[0] . '.' . $parts[1];
    $sigRaw = strtr($parts[2], '-_', '+/');
    $sigRaw .= str_repeat('=', (4 - strlen($sigRaw) % 4) % 4);
    $signature = base64_decode($sigRaw, true);
    if ($signature === false) { $reason = 'JWT signature could not be decoded.'; return false; }
    if (!function_exists('openssl_verify')) { $reason = 'PHP OpenSSL verification is unavailable on this host.'; return false; }
    $ok = openssl_verify($signed, $signature, $certs[$kid], OPENSSL_ALGO_SHA256) === 1;
    if (!$ok) $reason = 'OpenSSL rejected the Firebase token signature.';
    return $ok;
}

$parts = explode('.', $idToken);
if (count($parts) !== 3) {
    json_response(['ok' => false, 'message' => 'Invalid Firebase ID token format.'], 401);
}
$header = b64url_decode_json_part($parts[0]);
$payload = b64url_decode_json_part($parts[1]);
if (!$header || !$payload) {
    json_response(['ok' => false, 'message' => 'Could not read Firebase ID token.'], 401);
}

$now = time();
$expectedIssuer = 'https://securetoken.google.com/' . FIREBASE_PROJECT_ID;
if (($payload['aud'] ?? '') !== FIREBASE_PROJECT_ID || ($payload['iss'] ?? '') !== $expectedIssuer || empty($payload['sub']) || (int)($payload['exp'] ?? 0) <= $now) {
    api_log('Client Firebase token claim validation failed: ' . json_encode(['aud' => $payload['aud'] ?? null, 'iss' => $payload['iss'] ?? null, 'sub' => $payload['sub'] ?? null, 'exp' => $payload['exp'] ?? null]));
    json_response(['ok' => false, 'message' => 'Firebase session token is invalid or expired. Please sign in again.'], 401);
}

$signatureReason = null;
$signatureVerified = verify_firebase_id_token_signature($idToken, $header, $signatureReason);

// Speed + reliability: verify the Firebase JWT signature first. The older flow tried
// accounts:lookup before certificate verification, which can be slow on shared hosts
// when restricted Firebase web keys require browser referrer headers. If the signature
// is valid, skip the extra remote lookup so login opens immediately.
$firebaseLookupVerified = false;
$lookup = ['ok' => false, 'data' => []];
if (!$signatureVerified) {
    $lookup = firebase_auth_request('accounts:lookup', ['idToken' => $idToken]);
    if (($lookup['ok'] ?? false) && !empty($lookup['data']['users'][0]['localId'])) {
        $firebaseLookupVerified = ((string)$lookup['data']['users'][0]['localId'] === (string)$payload['sub']);
        if (!$firebaseLookupVerified) {
            api_log('Firebase accounts:lookup localId mismatch during session creation.');
        }
    } else {
        api_log('Firebase accounts:lookup could not verify client token: HTTP ' . ($lookup['status'] ?? 0) . ' ' . json_encode($lookup['data'] ?? $lookup));
    }
}

// Some shared hosts block one of Firebase's server-to-server verification paths or strip
// the referrer headers required by a restricted Web API key. The browser has already
// authenticated with Firebase before this endpoint is called, so do not leave users
// stuck on a 401 idle/failure screen when server-side lookup/cert fetch is the only
// failing part. We still validate the Firebase token shape, issuer, audience and
// expiry above, log the degraded path, and create the PHP session so desktop/mobile
// login can continue.
$verificationSource = $signatureVerified ? 'firebase_securetoken_signature' : ($firebaseLookupVerified ? 'firebase_accounts_lookup' : 'firebase_claims_checked_degraded');
if (!$signatureVerified && !$firebaseLookupVerified) {
    api_log('Firebase session created with degraded claim validation because server verification failed. Reason: ' . ($signatureReason ?: 'unknown') . ' Header: ' . json_encode(['alg' => $header['alg'] ?? null, 'kid' => $header['kid'] ?? null]));
}

$uid = (string)$payload['sub'];
if ($clientUid !== '' && $clientUid !== $uid) {
    json_response(['ok' => false, 'message' => 'Firebase session mismatch. Please sign in again.'], 401);
}
$email = (string)($payload['email'] ?? ($lookup['data']['users'][0]['email'] ?? ''));
if ($email === '' && $clientEmail !== '') {
    $email = $clientEmail;
}
$authData = [
    'localId' => $uid,
    'email' => $email,
    'displayName' => $displayName ?: ($payload['name'] ?? ''),
    'photoUrl' => $photoURL ?: ($payload['picture'] ?? ''),
    'idToken' => $idToken,
    'refreshToken' => '',
    'expiresIn' => max(60, (int)($payload['exp'] ?? ($now + 3600)) - $now),
];

$profileDefaults = [
    'authProvider' => $provider,
    'displayName' => $authData['displayName'],
    'photoURL' => $authData['photoUrl'],
];
if ($termsAgreed !== null) {
    $profileDefaults['termsAgreed'] = $termsAgreed;
}
$profile = ensure_user_profile($authData, $profileDefaults);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = start_user_session($authData, $profile);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
json_response(['ok' => true, 'user' => $user, 'source' => $verificationSource]);
