<?php
// ============================================================
// delivery/out-for-delivery.php — Orders currently being delivered
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('out-for-delivery.php');

$deliveryBoyId = (int)($_SESSION['delivery_boy_id'] ?? 0);

delivery_layout('Out for Delivery', 'orders', function () use ($deliveryBoyId) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-truck"></i> Out for Delivery</h3>
            <span class="dp-muted" id="listCount"></span>
        </div>
        <div class="dp-sec">
            <p class="dp-muted" style="font-size:13px;">Orders currently on the way — tap Delivered when done</p>
        </div>
    </section>
    <section class="dp-card">
        <div id="orderList" class="dp-list">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>

    <!-- Failure reason modal -->
    <div id="failModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center;">
        <div class="dp-card" style="width:min(360px,94vw);margin:0">
            <div class="dp-card-h"><h3><i class="fa-solid fa-ban"></i> Report Failed Delivery</h3></div>
            <div class="dp-sec">
                <label class="dp-label">Reason for failure</label>
                <select id="failReason" class="dp-input">
                    <option value="customer_not_home">Customer not home</option>
                    <option value="wrong_address">Wrong address</option>
                    <option value="customer_refused">Customer refused</option>
                    <option value="payment_issue">Payment issue</option>
                    <option value="other">Other</option>
                </select>
                <textarea id="failNote" class="dp-input" style="margin-top:8px" placeholder="Additional notes…" rows="3"></textarea>
            </div>
            <div class="dp-order-actions">
                <button class="dp-btn dp-btn-secondary" onclick="closeFailModal()">Cancel</button>
                <button class="dp-btn dp-btn-danger" onclick="confirmFail()">Report Failed</button>
            </div>
        </div>
    </div>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
const deliveryBoyId = <?= json_encode($deliveryBoyId) ?>;
let currentFailOrderId = null;

function loadOrders() {
    fetch(`../api/delivery_orders.php?status=out_for_delivery&delivery_boy_id=${deliveryBoyId}`)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('orderList');
            const orders = data.orders ?? [];
            document.getElementById('listCount').textContent = orders.length + ' order(s)';
            if (!orders.length) {
                list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-truck"></i> No active deliveries</div>';
                return;
            }
            list.innerHTML = orders.map(o => `
                <div class="dp-order-card">
                    <div class="dp-order-head">
                        <strong>#${o.order_number ?? o.id}</strong>
                        <span class="dp-badge dp-badge-primary">Out for Delivery</span>
                    </div>
                    <div class="dp-order-body">
                        <p><i class="fa-solid fa-user"></i> ${o.customer_name ?? 'Customer'}</p>
                        <p><i class="fa-solid fa-location-dot"></i> ${o.delivery_address ?? 'N/A'}</p>
                        <p><i class="fa-solid fa-phone"></i> <a href="tel:${o.customer_phone ?? ''}">${o.customer_phone ?? ''}</a></p>
                        <p><i class="fa-solid fa-indian-rupee-sign"></i> ₹${parseFloat(o.total_amount ?? 0).toFixed(2)}</p>
                    </div>
                    <div class="dp-order-actions">
                        <a href="https://maps.google.com/?q=${encodeURIComponent(o.delivery_address ?? '')}"
                           target="_blank" class="dp-btn dp-btn-sm dp-btn-secondary">
                            <i class="fa-solid fa-map-location-dot"></i> Map
                        </a>
                        <a href="tel:${o.customer_phone ?? ''}" class="dp-btn dp-btn-sm dp-btn-secondary">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                        <button class="dp-btn dp-btn-sm dp-btn-danger" onclick="openFailModal(${o.id})">
                            <i class="fa-solid fa-ban"></i> Failed
                        </button>
                        <button class="dp-btn dp-btn-sm dp-btn-success" onclick="markDelivered(${o.id})">
                            <i class="fa-solid fa-check"></i> Delivered
                        </button>
                    </div>
                </div>`).join('');
        })
        .catch(() => {
            document.getElementById('orderList').innerHTML =
                '<div class="dp-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load orders</div>';
        });
}

function markDelivered(id) {
    if (!confirm('Confirm order delivered successfully?')) return;
    fetch('../api/delivery_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: id, status: 'delivered' })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { loadOrders(); } else { alert(d.message ?? 'Error'); } });
}

function openFailModal(id) {
    currentFailOrderId = id;
    document.getElementById('failModal').style.display = 'flex';
}
function closeFailModal() {
    document.getElementById('failModal').style.display = 'none';
    currentFailOrderId = null;
}
function confirmFail() {
    if (!currentFailOrderId) return;
    const reason = document.getElementById('failReason').value;
    const note   = document.getElementById('failNote').value;
    fetch('../api/delivery_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: currentFailOrderId, status: 'failed', reason, note })
    })
    .then(r => r.json())
    .then(d => { closeFailModal(); if (d.success) { loadOrders(); } else { alert(d.message ?? 'Error'); } });
}

loadOrders();
setInterval(loadOrders, 30000);
</script>
