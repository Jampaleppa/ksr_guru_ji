<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../pricing.php';

$dbStatus = 'disconnected';
$dbDriver = 'none';
try {
    $pdo = getDBConnection();
    $dbDriver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $dbStatus = 'connected (' . $dbDriver . ')';
} catch (Throwable $e) {
    $dbStatus = 'error: ' . $e->getMessage();
}

// Test cart recalculation for Fresh Bread Loaf (ID 7 @ 45.00)
$testCart = [
    ['id' => '7', 'name' => 'Fresh Bread Loaf', 'price' => 45.00, 'quantity' => 1]
];
$pricingTest = ksrCalculateCartTotal($testCart);

echo json_encode([
    'status' => 'online',
    'app' => 'KSR GURU JI',
    'version' => '3.1.0',
    'php_version' => PHP_VERSION,
    'database' => $dbStatus,
    'pricing_engine' => [
        'bread_validated' => $pricingTest['validated'],
        'subtotal' => $pricingTest['subtotal'],
        'total' => $pricingTest['total'],
        'unmatched' => $pricingTest['unmatched']
    ],
    'timestamp' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
