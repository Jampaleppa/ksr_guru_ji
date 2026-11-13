<?php

declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

header('Content-Type: application/json');
(new SecurityManager())->sendCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET required.']);
    exit;
}

$paymentId = trim($_GET['pid'] ?? $_GET['payment_id'] ?? '');
if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'pid is required.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT pt.payment_id, pt.status, pt.method, pt.amount_paise,
            pt.created_at, pt.verified_at, pt.customer_utr,
            o.order_number
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? LIMIT 1"
);
$stmt->execute([$paymentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Payment not found.']);
    exit;
}

$status   = ksrNormalizePaymentStatus($row['status']);
$calc     = new AmountCalculator();
$utrMask  = '';
if (!empty($row['customer_utr'])) {
    $u = $row['customer_utr'];
    $utrMask = strlen($u) > 8
        ? substr($u, 0, 4) . str_repeat('*', strlen($u) - 8) . substr($u, -4)
        : str_repeat('*', strlen($u));
}

// NEVER expose bank_confirmed_ref or admin details
echo json_encode([
    'success'      => true,
    'payment_id'   => $row['payment_id'],
    'order_number' => $row['order_number'],
    'status'       => $status,
    'message'      => ksrPaymentStatusMessage($status),
    'amount_inr'   => $calc->formatINR((int)$row['amount_paise']),
    'method'       => $row['method'],
    'verified'     => !empty($row['verified_at']),
    'terminal'     => paymentStatusIsTerminal($status),
    'utr_mask'     => $utrMask,
    'created_at'   => $row['created_at'],
]);
