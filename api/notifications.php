<?php
require_once '../includes/init.php';
requireAuth();

header('Content-Type: application/json');

$notification = new Mori\Notification();
$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'unread') {
        $notifications = $notification->getUnread($userId);
        echo json_encode(['success' => true, 'data' => $notifications]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    if ($action === 'mark_read') {
        $notificationId = $data['notification_id'] ?? 0;
        $notification->markAsRead($notificationId);
        echo json_encode(['success' => true]);
    } elseif ($action === 'send') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $targetUserId = $data['user_id'] ?? 0;
        $type = $data['type'] ?? 'system';
        $title = $data['title'] ?? '';
        $message = $data['message'] ?? '';
        $medium = $data['medium'] ?? 'email';
        $notification->send($targetUserId, $type, $title, $message, $medium);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}