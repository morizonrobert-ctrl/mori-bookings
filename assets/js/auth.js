/**
 * MORI BOOKINGS - Authentication System
 * Handles login, registration, password reset with Google OAuth
 */

class AuthSystem {
    constructor() {
        this.modal = null;
        this.currentTab = 'login';
        this.forms = {};
        this.init();
    }
    
    init() {
        this.createModal();
        this.bindEvents();
        this.loadGoogleAuth();
    }
    
    createModal() {
        // Create modal HTML
        const modalHTML = `
            <!-- Authentication Modal -->
            <div class="auth-modal" id="authModalInstance">
                <div class="auth-modal-overlay" onclick="authSystem.close()"></div>
                <div class="auth-modal-content">
                    <!-- Close Button -->
                    <button class="auth-modal-close" onclick="authSystem.close()">
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
                            ${this.getLoginForm()}
                        </div>
                        
                        <!-- Register Form -->
                        <div class="tab-pane" id="register-tab">
                            ${this.getRegisterForm()}
                        </div>
                        
                        <!-- Reset Password Form -->
                        <div class="tab-pane" id="reset-tab">
                            ${this.getResetForm()}
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
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('authModalInstance');
        
        // Initialize forms
        this.forms.login = document.getElementById('loginForm');
        this.forms.register = document.getElementById('registerForm');
        this.forms.reset = document.getElementById('resetForm');
    }
    
    getLoginForm() {
        return `
            <div class="auth-social-login">
                <h4>Login with</h4>
                <div class="social-buttons">
                    <button type="button" class="social-btn google-btn" onclick="authSystem.loginWithGoogle()">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </button>
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
                        <button type="button" class="toggle-password" onclick="authSystem.togglePassword('loginPassword')">
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
                    <a href="#" class="forgot-password" onclick="authSystem.switchTab('reset')">
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
                        <a href="#" onclick="authSystem.switchTab('register')">Sign up here</a>
                    </p>
                </div>
            </form>
        `;
    }
    
    getRegisterForm() {
        return `
            <div class="auth-social-login">
                <h4>Sign up with</h4>
                <div class="social-buttons">
                    <button type="button" class="social-btn google-btn" onclick="authSystem.registerWithGoogle()">
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
                               placeholder="Create a strong password" required
                               oninput="authSystem.checkPasswordStrength(this.value)">
                        <button type="button" class="toggle-password" onclick="authSystem.togglePassword('registerPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="passwordStrength"></div>
                        </div>
                        <span class="strength-text" id="strengthText">Password strength</span>
                    </div>
                    <div class="password-requirements" id="passwordRequirements" style="display: none;">
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
                               placeholder="Confirm your password" required
                               oninput="authSystem.validatePasswordMatch()">
                        <button type="button" class="toggle-password" onclick="authSystem.togglePassword('confirmPassword')">
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
                        <a href="#" onclick="authSystem.switchTab('login')">Login here</a>
                    </p>
                </div>
            </form>
        `;
    }
    
    getResetForm() {
        return `
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
                        <a href="#" onclick="authSystem.switchTab('login')">Back to login</a>
                    </p>
                </div>
            </form>
            
            <div id="resetSuccess" style="display: none;">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h4>Reset Link Sent!</h4>
                    <p>We've sent a password reset link to your email. Please check your inbox and follow the instructions.</p>
                    <button class="btn btn-outline btn-block" onclick="authSystem.switchTab('login')">
                        Back to Login
                    </button>
                </div>
            </div>
        `;
    }
    
    bindEvents() {
        // Tab switching
        document.addEventListener('click', (e) => {
            if (e.target.closest('.auth-tab')) {
                const tab = e.target.closest('.auth-tab');
                const tabName = tab.dataset.tab;
                this.switchTab(tabName);
            }
        });
        
        // Form submissions
        document.addEventListener('submit', async (e) => {
            if (e.target.id === 'loginForm') {
                e.preventDefault();
                await this.handleLogin();
            } else if (e.target.id === 'registerForm') {
                e.preventDefault();
                await this.handleRegister();
            } else if (e.target.id === 'resetForm') {
                e.preventDefault();
                await this.handlePasswordReset();
            }
        });
        
        // Real-time validation
        document.addEventListener('input', (e) => {
            if (e.target.id === 'registerEmail') {
                this.validateEmail(e.target.value, 'registerEmailError');
            } else if (e.target.id === 'phone') {
                this.validatePhone(e.target.value);
            }
        });
    }
    
    open(tab = 'login') {
        this.modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        this.switchTab(tab);
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = this.modal.querySelector('.tab-pane.active input');
            if (firstInput) firstInput.focus();
        }, 300);
    }
    
    close() {
        this.modal.style.display = 'none';
        document.body.style.overflow = '';
        this.clearForms();
    }
    
    switchTab(tabName) {
        this.currentTab = tabName;
        
        // Update active tab
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            }
        });
        
        // Show active content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');
        
        // Reset forms when switching
        this.clearErrors();
        
        // Hide reset success message
        document.getElementById('resetSuccess').style.display = 'none';
        document.getElementById('resetForm').style.display = 'block';
    }
    
    async handleLogin() {
        const form = this.forms.login;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Validate inputs
        if (!this.validateLogin(data)) {
            return;
        }
        
        // Show loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner');
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        
        try {
            const response = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                this.showSuccess('Login successful! Redirecting...');
                
                // Store token if available
                if (result.token) {
                    localStorage.setItem('auth_token', result.token);
                    if (document.getElementById('rememberMe').checked) {
                        localStorage.setItem('remember_me', 'true');
                    }
                }
                
                // Redirect or reload
                setTimeout(() => {
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
                
            } else {
                // Show error
                this.showError('loginEmailError', result.message || 'Login failed');
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showError('loginEmailError', 'Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = 'none';
        }
    }
    
    async handleRegister() {
        const form = this.forms.register;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Validate inputs
        if (!this.validateRegister(data)) {
            return;
        }
        
        // Show loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner');
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        
        try {
            const response = await fetch('api/auth.php?action=register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                this.showSuccess('Registration successful! Please check your email for verification.');
                
                // Switch to login tab after delay
                setTimeout(() => {
                    this.switchTab('login');
                    this.showSuccess('Please login with your new account.');
                }, 3000);
                
            } else {
                // Show errors
                if (result.errors) {
                    for (const [field, message] of Object.entries(result.errors)) {
                        this.showError(`${field}Error`, message);
                    }
                } else {
                    this.showError('registerEmailError', result.message || 'Registration failed');
                }
            }
            
        } catch (error) {
            console.error('Registration error:', error);
            this.showError('registerEmailError', 'Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = 'none';
        }
    }
    
    async handlePasswordReset() {
        const form = this.forms.reset;
        const email = document.getElementById('resetEmail').value;
        
        // Validate email
        if (!this.validateEmail(email, 'resetEmailError')) {
            return;
        }
        
        // Show loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner');
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        
        try {
            const response = await fetch('api/auth.php?action=forgot_password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                form.style.display = 'none';
                document.getElementById('resetSuccess').style.display = 'block';
            } else {
                this.showError('resetEmailError', result.message || 'Failed to send reset link');
            }
            
        } catch (error) {
            console.error('Password reset error:', error);
            this.showError('resetEmailError', 'Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = 'none';
        }
    }
    
    validateLogin(data) {
        this.clearErrors(['loginEmailError', 'loginPasswordError']);
        
        let isValid = true;
        
        if (!data.email || data.email.trim() === '') {
            this.showError('loginEmailError', 'Email or phone is required');
            isValid = false;
        }
        
        if (!data.password || data.password.trim() === '') {
            this.showError('loginPasswordError', 'Password is required');
            isValid = false;
        }
        
        return isValid;
    }
    
    validateRegister(data) {
        this.clearErrors([
            'firstNameError', 'lastNameError', 'registerEmailError',
            'phoneError', 'registerPasswordError', 'confirmPasswordError'
        ]);
        
        let isValid = true;
        
        // First name
        if (!data.first_name || data.first_name.trim() === '') {
            this.showError('firstNameError', 'First name is required');
            isValid = false;
        }
        
        // Last name
        if (!data.last_name || data.last_name.trim() === '') {
            this.showError('lastNameError', 'Last name is required');
            isValid = false;
        }
        
        // Email
        if (!this.validateEmail(data.email, 'registerEmailError')) {
            isValid = false;
        }
        
        // Phone
        if (!this.validatePhone(data.phone)) {
            isValid = false;
        }
        
        // Password
        if (!this.validatePassword(data.password)) {
            isValid = false;
        }
        
        // Confirm password
        if (data.password !== data.confirm_password) {
            this.showError('confirmPasswordError', 'Passwords do not match');
            isValid = false;
        }
        
        // Terms agreement
        if (!data.terms) {
            this.showError('registerEmailError', 'You must agree to the terms and conditions');
            isValid = false;
        }
        
        return isValid;
    }
    
    validateEmail(email, errorElementId) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email || email.trim() === '') {
            this.showError(errorElementId, 'Email is required');
            return false;
        }
        
        if (!emailRegex.test(email)) {
            this.showError(errorElementId, 'Please enter a valid email address');
            return false;
        }
        
        this.hideError(errorElementId);
        return true;
    }
    
    validatePhone(phone) {
        const phoneRegex = /^(\+254|0)[17]\d{8}$/;
        
        if (!phone || phone.trim() === '') {
            this.showError('phoneError', 'Phone number is required');
            return false;
        }
        
        // Clean phone number
        const cleanPhone = phone.replace(/\s+/g, '').replace(/^0/, '254');
        
        if (!phoneRegex.test(cleanPhone) && !phoneRegex.test(phone)) {
            this.showError('phoneError', 'Please enter a valid Kenyan phone number (07XXXXXXXX or +2547XXXXXXXX)');
            return false;
        }
        
        this.hideError('phoneError');
        return true;
    }
    
    validatePassword(password) {
        if (!password || password.length < 8) {
            this.showError('registerPasswordError', 'Password must be at least 8 characters long');
            return false;
        }
        
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        if (!hasUpperCase || !hasLowerCase || !hasNumbers || !hasSpecialChar) {
            this.showError('registerPasswordError', 'Password must contain uppercase, lowercase, number, and special character');
            return false;
        }
        
        this.hideError('registerPasswordError');
        return true;
    }
    
    checkPasswordStrength(password) {
        const requirements = document.getElementById('passwordRequirements');
        const strengthFill = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('strengthText');
        
        if (!password) {
            requirements.style.display = 'none';
            strengthFill.className = 'strength-fill';
            strengthFill.style.width = '0%';
            strengthText.textContent = 'Password strength';
            return;
        }
        
        // Show requirements
        requirements.style.display = 'block';
        
        // Check requirements
        const requirementsList = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        // Update requirement indicators
        for (const [req, met] of Object.entries(requirementsList)) {
            const element = document.getElementById(`req-${req}`);
            if (element) {
                element.className = met ? 'valid' : 'invalid';
            }
        }
        
        // Calculate strength score
        let score = 0;
        if (requirementsList.length) score += 20;
        if (requirementsList.uppercase) score += 20;
        if (requirementsList.lowercase) score += 20;
        if (requirementsList.number) score += 20;
        if (requirementsList.special) score += 20;
        
        // Update strength meter
        strengthFill.style.width = `${score}%`;
        
        if (score < 40) {
            strengthFill.className = 'strength-fill weak';
            strengthText.textContent = 'Weak';
            strengthText.style.color = '#f44336';
        } else if (score < 60) {
            strengthFill.className = 'strength-fill fair';
            strengthText.textContent = 'Fair';
            strengthText.style.color = '#FF9800';
        } else if (score < 80) {
            strengthFill.className = 'strength-fill good';
            strengthText.textContent = 'Good';
            strengthText.style.color = '#4CAF50';
        } else {
            strengthFill.className = 'strength-fill strong';
            strengthText.textContent = 'Strong';
            strengthText.style.color = '#2E7D32';
        }
    }
    
    validatePasswordMatch() {
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const errorElement = document.getElementById('confirmPasswordError');
        
        if (confirmPassword && password !== confirmPassword) {
            this.showError('confirmPasswordError', 'Passwords do not match');
            return false;
        }
        
        this.hideError('confirmPasswordError');
        return true;
    }
    
    async loginWithGoogle() {
        try {
            // Initialize Google OAuth
            if (typeof google === 'undefined') {
                this.showError('loginEmailError', 'Google login service is not available');
                return;
            }
            
            google.accounts.id.initialize({
                client_id: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
                callback: this.handleGoogleResponse.bind(this),
                context: 'signin'
            });
            
            google.accounts.id.prompt();
            
        } catch (error) {
            console.error('Google login error:', error);
            this.showError('loginEmailError', 'Google login failed. Please try again.');
        }
    }
    
    async registerWithGoogle() {
        // Same as login for now - Google handles both
        this.loginWithGoogle();
    }
    
    async handleGoogleResponse(response) {
        try {
            // Send credential to server
            const result = await fetch('api/google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ credential: response.credential })
            });
            
            const data = await result.json();
            
            if (data.success) {
                this.showSuccess('Google login successful! Redirecting...');
                
                // Store token
                if (data.token) {
                    localStorage.setItem('auth_token', data.token);
                }
                
                // Redirect
                setTimeout(() => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 1000);
                
            } else {
                this.showError('loginEmailError', data.message || 'Google login failed');
            }
            
        } catch (error) {
            console.error('Google auth error:', error);
            this.showError('loginEmailError', 'Google authentication failed');
        }
    }
    
    loadGoogleAuth() {
        // Load Google OAuth script if not already loaded
        if (!document.querySelector('script[src*="accounts.google.com"]')) {
            const script = document.createElement('script');
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
    }
    
    togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.parentNode.querySelector('.toggle-password i');
        
        if (input.type === 'password') {
            input.type = 'text';
            button.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            button.className = 'fas fa-eye';
        }
    }
    
    showError(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.classList.add('show');
            
            // Add error class to input
            const input = element.parentNode.querySelector('input');
            if (input) {
                input.classList.add('error');
            }
        }
    }
    
    hideError(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.remove('show');
            
            // Remove error class from input
            const input = element.parentNode.querySelector('input');
            if (input) {
                input.classList.remove('error');
            }
        }
    }
    
    clearErrors(elementIds = null) {
        if (elementIds) {
            elementIds.forEach(id => this.hideError(id));
        } else {
            document.querySelectorAll('.error-message').forEach(el => {
                el.classList.remove('show');
            });
            document.querySelectorAll('input.error').forEach(input => {
                input.classList.remove('error');
            });
        }
    }
    
    showSuccess(message) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'auth-toast success';
        toast.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
    
    clearForms() {
        // Clear all form inputs
        document.querySelectorAll('#authModalInstance input').forEach(input => {
            input.value = '';
            input.classList.remove('error', 'success');
        });
        
        // Clear errors
        this.clearErrors();
        
        // Reset password strength
        document.getElementById('passwordRequirements').style.display = 'none';
        document.getElementById('passwordStrength').style.width = '0%';
        document.getElementById('strengthText').textContent = 'Password strength';
        document.getElementById('strengthText').style.color = '';
    }
}

// Initialize auth system
let authSystem;

document.addEventListener('DOMContentLoaded', () => {
    authSystem = new AuthSystem();
    
    // Add toast styles
    const style = document.createElement('style');
    style.textContent = `
        .auth-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .auth-toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .auth-toast i {
            font-size: 1.2rem;
        }
        
        .auth-toast.success {
            background: #4CAF50;
        }
        
        .auth-toast.error {
            background: #f44336;
        }
    `;
    document.head.appendChild(style);
});

// Global functions for HTML onclick handlers
window.openAuthModal = function(tab = 'login') {
    if (authSystem) authSystem.open(tab);
};

window.closeAuthModal = function() {
    if (authSystem) authSystem.close();
};

window.switchTab = function(tab) {
    if (authSystem) authSystem.switchTab(tab);
};

window.togglePassword = function(inputId) {
    if (authSystem) authSystem.togglePassword(inputId);
};

window.loginWithGoogle = function() {
    if (authSystem) authSystem.loginWithGoogle();
};

window.registerWithGoogle = function() {
    if (authSystem) authSystem.registerWithGoogle();
};