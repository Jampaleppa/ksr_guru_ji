<?php
// ============================================================
// api/cod_collect.php - COD Cash Collection Endpoint
// ============================================================
//
// Used by delivery personnel / admin to mark cash as collected.
//
// POST (JSON or form):
//   order_id             required
//   collected_amount     required (must equal the order total)
//   collection_reference optional (receipt no.)
//   collected_by         optional (defaults to session admin)
//   refused              optional ('1' => customer refused cash)
//
// Security: admin/super_admin or the delivery person assigned to the order.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_state.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/** Accept either JSON body or form-encoded POST. */
function codInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$input = codInput();

$orderId   = (int)($input['order_id'] ?? 0);
$amount    = isset($input['collected_amount']) ? (float)$input['collected_amount'] : null;
$reference = trim((string)($input['collection_reference'] ?? ''));
$refused   = !empty($input['refused']);

$role   = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');
$userId = $_SESSION['user_id'] ?? null;

$isAdmin    = in_array($role, ['admin', 'super_admin'], true);
$isDelivery = in_array($role, ['delivery', 'delivery_boy', 'driver'], true);

if (!$isAdmin && !$isDelivery) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, order_number, total, status, payment_method, driver_id, delivery_boy_id FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    $deliveryId = (int)($_SESSION['delivery_boy_id'] ?? $userId ?? 0);
    $isAssignedDelivery = $deliveryId > 0
        && ((int)($order['driver_id'] ?? 0) === $deliveryId
            || (int)($order['delivery_boy_id'] ?? 0) === $deliveryId);

    if (!$isAdmin && !$isAssignedDelivery) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }

    if (strtolower((string)$order['payment_method']) !== 'cod') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order is not a COD order.']);
        exit;
    }

    $payment = paymentRowForOrder($pdo, $orderId);

    if (!$payment) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No payment record for this order.']);
        exit;
    }

    $paymentId     = (int)$payment['id'];
    $currentStatus = ksrNormalizePaymentStatus($payment['status']);

    // --- Customer refused cash ------------------------------------------
    if ($refused) {
        $ok = applyPaymentStatus($pdo, $paymentId, 'cancelled', [
            'event_type'         => 'COD_REFUSED',
            'failure_reason'     => 'Customer refused to pay cash on delivery.',
            'source'             => 'delivery',
            'payload'            => ['order_id' => $orderId],
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'COD marked as refused / cancelled.' : 'Unable to update status.',
            'status'  => 'cancelled',
        ]);
        exit;
    }

    // --- Validate the collected amount ----------------------------------
    $expected = (float)$order['total'];

    if ($amount === null || abs($amount - $expected) > 1.00) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success'  => false,
            'error'    => 'Collected amount does not match order total.',
            'expected' => $expected,
        ]);
        exit;
    }

    if (in_array($currentStatus, ['cod_collected', 'success'], true)) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Cash already collected for this order.',
            'status'  => $currentStatus,
        ]);
        exit;
    }

    $collectedBy = trim((string)($input['collected_by'] ?? ''));
    if ($collectedBy === '') {
        $collectedBy = $isAdmin
            ? (string)($_SESSION['admin_name'] ?? ('Admin #' . $userId))
            : (string)($_SESSION['user_name'] ?? ('Delivery #' . $userId));
    }

    // cod_pending -> cod_collected
    $ok = applyPaymentStatus($pdo, $paymentId, 'cod_collected', [
        'event_type'            => 'COD_COLLECTED',
        'collected_amount'      => $expected,
        'collected_by'          => $collectedBy,
        'collection_reference'  => $reference ?: ('COD' . date('YmdHis')),
        'source'                => 'delivery',
        'payload'               => [
            'order_id' => $orderId,
            'amount'   => $expected,
        ],
    ]);

    if (!$ok) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Illegal COD transition.']);
        exit;
    }

    // cod_collected -> success (settled). The audit trail keeps both rows.
    applyPaymentStatus($pdo, $paymentId, 'success', [
        'event_type' => 'COD_SETTLED',
        'source'     => 'delivery',
    ]);

    $tStmt = $pdo->prepare("
        INSERT INTO order_timeline (order_id, status, notes, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $tStmt->execute([
        $orderId,
        $order['status'],
        'Cash collected on delivery: ₹' . number_format($expected, 2) . ' by ' . $collectedBy,
        $userId,
    ]);

    $pdo->commit();

    echo json_encode([
        'success'            => true,
        'message'            => 'COD cash collected successfully.',
        'status'             => 'success',
        'collected_amount'   => $expected,
        'collected_by'       => $collectedBy,
        'collection_reference' => $reference,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cod_collect.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to record COD collection.']);
}
