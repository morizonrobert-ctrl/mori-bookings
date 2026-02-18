<?php
// includes/init.php - Main initialization file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 0,
        'username' => 'guest',
        'role' => 'guest',
        'display_name' => 'Guest',
        'is_guest' => true
    ];
}
// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Load configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
//require_once __DIR__ . '/../config/email_config.php';
//require_once __DIR__ . '/../config/mpesa_config.php';
//require_once __DIR__ . '/../config/google_auth.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Mori\\';
    $base_dir = __DIR__ . '/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Helper functions
require_once __DIR__ . '/functions.php';

// Database connection (lazy loaded)
use Mori\Database;

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
    }
}

// Define base URL
if (!defined('BASE_URL')) {
    define('BASE_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'], 2) . '/');
}