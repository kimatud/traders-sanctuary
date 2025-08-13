<?php
// api/auth.php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['email'], $input['password'], $input['firebase_uid'])) {
            $email = sanitize_input($input['email']);
            $password = $input['password']; // Password will be hashed by Firebase Auth client-side
            $firebase_uid = sanitize_input($input['firebase_uid']);
            $role = 'member'; // Default role for new registrations

            // Check if user already exists in local DB (by firebase_uid or email)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE firebase_uid = ? OR email = ?");
            $stmt->execute([$firebase_uid, $email]);
            if ($stmt->fetch()) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'User already registered.']);
                exit;
            }

            // Insert user into local database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash password for local DB storage
            $stmt = $pdo->prepare("INSERT INTO users (firebase_uid, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$firebase_uid, $email, $hashed_password, $role])) {
                http_response_code(201); // Created
                echo json_encode(['success' => true, 'message' => 'User registered successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error during registration.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid registration data.']);
        }
        break;

    case 'login':
        // Login is primarily handled client-side by Firebase Auth.
        // This endpoint can be used for session management or setting cookies if needed.
        // For simplicity, we'll just acknowledge the login for now.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['firebase_uid'])) {
            $firebase_uid = sanitize_input($input['firebase_uid']);

            // Fetch user role from local DB and return it
            $stmt = $pdo->prepare("SELECT role FROM users WHERE firebase_uid = ?");
            $stmt->execute([$firebase_uid]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                // Set session or cookie for PHP-based authorization if needed
                // For this example, we rely on client-side Firebase Auth state
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Login successful.', 'role' => $user_data['role']]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found in database.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid login data.']);
        }
        break;

    case 'logout':
        // Logout is primarily handled client-side by Firebase Auth.
        // This endpoint can be used to clear server-side sessions if any.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Clear any PHP-based session data if used for auth
            // session_unset();
            // session_destroy();
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        }
        break;

    // Password reset is handled entirely by Firebase client-side
    // No specific backend endpoint needed unless you want to log reset requests.

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>