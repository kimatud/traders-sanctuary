<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ...the rest of your script follows...
header('Content-Type: application/json');
header('Content-Type: application/json');

$response = [];
$uploadDir = 'funded certificates/';
$jsonFile = 'certificates.json';

// Create the directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if a file was uploaded
if (isset($_FILES['certificate'])) {
    $file = $_FILES['certificate'];

    // Basic error checking
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['success'] = false;
        $response['message'] = 'File upload error.';
        echo json_encode($response);
        exit;
    }

    // Security: Check if it's a valid image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        $response['success'] = false;
        $response['message'] = 'Invalid file type.';
        echo json_encode($response);
        exit;
    }
    
    // Create a unique filename to prevent overwriting files
    $fileName = uniqid() . '-' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    // Move the file to the target directory
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Now, update the JSON file with the new filename
        $certificates = [];
        if (file_exists($jsonFile)) {
            $certificates = json_decode(file_get_contents($jsonFile), true);
        }
        
        // Add the new filename to the list
        $certificates[] = $fileName;
        
        // Save the updated list back to the JSON file
        file_put_contents($jsonFile, json_encode($certificates, JSON_PRETTY_PRINT));
        
        $response['success'] = true;
        $response['message'] = 'Certificate uploaded successfully.';
    } else {
        $response['success'] = false;
        $response['message'] = 'Failed to save the file.';
    }
} else {
    $response['success'] = false;
    $response['message'] = 'No file was sent.';
}

echo json_encode($response);
?>