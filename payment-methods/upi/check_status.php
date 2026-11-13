<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

header('Content-Type: application/json');

// GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET required.']);
    exit;
}

$paymentId = trim($_GET['pid'] ?? '');
if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing pid.']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT status, customer_utr, verified_at FROM payment_transactions WHERE payment_id = ? LIMIT 1"
);
$stmt->execute([$paymentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Payment not found.']);
    exit;
}

$status   = ksrNormalizePaymentStatus($row['status']);
$verified = !empty($row['verified_at']);
$utrMask  = '';

if (!empty($row['customer_utr'])) {
    $u       = $row['customer_utr'];
    $utrMask = strlen($u) > 8
        ? substr($u, 0, 4) . str_repeat('*', strlen($u) - 8) . substr($u, -4)
        : str_repeat('*', strlen($u));
}

// NEVER expose bank_confirmed_ref or admin details here
echo json_encode([
    'success'  => true,
    'status'   => $status,
    'message'  => ksrPaymentStatusMessage($status),
    'verified' => $verified,
    'utr_mask' => $utrMask,
    'terminal' => paymentStatusIsTerminal($status),
]);

