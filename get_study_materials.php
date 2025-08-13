<?php
header("Content-Type: application/json");
header("Cache-Control: no-cache, must-revalidate"); // Ensure fresh data

$json_file_path = 'study_materials/study_materials.json';

if (file_exists($json_file_path)) {
    echo file_get_contents($json_file_path);
} else {
    echo json_encode([]); // Return empty array if file doesn't exist
}
?>