<?php
// includes/functions.php - Helper functions

use Mori\Database;

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format currency
 */
function formatMoney($amount, $currency = 'KES') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Get user IP
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get setting from database
 */
function getSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $table = null, $recordId = null, $oldValues = null, $newValues = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $userId,
        $action,
        $table,
        $recordId,
        $oldValues ? json_encode($oldValues) : null,
        $newValues ? json_encode($newValues) : null,
        getUserIP(),
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Send notification
 */
function sendNotification($userId, $type, $title, $message, $medium = 'email') {
    $notification = new Mori\Notification();
    return $notification->send($userId, $type, $title, $message, $medium);
}

/**
 * Get user by ID
 */
function getUser($userId) {
    $user = new Mori\User();
    return $user->getUser($userId);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireAuth();
    $user = new Mori\User();
    if (!$user->isAdmin(currentUserId())) {
        redirect(BASE_URL . 'index.php?error=Access denied');
    }
}

/**
 * Check if current (or given) user is admin
 */
function isAdmin($userId = null) {
    if ($userId === null) {
        $userId = currentUserId();
    }
    if (!$userId) return false;
    $admin = new Mori\Admin();
    return $admin->isAdmin($userId);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate phone number (Kenyan)
 */
function validatePhone($phone) {
    return preg_match('/^(\+254|0)[17]\d{8}$/', $phone);
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Get Google OAuth URL
 */
function getGoogleAuthUrl() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => GOOGLE_SCOPES,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}

/**
 * Download receipt as PDF
 */
function downloadReceipt($bookingRef) {
    // implementation in separate file
}

/**
 * Send SMS notification
 */
function sendSMS($phone, $message) {
    $sms = new Mori\SMS();
    return $sms->send($phone, $message);
}
