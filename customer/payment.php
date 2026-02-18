<?php
require_once '../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check for pending booking
if (!isset($_SESSION['pending_booking'])) {
    header('Location: book.php');
    exit;
}

$pendingBooking = $_SESSION['pending_booking'];
$bookingId = $pendingBooking['booking_id'];
$bookingToken = $pendingBooking['booking_token'];
$selectedSeats = $pendingBooking['selected_seats'];
$totalAmount = $pendingBooking['total_amount'];
$isFreeTrip = $pendingBooking['is_free_trip'] ?? false;

// Get booking details
$db = \Mori\Database::getInstance();
$sql = "SELECT b.*, s.departure_date, s.departure_time, s.arrival_date, s.arrival_time,
               r.origin_city, r.destination_city, r.base_fare,
               bus.bus_name, bus.bus_number, bus.bus_type
        FROM bookings b
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        JOIN buses bus ON s.bus_id = bus.id
        WHERE b.id = ?";
$booking = $db->fetch($sql, [$bookingId]);

if (!$booking) {
    unset($_SESSION['pending_booking']);
    header('Location: book.php');
    exit;
}

// Check if booking token is still valid
if (strtotime($pendingBooking['expires']) < time()) {
    unset($_SESSION['pending_booking']);
    header('Location: book.php?error=Booking expired');
    exit;
}

$user = new \Mori\User();
$userData = $user->getUser($_SESSION['user_id']);

// Get loyalty points
$loyaltyPoints = $userData['loyalty_points'];
$freeTripsAvailable = $userData['free_trips_available'];

// Calculate points value (1 point = 1 KES)
$pointsValue = $loyaltyPoints;
$maxPointsToUse = min($pointsValue, $totalAmount * 0.5); // Max 50% of total with points

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'process_payment':
            $paymentMethod = $_POST['payment_method'] ?? '';
            $amount = floatval($_POST['amount'] ?? $totalAmount);
            $usePoints = intval($_POST['use_points'] ?? 0);
            $useFreeTrip = isset($_POST['use_free_trip']) && $freeTripsAvailable > 0;
            
            // Validate amount
            if ($amount <= 0) {
                $error = 'Invalid payment amount';
                break;
            }
            
            // Calculate final amount after points/free trip
            $finalAmount = $amount;
            
            if ($useFreeTrip) {
                $finalAmount = 0;
            } elseif ($usePoints > 0) {
                $pointsToUse = min($usePoints, $maxPointsToUse);
                $finalAmount = max(0, $amount - $pointsToUse);
            }
            
            // Process payment based on method
            $payment = new \Mori\Payment();
            
            try {
                switch ($paymentMethod) {
                    case 'mpesa':
                        $phone = $_POST['mpesa_phone'] ?? $userData['phone'];
                        
                        $result = $payment->processMpesa($phone, $finalAmount, $bookingId, $userData['id']);
                        
                        if ($result['success']) {
                            // Update booking with payment
                            $bookingClass = new \Mori\Booking();
                            $bookingClass->confirmBooking($bookingId, $bookingToken, [
                                'method' => 'mpesa',
                                'amount' => $finalAmount,
                                'mpesa_receipt' => $result['transaction_id']
                            ]);
                            
                            // Deduct loyalty points if used
                            if ($usePoints > 0) {
                                $user->updateLoyalty($userData['id'], -$usePoints);
                            }
                            
                            // Deduct free trip if used
                            if ($useFreeTrip) {
                                $db->update('users', [
                                    'free_trips_available' => $freeTripsAvailable - 1
                                ], 'id = ?', [$userData['id']]);
                            }
                            
                            // Clear pending booking
                            unset($_SESSION['pending_booking']);
                            
                            // Redirect to confirmation
                            header('Location: confirmation.php?booking_ref=' . $booking['booking_ref']);
                            exit;
                        } else {
                            $error = 'Mpesa payment failed';
                        }
                        break;
                        
                    case 'card':
                        $cardData = [
                            'card_number' => $_POST['card_number'] ?? '',
                            'card_holder' => $_POST['card_holder'] ?? '',
                            'expiry_month' => $_POST['expiry_month'] ?? '',
                            'expiry_year' => $_POST['expiry_year'] ?? '',
                            'cvv' => $_POST['cvv'] ?? ''
                        ];
                        
                        $result = $payment->processCardPayment($cardData, $finalAmount, $bookingId, $userData['id']);
                        
                        if ($result['success']) {
                            // Similar processing as Mpesa
                            // ... (implementation)
                        }
                        break;
                        
                    case 'free_trip':
                        if ($freeTripsAvailable > 0) {
                            // Confirm booking as free trip
                            $bookingClass = new \Mori\Booking();
                            $bookingClass->confirmBooking($bookingId, $bookingToken, [
                                'method' => 'free_trip',
                                'amount' => 0
                            ]);
                            
                            // Deduct free trip
                            $db->update('users', [
                                'free_trips_available' => $freeTripsAvailable - 1
                            ], 'id = ?', [$userData['id']]);
                            
                            // Clear pending booking
                            unset($_SESSION['pending_booking']);
                            
                            // Redirect to confirmation
                            header('Location: confirmation.php?booking_ref=' . $booking['booking_ref']);
                            exit;
                        } else {
                            $error = 'No free trips available';
                        }
                        break;
                        
                    default:
                        $error = 'Invalid payment method';
                        break;
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 'cancel_booking':
            // Cancel the booking and release seats
            $bookingClass = new \Mori\Booking();
            try {
                $bookingClass->cancelBooking($bookingId, $userData['id'], 'User cancelled before payment');
                unset($_SESSION['pending_booking']);
                header('Location: book.php?cancelled=1');
                exit;
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
            break;
    }
}

// Format departure time
$departureTime = date('l, F j, Y \a\t g:i A', 
    strtotime($booking['departure_date'] . ' ' . $booking['departure_time']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 5px 20px rgba(255, 152, 0, 0.3);
            text-align: center;
        }
        
        .payment-timer .time {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .payment-timer .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .selected-seats-display {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .selected-seat-badge {
            background: #e3f2fd;
            color: #1976D2;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .selected-seat-badge i {
            font-size: 0.9rem;
        }
        
        .loyalty-points-box {
            background: linear-gradient(135deg, #FFD700, #FFC107);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            color: #333;
        }
        
        .loyalty-points-box h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .points-available {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .points-value {
            font-size: 1rem;
            color: #666;
        }
        
        .payment-method-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .payment-method-card:hover,
        .payment-method-card.selected {
            border-color: #2196F3;
            background: #f8fdff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(33, 150, 243, 0.1);
        }
        
        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .payment-method-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        
        .payment-method-icon.mpesa {
            background: linear-gradient(135deg, #00B300, #008000);
        }
        
        .payment-method-icon.card {
            background: linear-gradient(135deg, #FF5722, #E64A19);
        }
        
        .payment-method-icon.free {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
        }
        
        .payment-form {
            display: none;
        }
        
        .payment-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .amount-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
        }
        
        .qr-code {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
        
        .qr-code img {
            max-width: 200px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Payment Timer -->
    <div class="payment-timer">
        <div class="time" id="countdown">15:00</div>
        <div class="label">Complete Payment</div>
    </div>

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
                <div class="step completed">
                    <div class="step-number">3</div>
                    <div class="step-label">Seats</div>
                </div>
                <div class="step active">
                    <div class="step-number">4</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="step">
                    <div class="step-number">5</div>
                    <div class="step-label">Confirm</div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Payment Container -->
        <div class="payment-container">
            <div class="payment-header">
                <h2><i class="fas fa-credit-card"></i> Complete Your Payment</h2>
                <p>Booking Reference: <strong><?php echo $booking['booking_ref']; ?></strong></p>
            </div>
            
            <!-- Journey and Seat Summary -->
            <div class="payment-summary">
                <div class="summary-card">
                    <div class="summary-header">
                        <h4><i class="fas fa-route"></i> Journey Details</h4>
                    </div>
                    <div class="summary-content">
                        <div class="journey-info">
                            <div class="route">
                                <div class="origin">
                                    <h5><?php echo htmlspecialchars($booking['origin_city']); ?></h5>
                                    <p><?php echo $departureTime; ?></p>
                                </div>
                                <div class="arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                                <div class="destination">
                                    <h5><?php echo htmlspecialchars($booking['destination_city']); ?></h5>
                                    <p><?php echo date('l, F j, Y \a\t g:i A', 
                                        strtotime($booking['arrival_date'] . ' ' . $booking['arrival_time'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="seats-info">
                            <h5><i class="fas fa-chair"></i> Selected Seats</h5>
                            <div class="selected-seats-display">
                                <?php foreach ($selectedSeats as $seat): ?>
                                    <span class="selected-seat-badge">
                                        <i class="fas fa-chair"></i> Seat <?php echo $seat; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loyalty Points (if available) -->
            <?php if ($loyaltyPoints > 0 || $freeTripsAvailable > 0): ?>
            <div class="loyalty-points-box">
                <h4><i class="fas fa-gift"></i> Use Your Rewards</h4>
                
                <?php if ($freeTripsAvailable > 0): ?>
                <div class="free-trip-option">
                    <label class="checkbox-label">
                        <input type="checkbox" id="useFreeTrip" name="use_free_trip" value="1">
                        <span class="checkmark"></span>
                        Use 1 Free Trip (Available: <?php echo $freeTripsAvailable; ?>)
                    </label>
                    <p class="note">You have earned free trips from your loyalty!</p>
                </div>
                <?php endif; ?>
                
                <?php if ($loyaltyPoints > 0): ?>
                <div class="points-option">
                    <div class="points-available">
                        <?php echo number_format($loyaltyPoints); ?> Points
                        <span class="points-value">(KES <?php echo number_format($pointsValue, 2); ?>)</span>
                    </div>
                    <div class="points-slider">
                        <label for="pointsToUse">Use points:</label>
                        <input type="range" id="pointsToUse" name="points_to_use" min="0" 
                               max="<?php echo $maxPointsToUse; ?>" value="0" step="10">
                        <div class="slider-values">
                            <span>0</span>
                            <span id="pointsValue">0</span>
                            <span><?php echo $maxPointsToUse; ?></span>
                        </div>
                        <p class="note">Maximum 50% of total amount can be paid with points</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Amount Breakdown -->
            <div class="amount-breakdown">
                <h4><i class="fas fa-receipt"></i> Amount Breakdown</h4>
                <div class="breakdown-item">
                    <span>Base Fare (<?php echo count($selectedSeats); ?> seats):</span>
                    <span>KES <?php echo number_format($totalAmount, 2); ?></span>
                </div>
                <div class="breakdown-item">
                    <span>Service Fee (5%):</span>
                    <span>KES <?php echo number_format($totalAmount * 0.05, 2); ?></span>
                </div>
                <div class="breakdown-item" id="pointsDiscountItem" style="display: none;">
                    <span>Loyalty Points Discount:</span>
                    <span id="pointsDiscount">KES 0.00</span>
                </div>
                <div class="breakdown-item" id="freeTripItem" style="display: none;">
                    <span>Free Trip Discount:</span>
                    <span id="freeTripDiscount">KES 0.00</span>
                </div>
                <div class="breakdown-item">
                    <span>Total Amount:</span>
                    <span id="totalAmount">KES <?php echo number_format($totalAmount * 1.05, 2); ?></span>
                </div>
                <div class="breakdown-item" id="finalAmountItem">
                    <span>Amount to Pay:</span>
                    <span id="finalAmount" class="highlight">KES <?php echo number_format($totalAmount * 1.05, 2); ?></span>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="payment-methods">
                <h4><i class="fas fa-wallet"></i> Select Payment Method</h4>
                
                <div class="payment-methods-grid">
                    <!-- Mpesa Payment -->
                    <div class="payment-method-card" onclick="selectPaymentMethod('mpesa')">
                        <div class="payment-method-header">
                            <div class="payment-method-icon mpesa">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="payment-method-info">
                                <h5>M-Pesa</h5>
                                <p>Pay via M-Pesa STK Push</p>
                            </div>
                        </div>
                        <div class="payment-method-desc">
                            <p><i class="fas fa-bolt"></i> Instant payment</p>
                            <p><i class="fas fa-shield-alt"></i> Secure & encrypted</p>
                        </div>
                    </div>
                    
                    <!-- Card Payment -->
                    <div class="payment-method-card" onclick="selectPaymentMethod('card')">
                        <div class="payment-method-header">
                            <div class="payment-method-icon card">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-method-info">
                                <h5>Credit/Debit Card</h5>
                                <p>Visa, Mastercard, American Express</p>
                            </div>
                        </div>
                        <div class="payment-method-desc">
                            <p><i class="fas fa-globe"></i> International cards accepted</p>
                            <p><i class="fas fa-lock"></i> 3D Secure enabled</p>
                        </div>
                    </div>
                    
                    <!-- Free Trip Payment -->
                    <?php if ($freeTripsAvailable > 0): ?>
                    <div class="payment-method-card" onclick="selectPaymentMethod('free_trip')">
                        <div class="payment-method-header">
                            <div class="payment-method-icon free">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="payment-method-info">
                                <h5>Free Trip</h5>
                                <p>Use your earned free trip</p>
                            </div>
                        </div>
                        <div class="payment-method-desc">
                            <p><i class="fas fa-crown"></i> You have <?php echo $freeTripsAvailable; ?> free trip(s)</p>
                            <p><i class="fas fa-star"></i> Reward for your loyalty</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Forms -->
            <form id="paymentForm" method="POST">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="payment_method" id="paymentMethod" value="">
                <input type="hidden" name="amount" id="paymentAmount" value="<?php echo $totalAmount * 1.05; ?>">
                <input type="hidden" name="use_points" id="usePoints" value="0">
                <input type="hidden" name="use_free_trip" id="useFreeTripInput" value="0">
                
                <!-- Mpesa Form -->
                <div id="mpesaForm" class="payment-form">
                    <div class="form-card">
                        <h5><i class="fas fa-mobile-alt"></i> M-Pesa Payment</h5>
                        <div class="form-group">
                            <label for="mpesaPhone">M-Pesa Phone Number</label>
                            <input type="text" id="mpesaPhone" name="mpesa_phone" 
                                   value="<?php echo htmlspecialchars($userData['phone']); ?>"
                                   placeholder="07XXXXXXXX or +2547XXXXXXXX" required>
                            <p class="note">You will receive an STK push to this number</p>
                        </div>
                        <div class="qr-code">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=MORI%3A<?php echo $booking['booking_ref']; ?>%3AKES<?php echo $totalAmount * 1.05; ?>"
                                 alt="Payment QR Code">
                            <p>Scan to pay with M-Pesa</p>
                        </div>
                    </div>
                </div>
                
                <!-- Card Form -->
                <div id="cardForm" class="payment-form">
                    <div class="form-card">
                        <h5><i class="fas fa-credit-card"></i> Card Payment</h5>
                        <div class="form-group">
                            <label for="cardNumber">Card Number</label>
                            <input type="text" id="cardNumber" name="card_number" 
                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="cardHolder">Card Holder Name</label>
                                <input type="text" id="cardHolder" name="card_holder" 
                                       placeholder="John Doe" required>
                            </div>
                            <div class="form-group">
                                <label for="expiryDate">Expiry Date</label>
                                <div class="expiry-inputs">
                                    <select id="expiryMonth" name="expiry_month" required>
                                        <option value="">MM</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <select id="expiryYear" name="expiry_year" required>
                                        <option value="">YYYY</option>
                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" placeholder="123" 
                                       maxlength="4" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Free Trip Form -->
                <div id="freeTripForm" class="payment-form">
                    <div class="form-card">
                        <h5><i class="fas fa-gift"></i> Free Trip Payment</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You're about to use 1 of your <?php echo $freeTripsAvailable; ?> free trip(s).
                            This booking will be completely free!
                        </div>
                        <p>Confirm to use your free trip for this booking.</p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="payment-actions">
                    <button type="button" class="btn btn-outline" onclick="cancelBooking()">
                        <i class="fas fa-times"></i> Cancel Booking
                    </button>
                    <button type="submit" id="payButton" class="btn btn-primary btn-lg" disabled>
                        <i class="fas fa-lock"></i> Complete Payment
                    </button>
                </div>
            </form>
            
            <!-- Cancel Booking Form -->
            <form id="cancelForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="cancel_booking">
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Global variables
    var baseAmount = <?php echo $totalAmount; ?>;
    var serviceFee = <?php echo $totalAmount * 0.05; ?>;
    var totalAmount = baseAmount + serviceFee;
    var maxPoints = <?php echo $maxPointsToUse; ?>;
    var freeTripsAvailable = <?php echo $freeTripsAvailable; ?>;
    var selectedMethod = '';
    var paymentTimer;
    
    // Initialize
    $(document).ready(function() {
        // Start payment countdown
        startPaymentTimer();
        
        // Initialize points slider
        $('#pointsToUse').on('input', updatePointsDiscount);
        
        // Initialize free trip checkbox
        $('#useFreeTrip').change(updateFreeTripDiscount);
        
        // Initialize card number formatting
        $('#cardNumber').on('input', formatCardNumber);
        
        // Initialize CVV formatting
        $('#cvv').on('input', formatCVV);
    });
    
    // Payment countdown timer
    function startPaymentTimer() {
        let timeLeft = 15 * 60; // 15 minutes in seconds
        paymentTimer = setInterval(() => {
            timeLeft--;
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            $('#countdown').text(
                minutes.toString().padStart(2, '0') + ':' + 
                seconds.toString().padStart(2, '0')
            );
            
            // Change color when less than 5 minutes
            if (timeLeft < 300) {
                $('#countdown').parent().css({
                    'background': 'linear-gradient(135deg, #F44336, #D32F2F)'
                });
            }
            
            // When time runs out
            if (timeLeft <= 0) {
                clearInterval(paymentTimer);
                alert('Payment time expired. Your seats have been released.');
                cancelBooking();
            }
        }, 1000);
    }
    
    // Select payment method
    function selectPaymentMethod(method) {
        selectedMethod = method;
        
        // Update UI
        $('.payment-method-card').removeClass('selected');
        $(`.payment-method-card[onclick*="${method}"]`).addClass('selected');
        
        // Show selected form, hide others
        $('.payment-form').removeClass('active');
        $(`#${method}Form`).addClass('active');
        
        // Update hidden field
        $('#paymentMethod').val(method);
        
        // Enable pay button
        $('#payButton').prop('disabled', false);
        
        // Update button text
        if (method === 'free_trip') {
            $('#payButton').html('<i class="fas fa-gift"></i> Use Free Trip');
        } else {
            $('#payButton').html('<i class="fas fa-lock"></i> Complete Payment');
        }
        
        // Recalculate final amount
        updateFinalAmount();
    }
    
    // Update points discount
    function updatePointsDiscount() {
        const pointsToUse = parseInt($('#pointsToUse').val());
        const pointsValue = pointsToUse; // 1 point = 1 KES
        
        $('#pointsValue').text(pointsValue.toLocaleString());
        $('#usePoints').val(pointsToUse);
        
        // Show/hide points discount item
        if (pointsToUse > 0) {
            $('#pointsDiscountItem').show();
            $('#pointsDiscount').text('KES ' + pointsValue.toFixed(2));
        } else {
            $('#pointsDiscountItem').hide();
        }
        
        updateFinalAmount();
    }
    
    // Update free trip discount
    function updateFreeTripDiscount() {
        const useFreeTrip = $('#useFreeTrip').is(':checked');
        $('#useFreeTripInput').val(useFreeTrip ? 1 : 0);
        
        if (useFreeTrip) {
            $('#freeTripItem').show();
            $('#freeTripDiscount').text('KES ' + totalAmount.toFixed(2));
        } else {
            $('#freeTripItem').hide();
        }
        
        updateFinalAmount();
    }
    
    // Update final amount
    function updateFinalAmount() {
        let finalAmount = totalAmount;
        const pointsToUse = parseInt($('#pointsToUse').val());
        const useFreeTrip = $('#useFreeTrip').is(':checked');
        
        // Apply discounts
        if (useFreeTrip) {
            finalAmount = 0;
        } else if (pointsToUse > 0) {
            finalAmount = Math.max(0, totalAmount - pointsToUse);
        }
        
        // Update display
        $('#finalAmount').text('KES ' + finalAmount.toFixed(2));
        $('#paymentAmount').val(finalAmount);
        
        // Update button text if amount is zero
        if (finalAmount <= 0 && selectedMethod && selectedMethod !== 'free_trip') {
            $('#payButton').html('<i class="fas fa-gift"></i> Complete with Rewards');
        } else if (selectedMethod === 'free_trip') {
            $('#payButton').html('<i class="fas fa-gift"></i> Use Free Trip');
        } else {
            $('#payButton').html('<i class="fas fa-lock"></i> Complete Payment');
        }
    }
    
    // Format card number
    function formatCardNumber() {
        let cardNumber = $(this).val().replace(/\D/g, '');
        let formatted = '';
        
        for (let i = 0; i < cardNumber.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formatted += ' ';
            }
            formatted += cardNumber[i];
        }
        
        $(this).val(formatted.substring(0, 19));
    }
    
    // Format CVV
    function formatCVV() {
        let cvv = $(this).val().replace(/\D/g, '');
        $(this).val(cvv.substring(0, 4));
    }
    
    // Cancel booking
    function cancelBooking() {
        if (confirm('Are you sure you want to cancel this booking? Your seats will be released.')) {
            clearInterval(paymentTimer);
            $('#cancelForm').submit();
        }
    }
    
    // Form submission
    $('#paymentForm').submit(function(e) {
        e.preventDefault();
        
        if (!selectedMethod) {
            alert('Please select a payment method');
            return;
        }
        
        // Validate Mpesa phone
        if (selectedMethod === 'mpesa') {
            const phone = $('#mpesaPhone').val();
            if (!/^(\+254|0)[17]\d{8}$/.test(phone)) {
                alert('Please enter a valid Kenyan phone number');
                return;
            }
        }
        
        // Validate card details
        if (selectedMethod === 'card') {
            const cardNumber = $('#cardNumber').val().replace(/\s/g, '');
            const expiryMonth = $('#expiryMonth').val();
            const expiryYear = $('#expiryYear').val();
            const cvv = $('#cvv').val();
            
            if (cardNumber.length < 16) {
                alert('Please enter a valid card number');
                return;
            }
            
            if (!expiryMonth || !expiryYear) {
                alert('Please select card expiry date');
                return;
            }
            
            if (cvv.length < 3) {
                alert('Please enter CVV');
                return;
            }
            
            // Validate expiry date
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            
            if (parseInt(expiryYear) < currentYear || 
                (parseInt(expiryYear) === currentYear && parseInt(expiryMonth) < currentMonth)) {
                alert('Card has expired');
                return;
            }
        }
        
        // Show loading
        $('#payButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        // Submit form
        this.submit();
    });
    </script>
</body>
</html>