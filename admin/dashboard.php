<?php
require_once '../includes/init.php';
require_once '../includes/auth_modal.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user = new \Mori\User();
$userData = $user->getUser($_SESSION['user_id']);

// Check if user is admin
if (!$user->isAdmin($_SESSION['user_id'])) {
    header('Location: ../customer/dashboard.php');
    exit;
}

// Get dashboard statistics
$booking = new \Mori\Booking();
$payment = new \Mori\Payment();
$db = \Mori\Database::getInstance();

// Overall stats
$totalBookings = $db->fetchColumn("SELECT COUNT(*) FROM bookings");
$totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount_paid), 0) FROM bookings WHERE payment_status = 'paid'");
$totalUsers = $db->fetchColumn("SELECT COUNT(*) FROM users");
$activeBookings = $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed'");

// Today's stats
$todayBookings = $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()");
$todayRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount_paid), 0) FROM bookings WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
$newUsersToday = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");

// Recent bookings
$recentBookings = $db->fetchAll("
    SELECT b.*, u.first_name, u.last_name, u.phone,
           r.origin_city, r.destination_city
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN schedules s ON b.schedule_id = s.id
    JOIN routes r ON s.route_id = r.id
    ORDER BY b.created_at DESC
    LIMIT 10
");

// Recent payments
$recentPayments = $db->fetchAll("
    SELECT p.*, b.booking_ref, u.first_name, u.last_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE p.status = 'completed'
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Bus occupancy
$busOccupancy = $db->fetchAll("
    SELECT b.bus_number, b.bus_name,
           COUNT(s.id) as total_schedules,
           SUM(s.booked_seats) as total_booked_seats,
           SUM(b.total_seats * COUNT(s.id)) as total_capacity,
           ROUND((SUM(s.booked_seats) / SUM(b.total_seats * COUNT(s.id))) * 100, 2) as occupancy_rate
    FROM buses b
    LEFT JOIN schedules s ON b.id = s.bus_id AND s.departure_date >= CURDATE()
    GROUP BY b.id
    ORDER BY occupancy_rate DESC
    LIMIT 10
");

// Revenue trend (last 7 days)
$revenueTrend = $db->fetchAll("
    SELECT DATE(created_at) as date,
           COUNT(*) as bookings,
           COALESCE(SUM(amount_paid), 0) as revenue
    FROM bookings
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MORI BOOKINGS</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css"
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-bus"></i>
                <span>MORI ADMIN</span>
            </a>
        </div>
        
        <div class="user-profile">
            <div class="avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></h4>
                <p class="role"><?php echo ucfirst(str_replace('_', ' ', $userData['role'])); ?></p>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <ul>
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
                <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                <li><a href="schedules.php"><i class="fas fa-calendar-alt"></i> Schedules</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($userData['first_name']); ?>!</p>
            </div>
            
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </div>
                <div class="quick-actions">
                    <a href="bookings.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-bookings">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalBookings); ?></h3>
                    <p>Total Bookings</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> 12%</span>
                    <small>vs last month</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total-revenue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>KES <?php echo number_format($totalRevenue, 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> 18%</span>
                    <small>vs last month</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card">
                    <div class="stat-icon total-users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalUsers); ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="stat-trend">
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> 8%</span>
                        <small>vs last month</small>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon active-bookings">
                    <i class="fas fa-bus"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($activeBookings); ?></h3>
                    <p>Active Bookings</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> 5%</span>
                    <small>Today: <?php echo $todayBookings; ?></small>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Revenue Trend (Last 7 Days)</h3>
                    <select class="chart-filter">
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Booking Status Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="content-grid">
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Bookings</h3>
                    <a href="bookings.php" class="view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking Ref</th>
                                <th>Customer</th>
                                <th>Route</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td><a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="booking-ref"><?php echo $booking['booking_ref']; ?></a></td>
                                <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['origin_city'] . ' → ' . $booking['destination_city']); ?></td>
                                <td><strong>KES <?php echo number_format($booking['total_amount'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, H:i', strtotime($booking['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Recent Payments</h3>
                    <a href="payments.php" class="view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Txn ID</th>
                                <th>Booking Ref</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><code><?php echo $payment['transaction_id']; ?></code></td>
                                <td><?php echo $payment['booking_ref']; ?></td>
                                <td>
                                    <span class="payment-method method-<?php echo $payment['payment_method']; ?>">
                                        <i class="fas fa-<?php echo $payment['payment_method'] === 'mpesa' ? 'mobile-alt' : 'credit-card'; ?>"></i>
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    </span>
                                </td>
                                <td><strong>KES <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, H:i', strtotime($payment['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bus Occupancy -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-bus"></i> Bus Occupancy</h3>
            </div>
            <div class="occupancy-grid">
                <?php foreach ($busOccupancy as $bus): ?>
                <div class="bus-card">
                    <div class="bus-header">
                        <h4><?php echo htmlspecialchars($bus['bus_name']); ?></h4>
                        <span class="bus-number"><?php echo $bus['bus_number']; ?></span>
                    </div>
                    <div class="bus-stats">
                        <div class="stat">
                            <span class="label">Schedules</span>
                            <span class="value"><?php echo $bus['total_schedules']; ?></span>
                        </div>
                        <div class="stat">
                            <span class="label">Booked Seats</span>
                            <span class="value"><?php echo $bus['total_booked_seats']; ?></span>
                        </div>
                        <div class="stat">
                            <span class="label">Occupancy</span>
                            <span class="value"><?php echo $bus['occupancy_rate']; ?>%</span>
                        </div>
                    </div>
                    <div class="occupancy-bar">
                        <div class="bar-fill" style="width: <?php echo min($bus['occupancy_rate'], 100); ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
    $(document).ready(function() {
        // Revenue Chart
        var revenueCtx = document.getElementById('revenueChart').getContext('2d');
        var revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($revenueTrend, 'date')) . '"'; ?>],
                datasets: [{
                    label: 'Revenue (KES)',
                    data: [<?php echo implode(',', array_column($revenueTrend, 'revenue')); ?>],
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Chart
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Confirmed', 'Pending', 'Cancelled', 'Completed', 'No Show'],
                datasets: [{
                    data: [
                        <?php echo $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed'"); ?>,
                        <?php echo $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'"); ?>,
                        <?php echo $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'cancelled'"); ?>,
                        <?php echo $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed'"); ?>,
                        <?php echo $db->fetchColumn("SELECT COUNT(*) FROM bookings WHERE booking_status = 'no_show'"); ?>
                    ],
                    backgroundColor: [
                        '#4CAF50',
                        '#FFC107',
                        '#F44336',
                        '#2196F3',
                        '#9C27B0'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Filter charts
        $('.chart-filter').change(function() {
            var days = $(this).val();
            // In production, this would fetch new data via AJAX
            console.log('Filter changed to: ' + days + ' days');
        });
    });
    </script>
</body>
</html>