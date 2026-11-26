<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AuditLogger;
use KsrPay\PaymentStateMachine;
use KsrPay\SecurityManager;

header('Content-Type: application/json');
header('Cache-Control: no-store');

SecurityManager::requireAdminApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$sec   = new SecurityManager();
$token = trim($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!$sec->validateCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$paymentId = trim($_POST['payment_id'] ?? '');
$reason    = trim($_POST['reason'] ?? 'Marked failed by admin');
$adminId   = (int)$_SESSION['user_id'];

if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'payment_id is required.']);
    exit;
}

if (mb_strlen($reason) > 250) {
    $reason = mb_substr($reason, 0, 250);
}

try {
    $sm = new PaymentStateMachine($pdo, new AuditLogger($pdo));

    $pdo->beginTransaction();

    $ok = $sm->transition($paymentId, 'failed', [
        'actor_type'     => 'admin',
        'actor_id'       => $adminId,
        'failure_reason' => $reason,
        'notes'          => "Marked failed via admin API. {$reason}",
    ]);

    if (!$ok) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'State transition to failed is not allowed for this payment.']);
        exit;
    }

    $pdo->commit();
    echo json_encode([
        'success'    => true,
        'payment_id' => $paymentId,
        'status'     => 'failed',
        'message'    => 'Payment marked as failed.',
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api/payment/mark-failed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}
