<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\SecurityManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;

header('Content-Type: application/json');

// ── Auth ─────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

// ── POST only ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$sec    = new SecurityManager();

// ── CSRF ─────────────────────────────────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$sec->validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please reload the page.']);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────────
$paymentId = trim($_POST['payment_id'] ?? '');
$orderId   = (int)($_POST['order_id'] ?? 0);
$utr       = strtoupper(trim($_POST['utr'] ?? ''));

if ($paymentId === '' || $orderId < 1 || $utr === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate UTR format: 10–22 alphanumeric
if (!preg_match('/^[A-Z0-9]{10,22}$/', $utr)) {
    echo json_encode(['success' => false, 'message' => 'Invalid UTR format. Must be 10–22 alphanumeric characters.']);
    exit;
}

// ── Rate limit: max 3 UTR submissions per payment per hour ───────────────────
$rlKey = 'utr_submit_' . $paymentId . '_' . $userId;
if (!$sec->rateLimitCheck($rlKey, 3, 3600)) {
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please wait and try again.']);
    exit;
}

// ── Load payment row (verify ownership) ──────────────────────────────────────
$ptStmt = $pdo->prepare(
    "SELECT pt.id, pt.status, pt.order_id, pt.amount_paise
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND pt.order_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     LIMIT 1"
);
$ptStmt->execute([$paymentId, $orderId, $userId, $userId]);
$payment = $ptStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Payment not found.']);
    exit;
}

// ── Duplicate UTR check: reject if same UTR used for a DIFFERENT payment ─────
$dupStmt = $pdo->prepare(
    "SELECT payment_id FROM payment_transactions
     WHERE customer_utr = ? AND payment_id != ?
     LIMIT 1"
);
$dupStmt->execute([$utr, $paymentId]);
$dup = $dupStmt->fetchColumn();

if ($dup) {
    echo json_encode([
        'success' => false,
        'message' => 'This UTR has already been submitted for another order. If this is a mistake, contact support.',
    ]);
    exit;
}

// ── Check status is transitionable ───────────────────────────────────────────
$currentStatus = ksrNormalizePaymentStatus($payment['status']);
if (!paymentStatusCanTransition($currentStatus, 'upi_awaiting')) {
    echo json_encode([
        'success' => false,
        'message' => 'This payment is already ' . ksrPaymentStatusMessage($currentStatus),
    ]);
    exit;
}

// ── Update payment_transactions ───────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Store the unverified UTR
    $pdo->prepare(
        "UPDATE payment_transactions SET customer_utr = ?, updated_at = NOW() WHERE payment_id = ?"
    )->execute([$utr, $paymentId]);

    // Insert into payment_references
    $pdo->prepare(
        "INSERT INTO payment_references (payment_transaction_id, ref_type, ref_value, submitted_by, source)
         VALUES (?, 'customer_utr', ?, ?, 'customer')"
    )->execute([(int)$payment['id'], $utr, $userId]);

    // Transition status
    $stateMachine = new PaymentStateMachine($pdo, new AuditLogger($pdo));
    $transitioned = $stateMachine->transition($paymentId, 'upi_awaiting', [
        'actor_type' => 'customer',
        'actor_id'   => $userId,
        'notes'      => "Customer submitted UTR: {$utr}",
    ]);

    if (!$transitioned) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not update payment status. Please contact support.']);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'message'  => 'UTR submitted successfully! We will verify your payment within 1–2 business hours.',
        'redirect' => '/payment/payment_pending.php?pid=' . urlencode($paymentId),
    ]);

} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('UPI submit_reference error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}

