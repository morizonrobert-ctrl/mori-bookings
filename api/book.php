<?php
require_once '../includes/init.php';
requireAuth();

header('Content-Type: application/json');

$booking = new Mori\Booking();
$method = $_SERVER['REQUEST_METHOD'];
$userId = currentUserId();

switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        if ($action === 'search') {
            $origin = $data['origin'] ?? '';
            $destination = $data['destination'] ?? '';
            $date = $data['date'] ?? '';
            $passengers = $data['passengers'] ?? 1;
            try {
                $result = $booking->searchRoutes($origin, $destination, $date, $passengers);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } elseif ($action === 'lock') {
            $scheduleId = $data['schedule_id'] ?? 0;
            $seats = $data['seats'] ?? [];
            $passengerDetails = $data['passenger_details'] ?? [];
            try {
                $result = $booking->lockSeats($scheduleId, $seats, $userId, $passengerDetails);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } elseif ($action === 'confirm') {
            $bookingId = $data['booking_id'] ?? 0;
            $token = $data['token'] ?? '';
            $paymentData = $data['payment'] ?? [];
            try {
                $result = $booking->confirmBooking($bookingId, $token, $paymentData);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } elseif ($action === 'cancel') {
            $bookingId = $data['booking_id'] ?? 0;
            $reason = $data['reason'] ?? '';
            try {
                $result = $booking->cancelBooking($bookingId, $userId, $reason);
                echo json_encode(['success' => true, 'data' => $result]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;

    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'my_bookings') {
            $status = $_GET['status'] ?? null;
            $bookings = $booking->getUserBookings($userId, $status);
            echo json_encode(['success' => true, 'data' => $bookings]);
        } elseif ($action === 'available_seats') {
            $scheduleId = $_GET['schedule_id'] ?? 0;
            try {
                $seats = $booking->getAvailableSeats($scheduleId);
                echo json_encode(['success' => true, 'data' => $seats]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}