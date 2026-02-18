/**
 * MORI BOOKINGS - Payment JavaScript
 * Handles payment method selection, form validation, and submission.
 */

$(document).ready(function() {
    // Payment method selection
    $('.payment-method-card').click(function() {
        const method = $(this).data('method');
        selectPaymentMethod(method);
    });

    // Initialize with default method if any
    if ($('.payment-method-card.selected').length === 0) {
        // Default to M-Pesa if available
        const defaultMethod = $('.payment-method-card[data-method="mpesa"]');
        if (defaultMethod.length) {
            selectPaymentMethod('mpesa');
        }
    }

    // Form submission
    $('#paymentForm').submit(function(e) {
        e.preventDefault();
        if (!validatePaymentForm()) return;

        const formData = {
            action: 'process_payment',
            method: $('#paymentMethod').val(),
            booking_id: $('#bookingId').val(),
            amount: $('#finalAmount').val(),
            // Additional fields based on method
        };

        // Add method-specific data
        if (formData.method === 'mpesa') {
            formData.phone = $('#mpesaPhone').val();
        } else if (formData.method === 'card') {
            formData.card = {
                number: $('#cardNumber').val().replace(/\s/g, ''),
                holder: $('#cardHolder').val(),
                expiry_month: $('#expiryMonth').val(),
                expiry_year: $('#expiryYear').val(),
                cvv: $('#cvv').val()
            };
        }

        // Disable button, show spinner
        const $btn = $('#payButton');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: BASE_URL + 'api/payment.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    showNotification('Payment successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = response.data.redirect || BASE_URL + 'customer/confirmation.php?booking_ref=' + response.data.booking_ref;
                    }, 2000);
                } else {
                    showNotification(response.message || 'Payment failed', 'error');
                    $btn.prop('disabled', false).html('Complete Payment');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
                $btn.prop('disabled', false).html('Complete Payment');
            }
        });
    });

    // Format card number input
    $('#cardNumber').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        let formatted = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) formatted += ' ';
            formatted += value[i];
        }
        $(this).val(formatted.substring(0, 19));
    });

    // CVV formatting
    $('#cvv').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, '').substring(0, 4));
    });

    // Toggle password visibility on payment page (if any)
    $('.toggle-password').click(function() {
        const input = $(this).parent().find('input');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});

function selectPaymentMethod(method) {
    $('#paymentMethod').val(method);
    $('.payment-method-card').removeClass('selected');
    $(`.payment-method-card[data-method="${method}"]`).addClass('selected');
    $('.payment-form').removeClass('active');
    $(`#${method}Form`).addClass('active');
    $('#payButton').prop('disabled', false);
}

function validatePaymentForm() {
    const method = $('#paymentMethod').val();
    if (!method) {
        showNotification('Please select a payment method.', 'warning');
        return false;
    }

    if (method === 'mpesa') {
        const phone = $('#mpesaPhone').val();
        if (!phone || !/^(\+254|0)[17]\d{8}$/.test(phone)) {
            showNotification('Please enter a valid Kenyan phone number (e.g., 0712345678).', 'error');
            return false;
        }
    } else if (method === 'card') {
        const cardNumber = $('#cardNumber').val().replace(/\s/g, '');
        const expiryMonth = $('#expiryMonth').val();
        const expiryYear = $('#expiryYear').val();
        const cvv = $('#cvv').val();

        if (cardNumber.length < 16) {
            showNotification('Please enter a valid card number.', 'error');
            return false;
        }
        if (!expiryMonth || !expiryYear) {
            showNotification('Please select card expiry date.', 'error');
            return false;
        }
        if (!cvv || cvv.length < 3) {
            showNotification('Please enter CVV.', 'error');
            return false;
        }
        // Additional validation (Luhn, etc.) can be added here
    }
    return true;
}