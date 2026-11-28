<?php

declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';
require_once __DIR__ . '/../upi/config.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit;
}

$paymentId = trim($_GET['pid'] ?? '');
$orderId   = (int)($_GET['oid'] ?? 0);
$calc      = new AmountCalculator();

$stmt = $pdo->prepare(
  "SELECT pt.*, o.order_number, o.net_amount FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.payment_id = ? AND pt.order_id = ? AND (pt.user_id = ? OR o.user_id = ?)
     LIMIT 1"
);
$stmt->execute([$paymentId, $orderId, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
  header('Location: /');
  exit;
}

$btConfig = getBankTransferConfig($pdo);
$amount   = $calc->formatINR((int)$payment['amount_paise']);

$sec  = new SecurityManager();
$csrf = $sec->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bank Transfer — KSR GURU JI PAY</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f4f7f6; font-family: 'Segoe UI', system-ui, sans-serif; color: #1f2937; }
    .bt-card {
      max-width: 540px;
      margin: 1.25rem auto 2.5rem;
      padding: 1.5rem;
      border-radius: 14px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .09);
      background: #fff;
    }
    @media (max-width: 560px) { .bt-card { margin: .75rem; padding: 1.1rem; } }
    .bank-detail {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem;
      padding: .6rem 0;
      border-bottom: 1px solid #e5e7eb;
      font-size: .93rem;
      flex-wrap: wrap;
    }
    .copy-mini {
      background: #e8f5e9;
      border: 1px solid #a7f3d0;
      color: #1a7f4b;
      border-radius: 6px;
      padding: .25rem .6rem;
      font-size: .76rem;
      font-weight: 700;
      cursor: pointer;
      margin-left: .4rem;
      min-height: 30px;
    }
    .amount-pill {
      background: #1a7f4b;
      color: #fff;
      border-radius: 8px;
      padding: .6rem 1.2rem;
      display: inline-block;
      font-size: 1.4rem;
      font-weight: 700;
    }
    .utr-input {
      width: 100%;
      padding: .8rem;
      font-size: 1rem;
      border: 2px solid #ccc;
      border-radius: 8px;
    }
    .utr-input:focus { border-color: #1a7f4b; outline: none; }
    .btn-submit {
      width: 100%;
      min-height: 48px;
      padding: .85rem;
      background: #1a7f4b;
      color: #fff;
      font-size: 1.05rem;
      font-weight: 700;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      margin-top: 1rem;
    }
    .btn-submit:disabled { opacity: .6; cursor: not-allowed; }
  </style>
</head>

<body>
  <?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

  <div class="bt-card">
    <h2 style="margin-top:0;"><i class="fas fa-university" style="color:#1a7f4b;"></i> Bank Transfer</h2>
    <p style="color:#6b7280;font-size:.9rem;">Order #<?= htmlspecialchars($payment['order_number']) ?></p>

    <div class="amount-pill" style="margin-bottom:1.2rem"><?= $amount ?></div>

    <p style="font-weight:600;">Transfer exactly <strong><?= $amount ?></strong> to:</p>

    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:1.2rem">
      <?php if (!empty($btConfig['account_number']) && $btConfig['account_number'] !== 'PLACEHOLDER'): ?>
        <div class="bank-detail"><span>Account Holder</span><strong><?= htmlspecialchars($btConfig['account_holder']) ?></strong></div>
        <div class="bank-detail"><span>Bank</span><span><?= htmlspecialchars($btConfig['bank_name']) ?></span></div>
        <div class="bank-detail">
          <span>Account Number</span>
          <span>
            <span style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($btConfig['account_number']) ?></span>
            <button type="button" class="copy-mini" onclick="copyBankField('<?= htmlspecialchars($btConfig['account_number'], ENT_QUOTES) ?>', this)">Copy</button>
          </span>
        </div>
        <div class="bank-detail">
          <span>IFSC Code</span>
          <span>
            <span style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($btConfig['ifsc']) ?></span>
            <button type="button" class="copy-mini" onclick="copyBankField('<?= htmlspecialchars($btConfig['ifsc'], ENT_QUOTES) ?>', this)">Copy</button>
          </span>
        </div>
        <div class="bank-detail" style="border-bottom:none;"><span>Account Type</span><span><?= htmlspecialchars($btConfig['account_type']) ?></span></div>
      <?php else: ?>
        <p style="color:#e53e3e;text-align:center;">Bank transfer details are being configured. Please use UPI or COD.</p>
      <?php endif; ?>
    </div>

    <?php if (!empty($payment['customer_utr'])): ?>
      <div style="background:#e8f5e9;border-left:4px solid #1a7f4b;border-radius:6px;padding:.75rem 1rem;font-size:.88rem;margin-bottom:1rem;color:#14532d;">
        <i class="fas fa-check-circle"></i> Reference already submitted: <strong style="font-family:monospace;"><?= htmlspecialchars($payment['customer_utr']) ?></strong>
      </div>
    <?php endif; ?>

    <div style="background:#fffde7;border:1px solid #fff59d;border-radius:8px;padding:.8rem;font-size:.88rem;margin-bottom:1.2rem">
      <i class="fas fa-info-circle" style="color:#f9a825;"></i>
      Use NEFT / IMPS. Add your order number <strong>#<?= htmlspecialchars($payment['order_number']) ?></strong> in the transfer remarks.
    </div>

    <form id="btForm" method="post" action="/payment-methods/bank-transfer/submit_reference.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="payment_id" value="<?= htmlspecialchars($paymentId) ?>">
      <input type="hidden" name="order_id" value="<?= $orderId ?>">

      <label for="utr" style="font-weight:600;display:block;margin-bottom:.4rem">
        Your Bank Transaction Reference / UTR
      </label>
      <input type="text" id="utr" name="utr" class="utr-input"
        placeholder="e.g. IMPS419876543210"
        maxlength="30" required autocomplete="off">
      <small style="color:#6b7280;display:block;margin:.4rem 0">6–30 alphanumeric characters from your bank app transaction history</small>

      <div id="formMsg" style="display:none;padding:.7rem 1rem;border-radius:6px;margin-top:.8rem"></div>
      <button type="submit" class="btn-submit">
        <i class="fas fa-paper-plane"></i> Submit &amp; Confirm
      </button>
    </form>
  </div>
  <script>
    function copyBankField(val, btn) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(() => {
          const prev = btn.textContent;
          btn.textContent = 'Copied!';
          setTimeout(() => { btn.textContent = prev; }, 1600);
        }).catch(() => {});
      }
    }

    document.getElementById('btForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.querySelector('.btn-submit');
      const msg = document.getElementById('formMsg');
      const utrVal = document.getElementById('utr').value.trim().toUpperCase();
      if (!/^[A-Z0-9\-\/]{6,30}$/.test(utrVal)) {
        msg.style.cssText = 'display:block;background:#fde;color:#c00;';
        msg.textContent = 'Please enter a valid 6–30 character bank reference number.';
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Submitting…';
      const fd = new FormData(this);
      try {
        const r = await fetch('/payment-methods/bank-transfer/submit_reference.php', {
          method: 'POST',
          body: fd
        });
        const d = await r.json();
        msg.style.cssText = `display:block;background:${d.success?'#dfd':'#fde'};color:${d.success?'#1a7f4b':'#c00'};`;
        msg.textContent = d.message;
        if (d.success) setTimeout(() => window.location.href = d.redirect || '/', 1200);
        else {
          btn.disabled = false;
          btn.textContent = 'Submit & Confirm';
        }
      } catch {
        msg.style.cssText = 'display:block;background:#fde;color:#c00;';
        msg.textContent = 'Network error.';
        btn.disabled = false;
        btn.textContent = 'Submit & Confirm';
      }
    });
  </script>
</body>

</html>
