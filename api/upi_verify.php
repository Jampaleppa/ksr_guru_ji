<?php
// ============================================================
// api/upi_verify.php - UPI Direct Payment Verification & UTR Submission
// ============================================================
//
// Dual-purpose endpoint:
// 1. Customer: submit UPI UTR (12-digit transaction reference number)
// 2. Admin: verify/confirm manual UPI payment or reject fraudulent submissions
// 3. Status query: GET order UPI payment verification state
// ============================================================

declare(strict_types=1);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

if (file_exists(__DIR__ . '/payment_state.php')) {
    require_once __DIR__ . '/payment_state.php';
} elseif (file_exists(__DIR__ . '/../payment_state.php')) {
    require_once __DIR__ . '/../payment_state.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function getUpiRequestInput(): array
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

$input = getUpiRequestInput();
$action = trim((string)($input['action'] ?? ($_GET['action'] ?? '')));

$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole   = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');
$isAdmin       = in_array($currentRole, ['admin', 'super_admin'], true);

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
//  GET: Retrieve UPI Verification Status for an Order
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'status') {
    $orderId = (int)($_GET['order_id'] ?? ($input['order_id'] ?? 0));

    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.user_id, o.total, o.payment_method, o.payment_status, o.transaction_id,
                   p.id AS payment_id, p.status AS payment_record_status, p.gateway_payment_id, p.paid_at
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Order not found.']);
            exit;
        }

        $guestOrders = $_SESSION['ksr_guest_orders'] ?? [];
        $isGuestOwner = is_array($guestOrders) && in_array($orderId, array_map('intval', $guestOrders), true);
        $isOwner = ($currentUserId !== null && (int)$order['user_id'] === (int)$currentUserId);

        if (!$isAdmin && !$isOwner && !$isGuestOwner) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied.']);
            exit;
        }

        $normStatus = ksrNormalizePaymentStatus($order['payment_record_status'] ?: $order['payment_status']);

        echo json_encode([
            'success'        => true,
            'order_id'       => (int)$order['id'],
            'order_number'   => $order['order_number'],
            'total'          => (float)$order['total'],
            'payment_method' => $order['payment_method'],
            'status'         => $normStatus,
            'transaction_id' => $order['transaction_id'] ?? '',
            'message'        => ksrPaymentStatusMessage($normStatus)
        ]);
        exit;
    } catch (Throwable $e) {
        error_log('upi_verify GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
//  POST: Customer submits UTR or Admin approves/rejects
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── ACTION 1: Customer submits UTR ────────────────────────
if ($action === 'submit_utr') {
    $orderId = (int)($input['order_id'] ?? 0);
    $utr     = trim((string)($input['utr'] ?? ''));
    $appUsed = trim((string)($input['upi_app'] ?? 'UPI'));

    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid order ID required.']);
        exit;
    }

    // Sanitize and validate UTR
    $utrClean = preg_replace('/[^A-Za-z0-9]/', '', $utr);
    if (strlen($utrClean) < 8 || strlen($utrClean) > 24) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter a valid 12-digit UPI UTR / Transaction Reference number.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, order_number, user_id, total, status, payment_status, payment_method FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Order not found.']);
            exit;
        }

        $guestOrders = $_SESSION['ksr_guest_orders'] ?? [];
        $isGuestOwner = is_array($guestOrders) && in_array($orderId, array_map('intval', $guestOrders), true);
        $isOwner = ($currentUserId !== null && (int)$order['user_id'] === (int)$currentUserId);

        if (!$isAdmin && !$isOwner && !$isGuestOwner) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied.']);
            exit;
        }

        $payment = paymentRowForOrder($pdo, $orderId);
        if (!$payment) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Payment record not found.']);
            exit;
        }

        $paymentId = (int)$payment['id'];
        $currentPaymentStatus = ksrNormalizePaymentStatus($payment['status']);

        if (paymentStatusIsSettled($currentPaymentStatus)) {
            $pdo->rollBack();
            echo json_encode([
                'success' => true,
                'message' => 'Payment already verified.',
                'status'  => $currentPaymentStatus
            ]);
            exit;
        }

        // Transition to upi_awaiting
        $updated = applyPaymentStatus($pdo, $paymentId, 'upi_awaiting', [
            'event_type'         => 'UPI_UTR_SUBMITTED',
            'gateway_payment_id' => $utrClean,
            'transaction_id'     => $utrClean,
            'source'             => 'customer_checkout',
            'payload'            => [
                'utr'     => $utrClean,
                'upi_app' => $appUsed,
                'order'   => $order['order_number']
            ]
        ]);

        // Update orders transaction_id and timeline
        $ordStmt = $pdo->prepare("UPDATE orders SET transaction_id = ?, payment_status = 'upi_awaiting' WHERE id = ?");
        $ordStmt->execute([$utrClean, $orderId]);

        $tlStmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'pending', ?, ?)");
        $tlStmt->execute([
            $orderId,
            "Customer submitted UPI Ref/UTR: {$utrClean} ({$appUsed}). Awaiting merchant bank confirmation.",
            $currentUserId
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'UPI Reference submitted successfully. Our team will verify and dispatch within 45 minutes.',
            'status'  => 'upi_awaiting',
            'utr'     => $utrClean
        ]);
        exit;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('submit_utr error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save UPI reference: ' . $e->getMessage()]);
        exit;
    }
}

// ── ACTION 2: Admin verifies/approves UPI payment ─────────
if ($action === 'admin_verify') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin authorization required.']);
        exit;
    }

    $orderId = (int)($input['order_id'] ?? 0);
    $status  = strtolower(trim((string)($input['status'] ?? 'success'))); // 'success' or 'failed'
    $utr     = trim((string)($input['utr'] ?? ''));
    $notes   = trim((string)($input['notes'] ?? ''));

    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, order_number, user_id, total, status, payment_status FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Order not found.']);
            exit;
        }

        $payment = paymentRowForOrder($pdo, $orderId);
        if (!$payment) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Payment record not found.']);
            exit;
        }

        $paymentId = (int)$payment['id'];
        $adminName = $_SESSION['admin_name'] ?? ($_SESSION['user_name'] ?? ('Admin #' . $currentUserId));

        if ($status === 'success') {
            // Move through processing to success
            applyPaymentStatus($pdo, $paymentId, 'processing', [
                'event_type' => 'ADMIN_UPI_VERIFYING',
                'source'     => 'admin_manual',
            ]);

            applyPaymentStatus($pdo, $paymentId, 'success', [
                'event_type'          => 'ADMIN_UPI_CONFIRMED',
                'gateway_payment_id'  => $utr ?: ($payment['transaction_id'] ?? 'UPI' . date('YmdHis')),
                'transaction_id'      => $utr ?: ($payment['transaction_id'] ?? 'UPI' . date('YmdHis')),
                'source'              => 'admin_manual',
                'gateway_response'    => [
                    'verified_by' => $adminName,
                    'verified_at' => date('Y-m-d H:i:s'),
                    'utr'         => $utr,
                    'notes'       => $notes
                ]
            ]);

            // Mark order confirmed / processing
            $upd = $pdo->prepare("UPDATE orders SET status = 'processing', payment_status = 'paid' WHERE id = ?");
            $upd->execute([$orderId]);

            $tlMsg = "UPI Payment of ₹" . number_format((float)$order['total'], 2) . " verified by {$adminName}." . ($utr ? " UTR: {$utr}." : "") . ($notes ? " Notes: {$notes}" : "");
            $tl = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'processing', ?, ?)");
            $tl->execute([$orderId, $tlMsg, $currentUserId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'UPI payment verified and order marked as paid.',
                'status'  => 'success'
            ]);
            exit;
        } else {
            // Reject payment
            applyPaymentStatus($pdo, $paymentId, 'failed', [
                'event_type'     => 'ADMIN_UPI_REJECTED',
                'failure_code'   => 'UPI_VERIFICATION_REJECTED',
                'failure_reason' => $notes ?: 'Payment rejected by merchant.',
                'source'         => 'admin_manual'
            ]);

            $upd = $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
            $upd->execute([$orderId]);

            $tlMsg = "UPI Payment rejected by {$adminName}. Reason: " . ($notes ?: 'Bank statement verification mismatch.');
            $tl = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'failed', ?, ?)");
            $tl->execute([$orderId, $tlMsg, $currentUserId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'UPI payment marked as failed.',
                'status'  => 'failed'
            ]);
            exit;
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin_verify error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Verification failed: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action.']);
exit;

