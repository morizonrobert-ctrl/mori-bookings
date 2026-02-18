/**
 * MORI BOOKINGS - Booking JavaScript
 * Handles search, schedule selection, and booking initiation.
 */

$(document).ready(function() {
    // Initialize date pickers on booking pages
    if ($('.datepicker').length) {
        flatpickr('.datepicker', {
            minDate: 'today',
            dateFormat: 'Y-m-d'
        });
    }

    // Passenger counter
    $('.passenger-plus').click(function() {
        const input = $(this).closest('.passengers').find('input');
        let val = parseInt(input.val());
        if (val < 10) input.val(val + 1);
    });
    $('.passenger-minus').click(function() {
        const input = $(this).closest('.passengers').find('input');
        let val = parseInt(input.val());
        if (val > 1) input.val(val - 1);
    });

    // Swap origin/destination
    $('#swap-locations').click(function() {
        const origin = $('#origin').val();
        const destination = $('#destination').val();
        $('#origin').val(destination);
        $('#destination').val(origin);
    });

    // View route stops
    $('.view-stops').click(function(e) {
        e.preventDefault();
        const routeId = $(this).data('route-id');
        $(`#stops-${routeId}`).slideToggle();
    });

    // Select a schedule and proceed to seat selection
    $('.select-schedule-btn').click(function() {
        const scheduleId = $(this).data('schedule-id');
        const passengers = $('#passengers').val() || 1;
        window.location.href = BASE_URL + 'customer/select_seats.php?schedule_id=' + scheduleId + '&passengers=' + passengers;
    });

    // AJAX search (optional, if using live search)
    $('#bookingSearchForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post(BASE_URL + 'api/book.php?action=search', formData, function(response) {
            if (response.success) {
                displaySearchResults(response.data);
            } else {
                showNotification(response.message || 'Search failed', 'error');
            }
        });
    });

    // Function to display search results dynamically
    window.displaySearchResults = function(data) {
        // Implementation depends on UI structure; we can provide a basic example
        console.log('Search results:', data);
        // ... (update DOM)
    };
});