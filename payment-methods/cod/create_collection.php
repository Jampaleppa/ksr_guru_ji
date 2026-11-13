<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AuditLogger;
use KsrPay\AmountCalculator;
use KsrPay\PaymentStateMachine;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
$orderId   = (int)($_GET['oid'] ?? 0);

if ($paymentId === '' || $orderId < 1) { header('Location: /'); exit; }

$stmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, o.net_amount, o.address, o.city
     FROM payment_transactions pt JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND pt.order_id = ?
       AND (pt.user_id = ? OR o.user_id = ?) AND pt.method = 'cod'
     LIMIT 1"
);
$stmt->execute([$paymentId, $orderId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { header('Location: /'); exit; }

// Transition to cod_pending if still initiated
if ($payment['status'] === 'initiated') {
    $sm = new PaymentStateMachine($pdo, new AuditLogger($pdo));
    $sm->transition($paymentId, 'cod_pending', [
        'actor_type' => 'system',
        'notes'      => 'COD order confirmed by customer',
    ]);
}

$calc   = new AmountCalculator();
$amount = $calc->formatINR((int)$payment['amount_paise']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cash on Delivery — KSR GURU JI</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.cod-card{max-width:500px;margin:2rem auto;padding:2rem;border-radius:14px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.1);background:#fff}
.cod-icon{width:80px;height:80px;background:#f9a825;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;font-size:2.2rem}
.info-box{background:#fffde7;border:1px solid #fff59d;border-radius:8px;padding:1rem;margin:1rem 0;font-size:.92rem;text-align:left}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="cod-card">
  <div class="cod-icon">💵</div>
  <h2 style="color:#f9a825;">Cash on Delivery Confirmed</h2>
  <p style="color:#888;font-size:.9rem;">Order #<?= htmlspecialchars($payment['order_number']) ?></p>
  <p style="font-size:1.3rem;font-weight:700;margin:1rem 0"><?= $amount ?></p>

  <div class="info-box">
    <i class="fas fa-map-marker-alt" style="color:#1a7f4b;"></i>
    <strong>Delivery to:</strong><br>
    <?= htmlspecialchars($payment['address'] ?? '') ?>,
    <?= htmlspecialchars($payment['city'] ?? '') ?>
  </div>

  <div class="info-box" style="background:#e8f5e9;border-color:#c8e6c9;">
    <i class="fas fa-info-circle" style="color:#1a7f4b;"></i>
    Please keep <strong><?= $amount ?></strong> ready in cash when our delivery person arrives.
    Our team will confirm the order and delivery time via SMS.
  </div>

  <p style="font-size:.82rem;color:#aaa;margin-top:1rem">
    Reference: <code><?= htmlspecialchars($paymentId) ?></code>
  </p>

  <div style="margin-top:1.5rem">
    <a href="/" style="display:inline-block;padding:.7rem 1.5rem;background:#1a7f4b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">
      <i class="fas fa-shopping-bag"></i> Continue Shopping
    </a>
  </div>
</div>
</body>
</html>

