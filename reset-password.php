<?php
// reset-password.php
require_once 'includes/init.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: index.php?error=Invalid reset token');
    exit;
}

// Check if token is valid
$db = \Mori\Database::getInstance();
$sql = "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()";
$user = $db->fetch($sql, [$token]);

if (!$user) {
    header('Location: index.php?error=Invalid or expired reset token');
    exit;
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Both password fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $userClass = new \Mori\User();
        try {
            $userClass->resetPassword($token, $password);
            header('Location: index.php?success=Password reset successful. Please login.');
            exit;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MORI BOOKINGS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><i class="fas fa-key"></i> Reset Your Password</h2>
                    <p>Create a new password for your account</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" 
                                   placeholder="Enter new password" required>
                            <button type="button" class="toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            <p><i class="fas fa-info-circle"></i> Must be at least 8 characters long</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required>
                            <button type="button" class="toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                    
                    <div class="auth-footer">
                        <p>Remember your password? <a href="index.php">Back to login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    </script>
</body>
</html>