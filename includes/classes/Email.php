<?php
namespace Mori;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setup();
    }

    private function setup() {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = SMTP_HOST;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = SMTP_USER;
            $this->mailer->Password   = SMTP_PASS;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = SMTP_PORT;
            $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
        } catch (Exception $e) {
            error_log("Email setup error: " . $e->getMessage());
        }
    }

    public function send($to, $subject, $body, $attachments = []) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;
            $this->mailer->isHTML(false);

            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $this->mailer->addAttachment($attachment);
                }
            }

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }

    public function sendHTML($to, $subject, $htmlBody, $textBody = '', $attachments = []) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody;
            $this->mailer->AltBody = $textBody ?: strip_tags($htmlBody);
            $this->mailer->isHTML(true);

            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $this->mailer->addAttachment($attachment);
                }
            }

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }

    public function sendBookingConfirmation($to, $booking, $pdfPath = null) {
        $subject = "Booking Confirmation - MORI BOOKINGS";
        $body = "Dear {$booking['first_name']},\n\n";
        $body .= "Your booking {$booking['booking_ref']} has been confirmed.\n";
        $body .= "Route: {$booking['origin_city']} → {$booking['destination_city']}\n";
        $body .= "Departure: " . date('M d, Y H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])) . "\n";
        $body .= "Seats: " . implode(', ', json_decode($booking['seat_numbers'], true)) . "\n";
        $body .= "Total: KES " . number_format($booking['total_amount'], 2) . "\n\n";
        $body .= "Thank you for choosing MORI BOOKINGS!";

        $attachments = $pdfPath ? [$pdfPath] : [];
        return $this->send($to, $subject, $body, $attachments);
    }
}