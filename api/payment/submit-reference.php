<?php
declare(strict_types=1);
// api/payment/submit-reference.php — API endpoint for customer UTR reference submission

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\SecurityManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST method required.']);
    exit;
}

$sec = new SecurityManager();
$sec->sendCORSHeaders();

// Support JSON input or POST form
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? $_POST;

$csrfToken = trim($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!$sec->validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$paymentId   = trim($body['payment_id'] ?? '');
$customerUtr = strtoupper(trim($body['customer_utr'] ?? ($body['utr'] ?? '')));
$userId      = (int)$_SESSION['user_id'];

if ($paymentId === '' || $customerUtr === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'payment_id and customer_utr are required.']);
    exit;
}

// Format check: 10–22 alphanumeric
if (!preg_match('/^[A-Z0-9]{10,22}$/', $customerUtr)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid UTR format. Must be 10–22 alphanumeric characters.']);
    exit;
}

// Rate limit: 5 submissions per payment per hour
$rlKey = 'api_utr_' . $paymentId . '_' . $userId;
if (!$sec->rateLimitCheck($rlKey, 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many submissions for this payment. Please wait.']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare(
        "SELECT pt.*, o.order_number
           FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
          WHERE pt.payment_id = ?
            AND (pt.user_id = ? OR o.user_id = ?)
          LIMIT 1"
    );
    $stmt->execute([$paymentId, $userId, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment record not found or access denied.']);
        exit;
    }

    $currentStatus = ksrNormalizePaymentStatus($payment['status']);
    if (in_array($currentStatus, ['success', 'cancelled', 'refunded', 'expired'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => "Cannot submit UTR for payment in status '{$currentStatus}'.",
        ]);
        exit;
    }

    // Prevent reusing the same UTR/reference across active orders or transactions
    $dupPt = $pdo->prepare(
        "SELECT id FROM payment_transactions
          WHERE UPPER(customer_utr) = ? AND id != ?
            AND status NOT IN ('failed', 'cancelled', 'expired')
          LIMIT 1"
    );
    $dupPt->execute([$customerUtr, (int)$payment['id']]);
    $dupOrd = $pdo->prepare(
        "SELECT id FROM orders
          WHERE UPPER(transaction_id) = ? AND id != ?
            AND status NOT IN ('cancelled', 'failed')
          LIMIT 1"
    );
    $dupOrd->execute([$customerUtr, (int)$payment['order_id']]);
    if ($dupPt->fetchColumn() || $dupOrd->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => "Reference / UTR '{$customerUtr}' is already submitted for another payment.",
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Update customer_utr on payment_transactions
    $pdo->prepare(
        "UPDATE payment_transactions
            SET customer_utr = ?, updated_at = NOW()
          WHERE id = ?"
    )->execute([$customerUtr, (int)$payment['id']]);

    // Sync order & legacy payments row cleanly when present
    try {
        $pdo->prepare(
            "UPDATE orders
                SET transaction_id = ?, payment_status = 'upi_awaiting'
              WHERE id = ? AND status NOT IN ('delivered', 'cancelled')"
        )->execute([$customerUtr, (int)$payment['order_id']]);
        $pdo->prepare(
            "UPDATE payments
                SET transaction_id = ?, gateway_payment_id = ?, status = 'upi_awaiting'
              WHERE order_id = ?"
        )->execute([$customerUtr, $customerUtr, (int)$payment['order_id']]);
    } catch (Throwable $ignore) {
    }

    // 2. Insert into payment_references
    $pdo->prepare(
        "INSERT INTO payment_references
            (payment_transaction_id, ref_type, ref_value, submitted_by, source)
         VALUES (?, 'customer_utr', ?, ?, 'customer')"
    )->execute([(int)$payment['id'], $customerUtr, $userId]);

    // 3. Transition status to verification_pending (or upi_pending_verification)
    $sm = new PaymentStateMachine($pdo, new AuditLogger($pdo));
    $targetStatus = 'upi_pending_verification';

    if (paymentStatusCanTransition($currentStatus, $targetStatus)) {
        $sm->transition($paymentId, $targetStatus, [
            'actor_type' => 'customer',
            'actor_id'   => $userId,
            'notes'      => "Customer submitted UTR: {$customerUtr}. Awaiting bank verification.",
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'payment_id'   => $paymentId,
        'customer_utr' => $customerUtr,
        'status'       => 'verification_pending',
        'message'      => 'Payment reference submitted for verification.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api/payment/submit-reference.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error while submitting reference.']);
}
