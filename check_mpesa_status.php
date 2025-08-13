<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// --- CONFIGURATION (Should match your create_mpesa_order.php) ---
$consumer_key = "U801gTapWNHRTQrkZ69YW48OyHgsdG2gjEJo7OvndMTeX84X";
$consumer_secret = "I9RplfFpKGeWyn32RrOICDaOicG61En4LwTZiGKl6pkwcQUkYc510v1qj3PXYQGB";
$business_short_code = 174379;
$lipa_na_mpesa_passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$is_live = false;
$api_base_url = $is_live ? "https://api.safaricom.co.ke" : "https://sandbox.safaricom.co.ke";

function get_access_token($url, $key, $secret) {
    $credentials = base64_encode($key . ':' . $secret);
    $ch = curl_init($url . "/oauth/v1/generate?grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $token_data = json_decode($response);
    return $token_data->access_token ?? null;
}

$input = json_decode(file_get_contents('php://input'), true);
$checkoutRequestId = $input['checkoutRequestId'] ?? null;

if (!$checkoutRequestId) {
    echo json_encode(['errorMessage' => 'CheckoutRequestID not provided.']);
    exit;
}

$access_token = get_access_token($api_base_url, $consumer_key, $consumer_secret);

if ($access_token) {
    $timestamp = date('YmdHis');
    $password = base64_encode($business_short_code . $lipa_na_mpesa_passkey . $timestamp);
    $headers = ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'];
    $data = [
        'BusinessShortCode' => $business_short_code,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutRequestId
    ];

    $ch = curl_init($api_base_url . '/mpesa/stkpushquery/v1/query');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    // --- IMPORTANT CHANGE ---
    // If the transaction is still processing, Safaricom sends a specific error.
    // We need to pass this along so our website knows to keep trying.
    $responseData = json_decode($response);
    if (isset($responseData->errorCode) && $responseData->errorCode == '500.001.1001') {
        echo json_encode(['ResultDesc' => 'The transaction is still under processing.', 'ResultCode' => -1]); // Use a custom code for "processing"
    } else {
        echo $response;
    }
} else {
    echo json_encode(['errorMessage' => 'Authentication failed.']);
}
?>