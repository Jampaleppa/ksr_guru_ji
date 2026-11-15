<?php
// ============================================================
// delivery/contact.php — Customer Contact & WhatsApp
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('contact.php');

// Look up a specific order for contact info
$orderId = (int)($_GET['order'] ?? 0);
$order   = null;

if ($orderId > 0) {
    try {
        $stmt = delivery_db()->prepare("SELECT id, order_number, name, phone, address FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

delivery_layout('Customer Contact', 'contact', function () use ($order) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-address-book"></i> Customer Contact</h3>
        </div>
        <?php if ($order): ?>
        <div class="dp-sec" style="padding:16px;">
            <div class="dp-item" style="flex-direction:column;align-items:flex-start;gap:10px;">
                <div>
                    <strong style="font-size:1.05rem;"><?= htmlspecialchars($order['name'] ?? '—', ENT_QUOTES) ?></strong>
                    <span class="dp-muted"> — Order <?= htmlspecialchars($order['order_number'] ?: '#'.$order['id'], ENT_QUOTES) ?></span>
                </div>
                <div class="dp-muted" style="display:flex;gap:6px;align-items:flex-start;">
                    <i class="fa-solid fa-map-pin" style="margin-top:3px;color:#0f7b3a;"></i>
                    <span><?= htmlspecialchars($order['address'] ?? '—', ENT_QUOTES) ?></span>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php if (!empty($order['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($order['phone'], ENT_QUOTES) ?>" class="dp-btn dp-btn-primary" style="flex:1;min-width:140px;text-align:center;">
                        <i class="fa-solid fa-phone"></i> Call Customer
                    </a>
                    <a href="https://wa.me/91<?= preg_replace('/\D/', '', $order['phone']) ?>?text=<?= urlencode('Hello, I am KSR GURU JI delivery agent. I am on my way to deliver Order ' . ($order['order_number'] ?: '#'.$order['id']) . '.') ?>"
                       target="_blank" class="dp-btn" style="background:#25D366;color:#fff;flex:1;min-width:140px;text-align:center;">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                    <?php else: ?>
                    <span class="dp-muted">No phone number on record.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="dp-sec" style="padding:16px;">
            <p class="dp-muted">Select an order to view customer contact details.</p>
            <form method="GET" style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
                <input type="number" name="order" class="dp-input" placeholder="Enter Order ID" style="max-width:180px;" min="1">
                <button type="submit" class="dp-btn dp-btn-primary"><i class="fa-solid fa-search"></i> Find</button>
            </form>
        </div>
        <?php endif; ?>
    </section>

    <section class="dp-card">
        <div class="dp-card-h"><h3><i class="fa-solid fa-headset"></i> KSR GURU JI Support</h3></div>
        <div class="dp-sec" style="padding:16px;">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="tel:8088584421" class="dp-btn dp-btn-primary" style="max-width:220px;">
                    <i class="fa-solid fa-phone"></i> Call Office: 8088584421
                </a>
                <a href="https://wa.me/918088584421" target="_blank" class="dp-btn" style="background:#25D366;color:#fff;max-width:220px;">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp Office
                </a>
            </div>
        </div>
    </section>
<?php
});
?>
