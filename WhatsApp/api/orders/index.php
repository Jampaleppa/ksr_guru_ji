<?php
/**
 * REST API Gateway: /orders
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// In a real application, you'd integrate the autoloader and dispatch the route:
// require_once __DIR__ . '/../../vendor/autoload.php';
// use App\Controllers\\OrderController;

// Simulated controller boot for scaffold test
$response = [
    'endpoint' => '/api/orders',
    'controller' => 'OrderController',
    'status' => 'active',
    'timestamp' => date('Y-m-d H:i:s'),
    'method_caught' => $_SERVER['REQUEST_METHOD']
];

echo json_encode($response);
