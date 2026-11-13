<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\ReconciliationManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;
use KsrPay\SecurityManager;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required.']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']); exit;
}

$sec = new SecurityManager();
if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']); exit;
}

$paymentId    = trim($_POST['payment_id']     ?? '');
$bankCreditId = (int)($_POST['bank_credit_id'] ?? 0);
$notes        = trim($_POST['notes']           ?? '');
$adminId      = (int)$_SESSION['user_id'];

if ($paymentId === '' || $bankCreditId < 1) {
    echo json_encode(['success' => false, 'error' => 'payment_id and bank_credit_id required.']); exit;
}

try {
    $recon = new ReconciliationManager($pdo, new AuditLogger($pdo));
    $sm    = new PaymentStateMachine($pdo, new AuditLogger($pdo));

    $pdo->beginTransaction();

    $matched = $recon->matchCredit($bankCreditId, $paymentId, $adminId, $notes);
    if (!$matched) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Matching failed — credit may already be matched.']); exit;
    }

    $ok = $sm->transition($paymentId, 'success', [
        'actor_type'           => 'admin',
        'actor_id'             => $adminId,
        'verification_source'  => 'admin_manual',
        'verified_by_admin_id' => $adminId,
        'notes'                => "Verified via API. Bank credit #{$bankCreditId}. {$notes}",
    ]);

    if (!$ok) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'State transition failed.']); exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payment verified and marked as success.']);
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('api/payment/verify error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}

