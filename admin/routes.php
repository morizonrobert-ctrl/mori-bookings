<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$routes = $db->fetchAll("SELECT * FROM routes ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Routes - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-route"></i> Manage Routes</h1>
            <a href="route_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Route</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Route Code</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Distance (km)</th>
                        <th>Duration (hrs)</th>
                        <th>Base Fare</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $r): ?>
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['route_code']); ?></td>
                        <td><?php echo htmlspecialchars($r['origin_city']); ?></td>
                        <td><?php echo htmlspecialchars($r['destination_city']); ?></td>
                        <td><?php echo $r['distance_km']; ?></td>
                        <td><?php echo $r['estimated_hours']; ?></td>
                        <td>KES <?php echo number_format($r['base_fare'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $r['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $r['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <a href="route_edit.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                            <a href="route_delete.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
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