<?php
// ============================================================
// delivery/earnings.php — real-time payouts / collection summary
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('earnings.php');

delivery_layout('Earnings', 'earnings', function () {
?>
    <section class="dp-card">
        <div class="dp-sec" style="padding-top:14px">
            <div class="dp-chips" id="rangeChips">
                <button class="dp-chip-btn" data-range="today">Today</button>
                <button class="dp-chip-btn on" data-range="week">Last 7 Days</button>
                <button class="dp-chip-btn" data-range="month">This Month</button>
                <button class="dp-chip-btn" data-range="all">All Time</button>
            </div>
        </div>
    </section>

    <section class="dp-grid dp-stats">
        <div class="dp-stat">
            <span class="dp-stat-i accent"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <b id="eAmount">₹0</b>
            <small id="eRangeLabel">Earnings</small>
        </div>
        <div class="dp-stat">
            <span class="dp-stat-i ok"><i class="fa-solid fa-box-open"></i></span>
            <b id="eDeliveries">0</b>
            <small>Deliveries</small>
        </div>
        <div class="dp-stat">
            <span class="dp-stat-i info"><i class="fa-solid fa-truck-fast"></i></span>
            <b id="ePending">₹0</b>
            <small>In transit (payout pending)</small>
        </div>
        <div class="dp-stat">
            <span class="dp-stat-i warn"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            <b id="eCash">₹0</b>
            <small>COD to collect</small>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-chart-simple"></i> Daily breakdown</h3>
            <span class="dp-muted" id="eAvg"></span>
        </div>
        <div class="dp-bars" id="earnBars"></div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-credit-card"></i> By payment method</h3>
        </div>
        <div class="dp-sec" id="methodList" style="padding-top:10px"></div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-list-check"></i> Delivered orders</h3>
            <span class="dp-muted" id="deliveredCount"></span>
        </div>
        <div class="dp-list" id="deliveredList">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    let RANGE = 'week';

    function renderBars(daily) {
        const box = document.getElementById('earnBars');
        if (!daily.length) {
            box.innerHTML = '<div class="dp-empty" style="width:100%">No deliveries in this period</div>';
            return;
        }
        const max = Math.max(1, ...daily.map(d => parseFloat(d.amt)));
        box.innerHTML = daily.map(d => {
            const dt = new Date(d.d + 'T00:00:00');
            const lbl = dt.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short'
            });
            return `<div class="dp-bar" title="${dpMoney(d.amt)} · ${d.c} order(s)">
            <div class="dp-bar-w"><div class="dp-bar-f" style="height:${(parseFloat(d.amt) / max * 100)}%"></div></div>
            <span class="dp-bar-l">${lbl}</span>
            <span class="dp-bar-v">${d.c}</span>
        </div>`;
        }).join('');
    }

    async function load() {
        const j = await dpApi.get('earnings', {
            range: RANGE
        });
        if (!j || !j.success) return;

        document.getElementById('eAmount').textContent = dpMoney(j.summary.amount);
        document.getElementById('eDeliveries').textContent = j.summary.deliveries;
        document.getElementById('ePending').textContent = dpMoney(j.summary.pending_payout);
        document.getElementById('eCash').textContent = dpMoney(j.summary.cash_to_collect);
        document.getElementById('eRangeLabel').textContent = 'Earnings · ' + j.range.label;
        document.getElementById('eAvg').textContent = j.summary.deliveries ?
            'Avg ' + dpMoney(j.summary.avg_per_order) + ' / order' :
            '';

        renderBars(j.daily);

        document.getElementById('methodList').innerHTML = j.by_method.length ?
            j.by_method.map(m => `<div class="dp-kv"><span>${dpEscape((m.m || 'other').toUpperCase())} · ${m.c}</span><b>${dpMoney(m.amt)}</b></div>`).join('') :
            '<div class="dp-kv"><span>Nothing yet</span><b>—</b></div>';

        document.getElementById('deliveredCount').textContent = j.deliveries.length + ' shown';
        document.getElementById('deliveredList').innerHTML = j.deliveries.length ?
            j.deliveries.map(d => `<a class="dp-row" href="order.php?id=${d.id}">
            <div class="dp-row-l"><b>${dpEscape(d.order_number)}</b>
              <small>${dpEscape(d.name)} · ${dpEscape(d.city || '—')} · ${(d.payment_method || '').toUpperCase()}</small></div>
            <div class="dp-row-r"><b>${dpMoney(d.total)}</b><small>${dpTimeAgo(d.delivered_at)}</small></div>
          </a>`).join('') :
            '<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No delivered orders in this period</div>';
    }

    document.getElementById('rangeChips').addEventListener('click', e => {
        const b = e.target.closest('[data-range]');
        if (!b) return;
        RANGE = b.dataset.range;
        document.querySelectorAll('#rangeChips .dp-chip-btn').forEach(x => x.classList.toggle('on', x === b));
        load();
    });

    dpPoll(load, 20000);
</script>