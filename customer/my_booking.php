<?php
require_once '../includes/init.php';
requireAuth();

$booking = new Mori\Booking();
$userId = currentUserId();

$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$bookings = $booking->getUserBookings($userId, $status === 'all' ? null : $status, $limit, $offset);

// Get total count for pagination
$db = Mori\Database::getInstance();
$countSql = "SELECT COUNT(*) FROM bookings WHERE user_id = ?";
$params = [$userId];
if ($status !== 'all') {
    $countSql .= " AND booking_status = ?";
    $params[] = $status;
}
$total = $db->fetchColumn($countSql, $params);
$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i> My Bookings</h1>
            <p>View and manage your trip bookings</p>
        </div>

        <!-- Status Filter -->
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=confirmed" class="filter-tab <?php echo $status === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=completed" class="filter-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?status=cancelled" class="filter-tab <?php echo $status === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="no-results">
                <i class="fas fa-ticket-alt"></i>
                <h3>No bookings found</h3>
                <p><a href="book.php">Book your first trip now!</a></p>
            </div>
        <?php else: ?>
            <div class="bookings-table">
                <table>
                    <thead>
                        <tr>
                            <th>Booking Ref</th>
                            <th>Route</th>
                            <th>Date</th>
                            <th>Seats</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong><?php echo $b['booking_ref']; ?></strong></td>
                            <td><?php echo $b['origin_city']; ?> → <?php echo $b['destination_city']; ?></td>
                            <td><?php echo formatDate($b['departure_date'] . ' ' . $b['departure_time'], 'M d, Y H:i'); ?></td>
                            <td><?php echo implode(', ', json_decode($b['seat_numbers'], true)); ?></td>
                            <td>KES <?php echo number_format($b['total_amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $b['booking_status']; ?>"><?php echo ucfirst($b['booking_status']); ?></span></td>
                            <td>
                                <a href="view_booking.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                <?php if ($b['booking_status'] === 'confirmed' && strtotime($b['departure_date'] . ' ' . $b['departure_time']) > time() + 24*3600): ?>
                                    <a href="cancel_booking.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>