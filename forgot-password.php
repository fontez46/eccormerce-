<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);
require 'includes/config.php';

header('Content-Type: application/json');

// Set time zone explicitly
date_default_timezone_set('Africa/Nairobi');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['errors'] = ['email' => 'Invalid email address'];
    echo json_encode($response);
    exit;
}

try {
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Delay to prevent enumeration
        usleep(rand(500000, 1000000));
        $response['message'] = 'An email with a reset link has been sent successfully.';
        echo json_encode($response);
        exit;
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $current_time = time();
    $expires_at = date('Y-m-d H:i:s', $current_time + 3600); // 1 hour from now
    error_log("Generating token for $email: $token, expires_at: $expires_at");

    // Store reset token
    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, token, created_at, expires_at)
        VALUES (?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
    ");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Database prepare error");
    }
    $stmt->bind_param('sssss', $email, $token, $expires_at, $token, $expires_at);
    $stmt->execute();
    error_log("Stored token for $email: $token, expires_at: $expires_at");

    // Send reset email
    $reset_link = "https://87f8320d454c.ngrok-free.app/SHOPP/reset-password.php?token=" . urlencode($token);
    $email_body = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
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
            color: #fffb70;
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
        
        .instructions {
            background: #fffaf5;
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
        
        .reset-button {
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
        
        .reset-button.disabled {
            background: #cccccc;
            cursor: not-allowed;
            box-shadow: none;
            pointer-events: none;
        }
        
        .reset-button:hover:not(.disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(110, 142, 251, 0.6);
        }
        
        .link-fallback {
            text-align: center;
            font-size: 15px;
            color: #666;
            margin-top: 25px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            word-break: break-all;
        }
        
        .link-fallback a {
            color: #ff7e5f;
            text-decoration: none;
            font-weight: 500;
        }
        
        .link-fallback a.disabled {
            color: #999;
            pointer-events: none;
            text-decoration: none;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
            text-align: center;
        }
        
        .expiry {
            color: #e74c3c;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
            text-align: center;
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
            
            .reset-button {
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
            <h1>Password Reset Request</h1>
            <p>We received a request to reset your account password</p>
        </div>
        
        <div class="content">
            <p class="welcome">Hello, click the button below to reset,</p>
                     
            <div class="button-container">
                <a href="' . $reset_link . '" id="reset-button" class="reset-button">Reset Password</a>
            </div>
            
            <div class="error-message" id="error-message" style="display: none;"></div>
            <div class="expiry">This link expires in 1 hour</div>

            <div class="instructions">
                <h3>To reset your password:</h3>
                <ol>
                    <li>Click the "Reset Password" button below</li>
                    <li>Create a new secure password for your account</li>
                    <li>Sign in with your new credentials</li>
                    <li>If you didn\'t request this, please secure your account</li>
                </ol>
            </div>
            
            <div class="link-fallback">
                <p>If the button doesn\'t work, copy and paste this link in your browser:</p>
                <a href="' . $reset_link . '" id="reset-link">' . $reset_link . '</a>
            </div>
        </div>
        
        <div class="footer">
            <div class="social-links">
                <a href="https://facebook.com/joemakeit"></a>
                <a href="https://twitter.com/joemakeit"></a>
                <a href="https://instagram.com/joemakeit"></a>
                <a href="https://linkedin.com/company/joemakeit"></a>
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const resetButton = document.getElementById("reset-button");
            const resetLink = document.getElementById("reset-link");
            const errorMessage = document.getElementById("error-message");
            const token = "' . urlencode($token) . '";

            // Make AJAX request to validate token
            fetch("https://87f8320d454c.ngrok-free.app/SHOPP/reset-password.php?token=" + token, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "error") {
                    // Disable button and link
                    resetButton.classList.add("disabled");
                    resetLink.classList.add("disabled");
                    errorMessage.textContent = data.message || "This reset link is invalid or has expired.";
                    errorMessage.style.display = "block";
                }
            })
            .catch(error => {
                console.error("Error validating token:", error);
                resetButton.classList.add("disabled");
                resetLink.classList.add("disabled");
                errorMessage.textContent = "Unable to validate reset link. Please request a new one.";
                errorMessage.style.display = "block";
            });
        });
    </script>
</body>
</html>
';

    if (sendEmail($email, "Password Reset Request", $email_body)) {
        $response = ['status' => 'success', 'message' => 'An email with reset link has been sent successfully.'];
    } else {
        $response['message'] = 'Failed to send reset email. Please try again.';
        error_log("Failed to send reset email to $email");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Server error. Please try again later.';
}

echo json_encode($response);
?>