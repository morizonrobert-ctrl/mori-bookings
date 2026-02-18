<?php
require_once '../includes/init.php';
requireAdmin();

$db = Mori\Database::getInstance();

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_bookings,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(amount_paid), 0) as total_paid,
        COUNT(DISTINCT user_id) as unique_customers
    FROM bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$startDate, $endDate]);

$daily = $db->fetchAll("
    SELECT DATE(created_at) as date, COUNT(*) as bookings, COALESCE(SUM(amount_paid), 0) as revenue
    FROM bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
", [$startDate, $endDate]);

$methods = $db->fetchAll("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
    GROUP BY payment_method
", [$startDate, $endDate]);

$routes = $db->fetchAll("
    SELECT r.origin_city, r.destination_city, COUNT(b.id) as bookings, COALESCE(SUM(b.amount_paid), 0) as revenue
    FROM bookings b
    JOIN schedules s ON b.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY r.id
    ORDER BY revenue DESC
    LIMIT 10
", [$startDate, $endDate]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - MORI BOOKINGS Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-dashboard">
    <?php include 'includes/admin_header.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-chart-bar"></i> Reports</h1>
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_bookings']; ?></h3>
                <p>Total Bookings</p>
            </div>
            <div class="stat-card">
                <h3>KES <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <h3>KES <?php echo number_format($stats['total_paid'], 2); ?></h3>
                <p>Amount Paid</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['unique_customers']; ?></h3>
                <p>Unique Customers</p>
            </div>
        </div>

        <div class="chart-card">
            <h2>Daily Bookings & Revenue</h2>
            <canvas id="dailyChart"></canvas>
        </div>

        <div class="chart-card">
            <h2>Payment Methods</h2>
            <canvas id="paymentChart"></canvas>
        </div>

        <div class="chart-card">
            <h2>Top Routes</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $r): ?>
                    <tr>
                        <td><?php echo $r['origin_city'] . ' → ' . $r['destination_city']; ?></td>
                        <td><?php echo $r['bookings']; ?></td>
                        <td>KES <?php echo number_format($r['revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Daily chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($daily, 'date')) . '"'; ?>],
                datasets: [
                    {
                        label: 'Bookings',
                        data: [<?php echo implode(',', array_column($daily, 'bookings')); ?>],
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        yAxisID: 'y',
                    },
                    {
                        label: 'Revenue (KES)',
                        data: [<?php echo implode(',', array_column($daily, 'revenue')); ?>],
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Bookings' } },
                    y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'Revenue' }, grid: { drawOnChartArea: false } }
                }
            }
        });

        // Payment methods chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($methods, 'payment_method')) . '"'; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($methods, 'count')); ?>],
                    backgroundColor: ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0']
                }]
            }
        });
    </script>

    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>