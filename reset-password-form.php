<?php
session_start();
require 'includes/config.php';

// Set time zone explicitly
date_default_timezone_set('Africa/Nairobi');

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';
$is_valid_token = false;

if ($token) {
    try {
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_valid_token = $result->num_rows > 0;
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = "Server error. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        body {
            background: linear-gradient(90deg, #6e8efb, #a777e3); 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 400px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #6e8efb, #a777e3);
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(110, 142, 251, 0.4);
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
            display: none;
        }
        .success-message {
            color: #2ecc71;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>
        <?php if ($error): ?>
            <div class="error-message" style="display: block;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="success-message"></div>
        <div class="error-message"></div>
        <form id="reset-form" method="POST" action="reset-password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required <?php echo $is_valid_token ? '' : 'disabled'; ?>>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required <?php echo $is_valid_token ? '' : 'disabled'; ?>>
            </div>
            <button type="submit" <?php echo $is_valid_token ? '' : 'disabled'; ?>>Reset Password</button>
        </form>
    </div>

    <script>
        document.getElementById('reset-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const successMessage = document.querySelector('.success-message');
            const errorMessage = document.querySelector('.error-message');

            // Password complexity rules
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;

            // Client-side validation
            if (!passwordRegex.test(newPassword.value)) {
                errorMessage.textContent = "Password must be 8+ characters with uppercase, lowercase, number, and special character.";
                errorMessage.style.display = "block";
                setTimeout(() => { errorMessage.style.display = "none"; }, 3000);
                return;
            }

            if (newPassword.value !== confirmPassword.value) {
                errorMessage.textContent = "Passwords do not match!";
                errorMessage.style.display = "block";
                setTimeout(() => { errorMessage.style.display = "none"; }, 3000);
                return;
            }

            const formData = new FormData(this);

            try {
                const response = await fetch('reset-password.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.status === 'success') {
                    successMessage.textContent = "✅ Password reset successfully! You can now log in.";
                    successMessage.style.display = "block";
                    newPassword.value = "";
                    confirmPassword.value = "";
                    setTimeout(() => {
                        successMessage.style.display = "none";
                        window.location.href = 'HOP.php';
                    }, 3000);
                } else {
                    errorMessage.textContent = data.message || "Error resetting password.";
                    if (data.errors) {
                        errorMessage.textContent = Object.values(data.errors).join(', ');
                    }
                    errorMessage.style.display = "block";
                    setTimeout(() => { errorMessage.style.display = "none"; }, 3000);
                }
            } catch (error) {
                console.error('Error:', error);
                errorMessage.textContent = "Server error. Please try again later.";
                errorMessage.style.display = "block";
                setTimeout(() => { errorMessage.style.display = "none"; }, 3000);
            }
        });
    </script>
</body>
</html>