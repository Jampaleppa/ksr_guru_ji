<?php
// ============================================================
// delivery/delivered.php — Today's Delivered Orders (read-only)
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('delivered.php');

$deliveryBoyId = (int)($_SESSION['delivery_boy_id'] ?? 0);
$dateFilter    = ($_GET['date'] ?? 'today') === 'today' ? date('Y-m-d') : (string)($_GET['date'] ?? date('Y-m-d'));

// Summary stats
$totalCount = 0;
$totalAmount = 0.0;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as amt
         FROM orders
         WHERE delivery_boy_id = ? AND status = 'delivered' AND DATE(completed_at) = ?"
    );
    $stmt->execute([$deliveryBoyId, $dateFilter]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCount  = (int)($row['cnt'] ?? 0);
    $totalAmount = (float)($row['amt'] ?? 0);
} catch (Exception $e) {
    // graceful fallback
}

delivery_layout('Delivered Orders', 'orders', function () use ($deliveryBoyId, $dateFilter, $totalCount, $totalAmount) {
?>
    <!-- Summary cards -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:4px 0 8px">
        <section class="dp-card" style="margin:0;text-align:center">
            <div class="dp-card-h" style="justify-content:center">
                <i class="fa-solid fa-check-double" style="color:#22c55e;font-size:22px"></i>
            </div>
            <div style="font-size:28px;font-weight:700;color:#22c55e"><?= $totalCount ?></div>
            <div class="dp-muted" style="font-size:12px">Delivered Today</div>
        </section>
        <section class="dp-card" style="margin:0;text-align:center">
            <div class="dp-card-h" style="justify-content:center">
                <i class="fa-solid fa-sack-dollar" style="color:#3b82f6;font-size:22px"></i>
            </div>
            <div style="font-size:24px;font-weight:700;color:#3b82f6">₹<?= number_format($totalAmount, 2) ?></div>
            <div class="dp-muted" style="font-size:12px">Total Value</div>
        </section>
    </div>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-check-double"></i> Delivered Orders</h3>
            <input type="date" id="dateFilter" class="dp-input" style="width:auto;font-size:13px"
                   value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <div class="dp-sec">
            <div id="orderList" class="dp-list">
                <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
            </div>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
const deliveryBoyId = <?= json_encode($deliveryBoyId) ?>;

function loadOrders(date) {
    fetch(`../api/delivery_orders.php?status=delivered&delivery_boy_id=${deliveryBoyId}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('orderList');
            const orders = data.orders ?? [];
            if (!orders.length) {
                list.innerHTML = '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No deliveries on this date</div>';
                return;
            }
            list.innerHTML = orders.map(o => `
                <div class="dp-order-card">
                    <div class="dp-order-head">
                        <strong>#${o.order_number ?? o.id}</strong>
                        <span class="dp-badge dp-badge-success">Delivered</span>
                    </div>
                    <div class="dp-order-body">
                        <p><i class="fa-solid fa-user"></i> ${o.customer_name ?? 'Customer'}</p>
                        <p><i class="fa-solid fa-location-dot"></i> ${o.delivery_address ?? 'N/A'}</p>
                        <p><i class="fa-solid fa-clock"></i> ${o.completed_at ?? o.updated_at ?? '—'}</p>
                        <p><i class="fa-solid fa-indian-rupee-sign"></i> ₹${parseFloat(o.total_amount ?? 0).toFixed(2)}</p>
                    </div>
                </div>`).join('');
        })
        .catch(() => {
            document.getElementById('orderList').innerHTML =
                '<div class="dp-empty"><i class="fa-solid fa-triangle-exclamation"></i> Could not load</div>';
        });
}

const dateInput = document.getElementById('dateFilter');
dateInput.addEventListener('change', () => loadOrders(dateInput.value));
loadOrders(dateInput.value);
</script>
