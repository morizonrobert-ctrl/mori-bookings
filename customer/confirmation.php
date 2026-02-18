<?php
require_once '../includes/init.php';
requireAuth();

$bookingRef = $_GET['booking_ref'] ?? '';
if (empty($bookingRef)) {
    redirect('my_bookings.php');
}

$booking = new Mori\Booking();
$bookingDetails = $booking->getBookingDetails($bookingRef);

if (!$bookingDetails || $bookingDetails['user_id'] != currentUserId()) {
    redirect('my_bookings.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 50px auto;
            text-align: center;
        }
        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-top: 30px;
            text-align: left;
        }
        .booking-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .detail-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 1.1rem;
            color: #333;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        .print-btn, .download-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }
        .print-btn {
            background: #2196F3;
            color: white;
        }
        .download-btn {
            background: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="confirmation-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Booking Confirmed!</h1>
        <p>Your booking has been successfully confirmed. A receipt has been sent to your email and phone.</p>

        <div class="booking-card">
            <h2>Booking Details</h2>
            <p><strong>Booking Reference:</strong> <?php echo $bookingDetails['booking_ref']; ?></p>
            <p><strong>Receipt Number:</strong> <?php echo $bookingDetails['receipt_number']; ?></p>

            <div class="booking-details">
                <div class="detail-item">
                    <div class="detail-label">Route</div>
                    <div class="detail-value"><?php echo $bookingDetails['origin_city']; ?> → <?php echo $bookingDetails['destination_city']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bus</div>
                    <div class="detail-value"><?php echo $bookingDetails['bus_name']; ?> (<?php echo $bookingDetails['bus_number']; ?>)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Departure</div>
                    <div class="detail-value"><?php echo formatDate($bookingDetails['departure_date'] . ' ' . $bookingDetails['departure_time']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Arrival</div>
                    <div class="detail-value"><?php echo formatDate($bookingDetails['arrival_date'] . ' ' . $bookingDetails['arrival_time']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Seats</div>
                    <div class="detail-value"><?php echo implode(', ', $bookingDetails['seat_numbers']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Paid</div>
                    <div class="detail-value">KES <?php echo number_format($bookingDetails['total_amount'], 2); ?></div>
                </div>
            </div>

            <div class="action-buttons">
                <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
                <a href="download_receipt.php?ref=<?php echo $bookingDetails['booking_ref']; ?>" class="download-btn"><i class="fas fa-download"></i> Download PDF</a>
            </div>
        </div>

        <p style="margin-top: 20px;">
            <a href="my_bookings.php">View My Bookings</a> | 
            <a href="book.php">Book Another Trip</a>
        </p>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>