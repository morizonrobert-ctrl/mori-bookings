<?php
// Application Constants
define('APP_NAME', 'MORI BOOKINGS');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/mori-bookings/');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// Security
define('ENCRYPTION_KEY', 'mori_secure_key_2024');
define('JWT_SECRET', 'mori_jwt_secret_2024');

// File uploads
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Payment
define('MPESA_SHORTCODE', '174379');
define('MPESA_PASSKEY', 'YOUR_PASSKEY');
define('MPESA_CALLBACK_URL', BASE_URL . 'api/mpesa_callback.php');

// SMS Configuration
define('SMS_API_KEY', 'YOUR_SMS_API_KEY');
define('SMS_SENDER_ID', 'MORIBOOK');
// Africa's Talking (fill with your credentials)
define('AT_USERNAME', 'YOUR_AT_USERNAME');
define('AT_API_KEY', 'YOUR_AT_API_KEY');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@moribookings.co.ke');
define('SMTP_PASS', 'your_email_password');

// Booking Settings
define('SEAT_LOCK_DURATION', 900); // 15 minutes
define('MAX_SEATS_PER_BOOKING', 6);
define('REFUND_DEADLINE_HOURS', 24);
define('LOYALTY_TRIPS_FOR_FREE', 10);
define('LOYALTY_POINTS_PER_100', 1);