<?php

declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\PaymentManager;
use KsrPay\PaymentRouter;
use KsrPay\IdempotencyManager;
use KsrPay\SecurityManager;
use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$sec     = new SecurityManager();
$orderId = (int)($_POST['order_id'] ?? 0);
$method  = trim($_POST['method'] ?? '');

// CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$sec->validateCSRFToken($csrfToken)) {
    header('Location: /payment/index.php?oid=' . $orderId . '&err=csrf');
    exit;
}

if ($orderId < 1 || !in_array($method, ['upi', 'cod', 'bank_transfer'], true)) {
    header('Location: /payment/index.php?oid=' . $orderId . '&err=invalid_method');
    exit;
}

// Enforce method & COD cap eligibility before creating transaction
$oStmt = $pdo->prepare("SELECT net_amount, total FROM orders WHERE id = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1");
$oStmt->execute([$orderId, $userId]);
$oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
if ($oRow) {
    $rawAmt = (!empty($oRow['net_amount']) && (float)$oRow['net_amount'] > 0) ? (float)$oRow['net_amount'] : (float)($oRow['total'] ?? 0);
    $paise  = AmountCalculator::toPaise($rawAmt);
    $router = new PaymentRouter($pdo);
    $elig   = $router->isMethodEligible($method, $paise);
    if (empty($elig['eligible'])) {
        $errKey = ($method === 'cod') ? 'cod_limit' : 'invalid_method';
        header('Location: /payment/index.php?oid=' . $orderId . '&err=' . $errKey);
        exit;
    }
}

$idem = new IdempotencyManager($pdo);
$key  = $idem->generate($orderId, $userId, $method);
$mgr  = new PaymentManager($pdo);

try {
    $result = $mgr->createPayment($orderId, $userId, $method, $key);
} catch (\Throwable $e) {
    error_log('create_payment error: ' . $e->getMessage());
    header('Location: /payment/payment_failed.php?oid=' . $orderId . '&err=create_failed');
    exit;
}

$pid    = urlencode($result['payment_id']);
$router = $router ?? new PaymentRouter($pdo);
header('Location: ' . $router->getCheckoutUrl($method, $result['payment_id'], $orderId));
exit;
