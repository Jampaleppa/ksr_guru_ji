<?php
// ============================================================
// delivery/order.php — full order detail + status actions + WhatsApp
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('order.php?id=' . (int) ($_GET['id'] ?? 0));

$orderId = (int) ($_GET['id'] ?? 0);
$stmt = delivery_db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$items = delivery_db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$orderId]);
$items = $items->fetchAll();

$timeline = delivery_db()->prepare('SELECT status, notes, created_at FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC');
$timeline->execute([$orderId]);
$timeline = $timeline->fetchAll();

$payment = delivery_db()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
$payment->execute([$orderId]);
$payment = $payment->fetch() ?: null;

$isMine = (int) ($order['driver_id'] ?: 0) === delivery_id() || (int) ($order['delivery_boy_id'] ?: 0) === delivery_id();
$isOpen = !$order['driver_id'] && !$order['delivery_boy_id'];
$isDone = in_array($order['status'], ['delivered', 'cancelled'], true);

delivery_layout('Order ' . $order['order_number'], 'orders', function () use ($order, $items, $timeline, $payment, $isMine, $isOpen, $isDone) {
    $waMsg = "Hi " . $order['name'] . ", your KSR GURU JI order " . $order['order_number']
        . " is " . delivery_status_meta($order['status'])['label'] . ".";
?>
    <a href="orders.php" class="dp-link" style="font-weight:800"><i class="fa-solid fa-arrow-left"></i> Back to orders</a>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-receipt"></i> <?= e($order['order_number']) ?></h3>
            <?= delivery_status_badge($order['status']) ?>
        </div>
        <div class="dp-sec" style="padding-top:12px">
            <div class="dp-kv"><span>Customer</span><b><?= e($order['name']) ?></b></div>
            <div class="dp-kv"><span>Phone</span>
                <b><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></b>
            </div>
            <div class="dp-kv"><span>Address</span>
                <b><?= e(trim(($order['address'] ?? '') . ', ' . ($order['city'] ?? '') . ' ' . ($order['pincode'] ?? ''))) ?></b>
            </div>
            <div class="dp-kv"><span>Placed</span><b><?= e(date('d M Y, h:i A', strtotime($order['placed_at'] ?: $order['created_at']))) ?></b></div>
            <div class="dp-kv"><span>Payment</span>
                <b><?= e(strtoupper($order['payment_method'] ?: '-')) ?>
                    <?= $order['payment_method'] === 'cod' ? '<span class="ds-badge ds-warn">Collect ' . e(delivery_money($order['total'])) . '</span>' : '' ?>
                </b>
            </div>
            <div class="dp-kv"><span>Agent</span><b><?= e($order['driver_id'] ? ($order['agent_name'] ?? 'Assigned') : 'Unassigned') ?></b></div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-boxes-stacked"></i> Items</h3>
        </div>
        <div class="dp-list">
            <?php foreach ($items as $it): ?>
                <div class="dp-row">
                    <div class="dp-row-l">
                        <b><?= e($it['product_name']) ?></b>
                        <small>Qty <?= (int) $it['quantity'] ?> × <?= e(delivery_money($it['price'])) ?></small>
                    </div>
                    <div class="dp-row-r"><b><?= e(delivery_money($it['total'] ?? $it['price'] * $it['quantity'])) ?></b></div>
                </div>
            <?php endforeach; ?>
            <div class="dp-row" style="background:var(--p-soft)">
                <div class="dp-row-l"><b>Order total</b></div>
                <div class="dp-row-r"><b><?= e(delivery_money($order['total'])) ?></b></div>
            </div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-bolt"></i> Update status</h3>
        </div>
        <div class="dp-actions" id="statusActions">
            <?php if ($isDone): ?>
                <p class="dp-muted" style="padding:0 16px 14px">
                    This order is <?= e(strtolower(delivery_status_meta($order['status'])['label'])) ?>.
                </p>
            <?php elseif ($isOpen): ?>
                <button class="dp-btn dp-btn-primary dp-btn-block" onclick="update('accept')">
                    <i class="fa-solid fa-hand"></i> Accept this order
                </button>
            <?php else: ?>
                <?php
                $steps = [
                    'pending'    => ['accept', 'Accept'],
                    'accepted'   => ['status|picked_up', 'Mark Picked Up'],
                    'picked_up'  => ['status|in_transit', 'Start Delivery'],
                    'in_transit' => ['status|delivered', 'Mark Delivered'],
                ];
                $next = $steps[$order['status']] ?? null;
                if ($next): ?>
                    <button class="dp-btn dp-btn-primary dp-btn-block" onclick="update('<?= $next[0] ?>')">
                        <i class="fa-solid fa-arrow-right"></i> <?= e($next[1]) ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="dp-actions" style="padding-top:0">
            <?php if (!empty($order['phone'])): ?>
                <a class="dp-btn dp-btn-wa" target="_blank" rel="noopener" href="<?= e(delivery_wa_link((string) $order['phone'], $waMsg)) ?>">
                    <i class="fa-brands fa-whatsapp"></i> Message customer
                </a>
            <?php endif; ?>
            <?php foreach (DELIVERY_ROSTER as $b): if (strcasecmp($b['name'], delivery_name()) === 0) continue; ?>
                <a class="dp-btn dp-btn-soft" target="_blank" rel="noopener"
                    href="<?= e(delivery_wa_link($b['phone'], 'Order ' . $order['order_number'] . ' (KSR GURU JI) — please take note.')) ?>">
                    <i class="fa-brands fa-whatsapp"></i> Notify <?= e($b['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-timeline"></i> Timeline</h3>
        </div>
        <div class="dp-tl">
            <?php if (!$timeline): ?>
                <div class="dp-empty">No timeline events yet</div>
            <?php endif; ?>
            <?php foreach ($timeline as $t): $m = delivery_status_meta($t['status']); ?>
                <div class="dp-tl-i on">
                    <div class="dp-tl-dot"><i class="fa-solid <?= e($m['icon']) ?>"></i></div>
                    <div class="dp-tl-b">
                        <b><?= e($m['label']) ?></b>
                        <small><?= e($t['notes'] ?: '—') ?> · <?= e(delivery_time_ago($t['created_at'])) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    const ORDER_ID = <?= (int) $orderId ?>;
    async function update(action) {
        let status = '';
        if (action.startsWith('status|')) status = action.split('|')[1];
        if (status === 'delivered' && !confirm('Confirm the order has been handed over to the customer?')) return;
        const j = await dpApi.post(action, {
            id: ORDER_ID,
            status
        });
        if (j && j.success) {
            dpToast(j.message || 'Updated');
            setTimeout(() => location.reload(), 700);
        } else dpToast((j && j.error) || 'Failed', 'bad');
    }
    dpPoll(async () => {
        const j = await dpApi.get('order', {
            id: ORDER_ID
        });
        if (j && j.success && j.order.status !== <?= json_encode($order['status']) ?>) location.reload();
    }, 20000);
</script>