<?php
declare(strict_types=1);
// api/payment/history.php — Customer payment history (authenticated)

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

$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = trim($_GET['status'] ?? '');

$where  = ['(pt.user_id = ? OR o.user_id = ?)'];
$params = [$userId, $userId];

if ($status !== '') {
    $where[]  = 'pt.status = ?';
    $params[] = $status;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
         {$whereSQL}"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $pdo->prepare(
        "SELECT pt.payment_id, pt.amount_paise, pt.currency, pt.method,
                pt.status, pt.created_at, pt.verified_at,
                o.order_number
           FROM payment_transactions pt
           JOIN orders o ON o.id = pt.order_id
         {$whereSQL}
         ORDER BY pt.created_at DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format amounts
    foreach ($rows as &$row) {
        $row['amount_inr'] = AmountCalculator::formatINR((int)$row['amount_paise']);
    }
    unset($row);

    echo json_encode([
        'success'      => true,
        'payments'     => $rows,
        'total'        => $total,
        'page'         => $page,
        'limit'        => $limit,
        'total_pages'  => max(1, (int)ceil($total / $limit)),
    ]);
} catch (Throwable $e) {
    error_log('api/payment/history.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load payment history.']);
}
