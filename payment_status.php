<?php
// ============================================================
// api/payment_status.php - Real-time Payment Status Endpoint
// ============================================================
//
// GET /api/payment_status.php?order_id=123
//
// Returns the current payment + order lifecycle state so the
// checkout screen (and the /payment/status.php page) can poll
// for confirmation instead of trusting the browser callback.
//
// Access: the logged-in owner of the order, or an admin.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/payment_state.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $orderId = (int)($_GET['order_id'] ?? 0);

    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
        exit;
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.total,
            o.status            AS order_status,
            o.payment_status,
            o.payment_method,
            o.transaction_id,

            p.id                AS payment_id,
            p.method,
            p.gateway,
            p.gateway_order_id,
            p.gateway_payment_id,
            p.status            AS payment_record_status,
            p.failure_code,
            p.failure_reason,
            p.paid_at,
            p.confirmed_at,
            p.failed_at,
            p.cancelled_at,
            p.expired_at,
            p.refund_status,
            p.refund_transaction_id,
            p.refunded_at

        FROM orders o

        LEFT JOIN payments p
            ON p.order_id = o.id

        WHERE o.id = ?

        LIMIT 1
    ");

    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    /*
     * Security:
     * Logged-in users can only inspect their own order.
     * Admins can inspect any order.
     * Guests may inspect an order they just placed in this session.
     */
    $userId = $_SESSION['user_id'] ?? null;
    $role   = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');

    $isAdmin = in_array($role, ['admin', 'super_admin'], true);

    $sessionOrderIds = $_SESSION['ksr_guest_orders'] ?? [];
    $isGuestOwner    = is_array($sessionOrderIds)
        && in_array($orderId, array_map('intval', $sessionOrderIds), true);

    $isOwner = ($userId !== null && (int)$row['user_id'] === (int)$userId);

    if (!$isAdmin && !$isOwner && !$isGuestOwner) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }

    // Prefer the payments record; fall back to the order-level column.
    $paymentStatus = ksrNormalizePaymentStatus(
        $row['payment_record_status'] ?: $row['payment_status']
    );

    $isTerminal = paymentStatusIsTerminal($paymentStatus);

    echo json_encode([
        'success' => true,

        'order' => [
            'id'     => (int)$row['id'],
            'number' => $row['order_number'],
            'amount' => (float)$row['total'],
            'status' => $row['order_status'],
        ],

        'payment' => [
            'id'                => $row['payment_id'] ? (int)$row['payment_id'] : null,
            'status'            => $paymentStatus,
            'method'            => $row['method'] ?: $row['payment_method'],
            'gateway'           => $row['gateway'],
            'gateway_order_id'  => $row['gateway_order_id'],
            'gateway_payment_id' => $row['gateway_payment_id'],
            'transaction_id'    => $row['transaction_id'],
            'failure_code'      => $row['failure_code'],
            'failure_reason'    => $row['failure_reason'],
            'paid_at'           => $row['paid_at'],
            'confirmed_at'      => $row['confirmed_at'],
            'failed_at'         => $row['failed_at'],
            'cancelled_at'      => $row['cancelled_at'],
            'expired_at'        => $row['expired_at'],
            'refund_status'     => $row['refund_status'],
            'refund_transaction_id' => $row['refund_transaction_id'],
            'refunded_at'       => $row['refunded_at'],
        ],

        'terminal' => $isTerminal,

        'message' => ksrPaymentStatusMessage($paymentStatus),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('payment_status.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Unable to retrieve payment status.',
    ]);
}
