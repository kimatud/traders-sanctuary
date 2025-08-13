<?php
// api/config.php

// --- Database Configuration ---
define('DB_HOST', 'localhost'); // Your database host
define('DB_NAME', 'gcxbdjfw_traders_sanctuary');
define('DB_USER', 'kimatu');    // Your database username
define('DB_PASS', 'Denokimlee1@'); // Your database password

// --- Email Configuration (for PHPMailer) ---
define('MAIL_HOST', 'pld112.truehost.cloud'); // e.g., smtp.gmail.com, smtp.sendgrid.net
define('MAIL_USERNAME', 'system@kimtechlabs.top');   // Your email address
define('MAIL_PASSWORD', 'TestPassword123!');       // Your email password or app-specific password
define('MAIL_PORT', 587); // Typically 587 for TLS, or 465 for SSL
define('MAIL_ENCRYPTION', 'tls'); // 'ssl' or 'tls'
define('CONTACT_FORM_RECIPIENT_EMAIL', 'system@kimtechlabs.top'); // Where contact form emails will be sent

// --- Firebase Client-Side Configuration (for JS SDK) ---
// This is PUBLICLY exposed in your frontend JS.
// Get this from your Firebase project settings -> Project settings -> General -> Your apps -> Firebase SDK snippet -> Config
define('FIREBASE_API_KEY', 'AIzaSyCMQ9nlvS1WPCszC8Bahzcec6WXF3F_0tE');
define('FIREBASE_AUTH_DOMAIN', 'traders-sanctuary.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'traders-sanctuary');
define('FIREBASE_STORAGE_BUCKET', 'traders-sanctuary.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '4336211178');
define('FIREBASE_APP_ID', '1:433621117821:web:e7996f5098b8e9c3e97a14');
define('FIREBASE_MEASUREMENT_ID', 'G-YEHJ0QBXQH'); // Optional

// --- Forex API Configuration (Backend Proxy) ---

// Twelve Data API Credentials
// Get your API key from your Twelve Data dashboard after signing up at twelvedata.com
define('TWELVE_DATA_API_BASE_URL', 'https://api.twelvedata.com');
// !!! IMPORTANT: Replace 'YOUR_TWELVE_DATA_API_KEY' with your actual Twelve Data key !!!
define('TWELVE_DATA_API_KEY', '123878165edf4c3599b7300772ef504a');

// Marketstack API Credentials
// Get your API key from your Marketstack dashboard after signing up at marketstack.com
define('MARKETSTACK_API_BASE_URL', 'http://api.marketstack.com/v1'); // Use http for free tier
// !!! IMPORTANT: Replace 'YOUR_MARKETSTACK_API_KEY' with your actual Marketstack key !!!
define('MARKETSTACK_API_KEY', '0bddd81ec8bfa792ab8a14633e97916a');

// --- Default API to Use (You can switch this in forex_proxy.php or via a parameter) ---
// You can set this to 'TWELVE_DATA' or 'MARKETSTACK' based on your preference.
define('DEFAULT_FOREX_API_PROVIDER', 'MARKETSTACK');

// --- CSRF Protection (Basic Example) ---
// In a real application, you'd generate and validate these tokens per session.
// For simplicity, we'll use a session-based approach.
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to get a database connection
function getDbConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed.");
    }
}

// Function for basic input sanitization
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
