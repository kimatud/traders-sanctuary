<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug.log');
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type");

// --- START CONFIGURATION ---
$consumer_key = "U801gTapWNHRTQrkZ69YW48OyHgsdG2gjEJo7OvndMTeX84X"; 
$consumer_secret = "I9RplfFpKGeWyn32RrOICDaOicG61En4LwTZiGKl6pkwcQUkYc510v1qj3PXYQGB";
$business_short_code = 174379;
$lipa_na_mpesa_passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
$is_live = false; 
$api_base_url = $is_live ? "https://api.safaricom.co.ke" : "https://sandbox.safaricom.co.ke";

// --- FUNCTIONS ---
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

function initiate_stk_push($url, $token, $shortcode, $passkey, $phone_number, $amount, $user_uid) {
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $callback_url = 'https://kimtechlabs.top/mpesa_callback.php'; 

    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
    $data = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone_number,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callback_url,
        'AccountReference' => $user_uid,
        'TransactionDesc' => 'Premium Subscription'
    ];

    $ch = curl_init($url . '/mpesa/stkpush/v1/processrequest');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

// --- MAIN EXECUTION ---
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['phone']) && isset($input['amount']) && isset($input['uid'])) {
    $access_token = get_access_token($api_base_url, $consumer_key, $consumer_secret);
    
    if ($access_token) {
        $phone = $input['phone'];
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        }

        $stk_response = initiate_stk_push($api_base_url, $access_token, $business_short_code, $lipa_na_mpesa_passkey, $phone, $input['amount'], $input['uid']);
        
        // Final check to make sure the response is valid before sending
        if ($stk_response) {
            echo json_encode($stk_response);
        } else {
            echo json_encode(['errorMessage' => 'Received an invalid response from Safaricom.']);
        }
    } else {
        echo json_encode(['errorMessage' => 'Authentication failed.']);
    }
} else {
    echo json_encode(['errorMessage' => 'Required data not provided.']);
}
?>