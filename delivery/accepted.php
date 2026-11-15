<?php
// ============================================================
// delivery/accepted.php — Accepted Orders (pending pickup)
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('accepted.php');

$deliveryBoyId = (int)($_SESSION['delivery_boy_id'] ?? 0);

delivery_layout('Accepted Orders', 'orders', function () use ($deliveryBoyId) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-check-circle"></i> Accepted Orders</h3>
            <span class="dp-muted" id="listCount"></span>
        </div>
        <div class="dp-sec">
            <p class="dp-muted" style="font-size:13px;">Orders you accepted — pending pickup from shop</p>
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
    fetch(`../api/delivery_orders.php?status=accepted&delivery_boy_id=${deliveryBoyId}`)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('orderList');
            const orders = data.orders ?? [];
            document.getElementById('listCount').textContent = orders.length + ' order(s)';
            if (!orders.length) {
                list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No accepted orders right now</div>';
                return;
            }
            list.innerHTML = orders.map(o => `
                <div class="dp-order-card">
                    <div class="dp-order-head">
                        <strong>#${o.order_number ?? o.id}</strong>
                        <span class="dp-badge dp-badge-warning">Accepted</span>
                    </div>
                    <div class="dp-order-body">
                        <p><i class="fa-solid fa-user"></i> ${o.customer_name ?? 'Customer'}</p>
                        <p><i class="fa-solid fa-location-dot"></i> ${o.delivery_address ?? 'N/A'}</p>
                        <p><i class="fa-solid fa-phone"></i> <a href="tel:${o.customer_phone ?? ''}">${o.customer_phone ?? ''}</a></p>
                        <p><i class="fa-solid fa-indian-rupee-sign"></i> ₹${parseFloat(o.total_amount ?? 0).toFixed(2)}</p>
                    </div>
                    <div class="dp-order-actions">
                        <a href="tel:${o.customer_phone ?? ''}" class="dp-btn dp-btn-sm dp-btn-secondary">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                        <button class="dp-btn dp-btn-sm dp-btn-primary" onclick="markPickup(${o.id})">
                            <i class="fa-solid fa-box-open"></i> Mark Picked Up
                        </button>
                    </div>
                </div>`).join('');
        })
        .catch(() => {
            document.getElementById('orderList').innerHTML =
                '<div class="dp-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load orders</div>';
        });
}

function markPickup(id) {
    if (!confirm('Mark this order as Picked Up from shop?')) return;
    fetch('../api/delivery_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: id, status: 'picked_up' })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { loadOrders(); } else { alert(d.message ?? 'Error updating order'); } });
}

loadOrders();
setInterval(loadOrders, 30000);
</script>
