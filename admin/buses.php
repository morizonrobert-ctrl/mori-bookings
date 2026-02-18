<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$buses = $db->fetchAll("SELECT * FROM buses ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Buses - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-bus"></i> Manage Buses</h1>
            <a href="bus_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Bus</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bus Number</th>
                        <th>Plate Number</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Seats</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buses as $bus): ?>
                    <tr>
                        <td><?php echo $bus['id']; ?></td>
                        <td><?php echo htmlspecialchars($bus['bus_number']); ?></td>
                        <td><?php echo htmlspecialchars($bus['plate_number']); ?></td>
                        <td><?php echo htmlspecialchars($bus['bus_name']); ?></td>
                        <td><?php echo ucfirst($bus['bus_type']); ?></td>
                        <td><?php echo $bus['total_seats']; ?></td>
                        <td><span class="status-badge status-<?php echo $bus['status']; ?>"><?php echo ucfirst($bus['status']); ?></span></td>
                        <td>
                            <a href="bus_edit.php?id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                            <a href="bus_delete.php?id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i> Delete</a>
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