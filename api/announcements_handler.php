<?php
// api/announcements_handler.php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// For admin actions, you'd typically verify user role here (e.g., via session or JWT)
// For simplicity, we'll assume the frontend only calls this if admin is logged in.
// In a real app, use Firebase Admin SDK to verify ID token and role.
$is_admin = true; // Placeholder: Replace with actual admin check

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST': // Add new announcement
        if ($is_admin && isset($input['title'], $input['content'], $input['author_id'])) {
            $title = sanitize_input($input['title']);
            $content = sanitize_input($input['content']);
            $author_id = sanitize_input($input['author_id']);

            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$title, $content, $author_id])) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Announcement added.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to add announcement.']);
            }
        } else {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => 'Access denied or invalid data.']);
        }
        break;

    case 'PUT': // Update announcement
        if ($is_admin && isset($_GET['id'], $input['title'], $input['content'])) {
            $id = (int) $_GET['id'];
            $title = sanitize_input($input['title']);
            $content = sanitize_input($input['content']);

            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$title, $content, $id])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Announcement updated.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update announcement.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied or invalid data.']);
        }
        break;

    case 'DELETE':
        if ($is_admin && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            if ($stmt->execute([$id])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Announcement deleted.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete announcement.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
        }
        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        break;
}
?>