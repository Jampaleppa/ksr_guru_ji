<?php
/**
 * REST API Gateway: /api/auth/login.php
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/Controllers/AuthController.php';

use App\Controllers\AuthController;

$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['identifier'])) {
    
    $controller = new AuthController();
    $response = $controller->loginUser($input);
    
    echo $response;
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Login Schema']);
}
