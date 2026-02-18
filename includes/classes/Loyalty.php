<?php
namespace Mori;

class Loyalty {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function addPoints($userId, $points, $source = 'booking', $description = '', $bookingId = null) {
        $this->db->insert('loyalty_points', [
            'user_id' => $userId,
            'points' => $points,
            'source' => $source,
            'description' => $description,
            'booking_id' => $bookingId,
            'expires_at' => date('Y-m-d', strtotime('+1 year')),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->db->query("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?", [$points, $userId]);
        $this->checkFreeTripMilestone($userId);
    }

    public function usePoints($userId, $points, $bookingId) {
        $user = (new User())->getUser($userId);
        if ($user['loyalty_points'] < $points) {
            throw new \Exception('Insufficient points');
        }

        $this->db->insert('loyalty_points', [
            'user_id' => $userId,
            'points' => -$points,
            'source' => 'redemption',
            'description' => 'Points used for booking ' . $bookingId,
            'booking_id' => $bookingId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->db->query("UPDATE users SET loyalty_points = loyalty_points - ? WHERE id = ?", [$points, $userId]);
    }

    public function checkFreeTripMilestone($userId) {
        $user = (new User())->getUser($userId);
        $trips = $user['total_trips'];
        $freeEarned = floor($trips / 10);
        $currentFree = $user['free_trips_earned'];

        if ($freeEarned > $currentFree) {
            $newFree = $freeEarned - $currentFree;
            $this->db->update('users', [
                'free_trips_earned' => $freeEarned,
                'free_trips_available' => $user['free_trips_available'] + $newFree
            ], 'id = ?', [$userId]);

            $notification = new Notification();
            $notification->send($userId, 'loyalty', 'Free Trip Earned!',
                "Congratulations! You've earned a free trip for completing 10 trips.", 'both');
        }
    }

    public function useFreeTrip($userId, $bookingId) {
        $user = (new User())->getUser($userId);
        if ($user['free_trips_available'] <= 0) {
            throw new \Exception('No free trips available');
        }

        $this->db->update('users', [
            'free_trips_available' => $user['free_trips_available'] - 1
        ], 'id = ?', [$userId]);

        $this->db->insert('loyalty_points', [
            'user_id' => $userId,
            'points' => 0,
            'source' => 'free_trip',
            'description' => 'Free trip used for booking ' . $bookingId,
            'booking_id' => $bookingId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getPointsHistory($userId) {
        $sql = "SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }

    public function getPointsBalance($userId) {
        $user = (new User())->getUser($userId);
        return $user['loyalty_points'];
    }
}