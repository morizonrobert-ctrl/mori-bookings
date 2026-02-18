<?php
// cron/process_refunds.php
require_once dirname(__DIR__) . '/includes/init.php';

use Mori\Database;
use Mori\Payment;

$db = Database::getInstance();
$payment = new Payment();

// Process approved refunds
$refunds = $db->fetchAll("
    SELECT r.*, b.booking_ref, b.user_id, b.payment_method
    FROM refunds r
    JOIN bookings b ON r.booking_id = b.id
    WHERE r.status = 'approved' AND r.processed_at IS NULL
");

foreach ($refunds as $refund) {
    // In production, integrate with payment gateway to actually refund
    // For now, just mark as processed
    $db->update('refunds', [
        'status' => 'processed',
        'processed_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$refund['id']]);
    
    echo "Processed refund for booking {$refund['booking_ref']}, amount KES {$refund['amount']}\n";
}