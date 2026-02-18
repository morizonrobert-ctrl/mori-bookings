<?php
// verify.php
require_once 'includes/init.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: index.php?error=Invalid verification token');
    exit;
}

$user = new \Mori\User();

try {
    $verifiedUser = $user->verifyAccount($token);
    
    // Auto login after verification
    $_SESSION['user_id'] = $verifiedUser['id'];
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $db = \Mori\Database::getInstance();
    $db->update('users', [
        'session_token' => $sessionToken,
        'token_expires_at' => $tokenExpires
    ], 'id = ?', [$verifiedUser['id']]);
    
    // Redirect to dashboard
    header('Location: customer/dashboard.php?verified=1');
    exit;
    
} catch (\Exception $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}