<?php
declare(strict_types=1);
// api/payment/refund-status.php — Read refund status for a payment

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$paymentId = trim($_GET['pid'] ?? '');
$userId    = (int)$_SESSION['user_id'];

if ($paymentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'pid required.']);
    exit;
}

try {
    // Ownership
    $ownerStmt = $pdo->prepare(
        "SELECT pt.id FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
          WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?)
          LIMIT 1"
    );
    $ownerStmt->execute([$paymentId, $userId, $userId]);
    $ptId = $ownerStmt->fetchColumn();

    if (!$ptId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found or access denied.']);
        exit;
    }

    $refStmt = $pdo->prepare(
        "SELECT id, amount_paise, reason, status, requested_at, approved_at, completed_at
           FROM payment_refunds
          WHERE payment_transaction_id = ?
          ORDER BY requested_at DESC"
    );
    $refStmt->execute([(int)$ptId]);
    $refunds = $refStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($refunds as &$r) {
        $r['amount_inr'] = AmountCalculator::formatINR((int)$r['amount_paise']);
    }
    unset($r);

    echo json_encode([
        'success'    => true,
        'payment_id' => $paymentId,
        'refunds'    => $refunds,
    ]);
} catch (Throwable $e) {
    error_log('api/payment/refund-status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load refund status.']);
}
