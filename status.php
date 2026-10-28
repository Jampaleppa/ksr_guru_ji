<?php
// ============================================================
// payment/status.php - Customer Payment Status Page
// ============================================================
//
// /payment/status.php?order=KSR-2026-0001
//
// Shows the live payment state, the full payment timeline built from
// payment_events, and order actions. Access is limited to the order
// owner / admin / the guest who placed it in this session.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_state.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderRef = trim((string)($_GET['order'] ?? $_GET['order_id'] ?? ''));

$pdo = getDBConnection();

$order   = null;
$payment = null;
$events  = [];
$error   = '';

if ($orderRef === '') {
    $error = 'No order reference provided.';
} else {
    try {
        // Accept either a numeric id or an order number.
        if (ctype_digit($orderRef)) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$orderRef]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
            $stmt->execute([$orderRef]);
        }
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error = 'Order not found.';
        } else {
            // ---- Access control ----------------------------------------
            $userId = $_SESSION['user_id'] ?? null;
            $role   = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');
            $isAdmin = in_array($role, ['admin', 'super_admin'], true);

            $guestOrders  = $_SESSION['ksr_guest_orders'] ?? [];
            $isGuestOwner = is_array($guestOrders)
                && in_array((int)$order['id'], array_map('intval', $guestOrders), true);
            $isOwner = ($userId !== null && (int)$order['user_id'] === (int)$userId);

            if (!$isAdmin && !$isOwner && !$isGuestOwner) {
                http_response_code(403);
                $error = 'You are not authorised to view this order.';
                $order = null;
            }
        }

        if ($order) {
            $payment = paymentRowForOrder($pdo, (int)$order['id']);

            $evStmt = $pdo->prepare("
                SELECT event_type, old_status, new_status, source, created_at, gateway_payment_id
                FROM payment_events
                WHERE order_id = ?
                ORDER BY id ASC
                LIMIT 100
            ");
            $evStmt->execute([(int)$order['id']]);
            $events = $evStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('payment/status.php: ' . $e->getMessage());
        $error = 'Unable to load this payment right now.';
    }
}

// ---- Presentation helpers ---------------------------------------------
function ksrPayBadge(string $status): array
{
    return match (ksrNormalizePaymentStatus($status)) {
        'success'        => ['✅', 'Payment Confirmed', '#dcfce7', '#15803d'],
        'processing'     => ['🔄', 'Verifying Payment', '#dbeafe', '#1e40af'],
        'pending',
        'initiated'      => ['⏳', 'Payment Pending', '#fef3c7', '#b45309'],
        'failed'         => ['❌', 'Payment Failed', '#fee2e2', '#b91c1c'],
        'cancelled'      => ['🚫', 'Payment Cancelled', '#fee2e2', '#b91c1c'],
        'expired'        => ['⌛', 'Payment Expired', '#f1f5f9', '#475569'],
        'refund_pending' => ['↩️', 'Refund Processing', '#fef3c7', '#b45309'],
        'refunded'       => ['💸', 'Refunded', '#e0e7ff', '#3730a3'],
        'refund_failed'  => ['⚠️', 'Refund Failed', '#fee2e2', '#b91c1c'],
        'cod_pending'    => ['💵', 'Cash on Delivery', '#fef3c7', '#b45309'],
        'cod_collected'  => ['✅', 'Cash Collected', '#dcfce7', '#15803d'],
        default          => ['ℹ️', 'Payment Status', '#f1f5f9', '#475569'],
    };
}

function ksrEventLabel(string $type): string
{
    return match ($type) {
        'ORDER_CREATED'              => 'Order Created',
        'PAYMENT_INITIATED'          => 'Payment Initiated',
        'RAZORPAY_ORDER_CREATED'     => 'Razorpay Order Created',
        'PAYMENT_VERIFYING'          => 'Customer Completed Payment',
        'RAZORPAY_PAYMENT_AUTHORIZED' => 'Payment Authorized',
        'RAZORPAY_PAYMENT_CAPTURED'  => 'Payment Captured',
        'PAYMENT_CONFIRMED'          => 'Payment Confirmed',
        'PAYMENT_PROCESSING'         => 'Order Moved To Processing',
        'SIGNATURE_VERIFICATION_FAILED' => 'Signature Verification Failed',
        'PAYMENT_AMOUNT_MISMATCH'    => 'Amount Mismatch Detected',
        'COD_COLLECTED'              => 'Cash Collected',
        'COD_SETTLED'                => 'COD Settled',
        'COD_REFUSED'                => 'Cash Refused By Customer',
        'PAYMENT_EXPIRED'            => 'Payment Expired',
        'RAZORPAY_REFUND_CREATED'    => 'Refund Initiated',
        'RAZORPAY_REFUND_PROCESSED'  => 'Refund Processed',
        'RAZORPAY_REFUND_FAILED'     => 'Refund Failed',
        'EXPIRY_WORKER_RECOVERED'    => 'Recovered From Gateway',
        default                      => ucwords(strtolower(str_replace(['_', 'RAZORPAY '], [' ', 'Razorpay '], $type))),
    };
}

$currentPaymentStatus = $payment
    ? ksrNormalizePaymentStatus($payment['status'])
    : ksrNormalizePaymentStatus($order['payment_status'] ?? 'pending');

[$badgeIcon, $badgeTitle, $badgeBg, $badgeColor] = ksrPayBadge($currentPaymentStatus);

$amount = $order ? (float)$order['total'] : 0.0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Status — KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(160deg, #f0fdf4 0%, #f8fafc 55%, #eff6ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #0f172a;
        }

        .wrap {
            width: 100%;
            max-width: 520px;
        }

        .card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 24px 60px -20px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .brand {
            padding: 20px 24px;
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand h1 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .brand span {
            font-size: 0.72rem;
            opacity: 0.9;
        }

        .body {
            padding: 26px 24px 28px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.9rem;
            background: <?= $badgeBg ?>;
            color: <?= $badgeColor ?>;
        }

        .badge-icon {
            font-size: 1.05rem;
        }

        .amount {
            font-size: 2.3rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin: 18px 0 2px;
        }

        .meta {
            font-size: 0.85rem;
            color: #64748b;
        }

        .meta strong {
            color: #0f172a;
        }

        .grid {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .field {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 12px;
        }

        .field .k {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
        }

        .field .v {
            font-size: 0.86rem;
            font-weight: 600;
            margin-top: 3px;
            word-break: break-all;
        }

        .timeline {
            margin-top: 24px;
        }

        .timeline h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .step {
            display: flex;
            gap: 12px;
            position: relative;
            padding-bottom: 16px;
        }

        .step:last-child {
            padding-bottom: 0;
        }

        .step::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 20px;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .step:last-child::before {
            display: none;
        }

        .dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #dcfce7;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            flex-shrink: 0;
            z-index: 1;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #bbf7d0;
        }

        .dot.warn {
            background: #fee2e2;
            color: #b91c1c;
            box-shadow: 0 0 0 1px #fecaca;
        }

        .step-text {
            flex: 1;
        }

        .step-text .t {
            font-size: 0.87rem;
            font-weight: 600;
        }

        .step-text .d {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        .actions {
            margin-top: 26px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.87rem;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1ea85a;
            color: #fff;
        }

        .btn-primary:hover {
            background: #16884a;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #cbd5e1;
            color: #0f172a;
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 18px;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            body {
                padding: 16px 10px;
            }
            .card {
                border-radius: 16px;
            }
            .brand {
                padding: 16px 18px;
            }
            .brand h1 {
                font-size: 1.05rem;
            }
            .body {
                padding: 20px 16px 24px;
            }
            .amount {
                font-size: 1.9rem;
            }
            .grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                min-height: 44px;
            }
        }

        @media (max-width: 360px) {
            body {
                padding: 10px 6px;
            }
            .card {
                border-radius: 12px;
            }
            .brand {
                padding: 14px 14px;
            }
            .body {
                padding: 16px 12px 20px;
            }
            .amount {
                font-size: 1.65rem;
            }
            .badge {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            .field .v {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="brand">
                <div>
                    <h1>KSR GURU JI</h1>
                    <span><i class="fas fa-shield-alt"></i> Secure Payment Receipt</span>
                </div>
                <i class="fas fa-receipt" style="font-size:1.6rem;opacity:.9;"></i>
            </div>

            <div class="body">
                <?php if ($error || !$order): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error ?: 'Order not found.') ?></span>
                    </div>
                    <div class="actions">
                        <a class="btn btn-outline" href="../index.php"><i class="fas fa-home"></i> Back to Store</a>
                    </div>
                <?php else: ?>

                    <div class="badge">
                        <span class="badge-icon"><?= $badgeIcon ?></span>
                        <span><?= htmlspecialchars($badgeTitle) ?></span>
                    </div>

                    <div class="amount">₹<?= number_format($amount, 2) ?></div>
                    <div class="meta">
                        Order <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                        &nbsp;•&nbsp;
                        <?= date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now')) ?>
                    </div>

                    <div class="grid">
                        <div class="field">
                            <div class="k">Payment Method</div>
                            <div class="v"><?= htmlspecialchars(strtoupper((string)($payment['method'] ?? $order['payment_method']))) ?></div>
                        </div>
                        <div class="field">
                            <div class="k">Gateway</div>
                            <div class="v"><?= htmlspecialchars(ucfirst((string)($payment['gateway'] ?? '—'))) ?></div>
                        </div>
                        <div class="field">
                            <div class="k">Payment ID</div>
                            <div class="v"><?= htmlspecialchars((string)($payment['gateway_payment_id'] ?? $order['transaction_id'] ?? '—')) ?></div>
                        </div>
                        <div class="field">
                            <div class="k">Order Status</div>
                            <div class="v"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$order['status']))) ?></div>
                        </div>
                        <?php if (!empty($payment['refund_transaction_id'])): ?>
                            <div class="field">
                                <div class="k">Refund ID</div>
                                <div class="v"><?= htmlspecialchars((string)$payment['refund_transaction_id']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($events)): ?>
                        <div class="timeline">
                            <h3><i class="fas fa-stream"></i> Payment Timeline</h3>
                            <?php foreach ($events as $ev):
                                $isWarn = (stripos($ev['event_type'], 'FAILED') !== false
                                    || stripos($ev['event_type'], 'MISMATCH') !== false
                                    || stripos($ev['event_type'], 'REFUSED') !== false
                                    || stripos($ev['event_type'], 'EXPIRED') !== false);
                            ?>
                                <div class="step">
                                    <div class="dot <?= $isWarn ? 'warn' : '' ?>">
                                        <i class="fas <?= $isWarn ? 'fa-times' : 'fa-check' ?>"></i>
                                    </div>
                                    <div class="step-text">
                                        <div class="t"><?= htmlspecialchars(ksrEventLabel((string)$ev['event_type'])) ?></div>
                                        <div class="d">
                                            <?= date('h:i:s A', strtotime((string)$ev['created_at'])) ?>
                                            <?php if (!empty($ev['source']) && $ev['source'] !== 'system'): ?>
                                                &nbsp;•&nbsp; <?= htmlspecialchars(ucfirst((string)$ev['source'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">
                        <a class="btn btn-primary" href="../delivery/tracking.php?ref=<?= urlencode((string)$order['order_number']) ?>">
                            <i class="fas fa-shipping-fast"></i> Track Order
                        </a>
                        <?php if (in_array($currentPaymentStatus, ['success', 'cod_collected'], true)): ?>
                            <a class="btn btn-outline" href="../invoices/Invoice_<?= urlencode((string)$order['order_number']) ?>.pdf" target="_blank">
                                <i class="fas fa-file-invoice"></i> Download Invoice
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="footer">
                        Need help? WhatsApp <strong>8088584421</strong>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($order): ?>
        <script>
            // Live refresh: keep the page in step with the authoritative server state
            // while the payment is still open.
            (function() {
                const orderId = <?= (int)$order['id'] ?>;
                const terminal = <?= paymentStatusIsTerminal($currentPaymentStatus) ? 'true' : 'false' ?>;

                if (terminal) return;

                let attempts = 0;
                const timer = setInterval(async () => {
                    attempts++;
                    if (attempts > 75) {
                        clearInterval(timer);
                        return;
                    }

                    try {
                        const res = await fetch(
                            `../api/payment_status.php?order_id=${orderId}`, {
                                cache: 'no-store',
                                credentials: 'same-origin'
                            }
                        );
                        const data = await res.json();
                        if (data.success && data.terminal) {
                            clearInterval(timer);
                            window.location.reload();
                        }
                    } catch (e) {
                        /* keep polling */
                    }
                }, 3000);
            })();
        </script>
    <?php endif; ?>
</body>

</html>