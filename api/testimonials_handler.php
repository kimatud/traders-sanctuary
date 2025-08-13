<?php
// api/testimonials_handler.php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$pdo = getDbConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// For admin actions, you'd typically verify user role here
$is_admin = true; // Placeholder: Replace with actual admin check

switch ($method) {
    case 'GET':
        $approved_only = isset($_GET['approved_only']) && $_GET['approved_only'] === 'true';
        $sql = "SELECT * FROM testimonials";
        if ($approved_only) {
            $sql .= " WHERE approved = TRUE";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST': // Submit new testimonial
        if (isset($input['author'], $input['content'])) {
            $author = sanitize_input($input['author']);
            $content = sanitize_input($input['content']);
            $user_id = sanitize_input($input['user_id'] ?? null); // Optional: if submitted by logged-in user

            $stmt = $pdo->prepare("INSERT INTO testimonials (author, content, user_id, approved) VALUES (?, ?, ?, FALSE)");
            if ($stmt->execute([$author, $content, $user_id])) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Testimonial submitted for approval.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to submit testimonial.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        break;

    case 'PUT': // Approve/Update testimonial
        if ($is_admin && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $action = $input['action'] ?? ''; // 'approve' or 'update'

            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE testimonials SET approved = TRUE, approved_at = NOW(), updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$id])) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Testimonial approved.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to approve testimonial.']);
                }
            } elseif ($action === 'update' && isset($input['author'], $input['content'], $input['approved'])) {
                $author = sanitize_input($input['author']);
                $content = sanitize_input($input['content']);
                $approved = (bool) $input['approved'];

                $stmt = $pdo->prepare("UPDATE testimonials SET author = ?, content = ?, approved = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$author, $content, $approved, $id])) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Testimonial updated.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update testimonial.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid update action or data.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
        }
        break;

    case 'DELETE':
        if ($is_admin && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
            if ($stmt->execute([$id])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Testimonial deleted.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete testimonial.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        break;
}
?>