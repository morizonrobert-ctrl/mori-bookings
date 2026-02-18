<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use AfricasTalking\SDK\AfricasTalking;

header('Content-Type: application/json');

// simple POST endpoint: expects `to` and `message` fields
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$to = isset($data['to']) ? $data['to'] : null;
$message = isset($data['message']) ? $data['message'] : null;

if (!$to || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing to or message']);
    exit;
}

try {
    $AT = new AfricasTalking(AT_USERNAME, AT_API_KEY);
    $sms = $AT->sms();
    $result = $sms->send([
        'to' => $to,
        'message' => $message,
        'from' => defined('SMS_SENDER_ID') ? SMS_SENDER_ID : null,
    ]);

    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
