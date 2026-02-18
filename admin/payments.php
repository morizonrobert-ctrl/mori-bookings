<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();
$payments = $db->fetchAll("
    SELECT p.*, b.booking_ref, u.first_name, u.last_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-credit-card"></i> Payments</h1>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Transaction ID</th>
                        <th>Booking Ref</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['transaction_id']); ?></td>
                        <td><?php echo $p['booking_ref']; ?></td>
                        <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                        <td>KES <?php echo number_format($p['amount'], 2); ?></td>
                        <td><?php echo ucfirst($p['payment_method']); ?></td>
                        <td><span class="status-badge status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                        <td><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>