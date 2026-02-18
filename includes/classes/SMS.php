<?php
namespace Mori;

require_once __DIR__ . '/../../vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

class SMS {
	private $at;
	private $db;

	public function __construct()
	{
		$this->db = Database::getInstance();
		// Use constants defined in config/constants.php
		$username = defined('AT_USERNAME') ? AT_USERNAME : null;
		$apiKey = defined('AT_API_KEY') ? AT_API_KEY : null;

		if ($username && $apiKey) {
			$this->at = new AfricasTalking($username, $apiKey);
		} else {
			$this->at = null;
		}

		// Ensure sms_logs table exists (best-effort)
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS sms_logs (
				id INT AUTO_INCREMENT PRIMARY KEY,
				recipient VARCHAR(255) NOT NULL,
				message TEXT,
				response TEXT,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
		} catch (\Exception $e) {
			// ignore if cannot create
		}
	}

	public function send($to, $message, $from = null)
	{
		try {
			if (!$this->at) {
				throw new \Exception('Africa\'s Talking not configured');
			}

			$sms = $this->at->sms();
			$params = [
				'to' => $to,
				'message' => $message
			];
			if ($from) $params['from'] = $from;

			$result = $sms->send($params);

			// Log SMS
			try {
				$this->db->insert('sms_logs', [
					'recipient' => $to,
					'message' => $message,
					'response' => json_encode($result),
					'created_at' => date('Y-m-d H:i:s')
				]);
			} catch (\Exception $e) {
				// ignore logging errors
			}

			return $result;
		} catch (\Exception $e) {
			try {
				$this->db->insert('sms_logs', [
					'recipient' => $to,
					'message' => $message,
					'response' => json_encode(['error' => $e->getMessage()]),
					'created_at' => date('Y-m-d H:i:s')
				]);
			} catch (\Exception $e) {
				// ignore
			}
			return false;
		}
	}

	public function sendBookingConfirmation($phone, $booking)
	{
		$seatList = is_array($booking['seat_numbers']) ? implode(',', $booking['seat_numbers']) : $booking['seat_numbers'];
		$message = "MORI Booking Confirmed: Ref {$booking['booking_ref']}. ";
		$message .= "Route: {$booking['origin_city']} to {$booking['destination_city']}. ";
		$message .= "Depart: " . date('M d, Y H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])) . ". ";
		$message .= "Seats: {$seatList}. ";
		$message .= "Amount: KES " . number_format($booking['total_amount'], 2) . ". ";
		$message .= "Receipt: {$booking['receipt_number']}";

		return $this->send($phone, $message, defined('SMS_SENDER_ID') ? SMS_SENDER_ID : null);
	}
}

