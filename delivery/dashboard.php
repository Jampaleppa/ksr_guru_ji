<?php
// ============================================================
// delivery/dashboard.php — live agent dashboard
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('dashboard.php');

delivery_layout('Dashboard', 'dashboard', function () {
    $me   = delivery_name();
    $phone = delivery_my_phone();
    $hour = (int) date('G');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $myOrder = delivery_roster_by_name($me);
?>
    <section class="dp-hero">
        <div class="dp-hero-l">
            <p class="dp-hero-hi"><?= e($greet) ?>,</p>
            <h2><?= e($me) ?></h2>
            <p class="dp-hero-p">
                <i class="fa-solid fa-phone"></i> <?= e($phone) ?>
                <?php if ($myOrder): ?>
                    <span class="dp-chip">Roster ID: <?= e($myOrder['code']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="dp-hero-r">
            <div class="dp-live" id="liveDot">
                <span class="dot"></span> Live
            </div>
        </div>
    </section>

    <section class="dp-grid dp-stats">
        <a href="orders.php?status=delivered" class="dp-stat">
            <span class="dp-stat-i ok"><i class="fa-solid fa-circle-check"></i></span>
            <b id="sDelivered">0</b>
            <small>Delivered today</small>
        </a>
        <a href="orders.php" class="dp-stat">
            <span class="dp-stat-i info"><i class="fa-solid fa-truck-fast"></i></span>
            <b id="sActive">0</b>
            <small>Active orders</small>
        </a>
        <a href="orders.php?scope=open" class="dp-stat">
            <span class="dp-stat-i warn"><i class="fa-solid fa-inbox"></i></span>
            <b id="sUnassigned">0</b>
            <small>Open for pickup</small>
        </a>
        <a href="earnings.php" class="dp-stat">
            <span class="dp-stat-i accent"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <b id="sEarned">₹0</b>
            <small>Earned today</small>
        </a>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-chart-column"></i> Deliveries — last 7 days</h3>
            <span class="dp-muted" id="trendTotal"></span>
        </div>
        <div class="dp-bars" id="trendBars"></div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent orders</h3>
            <a href="orders.php" class="dp-link">See all <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div id="recentList" class="dp-list">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-brands fa-whatsapp"></i> WhatsApp broadcast</h3>
        </div>
        <p class="dp-muted">Send a live update to all three delivery agents at once.</p>
        <textarea id="bcastMsg" class="dp-textarea" rows="3"
            placeholder="e.g. All pending orders are ready for pickup at the main store."></textarea>
        <div class="dp-row-end">
            <span class="dp-muted" id="bcastState"></span>
            <button class="dp-btn dp-btn-wa" id="bcastBtn">
                <i class="fa-brands fa-whatsapp"></i> Send to all 3
            </button>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    async function load() {
        const j = await dpApi.get('dashboard');
        if (!j || !j.success) return;
        document.getElementById('sDelivered').textContent = j.stats.delivered_today;
        document.getElementById('sActive').textContent = j.stats.active_orders;
        document.getElementById('sUnassigned').textContent = j.stats.unassigned;
        document.getElementById('sEarned').textContent = dpMoney(j.stats.earned_today);
        dpRenderTrend(j.trend);
        dpRenderRecent(j.recent);
        const dot = document.getElementById('dpUnreadDot');
        if (j.stats.unread > 0) {
            if (dot) dot.textContent = j.stats.unread;
        } else if (dot) dot.remove();
    }

    document.getElementById('bcastBtn').addEventListener('click', async () => {
        const msg = document.getElementById('bcastMsg').value.trim();
        if (!msg) {
            dpToast('Write a message first', 'bad');
            return;
        }
        const st = document.getElementById('bcastState');
        st.textContent = 'Sending…';
        const j = await dpApi.post('notify_all', {
            message: msg
        });
        st.textContent = '';
        if (j && j.success) {
            dpToast(j.message);
            document.getElementById('bcastMsg').value = '';
        } else dpToast((j && j.error) || 'Failed', 'bad');
    });

    load();
    // Real-time refresh every 12s + refresh when the tab regains focus
    setInterval(load, 12000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) load();
    });
</script>