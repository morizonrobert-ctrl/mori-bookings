<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

if ($pathParts[0] !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$controller = $pathParts[1] ?? '';
$action = $pathParts[2] ?? 'index';

$allowedControllers = ['auth', 'book', 'payment', 'admin', 'notifications', 'seat_availability', 'google_auth'];

if (!in_array($controller, $allowedControllers)) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid API endpoint']);
    exit;
}

$file = __DIR__ . '/' . $controller . '.php';
if (file_exists($file)) {
    require_once $file;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Controller file missing']);
}