<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\SecurityManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?: $_POST) : $_POST;

$userId = (int)$_SESSION['user_id'];
$sec    = new SecurityManager();

// CSRF check
$csrfToken = trim((string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!SecurityManager::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please reload the page.']);
    exit;
}

$paymentId = trim((string)($body['payment_id'] ?? ''));
$orderId   = (int)($body['order_id'] ?? 0);
$utr       = strtoupper(trim((string)($body['utr'] ?? ($body['customer_utr'] ?? ''))));

if ($paymentId === '' || $orderId < 1 || $utr === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Bank reference: 6–30 alphanumeric (plus - / for NEFT/IMPS bank refs)
if (!preg_match('/^[A-Z0-9\-\/]{6,30}$/', $utr)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reference number. Must be 6–30 alphanumeric characters.']);
    exit;
}

// Rate limit: 5 submissions per hour per payment
if (!$sec->rateLimitCheck('bt_submit_' . $paymentId . '_' . $userId, 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please wait and try again.']);
    exit;
}

// Verify ownership & bank_transfer method
$ptStmt = $pdo->prepare(
    "SELECT pt.id, pt.status, pt.order_id, pt.amount_paise
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND pt.order_id = ?
       AND (pt.user_id = ? OR o.user_id = ?)
       AND pt.method = 'bank_transfer'
     LIMIT 1"
);
$ptStmt->execute([$paymentId, $orderId, $userId, $userId]);
$payment = $ptStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Bank transfer payment not found.']);
    exit;
}

// Duplicate reference check across active transactions, references, and orders
$dupTxStmt = $pdo->prepare(
    "SELECT payment_id FROM payment_transactions
     WHERE UPPER(customer_utr) = ? AND payment_id != ?
       AND status NOT IN ('failed', 'cancelled', 'expired')
     LIMIT 1"
);
$dupTxStmt->execute([$utr, $paymentId]);
if ($dupTxStmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'This reference number was already submitted for another payment.']);
    exit;
}

$dupRefStmt = $pdo->prepare(
    "SELECT pr.payment_transaction_id
     FROM payment_references pr
     JOIN payment_transactions pt ON pt.id = pr.payment_transaction_id
     WHERE UPPER(pr.ref_value) = ? AND pr.payment_transaction_id != ?
       AND pt.status NOT IN ('failed', 'cancelled', 'expired')
     LIMIT 1"
);
$dupRefStmt->execute([$utr, (int)$payment['id']]);
if ($dupRefStmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'This bank reference number was already submitted for another payment.']);
    exit;
}

// Check transition is possible
$currentStatus = ksrNormalizePaymentStatus($payment['status']);
if (!paymentStatusCanTransition($currentStatus, 'upi_awaiting')) {
    echo json_encode([
        'success' => false,
        'message' => 'This payment is already ' . ksrPaymentStatusMessage($currentStatus),
    ]);
    exit;
}

try {
    $nowSql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') ? "datetime('now')" : 'NOW()';
    $pdo->beginTransaction();

    // Store unverified reference in payment_transactions
    $pdo->prepare(
        "UPDATE payment_transactions SET customer_utr = ?, updated_at = {$nowSql} WHERE payment_id = ?"
    )->execute([$utr, $paymentId]);

    // Sync order & legacy payments row cleanly when present
    try {
        $pdo->prepare(
            "UPDATE orders
             SET transaction_id = ?, payment_status = 'upi_awaiting'
             WHERE id = ? AND status NOT IN ('delivered', 'cancelled')"
        )->execute([$utr, $orderId]);
        $pdo->prepare(
            "UPDATE payments
             SET transaction_id = ?, status = 'upi_awaiting'
             WHERE order_id = ?"
        )->execute([$utr, $orderId]);
    } catch (\Throwable $ignore) {
    }

    // Log reference in payment_references
    $pdo->prepare(
        "INSERT INTO payment_references (payment_transaction_id, ref_type, ref_value, submitted_by, source)
         VALUES (?, 'bank_ref', ?, ?, 'customer')"
    )->execute([(int)$payment['id'], $utr, $userId]);

    // Transition to awaiting verification (reuses awaiting admin verification state)
    $sm = new PaymentStateMachine($pdo, new AuditLogger($pdo));
    $ok = $sm->transition($paymentId, 'upi_awaiting', [
        'actor_type' => 'customer',
        'actor_id'   => $userId,
        'notes'      => "Bank transfer reference submitted by customer: {$utr}",
    ]);

    if (!$ok) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Could not update payment status. Please contact support.']);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'message'  => 'Bank transfer reference submitted! Admin will verify within 1–3 business hours.',
        'redirect' => '/payment/payment_pending.php?pid=' . urlencode($paymentId),
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Bank transfer submit_reference error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
