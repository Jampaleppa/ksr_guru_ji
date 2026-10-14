<?php
// ============================================================
// delivery/orders.php — real-time order board (mine / open pool)
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('orders.php');

$scope  = (($_GET['scope'] ?? 'mine') === 'open') ? 'open' : 'mine';
$status = (string) ($_GET['status'] ?? 'all');

$GLOBALS['dp_scope']  = $scope;
$GLOBALS['dp_status'] = $status;
$GLOBALS['dp_open']   = delivery_unassigned_count();

delivery_layout('Orders', 'orders', function () {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-layer-group"></i> Board</h3>
            <span class="dp-muted" id="listCount"></span>
        </div>
        <div class="dp-sec" style="padding-top:12px">
            <div class="dp-chips" id="scopeChips">
                <button class="dp-chip-btn <?= $GLOBALS['dp_scope'] === 'mine' ? 'on' : '' ?>" data-scope="mine">
                    <i class="fa-solid fa-box"></i> My Orders
                </button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_scope'] === 'open' ? 'on' : '' ?>" data-scope="open">
                    <i class="fa-solid fa-inbox"></i> Open Pool (<?= (int) $GLOBALS['dp_open'] ?>)
                </button>
            </div>
        </div>
        <div class="dp-sec" style="padding-top:0">
            <div class="dp-chips" id="statusChips">
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'all' ? 'on' : '' ?>" data-status="all">All</button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'pending' ? 'on' : '' ?>" data-status="pending">Pending</button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'accepted' ? 'on' : '' ?>" data-status="accepted">Accepted</button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'picked' ? 'on' : '' ?>" data-status="picked">Picked Up</button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'out' ? 'on' : '' ?>" data-status="out">Out</button>
                <button class="dp-chip-btn <?= $GLOBALS['dp_status'] === 'delivered' ? 'on' : '' ?>" data-status="delivered">Delivered</button>
            </div>
            <input type="search" id="search" class="dp-input" style="margin-top:10px"
                placeholder="Search order no / customer / phone…">
        </div>
    </section>

    <section class="dp-card">
        <div id="orderList" class="dp-list">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading orders…</div>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    const STATE = {
        scope: <?= json_encode($scope) ?>,
        status: <?= json_encode($status) ?>,
        q: ''
    };

    function orderRow(o) {
        const canAccept = !o.driver_id && !o.delivery_boy_id;
        const next = {
            accepted: {
                label: 'Accept',
                cls: 'dp-btn-primary',
                act: o.status === 'pending' || o.status === 'confirmed' ? 'accept' : 'status',
                val: 'accepted'
            },
            picked_up: {
                label: 'Picked Up',
                cls: 'dp-btn-primary',
                act: 'status',
                val: 'picked_up'
            },
            in_transit: {
                label: 'Out for Delivery',
                cls: 'dp-btn-primary',
                act: 'status',
                val: 'in_transit'
            },
            delivered: {
                label: 'Delivered',
                cls: 'dp-btn-primary',
                act: 'status',
                val: 'delivered'
            }
        };
        const done = o.status === 'delivered' || o.status === 'cancelled';
        const btn = canAccept ?
            `<button class="dp-btn dp-btn-primary dp-btn-sm" onclick="act(${o.id},'accept')"><i class="fa-solid fa-hand"></i> Accept</button>` :
            (next[o.status] ? `<button class="dp-btn ${next[o.status].cls} dp-btn-sm" onclick="act(${o.id},'status','${next[o.status].val}')">${next[o.status].label}</button>` : '');
        const wa = o.phone ? `<a class="dp-btn dp-btn-wa dp-btn-sm" target="_blank" rel="noopener" href="${dpWaLink(o.phone, 'Hi ' + o.name + ', your KSR GURU JI order ' + o.order_number + ' is on the way.')}"><i class="fa-brands fa-whatsapp"></i> Call/Chat</a>` : '';

        return `<div class="dp-ord">
        <a class="dp-ord-h" href="order.php?id=${o.id}">
            <div class="dp-ord-l">
                <b>${dpEscape(o.order_number)}</b>
                <small><i class="fa-solid fa-user"></i> ${dpEscape(o.name)} ${o.phone ? '· ' + dpEscape(o.phone) : ''}</small>
                <small><i class="fa-solid fa-location-dot"></i> ${dpEscape((o.address || '') + ', ' + (o.city || ''))}</small>
            </div>
            <div class="dp-ord-r">
                <b>${dpMoney(o.total)}</b>
                <small>${(o.payment_method || '').toUpperCase()}</small>
                <small>${dpTimeAgo(o.placed_at || o.created_at)}</small>
            </div>
            ${dpStatusBadge(o.status)}
        </a>
        <div class="dp-ord-f">
            <a class="dp-btn dp-btn-ghost dp-btn-sm" href="order.php?id=${o.id}"><i class="fa-solid fa-eye"></i> Details</a>
            ${wa}
            ${done ? '' : btn}
        </div>
    </div>`;
    }

    async function load() {
        const j = await dpApi.get('orders', {
            scope: STATE.scope,
            status: STATE.status,
            q: STATE.q
        });
        const box = document.getElementById('orderList');
        if (!j || !j.success) {
            box.innerHTML = '<div class="dp-empty">Could not load orders</div>';
            return;
        }
        document.getElementById('listCount').textContent = j.orders.length + ' order' + (j.orders.length === 1 ? '' : 's');
        box.innerHTML = j.orders.length ?
            j.orders.map(orderRow).join('') :
            `<div class="dp-empty"><i class="fa-solid fa-box-open"></i> No ${STATE.scope === 'open' ? 'open ' : ''}orders here yet</div>`;
    }

    async function act(id, action, status) {
        const j = await dpApi.post(action, {
            id,
            status: status || ''
        });
        if (j && j.success) {
            dpToast(j.message || 'Updated');
            load();
        } else dpToast((j && j.error) || 'Failed', 'bad');
    }

    document.getElementById('scopeChips').addEventListener('click', e => {
        const b = e.target.closest('[data-scope]');
        if (!b) return;
        STATE.scope = b.dataset.scope;
        document.querySelectorAll('#scopeChips .dp-chip-btn').forEach(x => x.classList.toggle('on', x === b));
        load();
    });
    document.getElementById('statusChips').addEventListener('click', e => {
        const b = e.target.closest('[data-status]');
        if (!b) return;
        STATE.status = b.dataset.status;
        document.querySelectorAll('#statusChips .dp-chip-btn').forEach(x => x.classList.toggle('on', x === b));
        load();
    });
    let _t;
    document.getElementById('search').addEventListener('input', e => {
        clearTimeout(_t);
        _t = setTimeout(() => {
            STATE.q = e.target.value.trim();
            load();
        }, 300);
    });

    dpPoll(load, 15000);
</script>