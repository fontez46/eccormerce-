<?php
session_start();
require 'includes/config.php';

if (!isset($_SESSION['pending_verification']) || !$_SESSION['pending_verification']) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$resend_available = true;
$resend_time_left = 0;
$email = 'your@email.com'; // Initialize with default value

// Fetch user email and last resend time
$stmt = $conn->prepare("SELECT email, verification_sent_at FROM users WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $email = $user['email']; // Update with actual email
        
        // Calculate time since last resend
        $last_sent = $user['verification_sent_at'] ? strtotime($user['verification_sent_at']) : 0;
        $current_time = time();
        $time_since_last = $current_time - $last_sent;
        
        // Set 60-second cooldown for resending
        $cooldown = 60;
        $resend_time_left = max(0, $cooldown - $time_since_last);
        $resend_available = ($resend_time_left <= 0);
    }
    $stmt->close();
} else {
    $error = "Database connection error. Please try again later.";
}

// Handle verification link
if (isset($_GET['user_id']) && isset($_GET['code']) && $_GET['user_id'] == $user_id) {
    $entered_code = $_GET['code'];
    $stmt = $conn->prepare("SELECT verification_code, code_expires_at FROM users WHERE user_id = ? AND is_verified = FALSE");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stored_code = $user['verification_code'];
        $expires_at = $user['code_expires_at'];

        if (time() > strtotime($expires_at)) {
            $error = "Verification code has expired. Please request a new one.";
        } elseif ($entered_code === $stored_code) {
            $stmt = $conn->prepare("UPDATE users SET is_verified = TRUE, verification_code = NULL, code_expires_at = NULL WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $_SESSION['user_logged_in'] = true;
            unset($_SESSION['pending_verification']);
            header('Location: index.php');
            exit;
        } else {
            $error = "Invalid verification code.";
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = $_POST['verification_code'] ?? '';
    $stmt = $conn->prepare("SELECT verification_code, code_expires_at FROM users WHERE user_id = ? AND is_verified = FALSE");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "No pending verification found.";
    } else {
        $user = $result->fetch_assoc();
        $stored_code = $user['verification_code'];
        $expires_at = $user['code_expires_at'];

        if (time() > strtotime($expires_at)) {
            $error = "Verification code has expired. Please request a new one.";
        } elseif ($entered_code === $stored_code) {
            $stmt = $conn->prepare("UPDATE users SET is_verified = TRUE, verification_code = NULL, code_expires_at = NULL WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $_SESSION['user_logged_in'] = true;
            unset($_SESSION['pending_verification']);
            header('Location: index.php');
            exit;
        } else {
            $error = "Invalid verification code.";
        }
    }
}

// Handle resend request
if (isset($_GET['resend']) && $_GET['resend'] === 'true') {
    if ($resend_available) {
        // Generate new verification code
        $new_code = sprintf('%06d', mt_rand(0, 999999));
        $expiration = date('Y-m-d H:i:s', time() + 600); // 10 minutes expiration
        
        // Update user record
        $stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expires_at = ?, verification_sent_at = NOW() WHERE user_id = ?");
        $stmt->bind_param('ssi', $new_code, $expiration, $user_id);
        
        if ($stmt->execute()) {
            // Reset timer
            $resend_available = false;
            $resend_time_left = 60;
            
            // Redirect to avoid resubmission - FIXED URL
            header('Location: verify-code.php?resend=success');
            exit;
        } else {
            $error = "Failed to resend the verification code. Please try again.";
        }
    } else {
        $error = "Please wait before requesting a new code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Email Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #6e8efb, #a777e3);
            border-radius: 20px 20px 0 0;
        }

        .logo {
            margin-bottom: 25px;
        }

        .logo i {
            font-size: 50px;
            color: #6e8efb;
            background: rgba(110, 142, 251, 0.1);
            width: 100px;
            height: 100px;
            line-height: 100px;
            border-radius: 50%;
            margin-bottom: 15px;
            display: inline-block;
        }

        h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .email-highlight {
            background: rgba(110, 142, 251, 0.1);
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            color: #6e8efb;
            word-break: break-all;
        }

        .input-group {
            margin-bottom: 25px;
            position: relative;
        }

        .code-inputs {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 15px;
        }

        .code-inputs input {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            outline: none;
            color: #333;
        }

        .code-inputs input:focus {
            border-color: #6e8efb;
            box-shadow: 0 0 0 3px rgba(110, 142, 251, 0.2);
            transform: translateY(-3px);
        }

        .code-inputs input.filled {
            border-color: #a777e3;
            background: #f9f5ff;
        }

        .error-message {
            background: #ffebee;
            color: #f44336;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s;
        }

        .error-message i {
            font-size: 18px;
        }

        .submit-btn {
            background: linear-gradient(90deg, #6e8efb, #a777e3);
            color: white;
            border: none;
            padding: 16px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 15px rgba(110, 142, 251, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(110, 142, 251, 0.6);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .resend-section {
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }

        .resend-link {
            color: #6e8efb;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            margin-left: 5px;
        }

        .resend-link:hover {
            color: #a777e3;
            text-decoration: underline;
        }
        
        .resend-link.disabled {
            color: #aaa;
            cursor: not-allowed;
            pointer-events: none;
            text-decoration: none;
        }

        .timer {
            color: #ff6b6b;
            font-weight: 600;
            margin-top: 5px;
        }

        .success-message {
            background: #e8f5e9;
            color: #4caf50;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            font-size: 18px;
        }

        .secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            color: #4caf50;
            font-size: 14px;
            font-weight: 500;
        }

        .secure-note i {
            font-size: 16px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .code-inputs input {
                width: 45px;
                height: 55px;
                font-size: 24px;
            }
            
            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h2>Verify Your Email</h2>
        <p class="subtitle">We've sent a 6-digit verification code to<br><span class="email-highlight"><?php echo htmlspecialchars($email); ?></span></p>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['resend']) && $_GET['resend'] === 'success'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span>A new verification code has been sent to your email.</span>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="verificationForm">
            <input type="hidden" name="verification_code" id="verificationCode">
            <div class="input-group">
                <div class="code-inputs">
                    <input type="text" maxlength="1" autofocus class="digit-input">
                    <input type="text" maxlength="1" class="digit-input">
                    <input type="text" maxlength="1" class="digit-input">
                    <input type="text" maxlength="1" class="digit-input">
                    <input type="text" maxlength="1" class="digit-input">
                    <input type="text" maxlength="1" class="digit-input">
                </div>
            </div>
            <button type="submit" class="submit-btn">Verify Email</button>
        </form>
        
        <div class="resend-section">
            <p>Didn't receive the code? 
                <?php if ($resend_available): ?>
                    <!-- FIXED RESEND URL -->
                    <a href="verify-code.php?resend=true" class="resend-link">Resend Code</a>
                <?php else: ?>
                    <span class="resend-link disabled">Resend Code</span>
                <?php endif; ?>
            </p>
            <?php if (!$resend_available): ?>
                <p class="timer">Resend available in <span id="countdown"><?php echo $resend_time_left; ?></span> seconds</p>
            <?php endif; ?>
        </div>
        
        <div class="secure-note">
            <i class="fas fa-lock"></i>
            <span>Your information is securely encrypted</span>
        </div>
    </div>

    <script>
        // Input field focus shifting
        const inputs = document.querySelectorAll('.digit-input');
        const hiddenInput = document.getElementById('verificationCode');
        
        inputs.forEach((input, index) => {
            // Auto-tab to next input when a digit is entered
            input.addEventListener('input', function() {
                if (this.value.length === 1) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    this.classList.add('filled');
                } else {
                    this.classList.remove('filled');
                }
                updateHiddenInput();
            });
            
            // Allow backspace to go to previous input
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });
        
        // Update hidden input with combined code
        function updateHiddenInput() {
            let code = '';
            inputs.forEach(input => {
                code += input.value;
            });
            hiddenInput.value = code;
        }
        
        // Auto-focus first input on page load
        window.addEventListener('load', () => {
            inputs[0].focus();
        });
        
        // Timer countdown
        <?php if (!$resend_available): ?>
            let seconds = <?php echo $resend_time_left; ?>;
            const countdownEl = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                seconds--;
                countdownEl.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(timer);
                    location.reload(); // Refresh to enable resend
                }
            }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>