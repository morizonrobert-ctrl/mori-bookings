<?php
require_once '../includes/init.php';

if (isLoggedIn()) {
    $user = getUser(currentUserId());
    if ($user['role'] === 'customer') {
        header('Location: dashboard.php');
    } elseif (in_array($user['role'], ['admin', 'super_admin', 'operator'])) {
        header('Location: ../admin/dashboard.php');
    } elseif ($user['role'] === 'driver') {
        header('Location: ../driver/dashboard.php');
    } else {
        header('Location: ../index.php');
    }
} else {
    header('Location: ../index.php');
}
exit;