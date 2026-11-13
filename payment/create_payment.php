<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\PaymentManager;
use KsrPay\IdempotencyManager;
use KsrPay\SecurityManager;
use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /'); exit;
}

$userId  = (int)$_SESSION['user_id'];
$sec     = new SecurityManager();

// CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$sec->validateCSRFToken($csrfToken)) {
    header('Location: /?err=csrf'); exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$method  = trim($_POST['method'] ?? '');

if ($orderId < 1 || !in_array($method, ['upi','cod','bank_transfer'], true)) {
    header('Location: /payment/index.php?oid=' . $orderId . '&err=invalid_method'); exit;
}

$idem = new IdempotencyManager($pdo);
$key  = $idem->generate($orderId, $userId, $method);
$mgr  = new PaymentManager($pdo);

try {
    $result = $mgr->createPayment($orderId, $userId, $method, $key);
} catch (\Throwable $e) {
    error_log('create_payment error: ' . $e->getMessage());
    header('Location: /payment/payment_failed.php?oid=' . $orderId . '&err=create_failed'); exit;
}

$pid = urlencode($result['payment_id']);
$oid = $orderId;

// Redirect to the method-specific page
switch ($method) {
    case 'upi':
        header("Location: /payment-methods/upi/merchant_qr.php?pid={$pid}&oid={$oid}");
        break;
    case 'cod':
        header("Location: /payment-methods/cod/create_collection.php?pid={$pid}&oid={$oid}");
        break;
    case 'bank_transfer':
        header("Location: /payment-methods/bank-transfer/instructions.php?pid={$pid}&oid={$oid}");
        break;
    default:
        header('Location: /payment/index.php?oid=' . $oid);
}
exit;

