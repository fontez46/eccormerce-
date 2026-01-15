<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

require_once 'includes/config.php';
header('Content-Type: application/json');

// Secure CORS handling
$allowed_origins = ['http://localhost', 'http://yourdomain.com'];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: http://localhost"); // Default fallback
}
header("Access-Control-Allow-Credentials: true");

// Block non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die(json_encode(['error' => 'Direct access not allowed']));
}


// Validate session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Simplified rate limiting
if (!isset($_SESSION['last_pwd_change'])) {
    $_SESSION['last_pwd_change'] = time();
} elseif (time() - $_SESSION['last_pwd_change'] < 60) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many requests. Try again later.']));
}

// Validate CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid security token']));
}

// Validate input
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    die(json_encode(['error' => 'Both fields are required']));
}

// Password complexity validation
if (strlen($new_password) < 8) {
    http_response_code(400);
    die(json_encode(['error' => 'Password must be at least 8 characters']));
}

try {
    // Verify current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!password_verify($current_password, $user['password_hash'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Current password is incorrect']));
    }
    
    // Update password
    $new_hash = password_hash($new_password, PASSWORD_ARGON2ID);
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $update_stmt->bind_param('si', $new_hash, $_SESSION['user_id']);
    $update_stmt->execute();
    
    // Update session security
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['last_pwd_change'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully!',
        'new_csrf' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}