<?php
// includes/auth_modal.php
$googleClientId = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com'; // Get from Google Console
$googleRedirectUri = BASE_URL . 'api/google_callback.php';
?>
<!-- Authentication Modal -->
<div class="auth-modal" id="authModal">
    <div class="auth-modal-overlay" onclick="closeAuthModal()"></div>
    <div class="auth-modal-content">
        <!-- Close Button -->
        <button class="auth-modal-close" onclick="closeAuthModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Modal Header -->
        <div class="auth-modal-header">
            <div class="logo">
                <i class="fas fa-bus"></i>
                <span>MORI BOOKINGS</span>
            </div>
            <p>Welcome to Kenya's Premier Bus Booking System</p>
        </div>
        
        <!-- Tabs -->
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button class="auth-tab" data-tab="register">
                <i class="fas fa-user-plus"></i> Register
            </button>
            <button class="auth-tab" data-tab="reset">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </div>
        
        <!-- Tab Content -->
        <div class="auth-tab-content">
            
            <!-- Login Form -->
            <div class="tab-pane active" id="login-tab">
                <div class="auth-social-login">
                    <h4>Login with</h4>
                    <div class="social-buttons">
                        <button class="social-btn google-btn" onclick="loginWithGoogle()">
                            <i class="fab fa-google"></i>
                            <span>Google</span>
                        </button>
                        <!-- More social buttons can be added here -->
                    </div>
                    <div class="divider">
                        <span>or use email</span>
                    </div>
                </div>
                
                <form id="loginForm" class="auth-form">
                    <div class="form-group">
                        <label for="loginEmail">
                            <i class="fas fa-envelope"></i> Email or Phone
                        </label>
                        <input type="text" id="loginEmail" name="email" 
                               placeholder="Enter your email or phone number" required>
                        <div class="error-message" id="loginEmailError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="loginPassword" name="password" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('loginPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="loginPasswordError"></div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="rememberMe">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="#" class="forgot-password" onclick="switchTab('reset')">
                            Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Login
                        <span class="spinner" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? 
                            <a href="#" onclick="switchTab('register')">Sign up here</a>
                        </p>
                    </div>
                </form>
            </div>
            
            <!-- Register Form -->
            <div class="tab-pane" id="register-tab">
                <div class="auth-social-login">
                    <h4>Sign up with</h4>
                    <div class="social-buttons">
                        <button class="social-btn google-btn" onclick="registerWithGoogle()">
                            <i class="fab fa-google"></i>
                            <span>Google</span>
                        </button>
                    </div>
                    <div class="divider">
                        <span>or use email</span>
                    </div>
                </div>
                
                <form id="registerForm" class="auth-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <input type="text" id="firstName" name="first_name" 
                                   placeholder="Enter your first name" required>
                            <div class="error-message" id="firstNameError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <input type="text" id="lastName" name="last_name" 
                                   placeholder="Enter your last name" required>
                            <div class="error-message" id="lastNameError"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="registerEmail">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="registerEmail" name="email" 
                               placeholder="Enter your email address" required>
                        <div class="error-message" id="registerEmailError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone" 
                               placeholder="e.g., 0712345678 or +254712345678" required>
                        <div class="error-message" id="phoneError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="idNumber">
                            <i class="fas fa-id-card"></i> ID Number (Optional)
                        </label>
                        <input type="text" id="idNumber" name="id_number" 
                               placeholder="National ID number">
                        <div class="error-message" id="idNumberError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="registerPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="registerPassword" name="password" 
                                   placeholder="Create a strong password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('registerPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="passwordStrength"></div>
                            </div>
                            <span class="strength-text" id="strengthText">Password strength</span>
                        </div>
                        <div class="password-requirements">
                            <p><i class="fas fa-info-circle"></i> Must contain:</p>
                            <ul>
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-uppercase">One uppercase letter</li>
                                <li id="req-lowercase">One lowercase letter</li>
                                <li id="req-number">One number</li>
                                <li id="req-special">One special character</li>
                            </ul>
                        </div>
                        <div class="error-message" id="registerPasswordError"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="confirmPassword" name="confirm_password" 
                                   placeholder="Confirm your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="error-message" id="confirmPasswordError"></div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span class="checkmark"></span>
                            I agree to the 
                            <a href="terms.php" target="_blank">Terms of Service</a> 
                            and 
                            <a href="privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="newsletter" id="newsletter">
                            <span class="checkmark"></span>
                            Subscribe to our newsletter for updates and offers
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="registerBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                        <span class="spinner" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                    
                    <div class="auth-footer">
                        <p>Already have an account? 
                            <a href="#" onclick="switchTab('login')">Login here</a>
                        </p>
                    </div>
                </form>
            </div>
            
            <!-- Reset Password Form -->
            <div class="tab-pane" id="reset-tab">
                <div class="reset-header">
                    <h4><i class="fas fa-key"></i> Reset Your Password</h4>
                    <p>Enter your email address and we'll send you a link to reset your password.</p>
                </div>
                
                <form id="resetForm" class="auth-form">
                    <div class="form-group">
                        <label for="resetEmail">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="resetEmail" name="email" 
                               placeholder="Enter your email address" required>
                        <div class="error-message" id="resetEmailError"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="resetBtn">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                        <span class="spinner" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                    
                    <div class="reset-footer">
                        <p><i class="fas fa-lightbulb"></i> Remember your password? 
                            <a href="#" onclick="switchTab('login')">Back to login</a>
                        </p>
                    </div>
                </form>
                
                <div id="resetSuccess" style="display: none;">
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h4>Reset Link Sent!</h4>
                        <p>We've sent a password reset link to your email. Please check your inbox and follow the instructions.</p>
                        <button class="btn btn-outline btn-block" onclick="switchTab('login')">
                            Back to Login
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trust Indicators -->
        <div class="trust-indicators">
            <div class="trust-item">
                <i class="fas fa-shield-alt"></i>
                <span>Secure & Encrypted</span>
            </div>
            <div class="trust-item">
                <i class="fas fa-lock"></i>
                <span>Privacy Protected</span>
            </div>
            <div class="trust-item">
                <i class="fas fa-headset"></i>
                <span>24/7 Support</span>
            </div>
        </div>
    </div>
</div>

<!-- Google OAuth Script -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
    // Google OAuth Configuration
    const GOOGLE_CLIENT_ID = '<?php echo $googleClientId; ?>';
    
    // Initialize Google OAuth
    function initializeGoogleAuth() {
        google.accounts.id.initialize({
            client_id: GOOGLE_CLIENT_ID,
            callback: handleGoogleResponse,
            auto_select: false,
            cancel_on_tap_outside: true,
            context: 'signin',
            ux_mode: 'popup',
            login_uri: '<?php echo $googleRedirectUri; ?>'
        });
        
        // Render Google Sign In button
        google.accounts.id.renderButton(
            document.getElementById('googleSignIn'),
            {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'signin_with',
                shape: 'rectangular',
                logo_alignment: 'left',
                width: '300'
            }
        );
    }
    
    // Load Google OAuth when needed
    function loadGoogleAuth() {
        if (typeof google !== 'undefined' && google.accounts) {
            initializeGoogleAuth();
        } else {
            setTimeout(loadGoogleAuth, 100);
        }
    }
</script>