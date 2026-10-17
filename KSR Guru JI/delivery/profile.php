<?php
// ============================================================
// delivery/profile.php — agent profile, roster, WhatsApp log, logout
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('profile.php');

delivery_layout('Profile', 'profile', function () {
    $me = delivery_name();
?>
    <section class="dp-card">
        <div class="dp-sec" style="padding-top:18px; display:flex; align-items:center; gap:14px">
            <span class="dp-av" style="width:56px;height:56px;font-size:1.3rem"><?= e(strtoupper(substr($me, 0, 1))) ?></span>
            <div>
                <h2 style="font-size:1.15rem;font-weight:800"><?= e($me) ?></h2>
                <p class="dp-muted">Delivery Boy · KSR GURU JI</p>
            </div>
        </div>
        <div class="dp-sec">
            <div class="dp-kv"><span>Phone</span><b id="pPhone"><?= e(delivery_my_phone()) ?></b></div>
            <div class="dp-kv"><span>Total deliveries</span><b id="pDel">0</b></div>
            <div class="dp-kv"><span>Total delivered value</span><b id="pAmt">₹0</b></div>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-pen"></i> Edit details</h3>
        </div>
        <form id="profForm">
            <div class="dp-sec" style="padding-top:12px">
                <label class="dp-field">
                    <span>Name</span>
                    <input type="text" id="fName" value="<?= e(delivery_name()) ?>">
                </label>
                <label class="dp-field">
                    <span>Phone (must be a registered agent)</span>
                    <input type="tel" id="fPhone" value="<?= e(delivery_my_phone()) ?>">
                </label>
                <label class="dp-field">
                    <span>Email</span>
                    <input type="email" id="fEmail" value="<?= e((string) ($_SESSION['delivery_boy_email'] ?? '')) ?>">
                </label>
                <div class="dp-err" id="profErr" hidden></div>
                <button type="submit" class="dp-btn dp-btn-primary dp-btn-block" id="profBtn">
                    <i class="fa-solid fa-check"></i> Save changes
                </button>
            </div>
        </form>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-solid fa-users"></i> Delivery agents</h3>
        </div>
        <div class="dp-list" style="padding-top:8px">
            <?php foreach (DELIVERY_ROSTER as $b):
                $isMe = strcasecmp($b['name'], $me) === 0; ?>
                <div class="dp-row">
                    <span class="dp-av"><?= e(strtoupper(substr($b['name'], 0, 1))) ?></span>
                    <div class="dp-row-l">
                        <b>Delivery Boy: <?= e($b['name']) ?></b>
                        <small><?= e($b['phone']) ?></small>
                    </div>
                    <div class="dp-row-r">
                        <a class="dp-btn dp-btn-wa dp-btn-sm" target="_blank" rel="noopener"
                            href="<?= e(delivery_wa_link($b['phone'], 'Hi ' . $b['name'] . ', from KSR GURU JI delivery.')) ?>">
                            <i class="fa-brands fa-whatsapp"></i> Chat
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="dp-card">
        <div class="dp-card-h">
            <h3><i class="fa-brands fa-whatsapp"></i> Recent WhatsApp</h3>
        </div>
        <div class="dp-list" id="waList">
            <div class="dp-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </section>

    <a href="logout.php" class="dp-btn dp-btn-danger dp-btn-block" style="margin-top:4px">
        <i class="fa-solid fa-power-off"></i> Sign out
    </a>
<?php
});
?>
<script src="js/delivery-common.js?v=1"></script>
<script>
    document.getElementById('profForm').addEventListener('submit', async ev => {
        ev.preventDefault();
        const btn = document.getElementById('profBtn');
        const err = document.getElementById('profErr');
        err.hidden = true;
        btn.disabled = true;
        const j = await dpApi.post('update_profile', {
            name: document.getElementById('fName').value.trim(),
            phone: document.getElementById('fPhone').value.trim(),
            email: document.getElementById('fEmail').value.trim()
        });
        btn.disabled = false;
        if (j && j.success) {
            dpToast(j.message);
            load();
        } else {
            err.textContent = (j && j.error) || 'Could not save';
            err.hidden = false;
        }
    });

    async function load() {
        const j = await dpApi.get('profile');
        if (!j || !j.success) return;
        document.getElementById('pPhone').textContent = j.profile.phone;
        document.getElementById('pDel').textContent = j.stats.deliveries;
        document.getElementById('pAmt').textContent = dpMoney(j.stats.amount);
        document.getElementById('waList').innerHTML = j.wa_log.length ?
            j.wa_log.map(w => `<div class="dp-row">
            <div class="dp-row-l"><b>+${dpEscape(w.recipient)}</b>
            <small>${dpEscape((w.message || '').slice(0, 80))}</small></div>
            <div class="dp-row-r"><small>${dpTimeAgo(w.created_at)}</small></div>
          </div>`).join('') :
            '<div class="dp-empty"><i class="fa-regular fa-comments"></i> Nothing sent yet</div>';
    }
    load();
</script>