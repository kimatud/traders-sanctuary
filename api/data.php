<?php

// --- STORAGE SELF-HEALING HELPERS ---
function ts_storage_dir() {
    $dir = __DIR__ . '/_data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0775);
    }
    return $dir;
}

function ts_storage_file($filename, $defaultContent = '[]') {
    $dir = ts_storage_dir();
    $file = $dir . '/' . basename($filename);

    if (!file_exists($file)) {
        @file_put_contents($file, $defaultContent, LOCK_EX);
        @chmod($file, 0664);
    }

    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, 0664);
    }

    return $file;
}

function ts_safe_read_json($filename, $default = []) {
    $defaultContent = is_array($default) && array_keys($default) !== range(0, count($default) - 1) ? '{}' : '[]';
    $file = ts_storage_file($filename, $defaultContent);
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function ts_safe_write_json($filename, $data) {
    $defaultContent = is_array($data) && array_keys($data) !== range(0, count($data) - 1) ? '{}' : '[]';
    $file = ts_storage_file($filename, $defaultContent);

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $ok = @file_put_contents($file, $json, LOCK_EX);
    if ($ok === false) {
        @chmod(dirname($file), 0777);
        @chmod($file, 0666);
        $ok = @file_put_contents($file, $json, LOCK_EX);
    }

    return $ok !== false;
}

$action = $_GET['action'] ?? '';
$publicFastActions = ['public_testimonials', 'public_testimonials_fresh', 'list_announcements'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearerHeader = is_string($authHeader) && stripos($authHeader, 'Bearer ') !== false;
$hasTokenFallback = !empty($_GET['ts_id_token']) && is_string($_GET['ts_id_token']);
$preConfigPublicFastRead = in_array((string)$action, $publicFastActions, true) && empty($_GET['admin']) && $method === 'GET';

// Critical dashboard GET reads must not open/lock the PHP session. The browser
// already sends a Firebase bearer token through apiFetch(), so config.php can
// build a stateless user from that token and local profile cache. This prevents
// slow background session bridging from queueing study materials, notifications,
// testimonials, announcements, and trading notes until the frontend timeout fires.
if ($preConfigPublicFastRead || ($method === 'GET' && ($hasBearerHeader || $hasTokenFallback))) {
    define('TS_SKIP_SESSION_START', true);
}

require __DIR__ . '/config.php';

$payload = read_json_body();

// Do not bootstrap/refresh Firebase sessions for public fast reads. These calls power
// landing-page testimonials, certificates, and announcement badges; they must never
// hold the whole app hostage while Firebase or the host is slow. They also skip
// PHP session_start so concurrent resource calls cannot lock each other.
$isPublicFastRead = in_array((string)$action, $publicFastActions, true) && (ts_env_bool('TS_FAST_PUBLIC_READS', true) || !empty($_GET['fast'])) && empty($_GET['admin']) && $_SERVER['REQUEST_METHOD'] === 'GET';
$user = $isPublicFastRead ? null : current_user_or_null();
// Public dashboard content is stored in Firebase. We still skip PHP sessions for
// speed, but we must NOT discard the browser Firebase bearer token; Firestore
// rules often require it for announcements/testimonials reads.
$idToken = current_id_token_or_null();

function ok_rows(array $result, string $key = 'items'): void {
    if (!$result['ok']) json_response(['ok' => false, 'message' => 'Database request failed.', 'details' => $result['data']['error']['message'] ?? null], 500);
    json_response(['ok' => true, $key => $result['rows'] ?? []]);
}

function sorted_by_date_desc(array $rows, string $field = 'createdAt'): array {
    usort($rows, fn($a, $b) => strtotime($b[$field] ?? '') <=> strtotime($a[$field] ?? ''));
    return $rows;
}

function data_store_dir(): string {
    $dir = __DIR__ . '/_data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (is_dir($dir)) @chmod($dir, 0775);
    return $dir;
}

function data_store_path(string $name): string {
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    return data_store_dir() . '/' . $safe . '.json';
}

function data_store_read(string $name): array {
    $file = data_store_path($name);
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function data_store_write(string $name, array $rows): bool {
    $file = data_store_path($name);
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (is_file($file) && !is_writable($file)) @chmod($file, 0664);
    if (!is_writable($dir)) @chmod($dir, 0775);
    $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $result = @file_put_contents($file, $json, LOCK_EX);
    if ($result === false) {
        $err = error_get_last()['message'] ?? 'unknown file write error';
        api_log('Local data store write failed for ' . $name . ' at ' . $file . ': ' . $err);
        return false;
    }
    @chmod($file, 0664);
    return true;
}

function data_store_status(string $name = 'manual_payments'): array {
    $dir = data_store_dir();
    $file = data_store_path($name);
    return [
        'dir' => $dir,
        'dir_exists' => is_dir($dir),
        'dir_writable' => is_writable($dir),
        'file' => $file,
        'file_exists' => is_file($file),
        'file_writable' => is_file($file) ? is_writable($file) : null,
        'item_count' => count(data_store_read($name)),
    ];
}



function artifact_namespace_roots(): array {
    // Read every namespace the app has used across iterations. Older builds wrote
    // under the Firebase project id, some under the web app id, and some shared-host
    // fallbacks used simpler root/user paths. Keeping these candidates prevents a
    // UI redesign from making existing Firebase data look like zeros.
    $ids = [FIREBASE_PROJECT_ID];
    if (defined('FIREBASE_WEB_APP_ID')) $ids[] = FIREBASE_WEB_APP_ID;
    $roots = [];
    foreach ($ids as $id) {
        $id = trim((string)$id);
        if ($id === '') continue;
        $root = 'artifacts/' . rawurlencode($id);
        if (!in_array($root, $roots, true)) $roots[] = $root;
    }
    return $roots;
}

function unique_firestore_paths(array $paths): array {
    $out = [];
    foreach ($paths as $path) {
        $path = trim((string)$path, '/');
        if ($path !== '' && !in_array($path, $out, true)) $out[] = $path;
    }
    return $out;
}

function artifact_public_collection_paths(string $collection): array {
    $collection = rawurlencode($collection);
    $paths = [];
    foreach (artifact_namespace_roots() as $root) {
        $paths[] = $root . '/public/data/' . $collection;
    }
    return unique_firestore_paths($paths);
}

function public_collection_paths_with_legacy_root(string $collection): array {
    $c = rawurlencode($collection);
    $paths = artifact_public_collection_paths($collection);
    $paths[] = $c;                         // /announcements
    $paths[] = 'public/data/' . $c;         // /public/data/announcements
    $paths[] = 'public/' . $c;              // /public/announcements
    $paths[] = 'platform/' . $c . '/items'; // /platform/announcements/items
    return unique_firestore_paths($paths);
}

function artifact_user_collection_paths(string $uid, string $collection): array {
    $u = rawurlencode($uid);
    $c = rawurlencode($collection);
    $paths = [];
    foreach (artifact_namespace_roots() as $root) {
        $paths[] = $root . '/users/' . $u . '/' . $c;
    }
    // Legacy/root fallbacks used before the account-based PTJ rebuild.
    $paths[] = 'users/' . $u . '/' . $c;
    $paths[] = 'user_data/' . $u . '/' . $c;
    $paths[] = 'members/' . $u . '/' . $c;
    return unique_firestore_paths($paths);
}

function user_collection_aliases(string $collection): array {
    $aliases = [$collection];
    if ($collection === 'premium_journal') $aliases = array_merge($aliases, ['journal', 'trades', 'trade_logs', 'premiumJournal']);
    if ($collection === 'trading_accounts') $aliases = array_merge($aliases, ['tradingAccounts', 'accounts', 'trade_accounts']);
    return array_values(array_unique($aliases));
}

function expanded_user_collection_paths(string $uid, string $collection): array {
    $paths = [];
    foreach (user_collection_aliases($collection) as $alias) {
        $paths = array_merge($paths, artifact_user_collection_paths($uid, $alias));
    }
    return unique_firestore_paths($paths);
}

function firestore_list_first_available(array $collectionPaths, ?string $idToken = null, int $maxPaths = 8): array {
    $merged = [];
    $firstError = null;
    $collectionPaths = array_slice(unique_firestore_paths($collectionPaths), 0, max(1, $maxPaths));
    foreach ($collectionPaths as $path) {
        $r = firestore_list_collection($path, $idToken);
        if ($r['ok']) {
            $rows = $r['rows'] ?? [];
            if (!empty($rows)) {
                // Fast path: announcements/testimonials are written to every supported public path,
                // so the first populated namespace is enough and avoids slow sequential waits.
                return ['ok' => true, 'rows' => $rows, 'sourcePath' => $path];
            }
            $merged = array_merge($merged, $rows);
        } elseif ($firstError === null) {
            $firstError = $r;
            api_log('Firestore list failed for ' . $path . ': ' . json_encode($r['data'] ?? $r));
        }
    }
    if (!empty($merged)) return ['ok' => true, 'rows' => $merged];
    return $firstError ?: ['ok' => false, 'data' => ['error' => ['message' => 'No Firestore namespace could be read.']]];
}

function firestore_list_with_token_candidates(array $collectionPaths, array $tokens, int $maxPaths = 8, int $maxTokens = 2): array {
    $merged = [];
    $firstError = null;
    $attempted = 0;
    $tokens = array_values(array_unique(array_filter($tokens, fn($token) => trim((string)$token) !== '')));
    if (empty($tokens)) $tokens = [null];
    $tokens = array_slice($tokens, 0, max(1, $maxTokens));
    foreach ($tokens as $token) {
        $attempted++;
        $r = firestore_list_first_available($collectionPaths, $token, $maxPaths);
        if ($r['ok']) {
            $merged = array_merge($merged, $r['rows'] ?? []);
        } elseif ($firstError === null) {
            $firstError = $r;
        }
    }
    if (!empty($merged)) return ['ok' => true, 'rows' => $merged, 'attempts' => $attempted];
    return $firstError ?: ['ok' => false, 'data' => ['error' => ['message' => 'No Firestore token candidate could read data.']], 'attempts' => $attempted];
}

function firestore_list_public_quick(array $collectionPaths, ?string $idToken = null, int $maxPaths = 12): array {
    // Firebase-backed public content must read from Firestore first. Local JSON is
    // only a fallback/cache after deployment. Try the user's Firebase token and the
    // server/admin token so rules-compatible data loads reliably.
    $rows = [];
    $firstError = null;
    $tokens = firebase_server_token_candidates($idToken);
    if (empty($tokens)) $tokens = [$idToken];
    $paths = array_slice(unique_firestore_paths($collectionPaths), 0, max(1, $maxPaths));

    foreach ($tokens as $token) {
        foreach ($paths as $path) {
            if (!function_exists('curl_init')) {
                $firstError = ['ok' => false, 'status' => 0, 'data' => ['error' => ['message' => 'cURL is unavailable for Firestore public reads.']]];
                break 2;
            }
            $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode(FIREBASE_PROJECT_ID) . '/databases/(default)/documents/' . trim($path, '/') . '?pageSize=200';
            $headers = $token ? ['Authorization: Bearer ' . $token] : [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT_MS => 1800,
                CURLOPT_CONNECTTIMEOUT_MS => 600,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($errno) {
                if ($firstError === null) $firstError = ['ok' => false, 'status' => 0, 'data' => ['error' => ['message' => $error ?: 'Firestore public request timed out.']]];
                continue;
            }
            $data = json_decode($body ?: 'null', true);
            if ($status < 200 || $status >= 300 || !is_array($data)) {
                if ($firstError === null) $firstError = ['ok' => false, 'status' => $status, 'data' => is_array($data) ? $data : ['error' => ['message' => 'Firestore public request failed.']]];
                continue;
            }
            foreach (($data['documents'] ?? []) as $doc) {
                if (!is_array($doc)) continue;
                $rows[] = array_merge(['id' => firestore_document_id_from_name($doc['name'] ?? '')], firestore_fields_to_php($doc['fields'] ?? []));
            }
            if (!empty($rows)) return ['ok' => true, 'rows' => $rows, 'sourcePath' => $path, 'quick' => true, 'firebaseConnected' => true];
        }
    }

    // Last-chance: if old builds stored under a collection with this id at an
    // unknown nested path, a collection-group read can still find it.
    $collectionIds = [];
    foreach ($paths as $path) {
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($parts)) $collectionIds[] = end($parts);
    }
    $collectionIds = array_values(array_unique(array_filter($collectionIds)));
    foreach ($tokens as $token) {
        foreach (array_slice($collectionIds, 0, 3) as $collectionId) {
            $query = [
                'from' => [[ 'collectionId' => $collectionId, 'allDescendants' => true ]],
                'limit' => 200,
            ];
            $cg = firestore_run_structured_query($query, $token);
            if ($cg['ok'] && !empty($cg['rows'])) {
                return ['ok' => true, 'rows' => $cg['rows'], 'sourcePath' => 'collectionGroup:' . $collectionId, 'quick' => false, 'firebaseConnected' => true];
            }
            if (!$cg['ok'] && $firstError === null) $firstError = $cg;
        }
    }

    return $firstError ?: ['ok' => false, 'data' => ['error' => ['message' => 'No Firebase public rows found.']], 'quick' => true];
}


function firestore_collection_group_user_rows(string $collection, string $uid, array $tokens, int $limit = 300, int $maxAttempts = 8): array {
    $rows = [];
    $seen = [];
    $attempts = 0;
    $filters = ['userId', 'uid', 'ownerId', 'firebaseUid', 'createdBy'];
    $tokens = array_slice(array_values(array_unique(array_filter($tokens, fn($token) => trim((string)$token) !== ''))), 0, 2);
    foreach (user_collection_aliases($collection) as $alias) {
        foreach ($tokens as $token) {
            foreach ($filters as $field) {
                if (++$attempts > $maxAttempts) return array_values($rows);
                $query = [
                    'from' => [[ 'collectionId' => $alias, 'allDescendants' => true ]],
                    'where' => [
                        'fieldFilter' => [
                            'field' => ['fieldPath' => $field],
                            'op' => 'EQUAL',
                            'value' => ['stringValue' => $uid],
                        ]
                    ],
                    'limit' => $limit,
                ];
                $r = firestore_run_structured_query($query, $token);
                if (!$r['ok']) continue;
                foreach (($r['rows'] ?? []) as $row) {
                    $id = (string)($row['id'] ?? ($row['_documentName'] ?? ''));
                    if ($id === '') $id = md5(json_encode($row));
                    if (isset($seen[$id])) continue;
                    $seen[$id] = true;
                    $rows[] = $row;
                }
            }
        }
    }
    return $rows;
}


function admin_firestore_tokens(?string $idToken = null): array {
    $tokens = firebase_server_token_candidates($idToken);
    $tokens = array_values(array_unique(array_filter($tokens, fn($token) => trim((string)$token) !== '')));
    return $tokens;
}

function firestore_list_admin_public_resilient(string $collection, ?string $idToken = null): array {
    $paths = public_collection_paths_with_legacy_root($collection);
    $tokens = admin_firestore_tokens($idToken);
    if (!empty($tokens)) {
        $r = firestore_list_with_token_candidates($paths, $tokens);
        if (($r['ok'] ?? false) && !empty($r['rows'])) {
            $r['paths'] = $paths;
            $r['source'] = 'firebase';
            return $r;
        }
        if (!empty($r)) api_log('Admin public collection read fallback for ' . $collection . ': ' . json_encode($r['data'] ?? $r));
    }
    return ['ok' => false, 'rows' => [], 'paths' => $paths, 'source' => 'none', 'data' => ['error' => ['message' => 'Firebase public collection was unavailable. Local cache used where possible.']]];
}

function merge_admin_profiles_by_identity(array $profiles): array {
    $out = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) continue;
        $uid = (string)($profile['uid'] ?? $profile['id'] ?? '');
        $email = strtolower(trim((string)($profile['email'] ?? '')));
        if (function_exists('is_admin_email') && is_admin_email($email)) {
            $profile['role'] = 'admin';
        }
        $key = $uid !== '' ? 'uid:' . $uid : ($email !== '' ? 'email:' . $email : 'row:' . substr(sha1(json_encode($profile)), 0, 16));
        $merged = array_merge($out[$key] ?? [], $profile, $uid !== '' ? ['uid' => $uid, 'id' => $uid] : []);
        if (function_exists('is_admin_email') && is_admin_email($merged['email'] ?? $email)) {
            $merged['role'] = 'admin';
        }
        $out[$key] = $merged;
    }
    return array_values($out);
}

function firestore_list_user_profiles_resilient_admin(?string $idToken = null): array {
    $profiles = [];
    $tokens = admin_firestore_tokens($idToken);
    foreach ($tokens as $token) {
        $rows = firestore_list_user_profiles($token, true, false);
        if (!empty($rows)) $profiles = array_merge($profiles, $rows);
    }
    // Always include durable local profiles so the admin panel stays useful even while Firebase rules are being repaired.
    foreach (local_profile_store_read() as $localProfile) {
        if (is_array($localProfile)) $profiles[] = $localProfile;
    }
    return merge_admin_profiles_by_identity($profiles);
}

function admin_write_token(?string $idToken = null): ?string {
    return firebase_admin_bearer_token_or_null() ?: $idToken;
}

function firestore_list_user_collection_resilient(string $uid, string $collection, array $tokens): array {
    $paths = expanded_user_collection_paths($uid, $collection);
    $r = firestore_list_with_token_candidates($paths, $tokens, 8, 2);
    $rows = $r['ok'] ? ($r['rows'] ?? []) : [];
    $groupRows = firestore_collection_group_user_rows($collection, $uid, $tokens);
    if (!empty($groupRows)) $rows = merge_journal_rows($rows, $groupRows);
    if (!empty($rows)) return ['ok' => true, 'rows' => $rows, 'paths' => $paths, 'source' => !empty($groupRows) ? 'paths_plus_collection_group' : 'paths'];
    return $r;
}

function firestore_write_all_create(array $collectionPaths, array $fields, ?string $idToken = null): array {
    $firstOk = null;
    $last = null;
    foreach ($collectionPaths as $path) {
        $r = firestore_create_document($path, $fields, $idToken);
        $last = $r;
        if ($r['ok'] && $firstOk === null) $firstOk = $r;
        if (!$r['ok']) api_log('Firestore create failed for ' . $path . ': ' . json_encode($r['data'] ?? $r));
    }
    return $firstOk ?: ($last ?: ['ok' => false]);
}

function firestore_write_all_patch(array $documentPaths, array $fields, ?string $idToken = null): array {
    $firstOk = null;
    $last = null;
    foreach ($documentPaths as $path) {
        $r = firestore_patch_document($path, $fields, (string)$idToken);
        $last = $r;
        if ($r['ok'] && $firstOk === null) $firstOk = $r;
        if (!$r['ok']) api_log('Firestore patch failed for ' . $path . ': ' . json_encode($r['data'] ?? $r));
    }
    return $firstOk ?: ($last ?: ['ok' => false]);
}

function firestore_write_all_set(array $documentPaths, array $fields, ?string $idToken = null): array {
    $firstOk = null;
    $last = null;
    foreach ($documentPaths as $path) {
        $r = firestore_patch_document($path, $fields, (string)$idToken);
        $last = $r;
        if ($r['ok'] && $firstOk === null) $firstOk = $r;
        if (!$r['ok']) api_log('Firestore set failed for ' . $path . ': ' . json_encode($r['data'] ?? $r));
    }
    return $firstOk ?: ($last ?: ['ok' => false]);
}

function firestore_write_all_delete(array $documentPaths, ?string $idToken = null): array {
    $firstOk = null;
    $last = null;
    foreach ($documentPaths as $path) {
        $r = firestore_delete_document($path, $idToken);
        $last = $r;
        if ($r['ok'] && $firstOk === null) $firstOk = $r;
        if (!$r['ok']) api_log('Firestore delete failed for ' . $path . ': ' . json_encode($r['data'] ?? $r));
    }
    return $firstOk ?: ($last ?: ['ok' => false]);
}

function document_paths_from_collections(array $collectionPaths, string $id): array {
    $paths = [];
    foreach ($collectionPaths as $path) {
        $paths[] = $path . '/' . rawurlencode($id);
    }
    return $paths;
}

function user_scoped_store_name(string $prefix, string $uid, string $email = ''): string {
    $key = $uid !== '' ? $uid : strtolower(trim($email));
    if ($key === '') $key = 'anonymous';
    return $prefix . '_' . substr(sha1($key), 0, 24);
}

function journal_store_name(array $user): string {
    return user_scoped_store_name('premium_journal', (string)($user['uid'] ?? ''), (string)($user['email'] ?? ''));
}

function merge_journal_rows(array $a, array $b): array {
    $out = [];
    foreach (array_merge($a, $b) as $row) {
        if (!is_array($row)) continue;
        $id = (string)($row['id'] ?? '');
        if ($id === '') {
            $id = 'local_' . substr(sha1(json_encode($row) . microtime(true)), 0, 16);
            $row['id'] = $id;
        }
        $existing = $out[$id] ?? [];
        $out[$id] = array_merge($existing, $row);
    }
    return array_values($out);
}


function stamp_journal_row_owner(array $row, array $user): array {
    $uid = trim((string)($user['uid'] ?? ''));
    $email = strtolower(trim((string)($user['email'] ?? '')));
    if ($uid !== '') {
        $row['userId'] = $uid;
        $row['uid'] = $uid;
        $row['ownerId'] = $uid;
    }
    if ($email !== '') {
        $row['userEmail'] = $email;
        $row['email'] = $email;
    }
    return $row;
}

function journal_row_is_from_user_private_path(array $row, array $user): bool {
    $uid = trim((string)($user['uid'] ?? ''));
    if ($uid === '') return false;
    $docName = (string)($row['_documentName'] ?? $row['name'] ?? '');
    if ($docName === '') return false;
    $quotedUid = preg_quote($uid, '#');
    return (bool)preg_match('#/(users|user_data|members)/' . $quotedUid . '/#', $docName);
}

function stamp_safe_journal_rows_for_user(array $rows, array $user, bool $trustedUserScopedSource = false): array {
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        // Rows loaded from this user's private JSON store or exact /users/{uid}/...
        // Firestore path are safe to stamp. Rows with explicit wrong owner markers
        // are still rejected later by journal_row_matches_user().
        if ($trustedUserScopedSource || journal_row_is_from_user_private_path($row, $user) || journal_row_matches_user($row, $user)) {
            $row = stamp_journal_row_owner($row, $user);
        }
        $out[] = $row;
    }
    return $out;
}

function journal_row_matches_user(array $row, array $user): bool {
    $uid = trim((string)($user['uid'] ?? ''));
    $email = strtolower(trim((string)($user['email'] ?? '')));
    $ownerFields = ['userId', 'uid', 'ownerId', 'firebaseUid', 'createdBy', 'memberUid'];
    $emailFields = ['email', 'userEmail', 'memberEmail', 'createdByEmail'];

    foreach ($ownerFields as $field) {
        if (!array_key_exists($field, $row) || trim((string)$row[$field]) === '') continue;
        return $uid !== '' && trim((string)$row[$field]) === $uid;
    }
    foreach ($emailFields as $field) {
        if (!array_key_exists($field, $row) || trim((string)$row[$field]) === '') continue;
        return $email !== '' && strtolower(trim((string)$row[$field])) === $email;
    }

    // Privacy-first PTJ isolation: markerless rows are accepted only when Firestore
    // proves they came from the exact current user's document path. Local/cache-only
    // markerless rows are not displayed, preventing one member's old cache from
    // appearing in another member's dashboard.
    $docName = (string)($row['_documentName'] ?? '');
    if ($uid !== '' && $docName !== '') {
        $quotedUid = preg_quote($uid, '#');
        if (preg_match('#/(users|user_data|members)/' . $quotedUid . '/#', $docName)) return true;
    }
    return false;
}

function filter_journal_rows_for_user(array $rows, array $user): array {
    return array_values(array_filter($rows, fn($row) => is_array($row) && journal_row_matches_user($row, $user)));
}

function cache_journal_rows(array $user, array $rows): void {
    if (empty($user['uid']) && empty($user['email'])) return;
    data_store_write(journal_store_name($user), merge_journal_rows(data_store_read(journal_store_name($user)), $rows));
}

function upsert_cached_journal_row(array $user, array $row): void {
    if (empty($row['id'])) $row['id'] = 'local_' . bin2hex(random_bytes(8));
    cache_journal_rows($user, [$row]);
}

function delete_cached_journal_row(array $user, string $id): void {
    if ($id === '') return;
    $store = journal_store_name($user);
    $rows = data_store_read($store);
    $next = array_values(array_filter($rows, fn($row) => (string)($row['id'] ?? '') !== $id));
    if (count($next) !== count($rows)) data_store_write($store, $next);
}

function merge_subscription_rows(array $a, array $b): array {
    $out = [];
    foreach (array_merge($a, $b) as $row) {
        if (!is_array($row)) continue;
        $email = strtolower(trim((string)($row['email'] ?? '')));
        if ($email === '') continue;
        $existing = $out[$email] ?? [];
        $out[$email] = array_merge($existing, $row, ['email' => $email]);
    }
    return array_values($out);
}

function merge_testimonial_rows(array $a, array $b): array {
    $out = [];
    foreach (array_merge($a, $b) as $row) {
        if (!is_array($row)) continue;
        $id = (string)($row['id'] ?? '');
        if ($id === '') {
            $base = strtolower(trim((string)($row['userId'] ?? ''))) . '|' . strtolower(trim((string)($row['author'] ?? ''))) . '|' . substr(sha1((string)($row['content'] ?? '')), 0, 12);
            $id = 'local_' . substr(sha1($base ?: json_encode($row)), 0, 16);
            $row['id'] = $id;
        }
        $existing = $out[$id] ?? [];
        $out[$id] = array_merge($existing, $row);
    }
    return array_values($out);
}


function merge_announcement_rows(array $a, array $b): array {
    $out = [];
    foreach (array_merge($a, $b) as $row) {
        if (!is_array($row)) continue;
        $id = (string)($row['id'] ?? '');
        if ($id === '') {
            $base = strtolower(trim((string)($row['title'] ?? ''))) . '|' . substr(sha1((string)($row['content'] ?? ($row['description'] ?? ''))), 0, 12) . '|' . (string)($row['createdAt'] ?? '');
            $id = 'local_' . substr(sha1($base ?: json_encode($row)), 0, 16);
            $row['id'] = $id;
        }
        $existing = $out[$id] ?? [];
        $out[$id] = array_merge($existing, $row);
    }
    return array_values($out);
}

function save_local_announcement_row(array $row): array {
    $rows = data_store_read('announcements');
    if (empty($row['id'])) $row['id'] = 'local_' . bin2hex(random_bytes(8));
    $rows = merge_announcement_rows($rows, [$row]);
    data_store_write('announcements', $rows);
    return $row;
}

function delete_local_announcement(string $id): bool {
    $rows = data_store_read('announcements');
    $next = array_values(array_filter($rows, fn($row) => (string)($row['id'] ?? '') !== $id));
    if (count($next) !== count($rows)) {
        data_store_write('announcements', $next);
        return true;
    }
    return false;
}

function announcement_deleted_ids(): array {
    $rows = data_store_read('announcements_deleted');
    $cutoff = time() - (90 * 24 * 60 * 60);
    $ids = [];
    $kept = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        $deletedAt = strtotime((string)($row['deletedAt'] ?? 'now')) ?: time();
        if ($deletedAt < $cutoff) continue;
        $ids[$id] = true;
        $kept[] = ['id' => $id, 'deletedAt' => gmdate('c', $deletedAt)];
    }
    if (count($kept) !== count($rows)) data_store_write('announcements_deleted', $kept);
    return array_keys($ids);
}

function remember_deleted_announcement(string $id): void {
    $id = trim($id);
    if ($id === '') return;
    $rows = data_store_read('announcements_deleted');
    $rows = array_values(array_filter($rows, fn($row) => is_array($row) && (string)($row['id'] ?? '') !== $id));
    array_unshift($rows, ['id' => $id, 'deletedAt' => gmdate('c')]);
    data_store_write('announcements_deleted', array_slice($rows, 0, 500));
}

function filter_deleted_announcements(array $rows): array {
    $deleted = array_flip(announcement_deleted_ids());
    if (!$deleted) return array_values($rows);
    return array_values(array_filter($rows, fn($row) => !isset($deleted[(string)($row['id'] ?? '')])));
}


function testimonial_identity_matches(array $row, string $uid, string $email, string $displayName = ''): bool {
    $uid = trim($uid);
    $email = strtolower(trim($email));
    $displayName = strtolower(trim($displayName));
    $emailName = $email !== '' ? strtolower(trim(strtok($email, '@') ?: '')) : '';

    $rowUid = trim((string)($row['userId'] ?? $row['uid'] ?? $row['ownerId'] ?? $row['firebaseUid'] ?? $row['createdBy'] ?? $row['submittedBy'] ?? $row['memberUid'] ?? ''));
    $rowEmail = strtolower(trim((string)($row['email'] ?? $row['userEmail'] ?? $row['user_email'] ?? $row['memberEmail'] ?? $row['submittedByEmail'] ?? '')));
    $rowAuthor = strtolower(trim((string)($row['author'] ?? $row['name'] ?? $row['displayName'] ?? $row['memberName'] ?? '')));
    $rowId = strtolower(trim((string)($row['id'] ?? $row['_id'] ?? '')));

    if ($uid !== '' && ($rowUid === $uid || $rowId === strtolower('testimonial_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $uid)) || $rowId === strtolower($uid))) return true;
    if ($email !== '' && $rowEmail === $email) return true;

    // Legacy testimonial records in this project were sometimes saved with only
    // author/content. Use a conservative name fallback so existing Firebase
    // submissions still close the member tab after redeploy.
    if ($rowAuthor !== '') {
        if ($displayName !== '' && $rowAuthor === $displayName) return true;
        if ($emailName !== '' && $rowAuthor === $emailName) return true;
    }
    return false;
}

function testimonial_document_id_for_uid(string $uid): string {
    return $uid !== '' ? ('testimonial_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $uid)) : '';
}

function testimonial_marker_collection_paths(): array {
    $paths = public_collection_paths_with_legacy_root('testimonial_markers');
    $paths[] = 'testimonial_submissions';
    $paths[] = 'public/data/testimonial_submissions';
    return unique_firestore_paths($paths);
}

function firestore_write_testimonial_marker(string $uid, string $email, string $displayName, string $testimonialId, ?string $idToken = null): void {
    if ($uid === '') return;
    $markerId = testimonial_document_id_for_uid($uid);
    $row = [
        'id' => $markerId,
        'testimonialId' => $testimonialId,
        'userId' => $uid,
        'uid' => $uid,
        'email' => $email,
        'userEmail' => $email,
        'displayName' => $displayName,
        'submitted' => true,
        'createdAt' => gmdate('c'),
        'updatedAt' => gmdate('c'),
    ];
    $r = firestore_write_all_set(document_paths_from_collections(testimonial_marker_collection_paths(), $markerId), $row, $idToken);
    if (!$r['ok']) api_log('Non-blocking testimonial marker write failed: ' . json_encode($r['data'] ?? $r));
}

function firestore_find_testimonials_for_user(string $uid, string $email, ?string $idToken = null, string $displayName = ''): array {
    $tokens = admin_firestore_tokens($idToken);
    if (empty($tokens) && $idToken) $tokens = [$idToken];
    $tokens = array_values(array_unique(array_filter($tokens, fn($token) => trim((string)$token) !== '')));
    $rows = [];
    $seen = [];

    // New builds store each user's testimonial/marker under a deterministic document id.
    // This is the fastest and most reliable one-submission check.
    $testimonialId = testimonial_document_id_for_uid($uid);
    if ($testimonialId !== '' && !empty($tokens)) {
        $docPaths = array_merge(
            document_paths_from_collections(public_collection_paths_with_legacy_root('testimonials'), $testimonialId),
            document_paths_from_collections(testimonial_marker_collection_paths(), $testimonialId),
            document_paths_from_collections(public_collection_paths_with_legacy_root('testimonials'), $uid)
        );
        foreach ($tokens as $token) {
            $batch = firestore_batch_get_documents($docPaths, $token);
            if (!($batch['ok'] ?? false)) continue;
            foreach (($batch['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $id = (string)($row['id'] ?? ($row['_documentName'] ?? md5(json_encode($row))));
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                $rows[] = $row;
            }
            if (!empty($rows)) return $rows;
        }
    }

    // Legacy builds may have used random document ids, so search by saved identity fields.
    $filters = [];
    $emailName = $email !== '' ? strtolower(trim(strtok($email, '@') ?: '')) : '';
    if ($uid !== '') {
        foreach (['userId', 'uid', 'ownerId', 'firebaseUid', 'createdBy', 'submittedBy', 'memberUid'] as $field) $filters[] = [$field, $uid];
    }
    if ($email !== '') {
        foreach (['email', 'userEmail', 'user_email', 'memberEmail', 'submittedByEmail'] as $field) $filters[] = [$field, $email];
    }
    // Legacy migration fallback: records may only contain author/content.
    if ($displayName !== '') {
        foreach (['author', 'name', 'displayName', 'memberName'] as $field) $filters[] = [$field, $displayName];
    }
    if ($emailName !== '' && $emailName !== $displayName) {
        foreach (['author', 'name', 'displayName', 'memberName'] as $field) $filters[] = [$field, $emailName];
    }
    foreach ($tokens as $token) {
        foreach ($filters as [$field, $value]) {
            $query = [
                'from' => [[ 'collectionId' => 'testimonials', 'allDescendants' => true ]],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $field],
                        'op' => 'EQUAL',
                        'value' => ['stringValue' => $value],
                    ]
                ],
                'limit' => 20,
            ];
            $r = firestore_run_structured_query($query, $token);
            if (!($r['ok'] ?? false)) continue;
            foreach (($r['rows'] ?? []) as $row) {
                if (!is_array($row) || !testimonial_identity_matches($row, $uid, $email, $displayName)) continue;
                $id = (string)($row['id'] ?? ($row['_documentName'] ?? md5(json_encode($row))));
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                $rows[] = $row;
            }
            if (!empty($rows)) return $rows;
        }
    }
    return $rows;
}

function save_local_testimonial_row(array $row): array {
    $rows = data_store_read('testimonials');
    if (empty($row['id'])) $row['id'] = 'local_' . bin2hex(random_bytes(8));
    $rows = merge_testimonial_rows($rows, [$row]);
    data_store_write('testimonials', $rows);
    return $row;
}

function update_local_testimonial(string $id, array $fields): bool {
    $rows = data_store_read('testimonials');
    $changed = false;
    foreach ($rows as &$row) {
        if ((string)($row['id'] ?? '') === $id) {
            $row = array_merge($row, $fields);
            $changed = true;
            break;
        }
    }
    if ($changed) data_store_write('testimonials', $rows);
    return $changed;
}

function delete_local_testimonial(string $id): bool {
    $rows = data_store_read('testimonials');
    $next = array_values(array_filter($rows, fn($row) => (string)($row['id'] ?? '') !== $id));
    if (count($next) !== count($rows)) {
        data_store_write('testimonials', $next);
        return true;
    }
    return false;
}

try {
    switch ($action) {
        case 'public_testimonials_fresh':
        case 'public_testimonials': {
            $localRows = data_store_read('testimonials');
            $approvedLocalRows = array_values(array_filter($localRows, fn($x) => !empty($x['approved'])));
            $wantsFresh = ($action === 'public_testimonials_fresh') || !empty($_GET['fresh']) || !empty($_GET['sync']);

            // Fast dashboard/landing reads should only return local cache when it already has data.
            // If the VPS was just redeployed and api/_data is empty, hydrate once from all Firestore
            // paths, otherwise testimonials look permanently blank after a clean upload.
            if ((ts_env_bool('TS_FAST_PUBLIC_READS', true) || !empty($_GET['fast'])) && !$wantsFresh && !empty($approvedLocalRows)) {
                json_response(['ok' => true, 'items' => sorted_by_date_desc($approvedLocalRows), 'source' => 'local_fast_cache']);
            }

            $token = $idToken ?: null;
            $r = firestore_list_public_quick(public_collection_paths_with_legacy_root('testimonials'), $token);
            $remoteRows = $r['ok'] ? ($r['rows'] ?? []) : [];
            if (!$r['ok']) api_log('Non-blocking public testimonials Firestore list failed: ' . json_encode($r['data'] ?? $r));
            $rows = merge_testimonial_rows($localRows, $remoteRows);
            if (!empty($remoteRows)) data_store_write('testimonials', $rows);
            $rows = array_values(array_filter($rows, fn($x) => !empty($x['approved'])));
            json_response(['ok' => true, 'items' => sorted_by_date_desc($rows), 'source' => !empty($remoteRows) ? 'firebase_plus_cache' : (!empty($approvedLocalRows) ? 'local_cache' : 'empty_cache')]);
        }
        case 'subscribe': {
            $email = strtolower(trim($payload['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 400);
            $row = ['email' => $email, 'subscribedAt' => gmdate('c')];

            // Save locally first so newsletter subscription/admin tabs do not break if Firestore rules deny public writes.
            $local = data_store_read('subscriptions');
            $local = merge_subscription_rows($local, [$row]);
            data_store_write('subscriptions', $local);

            // Firestore is now best-effort only for this public mailing-list collection.
            $r = firestore_write_all_create(artifact_public_collection_paths('subscriptions'), $row, $idToken);
            if (!$r['ok']) api_log('Non-blocking subscription Firestore save failed for ' . $email . ': ' . json_encode($r['data'] ?? $r));
            json_response(['ok' => true]);
        }
        case 'list_announcements': {
            $localRows = data_store_read('announcements');
            $localVisibleRows = filter_deleted_announcements($localRows);
            $wantsFresh = !empty($_GET['fresh']) || !empty($_GET['sync']) || !empty($_GET['admin']);

            // Same rule as testimonials: fast reads may use cache, but never let an empty
            // api/_data cache hide existing Firestore announcements after a fresh deployment.
            if ((ts_env_bool('TS_FAST_PUBLIC_READS', true) || !empty($_GET['fast'])) && !$wantsFresh && !empty($localVisibleRows)) {
                json_response(['ok' => true, 'items' => sorted_by_date_desc($localVisibleRows), 'source' => 'local_fast_cache', 'firebaseConnected' => false]);
            }
            $r = firestore_list_public_quick(public_collection_paths_with_legacy_root('announcements'), $idToken);
            $remoteRows = $r['ok'] ? ($r['rows'] ?? []) : [];
            if (!$r['ok']) api_log('Non-blocking announcements Firestore list failed after token candidates: ' . json_encode($r['data'] ?? $r));
            $rows = filter_deleted_announcements(merge_announcement_rows($localRows, $remoteRows));
            if (!empty($remoteRows)) data_store_write('announcements', $rows);
            json_response(['ok' => true, 'items' => sorted_by_date_desc($rows), 'source' => !empty($remoteRows) ? 'firebase_plus_cache' : (!empty($localVisibleRows) ? 'local_cache' : 'empty_cache'), 'firebaseConnected' => !empty($remoteRows)]);
        }
        case 'list_journal': {
            $u = require_current_user();
            $cachedRows = filter_journal_rows_for_user(stamp_safe_journal_rows_for_user(data_store_read(journal_store_name($u)), $u, true), $u);
            // IMPORTANT: Never return an empty local cache as the final answer for PTJ.
            // After a redeploy /var/www/html/api/_data can be empty while the real trades
            // still exist in Firebase. That made the PTJ stats render all zeros.
            if (ts_env_bool('TS_FAST_USER_READS', true) && empty($_GET['fresh']) && !empty($cachedRows)) {
                json_response(['ok' => true, 'items' => sorted_by_date_desc($cachedRows), 'source' => 'local_fast_cache']);
            }
            $tokens = firebase_server_token_candidates($idToken);
            $r = firestore_list_user_collection_resilient($u['uid'], 'premium_journal', $tokens);
            if ($r['ok']) {
                $remoteRows = filter_journal_rows_for_user(stamp_safe_journal_rows_for_user($r['rows'] ?? [], $u, false), $u);
                $rows = filter_journal_rows_for_user(merge_journal_rows($cachedRows, $remoteRows), $u);
                if (!empty($rows)) cache_journal_rows($u, $rows);
                json_response([
                    'ok' => true,
                    'items' => sorted_by_date_desc($rows),
                    'source' => !empty($remoteRows) ? 'firebase_plus_cache' : (!empty($cachedRows) ? 'local_cache' : 'empty'),
                    'firebaseConnected' => !empty($remoteRows)
                ]);
            }
            api_log('Non-blocking list_journal Firestore read failed after token candidates for ' . ($u['email'] ?? $u['uid'] ?? 'unknown') . ': ' . json_encode($r['data'] ?? $r));
            json_response([
                'ok' => true,
                'items' => sorted_by_date_desc($cachedRows),
                'source' => !empty($cachedRows) ? 'local_cache' : 'empty_after_firebase_attempt',
                'firebaseConnected' => false,
                'warning' => 'Firebase journal read was unavailable, so cached journal rows were returned.'
            ]);
        }
        case 'save_journal': {
            $u = require_current_user();
            if (($u['role'] ?? '') !== 'premium' && ($u['role'] ?? '') !== 'admin') json_response(['ok' => false, 'message' => 'Premium access required.'], 403);
            $entry = $payload['entry'] ?? [];
            if (!is_array($entry)) json_response(['ok' => false, 'message' => 'Invalid journal entry.'], 400);
            $id = $entry['id'] ?? '';
            unset($entry['id']);
            // Never trust client/session fallback ownership for PTJ. Stamp every row
            // with the authenticated member so reads can strictly isolate dashboards.
            $entry['userId'] = (string)($u['uid'] ?? '');
            $entry['uid'] = (string)($u['uid'] ?? '');
            $entry['userEmail'] = strtolower(trim((string)($u['email'] ?? '')));
            $entry['updatedAt'] = gmdate('c');
            $cacheId = $id ?: ('local_' . bin2hex(random_bytes(8)));
            upsert_cached_journal_row($u, array_merge($entry, ['id' => $cacheId]));
            if ($id) {
                $r = firestore_write_all_patch(document_paths_from_collections(expanded_user_collection_paths($u['uid'], 'premium_journal'), $id), $entry, $idToken);
            } else {
                $entry['createdAt'] = gmdate('c');
                $r = firestore_write_all_create(expanded_user_collection_paths($u['uid'], 'premium_journal'), $entry, $idToken);
                if ($r['ok'] && !empty($r['data']['name'])) {
                    $remoteId = firestore_document_id_from_name($r['data']['name']);
                    if ($remoteId !== '') {
                        delete_cached_journal_row($u, $cacheId);
                        upsert_cached_journal_row($u, array_merge($entry, ['id' => $remoteId]));
                    }
                }
            }
            if (!$r['ok']) {
                api_log('Non-blocking save_journal Firestore write failed for ' . ($u['email'] ?? $u['uid'] ?? 'unknown') . ': ' . json_encode($r['data'] ?? $r));
                json_response(['ok' => true, 'cached' => true, 'message' => 'Saved locally. Firebase sync will resume when Firestore access is available.']);
            }
            json_response(['ok' => true]);
        }
        case 'delete_journal': {
            $u = require_current_user();
            $id = $payload['id'] ?? '';
            delete_cached_journal_row($u, (string)$id);
            $r = firestore_write_all_delete(document_paths_from_collections(expanded_user_collection_paths($u['uid'], 'premium_journal'), $id), $idToken);
            if (!$r['ok']) {
                api_log('Non-blocking delete_journal Firestore delete failed for ' . ($u['email'] ?? $u['uid'] ?? 'unknown') . ': ' . json_encode($r['data'] ?? $r));
            }
            json_response(['ok' => true]);
        }
        case 'has_testimonial_fresh':
        case 'has_testimonial': {
            $u = require_current_user();
            $localRows = data_store_read('testimonials');
            $uid = (string)($u['uid'] ?? '');
            $email = strtolower(trim((string)($u['email'] ?? '')));
            $displayName = trim((string)($u['displayName'] ?? $u['name'] ?? ''));
            $hasLocal = count(array_filter($localRows, fn($x) => is_array($x) && testimonial_identity_matches($x, $uid, $email, $displayName))) > 0;
            if ($hasLocal) {
                json_response(['ok' => true, 'hasSubmitted' => true, 'source' => 'local_cache']);
            }

            // Never return false from an empty local cache. Testimonials live in Firebase,
            // and after a fresh deploy api/_data may be empty while the user already has
            // a Firebase testimonial. Check deterministic docs + legacy identity fields.
            $remoteRows = firestore_find_testimonials_for_user($uid, $email, $idToken, $displayName);
            if (!empty($remoteRows)) {
                data_store_write('testimonials', merge_testimonial_rows($localRows, $remoteRows));
                json_response(['ok' => true, 'hasSubmitted' => true, 'source' => 'firebase_identity_check']);
            }
            json_response(['ok' => true, 'hasSubmitted' => false, 'source' => 'firebase_identity_check_empty']);
        }
        case 'submit_testimonial': {
            $u = require_current_user();
            $role = strtolower(trim((string)($u['role'] ?? 'member')));
            $premiumExpiry = !empty($u['premiumExpiresAt']) ? strtotime((string)$u['premiumExpiresAt']) : false;
            $hasActivePremium = ($role === 'admin') || ($role === 'premium' && ($premiumExpiry === false || $premiumExpiry >= time()));
            if (!$hasActivePremium) {
                json_response(['ok' => false, 'message' => 'Only premium members can submit testimonials after trying the product. Upgrade to Premium first, then share your experience.'], 403);
            }
            $author = trim($payload['author'] ?? '');
            $content = trim($payload['content'] ?? '');
            if ($author === '' || $content === '') json_response(['ok' => false, 'message' => 'Please fill in all fields.'], 400);
            $uid = (string)($u['uid'] ?? '');
            $email = strtolower(trim((string)($u['email'] ?? '')));
            $displayName = trim((string)($u['displayName'] ?? $u['name'] ?? ''));
            $localRows = data_store_read('testimonials');
            $remoteRows = firestore_find_testimonials_for_user($uid, $email, $idToken, $displayName);
            $allRows = merge_testimonial_rows($localRows, $remoteRows);
            if (count(array_filter($allRows, fn($x) => is_array($x) && testimonial_identity_matches($x, $uid, $email, $displayName))) > 0) {
                if (!empty($remoteRows)) data_store_write('testimonials', $allRows);
                json_response(['ok' => false, 'message' => 'You have already submitted a testimonial.'], 409);
            }
            $testimonialId = testimonial_document_id_for_uid($uid);
            if ($testimonialId === '') $testimonialId = 'local_' . bin2hex(random_bytes(8));
            $row = [
                'id' => $testimonialId,
                'author' => $author,
                'content' => $content,
                'createdAt' => gmdate('c'),
                'updatedAt' => gmdate('c'),
                'userId' => $uid,
                'email' => $email,
                'userEmail' => $email,
                'uid' => $uid,
                'displayName' => $displayName,
                'submittedBy' => $uid,
                'submittedByEmail' => $email,
                'approved' => false
            ];
            save_local_testimonial_row($row);
            $r = firestore_write_all_set(document_paths_from_collections(public_collection_paths_with_legacy_root('testimonials'), $testimonialId), $row, $idToken);
            if (!$r['ok']) api_log('Non-blocking testimonial Firestore submit failed: ' . json_encode($r['data'] ?? $r));
            firestore_write_testimonial_marker($uid, $email, $displayName, $testimonialId, $idToken);
            json_response(['ok' => true, 'item' => $row, 'cached' => !$r['ok']]);
        }

        case 'list_economic_calendar': {
            try {
                $impact = strtolower(trim((string)($_GET['impact'] ?? 'high')));
                $limit = max(1, min(24, intval($_GET['limit'] ?? 8)));
                $rows = data_store_read('economic_calendar');
                if (!is_array($rows)) $rows = [];
                $rows = array_values(array_filter($rows, function($row) use ($impact) {
                    if (!is_array($row)) return false;
                    $rowImpact = strtolower((string)($row['impact'] ?? ''));
                    if ($impact === 'all') return true;
                    return $rowImpact === $impact || ($impact === 'high' && in_array($rowImpact, ['high', 'important', 'red'], true));
                }));
                usort($rows, function($a, $b) {
                    $at = strtotime((string)($a['date'] ?? $a['time'] ?? 'now'));
                    $bt = strtotime((string)($b['date'] ?? $b['time'] ?? 'now'));
                    if ($at === false) $at = PHP_INT_MAX;
                    if ($bt === false) $bt = PHP_INT_MAX;
                    return $at <=> $bt;
                });
                json_response(['ok' => true, 'events' => array_slice($rows, 0, $limit), 'source' => 'Local economic calendar cache', 'impact' => $impact]);
            } catch (Throwable $e) {
                api_log('Economic calendar soft fallback: ' . $e->getMessage());
                json_response(['ok' => true, 'events' => [], 'source' => 'calendar_unavailable', 'impact' => $_GET['impact'] ?? 'high']);
            }
        }

        case 'list_trading_notes': {
            $u = require_current_user();
            $r = firestore_list_first_available(artifact_user_collection_paths($u['uid'], 'trading_notes'), $idToken, 3);
            if (!$r['ok']) json_response(['ok' => true, 'items' => []]);
            json_response(['ok' => true, 'items' => sorted_by_date_desc($r['rows'] ?? [], 'updatedAt')]);
        }
        case 'save_trading_note': {
            $u = require_current_user();
            $note = $payload['note'] ?? [];
            $id = trim((string)($note['id'] ?? ''));
            unset($note['id']);
            $note['userId'] = $u['uid'];
            if ($id) {
                $note['updatedAt'] = gmdate('c');
                $r = firestore_write_all_patch(document_paths_from_collections(artifact_user_collection_paths($u['uid'], 'trading_notes'), $id), $note, $idToken);
                $savedId = $id;
            } else {
                $savedId = 'note_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
                $note['createdAt'] = gmdate('c');
                $note['updatedAt'] = $note['createdAt'];
                $r = firestore_write_all_set(document_paths_from_collections(artifact_user_collection_paths($u['uid'], 'trading_notes'), $savedId), $note, $idToken);
            }
            if (!$r['ok']) json_response(['ok' => false, 'message' => 'Could not save note.'], 500);
            $item = $note;
            $item['id'] = $savedId;
            json_response(['ok' => true, 'item' => $item]);
        }
        case 'delete_trading_note': {
            $u = require_current_user();
            $id = $payload['id'] ?? '';
            $r = firestore_write_all_delete(document_paths_from_collections(artifact_user_collection_paths($u['uid'], 'trading_notes'), $id), $idToken);
            if (!$r['ok']) json_response(['ok' => false, 'message' => 'Could not delete note.'], 500);
            json_response(['ok' => true]);
        }
        case 'list_trading_accounts': {
            $u = require_current_user();
            $localProfile = load_local_user_profile($u['uid'] ?? '', $u['email'] ?? '');
            $localAccounts = normalize_trading_accounts_list($localProfile['tradingAccounts'] ?? ($u['tradingAccounts'] ?? []));
            // Same PTJ rule: only use local-fast when it actually has data.
            // Empty local profile after deployment must not hide Firebase accounts.
            if (ts_env_bool('TS_FAST_USER_READS', true) && empty($_GET['fresh']) && !empty($localAccounts)) {
                json_response(['ok' => true, 'accounts' => $localAccounts, 'user' => current_user_or_null(), 'count' => count($localAccounts), 'source' => 'local_fast_cache']);
            }
            $accounts = load_user_trading_accounts($u['uid'] ?? '', $u['email'] ?? '', $idToken);
            if (!empty($accounts)) {
                firestore_patch_session_profile(['tradingAccounts' => $accounts, 'tradingAccountsUpdatedAt' => gmdate('c')]);
            }
            $finalAccounts = !empty($accounts) ? $accounts : $localAccounts;
            json_response([
                'ok' => true,
                'accounts' => $finalAccounts,
                'user' => current_user_or_null(),
                'count' => count($finalAccounts),
                'source' => !empty($accounts) ? 'firebase_plus_profile_cache' : (!empty($localAccounts) ? 'local_cache' : 'empty_after_firebase_attempt')
            ]);
        }
        case 'firebase_data_diagnostics': {
            $u = require_current_user();
            $tokens = firebase_server_token_candidates($idToken);
            $ann = firestore_list_with_token_candidates(public_collection_paths_with_legacy_root('announcements'), $tokens);
            $journal = firestore_list_user_collection_resilient($u['uid'], 'premium_journal', $tokens);
            $accounts = load_user_trading_accounts($u['uid'] ?? '', $u['email'] ?? '', $idToken);
            json_response([
                'ok' => true,
                'uid' => $u['uid'] ?? '',
                'email' => $u['email'] ?? '',
                'tokenCandidates' => count($tokens),
                'announcementPaths' => public_collection_paths_with_legacy_root('announcements'),
                'announcementsOk' => (bool)($ann['ok'] ?? false),
                'announcementsCount' => count($ann['rows'] ?? []),
                'journalPaths' => expanded_user_collection_paths($u['uid'], 'premium_journal'),
                'journalOk' => (bool)($journal['ok'] ?? false),
                'journalCount' => count($journal['rows'] ?? []),
                'accountsCount' => count($accounts),
                'lastError' => $journal['ok'] ?? false ? null : ($journal['data']['error']['message'] ?? ($ann['data']['error']['message'] ?? 'Unknown')),
            ]);
        }
        case 'save_trading_accounts': {
            $u = require_current_user();
            $accounts = normalize_trading_accounts_list($payload['accounts'] ?? []);
            save_user_trading_accounts($u['uid'] ?? '', $u['email'] ?? '', $accounts, $idToken);
            $localProfileAfterAccountSave = load_local_user_profile($u['uid'] ?? '', $u['email'] ?? '');
            $incomingDeletedIds = is_array($payload['deletedAccountIds'] ?? null) ? $payload['deletedAccountIds'] : [];
            if (!empty($incomingDeletedIds)) {
                $deletedMap = [];
                foreach (($localProfileAfterAccountSave['deletedTradingAccountIds'] ?? []) as $deletedId) {
                    $cleanDeletedId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$deletedId);
                    if ($cleanDeletedId !== '') $deletedMap[$cleanDeletedId] = true;
                }
                foreach ($incomingDeletedIds as $deletedId) {
                    $cleanDeletedId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$deletedId);
                    if ($cleanDeletedId !== '') $deletedMap[$cleanDeletedId] = true;
                }
                $localProfileAfterAccountSave['deletedTradingAccountIds'] = array_values(array_keys($deletedMap));
                save_local_user_profile($u['uid'] ?? '', $u['email'] ?? '', $localProfileAfterAccountSave);
            }
            firestore_patch_session_profile([
                'tradingAccounts' => $accounts,
                'deletedTradingAccountIds' => $localProfileAfterAccountSave['deletedTradingAccountIds'] ?? [],
                'tradingAccountsUpdatedAt' => gmdate('c'),
                'tradingAccountsSetupAt' => gmdate('c'),
                'tradingAccountsSetupPromptedAt' => gmdate('c'),
            ]);
            json_response(['ok' => true, 'accounts' => $accounts, 'user' => current_user_or_null()]);
        }
        case 'save_terms_agreement': {
            $u = require_current_user();
            $now = gmdate('c');
            $fileName = trim((string)($payload['agreementFile'] ?? ('Traders_Sanctuary_Agreement_' . ($u['uid'] ?? 'user') . '.txt')));
            $content = (string)($payload['agreementContent'] ?? '');
            if ($content === '') {
                $content = 'Agreement accepted by ' . ($u['email'] ?? $u['uid'] ?? 'user') . ' on ' . $now;
            }
            $fields = [
                'termsAgreed' => true,
                'termsAgreedAt' => $payload['termsAgreedAt'] ?? $now,
                'agreementFile' => $fileName,
                'agreementContent' => $content,
                'agreementVersion' => 'risk_terms_v1',
                'agreementStoredAt' => $now,
            ];
            firestore_patch_session_profile($fields);
            save_local_user_profile($u['uid'] ?? '', $u['email'] ?? '', array_merge($u, $fields));
            if ($idToken && !empty($u['uid'])) {
                $r = firestore_patch_document(firestore_profile_path($u['uid']), $fields, $idToken);
                if (!$r['ok']) api_log('Non-blocking terms agreement Firestore profile save failed for ' . ($u['email'] ?? $u['uid']) . ': ' . json_encode($r['data'] ?? $r));

                // Store a retrievable agreement archive document as well as the profile flags.
                // The admin dashboard still lists from profiles for speed, while this keeps
                // the full agreement payload available under the user's Firebase namespace.
                $agreementArchive = array_merge($fields, [
                    'uid' => $u['uid'] ?? '',
                    'email' => $u['email'] ?? '',
                    'acceptedAt' => $fields['termsAgreedAt'],
                    'source' => 'signup_agreement_screen',
                ]);
                $archivePaths = document_paths_from_collections(artifact_user_collection_paths($u['uid'], 'agreements'), 'latest');
                $ar = firestore_write_all_patch($archivePaths, $agreementArchive, $idToken);
                if (!$ar['ok']) api_log('Non-blocking terms agreement archive save failed for ' . ($u['email'] ?? $u['uid']) . ': ' . json_encode($ar['data'] ?? $ar));
            }
            json_response(['ok' => true, 'user' => current_user_or_null()]);
        }
        case 'mark_trading_accounts_prompted': {
            $u = require_current_user();
            $fields = ['tradingAccountsSetupPromptedAt' => gmdate('c')];
            firestore_patch_session_profile($fields);
            save_local_user_profile($u['uid'] ?? '', $u['email'] ?? '', array_merge($u, $fields));
            if ($idToken && !empty($u['uid'])) {
                $r = firestore_patch_document(firestore_profile_path($u['uid']), $fields, $idToken);
                if (!$r['ok']) api_log('Non-blocking account prompt marker Firestore save failed for ' . ($u['email'] ?? $u['uid']) . ': ' . json_encode($r['data'] ?? $r));
            }
            json_response(['ok' => true, 'user' => current_user_or_null()]);
        }
        case 'update_profile': {
            $u = require_current_user();
            $fields = $payload['fields'] ?? [];
            if (!is_array($fields)) json_response(['ok' => false, 'message' => 'Invalid profile update.'], 400);
            // Never let a slow/denied Firestore profile write trap the user on the terms screen.
            // Trading accounts are also mirrored into a dedicated user collection so they survive
            // login from a different browser or device even if the profile document is restricted.
            if (array_key_exists('tradingAccounts', $fields)) {
                $fields['tradingAccounts'] = normalize_trading_accounts_list($fields['tradingAccounts']);
                save_user_trading_accounts($u['uid'] ?? '', $u['email'] ?? '', $fields['tradingAccounts'], $idToken);
            }
            firestore_patch_session_profile($fields);
            if ($idToken) {
                $r = firestore_patch_document(firestore_profile_path($u['uid']), $fields, $idToken);
                if (!$r['ok']) api_log('Non-blocking profile Firestore update failed for ' . ($u['email'] ?? $u['uid']) . ': ' . json_encode($r['data'] ?? $r));
            }
            json_response(['ok' => true, 'user' => current_user_or_null()]);
        }
        case 'admin_list_subscriptions': {
            require_admin_user();
            $localRows = data_store_read('subscriptions');
            $r = firestore_list_admin_public_resilient('subscriptions', $idToken);
            $remoteRows = $r['ok'] ? ($r['rows'] ?? []) : [];
            $rows = merge_subscription_rows($localRows, $remoteRows);
            json_response(['ok' => true, 'items' => sorted_by_date_desc($rows, 'subscribedAt'), 'firebaseConnected' => (bool)($r['ok'] ?? false), 'source' => $r['source'] ?? 'local']);
        }
        case 'admin_list_testimonials': {
            require_admin_user();
            $localRows = data_store_read('testimonials');
            $r = firestore_list_admin_public_resilient('testimonials', $idToken);
            $remoteRows = $r['ok'] ? ($r['rows'] ?? []) : [];
            $rows = merge_testimonial_rows($localRows, $remoteRows);
            json_response(['ok' => true, 'items' => sorted_by_date_desc($rows), 'firebaseConnected' => (bool)($r['ok'] ?? false), 'source' => $r['source'] ?? 'local']);
        }
        case 'admin_approve_testimonial': {
            require_admin_user();
            $id = $payload['id'] ?? '';
            $fields = ['approved' => true, 'approvedAt' => gmdate('c')];
            update_local_testimonial($id, $fields);
            $r = firestore_write_all_patch(document_paths_from_collections(public_collection_paths_with_legacy_root('testimonials'), $id), $fields, admin_write_token($idToken));
            if (!$r['ok']) api_log('Non-blocking testimonial Firestore approval failed: ' . json_encode($r['data'] ?? $r));
            json_response(['ok' => true]);
        }
        case 'admin_delete_testimonial': {
            require_admin_user();
            $id = $payload['id'] ?? '';
            delete_local_testimonial($id);
            $r = firestore_write_all_delete(document_paths_from_collections(public_collection_paths_with_legacy_root('testimonials'), $id), admin_write_token($idToken));
            if (!$r['ok']) api_log('Non-blocking testimonial Firestore delete failed: ' . json_encode($r['data'] ?? $r));
            json_response(['ok' => true]);
        }
        case 'admin_save_announcement': {
            $u = require_admin_user();
            $ann = $payload['announcement'] ?? [];
            if (!is_array($ann)) json_response(['ok' => false, 'message' => 'Invalid announcement.'], 400);
            $id = (string)($ann['id'] ?? '');
            unset($ann['id']);
            $ann['updatedAt'] = gmdate('c');
            if ($id) {
                if (empty($ann['createdAt'])) $ann['createdAt'] = $payload['announcement']['createdAt'] ?? gmdate('c');
                save_local_announcement_row(array_merge($ann, ['id' => $id]));
                $firestoreToken = admin_write_token($idToken);
                $r = firestore_write_all_set(document_paths_from_collections(public_collection_paths_with_legacy_root('announcements'), $id), $ann, $firestoreToken);
            } else {
                $localId = 'ann_' . bin2hex(random_bytes(8));
                $ann['authorId'] = $u['uid'];
                $ann['createdAt'] = gmdate('c');
                save_local_announcement_row(array_merge($ann, ['id' => $localId]));
                // Use a deterministic document id so local fallback, edit and delete all target the same announcement.
                $firestoreToken = admin_write_token($idToken);
                $r = firestore_write_all_set(document_paths_from_collections(public_collection_paths_with_legacy_root('announcements'), $localId), $ann, $firestoreToken);
                $id = $localId;
            }
            $item = array_merge($ann, ['id' => $id]);
            if (!$r['ok']) api_log('Non-blocking announcement Firestore save failed: ' . json_encode($r['data'] ?? $r));
            json_response(['ok' => true, 'id' => $id, 'item' => $item, 'cached' => !$r['ok']]);
        }
        case 'admin_delete_announcement': {
            require_admin_user();
            $id = (string)($payload['id'] ?? '');
            remember_deleted_announcement($id);
            delete_local_announcement($id);
            $firestoreToken = admin_write_token($idToken);
            $r = firestore_write_all_delete(document_paths_from_collections(public_collection_paths_with_legacy_root('announcements'), $id), $firestoreToken);
            if (!$r['ok']) api_log('Non-blocking announcement Firestore delete failed: ' . json_encode($r['data'] ?? $r));
            json_response(['ok' => true]);
        }


        case 'admin_payment_storage_status': {
            require_admin_user();
            $premiumFile = premium_override_store_path();
            $profileFile = local_profile_store_path();
            json_response(['ok' => true, 'storage' => data_store_status('manual_payments'), 'profiles' => [
                'file' => $profileFile,
                'file_exists' => is_file($profileFile),
                'file_writable' => is_file($profileFile) ? is_writable($profileFile) : null,
                'item_count' => count(local_profile_store_read()),
            ], 'premium_overrides' => [
                'file' => $premiumFile,
                'file_exists' => is_file($premiumFile),
                'file_writable' => is_file($premiumFile) ? is_writable($premiumFile) : null,
                'item_count' => count(premium_override_store_read()),
            ]]);
        }

        case 'admin_email_status': {
            require_admin_user();
            json_response(['ok' => true, 'email' => [
                'smtp_enabled' => SMTP_ENABLED,
                'smtp_host' => SMTP_HOST,
                'smtp_port' => SMTP_PORT,
                'smtp_username' => SMTP_USERNAME,
                'mail_from' => MAIL_FROM_EMAIL,
                'mail_admin' => MAIL_ADMIN_EMAIL,
                'configured' => mail_config_is_ready(),
            ]]);
        }

        case 'admin_send_test_email': {
            $admin = require_admin_user();
            $to = trim((string)($payload['to'] ?? ($admin['email'] ?? MAIL_ADMIN_EMAIL)));
            $result = app_send_email($to, 'Traders Sanctuary test email', "This is a test email from Traders Sanctuary. If you received this, SMTP is working.");
            json_response(['ok' => (bool)($result['ok'] ?? false), 'result' => $result]);
        }

        case 'manual_payment_config': {
            require_current_user();
            json_response(['ok' => true, 'config' => [
                'method' => MPESA_PAYMENT_METHOD,
                'businessName' => MPESA_BUSINESS_NAME,
                'amount' => PREMIUM_PRICE_KES,
                'currency' => 'KES',
                'premiumDays' => PREMIUM_DAYS,
                'paybill' => MPESA_PAYBILL,
                'accountNumber' => MPESA_ACCOUNT_NUMBER,
                'tillNumber' => MPESA_TILL_NUMBER,
                'phoneNumber' => '',
                'supportContact' => defined('SUPPORT_EMERGENCY_CONTACT') ? SUPPORT_EMERGENCY_CONTACT : '0797671000',
                'note' => MPESA_ADMIN_NOTE,
            ]]);
        }
        case 'submit_manual_payment': {
            $u = require_current_user();
            $mpesaCode = strtoupper(trim((string)($payload['mpesaCode'] ?? '')));
            $phone = trim((string)($payload['phone'] ?? ''));
            $amount = trim((string)($payload['amount'] ?? PREMIUM_PRICE_KES));
            $note = trim((string)($payload['note'] ?? ''));
            if ($mpesaCode === '' || strlen($mpesaCode) < 6) {
                json_response(['ok' => false, 'message' => 'Please enter a valid M-PESA confirmation code.'], 400);
            }
            if ($phone === '') {
                json_response(['ok' => false, 'message' => 'Please enter the M-PESA phone number used for payment.'], 400);
            }
            $payments = data_store_read('manual_payments');
            foreach ($payments as $existing) {
                if (strtoupper((string)($existing['mpesaCode'] ?? '')) === $mpesaCode && ($existing['status'] ?? '') !== 'rejected') {
                    json_response(['ok' => false, 'message' => 'This M-PESA code has already been submitted. Please contact admin if this is a mistake.'], 409);
                }
            }
            $payment = [
                'id' => 'mpesa_' . bin2hex(random_bytes(8)),
                'userId' => $u['uid'] ?? '',
                'email' => $u['email'] ?? '',
                'displayName' => $u['displayName'] ?? '',
                'plan' => 'premium_30_days',
                'amount' => $amount,
                'expectedAmount' => PREMIUM_PRICE_KES,
                'currency' => 'KES',
                'mpesaCode' => $mpesaCode,
                'phone' => $phone,
                'note' => $note,
                'status' => 'pending',
                'createdAt' => gmdate('c'),
            ];
            $payments[] = $payment;
            if (!data_store_write('manual_payments', $payments)) {
                json_response(['ok' => false, 'message' => 'Payment proof could not be saved on the server. Please contact admin to check api/_data folder permissions.'], 500);
            }
            $emailResult = send_payment_submitted_admin_email($payment);
            if (!($emailResult['ok'] ?? false)) api_log('Admin payment notification email failed: ' . json_encode($emailResult));
            json_response(['ok' => true, 'payment' => $payment, 'message' => 'Payment proof submitted. Admin will verify and activate premium access.']);
        }
        case 'my_manual_payments': {
            $u = require_current_user();
            $payments = data_store_read('manual_payments');
            $mine = array_values(array_filter($payments, fn($x) => ($x['userId'] ?? '') === ($u['uid'] ?? '') || strtolower($x['email'] ?? '') === strtolower($u['email'] ?? '')));
            json_response(['ok' => true, 'items' => sorted_by_date_desc($mine, 'createdAt')]);
        }
        case 'my_premium_status': {
            $u = require_current_user();
            $override = load_premium_override($u['uid'] ?? '', $u['email'] ?? '');
            $profile = load_local_user_profile($u['uid'] ?? '', $u['email'] ?? '');
            $merged = apply_backend_profile_guards(array_merge($u, $profile, $override), $u['email'] ?? '');
            $_SESSION['user'] = $merged;
            json_response(['ok' => true, 'user' => $merged, 'premiumOverride' => $override]);
        }
        case 'admin_list_manual_payments': {
            require_admin_user();
            $payments = data_store_read('manual_payments');
            json_response(['ok' => true, 'items' => sorted_by_date_desc($payments, 'createdAt')]);
        }
        case 'admin_verify_manual_payment': {
            $admin = require_admin_user();
            $paymentId = trim((string)($payload['id'] ?? ''));
            $decision = strtolower(trim((string)($payload['decision'] ?? 'approve')));
            $adminNote = trim((string)($payload['adminNote'] ?? ''));
            if ($paymentId === '' || !in_array($decision, ['approve', 'reject'], true)) {
                json_response(['ok' => false, 'message' => 'Invalid payment action.'], 400);
            }
            $payments = data_store_read('manual_payments');
            $found = false;
            $updatedPayment = null;
            foreach ($payments as &$payment) {
                if (($payment['id'] ?? '') !== $paymentId) continue;
                $found = true;
                if (($payment['status'] ?? '') === 'approved' && $decision === 'approve') {
                    $updatedPayment = $payment;
                    break;
                }
                if ($decision === 'approve') {
                    $now = time();
                    $expires = gmdate('c', $now + (PREMIUM_DAYS * 86400));
                    $payment['status'] = 'approved';
                    $payment['approvedAt'] = gmdate('c', $now);
                    $payment['approvedBy'] = $admin['email'] ?? 'admin';
                    $payment['premiumExpiresAt'] = $expires;
                    $payment['adminNote'] = $adminNote;
                    $premiumPayload = [
                        'uid' => $payment['userId'] ?? '',
                        'email' => $payment['email'] ?? '',
                        'role' => 'premium',
                        'premiumActivatedAt' => gmdate('c', $now),
                        'premiumExpiresAt' => $expires,
                        'premiumPaymentId' => $paymentId,
                    ];
                    save_local_user_profile($payment['userId'] ?? '', $payment['email'] ?? '', $premiumPayload);
                    $overrideSaved = save_premium_override($payment['userId'] ?? '', $payment['email'] ?? '', $premiumPayload);
                    if (!$overrideSaved) {
                        api_log('Premium override save failed for ' . ($payment['email'] ?? $payment['userId'] ?? 'unknown user'));
                    }
                    $checkProfile = load_local_user_profile($payment['userId'] ?? '', $payment['email'] ?? '');
                    $checkOverride = load_premium_override($payment['userId'] ?? '', $payment['email'] ?? '');
                    if (($checkProfile['role'] ?? '') !== 'premium' && ($checkOverride['role'] ?? '') !== 'premium') {
                        json_response(['ok' => false, 'message' => 'Premium could not be saved to the server profile store. Please check api/_data/profiles.json and premium_overrides.json permissions.'], 500);
                    }
                    if ($idToken && !empty($payment['userId'])) {
                        $r = firestore_patch_document(firestore_profile_path($payment['userId']), [
                            'role' => 'premium',
                            'premiumActivatedAt' => gmdate('c', $now),
                            'premiumExpiresAt' => $expires,
                            'premiumPaymentId' => $paymentId,
                        ], $idToken);
                        if (!$r['ok']) api_log('Non-blocking premium activation Firestore update failed: ' . json_encode($r['data'] ?? $r));
                    }
                } else {
                    $payment['status'] = 'rejected';
                    $payment['rejectedAt'] = gmdate('c');
                    $payment['rejectedBy'] = $admin['email'] ?? 'admin';
                    $payment['adminNote'] = $adminNote;
                }
                $updatedPayment = $payment;
                break;
            }
            unset($payment);
            if (!$found) json_response(['ok' => false, 'message' => 'Payment request not found.'], 404);
            if (!data_store_write('manual_payments', $payments)) {
                json_response(['ok' => false, 'message' => 'Payment update could not be saved on the server. Please check api/_data folder permissions.'], 500);
            }
            if (is_array($updatedPayment)) {
                $emailResult = ($decision === 'approve') ? send_payment_approved_email($updatedPayment) : send_payment_rejected_email($updatedPayment);
                if (!($emailResult['ok'] ?? false)) api_log('Payment decision email failed: ' . json_encode($emailResult));
            }
            json_response(['ok' => true, 'payment' => $updatedPayment]);
        }


        case 'admin_bootstrap_firestore_role': {
            $current = require_current_user();
            $email = strtolower(trim((string)($current['email'] ?? '')));
            if (!in_array($email, ADMIN_EMAILS, true)) {
                json_response(['ok' => false, 'message' => 'This email is not listed in ADMIN_EMAILS, so automatic Firestore admin bootstrap is blocked.'], 403);
            }
            if (empty($current['uid'])) {
                json_response(['ok' => false, 'message' => 'Missing Firebase user id. Log out and sign in again, then retry.'], 401);
            }
            $payload = [
                'uid' => $current['uid'],
                'email' => $current['email'] ?? '',
                'role' => 'admin',
                'displayName' => $current['displayName'] ?? ($current['email'] ?? 'Admin'),
                'adminBootstrapAt' => gmdate('c'),
                'updatedAt' => gmdate('c'),
            ];

            // Always save the local admin profile/session first. This keeps the admin
            // console usable even when Firestore rules temporarily block the mirror write.
            save_local_user_profile($current['uid'] ?? '', $current['email'] ?? '', $payload);
            $_SESSION['user'] = apply_backend_profile_guards(array_merge($current, $payload), $current['email'] ?? '');

            $uid = rawurlencode((string)$current['uid']);
            $profilePaths = [];
            foreach (artifact_namespace_roots() as $root) {
                $profilePaths[] = $root . '/users/' . $uid . '/profile/data';
            }
            $profilePaths[] = firestore_profile_path($current['uid']);
            $profilePaths = unique_firestore_paths($profilePaths);

            $tokens = array_values(array_unique(array_filter([
                admin_write_token($idToken),
                $idToken,
            ], fn($token) => trim((string)$token) !== '')));

            $mirrorOk = false;
            $mirrorWarnings = [];
            foreach ($profilePaths as $path) {
                foreach ($tokens as $token) {
                    $r = firestore_patch_document($path, $payload, (string)$token);
                    if ($r['ok'] ?? false) {
                        $mirrorOk = true;
                        break 2;
                    }
                    $mirrorWarnings[] = $path . ': ' . ($r['data']['error']['message'] ?? ($r['error']['message'] ?? ('HTTP ' . ($r['code'] ?? 'unknown'))));
                }
            }

            if (!$mirrorOk) {
                api_log('Non-blocking Firestore admin role bootstrap mirror failed; local admin session/profile preserved: ' . json_encode($mirrorWarnings));
            }

            firestore_admin_profiles_cache_clear();
            json_response([
                'ok' => true,
                'firestoreMirrored' => $mirrorOk,
                'message' => $mirrorOk
                    ? 'Admin role saved and mirrored to Firestore. Run diagnostics again.'
                    : 'Admin role saved locally and your admin session is active. Firestore mirror is still blocked by rules/token permissions, but admin fallback mode is ready.',
                'warnings' => array_slice($mirrorWarnings, 0, 3),
            ]);
        }

        case 'admin_firestore_diagnostics': {
            $current = require_current_user();
            $localProfile = load_local_user_profile((string)($current['uid'] ?? ''), $current['email'] ?? null);
            $localRole = $localProfile['role'] ?? null;
            $diagnostics = [
                'session' => [
                    'uid' => $current['uid'] ?? null,
                    'email' => $current['email'] ?? null,
                    'role' => $current['role'] ?? null,
                    'localRole' => $localRole,
                    'hasIdToken' => !empty($idToken),
                    'projectId' => FIREBASE_PROJECT_ID,
                ],
                'tests' => []
            ];
            $isAdminSession = (($current['role'] ?? '') === 'admin') || ($localRole === 'admin') || is_admin_email($current['email'] ?? '');
            $diagnostics['session']['isAdminSession'] = $isAdminSession;
            $diagnostics['tests']['localAdminBootstrap'] = [
                'ok' => $isAdminSession,
                'role' => $localRole ?: ($current['role'] ?? null),
                'message' => $isAdminSession ? 'Local/session admin state is ready.' : 'Local/session admin state is not admin.'
            ];

            if (!empty($current['uid']) && !empty($idToken)) {
                $profilePath = firestore_profile_path($current['uid']);
                $profileGet = firestore_get_document($profilePath, $idToken);
                $firestoreRole = ($profileGet['ok'] ?? false) ? (firestore_fields_to_php($profileGet['data']['fields'] ?? [])['role'] ?? null) : null;
                $diagnostics['tests']['currentAdminProfileGet'] = [
                    'ok' => (bool)($profileGet['ok'] ?? false) || ($localRole === 'admin'),
                    'firestoreOk' => (bool)($profileGet['ok'] ?? false),
                    'localFallbackOk' => $localRole === 'admin',
                    'path' => $profilePath,
                    'httpCode' => $profileGet['code'] ?? null,
                    'message' => ($profileGet['ok'] ?? false) ? null : ($profileGet['data']['error']['message'] ?? 'Using local admin fallback profile.'),
                    'roleInFirestore' => $firestoreRole,
                    'roleEffective' => $firestoreRole ?: $localRole,
                ];
            }

            if (!$isAdminSession) {
                $diagnostics['summary'] = 'Your PHP session is not admin. Log out/in after setting role=admin in artifacts/traders-sanctuary/users/{uid}/profile/data.';
                json_response(['ok' => true, 'diagnostics' => $diagnostics]);
            }

            $parentUsers = firestore_list_collection('artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/users', $idToken, 20);
            $localProfilesForDiagnostics = local_profile_store_read();
            $parentUsersFallbackOk = $isAdminSession && is_array($localProfilesForDiagnostics) && count($localProfilesForDiagnostics) > 0;
            $diagnostics['tests']['parentUsersList'] = [
                'ok' => (bool)($parentUsers['ok'] ?? false) || $parentUsersFallbackOk,
                'firestoreOk' => (bool)($parentUsers['ok'] ?? false),
                'localFallbackOk' => $parentUsersFallbackOk,
                'path' => 'artifacts/' . FIREBASE_PROJECT_ID . '/users',
                'httpCode' => $parentUsers['code'] ?? null,
                'count' => count($parentUsers['rows'] ?? []),
                'localProfileCount' => is_array($localProfilesForDiagnostics) ? count($localProfilesForDiagnostics) : 0,
                'message' => ($parentUsers['ok'] ?? false) ? null : ($parentUsersFallbackOk ? 'Using local admin profile cache until Firestore parent users list is readable.' : ($parentUsers['data']['error']['message'] ?? null)),
            ];

            $profileFetch = [
                'ok' => false,
                'method' => 'List parent users, then read each /users/{uid}/profile/data document',
                'testedUsers' => 0,
                'profilesRead' => 0,
                'failedReads' => 0,
                'sampleFailure' => null,
            ];
            if (($parentUsers['ok'] ?? false) && !empty($parentUsers['rows'])) {
                foreach (array_slice($parentUsers['rows'], 0, 20) as $row) {
                    $uid = (string)($row['id'] ?? '');
                    if ($uid === '') continue;
                    $profileFetch['testedUsers']++;
                    $profileGet = firestore_get_document(firestore_profile_path($uid), $idToken);
                    if ($profileGet['ok'] ?? false) {
                        $profileFetch['profilesRead']++;
                    } else {
                        $profileFetch['failedReads']++;
                        if (!$profileFetch['sampleFailure']) {
                            $profileFetch['sampleFailure'] = ($profileGet['data']['error']['message'] ?? ('HTTP ' . ($profileGet['status'] ?? 'unknown')));
                        }
                    }
                }
                $profileFetch['ok'] = $profileFetch['testedUsers'] > 0 && $profileFetch['profilesRead'] > 0;
            } else {
                $localProfiles = $localProfilesForDiagnostics ?? local_profile_store_read();
                $profileFetch['localFallbackProfiles'] = count($localProfiles);
                if ($isAdminSession && count($localProfiles) > 0) {
                    $profileFetch['ok'] = true;
                    $profileFetch['method'] = 'Local profile fallback because Firestore parent users collection is not readable yet';
                    $profileFetch['testedUsers'] = count($localProfiles);
                    $profileFetch['profilesRead'] = count($localProfiles);
                    $profileFetch['message'] = 'Using local profile cache until Firestore rules allow parent-to-profile reads.';
                } else {
                    $profileFetch['message'] = 'Parent users collection was not readable or returned no users.';
                }
            }
            $diagnostics['tests']['parentProfileFetch'] = $profileFetch;

            // Optional only: this is no longer required by the admin dashboard, because parentProfileFetch is rules-compatible.
            $cgQuery = [
                'from' => [[ 'collectionId' => 'profile', 'allDescendants' => true ]],
                'limit' => 20,
            ];
            $profileGroup = firestore_run_structured_query($cgQuery, $idToken);
            $diagnostics['tests']['profileCollectionGroupOptional'] = [
                'ok' => (bool)($profileGroup['ok'] ?? false) || $isAdminSession,
                'firestoreOk' => (bool)($profileGroup['ok'] ?? false),
                'localFallbackOk' => $isAdminSession && !($profileGroup['ok'] ?? false),
                'optional' => true,
                'usedByAdminDashboard' => false,
                'query' => 'collectionGroup(profile) filtered to /profile/data',
                'httpCode' => $profileGroup['code'] ?? null,
                'countBeforeFilter' => count($profileGroup['rows'] ?? []),
                'countProfileData' => count(array_filter($profileGroup['rows'] ?? [], fn($r) => substr((string)($r['_documentName'] ?? ''), -13) === '/profile/data')),
                'message' => ($profileGroup['ok'] ?? false) ? null : 'Optional collectionGroup(profile) read is not required; admin dashboard uses exact profile paths/local fallback.',
            ];

            $subs = firestore_list_collection('artifacts/' . rawurlencode(FIREBASE_PROJECT_ID) . '/public/data/subscriptions', $idToken, 20);
            $localSubs = data_store_read('manual_payments');
            $diagnostics['tests']['subscriptionsList'] = [
                'ok' => (bool)($subs['ok'] ?? false) || ($isAdminSession && is_array($localSubs)),
                'firestoreOk' => (bool)($subs['ok'] ?? false),
                'localFallbackOk' => $isAdminSession && is_array($localSubs),
                'path' => 'artifacts/' . FIREBASE_PROJECT_ID . '/public/data/subscriptions',
                'httpCode' => $subs['code'] ?? null,
                'count' => count($subs['rows'] ?? []),
                'localPaymentCount' => count($localSubs),
                'message' => ($subs['ok'] ?? false) ? null : ($subs['data']['error']['message'] ?? 'Using local fallback until Firestore subscriptions are readable.'),
            ];

            $roleInFirestore = $diagnostics['tests']['currentAdminProfileGet']['roleInFirestore'] ?? null;
            $roleEffective = $diagnostics['tests']['currentAdminProfileGet']['roleEffective'] ?? null;
            if ($roleEffective !== 'admin') {
                $diagnostics['summary'] = 'Admin role is not active yet. Click Bootstrap Firestore Admin Role, then run diagnostics again.';
            } elseif ($roleInFirestore !== 'admin') {
                $diagnostics['summary'] = 'Admin bootstrap is active locally and the admin console is ready in safe fallback mode. Publish the included firestore.rules file to enable direct Firestore admin reads too.';
            } elseif (!($diagnostics['tests']['parentProfileFetch']['ok'] ?? false)) {
                $diagnostics['summary'] = 'Firestore recognizes you as admin, but exact parent-to-profile reads are failing. Admin fallback remains available; publish/verify firestore.rules for direct reads.';
            } else {
                $diagnostics['summary'] = 'Admin diagnostics are healthy. Exact parent-to-profile reads are working; optional collection-group profile reads are not required by the admin dashboard.';
            }
            json_response(['ok' => true, 'diagnostics' => $diagnostics]);
        }

        case 'admin_list_users': {
            require_admin_user();
            $profiles = firestore_list_user_profiles_resilient_admin($idToken);
            json_response(['ok' => true, 'items' => sorted_by_date_desc($profiles, 'createdAt'), 'firebaseConnected' => count($profiles) > 0]);
        }
        case 'admin_list_agreements': {
            require_admin_user();
            $profiles = [];
            foreach (firestore_list_user_profiles_resilient_admin($idToken) as $profile) {
                if (!empty($profile['termsAgreed'])) $profiles[] = $profile;
            }
            json_response(['ok' => true, 'items' => sorted_by_date_desc($profiles, 'termsAgreedAt')]);
        }
        case 'admin_change_role': {
            require_admin_user();
            $targetUid = $payload['uid'] ?? '';
            $role = $payload['role'] ?? 'member';
            if (!$targetUid || !in_array($role, ['member', 'premium', 'admin'], true)) json_response(['ok' => false, 'message' => 'Invalid user or role.'], 400);
            save_local_user_profile($targetUid, $payload['email'] ?? '', ['uid' => $targetUid, 'role' => $role]);
            firestore_admin_profiles_cache_clear();
            if ($idToken) {
                $r = firestore_patch_document(firestore_profile_path($targetUid), ['role' => $role], admin_write_token($idToken));
                if (!$r['ok']) api_log('Non-blocking admin role Firestore update failed for ' . $targetUid . ': ' . json_encode($r['data'] ?? $r));
            }
            json_response(['ok' => true]);
        }
        case 'admin_delete_user_profile': {
            $admin = require_admin_user();
            $targetUid = trim((string)($payload['uid'] ?? ''));
            if (!$targetUid) json_response(['ok' => false, 'message' => 'Invalid user.'], 400);
            if ($targetUid === ($admin['uid'] ?? '')) {
                json_response(['ok' => false, 'message' => 'For safety, you cannot delete the currently signed-in admin account from this panel.'], 400);
            }

            $targetEmail = trim((string)($payload['email'] ?? ''));
            if ($targetEmail === '' && $idToken) {
                $profile = load_user_profile($targetUid, $idToken);
                $targetEmail = trim((string)($profile['email'] ?? ''));
            }
            if ($targetEmail === '') {
                $localProfile = load_local_user_profile($targetUid, null);
                $targetEmail = trim((string)($localProfile['email'] ?? ''));
            }

            $firestoreCleanup = firestore_hard_delete_user_app_data($targetUid, $idToken, $targetEmail ?: null);
            $authDelete = firebase_admin_delete_auth_user($targetUid);

            // Remove local manual payment traces linked to the user/email.
            $payments = data_store_read('manual_payments');
            if (is_array($payments)) {
                $payments = array_values(array_filter($payments, function($payment) use ($targetUid, $targetEmail) {
                    if (!is_array($payment)) return true;
                    $sameUid = (($payment['userId'] ?? '') === $targetUid || ($payment['uid'] ?? '') === $targetUid);
                    $sameEmail = $targetEmail !== '' && function_exists('normalize_email_key') && normalize_email_key($payment['email'] ?? '') === normalize_email_key($targetEmail);
                    return !($sameUid || $sameEmail);
                }));
                data_store_write('manual_payments', $payments);
            }

            firestore_admin_profiles_cache_clear();

            if (!$authDelete['ok']) {
                json_response([
                    'ok' => false,
                    'message' => 'User app data cleanup was attempted, but Firebase Auth deletion failed: ' . ($authDelete['message'] ?? 'Unknown error.'),
                    'firestoreCleanup' => $firestoreCleanup,
                    'authDeleted' => false,
                    'authDelete' => $authDelete,
                    'needsServiceAccount' => true,
                ], 500);
            }

            $authMessage = !empty($authDelete['alreadyDeleted'])
                ? 'User app data deleted. Firebase Auth account was already absent, so the user is fully removed.'
                : 'User app data and Firebase Auth account deleted permanently.';

            json_response([
                'ok' => true,
                'message' => $authMessage,
                'firestoreCleanup' => $firestoreCleanup,
                'authDeleted' => true,
                'authAlreadyDeleted' => !empty($authDelete['alreadyDeleted']),
                'authDelete' => $authDelete,
            ]);
        }
        default:
            json_response(['ok' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    api_log('Data API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_response(['ok' => false, 'message' => 'Server error while processing data request.'], 500);
}
