<?php
require_once '../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user = new \Mori\User();
$booking = new \Mori\Booking();
$seatMap = new \Mori\SeatMap();
$userData = $user->getUser($_SESSION['user_id']);

// Get parameters
$scheduleId = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$passengers = isset($_GET['passengers']) ? intval($_GET['passengers']) : 1;
$action = isset($_GET['action']) ? $_GET['action'] : 'select';

if ($scheduleId <= 0) {
    header('Location: book.php');
    exit;
}

// Get schedule details
$db = \Mori\Database::getInstance();
$sql = "SELECT s.*, b.bus_name, b.bus_number, b.bus_type, b.total_seats,
               r.origin_city, r.destination_city, r.base_fare,
               r.premium_fare, r.luxury_fare
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        WHERE s.id = ?";
$schedule = $db->fetch($sql, [$scheduleId]);

if (!$schedule) {
    echo "Schedule not found.";
    exit;
}

// Get selected seats from session or POST
$selectedSeats = $_SESSION['selected_seats'][$scheduleId] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_seats'])) {
    $selectedSeats = json_decode($_POST['selected_seats'], true) ?? [];
    $_SESSION['selected_seats'][$scheduleId] = $selectedSeats;
}

// Get seat suggestions if no seats selected
$suggestions = [];
if (empty($selectedSeats)) {
    $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : [];
    $suggestions = $seatMap->getSeatSuggestions($scheduleId, $passengers, $preferences);
}

// Get interactive seat map
$seatMapData = $seatMap->getInteractiveSeatMap($scheduleId, $selectedSeats);
$seatPrices = $seatMapData['seat_prices'];

// Calculate total price
$totalAmount = 0;
foreach ($selectedSeats as $seat) {
    if (isset($seatPrices[$seat])) {
        $totalAmount += $seatPrices[$seat]['final'];
    }
}

// Handle seat selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'lock_seats':
                if (count($selectedSeats) != $passengers) {
                    $error = "Please select exactly {$passengers} seat(s)";
                    break;
                }
                
                // Check seat availability
                $availability = $seatMap->checkSeatAvailability($scheduleId, $selectedSeats);
                if (!$availability['available']) {
                    $error = $availability['message'];
                    break;
                }
                
                // Lock seats and create booking
                try {
                    $result = $booking->lockSeats($scheduleId, $selectedSeats, $userData['id']);
                    
                    // Store booking info in session
                    $_SESSION['pending_booking'] = [
                        'booking_id' => $result['booking_id'],
                        'booking_token' => $result['booking_token'],
                        'booking_ref' => $result['booking_ref'],
                        'schedule_id' => $scheduleId,
                        'selected_seats' => $selectedSeats,
                        'total_amount' => $result['total_amount'],
                        'is_free_trip' => $result['is_free_trip'] ?? false,
                        'expires' => $result['token_expires']
                    ];
                    
                    // Clear selected seats from session
                    unset($_SESSION['selected_seats'][$scheduleId]);
                    
                    // Redirect to payment page
                    header('Location: payment.php?booking_id=' . $result['booking_id']);
                    exit;
                    
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'clear_selection':
                unset($_SESSION['selected_seats'][$scheduleId]);
                $selectedSeats = [];
                $totalAmount = 0;
                break;
                
            case 'apply_suggestion':
                $suggestionIndex = $_POST['suggestion_index'] ?? 0;
                if (isset($suggestions[$suggestionIndex])) {
                    $selectedSeats = $suggestions[$suggestionIndex]['seats'];
                    $_SESSION['selected_seats'][$scheduleId] = $selectedSeats;
                    
                    // Recalculate total
                    $totalAmount = 0;
                    foreach ($selectedSeats as $seat) {
                        if (isset($seatPrices[$seat])) {
                            $totalAmount += $seatPrices[$seat]['final'];
                        }
                    }
                }
                break;
        }
    }
}

// Format departure time
$departureTime = date('l, F j, Y \a\t g:i A', 
    strtotime($schedule['departure_date'] . ' ' . $schedule['departure_time']));
$arrivalTime = date('l, F j, Y \a\t g:i A', 
    strtotime($schedule['arrival_date'] . ' ' . $schedule['arrival_time']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Seats - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="../assets/css/seatmap.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .seat-tooltip {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            min-width: 200px;
        }
        
        .seat-tooltip h5 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .seat-tooltip p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .seat-price {
            font-weight: bold;
            color: #4CAF50;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <!-- Booking Progress -->
        <div class="booking-progress">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Search</div>
                </div>
                <div class="step completed">
                    <div class="step-number">2</div>
                    <div class="step-label">Schedule</div>
                </div>
                <div class="step active">
                    <div class="step-number">3</div>
                    <div class="step-label">Seats</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="step">
                    <div class="step-number">5</div>
                    <div class="step-label">Confirm</div>
                </div>
            </div>
        </div>

        <!-- Journey Summary -->
        <div class="journey-summary">
            <div class="summary-header">
                <h3><i class="fas fa-route"></i> Journey Summary</h3>
            </div>
            <div class="summary-content">
                <div class="route-info">
                    <div class="origin">
                        <h4><?php echo htmlspecialchars($schedule['origin_city']); ?></h4>
                        <p class="time"><?php echo $departureTime; ?></p>
                    </div>
                    <div class="journey-line">
                        <div class="line"></div>
                        <div class="duration">
                            <i class="fas fa-bus"></i>
                            <span><?php echo $schedule['estimated_hours']; ?> hrs</span>
                        </div>
                    </div>
                    <div class="destination">
                        <h4><?php echo htmlspecialchars($schedule['destination_city']); ?></h4>
                        <p class="time"><?php echo $arrivalTime; ?></p>
                    </div>
                </div>
                <div class="bus-info">
                    <div class="bus-detail">
                        <i class="fas fa-bus"></i>
                        <span><?php echo htmlspecialchars($schedule['bus_name']); ?> (<?php echo $schedule['bus_number']; ?>)</span>
                    </div>
                    <div class="bus-detail">
                        <i class="fas fa-star"></i>
                        <span><?php echo ucfirst($schedule['bus_type']); ?> Bus</span>
                    </div>
                    <div class="bus-detail">
                        <i class="fas fa-users"></i>
                        <span><?php echo $passengers; ?> Passenger(s)</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Main Seat Selection Area -->
        <div class="seat-selection-container">
            <div class="selection-header">
                <h2><i class="fas fa-chair"></i> Select Your Seats</h2>
                <p>Click on available seats to select. You need to select <strong><?php echo $passengers; ?> seat(s)</strong>.</p>
            </div>

            <!-- Seat Suggestions (if no seats selected) -->
            <?php if (empty($selectedSeats) && !empty($suggestions)): ?>
            <div class="seat-suggestions">
                <h4><i class="fas fa-lightbulb"></i> Smart Seat Suggestions</h4>
                <div class="suggestions-grid">
                    <?php foreach ($suggestions as $index => $suggestion): ?>
                    <div class="suggestion-card">
                        <div class="suggestion-header">
                            <h5><?php echo ucfirst($suggestion['type']); ?> Seats</h5>
                            <span class="suggestion-badge">Suggestion <?php echo $index + 1; ?></span>
                        </div>
                        <div class="suggestion-seats">
                            <?php foreach ($suggestion['seats'] as $seat): ?>
                                <span class="seat-badge"><?php echo $seat; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="suggestion-desc"><?php echo $suggestion['description']; ?></p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="apply_suggestion">
                            <input type="hidden" name="suggestion_index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn btn-sm btn-outline">
                                <i class="fas fa-check"></i> Select These Seats
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Seat Preferences -->
            <div class="seat-preferences">
                <h4><i class="fas fa-sliders-h"></i> Seat Preferences</h4>
                <form method="POST" class="preferences-form">
                    <div class="preference-options">
                        <div class="preference-group">
                            <label><i class="fas fa-window-maximize"></i> Seat Type</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="preferences[seat_type][]" value="window">
                                    <span class="checkmark"></span>
                                    Window Seats
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="preferences[seat_type][]" value="aisle">
                                    <span class="checkmark"></span>
                                    Aisle Seats
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="preferences[seat_type][]" value="extra_legroom">
                                    <span class="checkmark"></span>
                                    Extra Legroom
                                </label>
                            </div>
                        </div>
                        
                        <div class="preference-group">
                            <label><i class="fas fa-layer-group"></i> Row Preference</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="preferences[row_preference]" value="front">
                                    <span class="radiomark"></span>
                                    Front Rows
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="preferences[row_preference]" value="middle" checked>
                                    <span class="radiomark"></span>
                                    Middle Rows
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="preferences[row_preference]" value="back">
                                    <span class="radiomark"></span>
                                    Back Rows
                                </label>
                            </div>
                        </div>
                        
                        <div class="preference-group">
                            <button type="submit" name="action" value="apply_preferences" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter"></i> Apply Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Interactive Seat Map -->
            <div class="interactive-seatmap">
                <?php
                try {
                    $seatMapHTML = $seatMap->renderSeatMapHTML($seatMapData['seat_map']);
                    echo $seatMapHTML;
                } catch (\Exception $e) {
                    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <!-- Selected Seats Summary -->
            <div class="selected-seats-summary">
                <div class="summary-header">
                    <h4><i class="fas fa-shopping-cart"></i> Selected Seats</h4>
                    <div class="summary-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear_selection">
                            <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Clear all selected seats?')">
                                <i class="fas fa-trash"></i> Clear All
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="selected-seats-list">
                    <?php if (empty($selectedSeats)): ?>
                        <div class="no-seats">
                            <i class="fas fa-chair"></i>
                            <p>No seats selected yet. Click on available seats above.</p>
                        </div>
                    <?php else: ?>
                        <div class="seats-display">
                            <?php foreach ($selectedSeats as $seat): ?>
                                <?php 
                                $priceInfo = $seatPrices[$seat] ?? ['final' => $seatMapData['base_fare'], 'type' => 'standard'];
                                $seatType = $priceInfo['type'] ?? 'standard';
                                ?>
                                <div class="selected-seat-item" data-seat="<?php echo $seat; ?>">
                                    <div class="seat-info">
                                        <div class="seat-icon">
                                            <i class="fas <?php echo $seatMap->getSeatIcon($seatType); ?>"></i>
                                        </div>
                                        <div class="seat-details">
                                            <h5>Seat <?php echo $seat; ?></h5>
                                            <span class="seat-type"><?php echo ucfirst(str_replace('_', ' ', $seatType)); ?></span>
                                        </div>
                                    </div>
                                    <div class="seat-price">
                                        KES <?php echo number_format($priceInfo['final'], 2); ?>
                                        <button type="button" class="remove-seat" onclick="removeSeat('<?php echo $seat; ?>')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="seats-total">
                            <div class="total-item">
                                <span>Seats Selected:</span>
                                <strong><?php echo count($selectedSeats); ?> of <?php echo $passengers; ?></strong>
                            </div>
                            <div class="total-item">
                                <span>Subtotal:</span>
                                <strong>KES <?php echo number_format($totalAmount, 2); ?></strong>
                            </div>
                            <div class="total-item">
                                <span>Service Fee:</span>
                                <strong>KES <?php echo number_format($totalAmount * 0.05, 2); ?></strong>
                            </div>
                            <div class="total-item grand-total">
                                <span>Total Amount:</span>
                                <strong>KES <?php echo number_format($totalAmount * 1.05, 2); ?></strong>
                            </div>
                        </div>
                        
                        <!-- Hidden form to submit selected seats -->
                        <form id="seatSelectionForm" method="POST">
                            <input type="hidden" name="selected_seats" id="selectedSeatsInput" 
                                   value='<?php echo json_encode($selectedSeats); ?>'>
                            <input type="hidden" name="action" value="lock_seats">
                            
                            <?php if (count($selectedSeats) == $passengers): ?>
                                <div class="proceed-actions">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-lock"></i> Lock Seats & Proceed to Payment
                                    </button>
                                    <p class="note">
                                        <i class="fas fa-clock"></i> Selected seats will be reserved for 15 minutes
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="selection-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Please select <?php echo $passengers - count($selectedSeats); ?> more seat(s)
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Seat Type Information -->
            <div class="seat-type-info">
                <h4><i class="fas fa-info-circle"></i> Seat Type Information</h4>
                <div class="type-grid">
                    <div class="type-card">
                        <div class="type-icon window">
                            <i class="fas fa-window-maximize"></i>
                        </div>
                        <h5>Window Seat</h5>
                        <p>Enjoy the view with extra privacy</p>
                        <span class="type-price">+10%</span>
                    </div>
                    <div class="type-card">
                        <div class="type-icon aisle">
                            <i class="fas fa-walking"></i>
                        </div>
                        <h5>Aisle Seat</h5>
                        <p>Easy access to move around</p>
                        <span class="type-price">+5%</span>
                    </div>
                    <div class="type-card">
                        <div class="type-icon extra">
                            <i class="fas fa-arrows-alt-v"></i>
                        </div>
                        <h5>Extra Legroom</h5>
                        <p>More space for comfort</p>
                        <span class="type-price">+20%</span>
                    </div>
                    <div class="type-card">
                        <div class="type-icon standard">
                            <i class="fas fa-chair"></i>
                        </div>
                        <h5>Standard Seat</h5>
                        <p>Comfortable standard seating</p>
                        <span class="type-price">Base Price</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Tooltip Container -->
    <div id="seatTooltip" class="seat-tooltip"></div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/seatmap.js"></script>
    <script>
    // Global variables
    var selectedSeats = <?php echo json_encode($selectedSeats); ?>;
    var maxSeats = <?php echo $passengers; ?>;
    var seatPrices = <?php echo json_encode($seatPrices); ?>;
    var baseFare = <?php echo $seatMapData['base_fare']; ?>;
    
    // Tooltip element
    var tooltip = $('#seatTooltip');
    var currentTooltipSeat = null;
    
    // Initialize seat selection
    $(document).ready(function() {
        updateSelectedSeatsDisplay();
        updateAvailableCount();
        
        // Add hover effects for seats
        $('.seat').hover(
            function() {
                var seatNumber = $(this).data('seat-number');
                var seatType = $(this).data('seat-type');
                var seatStatus = $(this).hasClass('status-available') ? 'available' : 
                                 $(this).hasClass('status-booked') ? 'booked' : 
                                 $(this).hasClass('status-reserved') ? 'reserved' : 'unknown';
                
                if (seatStatus === 'available') {
                    showSeatTooltip($(this), seatNumber, seatType);
                }
            },
            function() {
                hideSeatTooltip();
            }
        );
    });
    
    // Function to show seat tooltip
    function showSeatTooltip(seatElement, seatNumber, seatType) {
        var priceInfo = seatPrices[seatNumber] || {
            final: baseFare * (seatElement.data('seat-price') || 1.0),
            type: seatType,
            description: seatType.charAt(0).toUpperCase() + seatType.slice(1).replace('_', ' ') + ' seat'
        };
        
        var tooltipHtml = '<h5>Seat ' + seatNumber + '</h5>';
        tooltipHtml += '<p>' + priceInfo.description + '</p>';
        tooltipHtml += '<div class="seat-price">KES ' + priceInfo.final.toFixed(2) + '</div>';
        
        tooltip.html(tooltipHtml);
        tooltip.show();
        
        // Position tooltip near the seat
        var offset = seatElement.offset();
        tooltip.css({
            top: offset.top - tooltip.outerHeight() - 10,
            left: offset.left - (tooltip.outerWidth() / 2) + (seatElement.outerWidth() / 2)
        });
        
        currentTooltipSeat = seatNumber;
    }
    
    // Function to hide seat tooltip
    function hideSeatTooltip() {
        tooltip.hide();
        currentTooltipSeat = null;
    }
    
    // Function to select a seat
    window.selectSeat = function(seatElement) {
        var seatNumber = $(seatElement).data('seat-number');
        var seatStatus = $(seatElement).hasClass('status-available');
        
        if (!seatStatus) {
            alert('This seat is not available for selection.');
            return;
        }
        
        // Check if seat is already selected
        var seatIndex = selectedSeats.indexOf(seatNumber);
        
        if (seatIndex > -1) {
            // Deselect seat
            selectedSeats.splice(seatIndex, 1);
            $(seatElement).removeClass('selected status-selected').addClass('status-available');
        } else {
            // Check if we can select more seats
            if (selectedSeats.length >= maxSeats) {
                alert('You can only select ' + maxSeats + ' seat(s). Please deselect a seat first.');
                return;
            }
            
            // Select seat
            selectedSeats.push(seatNumber);
            $(seatElement).removeClass('status-available').addClass('selected status-selected');
        }
        
        // Update display
        updateSelectedSeatsDisplay();
        updateAvailableCount();
        updateFormInput();
        
        // Hide tooltip if it's for this seat
        if (currentTooltipSeat === seatNumber) {
            hideSeatTooltip();
        }
    };
    
    // Function to remove a seat from selection
    window.removeSeat = function(seatNumber) {
        var seatIndex = selectedSeats.indexOf(seatNumber);
        
        if (seatIndex > -1) {
            selectedSeats.splice(seatIndex, 1);
            
            // Update seat on map
            $('.seat[data-seat-number="' + seatNumber + '"]')
                .removeClass('selected status-selected')
                .addClass('status-available');
            
            updateSelectedSeatsDisplay();
            updateAvailableCount();
            updateFormInput();
        }
    };
    
    // Function to update selected seats display
    function updateSelectedSeatsDisplay() {
        // Update count badge
        $('#selectedCount').text(selectedSeats.length + '/' + maxSeats);
        
        // Show/hide proceed button
        if (selectedSeats.length === maxSeats) {
            $('.proceed-actions').show();
            $('.selection-warning').hide();
        } else {
            $('.proceed-actions').hide();
            $('.selection-warning').show().html(
                '<i class="fas fa-exclamation-triangle"></i> ' +
                'Please select ' + (maxSeats - selectedSeats.length) + ' more seat(s)'
            );
        }
    }
    
    // Function to update available seats count
    function updateAvailableCount() {
        var availableCount = $('.seat.status-available:not(.aisle)').length;
        $('.available-count').text(availableCount);
    }
    
    // Function to update form input
    function updateFormInput() {
        $('#selectedSeatsInput').val(JSON.stringify(selectedSeats));
    }
    
    // Auto-refresh seat availability every 30 seconds
    setInterval(function() {
        $.ajax({
            url: '../api/seat_availability.php',
            method: 'POST',
            data: {
                schedule_id: <?php echo $scheduleId; ?>,
                action: 'check_availability'
            },
            success: function(response) {
                if (response.success) {
                    // Update seat statuses
                    response.unavailable_seats.forEach(function(seat) {
                        $('.seat[data-seat-number="' + seat.seat_number + '"]')
                            .removeClass('status-available selected status-selected')
                            .addClass('status-' + seat.status)
                            .off('click');
                    });
                    
                    updateAvailableCount();
                }
            }
        });
    }, 30000); // 30 seconds
    </script>
</body>
</html>