<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$user = new \Mori\User();
$response = [];

switch ($action) {
    case 'login':
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $remember = $data['remember'] ?? false;
        
        try {
            $result = $user->login($email, $password);
            
            if ($result) {
                $_SESSION['user_id'] = $result['user']['id'];
                
                // Set remember me cookie
                if ($remember) {
                    $cookieToken = bin2hex(random_bytes(32));
                    $cookieExpires = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    setcookie('remember_token', $cookieToken, $cookieExpires, '/', '', true, true);
                    
                    // Store token in database
                    $db = \Mori\Database::getInstance();
                    $db->update('users', [
                        'remember_token' => $cookieToken,
                        'remember_expires' => date('Y-m-d H:i:s', $cookieExpires)
                    ], 'id = ?', [$result['user']['id']]);
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $result['token'],
                    'user' => $result['user'],
                    'redirect' => getRedirectUrl($result['user']['role'])
                ];
            } else {
                $response = ['success' => false, 'message' => 'Invalid credentials'];
            }
            
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    case 'register':
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        $idNumber = $data['id_number'] ?? '';
        
        $errors = [];
        
        // Validate inputs
        if (empty($firstName)) $errors['firstName'] = 'First name is required';
        if (empty($lastName)) $errors['lastName'] = 'Last name is required';
        if (empty($email)) $errors['email'] = 'Email is required';
        if (empty($phone)) $errors['phone'] = 'Phone number is required';
        if (empty($password)) $errors['password'] = 'Password is required';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!preg_match('/^(\+254|0)[17]\d{8}$/', $phone)) {
            $errors['phone'] = 'Invalid phone number format';
        }
        
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        if (!empty($errors)) {
            $response = ['success' => false, 'errors' => $errors];
            break;
        }
        
        try {
            $result = $user->register([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'id_number' => $idNumber
            ]);
            
            $response = [
                'success' => true,
                'message' => $result['message'],
                'user_id' => $result['user_id']
            ];
            
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    case 'forgot_password':
        $email = $data['email'] ?? '';
        
        if (empty($email)) {
            $response = ['success' => false, 'message' => 'Email is required'];
            break;
        }
        
        try {
            $result = $user->requestPasswordReset($email);
            $response = ['success' => true, 'message' => 'Password reset link sent to your email'];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    case 'reset_password':
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($token) || empty($password)) {
            $response = ['success' => false, 'message' => 'Token and password are required'];
            break;
        }
        
        try {
            $result = $user->resetPassword($token, $password);
            $response = ['success' => true, 'message' => 'Password reset successful'];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    case 'check_session':
        if (isset($_SESSION['user_id'])) {
            $userData = $user->getUser($_SESSION['user_id']);
            $response = ['success' => true, 'user' => $userData];
        } else {
            $response = ['success' => false, 'message' => 'Not logged in'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($response);

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