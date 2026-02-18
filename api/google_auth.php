<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the ID token from Google
$data = json_decode(file_get_contents('php://input'), true);
$idToken = $data['credential'] ?? '';

if (empty($idToken)) {
    echo json_encode(['success' => false, 'message' => 'No credential provided']);
    exit;
}

// Verify the ID token
require_once '../config/google_auth.php';
$tokenInfo = verifyGoogleIdToken($idToken);

if (!$tokenInfo || !isset($tokenInfo['email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
    exit;
}

// Extract user info from token
$googleId = $tokenInfo['sub'];
$email = $tokenInfo['email'];
$firstName = $tokenInfo['given_name'] ?? '';
$lastName = $tokenInfo['family_name'] ?? '';
$profileImage = $tokenInfo['picture'] ?? '';
$emailVerified = $tokenInfo['email_verified'] ?? false;

// Check if user exists in database
$db = \Mori\Database::getInstance();
$user = new \Mori\User();

// Check if email already exists
$existingUser = $user->getUserByEmail($email);

if ($existingUser) {
    // User exists - check if Google ID is linked
    $sql = "SELECT google_id FROM users WHERE id = ?";
    $userData = $db->fetch($sql, [$existingUser['id']]);
    
    if (empty($userData['google_id'])) {
        // Link Google account to existing user
        $db->update('users', [
            'google_id' => $googleId,
            'profile_image' => $profileImage,
            'is_verified' => 1
        ], 'id = ?', [$existingUser['id']]);
    }
    
    // Login the user
    $_SESSION['user_id'] = $existingUser['id'];
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $db->update('users', [
        'session_token' => $sessionToken,
        'token_expires_at' => $tokenExpires
    ], 'id = ?', [$existingUser['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $sessionToken,
        'user' => $existingUser,
        'redirect' => getRedirectUrl($existingUser['role'])
    ]);
    
} else {
    // Create new user from Google
    // Generate a random password for Google users
    $password = bin2hex(random_bytes(8));
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Prepare user data
    $userData = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'password_hash' => $hashedPassword,
        'phone' => '', // Google doesn't provide phone
        'role' => 'customer',
        'google_id' => $googleId,
        'profile_image' => $profileImage,
        'is_verified' => $emailVerified ? 1 : 0
    ];
    
    try {
        // Insert new user
        $userId = $db->insert('users', $userData);
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $db->update('users', [
            'session_token' => $sessionToken,
            'token_expires_at' => $tokenExpires
        ], 'id = ?', [$userId]);
        
        // Get user data
        $newUser = $user->getUser($userId);
        
        // Login the user
        $_SESSION['user_id'] = $userId;
        
        // Send welcome email
        $emailService = new \Mori\Email();
        $emailService->send(
            $email,
            'Welcome to MORI BOOKINGS!',
            "Hello {$firstName},\n\nThank you for registering with MORI BOOKINGS using Google. You can now book buses across Kenya.\n\nIf you didn't request this, please contact our support.\n\nBest regards,\nMORI BOOKINGS Team"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'token' => $sessionToken,
            'user' => $newUser,
            'redirect' => getRedirectUrl('customer')
        ]);
        
    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ]);
    }
}

function getRedirectUrl($role) {
    switch ($role) {
        case 'super_admin':
        case 'admin':
            return BASE_URL . 'admin/dashboard.php';
        case 'operator':
            return BASE_URL . 'admin/bookings.php';
        case 'driver':
            return BASE_URL . 'driver/dashboard.php';
        default:
            return BASE_URL . 'customer/dashboard.php';
    }
}