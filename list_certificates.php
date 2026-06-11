<?php
// Safe public certificate listing endpoint.
// This file intentionally does not require api/config.php so the landing page
// will not crash if the /api folder is missing during deployment.
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $dir = __DIR__ . '/funded certificates';
    $jsonFile = __DIR__ . '/certificates.json';
    $allowed = ['png','jpg','jpeg','webp','gif'];
    $names = [];

    if (is_file($jsonFile) && is_readable($jsonFile)) {
        $decoded = json_decode(@file_get_contents($jsonFile) ?: '[]', true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $name = is_array($item) ? ($item['fileName'] ?? $item['name'] ?? '') : $item;
                $name = basename((string)$name);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($name !== '' && in_array($ext, $allowed, true)) {
                    $names[$name] = true;
                }
            }
        }
    }

    if (is_dir($dir) && is_readable($dir)) {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (is_file($path) && in_array($ext, $allowed, true)) {
                $names[basename($name)] = true;
            }
        }
    }

    $list = array_keys($names);
    usort($list, function ($a, $b) use ($dir) {
        $ta = @filemtime($dir . DIRECTORY_SEPARATOR . $a) ?: 0;
        $tb = @filemtime($dir . DIRECTORY_SEPARATOR . $b) ?: 0;
        return $tb <=> $ta;
    });
    echo json_encode(array_values($list), JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Public landing page should never break because certificate data is absent.
    http_response_code(200);
    echo json_encode([], JSON_UNESCAPED_SLASHES);
}
