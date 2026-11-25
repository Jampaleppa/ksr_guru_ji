<?php
declare(strict_types=1);
// api/payment/health.php — Public health-check endpoint

header('Content-Type: application/json');
header('Cache-Control: no-store');

$root = dirname(__DIR__, 2);
$status = ['ok' => true, 'service' => 'KSR GURU JI PAY', 'ts' => date('c'), 'checks' => []];

// DB check
try {
    require_once $root . '/config.php';
    require_once $root . '/includes/db.php';
    $pdo = getDBConnection();
    $pdo->query('SELECT 1');
    $status['checks']['db'] = 'ok';
} catch (Throwable $e) {
    $status['ok'] = false;
    $status['checks']['db'] = 'error';
}

// payment_state functions
$status['checks']['payment_state'] = function_exists('ksrNormalizePaymentStatus') ? 'ok' : 'missing';

http_response_code($status['ok'] ? 200 : 503);
echo json_encode($status, JSON_PRETTY_PRINT);
