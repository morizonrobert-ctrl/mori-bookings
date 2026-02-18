<?php


// ensure session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// if no authenticated user, provide a guest user context
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 0,
        'username' => 'guest',
        'role' => 'guest',
        'display_name' => 'Guest',
        'is_guest' => true
    ];
}

// optional constant for quick checks
if (!defined('IS_GUEST')) {
    define('IS_GUEST', ($_SESSION['user']['is_guest'] ?? false) === true);
}
// ...existing code...

require_once 'includes/init.php';
 
// Get Kenyan destinations
$db = Mori\Database::getInstance();
$kenyanData = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'major_cities'");
$cities = json_decode($kenyanData['setting_value'] ?? '[]', true) ?: [];

// Get popular routes
$popularRoutesData = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'popular_routes'");
$popularRoutes = json_decode($popularRoutesData['setting_value'] ?? '[]', true) ?: [];

// Get featured buses
$featuredBuses = $db->fetchAll("SELECT * FROM buses WHERE status = 'active' ORDER BY RAND() LIMIT 4");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MORI BOOKINGS - Kenya's Premier Bus Booking Platform</title>
    <meta name="description" content="Book bus tickets across Kenya easily. Search routes, select seats, pay with M-Pesa or card. Trusted by thousands of travelers.">
    <meta name="keywords" content="bus booking Kenya, travel Kenya, M-Pesa payment, Nairobi to Mombasa, bus tickets">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Modern Navbar */


.nav-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
}

.nav-toggle span {
    width: 25px;
    height: 3px;
    background: #333;
    margin: 3px 0;
    transition: 0.3s;
}



.btn-nav {
    background: #4CAF50;
    color: white !important;
    padding: 0.5rem 1.5rem !important;
}

/* Dropdown */
.dropdown {
    position: relative;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    min-width: 200px;
    z-index: 100;
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu li {
    list-style: none;
}

.dropdown-menu a {
    display: block;
    padding: 0.8rem 1.5rem !important;
    color: #333 !important;
    border-radius: 0 !important;
}

.dropdown-menu a:hover {
    background: #f8f9fa !important;
    color: #4CAF50 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .nav-toggle {
        display: flex;
    }
    
    .nav-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        flex-direction: column;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .nav-menu.active {
        display: flex;
    }
    
    .dropdown-menu {
        position: static;
        box-shadow: none;
        padding-left: 1rem;
    }
}
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section with Animated Background -->
    <section class="hero">
        <div class="hero-slideshow">
            <div class="slide" style="background-image: url('assets/images/bus1.jpg');"></div>
            <div class="slide" style="background-image: url('assets/images/bus2.jpg');"></div>
            <div class="slide" style="background-image: url('assets/images/bus3.jpg');"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <h1 class="animate-text">Travel Kenya with Ease</h1>
                <p class="subtitle animate-text-delay">Book bus tickets online, select seats, and pay with M-Pesa or card</p>
                
                <!-- Search Form -->
                <div class="search-box animate-up">
                    <form method="POST" action="customer/book.php">
                        <div class="search-row">
                            <div class="search-field">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" name="origin" id="origin" placeholder="From (e.g., Nairobi)" list="cities" autocomplete="off" required>
                            </div>
                            <div class="search-field">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" name="destination" id="destination" placeholder="To (e.g., Mombasa)" list="cities" autocomplete="off" required>
                            </div>
                            <datalist id="cities">
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="search-field">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="text" name="date" id="date" class="datepicker" placeholder="Travel Date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="search-field passengers">
                                <i class="fas fa-users"></i>
                                <input type="number" name="passengers" id="passengers" min="1" max="10" value="1">
                                <div class="passenger-controls">
                                    <button type="button" class="passenger-minus">-</button>
                                    <button type="button" class="passenger-plus">+</button>
                                </div>
                            </div>
                            <button type="submit" class="search-btn">Search Buses</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title">Why Choose MORI BOOKINGS?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Safe & Secure</h3>
                    <p>All buses are regularly inspected and drivers certified.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Easy Booking</h3>
                    <p>Book your ticket in less than 2 minutes.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h3>Best Prices</h3>
                    <p>Competitive fares with no hidden fees.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Mobile Friendly</h3>
                    <p>Book from anywhere, on any device.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Routes -->
    <section class="routes-section">
        <div class="container">
            <h2 class="section-title">Popular Routes</h2>
            <div class="routes-grid">
                <?php foreach (array_slice($popularRoutes, 0, 6) as $route):
                    list($origin, $dest) = explode('-', $route);
                ?>
                <a href="customer/book.php?origin=<?php echo urlencode($origin); ?>&destination=<?php echo urlencode($dest); ?>" class="route-card">
                    <div class="route-details">
                        <h4><?php echo htmlspecialchars($origin); ?> → <?php echo htmlspecialchars($dest); ?></h4>
                        <p><i class="fas fa-clock"></i> Multiple daily departures</p>
                        <p><i class="fas fa-chair"></i> Comfortable seats</p>
                    </div>
                    <span class="route-arrow"><i class="fas fa-arrow-right"></i></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Search & Select</h3>
                    <p>Enter your route, date, and number of passengers.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Choose Seats</h3>
                    <p>Select your preferred seats from interactive map.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Pay</h3>
                    <p>Pay via M-Pesa, card, or use loyalty points.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Travel</h3>
                    <p>Receive e-ticket and enjoy your journey.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to travel?</h2>
            <p>Join thousands of happy customers who travel with MORI BOOKINGS.</p>
            <?php if (!isLoggedIn()): ?>
                <button class="btn btn-primary btn-large" onclick="openAuthModal('register')">Sign Up Now</button>
            <?php else: ?>
                <a href="customer/book.php" class="btn btn-primary btn-large">Book Now</a>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/auth.js"></script>
    <script>
        // Date picker initialization
        flatpickr(".datepicker", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });

        // Passenger counter
        $('.passenger-plus').click(function() {
            let input = $('#passengers');
            let val = parseInt(input.val());
            if (val < 10) input.val(val + 1);
        });
        $('.passenger-minus').click(function() {
            let input = $('#passengers');
            let val = parseInt(input.val());
            if (val > 1) input.val(val - 1);
        });

        // Hero slideshow
        let currentSlide = 0;
        const slides = $('.slide');
        setInterval(() => {
            slides.eq(currentSlide).fadeOut(1000);
            currentSlide = (currentSlide + 1) % slides.length;
            slides.eq(currentSlide).fadeIn(1000);
        }, 5000);
    </script>
</body>
</html>