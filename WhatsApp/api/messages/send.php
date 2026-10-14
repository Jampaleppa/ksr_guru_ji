<?php
/**
 * REST API Gateway: /api/messages/send.php
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../app/Controllers/ChatController.php';
use App\Controllers\ChatController;

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['content'])) {
    
    $controller = new ChatController();
    $response = $controller->processInboundMessage($input);
    
    echo $response;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Message Schema']);
}
