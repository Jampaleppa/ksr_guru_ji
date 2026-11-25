<?php
declare(strict_types=1);
// api/payment/cancel.php — Cancel an eligible (non-terminal) payment

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\SecurityManager;
use KsrPay\PaymentManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

// CSRF
$sec = new SecurityManager();
$token = trim($_POST['csrf_token'] ?? '');
if (!$sec->validateCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$paymentId = trim($_POST['payment_id'] ?? '');
$userId    = (int)$_SESSION['user_id'];

if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'payment_id required.']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare(
        "SELECT pt.*, o.user_id AS order_user_id
           FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
          WHERE pt.payment_id = ? LIMIT 1"
    );
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found.']);
        exit;
    }

    // Ownership check
    if ((int)($payment['user_id'] ?? $payment['order_user_id']) !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }

    // Cannot cancel terminal states
    $current = ksrNormalizePaymentStatus($payment['status']);
    if (!paymentStatusCanTransition($current, 'cancelled')) {
        echo json_encode([
            'success' => false,
            'error'   => "Payment in status '{$current}' cannot be cancelled.",
        ]);
        exit;
    }

    $mgr = new PaymentManager($pdo);
    $ok  = $mgr->transition($paymentId, 'cancelled', [
        'actor_type' => 'customer',
        'actor_id'   => $userId,
        'notes'      => 'Customer-requested cancellation via API.',
    ]);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => 'Cancellation not permitted at this stage.']);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'payment_id' => $paymentId,
        'status'     => 'cancelled',
        'message'    => 'Payment cancelled successfully.',
    ]);
} catch (Throwable $e) {
    error_log('payment/cancel.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to process cancellation.']);
}
