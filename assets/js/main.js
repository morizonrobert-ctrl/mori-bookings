/**
 * MORI BOOKINGS - Main JavaScript
 * Global utilities, helper functions, and shared functionality.
 */

// Global configuration
const BASE_URL = window.location.origin + '/mori-bookings/'; // Adjust if needed

// Toggle password visibility (used in login/register modals)
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.toggle-password i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Open auth modal (customer)
function openAuthModal(tab = 'login') {
    if (typeof authSystem !== 'undefined') {
        authSystem.open(tab);
    } else {
        // Fallback: redirect to standalone login page
        window.location.href = BASE_URL + 'login.php?tab=' + tab;
    }
}

// Close auth modal
function closeAuthModal() {
    if (typeof authSystem !== 'undefined') {
        authSystem.close();
    }
}

// Show notification toast (used by various pages)
function showNotification(message, type = 'info', duration = 5000) {
    // Remove any existing toast
    $('.notification-toast').remove();

    const toast = $(`
        <div class="notification-toast toast-${type}">
            <i class="fas ${getToastIcon(type)}"></i>
            <span>${message}</span>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        </div>
    `);

    $('body').append(toast);
    setTimeout(() => toast.addClass('show'), 10);

    const autoClose = setTimeout(() => {
        toast.removeClass('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);

    toast.find('.toast-close').click(() => {
        clearTimeout(autoClose);
        toast.removeClass('show');
        setTimeout(() => toast.remove(), 300);
    });
}

function getToastIcon(type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    return icons[type] || 'fa-info-circle';
}

// Format number as currency (KES)
function formatCurrency(amount) {
    return 'KES ' + Number(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format date to readable string
function formatDate(dateString, format = 'short') {
    const date = new Date(dateString);
    if (format === 'short') {
        return date.toLocaleDateString('en-KE', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    return date.toLocaleDateString('en-KE', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

// Format time
function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' });
}

// Handle AJAX errors globally
$(document).ajaxError(function(event, jqxhr, settings, error) {
    console.error('AJAX Error:', error);
    showNotification('Network error. Please try again.', 'error');
});

// Document ready
$(document).ready(function() {
    // ==================== Mobile Menu Toggle (Hamburger) ====================
    const $navToggle = $('.nav-toggle');
    const $navMenu = $('.nav-menu');

    if ($navToggle.length && $navMenu.length) {
        $navToggle.click(function(e) {
            e.stopPropagation();
            $navMenu.toggleClass('active');
            $(this).toggleClass('active');
        });

        // Close menu when clicking outside
        $(document).click(function(event) {
            if (!$(event.target).closest('.navbar').length) {
                $navMenu.removeClass('active');
                $navToggle.removeClass('active');
            }
        });

        // Close menu when a link is clicked (optional)
        $navMenu.find('a').click(function() {
            $navMenu.removeClass('active');
            $navToggle.removeClass('active');
        });
    }

    // ==================== Initialize Tooltips (if using Bootstrap) ====================
    if ($.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // ==================== CSRF Token for AJAX ====================
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // ==================== Auto-close Alerts ====================
    $('.alert').not('.alert-permanent').delay(5000).fadeOut(500);

    // ==================== Date Picker (Flatpickr) ====================
    if ($.fn.flatpickr) {
        $('.datepicker').flatpickr({
            minDate: 'today',
            dateFormat: 'Y-m-d'
        });
    }

    // ==================== Passenger Counter (used on index and booking pages) ====================
    $('.passenger-plus').click(function() {
        const $input = $(this).closest('.passengers').find('input');
        let val = parseInt($input.val());
        if (val < 10) $input.val(val + 1);
    });
    $('.passenger-minus').click(function() {
        const $input = $(this).closest('.passengers').find('input');
        let val = parseInt($input.val());
        if (val > 1) $input.val(val - 1);
    });

    // ==================== Swap Origin/Destination (on index) ====================
    $('#swap-locations').click(function() {
        const origin = $('#origin').val();
        const destination = $('#destination').val();
        $('#origin').val(destination);
        $('#destination').val(origin);
    });

    // ==================== Route Stops Toggle ====================
    $('.view-stops').click(function(e) {
        e.preventDefault();
        const routeId = $(this).data('route-id');
        $(`#stops-${routeId}`).slideToggle();
    });

    // ==================== Hero Slideshow (if present) ====================
    if ($('.hero-slideshow .slide').length > 1) {
        let currentSlide = 0;
        const slides = $('.hero-slideshow .slide');
        setInterval(() => {
            slides.eq(currentSlide).fadeOut(1000);
            currentSlide = (currentSlide + 1) % slides.length;
            slides.eq(currentSlide).fadeIn(1000);
        }, 5000);
    }
});

// Open admin modal
function openAdminModal() {
    document.getElementById('adminModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    // Clear previous errors
    document.getElementById('adminLoginError').style.display = 'none';
    document.getElementById('adminLoginError').innerHTML = '';
}

// Close admin modal
function closeAdminModal() {
    document.getElementById('adminModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.toggle-password i');
    if (input.type === 'password') {
        input.type = 'text';
        button.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        button.className = 'fas fa-eye';
    }
}

// Handle admin login form submission via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const adminForm = document.getElementById('adminLoginForm');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            const errorDiv = document.getElementById('adminLoginError');
            const submitBtn = document.getElementById('adminLoginBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.spinner');
            
            // Disable button, show spinner
            submitBtn.disabled = true;
            btnText.style.opacity = '0.5';
            spinner.style.display = 'inline-block';
            
            // Clear previous errors
            errorDiv.style.display = 'none';
            errorDiv.innerHTML = '';
            
            // Send AJAX request
            fetch(BASE_URL + 'admin/ajax_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email, password: password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to appropriate dashboard
                    window.location.href = data.redirect;
                } else {
                    // Show error
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    errorDiv.style.display = 'flex';
                    // Re-enable button
                    submitBtn.disabled = false;
                    btnText.style.opacity = '1';
                    spinner.style.display = 'none';
                }
            })
            .catch(error => {
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                errorDiv.style.display = 'flex';
                submitBtn.disabled = false;
                btnText.style.opacity = '1';
                spinner.style.display = 'none';
            });
        });
    }
});

// Close modal when clicking overlay or pressing ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminModal();
    }
});

// Also close on overlay click (already handled in onclick)