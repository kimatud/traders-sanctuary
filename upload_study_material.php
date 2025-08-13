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

if (isset($_FILES['materialFile']) && isset($_POST['title']) && isset($_POST['description'])) {
    $upload_dir = 'study_materials/';
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    $original_filename = basename($_FILES['materialFile']['name']);
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $safe_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9\-_.]/", "", $original_filename);
    $target_file = $upload_dir . $safe_filename;

    if (move_uploaded_file($_FILES['materialFile']['tmp_name'], $target_file)) {
        $json_file_path = 'study_materials/study_materials.json';
        $materials = [];
        if (file_exists($json_file_path)) {
            $materials = json_decode(file_get_contents($json_file_path), true);
        }

        $new_material = [
            'id' => uniqid(),
            'title' => $title,
            'description' => $description,
            'fileName' => $safe_filename,
            'fileUrl' => $target_file,
            'createdAt' => date('c') // ISO 8601 date format
        ];

        $materials[] = $new_material;

        if (file_put_contents($json_file_path, json_encode($materials, JSON_PRETTY_PRINT))) {
            $response = ['success' => true, 'message' => 'File uploaded successfully.'];
        } else {
            $response['message'] = 'Failed to update the database file.';
        }
    } else {
        $response['message'] = 'Failed to move uploaded file.';
    }
} else {
    $response['message'] = 'Required data not provided.';
}

echo json_encode($response);
?>