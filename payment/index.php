<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\PaymentRouter;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/')); exit;
}

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['oid'] ?? 0);

if ($orderId < 1) { header('Location: /'); exit; }

// Load order (server side — verify ownership)
$oStmt = $pdo->prepare(
    "SELECT o.*, COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.id = ? AND (o.user_id = ? OR o.user_id IS NULL)
     GROUP BY o.id LIMIT 1"
);
$oStmt->execute([$orderId, $userId]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) { header('Location: /'); exit; }

// Order items (LEFT JOIN so custom items without product_id are never hidden)
$iStmt = $pdo->prepare(
    "SELECT oi.quantity, oi.price, oi.total,
            COALESCE(NULLIF(p.name, ''), oi.product_name, 'Item') AS name,
            p.unit
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.id"
);
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// Enabled payment methods
$mStmt = $pdo->query(
    "SELECT code, label, description, icon FROM payment_methods WHERE is_enabled=1 ORDER BY display_order"
);
$methods = $mStmt->fetchAll(PDO::FETCH_ASSOC);

$calc         = new AmountCalculator();
$sec          = new SecurityManager();
$router       = new PaymentRouter($pdo);
$csrf         = $sec->generateCSRFToken();
$rawAmount    = (!empty($order['net_amount']) && (float)$order['net_amount'] > 0) ? (float)$order['net_amount'] : (float)($order['total'] ?? 0);
$amountPaise  = $calc->toPaise($rawAmount);
$total        = $calc->formatINR($amountPaise);

$errMap = [
    'csrf'           => 'Your security session expired. Please choose a payment method again.',
    'invalid_method' => 'Please select a valid payment method before continuing.',
    'cod_limit'      => 'Cash on Delivery is available only for orders up to ₹2,000. Please use UPI or Bank Transfer.',
    'create_failed'  => 'Unable to initialize payment. Please try again or pick another method.',
];
$errCode = trim((string)($_GET['err'] ?? ''));
$errText = $errMap[$errCode] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Select Payment — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box}
body{margin:0;background:#f4f7f6;font-family:'Segoe UI',system-ui,sans-serif;color:#1f2937}
.pay-card{max-width:540px;margin:1.25rem auto 2.5rem;padding:1.4rem;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.08);background:#fff}
@media(max-width:560px){.pay-card{margin:.75rem;padding:1.1rem}}
.method-btn{display:flex;align-items:center;gap:1rem;width:100%;min-height:60px;padding:.95rem 1.1rem;margin-bottom:.75rem;border:2px solid #e5e7eb;border-radius:12px;background:#fff;cursor:pointer;font-size:1rem;text-align:left;transition:border-color .18s,box-shadow .18s,background .18s}
.method-btn:hover:not(:disabled),.method-btn.selected{border-color:#1a7f4b;background:#f0fdf6;box-shadow:0 0 0 3px rgba(26,127,75,.14)}
.method-btn:focus-visible{outline:3px solid #1a7f4b;outline-offset:2px}
.method-btn i{font-size:1.35rem;color:#1a7f4b;width:28px;text-align:center;flex-shrink:0}
.method-meta{flex:1;min-width:0}
.method-meta strong{display:block;font-size:.98rem;color:#111827}
.method-meta small{display:block;color:#6b7280;font-size:.8rem;margin-top:2px;line-height:1.35}
.amount-row{display:flex;justify-content:space-between;padding:.35rem 0;font-size:.92rem}
.amount-total{font-size:1.15rem;font-weight:700;border-top:2px solid #e5e7eb;padding-top:.55rem;margin-top:.35rem;color:#111827}
.btn-proceed{width:100%;min-height:50px;padding:.9rem;background:#1a7f4b;color:#fff;font-size:1.05rem;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:.9rem;display:flex;align-items:center;justify-content:center;gap:.5rem}
.btn-proceed:hover:not(:disabled){background:#15653b}
.btn-proceed:disabled{opacity:.45;cursor:not-allowed}
.disabled-method{opacity:.52;cursor:not-allowed;background:#f9fafb}
.err-banner{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.88rem;display:flex;align-items:flex-start;gap:.55rem}
.pill-tag{margin-left:auto;font-size:.72rem;background:#f3f4f6;color:#4b5563;padding:.22rem .55rem;border-radius:6px;white-space:nowrap;font-weight:600}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="pay-card">
  <h2 style="margin:0 0 .25rem;display:flex;align-items:center;gap:.5rem;font-size:1.3rem;">
    <i class="fas fa-lock" style="color:#1a7f4b;"></i> KSR GURU JI PAY
  </h2>
  <p style="color:#6b7280;font-size:.88rem;margin:0 0 1rem;">Order #<?= htmlspecialchars($order['order_number']) ?></p>

  <?php if ($errText !== ''): ?>
    <div class="err-banner" role="alert">
      <i class="fas fa-exclamation-circle" style="margin-top:2px;"></i>
      <span><?= htmlspecialchars($errText) ?></span>
    </div>
  <?php endif; ?>

  <!-- Order Summary -->
  <div style="background:#f9fafb;border:1px solid #f0f0f0;border-radius:10px;padding:.95rem;margin-bottom:1.2rem">
    <?php foreach ($items as $item): ?>
      <div class="amount-row">
        <span><?= htmlspecialchars($item['name']) ?> × <?= (int)$item['quantity'] ?></span>
        <span>₹<?= number_format((float)$item['total'], 2) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($order['delivery_charge']) && (float)$order['delivery_charge'] > 0): ?>
    <div class="amount-row"><span>Delivery</span><span>₹<?= number_format((float)$order['delivery_charge'], 2) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($order['discount']) && (float)$order['discount'] > 0): ?>
    <div class="amount-row" style="color:#1a7f4b"><span>Discount</span><span>− ₹<?= number_format((float)$order['discount'], 2) ?></span></div>
    <?php endif; ?>
    <div class="amount-row amount-total"><span>Total Payable</span><span><?= $total ?></span></div>
  </div>

  <!-- Payment Methods -->
  <p style="font-weight:700;margin:0 0 .75rem;font-size:.95rem;">Select payment method:</p>
  <form id="payForm" method="post" action="/payment/create_payment.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="order_id"   value="<?= $orderId ?>">
    <input type="hidden" name="method"     id="selectedMethod" value="">

    <div role="radiogroup" aria-label="Payment methods">
    <?php foreach ($methods as $m): ?>
      <?php
        $code        = (string)$m['code'];
        $comingSoon  = in_array($code, ['card', 'net_banking', 'wallet'], true);
        $eligibility = $comingSoon ? ['eligible' => false, 'reason' => 'Coming Soon'] : $router->isMethodEligible($code, $amountPaise);
        $isDisabled  = $comingSoon || empty($eligibility['eligible']);
        $tagText     = $comingSoon ? 'Coming Soon' : ($isDisabled ? 'Max ₹2,000' : '');
      ?>
      <button type="button"
              class="method-btn <?= $isDisabled ? 'disabled-method' : '' ?>"
              <?= $isDisabled ? 'disabled aria-disabled="true"' : 'onclick="selectMethod(\'' . htmlspecialchars($code, ENT_QUOTES) . '\', this)"' ?>
              aria-checked="false" role="radio">
        <i class="fas <?= htmlspecialchars($m['icon']) ?>"></i>
        <span class="method-meta">
          <strong><?= htmlspecialchars($m['label']) ?></strong>
          <?php if ($isDisabled && !$comingSoon): ?>
            <small style="color:#b45309;"><?= htmlspecialchars($eligibility['reason']) ?></small>
          <?php elseif (!empty($m['description'])): ?>
            <small><?= htmlspecialchars($m['description']) ?></small>
          <?php endif; ?>
        </span>
        <?php if ($tagText !== ''): ?>
          <span class="pill-tag"><?= htmlspecialchars($tagText) ?></span>
        <?php endif; ?>
      </button>
    <?php endforeach; ?>
    </div>

    <div id="clientErr" class="err-banner" style="display:none;margin-top:.5rem;" role="alert">
      <i class="fas fa-exclamation-triangle"></i>
      <span>Please select a payment method to continue.</span>
    </div>

    <button type="submit" class="btn-proceed" id="proceedBtn" disabled>
      <i class="fas fa-arrow-right"></i> <span>Continue to Pay <?= $total ?></span>
    </button>
  </form>
</div>

<script>
function selectMethod(code, el) {
  document.querySelectorAll('.method-btn:not(.disabled-method)').forEach(b => {
    b.classList.remove('selected');
    b.setAttribute('aria-checked', 'false');
  });
  el.classList.add('selected');
  el.setAttribute('aria-checked', 'true');
  document.getElementById('selectedMethod').value = code;
  document.getElementById('clientErr').style.display = 'none';
  const btn = document.getElementById('proceedBtn');
  btn.disabled = false;
  btn.textContent = '→ Pay via ' + el.querySelector('strong').textContent;
}

document.getElementById('payForm').addEventListener('submit', function(e) {
  if (!document.getElementById('selectedMethod').value) {
    e.preventDefault();
    document.getElementById('clientErr').style.display = 'flex';
  }
});
</script>
</body>
</html>
