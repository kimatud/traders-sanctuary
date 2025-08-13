<?php
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'filePath' => ''
];

// The directory where screenshots will be stored.
$target_dir = "trade_screenshots/";

// Create the directory if it doesn't exist.
if (!file_exists($target_dir)) {
    // 0775 permissions are usually a safe bet for web servers.
    if (!mkdir($target_dir, 0775, true)) {
        $response['message'] = 'Failed to create screenshot directory.';
        echo json_encode($response);
        exit;
    }
}

// Check if a file was uploaded.
if (isset($_FILES["screenshot"]) && $_FILES["screenshot"]["error"] == 0) {
    $original_name = basename($_FILES["screenshot"]["name"]);
    $imageFileType = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    // Generate a unique file name to prevent overwriting.
    $unique_name = uniqid('trade_', true) . '.' . $imageFileType;
    $target_file = $target_dir . $unique_name;

    // Check if it's a real image.
    $check = getimagesize($_FILES["screenshot"]["tmp_name"]);
    if($check === false) {
        $response['message'] = 'File is not a valid image.';
        echo json_encode($response);
        exit;
    }

    // Check file size (e.g., 5MB limit).
    if ($_FILES["screenshot"]["size"] > 5000000) {
        $response['message'] = 'Sorry, your file is too large (max 5MB).';
        echo json_encode($response);
        exit;
    }

    // Allow certain file formats.
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        $response['message'] = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
        echo json_encode($response);
        exit;
    }

    // Try to move the uploaded file to the target directory.
    if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $target_file)) {
        $response['success'] = true;
        $response['message'] = 'The file has been uploaded.';
        $response['filePath'] = $target_file; // Return the path
    } else {
        $response['message'] = 'Sorry, there was an error uploading your file.';
    }
} else {
    $response['message'] = 'No file was uploaded or an error occurred.';
}

echo json_encode($response);
?>