<?php 
// Generate CSRF token if not set 
if (empty($_SESSION['csrf_token'])) { 
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
} 

// Fetch order count for badge (only if logged in)
$order_count = 0;
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($order_count);
    $stmt->fetch();
    $stmt->close();
}
?>

<div class="account-container">
    <button class="account-button" onclick="toggleMenu()">
        <i class="fas fa-user-circle"></i>
        <span class="account-text"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'My Account'; ?></span>
        <i class="fas fa-chevron-down" id="dropdownIcon"></i>
    </button>
    
    <div class="account-menu" id="accountMenu">
        <?php if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']): ?>
            <div class="menu-section">
                <a class="menu-item" href="#" onclick="event.preventDefault(); openModal('login')">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a class="menu-item" href="#" onclick="event.preventDefault(); openModal('signup')">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Account</span>
                </a>
            </div>
        <?php else: ?>
            <div class="menu-header">
                <div class="welcome-message">Welcome back!</div>
                <div class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            </div>
            
            
                       
            <div class="menu-section">
                <div class="menu-title">My Shopping</div>
                <a href="profile.php#orders" class="menu-item">
                    <i class="fas fa-shopping-bag"></i>
                    <span>My Orders</span>
                    <?php if ($order_count > 0): ?>
                        <span class="order-count"><?= $order_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="profile.php#orders" class="menu-item">
                <i class="fas fa-truck"></i>
                <span>Track Shipment</span>

            </a>
            </div>
            <div class="menu-section">
                <div class="menu-title">My Account</div>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user-cog"></i>
                    <span>Account</span>
                </a>
                      <a href="profile.php#addresses" class="menu-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Saved Addresses</span>
                </a>
            </div>

            
            <div class="menu-section">
                <div class="menu-title">Support</div>
                <a href="contact.html" class="menu-item">
                    <i class="fas fa-headset"></i>
                    <span>Help Center</span>
                </a>
            </div>
            
            <div class="menu-footer">
                <a href="logout.php" class="menu-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log Out</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- Signup Modal -->
<div class="modal-overlay" id="signupModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('signup')">×</span>
        <form class="signup-form" id="signupForm" action="signup.php" method="POST">
            <div class="error-alert" id="signupError" style="display: none; color: #e74c3c; margin-bottom: 15px;"></div>
            <h2 class="form-title">Create Account</h2>
                        
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="input-group">
                <input type="text" name="Full_Name" id="Full_Name" placeholder="Full Name" required>
                <div class="error" id="error-Full_Name" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-user"></i>
            </div>
            
            <div class="input-group">
                <input type="text" name="username" id="username" placeholder="Username" required>
                 <div class="error" id="error-username" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-user"></i>
            </div>
            
            <div class="input-group">
                <input type="email" name="email" id="email" placeholder="Email Address" required>
                 <div class="error" id="error-email" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-envelope"></i>
            </div>
            
            <div class="input-group">
                <input type="password" id="password" name="password" placeholder="Password" required>
                 <div class="error" id="error-password" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-eye password-toggle" data-target="password"></i>
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            
            <div class="input-group">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                 <div class="error" id="error-confirm_password" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-eye password-toggle" data-target="confirm_password"></i>
            </div>
            
            <div class="input-group">
                <input type="tel" name="phone_number" id="phone_number" placeholder="Phone Number" required>
                <div class="error" id="error-phone_number" style="display: none; color: #e74c3c;"></div>
                <i class="fas fa-phone"></i>
            </div>
            
            <button type="submit" class="submit-btn">Sign Up</button>
            
            <p class="auth-links">
                Already have an account? <a href="#" onclick="event.preventDefault(); switchModal('signup', 'login')">Login</a>
            </p>
            <p class="auth-links">
                Forgot Password? <a href="#" onclick="event.preventDefault(); switchModal('signup', 'forgot-password')">Reset Here</a>
            </p>
            <div class="social-login">
                <button type="button" class="social-btn google" onclick="handleSocialLogin('google')">
                    <i class="fab fa-google"></i>
                </button>
                <button type="button" class="social-btn facebook" onclick="handleSocialLogin('facebook')">
                    <i class="fab fa-facebook-f"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Login Modal -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('login')">×</span>
        <form class="login-form" id="loginForm" action="login.php" method="POST">
            <h2 class="form-title">Login to Your Account</h2>
            <div class="error-alert" id="loginError" style="display: none;"></div>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="input-group">
                <input type="text" name="username" id="loginUsername" placeholder="Username or Email" required>
                <i class="fas fa-user"></i>
            </div>
            <div class="input-group">
                <input type="password" id="loginPassword" name="password" placeholder="Password" required>
                <i class="fas fa-eye password-toggle" data-target="loginPassword"></i>
            </div>
            <button type="submit" class="submit-btn">Login</button>
            <div class="auth-links">
                Don't have an account? <a href="#" onclick="event.preventDefault(); switchModal('login', 'signup')">Sign Up</a>
            </div>
            <div class="auth-links">
                <a href="#" onclick="event.preventDefault(); switchModal('login', 'forgot-password')">Forgot Password?</a>
            </div>
            <div class="social-login">
                <button type="button" class="social-btn google" onclick="handleSocialLogin('google')">
                    <i class="fab fa-google"></i>
                </button>
                <button type="button" class="social-btn facebook" onclick="handleSocialLogin('facebook')">
                    <i class="fab fa-facebook-f"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal-overlay" id="forgot-passwordModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('forgot-password')">×</span>
        <div class="logo">
            <i class="fas fa-key"></i>
        </div>
        <h2 class="form-title">Reset Your Password</h2>
        <p class="subtitle">Enter your email to receive a password reset link</p>
        
        <div id="forgot-passwordError" class="error-alert" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="errorMessage"></span>
        </div>
        
        <div id="forgot-passwordSuccess" class="success-alert" style="display: none;">
            <i class="fas fa-check-circle"></i>
            <span id="successMessage"></span>
        </div>
        
        <form class="forgotPasswordForm" id="forgotPasswordForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="input-group">
                <input type="email" id="forgot-email" name="email" placeholder="Enter your email" required>
                <i class="fas fa-envelope"></i>
            </div>
            <button type="submit" class="submit-btn" id="forgotSubmitBtn">Send Reset Link</button>
        </form>
        
        <div class="auth-linkss">
            <p>Remembered your password? <a href="#" onclick="event.preventDefault(); switchModal('forgot-password', 'login')">Login</a></p>
        </div>
        
        <div class="secure-note">
            <i class="fas fa-lock"></i>
            <span>Your information is securely encrypted</span>
        </div>
    </div>
</div>

<style>
    /* Additional styles for Forgot Password modal */
    .logo {
        margin-bottom: 25px;
        text-align: center;
    }
    
    .logo i {
        font-size: 50px;
        color: #6e8efb;
        background: rgba(110, 142, 251, 0.1);
        width: 70px;
        height: 70px;
        line-height: 70px;
        border-radius: 50%;
        margin-bottom: 15px;
        display: inline-block;
    }
    
    .subtitle {
        color: #666;
        font-size: 16px;
        margin-bottom: 30px;
        line-height: 1.6;
        text-align: center;
    }
    
    .success-alert {
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
    
    .success-alert i {
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
    
    /* Loading spinner */
    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
        margin-right: 10px;
        vertical-align: middle;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<script>
    // Add to existing modal functions
    function switchModal(fromModal, toModal) {
        closeModal(fromModal);
        openModal(toModal);
    }
    
    // Forgot password form submission with AJAX
    document.getElementById('forgotPasswordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const email = document.getElementById('forgot-email').value;
        const errorDiv = document.getElementById('forgot-passwordError');
        const successDiv = document.getElementById('forgot-passwordSuccess');
        const submitBtn = document.getElementById('forgotSubmitBtn');
        
        // Reset messages
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
        
        // Validate email
        if (!email) {
            showForgotError('Please enter your email address');
            return;
        }
        
        if (!validateEmail(email)) {
            showForgotError('Please enter a valid email address');
            return;
        }
        
        // Show loading state
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner"></span> Sending...';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData(form);
            
            const response = await fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Show success message
                document.getElementById('successMessage').textContent = data.message;
                successDiv.style.display = 'flex';
                
                // Clear form
                form.reset();
                
                // Auto-switch to login modal after delay
                setTimeout(() => {
                    closeModal('forgot-password');
                    openModal('login');
                }, 100000);
            } else {
                // Show error message
                showForgotError(data.message || 'An error occurred. Please try again.');
            }
        } catch (error) {
            showForgotError('Network error. Please try again.');
        } finally {
            // Restore button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }
    });
    
    function showForgotError(message) {
        const errorDiv = document.getElementById('forgot-passwordError');
        document.getElementById('errorMessage').textContent = message;
        errorDiv.style.display = 'flex';
    }
    
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Real-time email validation
    document.getElementById('forgot-email')?.addEventListener('input', function() {
        const errorDiv = document.getElementById('forgot-passwordError');
        const email = this.value;
        
        if (email && !validateEmail(email)) {
            showForgotError('Please enter a valid email address');
        } else {
            errorDiv.style.display = 'none';
        }
    });
</script>