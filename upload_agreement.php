<?php
// --- upload_agreement.php ---

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for your domain in production for better security
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['agreementFile']) && $_FILES['agreementFile']['error'] === UPLOAD_ERR_OK) {
        
        // Define the secure directory to save agreements
        $uploadDir = 'agreements/';

        // Create the directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Get user ID and create a unique filename to prevent overwrites
        $userId = isset($_POST['userId']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['userId']) : 'unknown_user';
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "agreement_{$userId}_{$timestamp}.txt";
        $uploadFilePath = $uploadDir . $fileName;

        // Move the uploaded file from the temporary location to the final destination
        if (move_uploaded_file($_FILES['agreementFile']['tmp_name'], $uploadFilePath)) {
            $response['success'] = true;
            $response['message'] = 'Agreement file uploaded successfully.';
            $response['filePath'] = $uploadFilePath;
        } else {
            $response['message'] = 'Failed to save the agreement file on the server.';
        }

    } else {
        $response['message'] = 'No agreement file was received or an upload error occurred.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>