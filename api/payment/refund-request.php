<?php
declare(strict_types=1);
// api/payment/refund-request.php — API endpoint for customer refund requests

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\SecurityManager;
use KsrPay\RefundManager;
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

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? $_POST;

$csrfToken = trim($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!$sec->validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$paymentId   = trim($body['payment_id'] ?? '');
$reason      = trim($body['reason'] ?? '');
$amountPaise = isset($body['amount_paise']) ? (int)$body['amount_paise'] : null;
$userId      = (int)$_SESSION['user_id'];

if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'payment_id is required.']);
    exit;
}

if (mb_strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a clear refund reason (at least 10 characters).']);
    exit;
}

try {
    // Verify ownership and successful status
    $stmt = $pdo->prepare(
        "SELECT pt.* FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
          WHERE pt.payment_id = ?
            AND (pt.user_id = ? OR o.user_id = ?)
          LIMIT 1"
    );
    $stmt->execute([$paymentId, $userId, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found or access denied.']);
        exit;
    }

    if ($payment['status'] !== 'success') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only fully settled, successful payments are eligible for refunds.']);
        exit;
    }

    $refundAmount = $amountPaise ?? (int)$payment['amount_paise'];

    $refundMgr = new RefundManager($pdo, new AuditLogger($pdo));
    $refundId  = $refundMgr->requestRefund($paymentId, $userId, $refundAmount, $reason);

    echo json_encode([
        'success'             => true,
        'refund_id'           => $refundId,
        'payment_id'          => $paymentId,
        'refund_amount_paise' => $refundAmount,
        'status'              => 'pending',
        'message'             => 'Refund request submitted successfully. It will be reviewed by our finance team.',
    ]);
} catch (InvalidArgumentException | RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/payment/refund-request.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to submit refund request. Please try again.']);
}
