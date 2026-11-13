<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit;
}

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['oid'] ?? 0);

if ($orderId < 1) { header('Location: /'); exit; }

// Load order with server-side ownership check
$oStmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?
     LIMIT 1"
);
$oStmt->execute([$orderId, $userId]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { header('Location: /'); exit; }

// Block checkout if already paid
if (in_array($order['payment_status'], ['paid', 'cod_collected', 'success'], true)) {
    header('Location: /payment/payment_success.php?oid=' . $orderId); exit;
}

// Load order items
$iStmt = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.image AS product_image
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id"
);
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// Server-calculated amounts (paise) — never trust client
$rawTotal         = $order['net_amount'] ?? $order['total'] ?? 0;
$netAmountPaise   = is_numeric($rawTotal) && (float)$rawTotal > 0 && strpos((string)$rawTotal, '.') === false && (int)$rawTotal > 1000 ? (int)$rawTotal : AmountCalculator::toPaise((float)$rawTotal);
$subtotalPaise    = (int)($order['subtotal']       ?? 0);
$deliveryPaise    = (int)($order['delivery_charge'] ?? 0);
$discountPaise    = (int)($order['discount']        ?? 0);
$taxPaise         = (int)($order['tax']             ?? 0);

// CSRF token for the proceed-to-pay form
$csrfToken = SecurityManager::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Review – KSR GURU JI</title>
    <?php include __DIR__ . '/../global-header.php'; ?>
    <style>
        :root {
            --primary: #ff6b35;
            --success: #28a745;
            --muted: #6c757d;
            --border: #dee2e6;
        }
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .checkout-wrapper { max-width: 760px; margin: 2rem auto; padding: 0 1rem 3rem; }
        h1.page-title { font-size: 1.5rem; font-weight: 700; color: #212529; margin-bottom: 1.5rem; }
        h1.page-title span { color: var(--primary); }

        /* Order summary card */
        .card { background: #fff; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 1.25rem; }
        .card-header { padding: .85rem 1.25rem; border-bottom: 1px solid var(--border); font-weight: 600; font-size: .95rem; color: #495057; }
        .card-body { padding: 1.25rem; }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .items-table th { text-align: left; padding: .6rem .5rem; color: var(--muted); border-bottom: 1px solid var(--border); }
        .items-table td { padding: .65rem .5rem; vertical-align: middle; border-bottom: 1px solid #f0f0f0; }
        .items-table tr:last-child td { border-bottom: none; }
        .item-img { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; }
        .item-name { font-weight: 500; }
        .item-qty { text-align: center; }
        .item-price { text-align: right; white-space: nowrap; }

        /* Amount breakdown */
        .amount-row { display: flex; justify-content: space-between; padding: .45rem 0; font-size: .93rem; color: #495057; }
        .amount-row.total { font-size: 1.1rem; font-weight: 700; color: #212529; border-top: 2px solid var(--border); padding-top: .75rem; margin-top: .4rem; }
        .discount-val { color: var(--success); }

        /* Customer info */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem 1.5rem; font-size: .9rem; color: #495057; }
        .info-item label { font-weight: 600; display: block; font-size: .8rem; color: var(--muted); }

        /* Proceed button */
        .proceed-btn {
            display: block; width: 100%; padding: .95rem;
            background: var(--primary); color: #fff; font-size: 1.1rem; font-weight: 700;
            border: none; border-radius: 8px; cursor: pointer; text-align: center;
            letter-spacing: .02em; transition: background .2s;
        }
        .proceed-btn:hover { background: #e55a2b; }
        .back-link { display: inline-block; margin-top: .75rem; color: var(--muted); font-size: .88rem; text-decoration: none; }
        .back-link:hover { color: var(--primary); }

        /* Security badge */
        .secure-note { text-align: center; font-size: .8rem; color: var(--muted); margin-top: .6rem; }
        .secure-note svg { vertical-align: middle; margin-right: .2rem; }

        @media (max-width: 540px) {
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="checkout-wrapper">
    <h1 class="page-title">Review Your Order &amp; <span>Proceed to Pay</span></h1>

    <!-- Order meta -->
    <div class="card">
        <div class="card-header">Order #<?= htmlspecialchars((string)$orderId) ?></div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Name</label>
                    <?= htmlspecialchars($order['customer_name'] ?? '—') ?>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <?= htmlspecialchars($order['customer_email'] ?? '—') ?>
                </div>
                <?php if (!empty($order['delivery_address'])): ?>
                <div class="info-item" style="grid-column:1/-1">
                    <label>Delivery Address</label>
                    <?= nl2br(htmlspecialchars($order['delivery_address'])) ?>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Order Date</label>
                    <?= htmlspecialchars(date('d M Y, h:i A', strtotime($order['created_at']))) ?>
                </div>
                <div class="info-item">
                    <label>Payment Status</label>
                    <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'pending')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Items -->
    <?php if ($items): ?>
    <div class="card">
        <div class="card-header">Items (<?= count($items) ?>)</div>
        <div class="card-body" style="padding:0 1.25rem">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:52px"></th>
                        <th>Product</th>
                        <th class="item-qty">Qty</th>
                        <th class="item-price">Price</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['product_image'])): ?>
                                <img src="<?= htmlspecialchars($item['product_image']) ?>"
                                     alt="" class="item-img">
                            <?php else: ?>
                                <div class="item-img" style="background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:.7rem;">No img</div>
                            <?php endif; ?>
                        </td>
                        <td class="item-name"><?= htmlspecialchars($item['product_name'] ?? 'Product') ?></td>
                        <td class="item-qty"><?= (int)$item['quantity'] ?></td>
                        <td class="item-price">
                            <?= AmountCalculator::formatINR((int)($item['price'] * (int)$item['quantity'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Amount breakdown -->
    <div class="card">
        <div class="card-header">Amount Breakdown</div>
        <div class="card-body">
            <?php if ($subtotalPaise > 0): ?>
            <div class="amount-row">
                <span class="amount-label">Subtotal</span>
                <span><?= AmountCalculator::formatINR($subtotalPaise) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($deliveryPaise > 0): ?>
            <div class="amount-row">
                <span class="amount-label">Delivery Charge</span>
                <span><?= AmountCalculator::formatINR($deliveryPaise) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($taxPaise > 0): ?>
            <div class="amount-row">
                <span class="amount-label">Tax &amp; GST</span>
                <span><?= AmountCalculator::formatINR($taxPaise) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($discountPaise > 0): ?>
            <div class="amount-row">
                <span class="amount-label">Discount</span>
                <span class="discount-val">– <?= AmountCalculator::formatINR($discountPaise) ?></span>
            </div>
            <?php endif; ?>
            <div class="amount-row total">
                <span class="amount-label">Total Payable</span>
                <span><?= AmountCalculator::formatINR($netAmountPaise) ?></span>
            </div>
        </div>
    </div>

    <!-- Proceed to Pay form -->
    <form method="POST" action="/payment/create_payment.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="order_id"   value="<?= $orderId ?>">
        <button type="submit" class="proceed-btn">
            🔒 Proceed to Pay <?= AmountCalculator::formatINR($netAmountPaise) ?>
        </button>
    </form>

    <div class="secure-note">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Payments are secured. Your payment info is never stored on our servers.
    </div>

    <a href="/orders.php" class="back-link">← Back to My Orders</a>
</div>
</body>
</html>

