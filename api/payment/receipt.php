<?php
declare(strict_types=1);
// api/payment/receipt.php — Serve a customer receipt for a confirmed payment

header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';
require_once dirname(__DIR__, 2) . '/payment-core/ReceiptGenerator.php';

use KsrPay\ReceiptGenerator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$paymentId = trim($_GET['pid'] ?? '');
$userId    = (int)$_SESSION['user_id'];

if ($paymentId === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'pid parameter required.']);
    exit;
}

$gen  = new ReceiptGenerator($pdo);
$html = $gen->generateHtml($paymentId, $userId);

if ($html === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Receipt not found or payment not yet confirmed.']);
    exit;
}

$receiptNo = 'RCP-' . strtoupper(substr($paymentId, 4, 12));
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="Receipt-' . $receiptNo . '.html"');
echo $html;
