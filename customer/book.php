<?php
require_once '../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user = new \Mori\User();
$booking = new \Mori\Booking();
$userData = $user->getUser($_SESSION['user_id']);

// Check if user is verified
if (!$userData['is_verified']) {
    header('Location: verify.php');
    exit;
}

// Get Kenyan cities
$db = \Mori\Database::getInstance();
$kenyanData = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'major_cities'");
$cities = json_decode($kenyanData['setting_value'], true) ?? [];

// Handle search
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $origin = $_POST['origin'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $passengers = $_POST['passengers'] ?? 1;
    
    try {
        $searchResults = $booking->searchRoutes($origin, $destination, $date, $passengers);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle seat selection
if (isset($_GET['schedule_id']) && isset($_GET['action']) && $_GET['action'] === 'select_seats') {
    $scheduleId = intval($_GET['schedule_id']);
    $passengers = intval($_GET['passengers'] ?? 1);
    
    // Get schedule details
    $schedule = $booking->getSchedule($scheduleId);
    $availableSeats = $booking->getAvailableSeats($scheduleId);
    
    include 'select_seats.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Bus Ticket - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-bus"></i> Book Bus Ticket</h1>
            <p>Search and book bus tickets across Kenya</p>
        </div>

        <!-- Booking Form -->
        <div class="booking-card">
            <div class="booking-header">
                <h2><i class="fas fa-search"></i> Search Buses</h2>
                <p>Enter your travel details to find available buses</p>
            </div>
            
            <form method="POST" action="" class="booking-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="origin"><i class="fas fa-map-marker-alt"></i> From</label>
                        <div class="autocomplete">
                            <input type="text" id="origin" name="origin" 
                                   value="<?php echo $_POST['origin'] ?? ''; ?>" 
                                   placeholder="Enter origin city" required 
                                   list="cities-list">
                            <datalist id="cities-list">
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" id="swap-locations" class="swap-btn" title="Swap locations">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination"><i class="fas fa-map-marker-alt"></i> To</label>
                        <div class="autocomplete">
                            <input type="text" id="destination" name="destination" 
                                   value="<?php echo $_POST['destination'] ?? ''; ?>" 
                                   placeholder="Enter destination" required
                                   list="cities-list">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date"><i class="fas fa-calendar-alt"></i> Travel Date</label>
                        <input type="text" id="date" name="date" class="datepicker" 
                               value="<?php echo $_POST['date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="passengers"><i class="fas fa-users"></i> Passengers</label>
                        <div class="passenger-selector">
                            <button type="button" class="passenger-btn minus"><i class="fas fa-minus"></i></button>
                            <input type="number" id="passengers" name="passengers" 
                                   value="<?php echo $_POST['passengers'] ?? 1; ?>" min="1" max="10" readonly>
                            <button type="button" class="passenger-btn plus"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="search" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Search Buses
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($searchResults)): ?>
            <!-- Search Results -->
            <div class="search-results">
                <div class="results-header">
                    <h2><i class="fas fa-bus"></i> Available Buses</h2>
                    <p><?php echo count($searchResults); ?> route(s) found</p>
                </div>
                
                <?php foreach ($searchResults as $route): ?>
                    <div class="route-card">
                        <div class="route-header">
                            <div class="route-info">
                                <h3><?php echo htmlspecialchars($route['origin_city']); ?> 
                                    <i class="fas fa-arrow-right"></i> 
                                    <?php echo htmlspecialchars($route['destination_city']); ?>
                                </h3>
                                <div class="route-meta">
                                    <span><i class="fas fa-road"></i> <?php echo $route['distance_km']; ?> km</span>
                                    <span><i class="fas fa-clock"></i> <?php echo $route['estimated_hours']; ?> hrs</span>
                                    <span><i class="fas fa-money-bill-wave"></i> From KES <?php echo number_format($route['base_fare'], 2); ?></span>
                                </div>
                            </div>
                            <div class="route-actions">
                                <a href="#" class="btn btn-outline view-stops" data-route-id="<?php echo $route['id']; ?>">
                                    <i class="fas fa-map-marker-alt"></i> View Stops
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($route['stops'])): ?>
                            <div class="route-stops" id="stops-<?php echo $route['id']; ?>" style="display: none;">
                                <h4><i class="fas fa-map-pin"></i> Route Stops</h4>
                                <div class="stops-list">
                                    <?php foreach ($route['stops'] as $stop): ?>
                                        <div class="stop-item">
                                            <div class="stop-dot"></div>
                                            <div class="stop-details">
                                                <strong><?php echo htmlspecialchars($stop['stop_name']); ?></strong>
                                                <span><?php echo $stop['distance_from_origin']; ?> km</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($route['schedules'])): ?>
                            <div class="schedules-list">
                                <?php foreach ($route['schedules'] as $schedule): ?>
                                    <div class="schedule-card">
                                        <div class="schedule-info">
                                            <div class="bus-details">
                                                <h4><?php echo htmlspecialchars($schedule['bus_name']); ?></h4>
                                                <p class="bus-meta">
                                                    <span><i class="fas fa-bus"></i> <?php echo htmlspecialchars($schedule['bus_number']); ?></span>
                                                    <span><i class="fas fa-star"></i> <?php echo ucfirst($schedule['bus_type']); ?></span>
                                                    <span><i class="fas fa-chair"></i> <?php echo $schedule['available_seats']; ?> seats left</span>
                                                </p>
                                                <div class="amenities">
                                                    <?php 
                                                    $amenities = json_decode($schedule['amenities'], true) ?? [];
                                                    foreach ($amenities as $amenity): 
                                                    ?>
                                                        <span class="amenity"><i class="fas fa-check"></i> <?php echo $amenity; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="timing">
                                                <div class="departure">
                                                    <strong>Departure</strong>
                                                    <p><?php echo date('h:i A', strtotime($schedule['departure_time'])); ?></p>
                                                    <small><?php echo date('D, M d', strtotime($schedule['departure_date'])); ?></small>
                                                </div>
                                                <div class="duration">
                                                    <i class="fas fa-arrow-right"></i>
                                                    <span><?php echo $schedule['duration_hours']; ?> hrs</span>
                                                </div>
                                                <div class="arrival">
                                                    <strong>Arrival</strong>
                                                    <p><?php echo date('h:i A', strtotime($schedule['arrival_time'])); ?></p>
                                                    <small><?php echo date('D, M d', strtotime($schedule['arrival_date'])); ?></small>
                                                </div>
                                            </div>
                                            
                                            <div class="pricing">
                                                <div class="price">
                                                    <strong>KES <?php echo number_format($schedule['final_fare_per_seat'], 2); ?></strong>
                                                    <small>per seat</small>
                                                </div>
                                                <?php if ($schedule['demand_factor'] > 1.0): ?>
                                                    <div class="demand-badge">
                                                        <i class="fas fa-fire"></i> High Demand
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="schedule-actions">
                                            <a href="book.php?schedule_id=<?php echo $schedule['id']; ?>&passengers=<?php echo $_POST['passengers'] ?? 1; ?>&action=select_seats" 
                                               class="btn btn-primary">
                                                <i class="fas fa-chair"></i> Select Seats
                                            </a>
                                            <?php if ($schedule['available_seats'] < 10): ?>
                                                <div class="seats-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $schedule['available_seats']; ?> seats left
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-schedules">
                                <i class="fas fa-calendar-times"></i>
                                <p>No schedules available for the selected date</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No buses found for your search</h3>
                <p>Try a different date or route</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/js/main.js"></script>
    <script>
    $(document).ready(function() {
        // Date picker
        flatpickr(".datepicker", {
            minDate: "today",
            dateFormat: "Y-m-d",
            disableMobile: true
        });
        
        // Passenger counter
        $('.passenger-btn.minus').click(function() {
            var input = $('#passengers');
            var value = parseInt(input.val());
            if (value > 1) {
                input.val(value - 1);
            }
        });
        
        $('.passenger-btn.plus').click(function() {
            var input = $('#passengers');
            var value = parseInt(input.val());
            if (value < 10) {
                input.val(value + 1);
            }
        });
        
        // Swap locations
        $('#swap-locations').click(function() {
            var origin = $('#origin').val();
            var destination = $('#destination').val();
            $('#origin').val(destination);
            $('#destination').val(origin);
        });
        
        // Toggle route stops
        $('.view-stops').click(function(e) {
            e.preventDefault();
            var routeId = $(this).data('route-id');
            $('#stops-' + routeId).slideToggle();
        });
        
        // Auto-fill popular routes
        $('.popular-route').click(function(e) {
            e.preventDefault();
            var route = $(this).data('route').split('-');
            $('#origin').val(route[0]);
            $('#destination').val(route[1]);
            $('form').submit();
        });
    });
    </script>
</body>
</html>