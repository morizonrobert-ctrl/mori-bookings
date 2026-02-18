<?php
// includes/header.php
require_once __DIR__ . '/../includes/init.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userData = null;

if ($isLoggedIn) {
    $user = new \Mori\User();
    $userData = $user->getUser($_SESSION['user_id']);
}
?>
<header class="header">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-bus"></i>
                <span>MORI BOOKINGS</span>
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Home</a></li>
                <li><a href="#how-it-works"><i class="fas fa-play-circle"></i> How it Works</a></li>
                <li><a href="#routes"><i class="fas fa-route"></i> Popular Routes</a></li>
                <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                
                <?php if ($isLoggedIn && $userData): ?>
                    <!-- User Menu for logged in users -->
                    <li class="user-menu">
                        <a href="#" class="user-dropdown">
                            <div class="user-avatar">
                                <?php if ($userData['profile_image']): ?>
                                    <img src="<?php echo htmlspecialchars($userData['profile_image']); ?>" alt="<?php echo htmlspecialchars($userData['first_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($userData['first_name']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu">
                            <?php if ($userData['role'] === 'customer'): ?>
                                <a href="customer/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                                <a href="customer/my_bookings.php"><i class="fas fa-ticket-alt"></i> My Bookings</a>
                                <a href="customer/profile.php"><i class="fas fa-user"></i> Profile</a>
                                <a href="customer/loyalty.php"><i class="fas fa-gift"></i> Loyalty</a>
                            <?php elseif (in_array($userData['role'], ['admin', 'super_admin', 'operator'])): ?>
                                <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Login/Register Button -->
                    <li>
                        <button class="btn btn-primary auth-btn" onclick="openAuthModal()">
                            <i class="fas fa-user"></i> Login / Register
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>
</header>

<!-- Authentication Modal (Will be added via JavaScript) -->
<div id="authModal"></div>
<script>
    // Expose BASE_URL to client-side scripts
    window.BASE_URL = '<?php echo rtrim(BASE_URL, "/"); ?>/';
</script>