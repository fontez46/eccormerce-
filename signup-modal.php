<div class="modal-overlay" id="signupModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeSignupModal()">×</span>
        <form class="signup-form" action="signup.php" method="POST" onsubmit="return validateSignupForm()">
            <h2 class="form-title">Create Account</h2>
            
            <?php if (isset($_SESSION['errors']['general'])): ?>
                <div class="error-alert"><?= htmlspecialchars($_SESSION['errors']['general']) ?></div>
            <?php endif; ?>

            <!-- Full Name -->
            <div class="input-group">
                <input type="text" name="Full_Name" id="Full_Name" placeholder="Full Name" 
                       value="<?= htmlspecialchars($_SESSION['form_data']['Full_Name'] ?? '') ?>">
                <?php if (isset($_SESSION['errors']['Full_Name'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['Full_Name']) ?></span>
                <?php endif; ?>
                <i class="fas fa-user"></i>
            </div>
            
            <!-- Username -->
            <div class="input-group">
                <input type="text" name="username" id="username" placeholder="Username" 
                       value="<?= htmlspecialchars($_SESSION['form_data']['username'] ?? '') ?>">
                <?php if (isset($_SESSION['errors']['username'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['username']) ?></span>
                <?php endif; ?>
                <i class="fas fa-user"></i>
            </div>
            
            <!-- Email -->
            <div class="input-group">
                <input type="email" name="email" id="email" placeholder="Email Address"
                       value="<?= htmlspecialchars($_SESSION['form_data']['email'] ?? '') ?>">
                <?php if (isset($_SESSION['errors']['email'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['email']) ?></span>
                <?php endif; ?>
                <i class="fas fa-envelope"></i>
            </div>

            <!-- Password -->
            <div class="input-group">
                <input type="password" id="password" name="password" placeholder="Password">
                <?php if (isset($_SESSION['errors']['password'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['password']) ?></span>
                <?php endif; ?>
                <i class="fas fa-eye password-toggle" data-target="password" style="cursor: pointer;"></i>
            </div>

            <!-- Confirm Password -->
            <div class="input-group">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password">
                <?php if (isset($_SESSION['errors']['confirm_password'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['confirm_password']) ?></span>
                <?php endif; ?>
                <i class="fas fa-eye password-toggle" data-target="confirm_password" style="cursor: pointer;"></i>
            </div>

            <!-- Phone Number -->
            <div class="input-group">
                <input type="tel" name="phone_number" id="phone_number" placeholder="Phone Number"
                       value="<?= htmlspecialchars($_SESSION['form_data']['phone_number'] ?? '') ?>">
                <?php if (isset($_SESSION['errors']['phone_number'])): ?>
                    <span class="error-message"><?= htmlspecialchars($_SESSION['errors']['phone_number']) ?></span>
                <?php endif; ?>
                <i class="fas fa-phone"></i>
            </div>

            <button type="submit" class="submit-btn">Sign Up</button>

            <p class="auth-links">
                Forgot Password? <a href="forgot-password.php" id="forgotPassword">Reset Here</a>
            </p>
            <p class="auth-links">
                Already have an account? <a class="logintrigger" href="#" onclick="event.preventDefault(); openModal('login')">Login</a>
            </p>
            <div class="social-login">
                <button type="button" class="social-btn google">
                    <i class="fab fa-google"></i>
                </button>
                <button type="button" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['show_signup_modal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    openModal('signup');
    <?php unset($_SESSION['show_signup_modal']); ?>
});
</script>
<?php endif; ?>

<?php if (isset($_SESSION['show_signup_modal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    openModal('signup');
    <?php unset($_SESSION['show_signup_modal']); ?>
});
</script>
<?php endif; ?>
<div class="modal-overlay" id="loginModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeLoginModal()">×</span>
        <form class="login-form" method="POST" action="login.php">
            <h2 class="form-title">Login to Your Account</h2>
            
            <!-- Error Messages -->
            <?php if (isset($_SESSION['errors']['login'])): ?>
                <div class="error-message"><?= htmlspecialchars($_SESSION['errors']['login']) ?></div>
            <?php endif; ?>

            <div class="input-group">
                <label for="loginUsername">Username or Email</label>
                <input type="text" name="username" id="loginUsername" placeholder="Username/Email" required>
            </div>

            <div class="input-group">
                <label for="loginPassword">Password</label>
                <div class="password-input">
                    <input type="password" id="loginPassword" name="password" placeholder="Enter Password" required>
                    <i class="fas fa-eye password-toggle" data-target="loginPassword" style="cursor: pointer;"></i>
                </div>
            </div>

            <button type="submit" class="submit-btn">Login</button>

            <div class="auth-links">
                Don't have an account? <a href="#" id="signupTrigger" onclick="event.preventDefault(); openModal('signup')">Sign Up</a>
            </div>
            <div class="auth-links">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
            <div class="social-login">
                <button type="button" class="social-btn google">
                    <i class="fab fa-google"></i>
                </button>
                <button type="button" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i>
                </button>
            </div>
        </form>
    </div>
</div>