<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);
require 'includes/config.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Login failed. Please try again.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $response['message'] = 'Invalid request';
    echo json_encode($response);
    exit;
}

// Input validation
$errors = [];
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username)) $errors['username'] = 'Username or email is required';
if (empty($password)) $errors['password'] = 'Password is required';

if (!empty($errors)) {
    $response['errors'] = $errors;
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT user_id, Full_Name, password_hash, is_verified, login_attempts, last_failed_login 
                          FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        usleep(rand(500000, 1000000)); // Prevent timing attacks
        $response['errors'] = ['general' => 'Invalid credentials'];
        echo json_encode($response);
        exit;
    }

    $user = $result->fetch_assoc();
    
    // Check email verification
    if (!$user['is_verified']) {
        $response['errors'] = ['general' => 'Please verify your email before logging in.'];
        echo json_encode($response);
        exit;
    }

    // Rate limiting
    if ($user['login_attempts'] >= 5) {
        $last_attempt = strtotime($user['last_failed_login']);
        if (time() - $last_attempt < 900) {
            $response['errors'] = ['general' => 'Account locked. Try again later.'];
            echo json_encode($response);
            exit;
        }
    }

    if (password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = htmlspecialchars($user['Full_Name'], ENT_QUOTES, 'UTF-8');
        $_SESSION['user_logged_in'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate CSRF token

        // Reset login attempts using prepared statement
        $stmt = $conn->prepare("UPDATE users SET login_attempts = 0 WHERE user_id = ?");
        $stmt->bind_param('i', $user['user_id']);
        $stmt->execute();
        
        $response = ['status' => 'success'];
        echo json_encode($response);
        exit;
    }

    // Increment failed attempts using prepared statement
    $stmt = $conn->prepare("UPDATE users SET login_attempts = login_attempts + 1, last_failed_login = NOW() WHERE user_id = ?");
    $stmt->bind_param('i', $user['user_id']);
    $stmt->execute();
    
    sleep(min($user['login_attempts'] + 1, 5)); // Progressive delay
    
    $response['errors'] = ['general' => 'Incorrect Email or Password'];
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode($response);
} finally {
    if (isset($stmt)) $stmt->close();
}
?>