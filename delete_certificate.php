<?php
header('Content-Type: application/json');

$response = [];
$uploadDir = 'funded certificates/';
$jsonFile = 'certificates.json';

// Get the filename from the POST request
$data = json_decode(file_get_contents('php://input'), true);
$fileName = $data['fileName'] ?? null;

if ($fileName) {
    $filePath = $uploadDir . $fileName;

    // Security check: make sure the file path is within the intended directory
    if (strpos(realpath($filePath), realpath($uploadDir)) === 0 && file_exists($filePath)) {
        // Delete the physical file
        unlink($filePath);

        // Update the JSON file
        if (file_exists($jsonFile)) {
            $certificates = json_decode(file_get_contents($jsonFile), true);
            // Remove the filename from the array
            $certificates = array_values(array_diff($certificates, [$fileName]));
            file_put_contents($jsonFile, json_encode($certificates, JSON_PRETTY_PRINT));
        }

        $response['success'] = true;
        $response['message'] = 'Certificate deleted successfully.';
    } else {
        $response['success'] = false;
        $response['message'] = 'File not found or invalid path.';
    }
} else {
    $response['success'] = false;
    $response['message'] = 'No filename provided.';
}

echo json_encode($response);
?>