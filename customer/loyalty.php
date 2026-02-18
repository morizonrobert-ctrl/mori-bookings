<?php
// customer/loyalty.php
require_once '../includes/init.php';
requireAuth();

$userId = currentUserId();
$db = Mori\Database::getInstance();

// Get user data
$user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

// Get loyalty points history
$pointsHistory = $db->fetchAll("
    SELECT lp.*, b.booking_ref 
    FROM loyalty_points lp
    LEFT JOIN bookings b ON lp.booking_id = b.id
    WHERE lp.user_id = ?
    ORDER BY lp.created_at DESC
", [$userId]);

// Get upcoming free trip eligibility
$nextFreeTrip = 10 - ($user['total_trips'] % 10);
$pointsToNextFree = $nextFreeTrip === 0 ? 0 : $nextFreeTrip;

// Get loyalty rules
$rules = $db->fetchAll("SELECT * FROM loyalty_rules WHERE is_active = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loyalty Program - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-gift"></i> Loyalty Program</h1>
            <p>Earn points and free trips every time you travel with us</p>
        </div>

        <div class="loyalty-summary">
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-star"></i></div>
                <div class="summary-details">
                    <h3><?php echo $user['loyalty_points']; ?></h3>
                    <p>Loyalty Points</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-gift"></i></div>
                <div class="summary-details">
                    <h3><?php echo $user['free_trips_available']; ?></h3>
                    <p>Free Trips Available</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="summary-details">
                    <h3><?php echo $user['total_trips']; ?></h3>
                    <p>Total Trips</p>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-clock"></i></div>
                <div class="summary-details">
                    <h3><?php echo $pointsToNextFree; ?> trips</h3>
                    <p>To next free trip</p>
                </div>
            </div>
        </div>

        <!-- Progress to next free trip -->
        <div class="progress-section">
            <h2>Your Journey to Next Free Trip</h2>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo (($user['total_trips'] % 10) / 10) * 100; ?>%"></div>
            </div>
            <p><?php echo $user['total_trips'] % 10; ?> / 10 trips completed</p>
        </div>

        <!-- Points History -->
        <div class="history-section">
            <h2>Points History</h2>
            <?php if (empty($pointsHistory)): ?>
                <p>No points earned yet. <a href="book.php">Book a trip</a> to start earning!</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Points</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pointsHistory as $point): ?>
                        <tr>
                            <td><?php echo formatDate($point['created_at'], 'M d, Y'); ?></td>
                            <td><?php echo ucfirst($point['source']); ?></td>
                            <td><?php echo $point['points']; ?></td>
                            <td><?php echo htmlspecialchars($point['description'] ?? ''); ?></td>
                            <td><?php echo $point['is_used'] ? 'Used' : 'Available'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Loyalty Rules -->
        <div class="rules-section">
            <h2>How It Works</h2>
            <div class="rules-grid">
                <?php foreach ($rules as $rule): ?>
                <div class="rule-card">
                    <h3><?php echo $rule['trips_required']; ?> Trips</h3>
                    <p>Earn a <?php echo $rule['reward_type'] === 'free_trip' ? 'Free Trip' : $rule['reward_value'] . ' discount'; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>