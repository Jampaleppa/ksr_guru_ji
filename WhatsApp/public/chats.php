<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chats - KSR GURU JI</title>
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
            --sent-bg: #d9fdd3;
            --chat-bg: #efeae2;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 68px; color: var(--text-main); overflow-x: hidden; }

        /* Sticky Header */
        .app-header {
            background: var(--primary);
            padding: 0 16px;
            height: 58px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
            color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .app-header .brand { display: flex; align-items: center; gap: 10px; }
        .app-header .brand-icon { width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .app-header h1 { margin: 0; font-size: 1.2rem; font-weight: 700; letter-spacing: -0.3px; }
        .header-actions { display: flex; gap: 18px; font-size: 1.15rem; }
        .header-actions i { cursor: pointer; padding: 4px; min-width: 24px; text-align: center; }

        /* Search Bar */
        .search-bar-wrap {
            background: var(--bg-screen);
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
        }
        .search-inner {
            display: flex; align-items: center; gap: 10px;
            background: var(--bg-body); border-radius: 10px;
            padding: 9px 14px;
        }
        .search-inner i { color: var(--text-muted); font-size: 0.95rem; }
        .search-inner input { flex: 1; border: none; outline: none; background: transparent; font-size: 0.95rem; color: var(--text-main); }
        .search-inner input::placeholder { color: var(--text-muted); }

        /* Pull to refresh hint */
        .ptr-hint {
            text-align: center; padding: 10px;
            font-size: 0.78rem; color: var(--text-muted);
            display: flex; align-items: center; justify-content: center; gap: 6px;
            animation: fadeInDown 0.4s;
        }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        /* Chat List */
        .chat-list { background: var(--bg-screen); min-height: calc(100vh - 180px); }
        .chat-node {
            display: flex; align-items: center;
            padding: 12px 16px; cursor: pointer;
            transition: background 0.15s; position: relative;
            -webkit-tap-highlight-color: transparent;
        }
        .chat-node:active { background: #f5f6f6; }
        .chat-node::after { content: ''; position: absolute; bottom: 0; left: 76px; right: 0; height: 1px; background: var(--border); }

        .avatar {
            width: 52px; height: 52px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.5rem; flex-shrink: 0;
            margin-right: 14px; overflow: hidden; position: relative;
        }
        .online-dot {
            position: absolute; bottom: 2px; right: 2px;
            width: 12px; height: 12px; border-radius: 50%;
            background: #25d366; border: 2px solid white;
        }

        .chat-info { flex: 1; min-width: 0; }
        .chat-header-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
        .chat-name { font-weight: 600; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 60px); }
        .chat-time { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }
        .chat-time.unread-time { color: var(--primary); font-weight: 700; }

        .chat-preview-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .chat-preview { font-size: 0.88rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
        .chat-preview .tick { color: #53bdeb; margin-right: 2px; font-size: 0.75rem; }
        .unread-badge {
            background: var(--primary); color: white;
            font-size: 0.72rem; font-weight: 700;
            border-radius: 50px; min-width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 6px; flex-shrink: 0;
        }

        .no-results { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .no-results i { font-size: 3rem; margin-bottom: 12px; display: block; }

        /* FAB */
        .fab {
            position: fixed; bottom: 84px; right: 18px;
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; box-shadow: 0 4px 14px rgba(0,168,132,0.4);
            cursor: pointer; z-index: 90;
            transition: transform 0.2s, box-shadow 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        .fab:active { transform: scale(0.92); box-shadow: 0 2px 8px rgba(0,168,132,0.3); }
        /* Ripple */
        .fab::after {
            content: ''; position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.3);
            width: 100%; height: 100%;
            transform: scale(0); opacity: 0;
            transition: transform 0.5s, opacity 0.4s;
        }
        .fab.ripple::after { transform: scale(2.5); opacity: 0; }

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
            padding: 6px 0; min-width: 60px; min-height: 44px;
            justify-content: center;
        }
        .bnav-item .ico { font-size: 1.35rem; line-height: 1; }
        .bnav-item.active { color: var(--primary); font-weight: 700; }
        .bnav-item.active .ico { transform: scale(1.1); }

        /* ===== CHAT ROOM OVERLAY ===== */
        #chatRoomOverlay {
            display: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--chat-bg); z-index: 300;
            flex-direction: column;
        }
        .room-header {
            background: var(--primary); color: white;
            padding: 10px 14px; display: flex; align-items: center; gap: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15); flex-shrink: 0;
        }
        .room-header .back-btn { font-size: 1.15rem; cursor: pointer; min-width: 36px; min-height: 36px; display: flex; align-items: center; }
        .room-header .room-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .room-header .room-info { flex: 1; min-width: 0; }
        .room-header .room-name { font-weight: 700; font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .room-header .room-status { font-size: 0.78rem; opacity: 0.85; }
        .room-header .room-actions { display: flex; gap: 18px; font-size: 1.1rem; }
        .room-header .room-actions i { cursor: pointer; }

        .room-body {
            flex: 1; overflow-y: auto; padding: 16px 12px;
            display: flex; flex-direction: column; gap: 8px;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2300a884' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='10'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .date-pill { text-align: center; margin: 6px 0; }
        .date-pill span { background: rgba(255,255,255,0.88); box-shadow: 0 1px 1px rgba(0,0,0,0.08); border-radius: 8px; padding: 5px 12px; font-size: 0.75rem; color: #54656f; }

        .bubble {
            max-width: 78%; padding: 8px 12px 6px;
            border-radius: 10px; font-size: 0.92rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            position: relative; word-break: break-word;
        }
        .bubble-received { background: white; align-self: flex-start; border-top-left-radius: 2px; }
        .bubble-sent { background: var(--sent-bg); align-self: flex-end; border-top-right-radius: 2px; }
        .bubble-meta { display: flex; align-items: center; justify-content: flex-end; gap: 4px; margin-top: 3px; }
        .bubble-time { font-size: 0.68rem; color: var(--text-muted); }
        .bubble-ticks { font-size: 0.75rem; color: #53bdeb; }

        .room-footer { background: #f0f2f5; padding: 8px 12px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .msg-input-wrap { flex: 1; background: white; border-radius: 24px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; }
        .msg-input-wrap i { color: var(--text-muted); font-size: 1.2rem; cursor: pointer; min-width: 22px; }
        .msg-input-wrap input { flex: 1; border: none; outline: none; font-size: 0.95rem; color: var(--text-main); min-width: 0; }
        .send-btn {
            width: 46px; height: 46px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; cursor: pointer; flex-shrink: 0;
            transition: background 0.2s;
        }
        .send-btn:active { background: var(--primary-dark); }
    </style>
</head>
<body>

<!-- Chat List View -->
<div id="chatListView">
    <header class="app-header">
        <div class="brand">
            <div class="brand-icon">💬</div>
            <h1>KSR GURU JI</h1>
        </div>
        <div class="header-actions">
            <i class="fas fa-search" onclick="focusSearch()" title="Search"></i>
            <i class="fas fa-ellipsis-v" title="More"></i>
        </div>
    </header>

    <div class="search-bar-wrap">
        <div class="search-inner">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search or start new chat" oninput="filterChats()" autocomplete="off">
        </div>
    </div>

    <div class="ptr-hint" id="ptrHint" style="display:none;">
        <i class="fas fa-sync-alt fa-spin"></i> Refreshing chats…
    </div>

    <div class="chat-list" id="chatList">
        <!-- Chat items rendered by JS -->
    </div>
    <div class="no-results" id="noResults" style="display:none;">
        <i class="fas fa-search"></i>
        <div>No chats found</div>
    </div>

    <div class="fab" id="composeFab" onclick="fabRipple(this)" title="New Chat">
        <i class="fas fa-plus"></i>
    </div>

    <nav class="bottom-nav">
        <a class="bnav-item active" href="chats.php"><span class="ico">💬</span><span>Chats</span></a>
        <a class="bnav-item" href="updates.php"><span class="ico">🔄</span><span>Updates</span></a>
        <a class="bnav-item" href="calls.php"><span class="ico">📞</span><span>Calls</span></a>
        <a class="bnav-item" href="services.php"><span class="ico">🛍️</span><span>Services</span></a>
        <a class="bnav-item" href="profile.php"><span class="ico">👤</span><span>Profile</span></a>
    </nav>
</div>

<!-- Chat Room Overlay -->
<div id="chatRoomOverlay">
    <div class="room-header">
        <span class="back-btn" onclick="closeChat()"><i class="fas fa-arrow-left"></i></span>
        <div class="room-avatar" id="roomAvatar"></div>
        <div class="room-info">
            <div class="room-name" id="roomName"></div>
            <div class="room-status" id="roomStatus"></div>
        </div>
        <div class="room-actions">
            <i class="fas fa-phone-alt" title="Voice call"></i>
            <i class="fas fa-video" title="Video call"></i>
            <i class="fas fa-ellipsis-v" title="More"></i>
        </div>
    </div>

    <div class="room-body" id="roomBody"></div>

    <div class="room-footer">
        <i class="far fa-face-smile" style="font-size:1.5rem; color:#54656f; cursor:pointer; min-width:28px;"></i>
        <div class="msg-input-wrap">
            <input type="text" id="msgInput" placeholder="Type a message…" oninput="toggleSendIcon()">
            <i class="fas fa-paperclip" id="attachIcon"></i>
        </div>
        <div class="send-btn" onclick="sendMessage()">
            <i class="fas fa-microphone" id="sendIcon"></i>
        </div>
    </div>
</div>

<script>
    const chats = [
        { id: 1, name: 'KSR GURU JI Group', status: '24 members', preview: 'Welcome to the official group!', time: '10:30 AM', unread: 3, icon: '👥', color: '#00a884', online: false, messages: [
            { from: 'them', text: 'Good morning everyone! 🙏', time: '9:00 AM' },
            { from: 'them', text: 'Welcome to the official KSR GURU JI group.', time: '9:01 AM' },
            { from: 'me', text: 'Thank you! Happy to be here.', time: '9:05 AM' }
        ]},
        { id: 2, name: 'Rahul Sharma', status: 'online', preview: 'Hello, how are you?', time: '10:15 AM', unread: 1, icon: '👤', color: '#0284c7', online: true, messages: [
            { from: 'them', text: 'Hello! 👋', time: '10:10 AM' },
            { from: 'me', text: 'Hi, how are you?', time: '10:12 AM' },
            { from: 'them', text: 'I am good, thanks! What about you?', time: '10:15 AM' }
        ]},
        { id: 3, name: 'Priya', status: 'last seen 10 min ago', preview: '📷 Photo', time: 'Yesterday', unread: 0, icon: '👩', color: '#f43f5e', online: false, messages: [
            { from: 'them', text: 'Hey! Check this out 😊', time: 'Yesterday' },
            { from: 'me', text: 'Wow that looks great!', time: 'Yesterday' }
        ]},
        { id: 4, name: 'Dr. Rathod', status: 'last seen today', preview: 'Your appointment is confirmed.', time: 'Yesterday', unread: 0, icon: '🩺', color: '#10b981', online: false, messages: [
            { from: 'them', text: 'Your appointment for Monday 10 AM is confirmed.', time: 'Yesterday' },
            { from: 'me', text: 'Thank you doctor!', time: 'Yesterday' }
        ]},
        { id: 5, name: 'KSR Support', status: 'Support team', preview: 'How can we help you today?', time: 'Mon', unread: 0, icon: '🎯', color: '#8b5cf6', online: true, messages: [
            { from: 'them', text: 'Hello! Welcome to KSR Support. How can we help you today?', time: 'Mon' }
        ]}
    ];

    let currentChatId = null;

    function renderChats(list) {
        const container = document.getElementById('chatList');
        const noRes = document.getElementById('noResults');
        if (!list.length) { container.innerHTML = ''; noRes.style.display = 'flex'; return; }
        noRes.style.display = 'none';
        container.innerHTML = list.map(c => `
            <div class="chat-node" onclick="openChat(${c.id})">
                <div class="avatar" style="background:${c.color}; font-size:1.6rem;">
                    ${c.icon}
                    ${c.online ? '<div class="online-dot"></div>' : ''}
                </div>
                <div class="chat-info">
                    <div class="chat-header-row">
                        <div class="chat-name">${c.name}</div>
                        <div class="chat-time ${c.unread ? 'unread-time' : ''}">${c.time}</div>
                    </div>
                    <div class="chat-preview-row">
                        <div class="chat-preview">
                            ${c.unread === 0 ? '<span class="tick">✓✓</span>' : ''}${c.preview}
                        </div>
                        ${c.unread ? `<div class="unread-badge">${c.unread}</div>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function filterChats() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        renderChats(chats.filter(c => c.name.toLowerCase().includes(q) || c.preview.toLowerCase().includes(q)));
    }

    function focusSearch() { document.getElementById('searchInput').focus(); }

    function fabRipple(el) {
        el.classList.remove('ripple');
        void el.offsetWidth;
        el.classList.add('ripple');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function makeBubble(text, time, fromMe, extraHtml) {
        const div = document.createElement('div');
        div.className = 'bubble ' + (fromMe ? 'bubble-sent' : 'bubble-received');
        const textNode = document.createTextNode(text);
        div.appendChild(textNode);
        const meta = document.createElement('div');
        meta.className = 'bubble-meta';
        const tSpan = document.createElement('span');
        tSpan.className = 'bubble-time';
        tSpan.textContent = time;
        meta.appendChild(tSpan);
        if (fromMe) {
            const ticks = document.createElement('span');
            ticks.className = 'bubble-ticks';
            ticks.textContent = '✓✓';
            meta.appendChild(ticks);
        }
        if (extraHtml) {
            const extra = document.createElement('span');
            extra.innerHTML = extraHtml; // safe – only internal strings
            meta.appendChild(extra);
        }
        div.appendChild(meta);
        return div;
    }

    function openChat(id) {
        const chat = chats.find(c => c.id === id);
        if (!chat) return;
        currentChatId = id;

        document.getElementById('roomName').textContent = chat.name;
        document.getElementById('roomStatus').textContent = chat.status;
        document.getElementById('roomAvatar').textContent = chat.icon;
        document.getElementById('roomAvatar').style.background = chat.color;

        const body = document.getElementById('roomBody');
        body.textContent = '';
        const datePill = document.createElement('div');
        datePill.className = 'date-pill';
        datePill.innerHTML = '<span>Today</span>';
        body.appendChild(datePill);

        // Wire room header action buttons
        const headerPhone = document.querySelector('.room-actions .fa-phone-alt');
        const headerVideo = document.querySelector('.room-actions .fa-video');
        if (headerPhone) headerPhone.parentElement.onclick = () => { showToast('Opening Calls…'); setTimeout(() => { window.location.href = 'calls.php'; }, 700); };
        if (headerVideo) headerVideo.parentElement.onclick = () => { showToast('Starting video call…'); setTimeout(() => { window.location.href = '../../telehealth/appointment.php'; }, 700); };

        chat.messages.forEach(m => {
            body.appendChild(makeBubble(m.text, m.time, m.from === 'me', null));
        });
        body.scrollTop = body.scrollHeight;

        document.getElementById('chatRoomOverlay').style.display = 'flex';
        document.getElementById('chatListView').style.display = 'none';
        // Mark as read
        chat.unread = 0;
    }

    function closeChat() {
        document.getElementById('chatRoomOverlay').style.display = 'none';
        document.getElementById('chatListView').style.display = 'block';
        renderChats(chats);
    }

    function toggleSendIcon() {
        const val = document.getElementById('msgInput').value.trim();
        const icon = document.getElementById('sendIcon');
        const attach = document.getElementById('attachIcon');
        icon.className = val.length ? 'fas fa-paper-plane' : 'fas fa-microphone';
        attach.style.display = val.length ? 'none' : 'block';
    }

    function sendMessage() {
        const input = document.getElementById('msgInput');
        const text = input.value.trim();
        if (!text) return;

        const body = document.getElementById('roomBody');
        const now = new Date();
        const time = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

        body.appendChild(makeBubble(text, time, true, null));
        body.scrollTop = body.scrollHeight;

        input.value = '';
        document.getElementById('sendIcon').className = 'fas fa-microphone';
        document.getElementById('attachIcon').style.display = 'block';

        // Update preview in chat list
        const chat = chats.find(c => c.id === currentChatId);
        if (chat) { chat.preview = text; chat.time = time; }

        // WebSocket send
        if (window.ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ type: 'v2_realtime_chat', sender_id: 8421, content: text }));
        }
        // REST persistence
        fetch('../api/messages/send.php', {
            method: 'POST',
            body: JSON.stringify({ sender_id: 8421, conversation_id: currentChatId, content: text }),
            headers: { 'Content-Type': 'application/json' }
        }).catch(() => {});
    }

    // Allow Enter key in chat
    document.getElementById('msgInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

    function initWebSocket() {
        try {
            window.ws = new WebSocket('ws://localhost:8080');
            ws.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.type === 'chat_message') {
                        const body = document.getElementById('roomBody');
                        const now = new Date();
                        const time = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
                        body.appendChild(makeBubble(data.content || '', time, false, null));
                        body.scrollTop = body.scrollHeight;
                    }
                } catch(err) {}
            };
            ws.onclose = () => setTimeout(initWebSocket, 3000);
        } catch(e) {}
    }
    initWebSocket();

    // Pull-to-refresh hint on swipe down
    let touchStartY = 0;
    document.addEventListener('touchstart', e => { touchStartY = e.touches[0].clientY; }, { passive: true });
    document.addEventListener('touchmove', e => {
        const delta = e.touches[0].clientY - touchStartY;
        const ptr = document.getElementById('ptrHint');
        if (delta > 60 && window.scrollY === 0) { ptr.style.display = 'flex'; }
    }, { passive: true });
    document.addEventListener('touchend', () => {
        setTimeout(() => { document.getElementById('ptrHint').style.display = 'none'; }, 1200);
    }, { passive: true });

    // Init
    renderChats(chats);
</script>
</body>
</html>
