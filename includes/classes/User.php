<?php
namespace Mori;

class User {
    private $db;
    private $userData = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($data) {
        // Validate required fields
        $required = ['email', 'phone', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("{$field} is required");
            }
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email address");
        }
        
        // Validate phone (Kenyan format)
        if (!preg_match('/^(\+254|0)[17]\d{8}$/', $data['phone'])) {
            throw new \Exception("Invalid phone number. Use Kenyan format (07XXXXXXXX or +2547XXXXXXXX)");
        }
        
        // Check if email exists
        if ($this->emailExists($data['email'])) {
            throw new \Exception("Email already registered");
        }
        
        // Check if phone exists
        if ($this->phoneExists($data['phone'])) {
            throw new \Exception("Phone number already registered");
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Prepare user data
        $userData = [
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => $hashedPassword,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'id_number' => $data['id_number'] ?? null,
            'role' => $data['role'] ?? 'customer',
            'verification_token' => $verificationToken,
            'profile_image' => $data['profile_image'] ?? null
        ];
        
        $this->db->beginTransaction();
        
        try {
            // Insert user
            $userId = $this->db->insert('users', $userData);
            
            // Send verification email
            $this->sendVerificationEmail($data['email'], $verificationToken, $data['first_name']);
            
            // Send welcome SMS
            $this->sendWelcomeSMS($data['phone'], $data['first_name']);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Registration successful. Please check your email for verification.'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function login($email, $password) {
        // Get user by email or phone
        $sql = "SELECT * FROM users WHERE (email = :identifier OR phone = :identifier) AND is_verified = 1";
        $user = $this->db->fetch($sql, [':identifier' => $email]);
        
        if (!$user) {
            throw new \Exception("Invalid credentials or account not verified");
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            throw new \Exception("Invalid credentials");
        }
        
        // Check if account is active
        if ($user['role'] === 'inactive') {
            throw new \Exception("Account is deactivated. Contact support.");
        }
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update session token
        $this->db->update('users', [
            'session_token' => $sessionToken,
            'token_expires_at' => $tokenExpires
        ], 'id = :id', [':id' => $user['id']]);
        
        // Log login
        $this->logActivity($user['id'], 'login', 'User logged in');
        
        // Return user data without password
        unset($user['password_hash']);
        unset($user['verification_token']);
        unset($user['reset_token']);
        
        return [
            'user' => $user,
            'token' => $sessionToken,
            'expires' => $tokenExpires
        ];
    }
    
    public function verifyToken($token) {
        $sql = "SELECT * FROM users WHERE session_token = :token AND token_expires_at > NOW()";
        $user = $this->db->fetch($sql, [':token' => $token]);
        
        if (!$user) {
            return false;
        }
        
        // Update token expiry
        $newExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->db->update('users', [
            'token_expires_at' => $newExpiry
        ], 'id = :id', [':id' => $user['id']]);
        
        $this->userData = $user;
        return $user;
    }
    
    public function updateProfile($userId, $data) {
        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'id_number', 'profile_image'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            throw new \Exception("No data to update");
        }
        
        // Check if new email exists
        if (isset($updateData['email']) && $updateData['email'] !== $this->userData['email']) {
            if ($this->emailExists($updateData['email'])) {
                throw new \Exception("Email already in use");
            }
            $updateData['is_verified'] = 0;
            $updateData['verification_token'] = bin2hex(random_bytes(32));
        }
        
        // Check if new phone exists
        if (isset($updateData['phone']) && $updateData['phone'] !== $this->userData['phone']) {
            if ($this->phoneExists($updateData['phone'])) {
                throw new \Exception("Phone number already in use");
            }
        }
        
        $this->db->update('users', $updateData, 'id = :id', [':id' => $userId]);
        
        // Log activity
        $this->logActivity($userId, 'profile_update', 'Profile updated');
        
        return true;
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Get current password hash
        $sql = "SELECT password_hash FROM users WHERE id = :id";
        $user = $this->db->fetch($sql, [':id' => $userId]);
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new \Exception("Current password is incorrect");
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->update('users', [
            'password_hash' => $newHash
        ], 'id = :id', [':id' => $userId]);
        
        // Log activity
        $this->logActivity($userId, 'password_change', 'Password changed');
        
        // Send notification
        $this->sendPasswordChangeNotification($userId);
        
        return true;
    }
    
    public function requestPasswordReset($email) {
        $sql = "SELECT id, email, first_name FROM users WHERE email = :email AND is_verified = 1";
        $user = $this->db->fetch($sql, [':email' => $email]);
        
        if (!$user) {
            throw new \Exception("Email not found or account not verified");
        }
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save reset token
        $this->db->update('users', [
            'reset_token' => $resetToken,
            'reset_expires' => $resetExpires
        ], 'id = :id', [':id' => $user['id']]);
        
        // Send reset email
        $this->sendPasswordResetEmail($user['email'], $resetToken, $user['first_name']);
        
        return true;
    }
    
    public function resetPassword($token, $newPassword) {
        $sql = "SELECT id FROM users WHERE reset_token = :token AND reset_expires > NOW()";
        $user = $this->db->fetch($sql, [':token' => $token]);
        
        if (!$user) {
            throw new \Exception("Invalid or expired reset token");
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->update('users', [
            'password_hash' => $newHash,
            'reset_token' => null,
            'reset_expires' => null
        ], 'id = :id', [':id' => $user['id']]);
        
        // Log activity
        $this->logActivity($user['id'], 'password_reset', 'Password reset via token');
        
        return true;
    }
    
    public function verifyAccount($token) {
        $sql = "SELECT id, email, first_name FROM users WHERE verification_token = :token";
        $user = $this->db->fetch($sql, [':token' => $token]);
        
        if (!$user) {
            throw new \Exception("Invalid verification token");
        }
        
        // Mark as verified
        $this->db->update('users', [
            'is_verified' => 1,
            'verification_token' => null
        ], 'id = :id', [':id' => $user['id']]);
        
        // Send welcome notification
        $this->sendAccountVerifiedNotification($user['id']);
        
        return $user;
    }
    
    public function getUser($userId) {
        $sql = "SELECT id, uuid, email, phone, first_name, last_name, id_number, role, 
                       loyalty_points, total_trips, free_trips_earned, free_trips_available,
                       profile_image, created_at
                FROM users WHERE id = :id";
        return $this->db->fetch($sql, [':id' => $userId]);
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT id, email, phone, first_name, last_name, role FROM users WHERE email = :email";
        return $this->db->fetch($sql, [':email' => $email]);
    }
    
    public function getAllUsers($role = null, $limit = 50, $offset = 0) {
        $where = '';
        $params = [];
        
        if ($role) {
            $where = "WHERE role = :role";
            $params[':role'] = $role;
        }
        
        $sql = "SELECT id, uuid, email, phone, first_name, last_name, role, 
                       loyalty_points, total_trips, is_verified, created_at
                FROM users {$where}
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function updateLoyalty($userId, $points, $tripCount = 0) {
        $updateData = [
            'loyalty_points' => $points
        ];
        
        if ($tripCount > 0) {
            $updateData['total_trips'] = $tripCount;
            
            // Check for free trip eligibility
            if ($tripCount % 10 === 0) {
                $updateData['free_trips_earned'] = $tripCount / 10;
                $updateData['free_trips_available'] = $tripCount / 10;
                
                // Notify user about free trip
                $this->notifyFreeTripEarned($userId, $tripCount / 10);
            }
        }
        
        $this->db->update('users', $updateData, 'id = :id', [':id' => $userId]);
        
        return true;
    }
    
    public function hasRole($userId, $role) {
        $sql = "SELECT role FROM users WHERE id = :id";
        $user = $this->db->fetch($sql, [':id' => $userId]);
        return $user && $user['role'] === $role;
    }
    
    public function isSuperAdmin($userId) {
        return $this->hasRole($userId, 'super_admin');
    }
    
    public function isAdmin($userId) {
        $role = $this->getUserRole($userId);
        return in_array($role, ['super_admin', 'admin']);
    }
    
    public function getUserRole($userId) {
        $sql = "SELECT role FROM users WHERE id = :id";
        $user = $this->db->fetch($sql, [':id' => $userId]);
        return $user ? $user['role'] : null;
    }
    
    public function updateRole($userId, $role) {
        $allowedRoles = ['super_admin', 'admin', 'operator', 'customer', 'driver'];
        
        if (!in_array($role, $allowedRoles)) {
            throw new \Exception("Invalid role");
        }
        
        $this->db->update('users', [
            'role' => $role
        ], 'id = :id', [':id' => $userId]);
        
        // Log activity
        $this->logActivity($userId, 'role_change', "Role changed to {$role}");
        
        return true;
    }
    
    public function deleteUser($userId) {
        // Don't delete, mark as inactive
        $this->db->update('users', [
            'role' => 'inactive',
            'email' => $this->db->fetchColumn("SELECT CONCAT('deleted_', UUID(), '@', email) FROM users WHERE id = ?", [$userId]),
            'phone' => $this->db->fetchColumn("SELECT CONCAT('deleted_', UUID()) FROM users WHERE id = ?", [$userId])
        ], 'id = :id', [':id' => $userId]);
        
        // Log activity
        $this->logActivity($userId, 'account_deletion', 'Account marked as inactive');
        
        return true;
    }
    
    public function searchUsers($query, $limit = 20) {
        $sql = "SELECT id, uuid, email, phone, first_name, last_name, role, 
                       loyalty_points, total_trips, created_at
                FROM users 
                WHERE email LIKE :query 
                   OR phone LIKE :query 
                   OR first_name LIKE :query 
                   OR last_name LIKE :query
                   OR CONCAT(first_name, ' ', last_name) LIKE :query
                ORDER BY created_at DESC
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            ':query' => "%{$query}%",
            ':limit' => $limit
        ]);
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Total users
        $stats['total_users'] = $this->db->fetchColumn("SELECT COUNT(*) FROM users");
        $stats['total_customers'] = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'customer'");
        $stats['total_admins'] = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role IN ('super_admin', 'admin')");
        $stats['new_users_today'] = $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
        
        // User growth (last 7 days)
        $stats['user_growth'] = $this->db->fetchAll("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM users
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        
        return $stats;
    }
    
    private function emailExists($email) {
        return $this->db->exists('users', 'email = :email', [':email' => $email]);
    }
    
    private function phoneExists($phone) {
        return $this->db->exists('users', 'phone = :phone', [':phone' => $phone]);
    }
    
    private function sendVerificationEmail($email, $token, $name) {
        $verificationLink = BASE_URL . "verify.php?token=" . $token;
        
        $subject = "Verify Your MORI BOOKINGS Account";
        $message = "Hello {$name},\n\n";
        $message .= "Thank you for registering with MORI BOOKINGS!\n\n";
        $message .= "Please click the link below to verify your email address:\n";
        $message .= "{$verificationLink}\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you didn't create an account, please ignore this email.\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($email, $subject, $message);
    }
    
    private function sendWelcomeSMS($phone, $name) {
        $message = "Welcome {$name} to MORI BOOKINGS! Your account has been created. Book buses conveniently across Kenya.";
        
        $smsService = new SMS();
        $smsService->send($phone, $message);
    }
    
    private function sendPasswordResetEmail($email, $token, $name) {
        $resetLink = BASE_URL . "reset-password.php?token=" . $token;
        
        $subject = "Reset Your MORI BOOKINGS Password";
        $message = "Hello {$name},\n\n";
        $message .= "We received a request to reset your password.\n\n";
        $message .= "Click the link below to reset your password:\n";
        $message .= "{$resetLink}\n\n";
        $message .= "This link will expire in 1 hour.\n\n";
        $message .= "If you didn't request a password reset, please ignore this email.\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($email, $subject, $message);
    }
    
    private function sendPasswordChangeNotification($userId) {
        $user = $this->getUser($userId);
        
        $subject = "Password Changed - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "Your password was successfully changed.\n\n";
        $message .= "If you didn't make this change, please contact our support immediately.\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS notification
        $smsMessage = "MORI: Your password was changed. If this wasn't you, contact support immediately.";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function sendAccountVerifiedNotification($userId) {
        $user = $this->getUser($userId);
        
        $subject = "Account Verified - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "Congratulations! Your account has been verified.\n\n";
        $message .= "You can now book buses, track your trips, and enjoy our loyalty program.\n\n";
        $message .= "Start your journey: " . BASE_URL . "customer/book.php\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS notification
        $smsMessage = "MORI: Account verified! Start booking buses at " . BASE_URL;
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function notifyFreeTripEarned($userId, $freeTrips) {
        $user = $this->getUser($userId);
        
        $subject = "Congratulations! You've Earned Free Trips";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "🎉 Congratulations! You've completed enough trips to earn {$freeTrips} free trip(s)!\n\n";
        $message .= "You now have {$freeTrips} free trip(s) available for booking.\n\n";
        $message .= "To use your free trip, select 'Use Free Trip' during payment on your next booking.\n\n";
        $message .= "Thank you for being a loyal MORI customer!\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS notification
        $smsMessage = "MORI: Congrats! You've earned {$freeTrips} free trip(s) for completing 10+ trips. Book now!";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function logActivity($userId, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => 'users',
            'record_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);
    }
}