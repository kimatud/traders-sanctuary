<?php
// Traders Sanctuary backend auth configuration.
// IMPORTANT: Put this api/ folder on the same domain as index.html.
// Update the constants below before going live.

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') || (strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? '')) && strpos((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https') !== false);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (!defined('TS_SKIP_SESSION_START') && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load optional local secrets file if present. Keep api/env.local.php outside git/uploads when possible.
$localEnvFile = __DIR__ . '/env.local.php';
if (is_file($localEnvFile)) {
    require_once $localEnvFile;
}

if (!function_exists('ts_env')) {
    function ts_env(string $key, $default = '') {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) $value = $_ENV[$key];
        if ($value === false && isset($_SERVER[$key])) $value = $_SERVER[$key];
        if ($value === false && defined($key)) $value = constant($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}

if (!function_exists('ts_env_bool')) {
    function ts_env_bool(string $key, bool $default = false): bool {
        $value = ts_env($key, $default ? '1' : '0');
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('ts_env_list')) {
    function ts_env_list(string $key, array $default = []): array {
        $value = ts_env($key, '');
        if ($value === '') return $default;
        return array_values(array_filter(array_map('trim', explode(',', (string)$value))));
    }
}


// Always return valid JSON for fatal backend errors so the frontend never sees
// "server returned an invalid response" without a useful message.
if (!defined('TS_API_FATAL_JSON_GUARD')) {
    define('TS_API_FATAL_JSON_GUARD', true);
    $GLOBALS['TS_JSON_RESPONSE_SENT'] = false;
    register_shutdown_function(function () {
        $error = error_get_last();
        if (!$error) return;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)$error['type'], $fatalTypes, true)) return;
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        $internalMessage = 'Server error in ' . basename((string)$error['file']) . ':' . (int)$error['line'] . ' - ' . (string)$error['message'];
        if (defined('API_DATA_DIR')) {
            @mkdir(API_DATA_DIR, 0775, true);
            @error_log('[' . gmdate('c') . '] FATAL ' . $internalMessage . PHP_EOL, 3, API_DATA_DIR . '/auth_error.log');
        }
        if (empty($GLOBALS['TS_JSON_RESPONSE_SENT'])) {
            echo json_encode([
                'ok' => false,
                'version' => 'API_FATAL_JSON_GUARD_CLEAN_2026_05_31',
                'message' => 'Server error. Please try again or contact support if it continues.'
            ]);
        }
    });
}

// Firebase web API key. This is normally public, but keeping it server-side avoids browser-side blocking.
define('FIREBASE_WEB_API_KEY', ts_env('FIREBASE_WEB_API_KEY', ''));
define('FIREBASE_PROJECT_ID', ts_env('FIREBASE_PROJECT_ID', 'traders-sanctuary'));
// Older frontend builds saved data under the Firebase web app-id namespace.
// Keep reading/writing that namespace so existing journal/testimonial data remains visible.
define('FIREBASE_WEB_APP_ID', ts_env('FIREBASE_WEB_APP_ID', '1:433621117821:web:traders-sanctuary'));

// Google OAuth credentials from Google Cloud Console.
// Create an OAuth Web Client and add this redirect URI:
// https://traderssanctuary.com/api/google_callback.php
define('GOOGLE_CLIENT_ID', ts_env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', ts_env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', ts_env('GOOGLE_REDIRECT_URI', 'https://traderssanctuary.com/api/google_callback.php'));

// Server-side inactivity timeout. Default is 60 minutes for a better dashboard experience.
define('SESSION_TIMEOUT_SECONDS', (int)ts_env('SESSION_TIMEOUT_SECONDS', 60 * 60));

// Manual Safaricom M-PESA premium payment settings.
// Premium payments are intentionally manual: user pays to the Buy Goods Till, submits the
// confirmation code, and admin verifies the request from the Payments panel.
define('PREMIUM_DAYS', 30);
define('PREMIUM_PRICE_KES', '3000');
define('MPESA_PAYMENT_METHOD', 'Safaricom M-PESA Buy Goods Till');
define('MPESA_BUSINESS_NAME', 'Traders Sanctuary');
define('MPESA_PAYBILL', '');
define('MPESA_ACCOUNT_NUMBER', 'PREMIUM');
define('MPESA_TILL_NUMBER', '6723191');
define('MPESA_PHONE_NUMBER', '');
define('SUPPORT_EMERGENCY_CONTACT', '0797671000');
define('MPESA_ADMIN_NOTE', 'Pay via Safaricom M-PESA Buy Goods Till 6723191, then submit the M-PESA confirmation code for admin verification.');

// Emails listed here always keep administrator rights in the backend session,
// even if Firestore is slow/unavailable or an older profile says otherwise.
define('ADMIN_EMAILS', ts_env_list('ADMIN_EMAILS', ['denniskimatu4028@gmail.com']));


function is_configured_admin_email(string $email): bool {
    $normalized = strtolower(trim($email));
    foreach ((array)ADMIN_EMAILS as $adminEmail) {
        if ($normalized !== '' && $normalized === strtolower(trim((string)$adminEmail))) {
            return true;
        }
    }
    return false;
}


// Optional Firebase service account support for irreversible admin deletes.
// Recommended production setup:
// 1) Create a Firebase service account key in Firebase Console / Google Cloud.
// 2) Upload it outside public web access, e.g. /home/youruser/firebase-service-account.json.
// 3) Set one of these environment variables in hosting:
//    FIREBASE_SERVICE_ACCOUNT_PATH=/home/youruser/firebase-service-account.json
//    OR FIREBASE_SERVICE_ACCOUNT_JSON={...full json...}
//    OR FIREBASE_SERVICE_ACCOUNT_JSON_BASE64=base64_encoded_json
// Do NOT commit the JSON key into public code.
define('FIREBASE_SERVICE_ACCOUNT_PATH', ts_env('FIREBASE_SERVICE_ACCOUNT_PATH', '/var/www/secrets/traders-sanctuary-firebase-adminsdk-fbsvc-17d8ca7310.json')); 



// Shared writable storage folder for local fallbacks/cache.
// Some admin actions depend on this existing even when Firestore is the primary source.
if (!defined('API_DATA_DIR')) {
    define('API_DATA_DIR', __DIR__ . '/_data');
}

if (!function_exists('ensure_dir')) {
    function ensure_dir(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0775);
        }
    }
}

function auth_rate_limit_check(string $scope, string $identity, int $limit = 8, int $windowSeconds = 900): void {
    ensure_dir(API_DATA_DIR);
    $file = API_DATA_DIR . '/auth_rate_limits.json';
    $now = time();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', strtolower(trim($scope . '|' . $identity . '|' . $ip)));
    $store = [];
    if (is_file($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) $store = $decoded;
    }
    foreach ($store as $k => $entry) {
        $first = (int)($entry['first'] ?? 0);
        if (!$first || ($now - $first) > max($windowSeconds * 2, 1800)) unset($store[$k]);
    }
    $entry = $store[$key] ?? ['first' => $now, 'count' => 0];
    if (($now - (int)$entry['first']) > $windowSeconds) {
        $entry = ['first' => $now, 'count' => 0];
    }
    $entry['count'] = (int)($entry['count'] ?? 0) + 1;
    $store[$key] = $entry;
    @file_put_contents($file, json_encode($store), LOCK_EX);
    if ($entry['count'] > $limit) {
        json_response([
            'ok' => false,
            'message' => 'Too many attempts. Please wait a few minutes, then try again.'
        ], 429);
    }
}

function auth_rate_limit_clear(string $scope, string $identity): void {
    $file = API_DATA_DIR . '/auth_rate_limits.json';
    if (!is_file($file)) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', strtolower(trim($scope . '|' . $identity . '|' . $ip)));
    $decoded = json_decode((string)@file_get_contents($file), true);
    if (!is_array($decoded) || !isset($decoded[$key])) return;
    unset($decoded[$key]);
    @file_put_contents($file, json_encode($decoded), LOCK_EX);
}

function json_response($payload, int $status = 200): void {
    $GLOBALS['TS_JSON_RESPONSE_SENT'] = true;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
    exit;
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function api_log(string $message): void {
    $line = '[' . gmdate('c') . '] ' . $message . PHP_EOL;
    ensure_dir(API_DATA_DIR);
    @error_log($line, 3, API_DATA_DIR . '/auth_error.log');
}

function redirect_with_auth_error(string $message, string $returnTo = '/'): void {
    $target = safe_return_to($returnTo ?: '/');
    $separator = (strpos($target, '?') === false) ? '?' : '&';
    header('Location: ' . $target . $separator . 'auth_error=' . urlencode($message));
    exit;
}

function http_request(string $url, ?string $body = null, string $method = 'POST', array $headers = []): array {
    $baseHeaders = $headers;
    $status = 0;
    $responseBody = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $baseHeaders,
            CURLOPT_TIMEOUT => (int)ts_env('TS_HTTP_TIMEOUT_SECONDS', 3),
            CURLOPT_CONNECTTIMEOUT => (int)ts_env('TS_HTTP_CONNECT_TIMEOUT_SECONDS', 1),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            api_log('cURL error for ' . $url . ': ' . $error);
            return ['ok' => false, 'status' => 0, 'error' => ['message' => $error ?: 'Network request failed']];
        }
    } else {
        if (!ini_get('allow_url_fopen')) {
            api_log('Neither cURL nor allow_url_fopen is available on this server.');
            return ['ok' => false, 'status' => 0, 'error' => ['message' => 'Server cannot make outbound HTTPS requests. Enable PHP cURL or allow_url_fopen.']];
        }
        $headerString = implode("\r\n", $baseHeaders);
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerString,
                'content' => $body ?? '',
                'timeout' => (int)ts_env('TS_HTTP_TIMEOUT_SECONDS', 3),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $err = error_get_last()['message'] ?? 'Network request failed';
            api_log('stream error for ' . $url . ': ' . $err);
            return ['ok' => false, 'status' => 0, 'error' => ['message' => $err]];
        }
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        }
    }

    $decoded = json_decode($responseBody ?: '{}', true);
    if (!is_array($decoded)) $decoded = ['raw' => $responseBody];
    if ($status < 200 || $status >= 300) {
        api_log('HTTP ' . $status . ' for ' . $url . ': ' . substr($responseBody ?: '', 0, 600));
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $decoded];
}

function http_json_request(string $url, array $payload = null, string $method = 'POST', array $headers = []): array {
    $body = $payload !== null ? json_encode($payload) : null;
    return http_request($url, $body, $method, array_merge(['Content-Type: application/json'], $headers));
}

function http_form_request(string $url, array $payload, string $method = 'POST', array $headers = []): array {
    $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    return http_request($url, $body, $method, array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers));
}

function firebase_auth_request(string $path, array $payload): array {
    $url = 'https://identitytoolkit.googleapis.com/v1/' . $path . '?key=' . urlencode(FIREBASE_WEB_API_KEY);

    // IMPORTANT: The Firebase Web API key can be restricted to HTTP referrers
    // such as https://traderssanctuary.com/*. Backend PHP requests do not
    // naturally include a browser Referer/Origin, so Google can return a plain
    // HTML 403 page: "Your client does not have permission to get URL
    // /v1/accounts:signInWithPassword". Send the live site origin explicitly
    // so restricted keys still work from this backend.
    $siteOrigin = 'https://traderssanctuary.com';
    return http_json_request($url, $payload, 'POST', [
        'Referer: ' . $siteOrigin . '/',
        'Origin: ' . $siteOrigin,
        'X-Requested-With: TradersSanctuaryBackend'
    ]);
}


function firebase_auth_error_details(array $result): array {
    $code = null;
    $message = null;
    $raw = $result;

    $candidates = [
        $result['data']['error']['message'] ?? null,
        $result['data']['error']['errors'][0]['message'] ?? null,
        $result['error']['message'] ?? null,
        $result['message'] ?? null,
        $result['data']['error'] ?? null,
        $result['data']['raw'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $message = trim($candidate);
            break;
        }
        if (is_array($candidate)) {
            $json = json_encode($candidate);
            if ($json !== false && $json !== '') {
                $message = $json;
                break;
            }
        }
    }

    if ($message !== null) {
        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? $message;
        }
    }

    $normalized = strtoupper(trim((string)($message ?? '')));
    $knownCodes = [
        'EMAIL_EXISTS', 'EMAIL_NOT_FOUND', 'INVALID_PASSWORD', 'INVALID_LOGIN_CREDENTIALS',
        'USER_DISABLED', 'WEAK_PASSWORD', 'OPERATION_NOT_ALLOWED', 'TOO_MANY_ATTEMPTS_TRY_LATER',
        'API_KEY_INVALID', 'API_KEY_SERVICE_BLOCKED', 'API_KEY_HTTP_REFERRER_BLOCKED',
        'INVALID_ID_TOKEN', 'MISSING_PASSWORD', 'MISSING_EMAIL', 'INVALID_EMAIL',
        'PROJECT_NOT_FOUND', 'CONFIGURATION_NOT_FOUND', 'NETWORK_REQUEST_FAILED'
    ];
    if (stripos((string)$message, 'Your client does not have permission to get URL') !== false || stripos((string)$message, 'Error 403') !== false) {
        $code = 'API_KEY_HTTP_REFERRER_BLOCKED_OR_IDENTITY_TOOLKIT_FORBIDDEN';
    } else {
        foreach ($knownCodes as $known) {
            if ($normalized === $known || strpos($normalized, $known) !== false) {
                $code = $known;
                break;
            }
        }
        if (!$code && $normalized !== '') {
            $code = preg_replace('/[^A-Z0-9_:-]/', '_', substr($normalized, 0, 80));
        }
        if (!$code) $code = 'UNKNOWN_AUTH_ERROR';
    }

    $safeRaw = $raw;
    $scrub = function (&$value) use (&$scrub) {
        if (is_array($value)) {
            foreach ($value as $k => &$v) {
                if (preg_match('/password|idToken|refreshToken|localId|email/i', (string)$k)) {
                    if (!in_array($k, ['message','code','status'], true)) $v = '[hidden]';
                } else {
                    $scrub($v);
                }
            }
        } elseif (is_string($value)) {
            $value = preg_replace('/(key=)[^&\s]+/i', '$1[hidden]', $value);
        }
    };
    $scrub($safeRaw);
    $rawJson = json_encode($safeRaw, JSON_UNESCAPED_SLASHES);
    if ($rawJson === false) $rawJson = '[unserializable Firebase response]';

    return [
        'code' => $code,
        'message' => $message ?: 'Firebase response did not include a readable error message.',
        'httpStatus' => $result['status'] ?? 0,
        'rawPreview' => substr($rawJson, 0, 1800),
    ];
}

function firebase_error_message(array $result, string $fallback = 'Authentication failed.'): string {
    $message = $result['data']['error']['message'] ?? $result['error']['message'] ?? $fallback;
    $map = [
        'EMAIL_EXISTS' => 'An account with this email already exists.',
        'EMAIL_NOT_FOUND' => 'No account found with this email.',
        'INVALID_PASSWORD' => 'Incorrect password. Please try again.',
        'INVALID_LOGIN_CREDENTIALS' => 'Invalid email or password.',
        'USER_DISABLED' => 'This account has been disabled.',
        'WEAK_PASSWORD' => 'Password is too weak. Use at least 6 characters.',
        'OPERATION_NOT_ALLOWED' => 'This sign-in method is not enabled in Firebase.',
        'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Too many attempts. Please try again later.',
    ];
    return $map[$message] ?? str_replace('_', ' ', ucfirst(strtolower($message)));
}


function firebase_login_code_meaning(string $code): string {
    $map = [
        'INVALID_LOGIN_CREDENTIALS' => 'Firebase Authentication does not have a matching email/password pair. The Auth user may have been deleted, the password is different, or the app is pointing to a different Firebase project/API key.',
        'EMAIL_NOT_FOUND' => 'This email does not exist in Firebase Authentication Users.',
        'INVALID_PASSWORD' => 'The email exists, but the password does not match Firebase Authentication.',
        'USER_DISABLED' => 'This Firebase Authentication user is disabled.',
        'OPERATION_NOT_ALLOWED' => 'Email/password sign-in is disabled in Firebase Console > Authentication > Sign-in method.',
        'API_KEY_INVALID' => 'The Firebase Web API key is invalid or belongs to another project.',
        'API_KEY_SERVICE_BLOCKED' => 'The API key is blocked from using Identity Toolkit / Firebase Authentication.',
        'API_KEY_HTTP_REFERRER_BLOCKED' => 'The API key has HTTP referrer restrictions that do not allow this domain.',
        'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Firebase temporarily blocked sign-in attempts because of too many failed logins.',
    ];
    return $map[$code] ?? 'Firebase returned this raw auth code. Check /api/auth_diagnostics.php and Firebase Console > Authentication > Users.';
}



function base64url_encode_string(string $input): string {
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function firebase_service_account_config(): ?array {
    $raw = getenv('FIREBASE_SERVICE_ACCOUNT_JSON') ?: '';
    if ($raw === '' && getenv('FIREBASE_SERVICE_ACCOUNT_JSON_BASE64')) {
        $decoded = base64_decode((string)getenv('FIREBASE_SERVICE_ACCOUNT_JSON_BASE64'), true);
        if ($decoded !== false) $raw = $decoded;
    }
    if ($raw === '' && defined('FIREBASE_SERVICE_ACCOUNT_PATH') && is_file(FIREBASE_SERVICE_ACCOUNT_PATH)) {
        $raw = (string)@file_get_contents(FIREBASE_SERVICE_ACCOUNT_PATH);
    }
    if (trim($raw) === '') return null;
    $cfg = json_decode($raw, true);
    if (!is_array($cfg) || empty($cfg['client_email']) || empty($cfg['private_key'])) {
        api_log('Firebase service account config is present but invalid.');
        return null;
    }
    return $cfg;
}

function firebase_admin_access_token(): array {
    $cfg = firebase_service_account_config();
    if (!$cfg) {
        return ['ok' => false, 'message' => 'Firebase service account is not configured on the server. Firestore app data can be deleted with admin session rules, but Firebase Auth account deletion requires a service account.'];
    }

    ensure_dir(API_DATA_DIR);
    $cacheFile = API_DATA_DIR . '/firebase_admin_access_token.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['access_token']) && (int)($cached['expires_at'] ?? 0) > time() + 90) {
            return ['ok' => true, 'access_token' => $cached['access_token'], 'source' => 'cache'];
        }
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $cfg['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/identitytoolkit',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $unsigned = base64url_encode_string(json_encode($header)) . '.' . base64url_encode_string(json_encode($claims));
    $signature = '';
    $ok = function_exists('openssl_sign') && openssl_sign($unsigned, $signature, $cfg['private_key'], OPENSSL_ALGO_SHA256);
    if (!$ok) {
        api_log('Could not sign Firebase service account JWT. Ensure OpenSSL is enabled and the private key is valid.');
        return ['ok' => false, 'message' => 'Server could not sign Firebase service account token. Check OpenSSL/private key configuration.'];
    }
    $jwt = $unsigned . '.' . base64url_encode_string($signature);
    $tokenResult = http_form_request('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    if (!$tokenResult['ok'] || empty($tokenResult['data']['access_token'])) {
        api_log('Firebase service account token exchange failed: ' . json_encode($tokenResult['data'] ?? $tokenResult));
        return ['ok' => false, 'message' => $tokenResult['data']['error_description'] ?? $tokenResult['data']['error'] ?? 'Could not obtain Firebase admin access token.'];
    }
    $accessToken = $tokenResult['data']['access_token'];
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $accessToken,
        'expires_at' => $now + (int)($tokenResult['data']['expires_in'] ?? 3600),
    ]), LOCK_EX);
    return ['ok' => true, 'access_token' => $accessToken, 'source' => 'fresh'];
}

function firebase_admin_bearer_token_or_null(): ?string {
    $token = firebase_admin_access_token();
    return $token['ok'] ? $token['access_token'] : null;
}

function firebase_admin_delete_auth_user(string $uid): array {
    $uid = trim($uid);
    if ($uid === '') return ['ok' => false, 'message' => 'Missing Firebase Auth UID.'];
    $token = firebase_admin_access_token();
    if (!$token['ok']) return ['ok' => false, 'message' => $token['message'] ?? 'Firebase service account unavailable.'];

    $url = 'https://identitytoolkit.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/accounts:delete';
    $result = http_json_request($url, ['localId' => $uid], 'POST', ['Authorization: Bearer ' . $token['access_token']]);
    if (!$result['ok']) {
        $msg = $result['data']['error']['message'] ?? $result['error']['message'] ?? 'Firebase Auth delete failed.';
        // USER_NOT_FOUND means the Firebase Auth account is already gone or the stored UID
        // no longer exists in Auth. For a hard-delete action, that is a clean final state,
        // not a failure, because there is no remaining Auth account associated with this UID.
        if ($msg === 'USER_NOT_FOUND' || stripos((string)$msg, 'USER_NOT_FOUND') !== false) {
            api_log('Firebase Auth delete skipped for ' . $uid . ': USER_NOT_FOUND, treating as already deleted.');
            return ['ok' => true, 'alreadyDeleted' => true, 'message' => 'Firebase Auth account was already deleted or not found.'];
        }
        api_log('Firebase Auth delete failed for ' . $uid . ': ' . json_encode($result['data'] ?? $result));
        return ['ok' => false, 'status' => $result['status'] ?? 0, 'message' => $msg];
    }
    return ['ok' => true, 'alreadyDeleted' => false];
}

function delete_local_user_profile(string $uid, ?string $email = null): void {
    $store = local_profile_store_read();
    $keys = [];
    if ($uid !== '') $keys[] = 'uid:' . $uid;
    if ($email) $keys[] = 'email:' . normalize_email_key($email);
    foreach ($store as $key => $profile) {
        if (!is_array($profile)) continue;
        if (($uid !== '' && (($profile['uid'] ?? '') === $uid || ($profile['id'] ?? '') === $uid)) || ($email && normalize_email_key($profile['email'] ?? '') === normalize_email_key($email))) {
            $keys[] = $key;
        }
    }
    foreach (array_unique($keys) as $key) unset($store[$key]);
    local_profile_store_write($store);
}

function firestore_delete_collection_documents(string $collectionPath, string $bearerToken): array {
    $result = ['collection' => $collectionPath, 'listed' => 0, 'deleted' => 0, 'errors' => []];
    $list = firestore_list_collection($collectionPath, $bearerToken, 100);
    if (!$list['ok']) {
        $result['errors'][] = $list['data']['error']['message'] ?? 'Could not list collection.';
        return $result;
    }
    $rows = $list['rows'] ?? [];
    $result['listed'] = count($rows);
    foreach ($rows as $row) {
        $docId = $row['id'] ?? '';
        if ($docId === '') continue;
        $del = firestore_delete_document($collectionPath . '/' . rawurlencode($docId), $bearerToken);
        if ($del['ok'] || (($del['status'] ?? 0) === 404)) {
            $result['deleted']++;
        } else {
            $result['errors'][] = $docId . ': ' . ($del['data']['error']['message'] ?? 'Delete failed.');
        }
    }
    return $result;
}

function firestore_hard_delete_user_app_data(string $uid, ?string $idToken = null, ?string $email = null): array {
    $uid = trim($uid);
    $adminToken = firebase_admin_bearer_token_or_null();
    $token = $adminToken ?: ($idToken ?: '');
    $summary = [
        'usedServiceAccountForFirestore' => (bool)$adminToken,
        'collections' => [],
        'documents' => [],
        'subscriptionsDeleted' => 0,
        'errors' => [],
    ];
    if ($uid === '' || $token === '') {
        $summary['errors'][] = 'Missing UID or authorized token for Firestore cleanup.';
        return $summary;
    }

    $base = 'artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/users/' . rawurlencode($uid);
    foreach (['trading_notes', 'premium_journal', 'trading_accounts'] as $collection) {
        $summary['collections'][] = firestore_delete_collection_documents($base . '/' . $collection, $token);
    }

    $profileDel = firestore_delete_document(firestore_profile_path($uid), $token);
    $summary['documents']['profile'] = $profileDel['ok'] || (($profileDel['status'] ?? 0) === 404);
    if (!$summary['documents']['profile']) $summary['errors'][] = 'Profile delete failed: ' . ($profileDel['data']['error']['message'] ?? 'Unknown error.');

    $parentDel = firestore_delete_document($base, $token);
    $summary['documents']['parentUser'] = $parentDel['ok'] || (($parentDel['status'] ?? 0) === 404);

    // Remove public subscription documents linked to this user/email.
    $subsPath = 'artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/public/data/subscriptions';
    $subs = firestore_list_collection($subsPath, $token, 250);
    if ($subs['ok']) {
        foreach (($subs['rows'] ?? []) as $sub) {
            $matchesUid = ($sub['userId'] ?? '') === $uid || ($sub['uid'] ?? '') === $uid || ($sub['id'] ?? '') === $uid;
            $matchesEmail = $email && normalize_email_key($sub['email'] ?? '') === normalize_email_key($email);
            if (!$matchesUid && !$matchesEmail) continue;
            $docId = $sub['id'] ?? '';
            if ($docId === '') continue;
            $del = firestore_delete_document($subsPath . '/' . rawurlencode($docId), $token);
            if ($del['ok'] || (($del['status'] ?? 0) === 404)) $summary['subscriptionsDeleted']++;
            else $summary['errors'][] = 'Subscription ' . $docId . ' delete failed: ' . ($del['data']['error']['message'] ?? 'Unknown error.');
        }
    } else {
        $summary['errors'][] = 'Could not list subscriptions: ' . ($subs['data']['error']['message'] ?? 'Unknown error.');
    }

    delete_local_user_profile($uid, $email);
    return $summary;
}

function firestore_value_to_php(array $value) {
    if (array_key_exists('stringValue', $value)) return $value['stringValue'];
    if (array_key_exists('booleanValue', $value)) return (bool)$value['booleanValue'];
    if (array_key_exists('integerValue', $value)) return (int)$value['integerValue'];
    if (array_key_exists('doubleValue', $value)) return (float)$value['doubleValue'];
    if (array_key_exists('timestampValue', $value)) return $value['timestampValue'];
    if (array_key_exists('nullValue', $value)) return null;
    if (array_key_exists('arrayValue', $value)) {
        $vals = $value['arrayValue']['values'] ?? [];
        return array_map('firestore_value_to_php', is_array($vals) ? $vals : []);
    }
    if (array_key_exists('mapValue', $value)) {
        return firestore_fields_to_php($value['mapValue']['fields'] ?? []);
    }
    return null;
}

function php_to_firestore_value($value): array {
    if (is_bool($value)) return ['booleanValue' => $value];
    if (is_int($value)) return ['integerValue' => (string)$value];
    if (is_float($value)) return ['doubleValue' => $value];
    if ($value === null) return ['nullValue' => null];
    if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return ['arrayValue' => ['values' => array_map('php_to_firestore_value', $value)]];
        }
        $fields = [];
        foreach ($value as $k => $v) $fields[(string)$k] = php_to_firestore_value($v);
        return ['mapValue' => ['fields' => $fields]];
    }
    return ['stringValue' => (string)$value];
}

function firestore_fields_to_php(array $fields): array {
    $out = [];
    foreach ($fields as $key => $value) {
        if (is_array($value)) $out[$key] = firestore_value_to_php($value);
    }
    return $out;
}

function firestore_profile_path(string $uid): string {
    return 'artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/users/' . rawurlencode($uid) . '/profile/data';
}

function firestore_get_document(string $documentPath, string $idToken): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . $documentPath;
    return http_json_request($url, null, 'GET', ['Authorization: Bearer ' . $idToken]);
}


function firestore_full_document_name(string $documentPath): string {
    return 'projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/' . ltrim($documentPath, '/');
}

function firestore_batch_get_documents(array $documentPaths, string $idToken): array {
    $documentPaths = array_values(array_unique(array_filter(array_map('strval', $documentPaths))));
    if (empty($documentPaths)) return ['ok' => true, 'status' => 200, 'rows' => []];

    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents:batchGet';
    $payload = [
        'documents' => array_map('firestore_full_document_name', $documentPaths),
    ];
    $result = http_json_request($url, $payload, 'POST', ['Authorization: Bearer ' . $idToken]);
    if (!$result['ok']) return $result;

    $rows = [];
    foreach (($result['data'] ?? []) as $entry) {
        if (empty($entry['found']) || !is_array($entry['found'])) continue;
        $doc = $entry['found'];
        $docName = (string)($doc['name'] ?? '');
        $fields = firestore_fields_to_php($doc['fields'] ?? []);
        $fields['_documentName'] = $docName;
        $fields['id'] = firestore_document_id_from_name($docName);
        $rows[$docName] = $fields;
    }
    $result['rows'] = array_values($rows);
    $result['rowsByName'] = $rows;
    return $result;
}

function firestore_patch_document(string $documentPath, array $fields, string $idToken): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . $documentPath;
    $fsFields = [];
    foreach ($fields as $key => $value) $fsFields[$key] = php_to_firestore_value($value);
    return http_json_request($url, ['fields' => $fsFields], 'PATCH', ['Authorization: Bearer ' . $idToken]);
}

function load_user_profile(string $uid, ?string $idToken): array {
    if (!$uid || !$idToken) return [];
    $result = firestore_get_document(firestore_profile_path($uid), $idToken);
    if (!$result['ok'] || empty($result['data']['fields'])) return [];
    return firestore_fields_to_php($result['data']['fields']);
}


function trading_accounts_collection_path(string $uid): string {
    return 'artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/users/' . rawurlencode($uid) . '/trading_accounts';
}

function trading_accounts_collection_paths(string $uid): array {
    $u = rawurlencode($uid);
    $ids = [FIREBASE_PROJECT_ID];
    if (defined('FIREBASE_WEB_APP_ID')) $ids[] = FIREBASE_WEB_APP_ID;
    $aliases = ['trading_accounts', 'tradingAccounts', 'accounts', 'trade_accounts'];
    $paths = [];
    foreach (array_values(array_unique($ids)) as $id) {
        $root = 'artifacts/' . rawurlencode((string)$id) . '/users/' . $u;
        foreach ($aliases as $alias) $paths[] = $root . '/' . rawurlencode($alias);
    }
    foreach ($aliases as $alias) {
        $paths[] = 'users/' . $u . '/' . rawurlencode($alias);
        $paths[] = 'user_data/' . $u . '/' . rawurlencode($alias);
        $paths[] = 'members/' . $u . '/' . rawurlencode($alias);
    }
    return array_values(array_unique($paths));
}

function normalize_trading_account_payload(array $account, int $index = 0): array {
    $id = trim((string)($account['id'] ?? ''));
    if ($id === '') $id = 'acct_' . ($index + 1) . '_' . time();
    return [
        'id' => $id,
        'name' => trim((string)($account['name'] ?? '')),
        'broker' => trim((string)($account['broker'] ?? '')),
        'accountNumber' => trim((string)($account['accountNumber'] ?? '')),
        'type' => trim((string)($account['type'] ?? 'Live')) ?: 'Live',
        'currency' => trim((string)($account['currency'] ?? 'USD')) ?: 'USD',
        'startingBalance' => trim((string)($account['startingBalance'] ?? '')),
        'currentBalance' => trim((string)($account['currentBalance'] ?? ($account['startingBalance'] ?? ''))),
        'isActive' => array_key_exists('isActive', $account) ? (bool)$account['isActive'] : true,
        'updatedAt' => gmdate('c'),
    ];
}

function normalize_trading_accounts_list($accounts): array {
    if (!is_array($accounts)) return [];
    $clean = [];
    foreach (array_values($accounts) as $i => $account) {
        if (!is_array($account)) continue;
        $row = normalize_trading_account_payload($account, $i);
        if ($row['name'] === '' || $row['startingBalance'] === '') continue;
        $clean[] = $row;
    }
    return $clean;
}

function account_row_matches_user(array $row, string $uid, ?string $email = null): bool {
    $uid = trim($uid);
    $email = normalize_email_key($email);
    $ownerFields = ['userId', 'uid', 'ownerId', 'firebaseUid', 'createdBy', 'memberUid'];
    $emailFields = ['email', 'userEmail', 'memberEmail', 'createdByEmail'];

    foreach ($ownerFields as $field) {
        if (!array_key_exists($field, $row) || trim((string)$row[$field]) === '') continue;
        return $uid !== '' && trim((string)$row[$field]) === $uid;
    }
    foreach ($emailFields as $field) {
        if (!array_key_exists($field, $row) || trim((string)$row[$field]) === '') continue;
        return $email !== '' && normalize_email_key($row[$field]) === $email;
    }

    // Markerless legacy rows are only trusted when their Firestore document name
    // proves they were read from the current user's private path. Never trust
    // markerless local/profile/browser cache rows for accounts.
    $docName = (string)($row['_documentName'] ?? '');
    if ($uid !== '' && $docName !== '') {
        $quotedUid = preg_quote($uid, '#');
        if (preg_match('#/(users|user_data|members)/' . $quotedUid . '/#', $docName)) return true;
    }
    return false;
}

function stamp_trading_account_owner(array $account, string $uid, ?string $email = null): array {
    $email = normalize_email_key($email);
    $account['userId'] = $uid;
    $account['uid'] = $uid;
    $account['ownerId'] = $uid;
    $account['userEmail'] = $email;
    $account['email'] = $email;
    return $account;
}

function filter_trading_accounts_for_user(array $accounts, string $uid, ?string $email = null): array {
    return array_values(array_filter($accounts, fn($row) => is_array($row) && account_row_matches_user($row, $uid, $email)));
}

function prefer_non_empty_array($primary, $fallback): array {
    return is_array($primary) && count($primary) > 0 ? $primary : (is_array($fallback) ? $fallback : []);
}

function merge_durable_user_profile(array $cloud, array $local, array $cloudTradingAccounts = []): array {
    $profile = array_merge($cloud, $local);

    $cloudAccounts = normalize_trading_accounts_list($cloudTradingAccounts ?: ($cloud['tradingAccounts'] ?? []));
    $localAccounts = normalize_trading_accounts_list($local['tradingAccounts'] ?? []);
    $profileAccounts = normalize_trading_accounts_list($profile['tradingAccounts'] ?? []);
    $bestAccounts = prefer_non_empty_array($cloudAccounts, prefer_non_empty_array($profileAccounts, $localAccounts));
    if (!empty($bestAccounts)) {
        $profile['tradingAccounts'] = $bestAccounts;
    }

    // Never downgrade a durable completed agreement back to false because one backend source is stale.
    if (!empty($cloud['termsAgreed']) || !empty($local['termsAgreed'])) {
        $profile['termsAgreed'] = true;
        $profile['termsAgreedAt'] = $cloud['termsAgreedAt'] ?? $local['termsAgreedAt'] ?? $profile['termsAgreedAt'] ?? gmdate('c');
        $profile['agreementFile'] = $cloud['agreementFile'] ?? $local['agreementFile'] ?? $profile['agreementFile'] ?? null;
        if (!empty($cloud['agreementContent']) || !empty($local['agreementContent'])) {
            $profile['agreementContent'] = $cloud['agreementContent'] ?? $local['agreementContent'];
        }
    }

    if (!empty($cloud['tradingAccountsSetupPromptedAt']) || !empty($local['tradingAccountsSetupPromptedAt'])) {
        $profile['tradingAccountsSetupPromptedAt'] = $cloud['tradingAccountsSetupPromptedAt'] ?? $local['tradingAccountsSetupPromptedAt'];
    }
    if (!empty($cloud['tradingAccountsSetupAt']) || !empty($local['tradingAccountsSetupAt'])) {
        $profile['tradingAccountsSetupAt'] = $cloud['tradingAccountsSetupAt'] ?? $local['tradingAccountsSetupAt'];
    }

    return $profile;
}

function load_user_trading_accounts(string $uid, ?string $email, ?string $idToken): array {
    // Strict account isolation: trading accounts are user-owned data, so every
    // returned row must either carry owner markers or come from the exact current
    // user's Firestore document path. This prevents one user's accounts/balances
    // from being merged into another user's PTJ after switching logins.
    $merged = [];
    $seen = [];
    $push = function($rows, bool $stampBecauseSourceIsPrivatePath = false) use (&$merged, &$seen, $uid, $email) {
        if (!is_array($rows)) return;
        foreach (array_values($rows) as $i => $rawRow) {
            if (!is_array($rawRow)) continue;
            $rowForCheck = $rawRow;
            if ($stampBecauseSourceIsPrivatePath && !account_row_matches_user($rowForCheck, $uid, $email)) {
                $rowForCheck = stamp_trading_account_owner($rowForCheck, $uid, $email);
            }
            if (!account_row_matches_user($rowForCheck, $uid, $email)) continue;
            $normalized = normalize_trading_accounts_list([$rowForCheck]);
            if (empty($normalized[0])) continue;
            $row = stamp_trading_account_owner(array_merge($rowForCheck, $normalized[0]), $uid, $email);
            $key = strtolower(trim((string)($row['id'] ?? '')));
            if ($key === '') $key = strtolower(trim((string)($row['accountNumber'] ?? $row['name'] ?? ('acct_' . $i))));
            if ($key === '') $key = 'acct_' . count($merged);
            if (isset($seen[$key]) && isset($merged[$seen[$key]])) {
                $merged[$seen[$key]] = array_merge($merged[$seen[$key]], $row);
            } else {
                $merged[] = $row;
                $seen[$key] = count($merged) - 1;
            }
        }
    };

    if ($uid) {
        $tokens = firebase_server_token_candidates($idToken);
        foreach ($tokens as $token) {
            foreach (array_slice(trading_accounts_collection_paths($uid), 0, 8) as $collectionPath) {
                $remote = firestore_list_collection($collectionPath, $token, 80);
                if ($remote['ok'] && !empty($remote['rows'])) {
                    $rows = [];
                    foreach ($remote['rows'] as $i => $row) {
                        if (!isset($row['id']) || trim((string)$row['id']) === '') {
                            $row['id'] = firestore_document_id_from_name($row['name'] ?? ('acct_' . $i));
                        }
                        $rows[] = $row;
                    }
                    // Rows came from /users/{uid}/..., so stamp old markerless rows safely.
                    $push($rows, true);
                } elseif (!$remote['ok']) {
                    api_log('Non-blocking trading accounts collection load failed for ' . ($email ?: $uid) . ' at ' . $collectionPath . ': ' . json_encode($remote['data'] ?? $remote));
                }
            }
            $profile = load_user_profile($uid, $token);
            // Profile arrays are accepted only from this exact uid profile and stamped.
            $push($profile['tradingAccounts'] ?? [], true);
        }
    }

    $local = load_local_user_profile($uid, $email);
    $localLooksOwned = (($local['uid'] ?? '') === $uid) || (normalize_email_key($local['email'] ?? '') !== '' && normalize_email_key($local['email'] ?? '') === normalize_email_key($email));
    if ($localLooksOwned) {
        // Old local rows saved under the current user's profile can be stamped.
        $push($local['tradingAccounts'] ?? [], true);
    }

    $deletedIds = [];
    foreach (($local['deletedTradingAccountIds'] ?? []) as $deletedId) {
        $cleanDeletedId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$deletedId);
        if ($cleanDeletedId !== '') $deletedIds[$cleanDeletedId] = true;
    }
    $localKeepIds = [];
    $localAccounts = $localLooksOwned ? normalize_trading_accounts_list($local['tradingAccounts'] ?? []) : [];
    foreach ($localAccounts as $localAccount) {
        $localId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($localAccount['id'] ?? ''));
        if ($localId !== '') $localKeepIds[$localId] = true;
    }

    $merged = array_values(array_filter($merged, function($row) use ($deletedIds, $localKeepIds) {
        $rowId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($row['id'] ?? ''));
        if ($rowId !== '' && isset($deletedIds[$rowId]) && !isset($localKeepIds[$rowId])) return false;
        return !empty($row['name']) && (!empty($row['startingBalance']) || !empty($row['currentBalance']));
    }));
    usort($merged, fn($a, $b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    return $merged;
}

function save_user_trading_accounts(string $uid, ?string $email, array $accounts, ?string $idToken): bool {
    $clean = array_map(fn($account) => stamp_trading_account_owner($account, $uid, $email), normalize_trading_accounts_list($accounts));

    $local = load_local_user_profile($uid, $email);
    $previousAccounts = normalize_trading_accounts_list($local['tradingAccounts'] ?? []);
    $cleanIds = [];
    foreach ($clean as $account) {
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($account['id'] ?? ''));
        if ($id !== '') $cleanIds[$id] = true;
    }
    $deletedIds = [];
    foreach (($local['deletedTradingAccountIds'] ?? []) as $deletedId) {
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$deletedId);
        if ($id !== '' && !isset($cleanIds[$id])) $deletedIds[$id] = true;
    }
    foreach ($previousAccounts as $previousAccount) {
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($previousAccount['id'] ?? ''));
        if ($id !== '' && !isset($cleanIds[$id])) $deletedIds[$id] = true;
    }
    $local['tradingAccounts'] = $clean;
    $local['deletedTradingAccountIds'] = array_values(array_keys($deletedIds));
    $local['tradingAccountsUpdatedAt'] = gmdate('c');
    save_local_user_profile($uid, $email, $local);

    if (!$uid) return true;

    $tokens = firebase_server_token_candidates($idToken);
    if (!$tokens) return true;

    $ok = false;
    $collections = trading_accounts_collection_paths($uid);
    $collection = trading_accounts_collection_path($uid);
    $keepIds = [];

    foreach ($tokens as $token) {
        $tokenOk = true;
        foreach ($collections as $collectionPath) {
            $existing = firestore_list_collection($collectionPath, $token, 50);
            foreach ($clean as $account) {
                $account['updatedAt'] = gmdate('c');
                $docId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$account['id']);
                if ($docId === '') $docId = 'acct_' . time();
                $account['id'] = $docId;
                $keepIds[$docId] = true;
                $result = firestore_patch_document($collectionPath . '/' . rawurlencode($docId), $account, $token);
                if (!$result['ok']) {
                    $tokenOk = false;
                    api_log('Non-blocking trading account save failed for ' . ($email ?: $uid) . ' at ' . $collectionPath . ': ' . json_encode($result['data'] ?? $result));
                }
            }

            if ($existing['ok']) {
                foreach (($existing['rows'] ?? []) as $row) {
                    $rawExistingId = (string)($row['id'] ?? '');
                    if ($rawExistingId === '' && !empty($row['_documentName'])) {
                        $rawExistingId = firestore_document_id_from_name((string)$row['_documentName']);
                    }
                    if ($rawExistingId === '' && !empty($row['name']) && is_string($row['name']) && str_contains($row['name'], '/')) {
                        $rawExistingId = firestore_document_id_from_name((string)$row['name']);
                    }
                    $existingId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rawExistingId);
                    if ($existingId !== '' && !isset($keepIds[$existingId])) {
                        $delete = firestore_delete_document($collectionPath . '/' . rawurlencode($existingId), $token);
                        if (!$delete['ok']) {
                            $tokenOk = false;
                            api_log('Non-blocking trading account delete failed for ' . ($email ?: $uid) . ' at ' . $collectionPath . ': ' . json_encode($delete['data'] ?? $delete));
                        }
                    }
                }
            }
        }

        $profileMirror = firestore_patch_document(firestore_profile_path($uid), [
            'tradingAccounts' => $clean,
            'deletedTradingAccountIds' => array_values(array_keys($deletedIds)),
            'tradingAccountsUpdatedAt' => gmdate('c'),
            'tradingAccountsSetupAt' => gmdate('c'),
        ], $token);
        if (!$profileMirror['ok']) {
            $tokenOk = false;
            api_log('Non-blocking trading accounts profile mirror failed for ' . ($email ?: $uid) . ': ' . json_encode($profileMirror['data'] ?? $profileMirror));
        }

        if ($tokenOk) {
            $ok = true;
            break;
        }
    }

    return $ok;
}

function firebase_account_created_at(?string $idToken): ?string {
    if (!$idToken) return null;
    $result = firebase_auth_request('accounts:lookup', ['idToken' => $idToken]);
    if (!$result['ok']) return null;
    $createdAtMs = $result['data']['users'][0]['createdAt'] ?? null;
    if (!$createdAtMs || !is_numeric($createdAtMs)) return null;
    return gmdate('c', (int)floor(((int)$createdAtMs) / 1000));
}


function normalize_email_key(?string $email): string {
    return strtolower(trim((string)$email));
}

function is_admin_email(?string $email): bool {
    return in_array(normalize_email_key($email), array_map('normalize_email_key', ADMIN_EMAILS), true);
}

function local_profile_store_path(): string {
    return __DIR__ . '/_data/profiles.json';
}

function local_profile_store_read(): array {
    $file = local_profile_store_path();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function local_profile_store_write(array $data): bool {
    $file = local_profile_store_path();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_writable($dir)) @chmod($dir, 0775);
    if (is_file($file) && !is_writable($file)) @chmod($file, 0664);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $ok = @file_put_contents($file, $json, LOCK_EX);
    if ($ok === false) {
        @chmod($dir, 0777);
        if (is_file($file)) @chmod($file, 0666);
        $ok = @file_put_contents($file, $json, LOCK_EX);
    }
    if ($ok !== false) @chmod($file, 0664);
    return $ok !== false;
}

function local_profile_key(string $uid, ?string $email = null): string {
    return $uid !== '' ? 'uid:' . $uid : 'email:' . normalize_email_key($email);
}

function load_local_user_profile(string $uid, ?string $email = null): array {
    $store = local_profile_store_read();
    $primary = local_profile_key($uid, $email);
    $emailKey = 'email:' . normalize_email_key($email);
    $profile = [];
    if (isset($store[$primary]) && is_array($store[$primary])) $profile = $store[$primary];
    if (!$profile && $email && isset($store[$emailKey]) && is_array($store[$emailKey])) $profile = $store[$emailKey];
    return $profile;
}

function save_local_user_profile(string $uid, ?string $email, array $fields): void {
    $store = local_profile_store_read();
    $key = local_profile_key($uid, $email);
    $existing = isset($store[$key]) && is_array($store[$key]) ? $store[$key] : [];
    $merged = array_merge($existing, $fields, [
        'uid' => $uid ?: ($fields['uid'] ?? ''),
        'email' => $email ?: ($fields['email'] ?? ($existing['email'] ?? '')),
        'updatedAt' => gmdate('c'),
    ]);
    if (is_admin_email($merged['email'] ?? $email)) $merged['role'] = 'admin';
    $store[$key] = $merged;
    if (!empty($merged['email'])) $store['email:' . normalize_email_key($merged['email'])] = $merged;
    local_profile_store_write($store);
}


function premium_override_store_path(): string {
    return __DIR__ . '/_data/premium_overrides.json';
}

function premium_override_store_read(): array {
    $file = premium_override_store_path();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function premium_override_store_write(array $data): bool {
    $file = premium_override_store_path();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_writable($dir)) @chmod($dir, 0775);
    if (is_file($file) && !is_writable($file)) @chmod($file, 0664);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $ok = @file_put_contents($file, $json, LOCK_EX);
    if ($ok === false) {
        @chmod($dir, 0777);
        if (is_file($file)) @chmod($file, 0666);
        $ok = @file_put_contents($file, $json, LOCK_EX);
    }
    if ($ok !== false) @chmod($file, 0664);
    return $ok !== false;
}

function premium_override_keys(string $uid = '', ?string $email = null): array {
    $keys = [];
    if ($uid !== '') $keys[] = 'uid:' . $uid;
    if ($email) $keys[] = 'email:' . normalize_email_key($email);
    return $keys;
}

function save_premium_override(string $uid, ?string $email, array $fields): bool {
    $store = premium_override_store_read();
    $payload = array_merge($fields, [
        'uid' => $uid ?: ($fields['uid'] ?? ''),
        'email' => $email ?: ($fields['email'] ?? ''),
        'updatedAt' => gmdate('c'),
    ]);
    foreach (premium_override_keys($payload['uid'] ?? '', $payload['email'] ?? '') as $key) {
        $existing = isset($store[$key]) && is_array($store[$key]) ? $store[$key] : [];
        $store[$key] = array_merge($existing, $payload);
    }
    return premium_override_store_write($store);
}

function load_premium_override(string $uid = '', ?string $email = null): array {
    $store = premium_override_store_read();
    foreach (premium_override_keys($uid, $email) as $key) {
        if (!empty($store[$key]) && is_array($store[$key])) {
            return $store[$key];
        }
    }
    return [];
}

function merge_premium_override_into_profile(array $profile, ?string $email = null): array {
    $override = load_premium_override($profile['uid'] ?? '', $email ?: ($profile['email'] ?? ''));
    if (!$override) return $profile;

    // If override has a valid future premium expiry, force premium access.
    $expiry = !empty($override['premiumExpiresAt']) ? strtotime((string)$override['premiumExpiresAt']) : false;
    if (($override['role'] ?? '') === 'premium' && $expiry !== false && $expiry >= time()) {
        $profile = array_merge($profile, $override);
        $profile['role'] = 'premium';
    }
    return $profile;
}

function secure_profile_trading_accounts_for_identity(array $profile, string $uid = '', ?string $email = null): array {
    $uid = $uid ?: (string)($profile['uid'] ?? '');
    $email = $email ?: ($profile['email'] ?? '');
    $profileUid = (string)($profile['uid'] ?? '');
    $profileEmail = normalize_email_key($profile['email'] ?? '');
    $identityOk = ($uid !== '' && $profileUid === $uid) || (normalize_email_key($email) !== '' && $profileEmail === normalize_email_key($email));
    if (!$identityOk) {
        $profile['tradingAccounts'] = [];
        return $profile;
    }
    $accounts = [];
    foreach (normalize_trading_accounts_list($profile['tradingAccounts'] ?? []) as $account) {
        $owned = stamp_trading_account_owner($account, $uid, $email);
        if (account_row_matches_user($owned, $uid, $email)) $accounts[] = $owned;
    }
    $profile['tradingAccounts'] = $accounts;
    return $profile;
}

function apply_backend_profile_guards(array $profile, ?string $email = null): array {
    $email = $email ?: ($profile['email'] ?? '');

    if (function_exists('merge_premium_override_into_profile')) {
        $profile = merge_premium_override_into_profile($profile, $email);
    }

    if (is_admin_email($email)) {
        $profile['role'] = 'admin';
        return $profile;
    }

    // Automatically downgrade expired premium accounts.
    if (($profile['role'] ?? '') === 'premium' && !empty($profile['premiumExpiresAt'])) {
        $expiry = strtotime((string)$profile['premiumExpiresAt']);
        if ($expiry !== false && $expiry < time()) {
            $profile['role'] = 'member';
            $profile['premiumExpiredAt'] = gmdate('c');
        }
    }
    return $profile;
}

function ensure_user_profile(array $authData, array $defaults = []): array {
    $uid = $authData['localId'] ?? '';
    $idToken = $authData['idToken'] ?? '';
    $email = $authData['email'] ?? ($defaults['email'] ?? '');

    // Production stability: authentication must not hang while PHP waits for several
    // Firestore/profile reads. Build the login session from the local mirror + safe
    // defaults first, then normal dashboard endpoints can sync remote data separately.
    if (ts_env_bool('TS_FAST_AUTH_PROFILE', true)) {
        $local = load_local_user_profile($uid, $email);
        $base = array_merge([
            'uid' => $uid,
            'email' => $email,
            'role' => 'member',
            'createdAt' => $authData['createdAt'] ?? ($local['createdAt'] ?? gmdate('c')),
            'displayName' => $authData['displayName'] ?? ($defaults['displayName'] ?? ($local['displayName'] ?? (explode('@', $email)[0] ?? 'Member'))),
            'photoURL' => $authData['photoUrl'] ?? ($defaults['photoURL'] ?? ($local['photoURL'] ?? '')),
            'authProvider' => $defaults['authProvider'] ?? ($local['authProvider'] ?? 'password'),
            'termsAgreed' => $defaults['termsAgreed'] ?? ($local['termsAgreed'] ?? false),
        ], $local, $defaults);
        $profile = apply_backend_profile_guards($base, $email);
        save_local_user_profile($uid, $email, $profile);
        return $profile;
    }

    $existing = load_user_profile($uid, $idToken);
    $local = load_local_user_profile($uid, $email);
    $cloudTradingAccounts = load_user_trading_accounts($uid, $email, $idToken);
    if (!empty($cloudTradingAccounts)) $local['tradingAccounts'] = $cloudTradingAccounts;

    if (!empty($existing)) {
        $profile = merge_durable_user_profile($existing, $local, $cloudTradingAccounts);
        if (empty($profile['createdAt'])) {
            $createdAt = firebase_account_created_at($idToken) ?? gmdate('c');
            $profile['createdAt'] = $createdAt;
            if ($uid && $idToken) {
                firestore_patch_document(firestore_profile_path($uid), ['createdAt' => $createdAt], $idToken);
            }
        }
        $profile = apply_backend_profile_guards($profile, $email);
        save_local_user_profile($uid, $email, $profile);
        return $profile;
    }

    $profile = merge_durable_user_profile(array_merge([
        'uid' => $uid,
        'email' => $email,
        'role' => 'member',
        'createdAt' => $authData['createdAt'] ?? firebase_account_created_at($idToken) ?? gmdate('c'),
        'displayName' => $authData['displayName'] ?? ($defaults['displayName'] ?? (explode('@', $email)[0] ?? 'Member')),
        'photoURL' => $authData['photoUrl'] ?? ($defaults['photoURL'] ?? ''),
        'authProvider' => $defaults['authProvider'] ?? 'password',
        'termsAgreed' => false,
    ], $defaults), $local, $cloudTradingAccounts);

    $profile = apply_backend_profile_guards($profile, $email);
    save_local_user_profile($uid, $email, $profile);

    if ($uid && $idToken) {
        firestore_patch_document(firestore_profile_path($uid), $profile, $idToken);
    }
    return $profile;
}

function normalize_firebase_user(array $authData, array $profile = []): array {
    $email = $authData['email'] ?? $profile['email'] ?? '';
    $uid = $authData['localId'] ?? $profile['uid'] ?? '';
    $profile = apply_backend_profile_guards($profile, $email);

    // Preserve durable profile fields such as tradingAccounts across devices/sessions.
    // Earlier versions only returned a fixed whitelist, so saved accounts could exist in
    // the backend profile but disappear from the frontend after logging in elsewhere.
    $user = array_merge($profile, [
        'uid' => $uid,
        'email' => $email,
        'displayName' => $profile['displayName'] ?? ($authData['displayName'] ?? (explode('@', $email)[0] ?? 'Member')),
        'photoURL' => $profile['photoURL'] ?? ($authData['photoUrl'] ?? ''),
        'role' => is_admin_email($email) ? 'admin' : ($profile['role'] ?? 'member'),
        'createdAt' => $profile['createdAt'] ?? ($authData['createdAt'] ?? null),
        'authProvider' => $profile['authProvider'] ?? 'password',
        'termsAgreed' => $profile['termsAgreed'] ?? false,
        'termsAgreedAt' => $profile['termsAgreedAt'] ?? null,
        'agreementFile' => $profile['agreementFile'] ?? null,
        'premiumExpiresAt' => $profile['premiumExpiresAt'] ?? null,
        'premiumActivatedAt' => $profile['premiumActivatedAt'] ?? null,
        'premiumPaymentId' => $profile['premiumPaymentId'] ?? null,
    ]);

    if (!isset($user['tradingAccounts']) || !is_array($user['tradingAccounts'])) {
        $user['tradingAccounts'] = [];
    } else {
        $user = secure_profile_trading_accounts_for_identity($user, $uid, $email);
    }

    return $user;
}

function start_user_session(array $authData, array $profile = []): array {
    session_regenerate_id(true);
    $_SESSION['user'] = apply_backend_profile_guards(normalize_firebase_user($authData, $profile), $authData['email'] ?? ($profile['email'] ?? ''));
    $_SESSION['idToken'] = $authData['idToken'] ?? null;
    $_SESSION['refreshToken'] = $authData['refreshToken'] ?? null;
    $_SESSION['lastActivity'] = time();

    // Backfill older sessions/profiles that were created before createdAt was returned to the frontend.
    if (empty($_SESSION['user']['createdAt']) && !empty($_SESSION['user']['uid']) && !empty($_SESSION['idToken'])) {
        $profile = load_user_profile($_SESSION['user']['uid'], $_SESSION['idToken']);
        if (empty($profile['createdAt'])) {
            $profile['createdAt'] = firebase_account_created_at($_SESSION['idToken']) ?? gmdate('c');
            firestore_patch_document(firestore_profile_path($_SESSION['user']['uid']), ['createdAt' => $profile['createdAt']], $_SESSION['idToken']);
        }
        firestore_patch_session_profile($profile);
    }

    return $_SESSION['user'];
}


function bearer_token_from_request(): string {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders() ?: [];
    }
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    foreach ($headers as $key => $value) {
        if (strtolower((string)$key) === 'authorization') {
            $auth = (string)$value;
            break;
        }
    }
    if (preg_match('/Bearer\s+(.+)/i', (string)$auth, $m)) {
        return trim($m[1]);
    }

    // Fallback for shared hosting / Apache/FastCGI setups that strip Authorization.
    // This is only used for Firebase ID tokens sent by our own frontend to /api/*.php over HTTPS.
    $fallback = $_GET['ts_id_token'] ?? $_POST['ts_id_token'] ?? '';
    if (is_string($fallback) && $fallback !== '') {
        $fallback = trim($fallback);
        if (substr_count($fallback, '.') === 2 && strlen($fallback) > 120) {
            return $fallback;
        }
    }
    return '';
}


function ts_jwt_payload_from_bearer_token(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $raw = strtr($parts[1], '-_', '+/');
    $raw .= str_repeat('=', (4 - strlen($raw) % 4) % 4);
    $decoded = base64_decode($raw, true);
    if ($decoded === false) return null;
    $payload = json_decode($decoded, true);
    if (!is_array($payload)) return null;
    $now = time();
    $expectedIssuer = 'https://securetoken.google.com/' . FIREBASE_PROJECT_ID;
    if (($payload['aud'] ?? '') !== FIREBASE_PROJECT_ID) return null;
    if (($payload['iss'] ?? '') !== $expectedIssuer) return null;
    if (empty($payload['sub']) || (int)($payload['exp'] ?? 0) <= $now) return null;
    return $payload;
}

function stateless_user_from_bearer_if_possible(): ?array {
    $token = bearer_token_from_request();
    if ($token === '') return null;
    $payload = ts_jwt_payload_from_bearer_token($token);
    if (!$payload) return null;
    $now = time();
    $uid = (string)($payload['sub'] ?? '');
    $email = strtolower(trim((string)($payload['email'] ?? '')));
    if ($uid === '' && $email === '') return null;

    // Local profile cache only: no Firebase call and no PHP session lock.
    $local = load_local_user_profile($uid, $email);
    $user = normalize_firebase_user([
        'localId' => $uid,
        'email' => $email,
        'displayName' => (string)($payload['name'] ?? ($local['displayName'] ?? '')),
        'photoUrl' => (string)($payload['picture'] ?? ($local['photoURL'] ?? '')),
        'idToken' => $token,
        'refreshToken' => '',
        'expiresIn' => max(60, (int)($payload['exp'] ?? ($now + 3600)) - $now),
    ], is_array($local) ? $local : []);
    $user = apply_backend_profile_guards(array_merge($user, is_array($local) ? $local : []), $email);
    $user = secure_profile_trading_accounts_for_identity($user, $uid, $email);
    $user['sessionSource'] = 'stateless_bearer_fast_path';
    return $user;
}

function create_session_from_bearer_if_possible(): ?array {
    if (!empty($_SESSION['user'])) return $_SESSION['user'];
    $token = bearer_token_from_request();
    if ($token === '') return null;
    $lookup = firebase_auth_request('accounts:lookup', ['idToken' => $token]);
    if (!($lookup['ok'] ?? false) || empty($lookup['data']['users'][0])) {
        api_log('Bearer session bootstrap failed: HTTP ' . ($lookup['status'] ?? 0) . ' ' . json_encode($lookup['data'] ?? $lookup));
        return null;
    }
    $fu = $lookup['data']['users'][0];
    $authData = [
        'localId' => (string)($fu['localId'] ?? ''),
        'email' => (string)($fu['email'] ?? ''),
        'displayName' => (string)($fu['displayName'] ?? ''),
        'photoUrl' => (string)($fu['photoUrl'] ?? ''),
        'idToken' => $token,
        'refreshToken' => '',
        'expiresIn' => '3600',
    ];
    if ($authData['localId'] === '' || $authData['email'] === '') return null;
    $profile = ensure_user_profile($authData, ['authProvider' => 'bearer']);
    return start_user_session($authData, $profile);
}

function current_user_or_null(): ?array {
    // Fast dashboard/API GET reads can be intentionally sessionless to avoid PHP
    // session-file locking on shared hosting. In that mode, trust the browser's
    // Firebase bearer token claims for identity and merge only local role/profile
    // cache. Firestore itself still receives the real bearer token for protected
    // reads/writes.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return stateless_user_from_bearer_if_possible();
    }

    // Critical user-isolation guard: when the browser sends a Firebase bearer
    // token, it is the freshest identity. If PHP still has an older session from
    // another user/device, replace that session before reading or writing PTJ data.
    $bearerPayload = ts_jwt_payload_from_bearer_token(bearer_token_from_request());
    if (is_array($bearerPayload)) {
        $bearerUid = (string)($bearerPayload['sub'] ?? '');
        $bearerEmail = strtolower(trim((string)($bearerPayload['email'] ?? '')));
        $sessionUid = (string)($_SESSION['user']['uid'] ?? '');
        $sessionEmail = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
        if (($bearerUid !== '' && $sessionUid !== '' && $bearerUid !== $sessionUid) || ($bearerEmail !== '' && $sessionEmail !== '' && $bearerEmail !== $sessionEmail)) {
            $_SESSION['user'] = null;
            $bootstrapped = create_session_from_bearer_if_possible();
            if (!$bootstrapped) {
                $fast = stateless_user_from_bearer_if_possible();
                if ($fast) return $fast;
            }
        }
    }

    if (empty($_SESSION['user'])) {
        $bootstrapped = create_session_from_bearer_if_possible();
        if (!$bootstrapped) return null;
    }
    $last = (int)($_SESSION['lastActivity'] ?? 0);
    if ($last && time() - $last > SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        return null;
    }
    $_SESSION['lastActivity'] = time();

    // Merge local backend profile updates into active sessions, e.g. when admin approves premium.
    if (!empty($_SESSION['user']['uid']) || !empty($_SESSION['user']['email'])) {
        $local = load_local_user_profile($_SESSION['user']['uid'] ?? '', $_SESSION['user']['email'] ?? '');
        if (!empty($local)) {
            $_SESSION['user'] = apply_backend_profile_guards(array_merge($_SESSION['user'], $local), $_SESSION['user']['email'] ?? '');
        } else {
            $_SESSION['user'] = apply_backend_profile_guards($_SESSION['user'], $_SESSION['user']['email'] ?? '');
        }
        $_SESSION['user'] = secure_profile_trading_accounts_for_identity($_SESSION['user'], (string)($_SESSION['user']['uid'] ?? ''), $_SESSION['user']['email'] ?? '');
    }
    return $_SESSION['user'];
}

function refresh_firebase_id_token_from_session(): ?string {
    $refreshToken = (string)($_SESSION['refreshToken'] ?? '');
    if ($refreshToken === '') return null;
    $url = 'https://securetoken.googleapis.com/v1/token?key=' . rawurlencode(FIREBASE_WEB_API_KEY);
    $result = http_form_request($url, [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);
    if (!($result['ok'] ?? false) || empty($result['data']['id_token'])) {
        api_log('Firebase ID token refresh failed: HTTP ' . ($result['status'] ?? 0) . ' ' . json_encode($result['data'] ?? $result));
        return null;
    }
    $_SESSION['idToken'] = (string)$result['data']['id_token'];
    if (!empty($result['data']['refresh_token'])) {
        $_SESSION['refreshToken'] = (string)$result['data']['refresh_token'];
    }
    return $_SESSION['idToken'];
}

function current_id_token_or_null(): ?string {
    $bearer = bearer_token_from_request();
    if ($bearer !== '') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['idToken'] = $bearer;
        }
        return $bearer;
    }
    current_user_or_null();
    $sessionToken = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['idToken'] ?? null) : null;
    if ($sessionToken) return $sessionToken;
    return (session_status() === PHP_SESSION_ACTIVE) ? refresh_firebase_id_token_from_session() : null;
}

function firebase_server_token_candidates(?string $idToken = null): array {
    $tokens = [];
    if ($idToken) $tokens[] = $idToken;
    $sessionToken = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['idToken'] ?? null) : null;
    if ($sessionToken && !in_array($sessionToken, $tokens, true)) $tokens[] = $sessionToken;
    // Do not refresh Firebase tokens during normal dashboard reads. On shared hosting
    // that refresh call was the main reason resources timed out and dashboards bounced
    // back to loading. Enable TS_ALLOW_SESSION_TOKEN_REFRESH only when the host is fast.
    if (ts_env_bool('TS_ALLOW_SESSION_TOKEN_REFRESH', false)) {
        $refreshed = refresh_firebase_id_token_from_session();
        if ($refreshed && !in_array($refreshed, $tokens, true)) $tokens[] = $refreshed;
    }
    $adminToken = firebase_admin_bearer_token_or_null();
    if ($adminToken && !in_array($adminToken, $tokens, true)) $tokens[] = $adminToken;
    return $tokens;
}

function require_current_user(): array {
    $user = current_user_or_null();
    if (!$user) json_response(['ok' => false, 'message' => 'Session expired. Please sign in again.'], 401);
    return $user;
}

function require_admin_user(): array {
    $user = require_current_user();
    if (($user['role'] ?? '') !== 'admin') json_response(['ok' => false, 'message' => 'Admin access required.'], 403);
    return $user;
}

function firestore_document_id_from_name(string $name): string {
    $parts = explode('/', $name);
    return urldecode(end($parts));
}

function firestore_list_collection(string $collectionPath, ?string $idToken = null, int $pageSize = 100): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . $collectionPath . '?pageSize=' . $pageSize;
    $headers = $idToken ? ['Authorization: Bearer ' . $idToken] : [];
    $result = http_json_request($url, null, 'GET', $headers);
    if (!$result['ok']) return $result;
    $rows = [];
    foreach (($result['data']['documents'] ?? []) as $doc) {
        $docName = (string)($doc['name'] ?? '');
        $rows[] = array_merge([
            'id' => firestore_document_id_from_name($docName),
            '_documentName' => $docName,
        ], firestore_fields_to_php($doc['fields'] ?? []));
    }
    $result['rows'] = $rows;
    return $result;
}


function firestore_run_structured_query(array $structuredQuery, ?string $idToken = null): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents:runQuery';
    $headers = $idToken ? ['Authorization: Bearer ' . $idToken] : [];
    $result = http_json_request($url, ['structuredQuery' => $structuredQuery], 'POST', $headers);
    if (!$result['ok']) return $result;
    $rows = [];
    foreach (($result['data'] ?? []) as $entry) {
        if (empty($entry['document']) || !is_array($entry['document'])) continue;
        $doc = $entry['document'];
        $fields = firestore_fields_to_php($doc['fields'] ?? []);
        $docName = $doc['name'] ?? '';
        $fields['_documentName'] = $docName;
        $fields['id'] = firestore_document_id_from_name($docName);
        $rows[] = $fields;
    }
    $result['rows'] = $rows;
    return $result;
}

function firestore_uid_from_profile_document_name(string $name): string {
    if (preg_match('#/users/([^/]+)/profile/data$#', $name, $m)) {
        return urldecode($m[1]);
    }
    return '';
}

function firestore_admin_profiles_cache_path(): string {
    ensure_dir(API_DATA_DIR);
    return API_DATA_DIR . '/admin_profiles_cache.json';
}

function firestore_admin_profiles_cache_read(int $ttlSeconds = 45): ?array {
    $file = firestore_admin_profiles_cache_path();
    if (!is_file($file) || (time() - filemtime($file)) > $ttlSeconds) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function firestore_admin_profiles_cache_write(array $profiles): void {
    ensure_dir(API_DATA_DIR);
    $json = json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;
    $file = firestore_admin_profiles_cache_path();
    if (is_file($file) && !is_writable($file)) @chmod($file, 0664);
    @file_put_contents($file, $json, LOCK_EX);
    if (is_file($file)) @chmod($file, 0664);
}

function firestore_admin_profiles_cache_clear(): void {
    $file = firestore_admin_profiles_cache_path();
    if (is_file($file)) @unlink($file);
}

function firestore_list_user_profiles(?string $idToken = null, bool $includeLocal = true, bool $useCache = true): array {
    if ($idToken && $useCache) {
        $cached = firestore_admin_profiles_cache_read();
        if (is_array($cached)) return $cached;
    }

    $profiles = [];
    $seen = [];
    $add = function(array $profile) use (&$profiles, &$seen) {
        $uid = (string)($profile['uid'] ?? '');
        $email = (string)($profile['email'] ?? '');
        $key = $uid !== '' ? 'uid:' . $uid : 'email:' . normalize_email_key($email);
        if ($key === 'email:' || isset($seen[$key])) return;
        $seen[$key] = true;
        $profile = apply_backend_profile_guards($profile, $email);
        $profiles[] = array_merge(['id' => $uid ?: $key, 'uid' => $uid], $profile);
    };

    if ($idToken) {
        // Fast rules-compatible primary path:
        // 1) list parent users once
        // 2) batchGet every /users/{uid}/profile/data document in one Firestore request.
        // This avoids slow N+1 sequential reads and avoids requiring collection-group rules.
        $usersResult = firestore_list_collection('artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/users', $idToken, 500);
        if ($usersResult['ok']) {
            $parentRows = $usersResult['rows'] ?? [];
            $profilePaths = [];
            foreach ($parentRows as $row) {
                $uid = (string)($row['id'] ?? '');
                if ($uid !== '') $profilePaths[$uid] = firestore_profile_path($uid);
            }

            $profilesByUid = [];
            if (!empty($profilePaths)) {
                foreach (array_chunk($profilePaths, 100, true) as $chunk) {
                    $batch = firestore_batch_get_documents(array_values($chunk), $idToken);
                    if (!($batch['ok'] ?? false)) {
                        api_log('Firestore batch profile read failed, falling back to individual reads: ' . json_encode($batch['data'] ?? $batch));
                        foreach ($chunk as $uid => $path) {
                            $single = firestore_get_document($path, $idToken);
                            if (($single['ok'] ?? false) && !empty($single['data']['fields'])) {
                                $profilesByUid[$uid] = firestore_fields_to_php($single['data']['fields']);
                            }
                        }
                        continue;
                    }
                    foreach (($batch['rows'] ?? []) as $row) {
                        $docName = (string)($row['_documentName'] ?? '');
                        $uid = firestore_uid_from_profile_document_name($docName);
                        if ($uid === '') continue;
                        unset($row['_documentName'], $row['id']);
                        $profilesByUid[$uid] = $row;
                    }
                }
            }

            foreach ($parentRows as $row) {
                $uid = (string)($row['id'] ?? '');
                if ($uid === '') continue;
                $rowProfile = array_merge(['uid' => $uid], $row);
                unset($rowProfile['id']);
                $profile = $profilesByUid[$uid] ?? [];
                $base = !empty($profile) ? array_merge($rowProfile, $profile, ['uid' => $uid]) : $rowProfile;
                $local = load_local_user_profile($uid, $base['email'] ?? '');
                if (!empty($base) || !empty($local)) $add(merge_durable_user_profile($base, $local));
            }
        } else {
            api_log('Firestore parent users list failed. Trying optional collection-group fallback: ' . json_encode($usersResult['data'] ?? $usersResult));

            // Optional fallback only. This may be blocked by rules, so it is intentionally not the primary path.
            $query = [
                'from' => [[
                    'collectionId' => 'profile',
                    'allDescendants' => true,
                ]],
                'limit' => 500,
            ];
            $cg = firestore_run_structured_query($query, $idToken);
            if ($cg['ok']) {
                foreach (($cg['rows'] ?? []) as $row) {
                    $docName = (string)($row['_documentName'] ?? '');
                    if (!preg_match('#/profile/data$#', $docName)) continue;
                    $uid = firestore_uid_from_profile_document_name($docName);
                    unset($row['_documentName']);
                    if ($uid !== '') $row['uid'] = $uid;
                    $local = load_local_user_profile($uid, $row['email'] ?? '');
                    $add(merge_durable_user_profile($row, $local));
                }
            }
        }
    }

    if ($includeLocal) {
        foreach (local_profile_store_read() as $localProfile) {
            if (!is_array($localProfile)) continue;
            $add($localProfile);
        }
    }

    if ($idToken) firestore_admin_profiles_cache_write($profiles);
    return $profiles;
}


function firestore_create_document(string $collectionPath, array $fields, ?string $idToken = null): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . $collectionPath;
    $fsFields = [];
    foreach ($fields as $key => $value) $fsFields[$key] = php_to_firestore_value($value);
    $headers = $idToken ? ['Authorization: Bearer ' . $idToken] : [];
    return http_json_request($url, ['fields' => $fsFields], 'POST', $headers);
}

function firestore_delete_document(string $documentPath, ?string $idToken = null): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . $documentPath;
    $headers = $idToken ? ['Authorization: Bearer ' . $idToken] : [];
    return http_json_request($url, null, 'DELETE', $headers);
}

function firestore_patch_session_profile(array $fields): void {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        foreach ($fields as $k => $v) {
            $_SESSION['user'][$k] = $v;
        }
        $_SESSION['user'] = apply_backend_profile_guards($_SESSION['user'], $_SESSION['user']['email'] ?? '');
        save_local_user_profile($_SESSION['user']['uid'] ?? '', $_SESSION['user']['email'] ?? '', $_SESSION['user']);
    }
}

function require_method(string $method): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function safe_return_to(?string $returnTo): string {
    if (!$returnTo || !is_string($returnTo)) return '/';
    if (preg_match('#^https?://#i', $returnTo)) return '/';
    if ($returnTo[0] !== '/') return '/';
    return $returnTo;
}

// SMTP email settings for app notifications.
// Create/use a Zoho app password for support@traderssanctuary.com and paste it below on the live server.
define('SMTP_ENABLED', ts_env_bool('SMTP_ENABLED', false));
define('SMTP_HOST', ts_env('SMTP_HOST', 'smtp.zoho.com'));
define('SMTP_PORT', (int)ts_env('SMTP_PORT', 465));
define('SMTP_SECURE', ts_env('SMTP_SECURE', 'ssl')); // ssl for 465, tls for 587.
define('SMTP_USERNAME', ts_env('SMTP_USERNAME', 'support@traderssanctuary.com'));
define('SMTP_PASSWORD', ts_env('SMTP_PASSWORD', ''));
define('MAIL_FROM_EMAIL', ts_env('MAIL_FROM_EMAIL', 'support@traderssanctuary.com'));
define('MAIL_FROM_NAME', ts_env('MAIL_FROM_NAME', 'Traders Sanctuary Support'));
define('MAIL_ADMIN_EMAIL', ts_env('MAIL_ADMIN_EMAIL', 'admin@traderssanctuary.com'));
define('MAIL_REPLY_TO', ts_env('MAIL_REPLY_TO', 'support@traderssanctuary.com'));
define('SITE_BASE_URL', ts_env('SITE_BASE_URL', 'https://traderssanctuary.com'));
define('PREMIUM_EXPIRY_REMINDER_DAYS', 3);
define('SUBSCRIPTION_CRON_SECRET', ts_env('SUBSCRIPTION_CRON_SECRET', 'CHANGE_ME'));

function mail_config_is_ready(): bool {
    return SMTP_ENABLED
        && SMTP_HOST !== ''
        && SMTP_USERNAME !== ''
        && SMTP_PASSWORD !== ''
        && SMTP_PASSWORD !== 'REPLACE_WITH_ZOHO_APP_PASSWORD';
}

function mail_header_encode(string $value): string {
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_expect($socket, array $codes, string $stage): array {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        return ['ok' => false, 'message' => 'SMTP ' . $stage . ' failed: ' . trim($response)];
    }
    return ['ok' => true, 'response' => trim($response)];
}

function smtp_send_command($socket, string $command, array $codes, string $stage): array {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $codes, $stage);
}

function app_send_email(string $to, string $subject, string $textBody, ?string $htmlBody = null): array {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid recipient email.'];
    }
    if (!mail_config_is_ready()) {
        api_log('Email skipped because SMTP is not configured. Intended recipient: ' . $to . ', subject: ' . $subject);
        return ['ok' => false, 'message' => 'SMTP is not configured.'];
    }

    $host = SMTP_HOST;
    $port = (int)SMTP_PORT;
    $securePrefix = strtolower(SMTP_SECURE) === 'ssl' ? 'ssl://' : '';
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($securePrefix . $host, $port, $errno, $errstr, 30);
    if (!$socket) {
        api_log('SMTP connection failed: ' . $errstr);
        return ['ok' => false, 'message' => 'SMTP connection failed: ' . $errstr];
    }
    stream_set_timeout($socket, 30);

    $check = smtp_expect($socket, [220], 'connect');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }

    $serverName = $_SERVER['SERVER_NAME'] ?? 'traderssanctuary.com';
    $check = smtp_send_command($socket, 'EHLO ' . $serverName, [250], 'EHLO');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }

    if (strtolower(SMTP_SECURE) === 'tls') {
        $check = smtp_send_command($socket, 'STARTTLS', [220], 'STARTTLS');
        if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            api_log('SMTP STARTTLS crypto negotiation failed.');
            return ['ok' => false, 'message' => 'SMTP STARTTLS failed.'];
        }
        $check = smtp_send_command($socket, 'EHLO ' . $serverName, [250], 'EHLO after STARTTLS');
        if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
    }

    $check = smtp_send_command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
    $check = smtp_send_command($socket, base64_encode(SMTP_USERNAME), [334], 'SMTP username');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
    $check = smtp_send_command($socket, base64_encode(SMTP_PASSWORD), [235], 'SMTP password');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }

    $from = MAIL_FROM_EMAIL;
    $check = smtp_send_command($socket, 'MAIL FROM:<' . $from . '>', [250], 'MAIL FROM');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
    $check = smtp_send_command($socket, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }
    $check = smtp_send_command($socket, 'DATA', [354], 'DATA');
    if (!$check['ok']) { fclose($socket); api_log($check['message']); return $check; }

    $date = date('r');
    $messageId = '<' . bin2hex(random_bytes(12)) . '@traderssanctuary.com>';
    $encodedSubject = mail_header_encode($subject);
    $encodedFromName = mail_header_encode(MAIL_FROM_NAME);
    $headers = [];
    $headers[] = 'Date: ' . $date;
    $headers[] = 'From: ' . $encodedFromName . ' <' . $from . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Reply-To: ' . MAIL_REPLY_TO;
    $headers[] = 'Message-ID: ' . $messageId;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Subject: ' . $encodedSubject;

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = 'ts_boundary_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $textBody . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $htmlBody . "\r\n";
        $body .= "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $textBody;
    }

    $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
    fwrite($socket, $data . "\r\n");
    $check = smtp_expect($socket, [250], 'message send');
    smtp_send_command($socket, 'QUIT', [221, 250], 'QUIT');
    fclose($socket);

    if (!$check['ok']) { api_log($check['message']); return $check; }
    return ['ok' => true, 'message' => 'Email sent.'];
}

function premium_email_html(string $title, array $lines): string {
    $escapedLines = array_map(fn($line) => '<p style="margin:0 0 12px;color:#334155;line-height:1.6;">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>', $lines);
    return '<div style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;">'
        . '<div style="max-width:620px;margin:auto;background:white;border-radius:14px;padding:28px;border:1px solid #e5e7eb;">'
        . '<h2 style="margin:0 0 16px;color:#1e40af;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
        . implode('', $escapedLines)
        . '<p style="margin-top:22px;color:#64748b;font-size:13px;">Traders Sanctuary — Educational community, journal tools, and analytics. No financial advice or profit guarantees.</p>'
        . '</div></div>';
}

function format_user_date(?string $iso): string {
    if (!$iso) return 'N/A';
    $ts = strtotime($iso);
    if ($ts === false) return $iso;
    return gmdate('M j, Y', $ts);
}

function send_payment_approved_email(array $payment): array {
    $to = $payment['email'] ?? '';
    $expiry = $payment['premiumExpiresAt'] ?? '';
    $lines = [
        'Hi ' . (($payment['displayName'] ?? '') ?: 'there') . ',',
        'Your M-PESA payment has been verified successfully. Your Premium access is now active.',
        'Plan: Premium Membership (' . PREMIUM_DAYS . ' days)',
        'Amount: KES ' . ($payment['amount'] ?? PREMIUM_PRICE_KES),
        'M-PESA Code: ' . ($payment['mpesaCode'] ?? 'N/A'),
        'Premium expiry date: ' . format_user_date($expiry),
        'Your journal data remains safe and will still be available whenever your Premium access is active.',
    ];
    return app_send_email($to, 'Payment confirmed — Premium activated', implode("\n\n", $lines), premium_email_html('Payment confirmed — Premium activated', $lines));
}

function send_payment_rejected_email(array $payment): array {
    $to = $payment['email'] ?? '';
    $lines = [
        'Hi ' . (($payment['displayName'] ?? '') ?: 'there') . ',',
        'Your submitted M-PESA payment proof could not be verified yet.',
        'M-PESA Code: ' . ($payment['mpesaCode'] ?? 'N/A'),
        'Admin note: ' . (($payment['adminNote'] ?? '') ?: 'Please contact support with the correct transaction details.'),
        'You can reply to this email or submit the correct payment code from the Premium page.',
    ];
    return app_send_email($to, 'Payment verification update', implode("\n\n", $lines), premium_email_html('Payment verification update', $lines));
}

function send_payment_submitted_admin_email(array $payment): array {
    $lines = [
        'A new manual M-PESA payment proof has been submitted.',
        'User: ' . (($payment['email'] ?? '') ?: 'N/A'),
        'Name: ' . (($payment['displayName'] ?? '') ?: 'N/A'),
        'Amount: KES ' . ($payment['amount'] ?? 'N/A'),
        'Phone: ' . ($payment['phone'] ?? 'N/A'),
        'M-PESA Code: ' . ($payment['mpesaCode'] ?? 'N/A'),
        'Open Admin → Payments to approve or reject it.',
    ];
    return app_send_email(MAIL_ADMIN_EMAIL, 'New M-PESA payment proof submitted', implode("\n\n", $lines), premium_email_html('New M-PESA payment proof submitted', $lines));
}

function send_premium_expiry_reminder_email(array $profile, int $daysLeft): array {
    $lines = [
        'Hi ' . (($profile['displayName'] ?? '') ?: 'there') . ',',
        'Your Traders Sanctuary Premium access expires in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . '.',
        'Expiry date: ' . format_user_date($profile['premiumExpiresAt'] ?? ''),
        'Your journal data is safe. Renew Premium to keep accessing your full journal and analytics.',
        'You can renew from the Premium page using M-PESA.',
    ];
    return app_send_email($profile['email'] ?? '', 'Premium expires soon — Traders Sanctuary', implode("\n\n", $lines), premium_email_html('Premium expires soon', $lines));
}

function send_premium_expired_email(array $profile): array {
    $lines = [
        'Hi ' . (($profile['displayName'] ?? '') ?: 'there') . ',',
        'Your Traders Sanctuary Premium access has expired and your account has returned to member access.',
        'Your journal data has not been deleted. It remains safe and will be available again when you renew Premium.',
        'You can renew anytime from the Premium page using M-PESA.',
    ];
    return app_send_email($profile['email'] ?? '', 'Premium expired — Your journal data is safe', implode("\n\n", $lines), premium_email_html('Premium expired', $lines));
}
