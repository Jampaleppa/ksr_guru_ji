<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\ReconciliationManager;
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

$bankCreditId = (int)($_POST['bank_credit_id'] ?? 0);
$paymentId    = trim($_POST['payment_id']       ?? '');
$notes        = trim($_POST['notes']            ?? '');
$adminId      = (int)$_SESSION['user_id'];

if ($bankCreditId < 1 || $paymentId === '') {
    echo json_encode(['success' => false, 'error' => 'bank_credit_id and payment_id required.']); exit;
}

try {
    $recon   = new ReconciliationManager($pdo, new AuditLogger($pdo));
    $matched = $recon->matchCredit($bankCreditId, $paymentId, $adminId, $notes);

    echo json_encode([
        'success' => $matched,
        'message' => $matched ? 'Credit matched to payment. Use verify endpoint to confirm payment.' : 'Matching failed.',
    ]);
} catch (\Throwable $e) {
    error_log('api/payment/reconcile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}

