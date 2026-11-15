<?php
// ============================================================
// delivery/navigation.php — Delivery Address & Navigation
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('navigation.php');

$orderId = (int)($_GET['order'] ?? 0);
$order   = null;

if ($orderId > 0) {
    try {
        $stmt = delivery_db()->prepare("SELECT id, order_number, name, phone, address, city, state, pincode FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// Build maps URL
$mapsUrl = '';
if ($order) {
    $addr = trim(implode(', ', array_filter([
        $order['address'] ?? '',
        $order['city']    ?? '',
        $order['state']   ?? '',
        $order['pincode'] ?? '',
    ])));
    $mapsUrl = $addr ? 'https://maps.google.com/?q=' . urlencode($addr) : '';
}

delivery_layout('Navigation', 'navigation', function () use ($order, $mapsUrl) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-map-location-dot"></i> Delivery Navigation</h3>
        </div>

        <?php if (!$order): ?>
        <div class="dp-sec" style="padding:16px;">
            <p class="dp-muted">Enter an order ID to open navigation.</p>
            <form method="GET" style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
                <input type="number" name="order" class="dp-input" placeholder="Order ID" style="max-width:180px;" min="1">
                <button type="submit" class="dp-btn dp-btn-primary"><i class="fa-solid fa-route"></i> Navigate</button>
            </form>
        </div>
        <?php else: ?>
        <div class="dp-sec" style="padding:16px;">
            <div style="margin-bottom:14px;">
                <strong><?= htmlspecialchars($order['name'] ?? '—', ENT_QUOTES) ?></strong>
                <span class="dp-muted"> · Order <?= htmlspecialchars($order['order_number'] ?: '#'.$order['id'], ENT_QUOTES) ?></span>
            </div>
            <div class="dp-muted" style="display:flex;gap:6px;margin-bottom:16px;align-items:flex-start;">
                <i class="fa-solid fa-map-pin" style="margin-top:3px;color:#0f7b3a;"></i>
                <span><?= htmlspecialchars(trim(implode(', ', array_filter([
                    $order['address'] ?? '',
                    $order['city']    ?? '',
                    $order['state']   ?? '',
                    $order['pincode'] ?? '',
                ]))), ENT_QUOTES) ?></span>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($mapsUrl): ?>
                <a href="<?= htmlspecialchars($mapsUrl, ENT_QUOTES) ?>" target="_blank" class="dp-btn dp-btn-primary">
                    <i class="fa-brands fa-google"></i> Google Maps
                </a>
                <a href="https://waze.com/ul?q=<?= urlencode($order['address'] ?? '') ?>" target="_blank" class="dp-btn" style="background:#27BBFE;color:#fff;">
                    <i class="fa-solid fa-waze"></i> Waze
                </a>
                <?php endif; ?>
                <?php if (!empty($order['phone'])): ?>
                <a href="tel:<?= htmlspecialchars($order['phone'], ENT_QUOTES) ?>" class="dp-btn" style="background:#e8f5e9;color:#0f7b3a;border:1px solid #c8e6c9;">
                    <i class="fa-solid fa-phone"></i> Call Customer
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Active orders needing delivery -->
    <section class="dp-card">
        <div class="dp-card-h"><h3><i class="fa-solid fa-list-check"></i> My Active Orders</h3></div>
        <div id="activeNavOrders" class="dp-list">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>
<script src="js/delivery-common.js?v=1"></script>
<script>
// Load active orders
fetch('api.php?action=orders&scope=mine&status=accepted,picked,out')
    .then(r => r.json())
    .then(data => {
        const list = document.getElementById('activeNavOrders');
        const orders = data.orders || data;
        if (!orders || !orders.length) {
            list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No active orders.</div>';
            return;
        }
        list.innerHTML = orders.map(o => `
            <div class="dp-item">
                <div style="flex:1;">
                    <strong>${o.order_number || '#' + o.id}</strong>
                    <span class="dp-muted"> · ${o.name || '—'}</span><br>
                    <small class="dp-muted"><i class="fa-solid fa-map-pin"></i> ${(o.address || '—').substring(0,55)}</small>
                </div>
                <a href="navigation.php?order=${o.id}" class="dp-btn dp-btn-primary" style="font-size:0.78rem;padding:6px 12px;">
                    <i class="fa-solid fa-route"></i> Navigate
                </a>
            </div>
        `).join('');
    })
    .catch(() => {
        document.getElementById('activeNavOrders').innerHTML = '<div class="dp-empty">Could not load orders.</div>';
    });
</script>
<?php
});
?>
