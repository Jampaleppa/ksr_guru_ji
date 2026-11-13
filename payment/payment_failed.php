<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
$orderId   = (int)($_GET['oid'] ?? 0);

$payment = null;
if ($paymentId !== '') {
    $stmt = $pdo->prepare(
        "SELECT pt.*, o.order_number, o.id AS order_id
         FROM payment_transactions pt JOIN orders o ON o.id = pt.order_id
         WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?) LIMIT 1"
    );
    $stmt->execute([$paymentId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}
$orderId = $payment['order_id'] ?? $orderId;
$calc    = new AmountCalculator();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Failed — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.fail-card{max-width:480px;margin:3rem auto;padding:2rem;border-radius:14px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1);background:#fff}
.x-circle{width:80px;height:80px;background:#e53e3e;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;font-size:2rem;color:#fff}
.btn-retry{display:inline-block;padding:.75rem 2rem;background:#1a7f4b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;margin:.5rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="fail-card">
  <div class="x-circle"><i class="fas fa-times"></i></div>
  <h2 style="color:#e53e3e;">Payment Failed</h2>
  <?php if ($payment): ?>
    <p style="color:#888;">Order #<?= htmlspecialchars($payment['order_number']) ?></p>
    <?php if (!empty($payment['failure_reason'])): ?>
      <p style="background:#fff5f5;border:1px solid #fed7d7;padding:.75rem;border-radius:8px;font-size:.9rem;margin:1rem 0;">
        <?= htmlspecialchars($payment['failure_reason']) ?>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p style="color:#888;">Your payment could not be processed.</p>
  <?php endif; ?>

  <div style="margin-top:1.5rem;">
    <?php if ($orderId): ?>
      <a href="/payment/index.php?oid=<?= $orderId ?>" class="btn-retry">
        <i class="fas fa-redo"></i> Retry Payment
      </a>
    <?php endif; ?>
    <a href="/payment/support.php<?= $paymentId ? '?pid=' . urlencode($paymentId) : '' ?>"
       style="display:inline-block;padding:.75rem 2rem;background:#f0f0f0;color:#333;border-radius:8px;text-decoration:none;font-weight:600;margin:.5rem">
      <i class="fas fa-headset"></i> Contact Support
    </a>
  </div>
  <p style="font-size:.8rem;color:#ccc;margin-top:1.5rem;">
    If money was deducted from your account, it will be refunded within 3–5 business days.
  </p>
</div>
</body>
</html>

