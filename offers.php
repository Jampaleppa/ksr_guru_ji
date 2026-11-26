<?php
// ============================================================
// offers.php — Customer Offers & Active Coupons Showcase
// Lists valid active coupons; lets customers apply one to cart
// in 1 click via /api/coupons/validate.php.
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/coupons.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();
ensureCouponTables($pdo);

$allCoupons = [];
try {
    $allCoupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignore) {
}

$now = time();
$activeOffers = array_values(array_filter($allCoupons, function (array $c) use ($now): bool {
    $rawStatus = strtolower(trim((string)($c['status'] ?? ($c['is_active'] ?? '1'))));
    if (!in_array($rawStatus, ['active', '1', 'true', 'yes'], true)) {
        return false;
    }
    $endRaw = trim((string)($c['end_date'] ?? $c['expiry_date'] ?? $c['expires_at'] ?? ''));
    if ($endRaw !== '' && $endRaw !== '0000-00-00' && $endRaw !== '0000-00-00 00:00:00') {
        $endTs = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw) ? strtotime($endRaw . ' 23:59:59') : strtotime($endRaw);
        if ($endTs !== false && $endTs < $now) {
            return false;
        }
    }
    $limit = (int)($c['usage_limit'] ?? 0);
    if ($limit > 0 && (int)($c['used_count'] ?? 0) >= $limit) {
        return false;
    }
    return true;
}));

$cartSubtotal = 0.0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $ci) {
        $cartSubtotal += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? $ci['quantity'] ?? 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Offers &amp; Coupons – KSR GURU JI</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f7b3a">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --green:#0f7b3a; --amber:#f59e0b; --gray:#f7f8fa; --radius:14px; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',sans-serif; background:var(--gray); color:#1e293b; min-height:100vh; }
  header { background:var(--green); color:#fff; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
  header h1 { font-size:1.1rem; }
  .container { max-width:820px; margin:22px auto; padding:0 16px 90px; }
  .offer-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
  .offer-card { background:#fff; border-radius:var(--radius); border:1.5px dashed #86efac; padding:18px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; flex-direction:column; gap:10px; }
  .code-pill { font-family:monospace; font-size:1.1rem; font-weight:800; color:var(--green); background:#f0fdf4; border:1px solid #bbf7d0; padding:6px 12px; border-radius:8px; display:inline-block; letter-spacing:1px; }
  .badge { font-size:.76rem; font-weight:700; background:#fef3c7; color:#92400e; padding:3px 9px; border-radius:20px; }
  .btn { padding:10px 14px; border:none; border-radius:10px; font-weight:700; cursor:pointer; font-size:.88rem; }
  .btn-apply { background:var(--green); color:#fff; width:100%; }
  .empty { background:#fff; border-radius:var(--radius); padding:40px 20px; text-align:center; color:#64748b; }
</style>
</head>
<body>
<header>
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/index.php" style="color:#fff;font-size:1.2rem;"><i class="fas fa-arrow-left"></i></a>
    <h1>🎟️ Offers &amp; Promo Coupons</h1>
  </div>
  <a href="/cart.php" style="color:#fff;text-decoration:none;font-weight:700;font-size:.9rem;"><i class="fas fa-shopping-cart"></i> Cart</a>
</header>
<div class="container">
  <div id="offer-msg" style="display:none;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.9rem;"></div>

  <?php if (empty($activeOffers)): ?>
    <div class="empty">
      <i class="fas fa-tags" style="font-size:2.5rem;color:#cbd5e1;margin-bottom:12px;display:block;"></i>
      <p>No promo coupons active right now. Check back soon or explore daily fresh deals on the store!</p>
      <a href="/index.php" class="btn btn-apply" style="display:inline-block;width:auto;margin-top:16px;text-decoration:none;">Shop Now</a>
    </div>
  <?php else: ?>
    <div class="offer-grid">
      <?php foreach ($activeOffers as $c):
          $dtype = strtolower((string)($c['discount_type'] ?? 'fixed'));
          $dval  = (float)($c['discount_value'] ?? 0);
          $minO  = (float)($c['min_order_amount'] ?? $c['min_order'] ?? 0);
          $endR  = (string)($c['end_date'] ?? $c['expiry_date'] ?? '');
          $valText = in_array($dtype, ['percent', 'percentage'], true)
              ? rtrim(rtrim(number_format($dval, 2, '.', ''), '0'), '.') . '% OFF'
              : '₹' . number_format($dval, 0) . ' OFF';
      ?>
      <div class="offer-card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span class="code-pill"><?= htmlspecialchars((string)$c['code']) ?></span>
          <span class="badge"><?= htmlspecialchars($valText) ?></span>
        </div>
        <p style="font-size:.9rem;color:#334155;"><?= htmlspecialchars((string)($c['description'] ?: 'Special store discount coupon')) ?></p>
        <div style="font-size:.8rem;color:#64748b;">
          <?php if ($minO > 0): ?>• Min order: ₹<?= number_format($minO, 0) ?><br><?php endif; ?>
          <?php if ($endR !== '' && $endR !== '0000-00-00'): ?>• Valid till: <?= date('d M Y', strtotime($endR)) ?><?php endif; ?>
        </div>
        <button class="btn btn-apply" onclick="applyOffer('<?= htmlspecialchars((string)$c['code'], ENT_QUOTES) ?>')">
          <i class="fas fa-check-circle"></i> Apply &amp; Go to Cart
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<script>
const cartSubtotal = <?= json_encode($cartSubtotal) ?>;
async function applyOffer(code) {
  const msgEl = document.getElementById('offer-msg');
  try {
    await navigator.clipboard.writeText(code);
  } catch (_) {}
  if (cartSubtotal <= 0) {
    msgEl.style.display = 'block';
    msgEl.style.background = '#e8f5ee';
    msgEl.style.color = '#0f7b3a';
    msgEl.textContent = 'Coupon code ' + code + ' copied! Add items to your cart to apply it.';
    return;
  }
  const res = await fetch('/api/coupons/validate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ code, subtotal: cartSubtotal })
  });
  const data = await res.json();
  if (data.success) {
    window.location.href = '/cart.php';
  } else {
    msgEl.style.display = 'block';
    msgEl.style.background = '#fff0f0';
    msgEl.style.color = '#b91c1c';
    msgEl.textContent = data.error || 'Could not apply coupon.';
  }
}
</script>
</body>
</html>
