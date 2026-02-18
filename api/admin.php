<?php
require_once '../includes/init.php';
requireAdmin();

header('Content-Type: application/json');

$admin = new Mori\Admin();
$method = $_SERVER['REQUEST_METHOD'];
$userId = currentUserId();

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'stats') {
        echo json_encode(['success' => true, 'data' => $admin->getDashboardStats()]);
    } elseif ($action === 'bookings') {
        $status = $_GET['status'] ?? '';
        $bookings = $admin->getAllBookings($status);
        echo json_encode(['success' => true, 'data' => $bookings]);
    } elseif ($action === 'buses') {
        $buses = $admin->getBuses();
        echo json_encode(['success' => true, 'data' => $buses]);
    } elseif ($action === 'routes') {
        $routes = $admin->getRoutes();
        echo json_encode(['success' => true, 'data' => $routes]);
    } elseif ($action === 'users') {
        $role = $_GET['role'] ?? '';
        $users = $admin->getUsers($role);
        echo json_encode(['success' => true, 'data' => $users]);
    } elseif ($action === 'payments') {
        $payments = $admin->getAllPayments();
        echo json_encode(['success' => true, 'data' => $payments]);
    } elseif ($action === 'schedules') {
        $schedules = $admin->getAllSchedules();
        echo json_encode(['success' => true, 'data' => $schedules]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    try {
        if ($action === 'assign_bus') {
            $bookingId = $data['booking_id'] ?? 0;
            $newScheduleId = $data['schedule_id'] ?? null;
            $admin->assignCustomerToBus($bookingId, $userId, $newScheduleId);
            echo json_encode(['success' => true]);
        } elseif ($action === 'create_bus') {
            $busData = $data['bus'] ?? [];
            $admin->createBus($busData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_bus') {
            $busId = $data['bus_id'] ?? 0;
            $busData = $data['bus'] ?? [];
            $admin->updateBus($busId, $busData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete_bus') {
            $busId = $data['bus_id'] ?? 0;
            $admin->deleteBus($busId);
            echo json_encode(['success' => true]);
        } elseif ($action === 'create_route') {
            $routeData = $data['route'] ?? [];
            $admin->createRoute($routeData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_route') {
            $routeId = $data['route_id'] ?? 0;
            $routeData = $data['route'] ?? [];
            $admin->updateRoute($routeId, $routeData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete_route') {
            $routeId = $data['route_id'] ?? 0;
            $admin->deleteRoute($routeId);
            echo json_encode(['success' => true]);
        } elseif ($action === 'create_schedule') {
            $scheduleData = $data['schedule'] ?? [];
            $admin->createSchedule($scheduleData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_schedule') {
            $scheduleId = $data['schedule_id'] ?? 0;
            $scheduleData = $data['schedule'] ?? [];
            $admin->updateSchedule($scheduleId, $scheduleData);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete_schedule') {
            $scheduleId = $data['schedule_id'] ?? 0;
            $admin->deleteSchedule($scheduleId);
            echo json_encode(['success' => true]);
        } elseif ($action === 'update_user_role') {
            $targetUserId = $data['user_id'] ?? 0;
            $role = $data['role'] ?? '';
            $admin->updateUserRole($targetUserId, $role);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}