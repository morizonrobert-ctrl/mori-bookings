<?php
namespace Mori;

class Admin {
    private $db;
    private $user;
    private $booking;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->user = new User();
        $this->booking = new Booking();
    }

    /**
     * Check whether a user ID is an admin (super_admin or admin)
     */
    public function isAdmin($userId)
    {
        return $this->user->isAdmin($userId);
    }

    /**
     * Convenience static check
     */
    public static function check($userId)
    {
        $inst = new self();
        return $inst->isAdmin($userId);
    }

    public function getDashboardStats()
    {
        // reuse Booking stats
        return $this->booking->getBookingStats();
    }

    public function getAllBookings($status = '')
    {
        $where = '';
        $params = [];
        if ($status) {
            $where = 'WHERE b.booking_status = :status';
            $params[':status'] = $status;
        }

        $sql = "SELECT b.*, s.departure_date, s.departure_time, r.origin_city, r.destination_city, u.first_name, u.last_name
                FROM bookings b
                JOIN schedules s ON b.schedule_id = s.id
                JOIN routes r ON s.route_id = r.id
                JOIN users u ON b.user_id = u.id
                {$where}
                ORDER BY b.created_at DESC
                LIMIT 1000";

        return $this->db->fetchAll($sql, $params);
    }

    public function getBuses()
    {
        return $this->db->fetchAll('SELECT * FROM buses ORDER BY id DESC');
    }

    public function getRoutes()
    {
        return $this->db->fetchAll('SELECT * FROM routes ORDER BY id DESC');
    }

    public function getUsers($role = '')
    {
        return $this->user->getAllUsers($role, 1000, 0);
    }

    public function getAllPayments()
    {
        return $this->db->fetchAll('SELECT * FROM payments ORDER BY created_at DESC LIMIT 1000');
    }

    public function getAllSchedules()
    {
        $sql = "SELECT s.*, b.bus_number, b.bus_name, r.origin_city, r.destination_city
                FROM schedules s
                JOIN buses b ON s.bus_id = b.id
                JOIN routes r ON s.route_id = r.id
                ORDER BY s.departure_date, s.departure_time";
        return $this->db->fetchAll($sql);
    }

    // administrative actions
    public function assignCustomerToBus($bookingId, $adminId, $newScheduleId = null)
    {
        return $this->booking->assignCustomerToBus($bookingId, $adminId, $newScheduleId);
    }

    public function createBus($busData)
    {
        return $this->db->insert('buses', $busData);
    }

    public function updateBus($busId, $busData)
    {
        return $this->db->update('buses', $busData, 'id = :id', [':id' => $busId]);
    }

    public function deleteBus($busId)
    {
        return $this->db->delete('buses', 'id = :id', [':id' => $busId]);
    }

    public function createRoute($routeData)
    {
        return $this->db->insert('routes', $routeData);
    }

    public function updateRoute($routeId, $routeData)
    {
        return $this->db->update('routes', $routeData, 'id = :id', [':id' => $routeId]);
    }

    public function deleteRoute($routeId)
    {
        return $this->db->delete('routes', 'id = :id', [':id' => $routeId]);
    }

    public function createSchedule($scheduleData)
    {
        return $this->db->insert('schedules', $scheduleData);
    }

    public function updateSchedule($scheduleId, $scheduleData)
    {
        return $this->db->update('schedules', $scheduleData, 'id = :id', [':id' => $scheduleId]);
    }

    public function deleteSchedule($scheduleId)
    {
        return $this->db->delete('schedules', 'id = :id', [':id' => $scheduleId]);
    }

    public function updateUserRole($userId, $role)
    {
        return $this->user->updateRole($userId, $role);
    }
}
