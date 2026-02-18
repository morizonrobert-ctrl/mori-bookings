<?php
namespace Mori;

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function processMpesa($phone, $amount, $bookingId, $userId) {
        // Clean phone number
        $phone = $this->cleanPhoneNumber($phone);
        
        // Validate phone
        if (!preg_match('/^(?:254|\+254|0)?(7[0-9]{8})$/', $phone, $matches)) {
            throw new \Exception("Invalid phone number format. Use 07XXXXXXXX or +2547XXXXXXXX");
        }
        
        $phone = '254' . $matches[1];
        
        // Get booking details
        $booking = $this->getBookingForPayment($bookingId, $userId);
        if (!$booking) {
            throw new \Exception("Booking not found or not pending payment");
        }
        
        // Validate amount
        if ($amount < 100) {
            throw new \Exception("Minimum payment is KES 100");
        }
        
        if ($amount > $booking['total_amount'] - $booking['amount_paid']) {
            throw new \Exception("Amount exceeds remaining balance");
        }
        
        // Simulate Mpesa STK push (in production, integrate with Safaricom API)
        $transactionId = $this->generateMpesaTransactionId();
        
        $this->db->beginTransaction();
        
        try {
            // Record payment
            $paymentId = $this->db->insert('payments', [
                'booking_id' => $bookingId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_method' => 'mpesa',
                'mpesa_code' => $transactionId,
                'mpesa_phone' => $phone,
                'status' => 'pending',
                'metadata' => json_encode([
                    'phone' => $phone,
                    'amount' => $amount,
                    'booking_ref' => $booking['booking_ref']
                ])
            ]);
            
            // Simulate Mpesa callback (in production, this would be from Safaricom)
            // For demo, auto-confirm after 2 seconds
            sleep(2);
            
            // Confirm payment
            $this->confirmMpesaPayment($transactionId, $paymentId);
            
            // Update booking payment
            $newAmountPaid = $booking['amount_paid'] + $amount;
            $paymentStatus = $newAmountPaid >= $booking['total_amount'] ? 'paid' : 'partial';
            
            $this->db->update('bookings', [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $paymentStatus,
                'mpesa_receipt' => $transactionId
            ], 'id = :id', [':id' => $bookingId]);
            
            // If fully paid, confirm booking
            if ($paymentStatus === 'paid') {
                $bookingClass = new Booking();
                $bookingClass->confirmBooking($bookingId, $booking['booking_token'], [
                    'method' => 'mpesa',
                    'amount' => $amount,
                    'mpesa_receipt' => $transactionId
                ]);
            }
            
            $this->db->commit();
            
            // Send payment confirmation
            $this->sendPaymentConfirmation($bookingId, $amount, 'mpesa', $transactionId);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_status' => $paymentStatus,
                'booking_status' => $paymentStatus === 'paid' ? 'confirmed' : 'pending'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function processCardPayment($cardData, $amount, $bookingId, $userId) {
        // Validate card data
        $this->validateCardData($cardData);
        
        // Get booking details
        $booking = $this->getBookingForPayment($bookingId, $userId);
        if (!$booking) {
            throw new \Exception("Booking not found or not pending payment");
        }
        
        // Validate amount
        if ($amount < 100) {
            throw new \Exception("Minimum payment is KES 100");
        }
        
        if ($amount > $booking['total_amount'] - $booking['amount_paid']) {
            throw new \Exception("Amount exceeds remaining balance");
        }
        
        // Simulate card payment (in production, integrate with payment gateway)
        $transactionId = $this->generateCardTransactionId();
        
        $this->db->beginTransaction();
        
        try {
            // Record payment
            $paymentId = $this->db->insert('payments', [
                'booking_id' => $bookingId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_method' => 'card',
                'card_last4' => substr($cardData['card_number'], -4),
                'card_type' => $this->detectCardType($cardData['card_number']),
                'status' => 'pending',
                'metadata' => json_encode([
                    'card_last4' => substr($cardData['card_number'], -4),
                    'card_type' => $this->detectCardType($cardData['card_number']),
                    'booking_ref' => $booking['booking_ref']
                ])
            ]);
            
            // Simulate payment processing
            sleep(2);
            
            // Confirm payment
            $this->confirmCardPayment($transactionId, $paymentId);
            
            // Update booking payment
            $newAmountPaid = $booking['amount_paid'] + $amount;
            $paymentStatus = $newAmountPaid >= $booking['total_amount'] ? 'paid' : 'partial';
            
            $this->db->update('bookings', [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $paymentStatus,
                'card_transaction_id' => $transactionId
            ], 'id = :id', [':id' => $bookingId]);
            
            // If fully paid, confirm booking
            if ($paymentStatus === 'paid') {
                $bookingClass = new Booking();
                $bookingClass->confirmBooking($bookingId, $booking['booking_token'], [
                    'method' => 'card',
                    'amount' => $amount,
                    'transaction_id' => $transactionId
                ]);
            }
            
            $this->db->commit();
            
            // Send payment confirmation
            $this->sendPaymentConfirmation($bookingId, $amount, 'card', $transactionId);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_status' => $paymentStatus,
                'booking_status' => $paymentStatus === 'paid' ? 'confirmed' : 'pending'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function processCashPayment($bookingId, $amount, $confirmedBy) {
        // Get booking details
        $sql = "SELECT * FROM bookings WHERE id = :id";
        $booking = $this->db->fetch($sql, [':id' => $bookingId]);
        
        if (!$booking) {
            throw new \Exception("Booking not found");
        }
        
        // Validate amount
        if ($amount < 100) {
            throw new \Exception("Minimum payment is KES 100");
        }
        
        if ($amount > $booking['total_amount'] - $booking['amount_paid']) {
            throw new \Exception("Amount exceeds remaining balance");
        }
        
        $transactionId = 'CASH' . date('YmdHis') . rand(1000, 9999);
        
        $this->db->beginTransaction();
        
        try {
            // Record payment
            $paymentId = $this->db->insert('payments', [
                'booking_id' => $bookingId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_method' => 'cash',
                'status' => 'completed',
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode([
                    'confirmed_by' => $confirmedBy,
                    'booking_ref' => $booking['booking_ref']
                ])
            ]);
            
            // Update booking payment
            $newAmountPaid = $booking['amount_paid'] + $amount;
            $paymentStatus = $newAmountPaid >= $booking['total_amount'] ? 'paid' : 'partial';
            
            $this->db->update('bookings', [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $paymentStatus
            ], 'id = :id', [':id' => $bookingId]);
            
            // If fully paid, confirm booking
            if ($paymentStatus === 'paid') {
                $bookingClass = new Booking();
                $bookingClass->confirmBooking($bookingId, $booking['booking_token'], [
                    'method' => 'cash',
                    'amount' => $amount
                ]);
            }
            
            $this->db->commit();
            
            // Send payment confirmation
            $this->sendPaymentConfirmation($bookingId, $amount, 'cash', $transactionId);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_status' => $paymentStatus
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getPaymentHistory($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT p.*, b.booking_ref, b.total_amount, 
                       r.origin_city, r.destination_city
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                WHERE b.user_id = :user_id
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            ':user_id' => $userId,
            ':limit' => $limit,
            ':offset' => $offset
        ]);
    }
    
    public function getTransactionDetails($transactionId) {
        $sql = "SELECT p.*, b.booking_ref, b.total_amount, b.user_id,
                       u.first_name, u.last_name, u.email, u.phone,
                       r.origin_city, r.destination_city
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                JOIN users u ON b.user_id = u.id
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                WHERE p.transaction_id = :transaction_id";
        
        $payment = $this->db->fetch($sql, [':transaction_id' => $transactionId]);
        
        if ($payment) {
            $payment['metadata'] = json_decode($payment['metadata'], true);
        }
        
        return $payment;
    }
    
    public function refundPayment($paymentId, $reason, $processedBy) {
        $sql = "SELECT p.*, b.booking_ref, b.user_id, b.total_amount, b.amount_paid
                FROM payments p
                JOIN bookings b ON p.booking_id = b.id
                WHERE p.id = :payment_id AND p.status = 'completed'";
        
        $payment = $this->db->fetch($sql, [':payment_id' => $paymentId]);
        
        if (!$payment) {
            throw new \Exception("Payment not found or not completed");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Mark payment as refunded
            $this->db->update('payments', [
                'status' => 'refunded'
            ], 'id = :id', [':id' => $paymentId]);
            
            // Create refund record
            $refundId = $this->db->insert('refunds', [
                'booking_id' => $payment['booking_id'],
                'amount' => $payment['amount'],
                'reason' => $reason,
                'status' => 'processed',
                'processed_by' => $processedBy,
                'processed_at' => date('Y-m-d H:i:s'),
                'transaction_id' => 'RFND' . date('YmdHis') . rand(1000, 9999)
            ]);
            
            // Update booking payment status
            $newAmountPaid = $payment['amount_paid'] - $payment['amount'];
            $this->db->update('bookings', [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $newAmountPaid > 0 ? 'partial' : 'refunded'
            ], 'id = :id', [':id' => $payment['booking_id']]);
            
            $this->db->commit();
            
            // Send refund notification
            $this->sendRefundNotificationToUser($payment['booking_id'], $payment['amount']);
            
            return [
                'success' => true,
                'refund_id' => $refundId,
                'amount' => $payment['amount']
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getPaymentStats($startDate = null, $endDate = null) {
        $where = "WHERE p.created_at IS NOT NULL";
        $params = [];
        
        if ($startDate && $endDate) {
            $where = "WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $stats = [];
        
        // Total payments
        $stats['total_payments'] = $this->db->fetchColumn("
            SELECT COUNT(*) FROM payments p {$where} AND p.status = 'completed'
        ", $params);
        
        // Total revenue
        $stats['total_revenue'] = $this->db->fetchColumn("
            SELECT COALESCE(SUM(amount), 0) FROM payments p {$where} AND p.status = 'completed'
        ", $params);
        
        // Payments by method
        $stats['by_method'] = $this->db->fetchAll("
            SELECT payment_method, COUNT(*) as count, SUM(amount) as total
            FROM payments p {$where} AND p.status = 'completed'
            GROUP BY payment_method
            ORDER BY total DESC
        ", $params);
        
        // Daily revenue for last 30 days
        $stats['daily_revenue'] = $this->db->fetchAll("
            SELECT DATE(created_at) as date, COUNT(*) as payments, SUM(amount) as revenue
            FROM payments
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        
        // Average transaction value
        $stats['avg_transaction'] = $this->db->fetchColumn("
            SELECT AVG(amount) FROM payments p {$where} AND p.status = 'completed'
        ", $params);
        
        return $stats;
    }
    
    private function getBookingForPayment($bookingId, $userId) {
        $sql = "SELECT * FROM bookings 
                WHERE id = :id AND user_id = :user_id 
                AND booking_status = 'pending' 
                AND token_expires_at > NOW()";
        
        return $this->db->fetch($sql, [
            ':id' => $bookingId,
            ':user_id' => $userId
        ]);
    }
    
    private function cleanPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove leading 0
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }
        
        // Add country code if missing
        if (strpos($phone, '254') !== 0) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    private function generateMpesaTransactionId() {
        return 'MP' . date('YmdHis') . rand(100, 999);
    }
    
    private function generateCardTransactionId() {
        return 'CARD' . date('YmdHis') . rand(100, 999);
    }
    
    private function confirmMpesaPayment($transactionId, $paymentId) {
        $this->db->update('payments', [
            'status' => 'completed',
            'confirmed_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $paymentId]);
        
        // Log successful payment
        error_log("Mpesa payment confirmed: {$transactionId}");
    }
    
    private function confirmCardPayment($transactionId, $paymentId) {
        $this->db->update('payments', [
            'status' => 'completed',
            'confirmed_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $paymentId]);
        
        // Log successful payment
        error_log("Card payment confirmed: {$transactionId}");
    }
    
    private function validateCardData($cardData) {
        $required = ['card_number', 'card_holder', 'expiry_month', 'expiry_year', 'cvv'];
        
        foreach ($required as $field) {
            if (empty($cardData[$field])) {
                throw new \Exception("{$field} is required");
            }
        }
        
        // Validate card number (Luhn algorithm)
        if (!$this->validateCardNumber($cardData['card_number'])) {
            throw new \Exception("Invalid card number");
        }
        
        // Validate expiry date
        $expiry = $cardData['expiry_month'] . '/' . $cardData['expiry_year'];
        if (!$this->validateExpiryDate($cardData['expiry_month'], $cardData['expiry_year'])) {
            throw new \Exception("Card has expired or invalid expiry date");
        }
        
        // Validate CVV
        if (!preg_match('/^[0-9]{3,4}$/', $cardData['cvv'])) {
            throw new \Exception("Invalid CVV");
        }
    }
    
    private function validateCardNumber($number) {
        $number = preg_replace('/\D/', '', $number);
        
        // Luhn algorithm
        $length = strlen($number);
        $sum = 0;
        $parity = $length % 2;
        
        for ($i = 0; $i < $length; $i++) {
            $digit = $number[$i];
            
            if ($i % 2 == $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 == 0;
    }
    
    private function validateExpiryDate($month, $year) {
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        $year = intval($year);
        $month = intval($month);
        
        if ($year < $currentYear) {
            return false;
        }
        
        if ($year == $currentYear && $month < $currentMonth) {
            return false;
        }
        
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        return true;
    }
    
    private function detectCardType($number) {
        $number = preg_replace('/\D/', '', $number);
        
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
            return 'visa';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'mastercard';
        } elseif (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'amex';
        } elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $number)) {
            return 'discover';
        } else {
            return 'unknown';
        }
    }
    
    private function sendPaymentConfirmation($bookingId, $amount, $method, $transactionId) {
        $booking = $this->db->fetch("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$booking['user_id']]);
        
        $subject = "Payment Received - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "We have received your payment of KES " . number_format($amount, 2) . " for booking {$booking['booking_ref']}.\n\n";
        $message .= "Payment Method: " . strtoupper($method) . "\n";
        $message .= "Transaction ID: {$transactionId}\n";
        $message .= "Amount Paid: KES " . number_format($amount, 2) . "\n";
        $message .= "Total Amount: KES " . number_format($booking['total_amount'], 2) . "\n";
        $message .= "Remaining Balance: KES " . number_format($booking['total_amount'] - $booking['amount_paid'] - $amount, 2) . "\n\n";
        
        if ($booking['amount_paid'] + $amount >= $booking['total_amount']) {
            $message .= "Your booking is now confirmed! Please check your email for the booking confirmation.\n";
        } else {
            $message .= "Please complete your payment to confirm your booking.\n";
        }
        
        $message .= "\nThank you for choosing MORI BOOKINGS!\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS
        $smsMessage = "MORI: Payment of KES " . number_format($amount, 2) . " received for booking {$booking['booking_ref']}. Txn: {$transactionId}";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function sendRefundNotificationToUser($bookingId, $amount) {
        $booking = $this->db->fetch("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$booking['user_id']]);
        
        $subject = "Refund Processed - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "A refund of KES " . number_format($amount, 2) . " has been processed for your booking {$booking['booking_ref']}.\n\n";
        $message .= "The refund will be credited to your original payment method within 3-5 business days.\n\n";
        $message .= "If you have any questions, please contact our support team.\n\n";
        $message .= "Best regards,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS
        $smsMessage = "MORI: Refund of KES " . number_format($amount, 2) . " processed for booking {$booking['booking_ref']}. Will reflect in 3-5 days.";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
}