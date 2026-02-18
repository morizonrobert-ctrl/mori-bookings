<?php
// cron/cleanup.php
require_once dirname(__DIR__) . '/includes/init.php';

use Mori\Database;

$db = Database::getInstance();

// Release expired seat locks
$sql = "UPDATE seat_maps SET status = 'available', locked_by = NULL, locked_until = NULL, booking_id = NULL 
        WHERE locked_until < NOW() AND status = 'reserved'";
$db->query($sql);
echo "[" . date('Y-m-d H:i:s') . "] Released expired seat locks.\n";

// Delete expired pending bookings
$sql = "DELETE FROM bookings WHERE booking_status = 'pending' AND token_expires_at < NOW()";
$db->query($sql);
echo "[" . date('Y-m-d H:i:s') . "] Deleted expired pending bookings.\n";