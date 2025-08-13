<?php
// api/contact_handler.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Basic CSRF protection (more robust implementation needed for production)
    // if (!validate_csrf_token($input['csrf_token'] ?? '')) {
    //     http_response_code(403);
    //     echo json_encode(['success' => false, 'message' => 'CSRF token mismatch.']);
    //     exit;
    // }

    $name = sanitize_input($input['name'] ?? '');
    $email = sanitize_input($input['email'] ?? '');
    $subject = sanitize_input($input['subject'] ?? '');
    $message = sanitize_input($input['message'] ?? '');

    // Server-side validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_USERNAME, 'Traders Sanctuary Contact'); // Sender's email
        $mail->addAddress(CONTACT_FORM_RECIPIENT_EMAIL); // Recipient of the contact form
        $mail->addReplyTo($email, $name); // Allow replying to the user's email

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Contact Form: ' . $subject;
        $mail->Body    = "
            <h3>New Contact Form Submission</h3>
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>Message:</strong><br>{$message}</p>
        ";
        $mail->AltBody = "New Contact Form Submission\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\nMessage:\n{$message}";

        $mail->send();
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);

        // Optional: Save newsletter subscription if user explicitly opted in
        // This part is for newsletter signup, not contact form.
        // If you want to merge, add a checkbox to contact form.
        // if (isset($input['subscribe_newsletter']) && $input['subscribe_newsletter']) {
        //     $pdo = getDbConnection();
        //     $stmt = $pdo->prepare("INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)");
        //     $stmt->execute([$email]);
        // }

    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Message could not be sent. Please try again later. Error: {$mail->ErrorInfo}"]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>