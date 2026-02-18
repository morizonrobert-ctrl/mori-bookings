<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if (!empty($status)) {
    $where[] = "b.booking_status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where[] = "(b.booking_ref LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $where);

$sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone,
               r.origin_city, r.destination_city
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        WHERE $whereClause
        ORDER BY b.created_at DESC
        LIMIT $limit OFFSET $offset";

$bookings = $db->fetchAll($sql, $params);

$countSql = "SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id = u.id WHERE $whereClause";
$total = $db->fetchColumn($countSql, $params);
$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-ticket-alt"></i> Bookings</h1>
            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search by ref, name, phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <!-- Status filter tabs -->
        <div class="filter-tabs">
            <a href="?status=" class="<?php echo empty($status) ? 'active' : ''; ?>">All</a>
            <a href="?status=pending" class="<?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=confirmed" class="<?php echo $status === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?status=completed" class="<?php echo $status === 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?status=cancelled" class="<?php echo $status === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
            <a href="?status=no_show" class="<?php echo $status === 'no_show' ? 'active' : ''; ?>">No Show</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Booking Ref</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?php echo $b['id']; ?></td>
                        <td><strong><?php echo $b['booking_ref']; ?></strong></td>
                        <td><?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?><br><small><?php echo $b['phone']; ?></small></td>
                        <td><?php echo $b['origin_city']; ?> → <?php echo $b['destination_city']; ?></td>
                        <td><?php echo formatDate($b['created_at'], 'M d, Y'); ?></td>
                        <td><?php echo $b['total_seats']; ?></td>
                        <td>KES <?php echo number_format($b['total_amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $b['booking_status']; ?>"><?php echo ucfirst($b['booking_status']); ?></span></td>
                        <td><span class="status-badge status-<?php echo $b['payment_status']; ?>"><?php echo ucfirst($b['payment_status']); ?></span></td>
                        <td>
                            <a href="booking_details.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline">View</a>
                            <?php if ($b['booking_status'] === 'confirmed' && $b['payment_status'] === 'paid'): ?>
                                <a href="assign_bus.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary">Assign</a>
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
                <a href="?status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>