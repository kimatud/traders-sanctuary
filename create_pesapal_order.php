<?php
// --- START DEBUGGING ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug.log');
error_reporting(E_ALL);
// --- END DEBUGGING ---

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");

// --- CONFIGURATION ---
$consumer_key = "U801gTapWNHRTQrkZ69YW48OyHgsdG2gjEJo7OvndMTeX84X"; 
$consumer_secret = "I9RplfFpKGeWyn32RrOICDaOicG61En4LwTZiGKl6pkwcQUkYc510v1qj3PXYQGB";
$is_live = false;
$api_base_url = $is_live ? "https://pay.pesapal.com/v3/api" : "https://cybqa.pesapal.com/pesapalv3/api";

// --- AUTHENTICATION FUNCTION ---
function get_access_token($url, $key, $secret) {
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $data = ['consumer_key' => $key, 'consumer_secret' => $secret];
    $ch = curl_init($url . "/Auth/RequestToken");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token_data = json_decode($response);

    // --- UPDATED ERROR HANDLING ---
    if ($http_code == 200 && isset($token_data->token)) {
        return $token_data->token;
    } else {
        // Log the actual error message from Pesapal
        error_log("Pesapal Auth Error: " . $response);
        return null;
    }
    // --- END OF UPDATE ---
}

// --- SUBMIT ORDER FUNCTION (No changes needed here) ---
function submit_order($url, $token, $notification_id, $user_data) {
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'];
    $order_data = [
        'id' => "TS-" . uniqid(),
        'currency' => 'KES',
        'amount' => 10.00,
        'description' => 'Traders Sanctuary Premium Subscription - 1 Month',
        'callback_url' => 'http://localhost/traders-sanctuary/react_app.html',
        'notification_id' => $notification_id,
        'billing_address' => [
            'email_address' => $user_data['email'],
            'phone_number' => '',
            'first_name' => $user_data['displayName'],
            'last_name' => ''
        ]
    ];
    $ch = curl_init($url . "/Transactions/SubmitOrderRequest");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

// --- MAIN EXECUTION ---
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['email']) && isset($input['displayName'])) {
    $access_token = get_access_token($api_base_url, $consumer_key, $consumer_secret);
    if ($access_token) {
        $ipn_id = "f20a591e-503c-425b-a113-d0515a2b2545"; // Temporary Sandbox IPN ID
        $order_response = submit_order($api_base_url, $access_token, $ipn_id, $input);
        echo json_encode($order_response);
    } else {
        echo json_encode(['error' => 'Authentication with Pesapal failed. Please check the server logs.']);
    }
} else {
    echo json_encode(['error' => 'User data not provided.']);
}
?>