<?php
require_once '../includes/init.php';
requireAuth();

$user = new Mori\User();
$booking = new Mori\Booking();
$userId = currentUserId();
$userData = $user->getUser($userId);

// Get upcoming bookings
$upcomingBookings = $booking->getUserBookings($userId, 'confirmed', 5, 0);
$pastBookings = $booking->getUserBookings($userId, 'completed', 5, 0);

// Get loyalty info
$loyaltyPoints = $userData['loyalty_points'];
$freeTrips = $userData['free_trips_available'];
$totalTrips = $userData['total_trips'];

// Get stats
$db = Mori\Database::getInstance();
$totalSpent = $db->fetchColumn("SELECT COALESCE(SUM(amount_paid), 0) FROM bookings WHERE user_id = ? AND payment_status = 'paid'", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> My Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($userData['first_name']); ?>!</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $totalTrips; ?></h3>
                    <p>Total Trips</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <h3><?php echo $loyaltyPoints; ?></h3>
                    <p>Loyalty Points</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-gift"></i></div>
                <div class="stat-info">
                    <h3><?php echo $freeTrips; ?></h3>
                    <p>Free Trips</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3>KES <?php echo number_format($totalSpent, 2); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="book.php" class="action-btn"><i class="fas fa-bus"></i> Book a Trip</a>
            <a href="my_bookings.php" class="action-btn"><i class="fas fa-history"></i> My Bookings</a>
            <a href="profile.php" class="action-btn"><i class="fas fa-user"></i> Profile</a>
            <a href="loyalty.php" class="action-btn"><i class="fas fa-gift"></i> Loyalty</a>
        </div>

        <!-- Upcoming Trips -->
        <div class="section">
            <h2><i class="fas fa-calendar-check"></i> Upcoming Trips</h2>
            <?php if (empty($upcomingBookings)): ?>
                <p>No upcoming trips. <a href="book.php">Book now</a>!</p>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="booking-item">
                            <div class="booking-route">
                                <strong><?php echo $booking['origin_city']; ?></strong> → <strong><?php echo $booking['destination_city']; ?></strong>
                            </div>
                            <div class="booking-details">
                                <span><i class="fas fa-clock"></i> <?php echo formatDate($booking['departure_date'] . ' ' . $booking['departure_time']); ?></span>
                                <span><i class="fas fa-bus"></i> <?php echo $booking['bus_name']; ?></span>
                                <span><i class="fas fa-chair"></i> <?php echo implode(', ', json_decode($booking['seat_numbers'], true)); ?></span>
                            </div>
                            <div class="booking-actions">
                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">Cancel</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>