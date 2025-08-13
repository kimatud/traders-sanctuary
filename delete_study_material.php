<?php
header("Content-Type: application/json");

// Allow requests from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id']) && isset($data['fileName'])) {
    $id_to_delete = $data['id'];
    $filename_to_delete = $data['fileName'];
    $file_path = 'study_materials/' . $filename_to_delete;

    // Delete the file
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Update the JSON database
    $json_file_path = 'study_materials/study_materials.json';
    if (file_exists($json_file_path)) {
        $materials = json_decode(file_get_contents($json_file_path), true);
        
        $updated_materials = array_filter($materials, function($material) use ($id_to_delete) {
            return $material['id'] !== $id_to_delete;
        });

        // Re-index the array to prevent it from becoming an object
        $updated_materials = array_values($updated_materials);

        if (file_put_contents($json_file_path, json_encode($updated_materials, JSON_PRETTY_PRINT))) {
            $response = ['success' => true, 'message' => 'Material deleted successfully.'];
        } else {
            $response['message'] = 'Failed to update the database file.';
        }
    } else {
         $response = ['success' => true, 'message' => 'Material deleted, database file not found.'];
    }
} else {
    $response['message'] = 'Required ID not provided.';
}

echo json_encode($response);
?>