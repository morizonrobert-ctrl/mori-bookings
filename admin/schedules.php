<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$schedules = $db->fetchAll("
    SELECT s.*, b.bus_number, r.origin_city, r.destination_city
    FROM schedules s
    JOIN buses b ON s.bus_id = b.id
    JOIN routes r ON s.route_id = r.id
    ORDER BY s.departure_date DESC, s.departure_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Schedules - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-calendar-alt"></i> Manage Schedules</h1>
            <a href="schedule_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Schedule</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bus</th>
                        <th>Route</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Available Seats</th>
                        <th>Price Factor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td><?php echo htmlspecialchars($s['bus_number']); ?></td>
                        <td><?php echo $s['origin_city'] . ' → ' . $s['destination_city']; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($s['departure_date'] . ' ' . $s['departure_time'])); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($s['arrival_date'] . ' ' . $s['arrival_time'])); ?></td>
                        <td><?php echo $s['available_seats']; ?></td>
                        <td><?php echo $s['price_factor']; ?></td>
                        <td><span class="status-badge status-<?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                        <td>
                            <a href="schedule_edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                            <a href="schedule_delete.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>