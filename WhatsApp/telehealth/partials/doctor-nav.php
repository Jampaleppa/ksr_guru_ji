<?php
/* ======================================================================
   partials/doctor-nav.php
   Mobile-responsive doctor navigation bar.

   Variables expected (define before including):
   - $docName  : string – display name of the signed-in doctor
   - $navActive : string – one of: dashboard, appointments, patients,
                           prescriptions, new-rx, earnings, profile
   ====================================================================== */
$navActive = $navActive ?? '';
$docName   = $docName   ?? 'Doctor';
?>
<style>
/* ── Doctor Portal Nav (mobile-first) ─────────────────────────── */
.doc-portal-nav {
    background: linear-gradient(135deg, #0B1F2A 0%, #16303D 100%);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    position: sticky;
    top: 0;
    z-index: 999;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
}
.doc-portal-nav__inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 58px;
    gap: 12px;
}
.doc-portal-nav__brand {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #fff;
    text-decoration: none;
    font-weight: 800;
    font-size: 0.95rem;
    white-space: nowrap;
    flex-shrink: 0;
}
.doc-portal-nav__brand i { color: #14B8A6; font-size: 1.1rem; }
.doc-portal-nav__links {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    margin: 0 12px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.doc-portal-nav__links::-webkit-scrollbar { display: none; }
.doc-portal-nav__links a {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    color: #94A3B8;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.83rem;
    white-space: nowrap;
    transition: background 0.18s, color 0.18s;
    flex-shrink: 0;
}
.doc-portal-nav__links a:hover { background: rgba(255,255,255,0.08); color: #fff; }
.doc-portal-nav__links a.is-active { background: rgba(20,184,166,0.18); color: #14B8A6; }
.doc-portal-nav__right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.doc-portal-nav__call {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16,185,129,0.15);
    border: 1px solid rgba(16,185,129,0.3);
    color: #6EE7B7;
    padding: 6px 12px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.78rem;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.18s;
}
.doc-portal-nav__call:hover { background: rgba(16,185,129,0.28); }
.doc-portal-nav__profile {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.14);
    color: #fff;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.18s;
}
.doc-portal-nav__profile:hover { background: rgba(255,255,255,0.15); }
.doc-portal-nav__burger {
    display: none;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.14);
    color: #fff;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    transition: background 0.18s;
}
.doc-portal-nav__burger:hover { background: rgba(255,255,255,0.18); }
.doc-portal-nav__drawer {
    display: none;
    flex-direction: column;
    background: #0f2535;
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 12px 16px 16px;
    gap: 4px;
}
.doc-portal-nav__drawer.is-open { display: flex; }
.doc-portal-nav__drawer a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    border-radius: 10px;
    color: #94A3B8;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: background 0.15s, color 0.15s;
}
.doc-portal-nav__drawer a:hover { background: rgba(255,255,255,0.06); color: #fff; }
.doc-portal-nav__drawer a.is-active { background: rgba(20,184,166,0.15); color: #14B8A6; }
.doc-portal-nav__drawer a i { width: 18px; text-align: center; }
.doc-portal-nav__drawer-sep { height: 1px; background: rgba(255,255,255,0.07); margin: 8px 0; }
@media (max-width: 768px) {
    .doc-portal-nav__links  { display: none; }
    .doc-portal-nav__call   { display: none; }
    .doc-portal-nav__profile{ display: none; }
    .doc-portal-nav__burger { display: flex; }
}
@media (min-width: 769px) {
    .doc-portal-nav__drawer { display: none !important; }
}
@media print {
    .doc-portal-nav { display: none !important; }
}
</style>

<?php
$_navLinks = [
    'dashboard'    => ['doctor-dashboard.php',      'fa-th-large',          'Dashboard'],
    'appointments' => ['doctor-appointments.php',   'fa-calendar-check',    'Appointments'],
    'patients'     => ['doctor-patients.php',       'fa-users',             'Patients'],
    'prescriptions'=> ['doctor-prescriptions.php',  'fa-file-prescription', 'Prescriptions'],
    'new-rx'       => ['build-prescription.php',    'fa-plus-circle',       'New Rx'],
    'earnings'     => ['doctor-earnings.php',       'fa-chart-bar',         'Earnings'],
    'profile'      => ['doctor-profile-settings.php','fa-user-cog',         'Settings'],
];
$_callNum = defined('TELEHEALTH_AUDIO_CALL_NUMBER') ? TELEHEALTH_AUDIO_CALL_NUMBER : '';
function _dn_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?>

<nav class="doc-portal-nav" aria-label="Doctor portal navigation">
    <div class="doc-portal-nav__inner">
        <a href="doctor-dashboard.php" class="doc-portal-nav__brand">
            <i class="fas fa-heartbeat"></i>
            <span>DOCTOR PORTAL</span>
        </a>

        <div class="doc-portal-nav__links">
            <?php foreach ($_navLinks as $_key => [$_href, $_icon, $_label]): ?>
                <a href="<?= _dn_h($_href) ?>"
                   <?= $navActive === $_key ? 'class="is-active" aria-current="page"' : '' ?>>
                    <i class="fas <?= _dn_h($_icon) ?>"></i><?= _dn_h($_label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="doc-portal-nav__right">
            <?php if ($_callNum): ?>
                <a href="tel:<?= _dn_h($_callNum) ?>" class="doc-portal-nav__call">
                    <i class="fas fa-phone-alt"></i>
                    <span><?= _dn_h($_callNum) ?></span>
                </a>
            <?php endif; ?>
            <a href="doctor-profile-settings.php" class="doc-portal-nav__profile">
                <i class="fas fa-user-md"></i>
                <span><?= _dn_h(mb_strimwidth($docName, 0, 22, '…')) ?></span>
            </a>
            <button class="doc-portal-nav__burger" id="docNavBurger"
                    aria-label="Open navigation menu" aria-expanded="false">
                <i class="fas fa-bars" id="docNavBurgerIcon"></i>
            </button>
        </div>
    </div>

    <div class="doc-portal-nav__drawer" id="docNavDrawer"
         role="navigation" aria-label="Doctor portal mobile menu">
        <?php foreach ($_navLinks as $_key => [$_href, $_icon, $_label]): ?>
            <a href="<?= _dn_h($_href) ?>"
               <?= $navActive === $_key ? 'class="is-active" aria-current="page"' : '' ?>>
                <i class="fas <?= _dn_h($_icon) ?>"></i><?= _dn_h($_label) ?>
            </a>
        <?php endforeach; ?>
        <div class="doc-portal-nav__drawer-sep"></div>
        <?php if ($_callNum): ?>
        <a href="tel:<?= _dn_h($_callNum) ?>">
            <i class="fas fa-phone-alt"></i>Call <?= _dn_h($_callNum) ?>
        </a>
        <?php endif; ?>
        <a href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i>Sign Out
        </a>
    </div>
</nav>

<script>
(function(){
    var burger = document.getElementById('docNavBurger');
    var drawer = document.getElementById('docNavDrawer');
    var icon   = document.getElementById('docNavBurgerIcon');
    if (!burger || !drawer) return;
    burger.addEventListener('click', function(){
        var open = drawer.classList.toggle('is-open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        icon.className = open ? 'fas fa-times' : 'fas fa-bars';
    });
    drawer.querySelectorAll('a').forEach(function(a){
        a.addEventListener('click', function(){
            drawer.classList.remove('is-open');
            burger.setAttribute('aria-expanded', 'false');
            icon.className = 'fas fa-bars';
        });
    });
})();
</script>
