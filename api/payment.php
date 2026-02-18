<?php
require_once '../includes/init.php';
requireAuth();

header('Content-Type: application/json');

$payment = new Mori\Payment();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'mpesa':
            $phone = $data['phone'] ?? '';
            $amount = $data['amount'] ?? 0;
            $bookingId = $data['booking_id'] ?? 0;
            $result = $payment->processMpesa($phone, $amount, $bookingId, $userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'card':
            $cardData = $data['card'] ?? [];
            $amount = $data['amount'] ?? 0;
            $bookingId = $data['booking_id'] ?? 0;
            $result = $payment->processCardPayment($cardData, $amount, $bookingId, $userId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'cash':
            $bookingId = $data['booking_id'] ?? 0;
            $amount = $data['amount'] ?? 0;
            $confirmedBy = $userId;
            $result = $payment->processCashPayment($bookingId, $amount, $confirmedBy);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}