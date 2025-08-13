<?php
// api/users_handler.php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// For admin actions, you'd typically verify user role here
$is_admin = true; // Placeholder: Replace with actual admin check

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT firebase_uid, email, role, created_at FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        break;

    case 'PUT': // Change user role
        if (isset($_GET['firebase_uid'], $input['role'])) {
            $firebase_uid = sanitize_input($_GET['firebase_uid']);
            $role = sanitize_input($input['role']);

            // Validate role
            if (!in_array($role, ['member', 'premium', 'admin'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE firebase_uid = ?");
            if ($stmt->execute([$role, $firebase_uid])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'User role updated.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update user role.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        break;

    case 'DELETE':
        if (isset($_GET['firebase_uid'])) {
            $firebase_uid = sanitize_input($_GET['firebase_uid']);

            // IMPORTANT: Deleting from this DB does NOT delete from Firebase Auth.
            // For a complete user deletion, you would need to use Firebase Admin SDK
            // on the backend to delete the user from Firebase Auth as well.
            $stmt = $pdo->prepare("DELETE FROM users WHERE firebase_uid = ?");
            if ($stmt->execute([$firebase_uid])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'User deleted from database. (Firebase Auth user still exists)']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        break;
}
?>