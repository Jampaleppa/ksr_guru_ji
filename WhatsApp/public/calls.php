<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Calls - KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --bg-body: #f0f2f5;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
            --missed: #dc2626;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 68px; color: var(--text-main); overflow-x: hidden; }

        .app-header {
            background: var(--primary); height: 58px; padding: 0 16px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100; color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .app-header h1 { margin: 0; font-size: 1.2rem; font-weight: 700; }
        .header-actions { display: flex; gap: 18px; font-size: 1.1rem; }
        .header-actions i { cursor: pointer; padding: 4px; min-width: 24px; }

        /* Filter Tabs */
        .filter-tabs {
            background: var(--bg-screen);
            display: flex; border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            flex: 1; padding: 13px; text-align: center;
            font-size: 0.9rem; font-weight: 600;
            color: var(--text-muted); cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s; min-height: 44px;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-btn .missed-count {
            background: var(--missed); color: white;
            font-size: 0.65rem; border-radius: 50px;
            padding: 2px 6px; margin-left: 6px;
        }

        /* Create Call Link Card */
        .call-link-card {
            background: var(--bg-screen); display: flex; align-items: center;
            padding: 16px 16px; cursor: pointer; border-bottom: 8px solid var(--bg-body);
            transition: background 0.15s;
        }
        .call-link-card:active { background: #f5f6f6; }
        .call-link-icon {
            width: 50px; height: 50px; border-radius: 50%;
            background: var(--primary-light, #e7f8f4); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0; margin-right: 14px;
        }
        .call-link-info .title { font-weight: 600; font-size: 1rem; margin-bottom: 3px; }
        .call-link-info .sub { font-size: 0.83rem; color: var(--text-muted); }

        /* Section title */
        .section-label { font-size: 0.82rem; font-weight: 700; padding: 14px 16px 6px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; background: var(--bg-screen); }

        /* Call Node */
        .call-node {
            display: flex; align-items: center;
            padding: 12px 16px; background: var(--bg-screen);
            cursor: pointer; transition: background 0.15s;
            position: relative;
        }
        .call-node:active { background: #f5f6f6; }
        .call-node::after { content: ''; position: absolute; bottom: 0; left: 80px; right: 0; height: 1px; background: var(--border); }
        .call-node.hidden { display: none; }

        .avatar {
            width: 50px; height: 50px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem; flex-shrink: 0; margin-right: 14px;
        }
        .call-info { flex: 1; min-width: 0; }
        .call-name { font-weight: 600; font-size: 1rem; margin-bottom: 4px; }
        .call-name.missed { color: var(--missed); }
        .call-meta { display: flex; align-items: center; gap: 7px; font-size: 0.82rem; color: var(--text-muted); }
        .call-meta .arrow-icon { font-size: 0.75rem; }
        .call-meta .arrow-icon.missed { color: var(--missed); }
        .call-meta .arrow-icon.outgoing { color: var(--primary); }
        .call-meta .arrow-icon.incoming { color: var(--primary); }
        .call-type-icon {
            font-size: 1.1rem; color: var(--primary);
            padding: 10px; cursor: pointer; min-width: 44px;
            text-align: center; min-height: 44px;
            display: flex; align-items: center; justify-content: center;
        }
        .callback-btn {
            background: none; border: none; cursor: pointer;
            color: var(--primary); font-size: 1.15rem;
            padding: 10px 12px; border-radius: 50%;
            min-width: 44px; min-height: 44px;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .callback-btn:hover { background: var(--bg-body); }

        /* FAB */
        .fab {
            position: fixed; bottom: 84px; right: 18px;
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; box-shadow: 0 4px 14px rgba(0,168,132,0.4);
            cursor: pointer; z-index: 90; transition: transform 0.2s;
        }
        .fab:active { transform: scale(0.92); }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%;
            height: 60px; background: var(--bg-screen);
            display: flex; justify-content: space-around; align-items: center;
            border-top: 1px solid var(--border); z-index: 100;
            box-shadow: 0 -1px 6px rgba(0,0,0,0.06);
        }
        .bnav-item {
            display: flex; flex-direction: column; align-items: center;
            text-decoration: none; color: var(--text-muted);
            font-size: 0.7rem; font-weight: 500; gap: 2px;
            padding: 6px 0; min-width: 60px; min-height: 44px; justify-content: center;
        }
        .bnav-item .ico { font-size: 1.35rem; line-height: 1; }
        .bnav-item.active { color: var(--primary); font-weight: 700; }
    </style>
</head>
<body>

    <header class="app-header">
        <h1>Calls</h1>
        <div class="header-actions">
            <button type="button" onclick="toggleCallSearch()" aria-label="Search calls" style="background:none;border:none;color:inherit;font-size:1.1rem;cursor:pointer;padding:6px;min-width:36px;min-height:36px;">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="void(0)" aria-label="More options" style="background:none;border:none;color:inherit;font-size:1.1rem;cursor:pointer;padding:6px;min-width:36px;min-height:36px;">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </header>

    <!-- Search bar (hidden by default) -->
    <div id="callSearchBar" style="display:none;padding:10px 14px;background:#fff;border-bottom:1px solid #e9edef;">
        <div style="display:flex;align-items:center;gap:10px;background:#f0f2f5;border-radius:12px;padding:9px 13px;">
            <i class="fas fa-search" style="color:#667781;"></i>
            <input type="search" id="callSearchInput" placeholder="Search call logs…"
                oninput="filterCallLog()" autocomplete="off"
                style="flex:1;border:none;background:transparent;outline:none;font-size:16px;color:#111b21;">
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button type="button" class="tab-btn active" id="tab-all" onclick="filterCalls('all')">All</button>
        <button type="button" class="tab-btn" id="tab-missed" onclick="filterCalls('missed')">
            Missed <span class="missed-count">2</span>
        </button>
    </div>

    <!-- Create Call Link -->
    <button type="button" class="call-link-card" onclick="openCallLinkModal()" aria-label="Create a call link">
        <div class="call-link-icon"><i class="fas fa-link"></i></div>
        <div class="call-link-info">
            <div class="title">Create call link</div>
            <div class="sub">Share a link for your KSR GURU JI call</div>
        </div>
    </button>

    <!-- Call Log -->
    <div class="section-label" id="recentLabel">Recent</div>

    <!-- Missed Video Call - Priya -->
    <div class="call-node" data-type="missed" onclick="void(0)">
        <div class="avatar" style="background:#f43f5e;">👩</div>
        <div class="call-info">
            <div class="call-name missed">Priya</div>
            <div class="call-meta">
                <i class="fas fa-arrow-down arrow-icon missed"></i>
                <i class="fas fa-video" style="color:var(--text-muted); font-size:0.85rem;"></i>
                Yesterday, 10:45 AM
            </div>
        </div>
        <button class="callback-btn" onclick="event.stopPropagation(); callBack('Priya', 'video');" title="Video call back">
            <i class="fas fa-video"></i>
        </button>
    </div>

    <!-- Missed Voice Call - Unknown -->
    <div class="call-node" data-type="missed" onclick="void(0)">
        <div class="avatar" style="background:#ef4444;">👤</div>
        <div class="call-info">
            <div class="call-name missed">+91 98001 23456</div>
            <div class="call-meta">
                <i class="fas fa-arrow-down arrow-icon missed"></i>
                <i class="fas fa-phone-alt" style="color:var(--text-muted); font-size:0.85rem;"></i>
                Yesterday, 8:10 AM
            </div>
        </div>
        <button class="callback-btn" onclick="event.stopPropagation(); callBack('+91 98001 23456', 'voice');" title="Call back">
            <i class="fas fa-phone-alt"></i>
        </button>
    </div>

    <!-- Outgoing Audio Call - Rahul -->
    <div class="call-node" data-type="all" onclick="void(0)">
        <div class="avatar" style="background:#0284c7;">👤</div>
        <div class="call-info">
            <div class="call-name">Rahul Sharma</div>
            <div class="call-meta">
                <i class="fas fa-arrow-up arrow-icon outgoing"></i>
                <i class="fas fa-phone-alt" style="color:var(--text-muted); font-size:0.85rem;"></i>
                Yesterday, 9:20 AM · 4 min 32 sec
            </div>
        </div>
        <button class="callback-btn" onclick="event.stopPropagation(); callBack('Rahul', 'voice');" title="Call back">
            <i class="fas fa-phone-alt"></i>
        </button>
    </div>

    <!-- Incoming Audio Call - KSR Enterprise -->
    <div class="call-node" data-type="all" onclick="void(0)">
        <div class="avatar" style="background:#4f46e5;">🏢</div>
        <div class="call-info">
            <div class="call-name">KSR Enterprise</div>
            <div class="call-meta">
                <i class="fas fa-arrow-down arrow-icon incoming"></i>
                <i class="fas fa-phone-alt" style="color:var(--text-muted); font-size:0.85rem;"></i>
                Monday, 3:15 PM · 12 min 05 sec
            </div>
        </div>
        <button class="callback-btn" onclick="event.stopPropagation(); callBack('KSR Enterprise', 'voice');" title="Call back">
            <i class="fas fa-phone-alt"></i>
        </button>
    </div>

    <!-- Outgoing Video Call - Dr. Rathod -->
    <div class="call-node" data-type="all" onclick="void(0)">
        <div class="avatar" style="background:#10b981;">🩺</div>
        <div class="call-info">
            <div class="call-name">Dr. Rathod</div>
            <div class="call-meta">
                <i class="fas fa-arrow-up arrow-icon outgoing"></i>
                <i class="fas fa-video" style="color:var(--text-muted); font-size:0.85rem;"></i>
                Sunday, 11:00 AM · 8 min 44 sec
            </div>
        </div>
        <button class="callback-btn" onclick="event.stopPropagation(); callBack('Dr. Rathod', 'video');" title="Video call back">
            <i class="fas fa-video"></i>
        </button>
    </div>

    <!-- FAB New Call -->
    <button type="button" class="fab" title="New call" onclick="openNewCallModal()" aria-label="Start a new call">
        <i class="fas fa-phone-alt"></i>
    </button>

    <nav class="bottom-nav">
        <a class="bnav-item" href="chats.php"><span class="ico">💬</span><span>Chats</span></a>
        <a class="bnav-item" href="updates.php"><span class="ico">🔄</span><span>Updates</span></a>
        <a class="bnav-item active" href="calls.php"><span class="ico">📞</span><span>Calls</span></a>
        <a class="bnav-item" href="services.php"><span class="ico">🛍️</span><span>Services</span></a>
        <a class="bnav-item" href="profile.php"><span class="ico">👤</span><span>Profile</span></a>
    </nav>

    <!-- Call Link Modal -->
    <div id="callLinkModal" role="dialog" aria-modal="true" aria-label="Create call link"
        style="display:none;position:fixed;inset:0;background:rgba(17,27,33,0.52);z-index:300;align-items:flex-end;justify-content:center;padding:12px;">
        <div style="width:100%;max-width:520px;background:#fff;border-radius:20px;padding:22px 20px 32px;box-shadow:0 10px 32px rgba(0,0,0,0.18);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="font-size:1.1rem;font-weight:800;">Create Call Link</h2>
                <button type="button" onclick="closeCallLinkModal()" style="background:#f0f2f5;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1rem;" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div id="callLinkDisplay" style="font-size:0.88rem;background:#f0f2f5;border-radius:12px;padding:12px 14px;word-break:break-all;margin-bottom:14px;color:#111b21;"></div>
            <button type="button" onclick="copyCallLink()" style="width:100%;border:none;border-radius:12px;background:#00a884;color:#fff;font-size:1rem;font-weight:700;padding:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;min-height:48px;">
                <i class="fas fa-copy"></i> Copy Link
            </button>
            <div style="margin-top:12px;text-align:center;">
                <a href="tel:+916362079192" style="color:#00a884;font-weight:700;font-size:0.9rem;text-decoration:none;"><i class="fas fa-phone-alt"></i> Doctor Audio Consult: +91 6362 079192</a>
            </div>
        </div>
    </div>

    <!-- New Call Picker Modal -->
    <div id="newCallModal" role="dialog" aria-modal="true" aria-label="Start a new call"
        style="display:none;position:fixed;inset:0;background:rgba(17,27,33,0.52);z-index:300;align-items:flex-end;justify-content:center;padding:12px;">
        <div style="width:100%;max-width:520px;background:#fff;border-radius:20px;padding:22px 20px 32px;box-shadow:0 10px 32px rgba(0,0,0,0.18);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <h2 style="font-size:1.1rem;font-weight:800;">Start a Call</h2>
                <button type="button" onclick="closeNewCallModal()" style="background:#f0f2f5;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1rem;" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <a href="tel:+916362079192" style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;background:#e7fce3;text-decoration:none;color:#111b21;margin-bottom:10px;">
                <div style="width:44px;height:44px;border-radius:50%;background:#00a884;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">🩺</div>
                <div><div style="font-weight:700;">Doctor Audio Consult</div><div style="font-size:0.82rem;color:#667781;">+91 6362 079192</div></div>
            </a>
            <a href="https://wa.me/918088584421" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;background:#f0faf5;text-decoration:none;color:#111b21;margin-bottom:10px;">
                <div style="width:44px;height:44px;border-radius:50%;background:#25d366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">💬</div>
                <div><div style="font-weight:700;">KSR Support Chat</div><div style="font-size:0.82rem;color:#667781;">+91 80885 84421</div></div>
            </a>
            <a href="chats.php" style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;background:#f8f9fa;text-decoration:none;color:#111b21;">
                <div style="width:44px;height:44px;border-radius:50%;background:#667781;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">👤</div>
                <div><div style="font-weight:700;">Search Contacts</div><div style="font-size:0.82rem;color:#667781;">Start from your chats list</div></div>
            </a>
        </div>
    </div>

    <script>
        function filterCalls(type) {
            document.getElementById('tab-all').classList.toggle('active', type === 'all');
            document.getElementById('tab-missed').classList.toggle('active', type === 'missed');
            document.querySelectorAll('.call-node').forEach(node => {
                const nodeType = node.getAttribute('data-type');
                node.classList.toggle('hidden', type === 'missed' && nodeType !== 'missed');
            });
        }

        function toggleCallSearch() {
            const bar = document.getElementById('callSearchBar');
            const visible = bar.style.display !== 'none';
            bar.style.display = visible ? 'none' : 'flex';
            if (!visible) document.getElementById('callSearchInput').focus();
        }

        function filterCallLog() {
            const q = document.getElementById('callSearchInput').value.trim().toLowerCase();
            document.querySelectorAll('.call-node').forEach(node => {
                const name = (node.querySelector('.call-name') || {}).textContent || '';
                node.style.display = (!q || name.toLowerCase().includes(q)) ? '' : 'none';
            });
        }

        function openCallLinkModal() {
            const link = window.location.origin + '/WhatsApp/public/calls.php?join=' + Math.random().toString(36).slice(2, 9);
            document.getElementById('callLinkDisplay').textContent = link;
            document.getElementById('callLinkModal').style.display = 'flex';
        }
        function closeCallLinkModal() { document.getElementById('callLinkModal').style.display = 'none'; }

        function copyCallLink() {
            const text = document.getElementById('callLinkDisplay').textContent;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
            } else {
                fallbackCopy(text);
            }
            showCallToast('Link copied!');
        }

        function openNewCallModal() { document.getElementById('newCallModal').style.display = 'flex'; }
        function closeNewCallModal() { document.getElementById('newCallModal').style.display = 'none'; }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (_) {}
            document.body.removeChild(ta);
        }

        function callBack(name, callType) {
            const icon = callType === 'video' ? '📹' : '📞';
            const msg = callType === 'video' ? 'Starting video call with ' : 'Calling ';
            showCallToast(icon + ' ' + msg + name + '…');
        }

        function showCallToast(msg) {
            let t = document.getElementById('callToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'callToast';
                t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#111b21;color:#fff;padding:10px 18px;border-radius:999px;font-size:0.85rem;z-index:9999;display:none;white-space:nowrap;';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 2200);
        }

        // Close modals on backdrop click
        ['callLinkModal', 'newCallModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.getElementById('callLinkModal').style.display = 'none';
                document.getElementById('newCallModal').style.display = 'none';
            }
        });
    </script>

</body>
</html>
