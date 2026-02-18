<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$scheduleId = isset($input['schedule_id']) ? intval($input['schedule_id']) : 0;
$action = isset($input['action']) ? $input['action'] : '';

if ($scheduleId <= 0) {
    echo json_encode(['error' => 'Invalid schedule ID']);
    exit;
}

$db = \Mori\Database::getInstance();
$seatMap = new \Mori\SeatMap();

switch ($action) {
    case 'check_availability':
        // Get current seat availability
        $sql = "SELECT seat_number, status FROM seat_maps 
                WHERE schedule_id = ? 
                AND status IN ('booked', 'reserved')";
        $unavailableSeats = $db->fetchAll($sql, [$scheduleId]);
        
        // Check if user's selected seats are still available
        $userSelectedSeats = isset($input['selected_seats']) ? json_decode($input['selected_seats'], true) : [];
        $conflicts = [];
        
        if (!empty($userSelectedSeats)) {
            $placeholders = implode(',', array_fill(0, count($userSelectedSeats), '?'));
            $params = array_merge([$scheduleId], $userSelectedSeats);
            
            $sql = "SELECT seat_number, status FROM seat_maps 
                    WHERE schedule_id = ? 
                    AND seat_number IN ($placeholders)
                    AND status IN ('booked', 'reserved')";
            
            $conflicts = $db->fetchAll($sql, $params);
        }
        
        echo json_encode([
            'success' => true,
            'schedule_id' => $scheduleId,
            'unavailable_seats' => $unavailableSeats,
            'conflicts' => $conflicts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'get_seat_map':
        // Get interactive seat map data
        try {
            $seatMapData = $seatMap->getInteractiveSeatMap($scheduleId);
            echo json_encode([
                'success' => true,
                'seat_map' => $seatMapData,
                'available_count' => $seatMap->getAvailableSeatsCount($scheduleId)
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'get_suggestions':
        $passengerCount = isset($input['passenger_count']) ? intval($input['passenger_count']) : 1;
        $preferences = isset($input['preferences']) ? $input['preferences'] : [];
        
        $suggestions = $seatMap->getSeatSuggestions($scheduleId, $passengerCount, $preferences);
        
        echo json_encode([
            'success' => true,
            'suggestions' => $suggestions,
            'passenger_count' => $passengerCount
        ]);
        break;
        
    case 'lock_seats':
        $seatNumbers = isset($input['seat_numbers']) ? json_decode($input['seat_numbers'], true) : [];
        $userId = $_SESSION['user_id'];
        
        if (empty($seatNumbers)) {
            echo json_encode(['success' => false, 'error' => 'No seats selected']);
            exit;
        }
        
        // Check availability first
        $availability = $seatMap->checkSeatAvailability($scheduleId, $seatNumbers);
        
        if (!$availability['available']) {
            echo json_encode([
                'success' => false,
                'error' => $availability['message'],
                'unavailable' => $availability['unavailable'] ?? []
            ]);
            exit;
        }
        
        // Lock seats
        $booking = new \Mori\Booking();
        
        try {
            $result = $booking->lockSeats($scheduleId, $seatNumbers, $userId);
            
            echo json_encode([
                'success' => true,
                'booking_id' => $result['booking_id'],
                'booking_ref' => $result['booking_ref'],
                'booking_token' => $result['booking_token'],
                'total_amount' => $result['total_amount'],
                'expires' => $result['token_expires']
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}