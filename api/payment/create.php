<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\PaymentManager;
use KsrPay\IdempotencyManager;
use KsrPay\SecurityManager;

header('Content-Type: application/json');
(new SecurityManager())->sendCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']); exit;
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$orderId        = (int)($body['order_id']        ?? 0);
$method         = trim($body['payment_method']   ?? '');
$idempotencyKey = trim($body['idempotency_key']  ?? '');
$userId         = (int)$_SESSION['user_id'];

if ($orderId < 1 || $method === '') {
    echo json_encode(['success' => false, 'error' => 'order_id and payment_method are required.']); exit;
}

// Auto-generate idempotency key if not provided
if ($idempotencyKey === '') {
    $idem          = new IdempotencyManager($pdo);
    $idempotencyKey = $idem->generate($orderId, $userId, $method);
}

try {
    $mgr    = new PaymentManager($pdo);
    $result = $mgr->createPayment($orderId, $userId, $method, $idempotencyKey);

    $instructions = match ($method) {
        'upi'          => ['action' => 'redirect', 'url' => "/payment-methods/upi/merchant_qr.php?pid={$result['payment_id']}&oid={$orderId}"],
        'cod'          => ['action' => 'redirect', 'url' => "/payment-methods/cod/create_collection.php?pid={$result['payment_id']}&oid={$orderId}"],
        'bank_transfer'=> ['action' => 'redirect', 'url' => "/payment-methods/bank-transfer/instructions.php?pid={$result['payment_id']}&oid={$orderId}"],
        default        => ['action' => 'redirect', 'url' => "/payment/index.php?oid={$orderId}"],
    };

    echo json_encode([
        'success'      => true,
        'payment_id'   => $result['payment_id'],
        'amount_paise' => $result['amount_paise'],
        'amount_inr'   => $result['amount_inr'],
        'method'       => $method,
        'status'       => $result['status'],
        'idempotent'   => $result['idempotent'],
        'instructions' => $instructions,
    ]);
} catch (\InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('api/payment/create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}

