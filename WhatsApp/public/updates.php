<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$displayName = $_SESSION['display_name'] ?? 'KSR Member';
$isLoggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['ksr_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>Updates &amp; Channels | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --primary-soft: #e7fce3;
            --bg-app: #f0f2f5;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-app);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px));
        }
        .app-shell {
            max-width: 720px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg-screen);
            box-shadow: 0 0 24px rgba(0,0,0,0.04);
        }
        .app-header {
            background: var(--primary);
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 4px rgba(0,0,0,0.12);
        }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand h1 { font-size: 1.2rem; font-weight: 700; letter-spacing: -0.2px; }
        .header-actions { display: flex; align-items: center; gap: 6px; }
        .icon-btn {
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
        }
        .icon-btn:active { background: rgba(255,255,255,0.25); }

        .search-drawer {
            display: none;
            padding: 10px 14px;
            background: #fff;
            border-bottom: 1px solid var(--border);
        }
        .search-drawer.open { display: block; }
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-app);
            border-radius: 12px;
            padding: 8px 12px;
        }
        .search-box i { color: var(--text-muted); }
        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 16px;
            color: var(--text-main);
        }

        .section-head {
            padding: 16px 16px 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .section-link {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-dark);
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .status-row {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: transparent;
            border: none;
            border-bottom: 1px solid #f4f6f8;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }
        .status-row:active { background: #f5f6f6; }

        .avatar-ring {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            padding: 2.5px;
            background: linear-gradient(135deg, #00a884, #25d366);
            flex-shrink: 0;
            position: relative;
        }
        .avatar-ring.seen { background: #cfd8dc; }
        .avatar-ring.mine { background: #e9edef; }
        .avatar-core {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #e7fce3;
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #006c55;
            font-weight: 700;
        }
        .plus-badge {
            position: absolute;
            right: -1px;
            bottom: -1px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .row-body { flex: 1; min-width: 0; }
        .row-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .row-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .verified-icon { color: #00a884; font-size: 0.85rem; }

        .channel-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 16px;
            border-bottom: 1px solid #f4f6f8;
        }
        .channel-thumb {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .follow-btn {
            border: 1.5px solid var(--primary);
            background: #e7fce3;
            color: var(--primary-dark);
            border-radius: 20px;
            padding: 7px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            min-height: 36px;
            flex-shrink: 0;
        }
        .follow-btn.following {
            background: var(--bg-app);
            border-color: var(--border);
            color: var(--text-muted);
        }

        .empty-note {
            display: none;
            text-align: center;
            padding: 28px 16px;
            color: var(--text-muted);
            font-size: 0.92rem;
        }

        .fab-group {
            position: fixed;
            bottom: calc(74px + env(safe-area-inset-bottom, 0px));
            right: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
            z-index: 95;
        }
        .fab-mini {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            color: var(--text-main);
            border: 1px solid var(--border);
            box-shadow: 0 3px 12px rgba(0,0,0,0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
        }
        .fab-main {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            box-shadow: 0 4px 16px rgba(0,168,132,0.38);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.3rem;
        }

        /* Bottom Nav (5-tab consistent across WhatsApp/public) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(62px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: var(--bg-screen);
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid var(--border);
            z-index: 100;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .bnav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 500;
            gap: 3px;
            min-width: 58px;
            min-height: 46px;
        }
        .bnav-item .ico { font-size: 1.25rem; line-height: 1; }
        .bnav-item.active { color: var(--primary-dark); font-weight: 700; }

        /* Story Viewer Overlay */
        .story-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: #0b141a;
            color: #fff;
            z-index: 400;
            flex-direction: column;
            padding: 16px;
            padding-top: max(16px, env(safe-area-inset-top, 16px));
        }
        .story-modal.open { display: flex; }
        .story-progress-track {
            height: 3px;
            background: rgba(255,255,255,0.25);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .story-progress-fill {
            height: 100%;
            width: 0%;
            background: #fff;
            transition: width 0.08s linear;
        }
        .story-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .story-author { display: flex; align-items: center; gap: 10px; }
        .story-author-ico {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .story-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px 18px;
            margin: 16px 0;
            border-radius: 18px;
        }
        .story-emoji { font-size: 3.2rem; margin-bottom: 16px; }
        .story-text { font-size: 1.25rem; font-weight: 600; line-height: 1.45; max-width: 440px; }
        .story-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            padding: 11px 20px;
            background: #fff;
            color: #111b21;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.92rem;
            text-decoration: none;
        }

        /* Composer Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17, 27, 33, 0.55);
            z-index: 350;
            align-items: flex-end;
            justify-content: center;
            padding: 12px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-sheet {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 12px 36px rgba(0,0,0,0.22);
        }
        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .modal-head h2 { font-size: 1.1rem; }
        .close-btn {
            background: var(--bg-app);
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
        }
        .composer-input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            font-size: 16px;
            font-family: inherit;
            min-height: 92px;
            resize: vertical;
            margin-bottom: 12px;
        }
        .composer-input:focus { outline: none; border-color: var(--primary); }
        .swatch-row { display: flex; gap: 10px; margin-bottom: 16px; }
        .swatch {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
        }
        .swatch.active { border-color: #111b21; transform: scale(1.1); }
        .primary-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            padding: 13px;
            cursor: pointer;
        }
        .toast {
            position: fixed;
            bottom: 84px;
            left: 50%;
            transform: translateX(-50%);
            background: #111b21;
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 0.85rem;
            display: none;
            z-index: 500;
        }
        .toast.show { display: block; }
    </style>
</head>
<body>

<div class="app-shell">
    <header class="app-header">
        <div class="brand">
            <h1>Updates</h1>
        </div>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="toggleSearch()" aria-label="Search updates and channels">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" class="icon-btn" onclick="openComposer()" aria-label="Post a status update">
                <i class="fas fa-camera"></i>
            </button>
            <a href="services.php" class="icon-btn" aria-label="All KSR services">
                <i class="fas fa-th-large"></i>
            </a>
        </div>
    </header>

    <div class="search-drawer" id="searchDrawer">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="search" id="updatesSearch" placeholder="Filter updates or channels..." oninput="filterUpdates()">
        </div>
    </div>

    <div class="section-head">
        <span class="section-title">Status</span>
        <button type="button" class="section-link" onclick="openComposer()">+ Add update</button>
    </div>

    <!-- My Status -->
    <button type="button" class="status-row" id="myStatusBtn" onclick="handleMyStatusTap()">
        <div class="avatar-ring mine" id="myStatusRing">
            <div class="avatar-core" id="myStatusAvatar"><?php echo htmlspecialchars(mb_substr($displayName, 0, 1)); ?></div>
            <span class="plus-badge"><i class="fas fa-plus"></i></span>
        </div>
        <div class="row-body">
            <div class="row-title">My status</div>
            <div class="row-sub" id="myStatusSub">Tap to add a status update</div>
        </div>
    </button>

    <div id="myPostedContainer"></div>

    <div class="section-head">
        <span class="section-title">Recent updates</span>
    </div>

    <div id="statusFeed">
        <button type="button" class="status-row filterable-item" data-search="ksr guru ji store fresh vegetables stationery offers"
            onclick="openStory(this, 'KSR GURU JI Store', 'Today, 09:30 AM', '🥬', 'Fresh Hubballi farm vegetables, A4 B2B paper bundles, lamination sheets & ink refills are now restocked for same-day delivery.', '../../index.php', 'Shop Store Now', '#0b5345')">
            <div class="avatar-ring">
                <div class="avatar-core">🛒</div>
            </div>
            <div class="row-body">
                <div class="row-title">KSR GURU JI Store <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Today, 09:30 AM • Fresh veggies &amp; stationery deals</div>
            </div>
        </button>

        <button type="button" class="status-row filterable-item" data-search="dr shivukumar rathod telehealth clinic pediatrics medicine"
            onclick="openStory(this, 'Dr. Shivukumar Rathod', 'Today, 08:45 AM', '🩺', 'Video & audio OPD consultation slots open today (₹200). Priority pediatric & general medicine guidance via KSR Telehealth.', '../../telehealth/appointment.php?doctor=' + encodeURIComponent('Dr. Shivukumar Rathod'), 'Book Slot Now', '#1a3c6e')">
            <div class="avatar-ring">
                <div class="avatar-core">👨‍⚕️</div>
            </div>
            <div class="row-body">
                <div class="row-title">Dr. Shivukumar Rathod <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Today, 08:45 AM • Telehealth OPD open</div>
            </div>
        </button>

        <button type="button" class="status-row filterable-item" data-search="ksr express parcels delivery hubballi dispatch"
            onclick="openStory(this, 'KSR Express Delivery', 'Yesterday, 06:15 PM', '📦', 'Send local parcels and bulk stationery cartons across Karnataka with live tracking and COD collection.', '../../categories/parcels.php', 'Book a Parcel', '#4a235a')">
            <div class="avatar-ring seen">
                <div class="avatar-core">🚚</div>
            </div>
            <div class="row-body">
                <div class="row-title">KSR Express Delivery <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Yesterday • Express pickup active</div>
            </div>
        </button>
    </div>

    <div class="section-head">
        <span class="section-title">Channels</span>
        <a href="services.php" class="section-link">Explore all</a>
    </div>

    <div id="channelsFeed">
        <div class="channel-card filterable-item" data-search="ksr fresh mart daily price alerts">
            <div class="channel-thumb">🍅</div>
            <div class="row-body">
                <div class="row-title">KSR Fresh Mart Alerts <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Daily mandi rates &amp; combo offers</div>
            </div>
            <button type="button" class="follow-btn" data-channel="fresh_mart" onclick="toggleFollow(this)">Follow</button>
        </div>

        <div class="channel-card filterable-item" data-search="ksr telehealth health tips doctor updates">
            <div class="channel-thumb">🏥</div>
            <div class="row-body">
                <div class="row-title">KSR Health &amp; Clinic <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Doctor clinic hours &amp; seasonal care</div>
            </div>
            <button type="button" class="follow-btn following" data-channel="telehealth" onclick="toggleFollow(this)">Following</button>
        </div>

        <div class="channel-card filterable-item" data-search="ksr stationery b2b paper ink lamination">
            <div class="channel-thumb">🖨️</div>
            <div class="row-body">
                <div class="row-title">B2B Stationery &amp; Print <i class="fas fa-circle-check verified-icon"></i></div>
                <div class="row-sub">Paper rims, ink, band paper &amp; pouches</div>
            </div>
            <button type="button" class="follow-btn" data-channel="stationery" onclick="toggleFollow(this)">Follow</button>
        </div>
    </div>

    <div class="empty-note" id="emptyUpdates">No matching updates or channels found.</div>
</div>

<div class="fab-group">
    <button type="button" class="fab-mini" onclick="openComposer()" title="Write text status" aria-label="Write text status">
        <i class="fas fa-pen"></i>
    </button>
    <button type="button" class="fab-main" onclick="openComposer()" title="Add status update" aria-label="Add status update">
        <i class="fas fa-camera"></i>
    </button>
</div>

<nav class="bottom-nav" aria-label="WhatsApp navigation">
    <a class="bnav-item" href="chats.php"><span class="ico">💬</span><span>Chats</span></a>
    <a class="bnav-item active" href="updates.php"><span class="ico">🔄</span><span>Updates</span></a>
    <a class="bnav-item" href="calls.php"><span class="ico">📞</span><span>Calls</span></a>
    <a class="bnav-item" href="services.php"><span class="ico">🛍️</span><span>Services</span></a>
    <a class="bnav-item" href="profile.php"><span class="ico">👤</span><span>Profile</span></a>
</nav>

<!-- Story Viewer -->
<div class="story-modal" id="storyModal" role="dialog" aria-modal="true" aria-label="Status update viewer">
    <div class="story-progress-track">
        <div class="story-progress-fill" id="storyProgress"></div>
    </div>
    <div class="story-head">
        <div class="story-author">
            <div class="story-author-ico" id="storyIcon"></div>
            <div>
                <div id="storyName" style="font-weight:700;font-size:1rem;"></div>
                <div id="storyTime" style="font-size:0.78rem;opacity:0.8;"></div>
            </div>
        </div>
        <button type="button" class="icon-btn" onclick="closeStory()" aria-label="Close status">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="story-body" id="storyCardBody">
        <div class="story-emoji" id="storyBigEmoji"></div>
        <div class="story-text" id="storyText"></div>
        <a href="#" class="story-cta" id="storyCtaLink"><span id="storyCtaLabel">Open</span> <i class="fas fa-arrow-right"></i></a>
    </div>
</div>

<!-- Add Status Modal -->
<div class="modal-backdrop" id="composerModal" role="dialog" aria-modal="true" aria-label="Post a status update">
    <div class="modal-sheet">
        <div class="modal-head">
            <h2>Post a Status Update</h2>
            <button type="button" class="close-btn" onclick="closeComposer()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <textarea class="composer-input" id="statusInput" maxlength="200" placeholder="Share an update with your KSR contacts..."></textarea>
        <div class="swatch-row" id="swatchRow">
            <button type="button" class="swatch active" data-color="#0b5345" style="background:#0b5345;" aria-label="Dark green"></button>
            <button type="button" class="swatch" data-color="#1a3c6e" style="background:#1a3c6e;" aria-label="Navy blue"></button>
            <button type="button" class="swatch" data-color="#6c3483" style="background:#6c3483;" aria-label="Plum"></button>
            <button type="button" class="swatch" data-color="#9a3412" style="background:#9a3412;" aria-label="Terracotta"></button>
        </div>
        <button type="button" class="primary-btn" onclick="saveMyStatus()">Share Status</button>
    </div>
</div>

<div class="toast" id="toastBar"></div>

<script>
let selectedColor = '#0b5345';
let storyTimer = null;
const STORAGE_STATUS = 'ksr_my_statuses_v1';
const STORAGE_CHANNELS = 'ksr_followed_channels_v1';

document.querySelectorAll('.swatch').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.swatch').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        selectedColor = btn.dataset.color || '#0b5345';
    });
});

function showToast(msg) {
    const t = document.getElementById('toastBar');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2600);
}

function toggleSearch() {
    const d = document.getElementById('searchDrawer');
    d.classList.toggle('open');
    if (d.classList.contains('open')) {
        document.getElementById('updatesSearch').focus();
    }
}

function filterUpdates() {
    const q = document.getElementById('updatesSearch').value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.filterable-item').forEach((el) => {
        const hay = (el.dataset.search || el.textContent).toLowerCase();
        const show = !q || hay.includes(q);
        el.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('emptyUpdates').style.display = visible === 0 ? 'block' : 'none';
}

function loadMyStatuses() {
    try {
        const list = JSON.parse(localStorage.getItem(STORAGE_STATUS) || '[]');
        return Array.isArray(list) ? list : [];
    } catch (_) {
        return [];
    }
}

function renderMyStatuses() {
    const list = loadMyStatuses();
    const container = document.getElementById('myPostedContainer');
    const sub = document.getElementById('myStatusSub');
    const ring = document.getElementById('myStatusRing');
    container.textContent = '';

    if (list.length === 0) {
        sub.textContent = 'Tap to add a status update';
        ring.classList.add('mine');
        return;
    }

    ring.classList.remove('mine');
    sub.textContent = list.length + ' update' + (list.length > 1 ? 's' : '') + ' shared';

    list.forEach((item, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'status-row';

        const ringEl = document.createElement('div');
        ringEl.className = 'avatar-ring';
        const core = document.createElement('div');
        core.className = 'avatar-core';
        core.style.background = item.color || '#0b5345';
        core.style.color = '#fff';
        core.textContent = '✨';
        ringEl.appendChild(core);

        const body = document.createElement('div');
        body.className = 'row-body';
        const title = document.createElement('div');
        title.className = 'row-title';
        title.textContent = 'My Status #' + (idx + 1);
        const subEl = document.createElement('div');
        subEl.className = 'row-sub';
        subEl.textContent = (item.time || 'Just now') + ' • ' + (item.text || '');

        body.appendChild(title);
        body.appendChild(subEl);
        btn.appendChild(ringEl);
        btn.appendChild(body);
        btn.addEventListener('click', () => {
            openStory(null, <?php echo json_encode($displayName); ?>, item.time || 'Just now', '✨', item.text, 'chats.php', 'Back to Chats', item.color || '#0b5345');
        });
        container.appendChild(btn);
    });
}

function handleMyStatusTap() {
    const list = loadMyStatuses();
    if (list.length > 0) {
        const latest = list[0];
        openStory(null, <?php echo json_encode($displayName); ?>, latest.time || 'Just now', '✨', latest.text, 'chats.php', 'Back to Chats', latest.color || '#0b5345');
    } else {
        openComposer();
    }
}

function openComposer() {
    document.getElementById('composerModal').classList.add('open');
    document.getElementById('statusInput').focus();
}

function closeComposer() {
    document.getElementById('composerModal').classList.remove('open');
}

function saveMyStatus() {
    const input = document.getElementById('statusInput');
    const text = input.value.trim();
    if (!text) {
        showToast('Enter a short update first');
        return;
    }
    const list = loadMyStatuses();
    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    list.unshift({ text, color: selectedColor, time: 'Today, ' + timeStr });
    localStorage.setItem(STORAGE_STATUS, JSON.stringify(list.slice(0, 5)));
    input.value = '';
    closeComposer();
    renderMyStatuses();
    showToast('Status update shared');
}

function openStory(rowEl, name, time, emoji, text, ctaUrl, ctaLabel, bgColor) {
    if (rowEl) {
        const ring = rowEl.querySelector('.avatar-ring');
        if (ring) ring.classList.add('seen');
    }
    document.getElementById('storyIcon').textContent = emoji;
    document.getElementById('storyBigEmoji').textContent = emoji;
    document.getElementById('storyName').textContent = name;
    document.getElementById('storyTime').textContent = time;
    document.getElementById('storyText').textContent = text;
    document.getElementById('storyCardBody').style.background = bgColor || '#0b5345';

    const cta = document.getElementById('storyCtaLink');
    const label = document.getElementById('storyCtaLabel');
    if (ctaUrl) {
        cta.href = ctaUrl;
        label.textContent = ctaLabel || 'Open';
        cta.style.display = 'inline-flex';
    } else {
        cta.style.display = 'none';
    }

    document.getElementById('storyModal').classList.add('open');
    clearInterval(storyTimer);
    let pct = 0;
    const bar = document.getElementById('storyProgress');
    bar.style.width = '0%';
    storyTimer = setInterval(() => {
        pct += 2;
        bar.style.width = Math.min(pct, 100) + '%';
        if (pct >= 100) {
            closeStory();
        }
    }, 100);
}

function closeStory() {
    clearInterval(storyTimer);
    document.getElementById('storyModal').classList.remove('open');
}

function toggleFollow(btn) {
    const ch = btn.dataset.channel;
    const following = btn.classList.toggle('following');
    btn.textContent = following ? 'Following' : 'Follow';
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_CHANNELS) || '{}');
        saved[ch] = following;
        localStorage.setItem(STORAGE_CHANNELS, JSON.stringify(saved));
    } catch (_) {}
    showToast(following ? 'Following channel' : 'Unfollowed channel');
}

(function init() {
    renderMyStatuses();
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_CHANNELS) || '{}');
        document.querySelectorAll('.follow-btn').forEach((btn) => {
            const ch = btn.dataset.channel;
            if (typeof saved[ch] === 'boolean') {
                btn.classList.toggle('following', saved[ch]);
                btn.textContent = saved[ch] ? 'Following' : 'Follow';
            }
        });
    } catch (_) {}
})();
</script>
</body>
</html>
