<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$paymentId = trim($_GET['pid'] ?? '');
if ($paymentId === '') { header('Location: /'); exit; }

$stmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, o.net_amount, u.name AS customer_name
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     LEFT JOIN users u ON u.id = pt.user_id
     WHERE pt.payment_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     LIMIT 1"
);
$stmt->execute([$paymentId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) { header('Location: /'); exit; }

$status  = ksrNormalizePaymentStatus($payment['status']);
$calc    = new AmountCalculator();
$amount  = $calc->formatINR((int)$payment['amount_paise']);
$message = ksrPaymentStatusMessage($status);
$terminal = paymentStatusIsTerminal($status);

// Map status to redirect
$redirect = match(true) {
    $status === 'success' || $status === 'cod_collected' =>
        '/payment/payment_success.php?pid=' . urlencode($paymentId),
    in_array($status, ['failed','cancelled','expired'], true) =>
        '/payment/payment_failed.php?pid=' . urlencode($paymentId),
    default => null,
};
if ($redirect) { header("Location: {$redirect}"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Status — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.status-card{max-width:480px;margin:3rem auto;padding:2rem;border-radius:14px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.1);background:#fff}
.spinner{width:60px;height:60px;border:5px solid #e0e0e0;border-top-color:#1a7f4b;border-radius:50%;animation:spin 1s linear infinite;margin:1.5rem auto}
@keyframes spin{to{transform:rotate(360deg)}}
.status-msg{font-size:1.1rem;color:#555;margin:.8rem 0}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="status-card">
  <h2><i class="fas fa-clock" style="color:#f9a825;"></i> Payment in Progress</h2>
  <p style="color:#888;font-size:.9rem;">Order #<?= htmlspecialchars($payment['order_number']) ?></p>
  <div class="spinner" id="spinner"></div>
  <div class="status-msg" id="statusMsg"><?= htmlspecialchars($message) ?></div>
  <p style="font-size:.85rem;color:#aaa;">Amount: <strong><?= $amount ?></strong></p>
  <p style="font-size:.8rem;color:#ccc;margin-top:1rem;">Checking every 10 seconds…</p>
</div>

<script>
const pid = <?= json_encode($paymentId) ?>;
let checks = 0;
const maxChecks = 36; // 6 minutes max

async function checkStatus() {
  try {
    const r = await fetch('/payment-methods/upi/check_status.php?pid=' + encodeURIComponent(pid));
    const d = await r.json();
    document.getElementById('statusMsg').textContent = d.message || '';

    if (d.status === 'success' || d.status === 'cod_collected') {
      clearInterval(timer);
      window.location.href = '/payment/payment_success.php?pid=' + encodeURIComponent(pid);
    } else if (d.terminal && d.status !== 'upi_awaiting' && d.status !== 'upi_pending_verification') {
      clearInterval(timer);
      window.location.href = '/payment/payment_failed.php?pid=' + encodeURIComponent(pid);
    }
  } catch {}

  if (++checks >= maxChecks) {
    clearInterval(timer);
    document.getElementById('statusMsg').textContent = 'Still waiting. If you have paid, please save your UTR and contact support.';
  }
}

const timer = setInterval(checkStatus, 10000);
checkStatus();
</script>
</body>
</html>

