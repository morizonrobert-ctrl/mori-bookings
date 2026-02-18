/**
 * MORI BOOKINGS - Admin JavaScript
 * Handles admin dashboard, charts, and management pages.
 */

$(document).ready(function() {
    // Initialize charts if on dashboard
    if ($('#revenueChart').length) {
        loadDashboardCharts();
    }

    // Confirm delete actions
    $('.delete-btn').click(function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Toggle user status (active/inactive)
    $('.toggle-status').change(function() {
        const userId = $(this).data('user-id');
        const status = $(this).is(':checked') ? 'active' : 'inactive';
        $.post(BASE_URL + 'api/admin.php?action=update_user_status', {
            user_id: userId,
            status: status
        }, function(response) {
            if (response.success) {
                showNotification('User status updated', 'success');
            } else {
                showNotification('Failed to update status', 'error');
            }
        });
    });

    // Handle bulk actions
    $('#bulkActionBtn').click(function() {
        const action = $('#bulkAction').val();
        const selected = [];
        $('.select-item:checked').each(function() {
            selected.push($(this).val());
        });
        if (selected.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        if (action === 'delete') {
            if (!confirm('Delete selected items?')) return;
        }
        // Send bulk request
        $.post(BASE_URL + 'api/admin.php?action=bulk', {
            action: action,
            ids: selected
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Action failed');
            }
        });
    });

    // Date range picker for reports
    if ($('#dateRange').length) {
        flatpickr('#dateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            onChange: function(selectedDates, dateStr) {
                $('#startDate').val(selectedDates[0] ? formatDate(selectedDates[0]) : '');
                $('#endDate').val(selectedDates[1] ? formatDate(selectedDates[1]) : '');
            }
        });
    }

    // Export report
    $('#exportReport').click(function() {
        const type = $('#exportType').val();
        const start = $('#startDate').val();
        const end = $('#endDate').val();
        window.location.href = BASE_URL + 'admin/export.php?type=' + type + '&start=' + start + '&end=' + end;
    });
});

function loadDashboardCharts() {
    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: window.chartLabels || [],
            datasets: [{
                label: 'Revenue (KES)',
                data: window.chartData || [],
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4
            }]
        }
    });

    // Booking status pie chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: window.statusLabels || [],
            datasets: [{
                data: window.statusCounts || [],
                backgroundColor: ['#4CAF50', '#FFC107', '#F44336', '#2196F3', '#9C27B0']
            }]
        }
    });
}