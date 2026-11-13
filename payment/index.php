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

// Order items
$iStmt = $pdo->prepare(
    "SELECT oi.quantity, oi.price, oi.total, p.name, p.unit
     FROM order_items oi JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.id"
);
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// Enabled payment methods
$mStmt = $pdo->query(
    "SELECT code, label, description, icon FROM payment_methods WHERE is_enabled=1 ORDER BY display_order"
);
$methods = $mStmt->fetchAll(PDO::FETCH_ASSOC);

$calc  = new AmountCalculator();
$sec   = new SecurityManager();
$csrf  = $sec->generateCSRFToken();
$total = $calc->formatINR($calc->toPaise((float)$order['net_amount']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Select Payment — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.pay-card{max-width:540px;margin:2rem auto;padding:1.5rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);background:#fff}
.method-btn{display:flex;align-items:center;gap:1rem;width:100%;padding:1rem 1.2rem;margin-bottom:.8rem;border:2px solid #e0e0e0;border-radius:10px;background:#fff;cursor:pointer;font-size:1rem;transition:border-color .2s,box-shadow .2s}
.method-btn:hover,.method-btn.selected{border-color:#1a7f4b;box-shadow:0 0 0 3px rgba(26,127,75,.15)}
.method-btn i{font-size:1.4rem;color:#1a7f4b;width:30px}
.amount-row{display:flex;justify-content:space-between;padding:.35rem 0;font-size:.95rem}
.amount-total{font-size:1.2rem;font-weight:700;border-top:2px solid #eee;padding-top:.5rem;margin-top:.3rem}
.btn-proceed{width:100%;padding:.85rem;background:#1a7f4b;color:#fff;font-size:1.1rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:1rem}
.btn-proceed:disabled{opacity:.5;cursor:not-allowed}
.disabled-method{opacity:.45;cursor:not-allowed}
.disabled-method:hover{border-color:#e0e0e0;box-shadow:none}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="pay-card">
  <h2><i class="fas fa-lock" style="color:#1a7f4b;"></i> KSR GURU JI PAY</h2>
  <p style="color:#888;font-size:.9rem;">Order #<?= htmlspecialchars($order['order_number']) ?></p>

  <!-- Order Summary -->
  <div style="background:#f9f9f9;border-radius:8px;padding:1rem;margin-bottom:1.2rem">
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
    <div class="amount-row amount-total"><span>Total</span><span><?= $total ?></span></div>
  </div>

  <!-- Payment Methods -->
  <p style="font-weight:600;margin-bottom:.8rem;">Select payment method:</p>
  <form id="payForm" method="post" action="/payment/create_payment.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="order_id"   value="<?= $orderId ?>">
    <input type="hidden" name="method"     id="selectedMethod" value="">

    <?php foreach ($methods as $m): ?>
      <?php $disabled = (in_array($m['code'], ['card','net_banking','wallet'], true)) ? 'disabled-method' : ''; ?>
      <div class="method-btn <?= $disabled ?>"
           <?= !$disabled ? 'onclick="selectMethod(\'' . htmlspecialchars($m['code']) . '\', this)"' : '' ?>
           role="<?= !$disabled ? 'button' : 'presentation' ?>">
        <i class="fas <?= htmlspecialchars($m['icon']) ?>"></i>
        <div>
          <strong><?= htmlspecialchars($m['label']) ?></strong>
          <?php if ($m['description']): ?>
            <small style="display:block;color:#888;font-size:.82rem;"><?= htmlspecialchars($m['description']) ?></small>
          <?php endif; ?>
        </div>
        <?php if ($disabled): ?>
          <span style="margin-left:auto;font-size:.75rem;background:#f0f0f0;padding:.2rem .5rem;border-radius:4px;">Coming Soon</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn-proceed" id="proceedBtn" disabled>
      <i class="fas fa-arrow-right"></i> Continue to Pay
    </button>
  </form>
</div>

<script>
function selectMethod(code, el) {
  document.querySelectorAll('.method-btn:not(.disabled-method)').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('selectedMethod').value = code;
  document.getElementById('proceedBtn').disabled = false;
  document.getElementById('proceedBtn').textContent = '→ Pay via ' + el.querySelector('strong').textContent;
}

document.getElementById('payForm').addEventListener('submit', function(e) {
  if (!document.getElementById('selectedMethod').value) {
    e.preventDefault();
    alert('Please select a payment method.');
  }
});
</script>
</body>
</html>

