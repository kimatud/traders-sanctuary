<?php
// Fast public study-material listing. This endpoint intentionally avoids api/config.php
// and any PHP session/Firebase work so dashboard boot cannot be blocked by auth syncing.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30, stale-while-revalidate=120');

function ts_materials_log(string $message): void {
    $dir = __DIR__ . '/api/_data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @error_log('[' . gmdate('c') . '] ' . $message . PHP_EOL, 3, $dir . '/study_materials_error.log');
}

try {
    $folder = __DIR__ . '/study materials';
    $store = __DIR__ . '/api/_data/study_materials.json';
    $legacyStore = __DIR__ . '/study_materials.json';

    $items = [];
    $metadataByName = [];

    $cleanText = static function ($value): string {
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    };

    $materialDescription = static function (array $item) use ($cleanText): string {
        foreach (['description', 'desc', 'summary', 'details', 'note', 'notes', 'uploadedDescription', 'materialDescription'] as $key) {
            if (array_key_exists($key, $item)) {
                $text = $cleanText($item[$key]);
                if ($text !== '') return $text;
            }
        }
        return '';
    };

    $indexMaterialMetadata = static function (array $item) use (&$metadataByName): void {
        $keys = [];
        foreach (['fileName', 'name', 'originalName'] as $field) {
            $value = basename((string)($item[$field] ?? ''));
            if ($value !== '') $keys[] = $value;
        }
        $urlPath = parse_url((string)($item['fileUrl'] ?? ''), PHP_URL_PATH);
        $urlName = basename((string)$urlPath);
        if ($urlName !== '') $keys[] = rawurldecode($urlName);
        foreach (array_unique($keys) as $key) {
            $metadataByName[$key] = $item;
        }
    };

    foreach ([$store, $legacyStore] as $source) {
        if (!is_file($source) || !is_readable($source)) continue;
        $decoded = json_decode(@file_get_contents($source) ?: '[]', true);
        if (!is_array($decoded)) continue;
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;
            $name = basename((string)($item['fileName'] ?? $item['name'] ?? ''));
            if ($name === '') continue;
            $item['fileName'] = $name;
            $item['fileUrl'] = $item['fileUrl'] ?? ('/study materials/' . rawurlencode($name));
            $item['id'] = $item['id'] ?? ('mat_' . substr(sha1($name), 0, 16));
            $item['description'] = $materialDescription($item);
            $indexMaterialMetadata($item);
            $items[$name] = $item;
        }
    }

    if (is_dir($folder) && is_readable($folder)) {
        foreach (scandir($folder) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $safeName = basename($name);
            $path = $folder . DIRECTORY_SEPARATOR . $safeName;
            if (!is_file($path)) continue;
            $metadata = $metadataByName[$safeName] ?? null;
            if (isset($items[$safeName])) {
                $items[$safeName]['fileUrl'] = '/study materials/' . rawurlencode($safeName);
                $items[$safeName]['description'] = $materialDescription($items[$safeName]);
                continue;
            }
            $title = preg_replace('/[_-]+/', ' ', pathinfo($safeName, PATHINFO_FILENAME));
            $items[$safeName] = [
                'id' => $metadata['id'] ?? ('mat_' . substr(sha1($safeName), 0, 16)),
                'title' => $cleanText($metadata['title'] ?? '') ?: ucwords(trim($title) ?: 'Study Material'),
                'description' => is_array($metadata) ? $materialDescription($metadata) : '',
                'fileName' => $safeName,
                'fileUrl' => '/study materials/' . rawurlencode($safeName),
                'originalName' => $metadata['originalName'] ?? $safeName,
                'createdAt' => $metadata['createdAt'] ?? gmdate('c', @filemtime($path) ?: time()),
                'source' => is_array($metadata) ? 'uploaded-metadata' : 'folder-scan'
            ];
        }
    }

    foreach ($items as $name => $item) {
        $items[$name]['description'] = $materialDescription(is_array($item) ? $item : []);
    }

    $list = array_values($items);
    usort($list, fn($a, $b) => strtotime($b['createdAt'] ?? '1970-01-01') <=> strtotime($a['createdAt'] ?? '1970-01-01'));
    echo json_encode($list, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    ts_materials_log('get_study_materials error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode([], JSON_UNESCAPED_SLASHES);
}
