<?php
// cron/reminders.php
require_once dirname(__DIR__) . '/includes/init.php';

use Mori\Database;
use Mori\Notification;

$db = Database::getInstance();
$notification = new Notification();

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$bookings = $db->fetchAll("
    SELECT b.*, u.phone, u.email, u.first_name, u.id as user_id,
           s.departure_date, s.departure_time,
           r.origin_city, r.destination_city
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    WHERE s.departure_date = ? AND b.booking_status = 'confirmed'
", [$tomorrow]);

foreach ($bookings as $b) {
    $message = "Dear {$b['first_name']}, this is a reminder of your trip from {$b['origin_city']} to {$b['destination_city']} tomorrow at {$b['departure_time']}. Thank you for choosing MORI BOOKINGS.";
    
    // Send SMS
    $notification->sendSMS($b['phone'], $message);
    
    // Send email
    $notification->sendEmail($b['email'], 'Trip Reminder', $message);
    
    echo "Reminder sent to {$b['phone']} for booking {$b['booking_ref']}\n";
}