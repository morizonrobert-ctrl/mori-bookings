<?php
namespace Mori;

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFGenerator {
    private $options;

    public function __construct() {
        $this->options = new Options();
        $this->options->set('defaultFont', 'DejaVu Sans');
        $this->options->set('isHtml5ParserEnabled', true);
    }

    public function generateReceipt($booking) {
        $html = $this->getReceiptHTML($booking);

        $dompdf = new Dompdf($this->options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'receipt_' . ($booking['booking_ref'] ?? uniqid()) . '.pdf';
        $path = __DIR__ . '/../../assets/receipts/' . $filename;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $dompdf->output());
        return $path;
    }

    private function getReceiptHTML($booking) {
        $seats = [];
        if (isset($booking['seat_numbers'])) {
            if (is_array($booking['seat_numbers'])) {
                $seats = $booking['seat_numbers'];
            } else {
                $decoded = json_decode($booking['seat_numbers'], true);
                $seats = is_array($decoded) ? $decoded : [$booking['seat_numbers']];
            }
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>';
        $html .= 'body{font-family: DejaVu Sans, sans-serif; font-size:14px; color:#333;}';
        $html .= 'table{width:100%; border-collapse:collapse;}';
        $html .= 'td{padding:6px; vertical-align:top;}';
        $html .= 'h1{font-size:20px;margin-bottom:0;}';
        $html .= 'hr{border:none;border-top:1px solid #eee;margin:10px 0;}';
        $html .= '</style></head><body>';

        $html .= '<h1>MORI BOOKINGS</h1>';
        $html .= '<h2>Booking Receipt</h2>';
        $html .= '<hr>';
        $html .= '<table>';
        $html .= '<tr><td><strong>Booking Ref:</strong></td><td>' . htmlspecialchars($booking['booking_ref'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td><strong>Receipt No:</strong></td><td>' . htmlspecialchars($booking['receipt_number'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td><strong>Customer:</strong></td><td>' . htmlspecialchars(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')) . '</td></tr>';
        $html .= '<tr><td><strong>Route:</strong></td><td>' . htmlspecialchars(($booking['origin_city'] ?? '') . ' → ' . ($booking['destination_city'] ?? '')) . '</td></tr>';
        $html .= '<tr><td><strong>Departure:</strong></td><td>' . htmlspecialchars(isset($booking['departure_date'], $booking['departure_time']) ? date('M d, Y H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])) : '') . '</td></tr>';
        $html .= '<tr><td><strong>Bus:</strong></td><td>' . htmlspecialchars(($booking['bus_name'] ?? '') . ' (' . ($booking['bus_number'] ?? '') . ')') . '</td></tr>';
        $html .= '<tr><td><strong>Seats:</strong></td><td>' . htmlspecialchars(implode(', ', $seats)) . '</td></tr>';
        $html .= '<tr><td><strong>Total Amount:</strong></td><td>KES ' . htmlspecialchars(number_format($booking['total_amount'] ?? 0, 2)) . '</td></tr>';
        $html .= '<tr><td><strong>Payment Method:</strong></td><td>' . htmlspecialchars(ucfirst($booking['payment_method'] ?? 'N/A')) . '</td></tr>';
        $html .= '</table>';
        $html .= '<hr>';
        $html .= '<p>Thank you for choosing MORI BOOKINGS!</p>';
        $html .= '</body></html>';

        return $html;
    }

    public function generateTicket($booking) {
        $seats = [];
        if (isset($booking['seat_numbers'])) {
            if (is_array($booking['seat_numbers'])) {
                $seats = $booking['seat_numbers'];
            } else {
                $decoded = json_decode($booking['seat_numbers'], true);
                $seats = is_array($decoded) ? $decoded : [$booking['seat_numbers']];
            }
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>';
        $html .= 'body{font-family: DejaVu Sans, sans-serif; font-size:14px; color:#333;}';
        $html .= '.ticket{border:1px solid #ddd;padding:10px;}';
        $html .= 'h1{font-size:18px;margin-bottom:0;}';
        $html .= 'table{width:100%; border-collapse:collapse;margin-top:10px;}';
        $html .= 'td{padding:6px; vertical-align:top;}';
        $html .= '</style></head><body>';

        $html .= '<div class="ticket">';
        $html .= '<h1>MORI BOOKINGS - E-Ticket</h1>';
        $html .= '<h2>Boarding Pass</h2>';
        $html .= '<hr>';
        $html .= '<table>';
        $html .= '<tr><td><strong>Booking Ref:</strong></td><td>' . htmlspecialchars($booking['booking_ref'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td><strong>Passenger:</strong></td><td>' . htmlspecialchars(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')) . '</td></tr>';
        $html .= '<tr><td><strong>Route:</strong></td><td>' . htmlspecialchars(($booking['origin_city'] ?? '') . ' → ' . ($booking['destination_city'] ?? '')) . '</td></tr>';
        $html .= '<tr><td><strong>Departure:</strong></td><td>' . htmlspecialchars(isset($booking['departure_date'], $booking['departure_time']) ? date('M d, Y H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])) : '') . '</td></tr>';
        $html .= '<tr><td><strong>Bus:</strong></td><td>' . htmlspecialchars(($booking['bus_name'] ?? '') . ' (' . ($booking['bus_number'] ?? '') . ')') . '</td></tr>';
        $html .= '<tr><td><strong>Seat(s):</strong></td><td>' . htmlspecialchars(implode(', ', $seats)) . '</td></tr>';
        $html .= '</table>';
        $html .= '<hr>';
        $html .= '<p>Please arrive at least 30 minutes before departure.</p>';
        $html .= '</div>';
        $html .= '</body></html>';

        $dompdf = new Dompdf($this->options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        $filename = 'ticket_' . ($booking['booking_ref'] ?? uniqid()) . '.pdf';
        $path = __DIR__ . '/../../assets/tickets/' . $filename;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, $dompdf->output());
        return $path;
    }
}