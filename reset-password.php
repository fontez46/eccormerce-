<?php
session_start();
require 'includes/config.php';

// Set time zone explicitly
date_default_timezone_set('Africa/Nairobi');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = trim($_GET['token']);
    error_log("Received token: $token");
    error_log("Current server time: " . date('Y-m-d H:i:s'));
    error_log("MySQL NOW(): " . $conn->query("SELECT NOW()")->fetch_row()[0]);

    // Detect AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    try {
        $stmt = $conn->prepare("SELECT email, created_at, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['reset_token'] = $token;

            if ($isAjax) {
                echo json_encode(["status" => "success", "message" => "Valid token"]);
                exit;
            } else {
                // Redirect to form with token
                header("Location: reset-password-form.php?token=" . urlencode($token));
                exit;
            }
        } else {
            error_log("Token not found or expired");

            if ($isAjax) {
                echo json_encode(["status" => "error", "message" => "This reset link is invalid or has expired"]);
                exit;
            } else {
                // Pass error to form to disable elements
                header("Location: reset-password-form.php?token=" . urlencode($token) . "&error=" . urlencode('This reset link is invalid or has expired'));
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Reset token validation error: " . $e->getMessage());
        if ($isAjax) {
            echo json_encode(["status" => "error", "message" => "Server error"]);
        } else {
            header("Location: reset-password-form.php?token=" . urlencode($token) . "&error=" . urlencode('Server error'));
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Validate reset token and new password
    $token = $_POST['token'] ?? $_SESSION['reset_token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (empty($token)) {
        $errors['general'] = 'No reset token provided';
    }

    if (empty($new_password) || strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $errors['new_password'] = 'Password must be 8+ characters with uppercase, lowercase, number, and special character';
    }

    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }

    try {
        // Verify token
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            unset($_SESSION['reset_token']);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
            exit;
        }

        $reset = $result->fetch_assoc();
        $email = $reset['email'];

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->bind_param('ss', $hashed_password, $email);
        if ($stmt->execute()) {
            // Delete reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();

            unset($_SESSION['reset_token']);
            echo json_encode(['status' => 'success', 'message' => 'Password reset successfully. Please log in.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password']);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again later.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>