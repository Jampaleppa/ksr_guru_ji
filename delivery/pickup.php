<?php
// ============================================================
// delivery/pickup.php — Pickup Orders (ready to collect from shop)
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('pickup.php');

$deliveryBoyId = (int)($_SESSION['delivery_boy_id'] ?? 0);

delivery_layout('Pickup Orders', 'orders', function () use ($deliveryBoyId) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-box-open"></i> Pickup Orders</h3>
            <span class="dp-muted" id="listCount"></span>
        </div>
        <div class="dp-sec">
            <p class="dp-muted" style="font-size:13px;">Orders packed and ready — go to shop to collect these</p>
        </div>
    </section>
    <section class="dp-card">
        <div id="orderList" class="dp-list">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
const deliveryBoyId = <?= json_encode($deliveryBoyId) ?>;

function loadOrders() {
    fetch(`../api/delivery_orders.php?status=picked_up&delivery_boy_id=${deliveryBoyId}`)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('orderList');
            const orders = data.orders ?? [];
            document.getElementById('listCount').textContent = orders.length + ' order(s)';
            if (!orders.length) {
                list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No pickup orders right now</div>';
                return;
            }
            list.innerHTML = orders.map(o => `
                <div class="dp-order-card">
                    <div class="dp-order-head">
                        <strong>#${o.order_number ?? o.id}</strong>
                        <span class="dp-badge dp-badge-info">Pickup</span>
                    </div>
                    <div class="dp-order-body">
                        <p><i class="fa-solid fa-user"></i> ${o.customer_name ?? 'Customer'}</p>
                        <p><i class="fa-solid fa-location-dot"></i> ${o.delivery_address ?? 'N/A'}</p>
                        <p><i class="fa-solid fa-phone"></i> <a href="tel:${o.customer_phone ?? ''}">${o.customer_phone ?? ''}</a></p>
                        <p><i class="fa-solid fa-indian-rupee-sign"></i> ₹${parseFloat(o.total_amount ?? 0).toFixed(2)}</p>
                        <p><i class="fa-solid fa-store"></i> ${o.shop_address ?? 'KSR Main Store'}</p>
                    </div>
                    <div class="dp-order-actions">
                        <a href="tel:${o.customer_phone ?? ''}" class="dp-btn dp-btn-sm dp-btn-secondary">
                            <i class="fa-solid fa-phone"></i> Call Customer
                        </a>
                        <button class="dp-btn dp-btn-sm dp-btn-primary" onclick="markOutForDelivery(${o.id})">
                            <i class="fa-solid fa-truck"></i> Out for Delivery
                        </button>
                    </div>
                </div>`).join('');
        })
        .catch(() => {
            document.getElementById('orderList').innerHTML =
                '<div class="dp-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load orders</div>';
        });
}

function markOutForDelivery(id) {
    if (!confirm('Confirm order picked up and out for delivery?')) return;
    fetch('../api/delivery_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: id, status: 'out_for_delivery' })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { loadOrders(); } else { alert(d.message ?? 'Error updating order'); } });
}

loadOrders();
setInterval(loadOrders, 30000);
</script>
