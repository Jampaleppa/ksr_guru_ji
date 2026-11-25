<?php
declare(strict_types=1);
// api/payment/support.php — Create a payment support case

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\SecurityManager;

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

$sec = new SecurityManager();
if (!$sec->validateCSRFToken(trim($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$orderId   = (int)($_POST['order_id']   ?? 0);
$caseType  = trim($_POST['case_type']   ?? 'other');
$subject   = trim($_POST['subject']     ?? '');
$description = trim($_POST['description'] ?? '');
$paymentId = trim($_POST['payment_id']  ?? '');

// Validate
$errors = [];
if ($orderId < 1)   $errors[] = 'Order reference required.';
if ($subject === '') $errors[] = 'Subject is required.';
if (mb_strlen($subject) > 200) $errors[] = 'Subject too long (max 200 chars).';

$allowedTypes = [
    'payment_pending_debited','double_payment','wrong_amount','wrong_merchant',
    'refund_delayed','order_cancelled_after_payment','receipt_missing',
    'payment_reversed','other'
];
if (!in_array($caseType, $allowedTypes, true)) {
    $caseType = 'other';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Verify order belongs to user
    $ordStmt = $pdo->prepare(
        "SELECT id FROM orders WHERE id = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1"
    );
    $ordStmt->execute([$orderId, $userId]);
    if (!$ordStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Order not found or access denied.']);
        exit;
    }

    // Resolve payment_transaction_id if payment_id supplied
    $ptId = null;
    if ($paymentId !== '') {
        $ptStmt = $pdo->prepare(
            "SELECT id FROM payment_transactions
              WHERE payment_id = ? AND order_id = ? LIMIT 1"
        );
        $ptStmt->execute([$paymentId, $orderId]);
        $ptId = $ptStmt->fetchColumn() ?: null;
    }

    // Generate unique case UID
    $caseUid = 'KSR-CASE-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->prepare(
        "INSERT INTO payment_support_cases
            (case_uid, payment_transaction_id, order_id, user_id, case_type, subject, description)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$caseUid, $ptId, $orderId, $userId, $caseType, $subject, $description]);

    echo json_encode([
        'success'  => true,
        'case_uid' => $caseUid,
        'message'  => 'Support case created. We will respond within 1 business day.',
    ]);
} catch (Throwable $e) {
    error_log('api/payment/support.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to create support case.']);
}
