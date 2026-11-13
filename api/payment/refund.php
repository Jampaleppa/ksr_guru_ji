<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\RefundManager;
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

$refundId         = (int)($_POST['refund_id']          ?? 0);
$action           = trim($_POST['action']              ?? '');
$providerRefundId = trim($_POST['provider_refund_id']  ?? '');
$bankRef          = trim($_POST['bank_ref']            ?? '');
$adminId          = (int)$_SESSION['user_id'];

if ($refundId < 1 || !in_array($action, ['approve', 'complete'], true)) {
    echo json_encode(['success' => false, 'error' => 'refund_id and action (approve|complete) required.']); exit;
}

try {
    $refund = new RefundManager($pdo, new AuditLogger($pdo));

    if ($action === 'approve') {
        $ok = $refund->approveRefund($refundId, $adminId);
    } else {
        $ok = $refund->completeRefund($refundId, $adminId, $providerRefundId ?: null, $bankRef ?: null);
    }

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? "Refund {$action}d successfully." : "Could not {$action} refund. Check status.",
    ]);
} catch (\Throwable $e) {
    error_log('api/payment/refund error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}

