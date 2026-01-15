<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);
require 'includes/config.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// CSRF token validation
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Sanitize and validate inputs
$full_name = trim($_POST['Full_Name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$phone_number = trim($_POST['phone_number'] ?? '');

// Server-side validation
$errors = [];

if (empty($full_name) || !preg_match('/^[a-zA-Z\s]{2,50}$/', $full_name)) {
    $errors['Full_Name'] = 'Full name must be 2-50 characters, letters only';
}

if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    $errors['username'] = 'Username must be 3-20 characters, alphanumeric';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address';
}

if (empty($password) || strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || 
    !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || 
    !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $errors['password'] = 'Password must be 8+ characters with uppercase, lowercase, number, and special character';
}

if ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match';
}

if (empty($phone_number) || !preg_match('/^\+?\d{10,15}$/', $phone_number)) {
    $errors['phone_number'] = 'Invalid phone number';
}

// Check for duplicates
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['username'] = 'Username already exists';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['email'] = 'Email already exists';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ?");
    $stmt->bind_param('s', $phone_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['phone_number'] = 'Phone number already exists';
    }
    $stmt->close();
}

if (!empty($errors)) {
    $response['message'] = 'Invalid Entries';
    $response['errors'] = $errors;
    echo json_encode($response);
    exit;
}

// Generate verification code and expiration
$verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$code_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Hash password and insert user
try {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (Full_Name, username, email, password_hash, phone_number, verification_code, code_expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('sssssss', $full_name, $username, $email, $hashed_password, $phone_number, $verification_code, $code_expires_at);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $verification_link = "https://joemakeit.com/verify-code.php?user_id=$user_id&code=$verification_code";

// Modern email template
$email_body = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        }
        
        body {
            background-color: #f7f9fc;
            padding: 20px 0;
            color: #333;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.08);
        }
        
        .header {
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            padding: 40px 20px;
            text-align: center;
        }
        
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logo span {
            color: #ffdb70;
        }
        
        .header h1 {
            color: white;
            font-size: 28px;
            margin: 20px 0 10px;
            font-weight: 600;
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 16px;
            max-width: 80%;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .content {
            padding: 40px;
        }
        
        .welcome {
            font-size: 20px;
            color: #444;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .code-container {
            background: #f9f5ff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin: 30px 0;
            border: 1px dashed #a777e3;
        }
        
        .code-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .verification-code {
            font-size: 42px;
            letter-spacing: 8px;
            font-weight: 700;
            color: #6e8efb;
            padding: 10px 20px;
            background: white;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(110, 142, 251, 0.15);
            margin: 10px 0;
        }
        
        .expiry {
            color: #e74c3c;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
        }
        
        .instructions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #6e8efb;
        }
        
        .instructions h3 {
            color: #444;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .instructions ol {
            padding-left: 20px;
            margin: 15px 0;
        }
        
        .instructions li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0 20px;
        }
        
        .verify-button {
            display: inline-block;
            background: linear-gradient(90deg, #6e8efb, #a777e3);
            color: white !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 18px;
            box-shadow: 0 6px 20px rgba(110, 142, 251, 0.4);
            transition: all 0.3s ease;
        }
        
        .verify-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(110, 142, 251, 0.6);
        }
        
        .alternative {
            text-align: center;
            font-size: 15px;
            color: #666;
            margin-top: 20px;
            line-height: 1.6;
        }
        
        .alternative a {
            color: #6e8efb;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer {
            background: #f0f3f8;
            padding: 30px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .footer p {
            margin: 8px 0;
            line-height: 1.6;
        }
        
        .social-links {
            margin: 20px 0;
        }
        
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: #6e8efb;
            color: white;
            border-radius: 50%;
            margin: 0 8px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: #a777e3;
            transform: translateY(-3px);
        }
        
        .unsubscribe {
            color: #999;
            font-size: 13px;
            margin-top: 20px;
        }
        
        .unsubscribe a {
            color: #999;
        }
        
        @media (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            
            .header {
                padding: 30px 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .verification-code {
                font-size: 32px;
                letter-spacing: 5px;
                padding: 8px 15px;
            }
            
            .verify-button {
                padding: 14px 30px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">Joe<span>MakeIt</span></div>
            <h1>Verify Your Email Address</h1>
            <p>Thank you for signing up! Please verify your email to complete your registration.</p>
        </div>
        
        <div class="content">
            <p class="welcome">Hi '.htmlspecialchars($full_name).', welcome to JoeMakeIt!</p>
            
            <p>Your verification code is:</p>
            
            <div class="code-container">
                <div class="code-label">Below is a one timepasscode that you need to use<br> to complete the authentication</div>
                <div class="verification-code">'.$verification_code.'</div>
                <div class="expiry">The verification code expires in 15 minutes</div>
                 <p>Please do not share to anyone</p>
            </div>
           
                    
            <div class="instructions">
                <h3>How to verify your account:</h3>
                <ol>
                    <li>Enter the 6-digit code above on our verification page</li>
                    <li>Click the "Verify Email Now" button to be taken directly to the verification page</li>
                    <li>If you didn\'t request this, you can safely ignore this email</li>
                </ol>
            </div>
        </div>
        
        <div class="footer">
            <div class="social-links">
                <a href="https://facebook.com/joemakeit">&#xf082;</a>
                <a href="https://twitter.com/joemakeit">&#xf081;</a>
                <a href="https://instagram.com/joemakeit">&#xf16d;</a>
                <a href="https://linkedin.com/company/joemakeit">&#xf08c;</a>
            </div>
            
            <p>JoeMakeIt - Crafting Digital Excellence</p>
            <p>123 Innovation Street, Tech City, TC 10001</p>
            <p>Email: support@joemakeit.com | Phone: +1 (555) 123-4567</p>
            
            <p class="unsubscribe">
                <a href="#">Unsubscribe</a> | 
                <a href="#">Privacy Policy</a> | 
                <a href="#">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
';
        if (sendEmail($email, "Verify Your Email Address", $email_body)) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $username; // For account menu display
            $_SESSION['pending_verification'] = true;
            session_regenerate_id(true);
            $response = ['status' => 'success', 'message' => 'Registration successful. Please check your email for the verification code.'];
        } else {
            $response['message'] = 'Failed to send verification email. Please try again.';
        }
    } else {
        $response['message'] = 'Failed to register user';
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error in signup.php: " . $e->getMessage());
    $response['message'] = 'Database error. Please try again.';
}

echo json_encode($response);
?>