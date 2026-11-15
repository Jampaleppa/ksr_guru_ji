<?php
// ============================================================
// delivery/cancelled.php — Failed / Cancelled Deliveries
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('cancelled.php');

$deliveryBoyId = (int)($_SESSION['delivery_boy_id'] ?? 0);

delivery_layout('Failed / Cancelled', 'orders', function () use ($deliveryBoyId) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-ban"></i> Failed / Cancelled Deliveries</h3>
            <span class="dp-muted" id="listCount"></span>
        </div>
        <div class="dp-sec">
            <div class="dp-chips" id="statusChips">
                <button class="dp-chip-btn on" data-status="failed">Failed</button>
                <button class="dp-chip-btn" data-status="cancelled">Cancelled</button>
                <button class="dp-chip-btn" data-status="all">All</button>
            </div>
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
let currentStatus = 'failed';

function loadOrders() {
    const url = currentStatus === 'all'
        ? `../api/delivery_orders.php?status=failed,cancelled&delivery_boy_id=${deliveryBoyId}`
        : `../api/delivery_orders.php?status=${currentStatus}&delivery_boy_id=${deliveryBoyId}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('orderList');
            const orders = data.orders ?? [];
            document.getElementById('listCount').textContent = orders.length + ' order(s)';
            if (!orders.length) {
                list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-ban"></i> No failed/cancelled orders</div>';
                return;
            }
            list.innerHTML = orders.map(o => `
                <div class="dp-order-card">
                    <div class="dp-order-head">
                        <strong>#${o.order_number ?? o.id}</strong>
                        <span class="dp-badge dp-badge-danger">${o.status ?? 'Failed'}</span>
                    </div>
                    <div class="dp-order-body">
                        <p><i class="fa-solid fa-user"></i> ${o.customer_name ?? 'Customer'}</p>
                        <p><i class="fa-solid fa-location-dot"></i> ${o.delivery_address ?? 'N/A'}</p>
                        <p><i class="fa-solid fa-phone"></i> <a href="tel:${o.customer_phone ?? ''}">${o.customer_phone ?? ''}</a></p>
                        <p><i class="fa-solid fa-indian-rupee-sign"></i> ₹${parseFloat(o.total_amount ?? 0).toFixed(2)}</p>
                        ${o.failure_reason ? `<p><i class="fa-solid fa-circle-info"></i> <em>${o.failure_reason}</em></p>` : ''}
                    </div>
                    <div class="dp-order-actions">
                        <a href="tel:${o.customer_phone ?? ''}" class="dp-btn dp-btn-sm dp-btn-secondary">
                            <i class="fa-solid fa-phone"></i> Call
                        </a>
                        <button class="dp-btn dp-btn-sm dp-btn-warning" onclick="reAttempt(${o.id})">
                            <i class="fa-solid fa-rotate-right"></i> Re-attempt
                        </button>
                    </div>
                </div>`).join('');
        })
        .catch(() => {
            document.getElementById('orderList').innerHTML =
                '<div class="dp-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load orders</div>';
        });
}

function reAttempt(id) {
    if (!confirm('Re-attempt this delivery? Order will move back to Out for Delivery.')) return;
    fetch('../api/delivery_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', order_id: id, status: 'out_for_delivery' })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { loadOrders(); } else { alert(d.message ?? 'Error'); } });
}

document.getElementById('statusChips').addEventListener('click', e => {
    const btn = e.target.closest('[data-status]');
    if (!btn) return;
    document.querySelectorAll('.dp-chip-btn').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    currentStatus = btn.dataset.status;
    loadOrders();
});

loadOrders();
setInterval(loadOrders, 30000);
</script>
