<?php
declare(strict_types=1);
// api/payment/attempts.php — Payment attempt history for a given payment_id

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

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
    // Verify ownership
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

    $attStmt = $pdo->prepare(
        "SELECT attempt_number, status, failure_reason, attempted_at
           FROM payment_attempts
          WHERE payment_transaction_id = ?
          ORDER BY attempt_number ASC"
    );
    $attStmt->execute([(int)$ptId]);
    $attempts = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'payment_id' => $paymentId,
        'attempts'   => $attempts,
    ]);
} catch (Throwable $e) {
    error_log('api/payment/attempts.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load attempt history.']);
}
