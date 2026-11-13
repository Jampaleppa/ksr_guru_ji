<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
if ($paymentId === '') { header('Location: /'); exit; }

$stmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, o.created_at AS order_date, o.name AS cust_name,
            o.phone, o.address, o.city, o.state, o.pincode
     FROM payment_transactions pt JOIN orders o ON o.id = pt.order_id
     LEFT JOIN users u ON u.id = pt.user_id
     WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     AND pt.status IN ('success','cod_collected')
     LIMIT 1"
);
$stmt->execute([$paymentId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { header('Location: /'); exit; }

$iStmt = $pdo->prepare(
    "SELECT oi.quantity, oi.price, oi.total, p.name, p.unit
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.id"
);
$iStmt->execute([(int)$payment['order_id']]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

$calc   = new AmountCalculator();
$amount = $calc->formatINR((int)$payment['amount_paise']);
$method = ucfirst(str_replace('_', ' ', $payment['method']));
$paidAt = $payment['verified_at'] ? date('d M Y, h:i A', strtotime($payment['verified_at'])) : date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt — <?= htmlspecialchars($payment['order_number']) ?> — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.receipt{max-width:600px;margin:2rem auto;padding:2rem;border:1px solid #ddd;border-radius:8px;background:#fff;font-size:.93rem}
.receipt-header{text-align:center;border-bottom:2px solid #1a7f4b;padding-bottom:1rem;margin-bottom:1.5rem}
.paid-stamp{color:#1a7f4b;border:3px solid #1a7f4b;display:inline-block;padding:.3rem 1rem;border-radius:4px;font-weight:900;font-size:1.3rem;transform:rotate(-8deg);margin-left:1rem}
table{width:100%;border-collapse:collapse;margin:1rem 0}
th,td{padding:.5rem .7rem;text-align:left;border-bottom:1px solid #f0f0f0}
th{background:#f9f9f9;font-weight:600}
.total-row td{font-weight:700;border-top:2px solid #1a7f4b;font-size:1rem}
.btn-print{padding:.6rem 1.4rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.95rem;font-weight:700}
@media print{
  body *{visibility:hidden}
  .receipt,.receipt *{visibility:visible}
  .receipt{position:absolute;top:0;left:0;width:100%;border:none;box-shadow:none}
  .no-print{display:none}
}
</style>
</head>
<body>
<div class="receipt" id="receipt">
  <div class="receipt-header">
    <h2 style="margin:0;color:#1a7f4b;">KSR GURU JI PAY</h2>
    <p style="margin:.3rem 0;color:#888;">Payment Receipt</p>
    <span class="paid-stamp">PAID</span>
  </div>

  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <div>
      <strong>Receipt No:</strong><br>
      <code style="font-size:.85rem"><?= htmlspecialchars($paymentId) ?></code>
    </div>
    <div>
      <strong>Order No:</strong><br>
      #<?= htmlspecialchars($payment['order_number']) ?>
    </div>
    <div>
      <strong>Date:</strong><br>
      <?= $paidAt ?>
    </div>
    <div>
      <strong>Method:</strong><br>
      <?= htmlspecialchars($method) ?>
    </div>
  </div>

  <?php if (!empty($payment['cust_name'])): ?>
  <div style="margin-bottom:1rem">
    <strong>Delivery Address:</strong><br>
    <?= htmlspecialchars($payment['cust_name']) ?><br>
    <?= htmlspecialchars($payment['address'] ?? '') ?>,
    <?= htmlspecialchars($payment['city'] ?? '') ?>,
    <?= htmlspecialchars($payment['state'] ?? '') ?> —
    <?= htmlspecialchars($payment['pincode'] ?? '') ?>
  </div>
  <?php endif; ?>

  <table>
    <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['name']) ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td>₹<?= number_format((float)$item['price'], 2) ?></td>
          <td>₹<?= number_format((float)$item['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="3">Total Paid</td>
        <td><?= $amount ?></td>
      </tr>
    </tbody>
  </table>

  <div style="margin-top:1.5rem;font-size:.82rem;color:#888;text-align:center">
    Thank you for shopping with KSR GURU JI!<br>
    For support: <a href="/payment/support.php">Contact Us</a>
  </div>
</div>

<div class="no-print" style="text-align:center;margin:1.5rem auto;max-width:600px">
  <button class="btn-print" onclick="window.print()">
    <span>🖨</span> Print / Save as PDF
  </button>
  &nbsp;
  <a href="/" style="color:#1a7f4b;font-weight:600;">← Back to Shop</a>
</div>
</body>
</html>

