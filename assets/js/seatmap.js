/**
 * MORI BOOKINGS - Interactive Seat Map JavaScript
 * Handles seat selection, tooltips, and real-time updates
 */

class SeatMapManager {
    constructor(options = {}) {
        this.options = {
            scheduleId: 0,
            maxSeats: 1,
            selectedSeats: [],
            seatPrices: {},
            baseFare: 0,
            autoRefresh: true,
            refreshInterval: 30000, // 30 seconds
            ...options
        };
        
        this.selectedSeats = this.options.selectedSeats;
        this.maxSeats = this.options.maxSeats;
        this.seatPrices = this.options.seatPrices;
        this.baseFare = this.options.baseFare;
        
        this.tooltip = $('#seatTooltip');
        this.currentTooltipSeat = null;
        this.isSelecting = false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updateDisplay();
        
        if (this.options.autoRefresh) {
            this.startAutoRefresh();
        }
    }
    
    bindEvents() {
        // Seat hover events
        $(document).on('mouseenter', '.seat.status-available', (e) => {
            const seatElement = $(e.currentTarget);
            const seatNumber = seatElement.data('seat-number');
            const seatType = seatElement.data('seat-type');
            this.showSeatTooltip(seatElement, seatNumber, seatType);
        });
        
        $(document).on('mouseleave', '.seat', () => {
            this.hideSeatTooltip();
        });
        
        // Seat click events
        $(document).on('click', '.seat.status-available', (e) => {
            this.selectSeat($(e.currentTarget));
        });
        
        // Remove seat button
        $(document).on('click', '.remove-seat', (e) => {
            const seatNumber = $(e.currentTarget).closest('.selected-seat-item').data('seat');
            this.removeSeat(seatNumber);
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') {
                this.clearSelection();
            }
            if (e.key === 'r' && e.ctrlKey) {
                e.preventDefault();
                this.refreshSeatAvailability();
            }
        });
        
        // Window resize
        $(window).on('resize', () => {
            this.hideSeatTooltip();
        });
    }
    
    selectSeat(seatElement) {
        if (this.isSelecting) return;
        
        this.isSelecting = true;
        
        const seatNumber = seatElement.data('seat-number');
        const seatIndex = this.selectedSeats.indexOf(seatNumber);
        
        if (seatIndex > -1) {
            // Deselect seat
            this.selectedSeats.splice(seatIndex, 1);
            this.updateSeatElement(seatElement, 'available');
            this.showToast(`Seat ${seatNumber} deselected`, 'info');
        } else {
            // Check if max seats reached
            if (this.selectedSeats.length >= this.maxSeats) {
                this.showToast(`You can only select ${this.maxSeats} seat(s)`, 'warning');
                this.isSelecting = false;
                return;
            }
            
            // Select seat
            this.selectedSeats.push(seatNumber);
            this.updateSeatElement(seatElement, 'selected');
            this.showToast(`Seat ${seatNumber} selected`, 'success');
            
            // Play selection sound (optional)
            this.playSelectionSound();
        }
        
        this.updateDisplay();
        this.updateFormInput();
        
        // Hide tooltip if it's for this seat
        if (this.currentTooltipSeat === seatNumber) {
            this.hideSeatTooltip();
        }
        
        this.isSelecting = false;
    }
    
    removeSeat(seatNumber) {
        const seatIndex = this.selectedSeats.indexOf(seatNumber);
        
        if (seatIndex > -1) {
            this.selectedSeats.splice(seatIndex, 1);
            
            // Update seat on map
            $(`.seat[data-seat-number="${seatNumber}"]`)
                .removeClass('selected status-selected')
                .addClass('status-available');
            
            this.updateDisplay();
            this.updateFormInput();
            this.showToast(`Seat ${seatNumber} removed`, 'info');
        }
    }
    
    clearSelection() {
        if (this.selectedSeats.length === 0) return;
        
        if (confirm(`Clear all ${this.selectedSeats.length} selected seat(s)?`)) {
            // Reset all selected seats on map
            this.selectedSeats.forEach(seatNumber => {
                $(`.seat[data-seat-number="${seatNumber}"]`)
                    .removeClass('selected status-selected')
                    .addClass('status-available');
            });
            
            this.selectedSeats = [];
            this.updateDisplay();
            this.updateFormInput();
            this.showToast('All seats cleared', 'info');
        }
    }
    
    updateSeatElement(seatElement, status) {
        seatElement.removeClass('status-available status-selected status-booked status-reserved selected');
        
        switch (status) {
            case 'available':
                seatElement.addClass('status-available');
                break;
            case 'selected':
                seatElement.addClass('selected status-selected');
                break;
            case 'booked':
                seatElement.addClass('status-booked');
                seatElement.off('click');
                break;
            case 'reserved':
                seatElement.addClass('status-reserved');
                seatElement.off('click');
                break;
        }
    }
    
    showSeatTooltip(seatElement, seatNumber, seatType) {
        const priceInfo = this.seatPrices[seatNumber] || {
            final: this.baseFare * (seatElement.data('seat-price') || 1.0),
            type: seatType,
            description: seatType.charAt(0).toUpperCase() + seatType.slice(1).replace('_', ' ') + ' seat'
        };
        
        const tooltipHtml = `
            <h5>Seat ${seatNumber}</h5>
            <p>${priceInfo.description}</p>
            <div class="seat-price">KES ${priceInfo.final.toFixed(2)}</div>
            <div class="tooltip-actions">
                <button onclick="window.seatMapManager.selectSeat($(this).closest('.seat'))" 
                        class="btn-tooltip-select">
                    <i class="fas fa-check"></i> Select
                </button>
            </div>
        `;
        
        this.tooltip.html(tooltipHtml);
        this.tooltip.show();
        
        // Position tooltip
        const offset = seatElement.offset();
        this.tooltip.css({
            top: offset.top - this.tooltip.outerHeight() - 10,
            left: offset.left - (this.tooltip.outerWidth() / 2) + (seatElement.outerWidth() / 2)
        });
        
        this.currentTooltipSeat = seatNumber;
    }
    
    hideSeatTooltip() {
        this.tooltip.hide();
        this.currentTooltipSeat = null;
    }
    
    updateDisplay() {
        // Update selected count
        $('#selectedCount').text(`${this.selectedSeats.length}/${this.maxSeats}`);
        
        // Update selected seats list
        this.updateSelectedSeatsList();
        
        // Update available count
        this.updateAvailableCount();
        
        // Show/hide proceed button
        if (this.selectedSeats.length === this.maxSeats) {
            $('.proceed-actions').show();
            $('.selection-warning').hide();
        } else {
            $('.proceed-actions').hide();
            $('.selection-warning').show().html(`
                <i class="fas fa-exclamation-triangle"></i>
                Please select ${this.maxSeats - this.selectedSeats.length} more seat(s)
            `);
        }
        
        // Update total amount
        this.updateTotalAmount();
    }
    
    updateSelectedSeatsList() {
        const container = $('.seats-display');
        if (!container.length) return;
        
        if (this.selectedSeats.length === 0) {
            container.html(`
                <div class="no-seats">
                    <i class="fas fa-chair"></i>
                    <p>No seats selected yet. Click on available seats above.</p>
                </div>
            `);
            return;
        }
        
        let html = '';
        this.selectedSeats.forEach(seatNumber => {
            const priceInfo = this.seatPrices[seatNumber] || {
                final: this.baseFare,
                type: 'standard'
            };
            const seatType = priceInfo.type;
            const icon = this.getSeatIcon(seatType);
            
            html += `
                <div class="selected-seat-item" data-seat="${seatNumber}">
                    <div class="seat-info">
                        <div class="seat-icon">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="seat-details">
                            <h5>Seat ${seatNumber}</h5>
                            <span class="seat-type">${seatType.charAt(0).toUpperCase() + seatType.slice(1).replace('_', ' ')}</span>
                        </div>
                    </div>
                    <div class="seat-price">
                        KES ${priceInfo.final.toFixed(2)}
                        <button type="button" class="remove-seat">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    updateAvailableCount() {
        const availableCount = $('.seat.status-available:not(.aisle)').length;
        $('.available-count').text(availableCount);
    }
    
    updateTotalAmount() {
        let subtotal = 0;
        this.selectedSeats.forEach(seatNumber => {
            const priceInfo = this.seatPrices[seatNumber];
            if (priceInfo) {
                subtotal += priceInfo.final;
            }
        });
        
        const serviceFee = subtotal * 0.05;
        const total = subtotal + serviceFee;
        
        $('.seats-total .total-item:nth-child(2) strong').text(`KES ${subtotal.toFixed(2)}`);
        $('.seats-total .total-item:nth-child(3) strong').text(`KES ${serviceFee.toFixed(2)}`);
        $('.seats-total .total-item:nth-child(4) strong').text(`KES ${total.toFixed(2)}`);
    }
    
    updateFormInput() {
        $('#selectedSeatsInput').val(JSON.stringify(this.selectedSeats));
    }
    
    getSeatIcon(seatType) {
        const icons = {
            'window': 'fa-window-maximize',
            'aisle': 'fa-walking',
            'extra_legroom': 'fa-arrows-alt-v',
            'standard': 'fa-chair'
        };
        return icons[seatType] || 'fa-chair';
    }
    
    showToast(message, type = 'info') {
        // Remove existing toast
        $('.seat-toast').remove();
        
        const toast = $(`
            <div class="seat-toast toast-${type}">
                <i class="fas ${this.getToastIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $('body').append(toast);
        
        // Show toast with animation
        toast.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: 9999,
            opacity: 0,
            transform: 'translateX(100px)'
        });
        
        toast.animate({
            opacity: 1,
            transform: 'translateX(0)'
        }, 300);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.animate({
                opacity: 0,
                transform: 'translateX(100px)'
            }, 300, () => {
                toast.remove();
            });
        }, 3000);
    }
    
    getToastIcon(type) {
        const icons = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        return icons[type] || 'fa-info-circle';
    }
    
    playSelectionSound() {
        // Optional: Play a subtle sound effect
        try {
            const audio = new Audio('../assets/sounds/select.mp3');
            audio.volume = 0.3;
            audio.play();
        } catch (e) {
            // Sound not available, continue silently
        }
    }
    
    refreshSeatAvailability() {
        if (!this.options.scheduleId) return;
        
        $.ajax({
            url: '../api/seat_availability.php',
            method: 'POST',
            data: {
                schedule_id: this.options.scheduleId,
                action: 'check_availability',
                selected_seats: this.selectedSeats
            },
            beforeSend: () => {
                $('.seat-map-container').addClass('refreshing');
            },
            success: (response) => {
                if (response.success) {
                    this.processSeatUpdates(response.unavailable_seats);
                    this.showToast('Seat availability updated', 'success');
                }
            },
            error: () => {
                this.showToast('Failed to refresh seat availability', 'error');
            },
            complete: () => {
                $('.seat-map-container').removeClass('refreshing');
            }
        });
    }
    
    processSeatUpdates(unavailableSeats) {
        unavailableSeats.forEach(seat => {
            const seatElement = $(`.seat[data-seat-number="${seat.seat_number}"]`);
            
            if (seatElement.length) {
                // If seat was selected by us but now unavailable, deselect it
                if (this.selectedSeats.includes(seat.seat_number)) {
                    this.selectedSeats = this.selectedSeats.filter(s => s !== seat.seat_number);
                    this.showToast(`Seat ${seat.seat_number} is no longer available`, 'warning');
                }
                
                // Update seat status
                this.updateSeatElement(seatElement, seat.status);
            }
        });
        
        this.updateDisplay();
        this.updateFormInput();
    }
    
    startAutoRefresh() {
        setInterval(() => {
            this.refreshSeatAvailability();
        }, this.options.refreshInterval);
    }
    
    // Public methods
    setMaxSeats(maxSeats) {
        this.maxSeats = maxSeats;
        this.updateDisplay();
    }
    
    setSelectedSeats(seats) {
        this.selectedSeats = seats;
        this.updateDisplay();
        this.updateFormInput();
    }
    
    getSelectedSeats() {
        return [...this.selectedSeats];
    }
    
    getTotalAmount() {
        let total = 0;
        this.selectedSeats.forEach(seatNumber => {
            const priceInfo = this.seatPrices[seatNumber];
            if (priceInfo) {
                total += priceInfo.final;
            }
        });
        return total * 1.05; // Include service fee
    }
}

// Initialize when document is ready
$(document).ready(function() {
    // Check if we're on a seat selection page
    if ($('.seat-map-container').length) {
        // Get configuration from data attributes or global variables
        const scheduleId = $('.seat-map-container').data('schedule-id') || window.scheduleId || 0;
        const maxSeats = $('.seat-map-container').data('max-seats') || window.maxSeats || 1;
        const selectedSeats = window.selectedSeats || [];
        const seatPrices = window.seatPrices || {};
        const baseFare = window.baseFare || 0;
        
        // Initialize seat map manager
        window.seatMapManager = new SeatMapManager({
            scheduleId: scheduleId,
            maxSeats: maxSeats,
            selectedSeats: selectedSeats,
            seatPrices: seatPrices,
            baseFare: baseFare
        });
        
        // Add CSS for toast notifications
        $('head').append(`
            <style>
                .seat-toast {
                    background: white;
                    border-radius: 8px;
                    padding: 15px 20px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    min-width: 300px;
                    max-width: 400px;
                    border-left: 4px solid;
                    animation: slideIn 0.3s ease;
                }
                
                .toast-success {
                    border-left-color: #4CAF50;
                }
                
                .toast-success i {
                    color: #4CAF50;
                }
                
                .toast-error {
                    border-left-color: #F44336;
                }
                
                .toast-error i {
                    color: #F44336;
                }
                
                .toast-warning {
                    border-left-color: #FF9800;
                }
                
                .toast-warning i {
                    color: #FF9800;
                }
                
                .toast-info {
                    border-left-color: #2196F3;
                }
                
                .toast-info i {
                    color: #2196F3;
                }
                
                .seat-toast i {
                    font-size: 1.2rem;
                }
                
                .seat-toast span {
                    flex: 1;
                    color: #333;
                    font-weight: 500;
                }
                
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateX(100px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
                
                .btn-tooltip-select {
                    background: #2196F3;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    padding: 5px 10px;
                    font-size: 12px;
                    cursor: pointer;
                    margin-top: 8px;
                    display: inline-block;
                }
                
                .btn-tooltip-select:hover {
                    background: #1976D2;
                }
                
                .refreshing {
                    position: relative;
                }
                
                .refreshing:after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255,255,255,0.7);
                    z-index: 10;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .refreshing:before {
                    content: '🔄';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 2rem;
                    z-index: 11;
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    0% { transform: translate(-50%, -50%) rotate(0deg); }
                    100% { transform: translate(-50%, -50%) rotate(360deg); }
                }
            </style>
        `);
    }
});

// Global functions for inline onclick handlers
window.selectSeat = function(seatElement) {
    if (window.seatMapManager) {
        window.seatMapManager.selectSeat($(seatElement));
    }
};

window.removeSeat = function(seatNumber) {
    if (window.seatMapManager) {
        window.seatMapManager.removeSeat(seatNumber);
    }
};

window.clearSeatSelection = function() {
    if (window.seatMapManager) {
        window.seatMapManager.clearSelection();
    }
};