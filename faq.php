<?php
require_once 'includes/init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - MORI BOOKINGS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .faq-section {
            max-width: 800px;
            margin: 0 auto;
        }
        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .faq-question {
            background: #f8f9fa;
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .faq-question i {
            transition: transform 0.3s;
        }
        .faq-question.active i {
            transform: rotate(180deg);
        }
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: white;
        }
        .faq-answer.show {
            padding: 20px;
            max-height: 500px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="page-banner">
        <div class="container">
            <h1>Frequently Asked Questions</h1>
            <p>Find answers to common questions about bus booking.</p>
        </div>
    </div>

    <div class="container page-content">
        <div class="faq-section">
            <div class="faq-item">
                <div class="faq-question">
                    How do I book a bus ticket?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Simply enter your origin, destination, travel date, and number of passengers on the homepage. Click "Search Buses" to see available schedules. Select your preferred bus, choose seats, and proceed to payment via M-Pesa or card.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    Can I select my seat?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Yes! After selecting a schedule, you'll see an interactive seat map. Click on available seats to choose your preferred ones. You can select up to 6 seats per booking.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    What payment methods do you accept?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>We accept M-Pesa (STK Push) and credit/debit cards (Visa, Mastercard). You can also use loyalty points or free trips if you have them.</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    How do I cancel my booking?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Log in to your account, go to "My Bookings", find the booking you want to cancel, and click "Cancel". Cancellations are allowed up to 24 hours before departure with a refund (subject to our cancellation policy).</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    How does the loyalty program work?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>For every KES 100 you spend, you earn 1 loyalty point. After 10 completed trips, you earn a free trip! You can use points to pay for future bookings (up to 50% of the fare).</p>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    What if I miss my bus?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>If you miss your trip, we'll try to rebook you on the next available bus. If that's not possible, you may receive a partial refund based on our policy. Contact support immediately for assistance.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');
                
                // Close all others
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('show');
                });
                
                if (!isActive) {
                    question.classList.add('active');
                    answer.classList.add('show');
                }
            });
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>