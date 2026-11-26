<?php
declare(strict_types=1);
// api/payment/settlement.php — Settlement records (admin only)

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

SecurityManager::requireAdminApi();

$from  = trim($_GET['from']  ?? date('Y-m-01'));
$to    = trim($_GET['to']    ?? date('Y-m-d'));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM payment_settlement_reports
          WHERE report_date BETWEEN ? AND ?"
    );
    $countStmt->execute([$from, $to]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT psr.*, u.name AS generated_by_name
           FROM payment_settlement_reports psr
           LEFT JOIN users u ON u.id = psr.generated_by
          WHERE psr.report_date BETWEEN ? AND ?
          ORDER BY psr.report_date DESC
          LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['total_collected_inr'] = AmountCalculator::formatINR((int)$row['total_collected_paise']);
        $row['total_refunded_inr']  = AmountCalculator::formatINR((int)$row['total_refunded_paise']);
        $row['net_inr']             = AmountCalculator::formatINR((int)$row['net_paise']);
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'reports' => $rows,
        'total'   => $total,
        'page'    => $page,
        'limit'   => $limit,
    ]);
} catch (Throwable $e) {
    error_log('api/payment/settlement.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load settlement reports.']);
}
