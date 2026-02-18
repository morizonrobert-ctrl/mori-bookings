<?php
namespace Mori;

class SeatMap {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function generateSeatMap($busId, $scheduleId = null) {
        // Get bus details and seat layout
        $sql = "SELECT * FROM buses WHERE id = ?";
        $bus = $this->db->fetch($sql, [$busId]);
        
        if (!$bus) {
            throw new \Exception("Bus not found");
        }
        
        // Parse seat layout (2x2, 2x3, 3x3, etc.)
        $layout = $bus['seat_layout'] ?? '2x2';
        list($cols, $rows) = explode('x', $layout);
        $totalSeats = $bus['total_seats'];
        
        // Get seat statuses for this schedule
        $occupiedSeats = [];
        if ($scheduleId) {
            $sql = "SELECT seat_number, status, seat_type FROM seat_maps 
                    WHERE schedule_id = ? AND status IN ('booked', 'reserved')";
            $occupied = $this->db->fetchAll($sql, [$scheduleId]);
            
            foreach ($occupied as $seat) {
                $occupiedSeats[$seat['seat_number']] = [
                    'status' => $seat['status'],
                    'type' => $seat['seat_type']
                ];
            }
        }
        
        // Generate seat layout
        $seatMap = [
            'bus' => $bus,
            'layout' => $layout,
            'total_seats' => $totalSeats,
            'columns' => intval($cols),
            'rows' => intval($rows),
            'seats' => [],
            'legend' => $this->getSeatLegend()
        ];
        
        // Generate seat grid
        $seatGrid = $this->generateSeatGrid($layout, $totalSeats, $occupiedSeats);
        $seatMap['seats'] = $seatGrid;
        
        return $seatMap;
    }
    
    private function generateSeatGrid($layout, $totalSeats, $occupiedSeats) {
        $seats = [];
        $seatTypes = ['window', 'aisle', 'extra_legroom', 'standard'];
        
        // For 2x2 layout: A1, A2, B1, B2, etc.
        // For 2x3 layout: A1, A2, A3, B1, B2, B3, etc.
        list($cols, $rows) = explode('x', $layout);
        $cols = intval($cols);
        $rows = intval($rows);
        
        $seatCount = 0;
        $rowLetters = range('A', 'Z');
        
        for ($row = 0; $row < $rows; $row++) {
            $rowLetter = $rowLetters[$row];
            
            for ($col = 1; $col <= $cols; $col++) {
                if ($seatCount >= $totalSeats) break;
                
                $seatNumber = $rowLetter . $col;
                $seatCount++;
                
                // Determine seat type based on position
                $seatType = $this->determineSeatType($col, $cols, $layout);
                
                // Check if seat is occupied
                $status = isset($occupiedSeats[$seatNumber]) ? $occupiedSeats[$seatNumber]['status'] : 'available';
                
                $seats[] = [
                    'number' => $seatNumber,
                    'row' => $row + 1,
                    'column' => $col,
                    'type' => $seatType,
                    'status' => $status,
                    'class' => $this->getSeatClass($status, $seatType),
                    'icon' => $this->getSeatIcon($seatType),
                    'price_modifier' => $this->getPriceModifier($seatType),
                    'description' => $this->getSeatDescription($seatType)
                ];
            }
            
            // Add aisle if needed
            if ($cols > 2 && $row < $rows - 1) {
                $seats[] = [
                    'number' => 'aisle_' . $row,
                    'type' => 'aisle',
                    'status' => 'aisle',
                    'class' => 'aisle',
                    'is_aisle' => true
                ];
            }
        }
        
        return $seats;
    }
    
    private function determineSeatType($column, $totalColumns, $layout) {
        // Window seats are first and last column
        if ($column == 1 || $column == $totalColumns) {
            return 'window';
        }
        
        // Aisle seats are second and second-last in 2x3 or 3x3 layouts
        if ($totalColumns >= 3) {
            if ($column == 2 || $column == $totalColumns - 1) {
                return 'aisle';
            }
        }
        
        // Extra legroom seats (first row usually)
        // This can be customized based on business rules
        
        return 'standard';
    }
    
    private function getSeatClass($status, $type) {
        $classes = [];
        $classes[] = 'seat';
        $classes[] = 'type-' . $type;
        $classes[] = 'status-' . $status;
        
        // Add specific classes for special types
        if ($type == 'window') $classes[] = 'window-seat';
        if ($type == 'aisle') $classes[] = 'aisle-seat';
        if ($type == 'extra_legroom') $classes[] = 'extra-legroom';
        
        return implode(' ', $classes);
    }
    
    private function getSeatIcon($type) {
        $icons = [
            'window' => 'fa-window-maximize',
            'aisle' => 'fa-walking',
            'extra_legroom' => 'fa-arrows-alt-v',
            'standard' => 'fa-chair'
        ];
        
        return $icons[$type] ?? 'fa-chair';
    }
    
    private function getPriceModifier($type) {
        $modifiers = [
            'window' => 1.1,   // 10% extra
            'aisle' => 1.05,   // 5% extra
            'extra_legroom' => 1.2,  // 20% extra
            'standard' => 1.0
        ];
        
        return $modifiers[$type] ?? 1.0;
    }
    
    private function getSeatDescription($type) {
        $descriptions = [
            'window' => 'Window seat with view',
            'aisle' => 'Aisle seat for easy access',
            'extra_legroom' => 'Extra legroom for comfort',
            'standard' => 'Standard seat'
        ];
        
        return $descriptions[$type] ?? 'Standard seat';
    }
    
    private function getSeatLegend() {
        return [
            ['class' => 'seat-available', 'label' => 'Available', 'icon' => 'fa-circle', 'color' => '#4CAF50'],
            ['class' => 'seat-selected', 'label' => 'Selected', 'icon' => 'fa-check-circle', 'color' => '#2196F3'],
            ['class' => 'seat-booked', 'label' => 'Booked', 'icon' => 'fa-times-circle', 'color' => '#F44336'],
            ['class' => 'seat-reserved', 'label' => 'Reserved', 'icon' => 'fa-clock', 'color' => '#FF9800'],
            ['class' => 'seat-window', 'label' => 'Window Seat', 'icon' => 'fa-window-maximize', 'color' => '#9C27B0'],
            ['class' => 'seat-aisle', 'label' => 'Aisle Seat', 'icon' => 'fa-walking', 'color' => '#009688'],
            ['class' => 'seat-extra', 'label' => 'Extra Legroom', 'icon' => 'fa-arrows-alt-v', 'color' => '#FF5722']
        ];
    }
    
    public function getInteractiveSeatMap($scheduleId, $selectedSeats = []) {
        // Get schedule and bus details
        $sql = "SELECT s.*, b.*, r.base_fare, r.premium_fare, r.luxury_fare
                FROM schedules s
                JOIN buses b ON s.bus_id = b.id
                JOIN routes r ON s.route_id = r.id
                WHERE s.id = ?";
        
        $schedule = $this->db->fetch($sql, [$scheduleId]);
        
        if (!$schedule) {
            throw new \Exception("Schedule not found");
        }
        
        // Generate seat map
        $seatMap = $this->generateSeatMap($schedule['bus_id'], $scheduleId);
        
        // Get available seats count
        $availableSeats = $this->getAvailableSeatsCount($scheduleId);
        
        // Get fare based on bus type
        $baseFare = $schedule['base_fare'];
        if ($schedule['bus_type'] == 'premium') {
            $baseFare = $schedule['premium_fare'];
        } elseif ($schedule['bus_type'] == 'luxury' || $schedule['bus_type'] == 'executive') {
            $baseFare = $schedule['luxury_fare'];
        }
        
        // Apply dynamic pricing factor
        $finalFare = $baseFare * $schedule['price_factor'];
        
        return [
            'schedule' => $schedule,
            'seat_map' => $seatMap,
            'available_seats' => $availableSeats,
            'base_fare' => $finalFare,
            'selected_seats' => $selectedSeats,
            'seat_prices' => $this->calculateSeatPrices($finalFare, $seatMap['seats'])
        ];
    }
    
    public function getAvailableSeatsCount($scheduleId) {
        $sql = "SELECT COUNT(*) as count FROM seat_maps 
                WHERE schedule_id = ? 
                AND status = 'available' 
                AND (locked_until IS NULL OR locked_until < NOW())";
        
        $result = $this->db->fetch($sql, [$scheduleId]);
        return $result['count'] ?? 0;
    }
    
    private function calculateSeatPrices($baseFare, $seats) {
        $prices = [];
        
        foreach ($seats as $seat) {
            if (isset($seat['is_aisle'])) continue;
            
            $price = $baseFare * ($seat['price_modifier'] ?? 1.0);
            $prices[$seat['number']] = [
                'base' => $baseFare,
                'modifier' => $seat['price_modifier'] ?? 1.0,
                'final' => round($price, 2),
                'type' => $seat['type'],
                'description' => $seat['description']
            ];
        }
        
        return $prices;
    }
    
    public function checkSeatAvailability($scheduleId, $seatNumbers) {
        if (empty($seatNumbers)) {
            return ['available' => false, 'message' => 'No seats selected'];
        }
        
        $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
        $params = array_merge([$scheduleId], $seatNumbers);
        
        $sql = "SELECT seat_number, status FROM seat_maps 
                WHERE schedule_id = ? 
                AND seat_number IN ($placeholders)";
        
        $seats = $this->db->fetchAll($sql, $params);
        
        $unavailable = [];
        foreach ($seats as $seat) {
            if ($seat['status'] != 'available') {
                $unavailable[] = [
                    'seat' => $seat['seat_number'],
                    'status' => $seat['status']
                ];
            }
        }
        
        if (!empty($unavailable)) {
            $unavailableSeats = array_column($unavailable, 'seat');
            return [
                'available' => false,
                'message' => 'Seats ' . implode(', ', $unavailableSeats) . ' are not available',
                'unavailable' => $unavailable
            ];
        }
        
        return ['available' => true, 'message' => 'All seats are available'];
    }
    
    public function getSeatSuggestions($scheduleId, $passengerCount, $preferences = []) {
        $sql = "SELECT seat_number, seat_type, seat_row, seat_column 
                FROM seat_maps 
                WHERE schedule_id = ? 
                AND status = 'available'
                AND (locked_until IS NULL OR locked_until < NOW())
                ORDER BY seat_row, seat_column";
        
        $availableSeats = $this->db->fetchAll($sql, [$scheduleId]);
        
        if (empty($availableSeats)) {
            return [];
        }
        
        $suggestions = [];
        
        // Group seats by row
        $seatsByRow = [];
        foreach ($availableSeats as $seat) {
            $row = $seat['seat_row'];
            if (!isset($seatsByRow[$row])) {
                $seatsByRow[$row] = [];
            }
            $seatsByRow[$row][] = $seat;
        }
        
        // Try to find seats together in the same row
        foreach ($seatsByRow as $row => $seats) {
            if (count($seats) >= $passengerCount) {
                // Check for consecutive seats
                $seatNumbers = array_column($seats, 'seat_number');
                $consecutiveGroups = $this->findConsecutiveSeats($seatNumbers, $passengerCount);
                
                if (!empty($consecutiveGroups)) {
                    foreach ($consecutiveGroups as $group) {
                        $suggestions[] = [
                            'seats' => $group,
                            'type' => 'together',
                            'row' => $row,
                            'description' => 'Seats together in row ' . $row
                        ];
                    }
                }
            }
        }
        
        // If no seats together, suggest best available
        if (empty($suggestions)) {
            $selectedSeats = array_slice($availableSeats, 0, $passengerCount);
            $seatNumbers = array_column($selectedSeats, 'seat_number');
            
            $suggestions[] = [
                'seats' => $seatNumbers,
                'type' => 'scattered',
                'description' => 'Best available seats'
            ];
        }
        
        // Filter by preferences
        if (!empty($preferences)) {
            $filteredSuggestions = [];
            foreach ($suggestions as $suggestion) {
                $matches = 0;
                foreach ($suggestion['seats'] as $seatNumber) {
                    $seatInfo = $this->getSeatInfo($availableSeats, $seatNumber);
                    if ($seatInfo && $this->matchesPreferences($seatInfo, $preferences)) {
                        $matches++;
                    }
                }
                if ($matches >= $passengerCount || empty($preferences)) {
                    $suggestion['match_score'] = $matches / $passengerCount;
                    $filteredSuggestions[] = $suggestion;
                }
            }
            
            // Sort by match score
            usort($filteredSuggestions, function($a, $b) {
                return $b['match_score'] <=> $a['match_score'];
            });
            
            $suggestions = $filteredSuggestions;
        }
        
        return array_slice($suggestions, 0, 3); // Return top 3 suggestions
    }
    
    private function findConsecutiveSeats($seatNumbers, $count) {
        $groups = [];
        
        // Sort seat numbers
        sort($seatNumbers);
        
        for ($i = 0; $i <= count($seatNumbers) - $count; $i++) {
            $group = array_slice($seatNumbers, $i, $count);
            
            // Check if seats are consecutive
            $consecutive = true;
            for ($j = 0; $j < $count - 1; $j++) {
                $current = $this->parseSeatNumber($group[$j]);
                $next = $this->parseSeatNumber($group[$j + 1]);
                
                if ($current['row'] != $next['row'] || 
                    $current['col'] + 1 != $next['col']) {
                    $consecutive = false;
                    break;
                }
            }
            
            if ($consecutive) {
                $groups[] = $group;
            }
        }
        
        return $groups;
    }
    
    private function parseSeatNumber($seatNumber) {
        preg_match('/([A-Z]+)(\d+)/', $seatNumber, $matches);
        return [
            'row' => $matches[1] ?? '',
            'col' => intval($matches[2] ?? 0)
        ];
    }
    
    private function getSeatInfo($seats, $seatNumber) {
        foreach ($seats as $seat) {
            if ($seat['seat_number'] == $seatNumber) {
                return $seat;
            }
        }
        return null;
    }
    
    private function matchesPreferences($seat, $preferences) {
        $match = true;
        
        if (isset($preferences['seat_type']) && !empty($preferences['seat_type'])) {
            if (!in_array($seat['seat_type'], $preferences['seat_type'])) {
                $match = false;
            }
        }
        
        if (isset($preferences['row_preference']) && !empty($preferences['row_preference'])) {
            if ($preferences['row_preference'] == 'front' && $seat['seat_row'] > 5) {
                $match = false;
            }
            if ($preferences['row_preference'] == 'middle' && 
                ($seat['seat_row'] <= 5 || $seat['seat_row'] > 10)) {
                $match = false;
            }
            if ($preferences['row_preference'] == 'back' && $seat['seat_row'] <= 10) {
                $match = false;
            }
        }
        
        return $match;
    }
    
    public function renderSeatMapHTML($seatMapData, $interactive = true) {
        $html = '';
        
        $html .= '<div class="seat-map-container">';
        $html .= '<div class="seat-map-header">';
        $html .= '<h3><i class="fas fa-chair"></i> Select Your Seats</h3>';
        $html .= '<p>Click on available seats to select. Selected seats will be reserved for 15 minutes.</p>';
        $html .= '</div>';
        
        // Seat legend
        $html .= $this->renderSeatLegend();
        
        // Bus layout
        $html .= '<div class="bus-layout">';
        $html .= '<div class="bus-front">';
        $html .= '<div class="driver-seat">';
        $html .= '<i class="fas fa-user-tie"></i>';
        $html .= '<span>Driver</span>';
        $html .= '</div>';
        $html .= '<div class="front-label">Front</div>';
        $html .= '</div>';
        
        // Seat grid
        $html .= '<div class="seat-grid">';
        
        $currentRow = null;
        foreach ($seatMapData['seats'] as $seat) {
            if (isset($seat['is_aisle'])) {
                $html .= '<div class="aisle"></div>';
                continue;
            }
            
            // New row indicator
            if ($seat['row'] != $currentRow) {
                if ($currentRow !== null) {
                    $html .= '<div class="row-label right">Row ' . $seat['row'] . '</div>';
                }
                $currentRow = $seat['row'];
                $html .= '<div class="row-label left">Row ' . $seat['row'] . '</div>';
            }
            
            $html .= $this->renderSeatHTML($seat, $interactive);
        }
        
        $html .= '</div>'; // .seat-grid
        
        // Bus rear
        $html .= '<div class="bus-rear">';
        $html .= '<div class="engine">Engine</div>';
        $html .= '<div class="rear-label">Rear</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // .bus-layout
        
        // Seat information panel
        $html .= '<div class="seat-info-panel">';
        $html .= '<div class="info-header">';
        $html .= '<h4><i class="fas fa-info-circle"></i> Seat Information</h4>';
        $html .= '</div>';
        $html .= '<div class="info-content">';
        $html .= '<div class="seat-details">';
        $html .= '<div class="detail-item"><span class="label">Total Seats:</span> <span class="value">' . $seatMapData['total_seats'] . '</span></div>';
        $html .= '<div class="detail-item"><span class="label">Layout:</span> <span class="value">' . $seatMapData['layout'] . '</span></div>';
        $html .= '<div class="detail-item"><span class="label">Available:</span> <span class="value available-count">' . count(array_filter($seatMapData['seats'], function($s) { 
            return !isset($s['is_aisle']) && $s['status'] == 'available'; 
        })) . '</span></div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // .seat-map-container
        
        return $html;
    }
    
    private function renderSeatLegend() {
        $legend = $this->getSeatLegend();
        
        $html = '<div class="seat-legend">';
        $html .= '<h5><i class="fas fa-key"></i> Legend:</h5>';
        $html .= '<div class="legend-items">';
        
        foreach ($legend as $item) {
            $html .= '<div class="legend-item">';
            $html .= '<span class="legend-color" style="background-color: ' . $item['color'] . '">';
            $html .= '<i class="fas ' . $item['icon'] . '"></i>';
            $html .= '</span>';
            $html .= '<span class="legend-label">' . $item['label'] . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderSeatHTML($seat, $interactive = true) {
        $html = '<div class="' . $seat['class'] . '"';
        $html .= ' data-seat-number="' . $seat['number'] . '"';
        $html .= ' data-seat-type="' . $seat['type'] . '"';
        $html .= ' data-seat-price="' . ($seat['price_modifier'] ?? 1.0) . '"';
        
        if ($interactive && $seat['status'] == 'available') {
            $html .= ' onclick="selectSeat(this)"';
            $html .= ' title="' . $seat['description'] . ' - Click to select"';
        } else {
            $html .= ' title="' . $seat['description'] . ' - ' . ucfirst($seat['status']) . '"';
        }
        
        $html .= '>';
        $html .= '<div class="seat-content">';
        $html .= '<div class="seat-number">' . $seat['number'] . '</div>';
        $html .= '<div class="seat-icon"><i class="fas ' . $seat['icon'] . '"></i></div>';
        
        if ($seat['status'] == 'booked') {
            $html .= '<div class="seat-status"><i class="fas fa-lock"></i></div>';
        } elseif ($seat['status'] == 'reserved') {
            $html .= '<div class="seat-status"><i class="fas fa-clock"></i></div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}