<?php
namespace Mori;

class Booking {
    private $db;
    private $user;
    private $payment;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->user = new User();
        $this->payment = new Payment();
    }
    
    public function searchRoutes($origin, $destination, $date, $passengers = 1) {
        // Validate inputs
        if (empty($origin) || empty($destination) || empty($date)) {
            throw new \Exception("Origin, destination, and date are required");
        }
        
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            throw new \Exception("Cannot book for past dates");
        }
        
        // Get routes matching origin and destination
        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM schedules s 
                        WHERE s.route_id = r.id 
                        AND s.departure_date = :date 
                        AND s.status = 'scheduled'
                        AND s.available_seats >= :passengers) as available_schedules
                FROM routes r
                WHERE r.is_active = 1
                AND ((r.origin_city LIKE :origin AND r.destination_city LIKE :destination)
                     OR (r.origin_city LIKE :destination AND r.destination_city LIKE :origin))
                ORDER BY r.distance_km";
        
        $routes = $this->db->fetchAll($sql, [
            ':origin' => "%{$origin}%",
            ':destination' => "%{$destination}%",
            ':date' => $date,
            ':passengers' => $passengers
        ]);
        
        if (empty($routes)) {
            // Try to find routes with terminals
            $sql = "SELECT r.*, 
                           (SELECT COUNT(*) FROM schedules s 
                            WHERE s.route_id = r.id 
                            AND s.departure_date = :date 
                            AND s.status = 'scheduled'
                            AND s.available_seats >= :passengers) as available_schedules
                    FROM routes r
                    WHERE r.is_active = 1
                    AND ((r.origin_terminal LIKE :origin OR r.destination_terminal LIKE :destination)
                         OR (r.origin_terminal LIKE :destination OR r.destination_terminal LIKE :origin))
                    ORDER BY r.distance_km";
            
            $routes = $this->db->fetchAll($sql, [
                ':origin' => "%{$origin}%",
                ':destination' => "%{$destination}%",
                ':date' => $date,
                ':passengers' => $passengers
            ]);
        }
        
        // For each route, get schedules
        foreach ($routes as &$route) {
            $route['schedules'] = $this->getSchedulesForRoute($route['id'], $date, $passengers);
            $route['stops'] = $this->getRouteStops($route['id']);
        }
        
        return $routes;
    }
    
    public function getSchedulesForRoute($routeId, $date, $passengers = 1) {
        $sql = "SELECT s.*, b.bus_number, b.bus_name, b.bus_type, b.total_seats, b.amenities,
                       b.seat_layout, b.image_path,
                       u.first_name as driver_first_name, u.last_name as driver_last_name,
                       CASE 
                           WHEN b.bus_type = 'premium' THEN r.premium_fare * s.price_factor
                           WHEN b.bus_type = 'luxury' THEN r.luxury_fare * s.price_factor
                           WHEN b.bus_type = 'executive' THEN r.luxury_fare * s.price_factor * 1.2
                           ELSE r.base_fare * s.price_factor
                       END as fare_per_seat,
                       TIMESTAMPDIFF(HOUR, CONCAT(s.departure_date, ' ', s.departure_time), 
                                           CONCAT(s.arrival_date, ' ', s.arrival_time)) as duration_hours
                FROM schedules s
                JOIN buses b ON s.bus_id = b.id
                JOIN routes r ON s.route_id = r.id
                LEFT JOIN users u ON s.driver_id = u.id
                WHERE s.route_id = :route_id
                AND s.departure_date = :date
                AND s.status = 'scheduled'
                AND s.available_seats >= :passengers
                AND s.departure_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY s.departure_time";
        
        $schedules = $this->db->fetchAll($sql, [
            ':route_id' => $routeId,
            ':date' => $date,
            ':passengers' => $passengers
        ]);
        
        // Apply dynamic pricing based on demand
        foreach ($schedules as &$schedule) {
            // Adjust price based on demand (more seats booked = higher price)
            $demandFactor = 1.0;
            $occupancy = ($schedule['total_seats'] - $schedule['available_seats']) / $schedule['total_seats'];
            
            if ($occupancy > 0.8) {
                $demandFactor = 1.3; // 30% increase when almost full
            } elseif ($occupancy > 0.6) {
                $demandFactor = 1.2; // 20% increase when 60-80% full
            } elseif ($occupancy > 0.4) {
                $demandFactor = 1.1; // 10% increase when 40-60% full
            }
            
            // Weekend/holiday pricing
            $dayOfWeek = date('N', strtotime($date));
            if ($dayOfWeek >= 6) { // Saturday or Sunday
                $demandFactor *= 1.15;
            }
            
            // Peak hours (6-9 AM, 4-7 PM)
            $departureHour = (int)date('H', strtotime($schedule['departure_time']));
            if (($departureHour >= 6 && $departureHour <= 9) || ($departureHour >= 16 && $departureHour <= 19)) {
                $demandFactor *= 1.2;
            }
            
            $schedule['final_fare_per_seat'] = round($schedule['fare_per_seat'] * $demandFactor, 2);
            $schedule['demand_factor'] = $demandFactor;
            
            // Get seat layout
            $schedule['seat_layout_data'] = $this->getSeatLayout($schedule['bus_id']);
        }
        
        return $schedules;
    }
    
    public function getAvailableSeats($scheduleId, $gender = null) {
        $sql = "SELECT seat_number, seat_type, seat_row, seat_column, status, 
                       gender_preference, locked_until
                FROM seat_maps
                WHERE schedule_id = :schedule_id
                AND status IN ('available', 'reserved')
                AND (locked_until IS NULL OR locked_until < NOW())
                ORDER BY seat_row, seat_column";
        
        $seats = $this->db->fetchAll($sql, [':schedule_id' => $scheduleId]);
        
        // Apply gender preferences if specified
        if ($gender && in_array($gender, ['male', 'female'])) {
            $filteredSeats = [];
            foreach ($seats as $seat) {
                if ($seat['gender_preference'] === 'none' || $seat['gender_preference'] === $gender) {
                    $filteredSeats[] = $seat;
                }
            }
            return $filteredSeats;
        }
        
        return $seats;
    }
    
    public function lockSeats($scheduleId, $seatNumbers, $userId, $passengerDetails = []) {
        // Validate schedule
        $schedule = $this->getSchedule($scheduleId);
        if (!$schedule || $schedule['status'] !== 'scheduled') {
            throw new \Exception("Schedule not available");
        }
        
        // Validate seat count
        if (count($seatNumbers) > MAX_SEATS_PER_BOOKING) {
            throw new \Exception("Maximum " . MAX_SEATS_PER_BOOKING . " seats per booking");
        }
        
        // Check if seats are available
        $availableSeats = $this->getAvailableSeats($scheduleId);
        $availableSeatNumbers = array_column($availableSeats, 'seat_number');
        
        foreach ($seatNumbers as $seatNumber) {
            if (!in_array($seatNumber, $availableSeatNumbers)) {
                throw new \Exception("Seat {$seatNumber} is not available");
            }
        }
        
        $this->db->beginTransaction();
        
        try {
            // Generate booking reference and token
            $bookingRef = $this->generateBookingRef();
            $bookingToken = bin2hex(random_bytes(32));
            $tokenExpires = date('Y-m-d H:i:s', time() + SEAT_LOCK_DURATION);
            
            // Calculate total amount
            $seatCount = count($seatNumbers);
            $totalAmount = round($schedule['final_fare_per_seat'] * $seatCount, 2);
            
            // Check for free trip eligibility
            $userData = $this->user->getUser($userId);
            $isFreeTrip = false;
            
            if ($userData['free_trips_available'] > 0) {
                $isFreeTrip = true;
                $totalAmount = 0;
            }
            
            // Create booking record
            $bookingId = $this->db->insert('bookings', [
                'booking_ref' => $bookingRef,
                'user_id' => $userId,
                'schedule_id' => $scheduleId,
                'seat_numbers' => json_encode($seatNumbers),
                'total_seats' => $seatCount,
                'total_amount' => $totalAmount,
                'payment_status' => $isFreeTrip ? 'paid' : 'pending',
                'booking_status' => 'pending',
                'payment_method' => $isFreeTrip ? 'free_trip' : 'pending',
                'is_free_trip' => $isFreeTrip ? 1 : 0,
                'booking_token' => $bookingToken,
                'token_expires_at' => $tokenExpires,
                'passenger_details' => json_encode($passengerDetails)
            ]);
            
            // Lock seats
            $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
            $params = array_merge([$bookingId, $userId, date('Y-m-d H:i:s', time() + SEAT_LOCK_DURATION), $scheduleId], $seatNumbers);
            
            $sql = "UPDATE seat_maps 
                    SET status = 'reserved', 
                        booking_id = ?, 
                        locked_by = ?, 
                        locked_until = ?
                    WHERE schedule_id = ? 
                    AND seat_number IN ({$placeholders})";
            
            $this->db->query($sql, $params);
            
            // Update schedule seat count
            $this->db->update('schedules', [
                'available_seats' => $schedule['available_seats'] - $seatCount
            ], 'id = :id', [':id' => $scheduleId]);
            
            // If free trip, mark it as used
            if ($isFreeTrip) {
                $this->db->update('users', [
                    'free_trips_available' => $userData['free_trips_available'] - 1
                ], 'id = :id', [':id' => $userId]);
                
                // Mark booking as confirmed
                $this->confirmBooking($bookingId, $bookingToken);
            }
            
            $this->db->commit();
            
            return [
                'booking_id' => $bookingId,
                'booking_ref' => $bookingRef,
                'booking_token' => $bookingToken,
                'token_expires' => $tokenExpires,
                'total_amount' => $totalAmount,
                'is_free_trip' => $isFreeTrip,
                'seat_numbers' => $seatNumbers
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function confirmBooking($bookingId, $token, $paymentData = null) {
        // Validate token and booking
        $sql = "SELECT b.*, s.bus_id, s.route_id, s.departure_date, s.departure_time,
                       u.email, u.phone, u.first_name, u.last_name,
                       r.origin_city, r.destination_city, r.base_fare
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                JOIN users u ON b.user_id = u.id
                JOIN routes r ON s.route_id = r.id
                WHERE b.id = :booking_id 
                AND b.booking_token = :token
                AND b.token_expires_at > NOW()
                AND b.booking_status = 'pending'";
        
        $booking = $this->db->fetch($sql, [
            ':booking_id' => $bookingId,
            ':token' => $token
        ]);
        
        if (!$booking) {
            throw new \Exception("Invalid or expired booking");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update booking status
            $updateData = [
                'booking_status' => 'confirmed',
                'token_expires_at' => null
            ];
            
            // If payment data provided, update payment info
            if ($paymentData) {
                $updateData['payment_method'] = $paymentData['method'];
                $updateData['amount_paid'] = $paymentData['amount'];
                $updateData['payment_status'] = $paymentData['amount'] >= $booking['total_amount'] ? 'paid' : 'partial';
                
                if ($paymentData['method'] === 'mpesa') {
                    $updateData['mpesa_receipt'] = $paymentData['mpesa_receipt'] ?? null;
                } elseif ($paymentData['method'] === 'card') {
                    $updateData['card_transaction_id'] = $paymentData['transaction_id'] ?? null;
                }
            }
            
            $this->db->update('bookings', $updateData, 'id = :id', [':id' => $bookingId]);
            
            // Update seat status to booked
            $seatNumbers = json_decode($booking['seat_numbers'], true);
            $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
            $params = array_merge(['booked', $bookingId, null, null, $booking['schedule_id']], $seatNumbers);
            
            $sql = "UPDATE seat_maps 
                    SET status = ?, 
                        booking_id = ?, 
                        locked_by = ?, 
                        locked_until = ?
                    WHERE schedule_id = ? 
                    AND seat_number IN ({$placeholders})";
            
            $this->db->query($sql, $params);
            
            // Update booked seats count
            $this->db->update('schedules', [
                'booked_seats' => $booking['booked_seats'] + $booking['total_seats']
            ], 'id = :id', [':id' => $booking['schedule_id']]);
            
            // Update user trip count and loyalty points (if not free trip)
            if (!$booking['is_free_trip']) {
                $user = $this->user->getUser($booking['user_id']);
                
                // Calculate loyalty points (1 point per 100 KES)
                $pointsEarned = floor($booking['total_amount'] / 100);
                
                $this->db->update('users', [
                    'total_trips' => $user['total_trips'] + 1,
                    'loyalty_points' => $user['loyalty_points'] + $pointsEarned
                ], 'id = :id', [':id' => $booking['user_id']]);
                
                // Record loyalty points
                $this->db->insert('loyalty_points', [
                    'user_id' => $booking['user_id'],
                    'points' => $pointsEarned,
                    'source' => 'booking',
                    'description' => "Points earned from booking {$booking['booking_ref']}",
                    'booking_id' => $bookingId,
                    'expires_at' => date('Y-m-d', strtotime('+1 year'))
                ]);
                
                // Check for free trip eligibility
                if (($user['total_trips'] + 1) % 10 === 0) {
                    $freeTripsEarned = floor(($user['total_trips'] + 1) / 10);
                    
                    $this->db->update('users', [
                        'free_trips_earned' => $freeTripsEarned,
                        'free_trips_available' => $freeTripsEarned
                    ], 'id = :id', [':id' => $booking['user_id']]);
                }
            }
            
            // Generate receipt number
            $receiptNumber = 'RCT' . date('Ymd') . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
            $this->db->update('bookings', [
                'receipt_number' => $receiptNumber
            ], 'id = :id', [':id' => $bookingId]);
            
            // Send confirmation notifications
            $this->sendBookingConfirmation($bookingId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'booking_ref' => $booking['booking_ref'],
                'receipt_number' => $receiptNumber,
                'total_amount' => $booking['total_amount']
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function cancelBooking($bookingId, $userId, $reason = '') {
        $sql = "SELECT b.*, s.departure_date, s.departure_time,
                       TIMESTAMPDIFF(HOUR, NOW(), CONCAT(s.departure_date, ' ', s.departure_time)) as hours_to_departure
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                WHERE b.id = :booking_id 
                AND b.user_id = :user_id
                AND b.booking_status = 'confirmed'";
        
        $booking = $this->db->fetch($sql, [
            ':booking_id' => $bookingId,
            ':user_id' => $userId
        ]);
        
        if (!$booking) {
            throw new \Exception("Booking not found or cannot be cancelled");
        }
        
        // Check cancellation deadline
        if ($booking['hours_to_departure'] < REFUND_DEADLINE_HOURS) {
            throw new \Exception("Cannot cancel within " . REFUND_DEADLINE_HOURS . " hours of departure");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update booking status
            $this->db->update('bookings', [
                'booking_status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_by' => $userId,
                'cancelled_at' => date('Y-m-d H:i:s')
            ], 'id = :id', [':id' => $bookingId]);
            
            // Free up seats
            $seatNumbers = json_decode($booking['seat_numbers'], true);
            $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
            $params = array_merge(['available', null, null, null, $booking['schedule_id']], $seatNumbers);
            
            $sql = "UPDATE seat_maps 
                    SET status = ?, 
                        booking_id = ?, 
                        locked_by = ?, 
                        locked_until = ?
                    WHERE schedule_id = ? 
                    AND seat_number IN ({$placeholders})";
            
            $this->db->query($sql, $params);
            
            // Update schedule seat count
            $this->db->update('schedules', [
                'available_seats' => $booking['available_seats'] + $booking['total_seats'],
                'booked_seats' => $booking['booked_seats'] - $booking['total_seats']
            ], 'id = :id', [':id' => $booking['schedule_id']]);
            
            // Process refund if paid
            if ($booking['payment_status'] === 'paid' && $booking['amount_paid'] > 0) {
                $refundAmount = $this->calculateRefundAmount($booking['amount_paid'], $booking['hours_to_departure']);
                
                $this->db->insert('refunds', [
                    'booking_id' => $bookingId,
                    'amount' => $refundAmount,
                    'reason' => $reason . " (User cancellation)",
                    'status' => 'approved',
                    'processed_by' => $userId,
                    'processed_at' => date('Y-m-d H:i:s')
                ]);
                
                // Update booking payment status
                $this->db->update('bookings', [
                    'payment_status' => 'refunded',
                    'amount_paid' => $booking['amount_paid'] - $refundAmount
                ], 'id = :id', [':id' => $bookingId]);
                
                // Send refund notification
                $this->sendRefundNotification($bookingId, $refundAmount);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'refund_amount' => $refundAmount ?? 0,
                'message' => 'Booking cancelled successfully'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getUserBookings($userId, $status = null, $limit = 20, $offset = 0) {
        $where = "WHERE b.user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($status) {
            $where .= " AND b.booking_status = :status";
            $params[':status'] = $status;
        }
        
        $sql = "SELECT b.*, 
                       s.departure_date, s.departure_time, s.arrival_date, s.arrival_time, s.status as schedule_status,
                       r.origin_city, r.origin_terminal, r.destination_city, r.destination_terminal,
                       bus.bus_number, bus.bus_name, bus.bus_type
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                JOIN buses bus ON s.bus_id = bus.id
                {$where}
                ORDER BY b.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getBookingDetails($bookingRef) {
        $sql = "SELECT b.*, 
                       s.departure_date, s.departure_time, s.arrival_date, s.arrival_time, 
                       s.status as schedule_status, s.price_factor,
                       r.origin_city, r.origin_terminal, r.destination_city, r.destination_terminal,
                       r.distance_km, r.estimated_hours,
                       bus.bus_number, bus.bus_name, bus.bus_type, bus.total_seats, bus.amenities,
                       u.first_name, u.last_name, u.email, u.phone,
                       driver.first_name as driver_first_name, driver.last_name as driver_last_name
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                JOIN buses bus ON s.bus_id = bus.id
                JOIN users u ON b.user_id = u.id
                LEFT JOIN users driver ON s.driver_id = driver.id
                WHERE b.booking_ref = :booking_ref";
        
        $booking = $this->db->fetch($sql, [':booking_ref' => $bookingRef]);
        
        if ($booking) {
            $booking['seat_numbers'] = json_decode($booking['seat_numbers'], true);
            $booking['passenger_details'] = json_decode($booking['passenger_details'], true);
            $booking['amenities'] = json_decode($booking['amenities'], true);
        }
        
        return $booking;
    }
    
    public function handleMissedTrip($bookingId) {
        $sql = "SELECT b.*, s.departure_date, s.departure_time,
                       TIMESTAMPDIFF(HOUR, CONCAT(s.departure_date, ' ', s.departure_time), NOW()) as hours_since_departure
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                WHERE b.id = :booking_id 
                AND b.booking_status = 'confirmed'
                AND s.status = 'departed'
                AND b.missed_trip_handled = 0";
        
        $booking = $this->db->fetch($sql, [':booking_id' => $bookingId]);
        
        if (!$booking) {
            return false;
        }
        
        // If within 2 hours of departure, try to rebook
        if ($booking['hours_since_departure'] <= 2) {
            $nextSchedule = $this->findNextAvailableSchedule($booking['schedule_id']);
            
            if ($nextSchedule) {
                return $this->rebookToNextSchedule($bookingId, $nextSchedule['id']);
            }
        }
        
        // Otherwise process as no-show with possible refund
        return $this->processNoShow($bookingId);
    }
    
    public function assignCustomerToBus($bookingId, $adminId, $newScheduleId = null) {
        $booking = $this->getBookingDetailsById($bookingId);
        
        if (!$booking || $booking['booking_status'] !== 'confirmed') {
            throw new \Exception("Booking not found or not confirmed");
        }
        
        // Check if current bus is full
        $sql = "SELECT available_seats FROM schedules WHERE id = :schedule_id";
        $schedule = $this->db->fetch($sql, [':schedule_id' => $booking['schedule_id']]);
        
        if ($schedule['available_seats'] <= 0 || $newScheduleId) {
            // Find next available bus
            if (!$newScheduleId) {
                $nextSchedule = $this->findNextAvailableSchedule($booking['schedule_id']);
                if (!$nextSchedule) {
                    throw new \Exception("No available buses on this route");
                }
                $newScheduleId = $nextSchedule['id'];
            }
            
            // Move booking to new schedule
            return $this->rebookToNextSchedule($bookingId, $newScheduleId, $adminId);
        }
        
        // Mark as assigned (completed)
        $this->db->update('bookings', [
            'booking_status' => 'completed'
        ], 'id = :id', [':id' => $bookingId]);
        
        // Log assignment
        $this->logActivity($adminId, 'bus_assignment', "Assigned booking {$booking['booking_ref']} to bus");
        
        return true;
    }
    
    public function getBookingStats($startDate = null, $endDate = null) {
        $where = "WHERE b.created_at IS NOT NULL";
        $params = [];
        
        if ($startDate && $endDate) {
            $where = "WHERE DATE(b.created_at) BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $stats = [];
        
        // Total bookings
        $stats['total_bookings'] = $this->db->fetchColumn("
            SELECT COUNT(*) FROM bookings b {$where}
        ", $params);
        
        // Total revenue
        $stats['total_revenue'] = $this->db->fetchColumn("
            SELECT COALESCE(SUM(amount_paid), 0) FROM bookings b {$where} AND payment_status = 'paid'
        ", $params);
        
        // Bookings by status
        $stats['by_status'] = $this->db->fetchAll("
            SELECT booking_status, COUNT(*) as count
            FROM bookings b {$where}
            GROUP BY booking_status
            ORDER BY count DESC
        ", $params);
        
        // Bookings by payment method
        $stats['by_payment_method'] = $this->db->fetchAll("
            SELECT payment_method, COUNT(*) as count, SUM(amount_paid) as total
            FROM bookings b {$where}
            GROUP BY payment_method
            ORDER BY total DESC
        ", $params);
        
        // Daily bookings for last 30 days
        $stats['daily_trend'] = $this->db->fetchAll("
            SELECT DATE(created_at) as date, COUNT(*) as bookings, SUM(amount_paid) as revenue
            FROM bookings
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        
        // Top routes
        $stats['top_routes'] = $this->db->fetchAll("
            SELECT r.origin_city, r.destination_city, COUNT(b.id) as bookings
            FROM bookings b
            JOIN schedules s ON b.schedule_id = s.id
            JOIN routes r ON s.route_id = r.id
            {$where}
            GROUP BY r.origin_city, r.destination_city
            ORDER BY bookings DESC
            LIMIT 10
        ", $params);
        
        return $stats;
    }
    
    private function generateBookingRef() {
        $prefix = 'MB';
        $date = date('ymd');
        $random = strtoupper(substr(md5(microtime()), 0, 6));
        return $prefix . $date . $random;
    }
    
    private function getSchedule($scheduleId) {
        $sql = "SELECT s.*, b.total_seats FROM schedules s 
                JOIN buses b ON s.bus_id = b.id 
                WHERE s.id = :id";
        return $this->db->fetch($sql, [':id' => $scheduleId]);
    }
    
    private function getRouteStops($routeId) {
        $sql = "SELECT stop_name, stop_order, distance_from_origin, fare_from_origin
                FROM route_stops
                WHERE route_id = :route_id
                ORDER BY stop_order";
        return $this->db->fetchAll($sql, [':route_id' => $routeId]);
    }
    
    private function getSeatLayout($busId) {
        $sql = "SELECT seat_layout, total_seats FROM buses WHERE id = :id";
        return $this->db->fetch($sql, [':id' => $busId]);
    }
    
    private function calculateRefundAmount($amountPaid, $hoursToDeparture) {
        if ($hoursToDeparture >= 48) {
            return $amountPaid * 0.9; // 90% refund
        } elseif ($hoursToDeparture >= 24) {
            return $amountPaid * 0.75; // 75% refund
        } elseif ($hoursToDeparture >= 12) {
            return $amountPaid * 0.5; // 50% refund
        } else {
            return $amountPaid * 0.25; // 25% refund
        }
    }
    
    private function findNextAvailableSchedule($currentScheduleId) {
        $sql = "SELECT s.* FROM schedules s
                WHERE s.route_id = (
                    SELECT route_id FROM schedules WHERE id = :current_id
                )
                AND s.departure_time > (
                    SELECT departure_time FROM schedules WHERE id = :current_id
                )
                AND s.available_seats > 0
                AND s.status = 'scheduled'
                ORDER BY s.departure_time
                LIMIT 1";
        
        return $this->db->fetch($sql, [':current_id' => $currentScheduleId]);
    }
    
    private function rebookToNextSchedule($bookingId, $newScheduleId, $adminId = null) {
        $this->db->beginTransaction();
        
        try {
            // Get booking details
            $booking = $this->getBookingDetailsById($bookingId);
            
            // Free seats from old schedule
            $seatNumbers = json_decode($booking['seat_numbers'], true);
            $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
            $params = array_merge(['available', null, null, null, $booking['schedule_id']], $seatNumbers);
            
            $sql = "UPDATE seat_maps 
                    SET status = ?, 
                        booking_id = ?, 
                        locked_by = ?, 
                        locked_until = ?
                    WHERE schedule_id = ? 
                    AND seat_number IN ({$placeholders})";
            
            $this->db->query($sql, $params);
            
            // Update old schedule seat count
            $this->db->update('schedules', [
                'available_seats' => $booking['available_seats'] + $booking['total_seats'],
                'booked_seats' => $booking['booked_seats'] - $booking['total_seats']
            ], 'id = :id', [':id' => $booking['schedule_id']]);
            
            // Assign to new schedule
            $this->db->update('bookings', [
                'schedule_id' => $newScheduleId,
                'booking_status' => 'rebooked',
                'missed_trip_handled' => 1
            ], 'id = :id', [':id' => $bookingId]);
            
            // Get new schedule
            $newSchedule = $this->getSchedule($newScheduleId);
            
            // Reserve seats in new schedule
            $params = array_merge([$bookingId, $booking['user_id'], date('Y-m-d H:i:s', time() + 3600), $newScheduleId], $seatNumbers);
            
            $sql = "UPDATE seat_maps 
                    SET status = 'reserved', 
                        booking_id = ?, 
                        locked_by = ?, 
                        locked_until = ?
                    WHERE schedule_id = ? 
                    AND seat_number IN ({$placeholders})";
            
            $this->db->query($sql, $params);
            
            // Update new schedule seat count
            $this->db->update('schedules', [
                'available_seats' => $newSchedule['available_seats'] - $booking['total_seats'],
                'booked_seats' => $newSchedule['booked_seats'] + $booking['total_seats']
            ], 'id = :id', [':id' => $newScheduleId]);
            
            // Send rebooking notification
            $this->sendRebookingNotification($bookingId, $newScheduleId);
            
            // Log activity
            if ($adminId) {
                $this->logActivity($adminId, 'rebooking', "Rebooked booking {$booking['booking_ref']} to new schedule");
            }
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function processNoShow($bookingId) {
        $this->db->update('bookings', [
            'booking_status' => 'no_show',
            'missed_trip_handled' => 1
        ], 'id = :id', [':id' => $bookingId]);
        
        // Send no-show notification
        $this->sendNoShowNotification($bookingId);
        
        return true;
    }
    
    private function getBookingDetailsById($bookingId) {
        $sql = "SELECT b.*, s.available_seats, s.booked_seats 
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                WHERE b.id = :id";
        return $this->db->fetch($sql, [':id' => $bookingId]);
    }
    
    private function sendBookingConfirmation($bookingId) {
        $booking = $this->getBookingDetailsById($bookingId);
        $user = $this->user->getUser($booking['user_id']);
        
        // Generate PDF receipt
        $pdfGenerator = new PDFGenerator();
        $pdfPath = $pdfGenerator->generateReceipt($booking);
        
        // Send email
        $emailService = new Email();
        $emailSent = $emailService->sendBookingConfirmation($user['email'], $booking, $pdfPath);
        
        // Send SMS
        $smsService = new SMS();
        $smsSent = $smsService->sendBookingConfirmation($user['phone'], $booking);
        
        // Record notification
        $this->db->insert('notifications', [
            'user_id' => $user['id'],
            'type' => 'booking',
            'title' => 'Booking Confirmation',
            'message' => "Your booking {$booking['booking_ref']} has been confirmed",
            'medium' => $emailSent && $smsSent ? 'both' : ($emailSent ? 'email' : 'sms'),
            'status' => $emailSent || $smsSent ? 'sent' : 'failed',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function sendRefundNotification($bookingId, $refundAmount) {
        $booking = $this->getBookingDetailsById($bookingId);
        $user = $this->user->getUser($booking['user_id']);
        
        $subject = "Refund Processed - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "A refund of KES " . number_format($refundAmount, 2) . " has been processed for your cancelled booking {$booking['booking_ref']}.\n\n";
        $message .= "The refund will reflect in your account within 3-5 business days.\n\n";
        $message .= "Thank you,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS
        $smsMessage = "MORI: Refund of KES " . number_format($refundAmount, 2) . " processed for booking {$booking['booking_ref']}. Will reflect in 3-5 days.";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function sendRebookingNotification($bookingId, $newScheduleId) {
        $booking = $this->getBookingDetailsById($bookingId);
        $newSchedule = $this->getSchedule($newScheduleId);
        $user = $this->user->getUser($booking['user_id']);
        
        $subject = "Booking Rebooked - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "Your booking {$booking['booking_ref']} has been rebooked to a new schedule due to missed trip.\n\n";
        $message .= "New Departure: " . date('M d, Y H:i', strtotime($newSchedule['departure_date'] . ' ' . $newSchedule['departure_time'])) . "\n";
        $message .= "Please arrive at least 30 minutes before departure.\n\n";
        $message .= "Thank you,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS
        $smsMessage = "MORI: Your booking {$booking['booking_ref']} rebooked. New departure: " . date('M d, H:i', strtotime($newSchedule['departure_date'] . ' ' . $newSchedule['departure_time']));
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function sendNoShowNotification($bookingId) {
        $booking = $this->getBookingDetailsById($bookingId);
        $user = $this->user->getUser($booking['user_id']);
        
        $subject = "Missed Trip - MORI BOOKINGS";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "We noticed you missed your scheduled trip for booking {$booking['booking_ref']}.\n\n";
        $message .= "As per our policy, missed trips may result in forfeiture of payment.\n";
        $message .= "Please contact our support for assistance.\n\n";
        $message .= "Thank you,\n";
        $message .= "MORI BOOKINGS Team";
        
        $emailService = new Email();
        $emailService->send($user['email'], $subject, $message);
        
        // SMS
        $smsMessage = "MORI: You missed your trip for booking {$booking['booking_ref']}. Contact support for assistance.";
        $smsService = new SMS();
        $smsService->send($user['phone'], $smsMessage);
    }
    
    private function logActivity($userId, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => 'bookings',
            'record_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ]);
    }
}