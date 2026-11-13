<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';
require_once __DIR__ . '/config.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

// ── Auth ─────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$paymentId = trim($_GET['pid'] ?? '');
$orderId   = (int)($_GET['oid'] ?? 0);

if ($paymentId === '' || $orderId < 1) {
    header('Location: /'); exit;
}

// ── Load payment + order ─────────────────────────────────────────────────────
$ptStmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, o.net_amount, o.total_amount, o.created_at AS order_date
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND pt.order_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     LIMIT 1"
);
$ptStmt->execute([$paymentId, $orderId, $userId, $userId]);
$payment = $ptStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) { header('Location: /'); exit; }

// Order items
$itemsStmt = $pdo->prepare(
    "SELECT oi.quantity, oi.price, oi.total, p.name, p.unit
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.id"
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$calc   = new AmountCalculator();
$sec    = new SecurityManager();
$csrf   = $sec->generateCSRFToken();
$upi    = getUpiConfig($pdo);
$amount = $calc->formatINR((int)$payment['amount_paise']);
$amountPaise = (int)$payment['amount_paise'];

$pageTitle = 'Pay via UPI — KSR GURU JI PAY';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  .pay-card { max-width: 520px; margin: 2rem auto; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.1); background: #fff; }
  .amount-pill { background: #1a7f4b; color: #fff; border-radius: 8px; padding: .6rem 1.2rem; display: inline-block; font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; }
  .upi-id-box { background: #f5f5f5; border: 2px dashed #1a7f4b; border-radius: 8px; padding: 1rem; text-align: center; font-size: 1.1rem; letter-spacing: .05em; margin-bottom: 1.2rem; }
  .steps li { margin-bottom: .5rem; }
  .utr-input { width: 100%; padding: .75rem; font-size: 1.1rem; border: 2px solid #ccc; border-radius: 8px; letter-spacing: .1em; }
  .utr-input:focus { border-color: #1a7f4b; outline: none; }
  .btn-pay { width: 100%; padding: .85rem; background: #1a7f4b; color: #fff; font-size: 1.1rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; }
  .btn-pay:disabled { opacity: .6; cursor: not-allowed; }
  .order-summary { font-size: .9rem; color: #555; margin-bottom: 1.2rem; }
  .alert-info { background: #e8f4fd; border-left: 4px solid #2196f3; padding: .75rem 1rem; border-radius: 4px; font-size: .9rem; margin-bottom: 1rem; }
  @media(prefers-color-scheme:dark){ .pay-card{background:#1e1e2e;color:#cdd6f4;} .upi-id-box{background:#2a2a3c;border-color:#89dceb;} }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="pay-card">
  <h2 style="margin-bottom:.5rem;"><i class="fas fa-mobile-alt" style="color:#1a7f4b;"></i> Pay via UPI</h2>
  <p class="order-summary">
    Order #<?= htmlspecialchars($payment['order_number']) ?> &nbsp;|&nbsp;
    <?= date('d M Y', strtotime($payment['order_date'])) ?>
  </p>

  <div class="amount-pill"><?= $amount ?></div>

  <?php if (!empty($upi['upi_id']) && $upi['upi_id'] !== 'PLACEHOLDER_UPI_ID@sbi'): ?>
    <p><strong>Send exactly <?= $amount ?> to:</strong></p>
    <div class="upi-id-box" id="upiIdBox">
      <?= htmlspecialchars($upi['upi_id']) ?>
    </div>
    <button onclick="copyUPI()" style="background:none;border:1px solid #1a7f4b;color:#1a7f4b;border-radius:6px;padding:.4rem .9rem;cursor:pointer;margin-bottom:1rem;">
      <i class="fas fa-copy"></i> Copy UPI ID
    </button>

    <?php if (!empty($upi['qr_image_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $upi['qr_image_path'])): ?>
    <div style="text-align:center;margin-bottom:1rem;">
      <img src="<?= htmlspecialchars($upi['qr_image_path']) ?>" alt="Scan to Pay" style="width:200px;border:1px solid #ddd;border-radius:8px;">
      <p style="font-size:.8rem;color:#888;margin-top:.4rem;">Scan with any UPI app</p>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert-info">
      <i class="fas fa-info-circle"></i>
      Our UPI payment is being set up. For now please use <strong>Bank Transfer</strong> or <strong>Cash on Delivery</strong>.
      <br><a href="/payment/index.php?oid=<?= $orderId ?>">← Change payment method</a>
    </div>
  <?php endif; ?>

  <ol class="steps">
    <li>Open <strong>PhonePe / GPay / Paytm</strong> or any UPI app</li>
    <li>Send exactly <strong><?= $amount ?></strong></li>
    <li>Note your 12-digit UTR number from the app</li>
    <li>Enter it below and submit</li>
  </ol>

  <div class="alert-info" style="margin-top:1rem;">
    <i class="fas fa-clock"></i>
    After submitting, we verify your payment within <strong>1–2 business hours</strong>.
    You'll receive a confirmation SMS/email.
  </div>

  <form id="utrForm" method="post" action="/payment-methods/upi/submit_reference.php">
    <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="payment_id"  value="<?= htmlspecialchars($paymentId) ?>">
    <input type="hidden" name="order_id"    value="<?= (int)$orderId ?>">

    <label for="utr" style="font-weight:600;display:block;margin-bottom:.4rem;">
      Your UPI Transaction ID / UTR Number
    </label>
    <input type="text" id="utr" name="utr" class="utr-input"
           placeholder="e.g. 419876543210"
           maxlength="22" pattern="[A-Za-z0-9]{10,22}" required
           autocomplete="off">
    <small style="color:#888;display:block;margin:.4rem 0 1rem;">
      10–22 alphanumeric characters. Find it in your UPI app under transaction history.
    </small>

    <div id="formMsg" style="display:none;margin-bottom:.8rem;padding:.6rem 1rem;border-radius:6px;"></div>

    <button type="submit" class="btn-pay" id="submitBtn">
      <i class="fas fa-paper-plane"></i> Submit UTR &amp; Confirm Payment
    </button>
  </form>
</div>

<script>
function copyUPI() {
  const txt = document.getElementById('upiIdBox').innerText.trim();
  navigator.clipboard.writeText(txt).then(() => {
    alert('UPI ID copied: ' + txt);
  });
}

document.getElementById('utrForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  const msg = document.getElementById('formMsg');
  const utr = document.getElementById('utr').value.trim();

  if (!/^[A-Za-z0-9]{10,22}$/.test(utr)) {
    msg.textContent = 'Please enter a valid UTR (10–22 alphanumeric characters).';
    msg.style.cssText = 'display:block;background:#fde;color:#c00;';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Submitting…';

  const fd = new FormData(this);
  try {
    const res  = await fetch('/payment-methods/upi/submit_reference.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      msg.textContent = data.message || 'UTR submitted successfully!';
      msg.style.cssText = 'display:block;background:#dfd;color:#1a7f4b;';
      setTimeout(() => { window.location.href = data.redirect || '/payment/payment_pending.php?pid=<?= urlencode($paymentId) ?>'; }, 1200);
    } else {
      msg.textContent = data.message || 'Submission failed. Please try again.';
      msg.style.cssText = 'display:block;background:#fde;color:#c00;';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit UTR & Confirm Payment';
    }
  } catch {
    msg.textContent = 'Network error. Please try again.';
    msg.style.cssText = 'display:block;background:#fde;color:#c00;';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit UTR & Confirm Payment';
  }
});
</script>
</body>
</html>

