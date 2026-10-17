<?php
// ============================================================
// delivery/notifications.php — in-app alerts + WhatsApp dispatch log
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('notifications.php');

delivery_layout('Alerts', 'notifications', function () {
?>
    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-bell"></i> Notifications</h3>
            <button class="dp-btn dp-btn-ghost dp-btn-sm" id="markRead">
                <i class="fa-solid fa-check-double"></i> Mark all read
            </button>
        </div>
        <div class="dp-list" id="notifList">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-paper-plane"></i> Notify a delivery agent</h3>
        </div>
        <div class="dp-sec" style="padding-top:12px">
            <div class="dp-chips" id="agentChips">
                <?php foreach (DELIVERY_ROSTER as $b): ?>
                    <button class="dp-chip-btn" data-agent="<?= e($b['code']) ?>">
                        <?= e($b['name']) ?> · <?= e($b['phone']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <textarea id="agentMsg" class="dp-textarea" rows="3"
                placeholder="Message for the selected agent…"></textarea>
            <div class="dp-row-end" style="padding:10px 0 0">
                <span class="dp-muted" id="agentState"></span>
                <button class="dp-btn dp-btn-wa" id="sendAgent"><i class="fa-brands fa-whatsapp"></i> Send</button>
            </div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-brands fa-whatsapp"></i> WhatsApp log</h3>
            <span class="dp-muted">Latest dispatches</span>
        </div>
        <div class="dp-list" id="waList">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    let AGENT = <?= json_encode(DELIVERY_ROSTER[0]['code']) ?>;

    async function load() {
        const j = await dpApi.get('notifications');
        if (!j || !j.success) return;

        document.getElementById('notifList').innerHTML = j.notifications.length ?
            j.notifications.map(n => `
          <div class="dp-row ${n.is_read ? '' : 'is-unread'}">
            <div class="dp-row-l">
              <b>${dpEscape(n.title)}</b>
              <small>${dpEscape(n.message)}</small>
            </div>
            <div class="dp-row-r"><small>${dpTimeAgo(n.created_at)}</small></div>
          </div>`).join('') :
            '<div class="dp-empty"><i class="fa-solid fa-bell-slash"></i> No notifications yet</div>';

        document.getElementById('waList').innerHTML = j.whatsapp_log.length ?
            j.whatsapp_log.map(w => `
          <div class="dp-row">
            <div class="dp-row-l">
              <b>+${dpEscape(w.recipient)}</b>
              <small>${dpEscape((w.message || '').slice(0, 90))}</small>
            </div>
            <div class="dp-row-r">
              <span class="ds-badge ds-${w.status === 'sent' ? 'ok' : (w.status === 'failed' ? 'bad' : 'muted')}">${dpEscape(w.status)}</span>
              <small>${dpTimeAgo(w.created_at)}</small>
            </div>
          </div>`).join('') :
            '<div class="dp-empty"><i class="fa-regular fa-comments"></i> No WhatsApp messages dispatched yet</div>';

        const dot = document.getElementById('dpUnreadDot');
        if (j.unread > 0) {
            if (dot) dot.textContent = j.unread;
        } else if (dot) dot.remove();
    }

    document.getElementById('agentChips').addEventListener('click', e => {
        const b = e.target.closest('[data-agent]');
        if (!b) return;
        AGENT = b.dataset.agent;
        document.querySelectorAll('#agentChips .dp-chip-btn').forEach(x => x.classList.toggle('on', x === b));
    });

    document.getElementById('sendAgent').addEventListener('click', async () => {
        const msg = document.getElementById('agentMsg').value.trim();
        const st = document.getElementById('agentState');
        st.textContent = 'Sending…';
        const j = await dpApi.post('notify_agent', {
            agent: AGENT,
            message: msg
        });
        st.textContent = '';
        if (j && j.success) {
            dpToast(j.message);
            load();
        } else dpToast((j && j.error) || 'Failed', 'bad');
    });

    document.getElementById('markRead').addEventListener('click', async () => {
        const j = await dpApi.post('read_all');
        if (j && j.success) {
            dpToast('All caught up');
            load();
        }
    });

    dpPoll(load, 10000);
</script>