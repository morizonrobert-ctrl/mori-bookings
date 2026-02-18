<?php
// logout.php
require_once 'includes/init.php';

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to homepage
header('Location: index.php');
exit;