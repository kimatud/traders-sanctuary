<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode([
  'ok' => true,
  'version' => 'API_PING_2026_05_19_CORE_BACKEND_PRESENT',
  'serverTime' => gmdate('c'),
  'file' => __FILE__,
  'documentRoot' => $_SERVER['DOCUMENT_ROOT'] ?? null,
  'scriptName' => $_SERVER['SCRIPT_NAME'] ?? null,
]);
