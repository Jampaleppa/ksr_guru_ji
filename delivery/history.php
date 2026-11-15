<?php
// ============================================================
// delivery/history.php — Daily Delivery History
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('history.php');

$agentId = delivery_id();
$pdo     = delivery_db();

$date = $_GET['date'] ?? date('Y-m-d');

// Fetch delivered orders for selected date
$deliveredOrders = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, order_number, name, phone, address, total, payment_method, status, updated_at
         FROM orders
         WHERE (delivery_boy_id = ? OR driver_id = ?)
           AND status = 'delivered'
           AND DATE(updated_at) = ?
         ORDER BY updated_at DESC"
    );
    $stmt->execute([$agentId, $agentId, $date]);
    $deliveredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $deliveredOrders = [];
}

$GLOBALS['dp_date'] = $date;

delivery_layout('Delivery History', 'history', function () use ($deliveredOrders) {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Daily History</h3>
        </div>
        <div class="dp-sec" style="padding-top:14px;">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="date" name="date" class="dp-input" value="<?= htmlspecialchars($GLOBALS['dp_date'], ENT_QUOTES) ?>" style="max-width:180px;">
                <button type="submit" class="dp-btn dp-btn-primary"><i class="fa-solid fa-search"></i> View</button>
            </form>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3>Deliveries on <?= htmlspecialchars(date('d M Y', strtotime($GLOBALS['dp_date'])), ENT_QUOTES) ?></h3>
            <span class="dp-muted"><?= count($deliveredOrders) ?> delivered</span>
        </div>
        <?php if (empty($deliveredOrders)): ?>
            <div class="dp-empty"><i class="fa-solid fa-box-open"></i> No deliveries found for this date.</div>
        <?php else: ?>
            <div class="dp-list">
            <?php foreach ($deliveredOrders as $ord): ?>
                <div class="dp-item">
                    <div style="flex:1;">
                        <strong><?= htmlspecialchars($ord['order_number'] ?: '#' . $ord['id'], ENT_QUOTES) ?></strong>
                        <span class="dp-muted"> · <?= htmlspecialchars($ord['name'] ?? '—', ENT_QUOTES) ?></span><br>
                        <small class="dp-muted">
                            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($ord['phone'] ?? '—', ENT_QUOTES) ?> &nbsp;
                            <i class="fa-solid fa-map-pin"></i> <?= htmlspecialchars(substr($ord['address'] ?? '—', 0, 60), ENT_QUOTES) ?>
                        </small>
                    </div>
                    <div style="text-align:right;min-width:90px;">
                        <strong style="color:#0f7b3a;">₹<?= number_format((float)($ord['total'] ?? 0), 0) ?></strong><br>
                        <small class="dp-muted"><?= htmlspecialchars(strtoupper($ord['payment_method'] ?? 'COD'), ENT_QUOTES) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="dp-sec" style="border-top:1px solid #e2e8f0;padding-top:12px;margin-top:4px;">
                <strong>Total collected today:</strong>
                <span style="color:#0f7b3a;font-weight:800;">
                    ₹<?= number_format(array_sum(array_column($deliveredOrders, 'total')), 2) ?>
                </span>
            </div>
        <?php endif; ?>
    </section>
<?php
});
?>
