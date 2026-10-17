<?php
/**
 * REST API Gateway: /api/auth/register.php
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simulated Autoloader capability for the prototype
require_once __DIR__ . '/../../app/Controllers/AuthController.php';
use App\Controllers\AuthController;

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['action']) && $input['action'] === 'register') {
    
    $controller = new AuthController();
    $response = $controller->registerUser($input);
    
    echo $response;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request Schema']);
}
