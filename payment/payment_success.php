<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
if ($paymentId === '') { header('Location: /'); exit; }

$stmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, o.created_at AS order_date
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     AND pt.status IN ('success','cod_collected')
     LIMIT 1"
);
$stmt->execute([$paymentId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) { header('Location: /payment/payment_status.php?pid=' . urlencode($paymentId)); exit; }

$calc     = new AmountCalculator();
$amount   = $calc->formatINR((int)$payment['amount_paise']);
$orderNum = htmlspecialchars($payment['order_number']);
$method   = ucfirst(str_replace('_', ' ', $payment['method']));
$paidAt   = $payment['verified_at'] ? date('d M Y, h:i A', strtotime($payment['verified_at'])) : date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Successful — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.success-card{max-width:500px;margin:3rem auto;padding:2rem;border-radius:14px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1);background:#fff}
.check-circle{width:80px;height:80px;background:#1a7f4b;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;font-size:2rem;color:#fff}
.detail-row{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #f0f0f0;font-size:.95rem}
.btn-action{display:inline-block;padding:.6rem 1.4rem;border-radius:8px;text-decoration:none;font-weight:600;margin:.4rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="success-card">
  <div class="check-circle"><i class="fas fa-check"></i></div>
  <h2 style="color:#1a7f4b;margin-bottom:.3rem;">Payment Confirmed!</h2>
  <p style="color:#888;margin-bottom:1.5rem;">Your payment has been verified successfully.</p>

  <div style="text-align:left;margin-bottom:1.5rem;">
    <div class="detail-row"><span>Order Number</span><strong>#<?= $orderNum ?></strong></div>
    <div class="detail-row"><span>Amount Paid</span><strong><?= $amount ?></strong></div>
    <div class="detail-row"><span>Payment Method</span><span><?= htmlspecialchars($method) ?></span></div>
    <div class="detail-row"><span>Confirmed On</span><span><?= $paidAt ?></span></div>
    <div class="detail-row"><span>Reference</span><small style="font-family:monospace"><?= htmlspecialchars($paymentId) ?></small></div>
  </div>

  <a href="/payment/receipt.php?pid=<?= urlencode($paymentId) ?>" class="btn-action" style="background:#1a7f4b;color:#fff;">
    <i class="fas fa-receipt"></i> Download Receipt
  </a>
  <a href="/" class="btn-action" style="background:#f0f0f0;color:#333;">
    <i class="fas fa-shopping-bag"></i> Continue Shopping
  </a>

  <!-- WhatsApp Share -->
  <div style="margin-top:1rem;">
    <a href="https://wa.me/?text=My+order+%23<?= urlencode($orderNum) ?>+from+KSR+GURU+JI+is+confirmed%21+Payment+of+<?= urlencode($amount) ?>+received."
       target="_blank" style="color:#25d366;font-size:.9rem;">
       <i class="fab fa-whatsapp"></i> Share on WhatsApp
    </a>
  </div>
</div>
</body>
</html>

