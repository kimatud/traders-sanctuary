<?php
// api/forex_proxy.php
// This script acts as a proxy to fetch Forex data from external APIs (Twelve Data, Marketstack).
// It keeps your API keys secure on the server and provides a consistent interface for the frontend.

require_once __DIR__ . '/config.php'; // Include your configuration file with API keys

header('Content-Type: application/json'); // Ensure the response is always JSON

// Get the request method (should be GET for data fetching)
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Determine which API provider to use.
    // Default to the one defined in config.php, but allow override via 'provider' GET parameter.
    $provider = strtoupper($_GET['provider'] ?? DEFAULT_FOREX_API_PROVIDER);
    $endpoint = $_GET['endpoint'] ?? ''; // e.g., 'time_series', 'intraday', 'quote'
    $symbol = strtoupper($_GET['symbol'] ?? 'EURUSD'); // Currency pair, e.g., EURUSD
    $resolution = $_GET['resolution'] ?? '1'; // Data interval, e.g., '1' for 1-minute
    $from = $_GET['from'] ?? null; // Start timestamp (Unix)
    $to = $_GET['to'] ?? null; // End timestamp (Unix)

    // Log the incoming parameters to the proxy for debugging
    error_log("Forex Proxy received request: Provider={$provider}, Symbol={$symbol}, Resolution={$resolution}, From={$from}, To={$to}");


    // Initialize variables for API request
    $url = '';
    $headers = [];
    $error_message = '';
    $api_response_data = null; // To store the decoded response from the external API

    switch ($provider) {
        case 'TWELVE_DATA':
            // Twelve Data API for time series (candlestick) data
            // Endpoint: /time_series
            // Parameters: symbol, interval, apikey, start_date, end_date (optional)
            $url = TWELVE_DATA_API_BASE_URL . '/time_series';
            $params = [
                'symbol' => $symbol,
                'interval' => $resolution . 'min', // Twelve Data uses '1min', '5min', etc.
                'apikey' => TWELVE_DATA_API_KEY,
            ];

            // Add date range if provided (Twelve Data expects YYYY-MM-DD HH:MM:SS)
            if ($from) {
                $params['start_date'] = date('Y-m-d H:i:s', $from);
            }
            if ($to) {
                $params['end_date'] = date('Y-m-d H:i:s', $to);
            }

            $url .= '?' . http_build_query($params);
            error_log("Twelve Data API URL being called: " . $url); // Log the constructed URL
            break;

        case 'MARKETSTACK':
            // Marketstack API for intraday data (candlestick)
            // Endpoint: /intraday
            // Parameters: access_key, symbols, interval
            // Note: Marketstack's free tier might have limitations on intervals and historical depth.
            $url = MARKETSTACK_API_BASE_URL . '/intraday';
            $params = [
                'access_key' => MARKETSTACK_API_KEY,
                'symbols' => $symbol,
                'interval' => $resolution . 'min', // Marketstack uses '1min', '5min', etc.
            ];

            // Marketstack's free intraday often gives recent data, date range might be ignored or limited.
            // Still, pass them if the API supports it.
            if ($from) {
                $params['date_from'] = date('Y-m-d', $from); // Marketstack uses YYYY-MM-DD for date_from/to
            }
            if ($to) {
                $params['date_to'] = date('Y-m-d', $to);
            }

            $url .= '?' . http_build_query($params);
            error_log("Marketstack API URL being called: " . $url); // Log the constructed URL
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or unsupported Forex API provider specified.']);
            exit;
    }

    // Check if the URL was successfully constructed
    if (empty($url)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to construct API URL for the chosen provider.']);
        exit;
    }

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Set a timeout for the request (15 seconds)

    // Set headers if any (e.g., for some APIs, though not strictly needed for Twelve Data/Marketstack with key in URL)
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Execute cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
    $curl_error = curl_error($ch); // Get cURL error, if any
    curl_close($ch); // Close cURL session

    // Handle cURL errors (e.g., network issues, timeouts)
    if ($response === false) {
        error_log("cURL Error for {$provider}: " . $curl_error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_error]);
        exit;
    }

    // Decode the JSON response from the external API
    $api_response_data = json_decode($response, true);

    // Handle HTTP status codes from the external API
    if ($http_code >= 400) {
        error_log("API returned HTTP error {$http_code} for {$provider}: " . $response);
        // Pass through the API's error message if available, otherwise a generic one
        $error_message = isset($api_response_data['error']) ? $api_response_data['error'] : ($api_response_data['message'] ?? 'External API returned an error.');
        http_response_code($http_code); // Pass through the API's HTTP status code
        echo json_encode(['success' => false, 'message' => $error_message, 'http_code' => $http_code]);
        exit;
    }

    // --- Process and Format Data for Frontend ---
    $formatted_data = [];
    $message_from_api = '';

    switch ($provider) {
        case 'TWELVE_DATA':
            if (isset($api_response_data['values']) && is_array($api_response_data['values'])) {
                // Twelve Data returns data in reverse chronological order, so reverse it for charting
                $values = array_reverse($api_response_data['values']);
                foreach ($values as $candle) {
                    // Twelve Data provides 'datetime' in "YYYY-MM-DD HH:MM:SS" format
                    // We need 'time' (HH:MM:SS) and 'price' (close price)
                    $formatted_data[] = [
                        'time' => date('H:i:s', strtotime($candle['datetime'])),
                        'price' => (float)$candle['close']
                    ];
                }
            } else {
                $message_from_api = $api_response_data['message'] ?? 'No data or unexpected format from Twelve Data.';
            }
            break;

        case 'MARKETSTACK':
            if (isset($api_response_data['data']) && is_array($api_response_data['data'])) {
                // Marketstack also typically returns data in reverse chronological order
                $data_points = array_reverse($api_response_data['data']);
                foreach ($data_points as $candle) {
                    // Marketstack's 'date' field contains the full timestamp
                    $formatted_data[] = [
                        'time' => date('H:i:s', strtotime($candle['date'])),
                        'price' => (float)$candle['close']
                    ];
                }
            } else {
                $message_from_api = $api_response_data['error']['message'] ?? 'No data or unexpected format from Marketstack.';
            }
            break;
    }

    // Send the formatted data back to the frontend
    if (!empty($formatted_data)) {
        echo json_encode([
            'success' => true,
            'message' => 'Data fetched successfully.',
            'c' => array_column($formatted_data, 'price'), // Close prices
            't' => array_map(function($item) { return strtotime(date('Y-m-d ') . $item['time']); }, $formatted_data) // Timestamps (approximate for charting, actual timestamps are in the original data)
            // Note: For simplicity, we're just sending 'c' (close) and 't' (time) to match the previous Finnhub structure in React.
            // If React needs 'o', 'h', 'l', 'v' as well, you'd need to extract and send those too.
        ]);
    } else {
        // If no data was formatted, return an appropriate message
        http_response_code(200); // Still a success, just no data found
        echo json_encode(['success' => true, 'message' => $message_from_api ?: 'No Forex data available for the requested parameters.', 'data' => []]);
    }

} else {
    // Method Not Allowed for non-GET requests
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>
