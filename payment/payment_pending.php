<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
if ($paymentId === '') { header('Location: /'); exit; }

$stmt = $pdo->prepare(
    "SELECT pt.*, o.order_number FROM payment_transactions pt JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?) LIMIT 1"
);
$stmt->execute([$paymentId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { header('Location: /'); exit; }

$status  = ksrNormalizePaymentStatus($payment['status']);
if ($status === 'success' || $status === 'cod_collected') {
    header('Location: /payment/payment_success.php?pid=' . urlencode($paymentId)); exit;
}

$calc    = new AmountCalculator();
$amount  = $calc->formatINR((int)$payment['amount_paise']);
$utr     = $payment['customer_utr'] ?? '';
$utrMask = $utr ? (strlen($utr) > 8
    ? substr($utr, 0, 4) . str_repeat('*', strlen($utr) - 8) . substr($utr, -4)
    : str_repeat('*', strlen($utr))) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Pending — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.pend-card{max-width:500px;margin:3rem auto;padding:2rem;border-radius:14px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1);background:#fff}
.clock-circle{width:80px;height:80px;background:#f9a825;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;font-size:2rem;color:#fff}
.info-box{background:#fffde7;border:1px solid #fff59d;border-radius:8px;padding:1rem;margin:1rem 0;font-size:.92rem;text-align:left}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="pend-card">
  <div class="clock-circle"><i class="fas fa-clock"></i></div>
  <h2 style="color:#f9a825;">Verification in Progress</h2>
  <p style="color:#888;margin-bottom:1rem;">Order #<?= htmlspecialchars($payment['order_number']) ?></p>
  <p style="font-size:1.1rem;">We're verifying your UPI payment of <strong><?= $amount ?></strong></p>

  <?php if ($utrMask): ?>
  <div class="info-box">
    <i class="fas fa-receipt"></i>
    UTR submitted: <strong style="font-family:monospace"><?= htmlspecialchars($utrMask) ?></strong>
    &nbsp;<a href="/payment-methods/upi/merchant_qr.php?pid=<?= urlencode($paymentId) ?>&oid=<?= (int)$payment['order_id'] ?>"
             style="font-size:.82rem;color:#1a7f4b;">Change UTR</a>
  </div>
  <?php endif; ?>

  <div class="info-box" style="background:#e8f5e9;border-color:#c8e6c9;">
    <i class="fas fa-info-circle" style="color:#1a7f4b;"></i>
    Our team verifies payments manually within <strong>1–2 business hours</strong>.
    You'll receive an SMS/email confirmation once verified.
  </div>

  <div id="statusUpdate" style="font-size:.88rem;color:#aaa;margin-top:1rem;">Checking status…</div>

  <p style="font-size:.8rem;color:#bbb;margin-top:1.5rem;">
    Reference: <code><?= htmlspecialchars($paymentId) ?></code>
  </p>
</div>

<script>
const pid = <?= json_encode($paymentId) ?>;
let checks = 0;
async function poll() {
  try {
    const r = await fetch('/payment-methods/upi/check_status.php?pid=' + encodeURIComponent(pid));
    const d = await r.json();
    document.getElementById('statusUpdate').textContent = d.message || '';
    if (d.status === 'success' || d.status === 'cod_collected') {
      clearInterval(timer);
      window.location.href = '/payment/payment_success.php?pid=' + encodeURIComponent(pid);
    } else if (d.terminal) {
      clearInterval(timer);
      window.location.href = '/payment/payment_failed.php?pid=' + encodeURIComponent(pid);
    }
  } catch {}
  if (++checks >= 36) clearInterval(timer);
}
const timer = setInterval(poll, 10000);
poll();
</script>
</body>
</html>

