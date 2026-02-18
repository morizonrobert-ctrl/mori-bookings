<?php
namespace Mori;

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function send($userId, $type, $title, $message, $medium = 'email') {
        $id = $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'medium' => $medium,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $user = (new User())->getUser($userId);
        if (!$user) return false;

        $sent = false;

        if ($medium === 'email' || $medium === 'both') {
            $email = new Email();
            $sent = $email->send($user['email'], $title, $message);
        }

        if ($medium === 'sms' || $medium === 'both') {
            $sms = new SMS();
            $smsSent = $sms->send($user['phone'], $message);
            $sent = $sent || $smsSent;
        }

        $this->db->update('notifications', [
            'status' => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? date('Y-m-d H:i:s') : null
        ], 'id = ?', [$id]);

        return $sent;
    }

    public function sendBulk($userIds, $type, $title, $message, $medium = 'email') {
        $success = 0;
        foreach ($userIds as $userId) {
            if ($this->send($userId, $type, $title, $message, $medium)) {
                $success++;
            }
        }
        return $success;
    }

    public function getUnread($userId) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND read_at IS NULL ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }

    public function markAsRead($notificationId) {
        $this->db->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = ?', [$notificationId]);
    }

    public function sendSMS($phone, $message) {
        $sms = new SMS();
        return $sms->send($phone, $message);
    }

    public function sendEmail($email, $subject, $message) {
        $mail = new Email();
        return $mail->send($email, $subject, $message);
    }

    public function sendBookingConfirmation($booking) {
        $user = (new User())->getUser($booking['user_id']);
        $message = "Dear {$user['first_name']}, your booking {$booking['booking_ref']} has been confirmed. ";
        $message .= "Route: {$booking['origin_city']} → {$booking['destination_city']} at ";
        $message .= date('M d, H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])) . ". ";
        $message .= "Seats: " . implode(', ', json_decode($booking['seat_numbers'], true)) . ". Thank you!";
        return $this->send($user['id'], 'booking', 'Booking Confirmed', $message, 'both');
    }
}