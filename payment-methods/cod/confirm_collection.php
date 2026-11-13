<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;
use KsrPay\SecurityManager;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Role check: admin OR delivery_boy
$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'delivery', 'delivery_boy'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$sec = new SecurityManager();
if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$paymentId            = trim($_POST['payment_id'] ?? '');
$collectedAmountPaise = (int)($_POST['collected_amount_paise'] ?? 0);
$collectionReference  = trim($_POST['collection_reference'] ?? '');
$actorId              = (int)$_SESSION['user_id'];

if ($paymentId === '' || $collectedAmountPaise < 1) {
    echo json_encode(['success' => false, 'message' => 'payment_id and collected_amount_paise are required.']);
    exit;
}

// Verify payment exists and is COD
$stmt = $pdo->prepare(
    "SELECT id, order_id, amount_paise, status FROM payment_transactions
     WHERE payment_id = ? AND method = 'cod' LIMIT 1"
);
$stmt->execute([$paymentId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'COD payment not found.']);
    exit;
}

if ($collectedAmountPaise !== (int)$payment['amount_paise']) {
    echo json_encode([
        'success' => false,
        'message' => 'Collected amount does not match order amount. Expected: ' . $payment['amount_paise'] . ' paise.',
    ]);
    exit;
}

try {
    $sm = new PaymentStateMachine($pdo, new AuditLogger($pdo));
    $ok = $sm->transition($paymentId, 'cod_collected', [
        'actor_type'          => in_array($role, ['admin'], true) ? 'admin' : 'system',
        'actor_id'            => $actorId,
        'collected_amount'    => $collectedAmountPaise,
        'collected_by'        => $actorId,
        'collection_reference'=> $collectionReference,
        'notes'               => "COD collected. Ref: {$collectionReference}",
    ]);

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Could not confirm collection. Payment may already be in a terminal state.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Cash collection confirmed successfully.']);
} catch (\Throwable $e) {
    error_log('COD confirm_collection error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}

