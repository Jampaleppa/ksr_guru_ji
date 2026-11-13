<?php

declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\ReconciliationManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;
use KsrPay\SecurityManager;

header('Content-Type: application/json');

// ── Admin session guard ───────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$adminId       = (int)$_SESSION['user_id'];
$sec           = new SecurityManager();
$csrfToken     = $_POST['csrf_token'] ?? '';

if (!$sec->validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$bankCreditId = (int)($_POST['bank_credit_id'] ?? 0);
$paymentId    = trim($_POST['payment_id'] ?? '');
$notes        = trim($_POST['notes'] ?? '');

if ($bankCreditId < 1 || $paymentId === '') {
    echo json_encode(['success' => false, 'message' => 'bank_credit_id and payment_id are required.']);
    exit;
}

try {
    $recon = new ReconciliationManager($pdo, new AuditLogger($pdo));
    $sm    = new PaymentStateMachine($pdo, new AuditLogger($pdo));

    $pdo->beginTransaction();

    // Match bank credit to payment
    $matched = $recon->matchCredit($bankCreditId, $paymentId, $adminId, $notes);
    if (!$matched) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Matching failed — credit may already be matched or payment not found.']);
        exit;
    }

    // Transition payment to success
    $ok = $sm->transition($paymentId, 'success', [
        'actor_type'          => 'admin',
        'actor_id'            => $adminId,
        'verification_source' => 'admin_manual',
        'verified_by_admin_id' => $adminId,
        'notes'               => "Reconciled with bank credit #{$bankCreditId}. {$notes}",
    ]);

    if (!$ok) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not transition payment to success. Check current status.']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payment verified and marked as success.']);
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('UPI reconcile error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error during reconciliation.']);
}
