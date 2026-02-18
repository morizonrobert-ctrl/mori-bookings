<?php
require_once 'includes/init.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $user = new Mori\User();
        try {
            $result = $user->login($email, $password);
            if ($result) {
                $_SESSION['user_id'] = $result['user']['id'];
                
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60);
                    setcookie('remember_token', $token, $expires, '/', '', true, true);
                    
                    $db = Mori\Database::getInstance()->getConnection();
                    $db->prepare("UPDATE users SET remember_token = ?, remember_expires = ? WHERE id = ?")
                       ->execute([$token, date('Y-m-d H:i:s', $expires), $result['user']['id']]);
                }
                
                if (!empty($redirect)) {
                    redirect($redirect);
                } else {
                    redirect(getRedirectUrl($result['user']['role']));
                }
            } else {
                $error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

function getRedirectUrl($role) {
    switch ($role) {
        case 'super_admin':
        case 'admin':
            return 'admin/dashboard.php';
        case 'operator':
            return 'admin/bookings.php';
        case 'driver':
            return 'driver/dashboard.php';
        default:
            return 'customer/dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MORI BOOKINGS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="auth-container" style="max-width: 400px; margin: 50px auto;">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                    <p>Welcome back! Please login to continue.</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email or Phone</label>
                        <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="toggle-password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="reset-password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? <a href="register.php">Sign up</a></p>
                    </div>
                    
                    <div class="divider"><span>or</span></div>
                    
                    <button type="button" class="social-btn google-btn" onclick="window.location.href='<?php echo getGoogleAuthUrl(); ?>'">
                        <i class="fab fa-google"></i> Continue with Google
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
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