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
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #f0f2f5; --bg-screen: #ffffff; --text-main: #111b21; --text-muted: #667781; --border: #e9edef; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 70px; color: var(--text-main); }
        
        /* Modern App Header */
        .app-header { background: var(--primary); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .nav-icons { display: flex; gap: 20px; font-size: 1.2rem; }
        
        /* Chat List Container */
        .chat-list-container { background: var(--bg-screen); min-height: calc(100vh - 130px); }
        
        /* Single Chat Node */
        .chat-node { display: flex; align-items: center; padding: 12px 15px; cursor: pointer; transition: background 0.2s; position: relative; }
        .chat-node:active { background: #f5f6f6; }
        .chat-node:after { content: ''; position: absolute; bottom: 0; right: 0; width: calc(100% - 75px); border-bottom: 1px solid var(--border); }
        
        .avatar { width: 50px; height: 50px; border-radius: 50%; background: #dfe5e7; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; flex-shrink: 0; margin-right: 15px; overflow: hidden; position: relative; }
        
        .chat-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
        .chat-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 3px; }
        .chat-name { font-weight: 600; font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display:flex; align-items:center; gap:5px;}
        .chat-time { font-size: 0.75rem; color: var(--text-muted); }
        
        .chat-preview-box { display: flex; justify-content: space-between; align-items: center; }
        .chat-preview { font-size: 0.9rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right:10px; }
        
        /* Floating Action Button */
        .fab { position: fixed; bottom: 85px; right: 20px; width: 54px; height: 54px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); cursor: pointer; z-index: 90; transition: transform 0.2s; }
        .fab:active { transform: scale(0.95); }
        
        /* Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; display: flex; justify-content: space-around; align-items: center; border-top: 1px solid var(--border); z-index: 100; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; gap: 4px; padding: 8px 0; width: 60px; }
        .bottom-nav-item.active { color: var(--primary); font-weight: 700; }
        .nav-icon-wrapper { font-size: 1.5rem; }
        
        /* Chat Room Screen (Hidden by Default) */
        #personalChatScreen { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #efeae2; z-index: 200; flex-direction: column; }
        .chat-room-header { background: var(--primary); color: white; padding: 10px 15px; display: flex; align-items: center; gap: 15px; }
        .chat-room-body { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: contain; }
        .chat-bubble { max-width: 75%; padding: 8px 12px; border-radius: 8px; font-size: 0.95rem; box-shadow: 0 1px 1px rgba(0,0,0,0.05); position: relative; }
        .chat-bubble-received { background: white; align-self: flex-start; border-top-left-radius: 0; }
        .chat-bubble-sent { background: #d9fdd3; align-self: flex-end; border-top-right-radius: 0; }
        .chat-room-footer { background: #f0f2f5; padding: 10px 15px; display: flex; align-items: center; gap: 10px; }
        .message-input-wrapper { flex: 1; background: white; border-radius: 24px; padding: 10px 15px; display: flex; align-items: center; gap: 10px; }
        .message-input-wrapper input { flex: 1; border: none; outline: none; font-size: 1rem; }
        .send-btn { width: 45px; height: 45px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: pointer; }
    </style>
</head>
<body>

<!-- CHAT LIST VIEW MAP -->
<div id="chatListScreen">
    <header class="app-header">
        <h1><i class="fas fa-bars"></i> KSR GURU JI</h1>
        <div class="nav-icons">
            <i class="fas fa-search"></i>
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </header>

    <div class="chat-list-container">
        
        <!-- Node 1: Rahul -->
        <div class="chat-node" onclick="openChat('Rahul', 'online', 'fas fa-user')">
            <div class="avatar" style="background:#0284c7;"><i class="fas fa-user" style="font-size:1.8rem;"></i></div>
            <div class="chat-info">
                <div class="chat-header">
                    <div class="chat-name">Rahul</div>
                    <div class="chat-time">10:30 PM</div>
                </div>
                <div class="chat-preview-box">
                    <div class="chat-preview">Hello, how are you?</div>
                </div>
            </div>
        </div>

        <!-- Node 2: KSR GURU JI Group -->
        <div class="chat-node" onclick="openChat('KSR GURU JI Group', '15 members', 'fas fa-users')">
            <div class="avatar" style="background:#10b981;"><i class="fas fa-users" style="font-size:1.8rem;"></i></div>
            <div class="chat-info">
                <div class="chat-header">
                    <div class="chat-name">KSR GURU JI Group</div>
                    <div class="chat-time">09:15 AM</div>
                </div>
                <div class="chat-preview-box">
                    <div class="chat-preview">Welcome to the group</div>
                </div>
            </div>
        </div>
        
        <!-- Node 3: Priya -->
        <div class="chat-node" onclick="openChat('Priya', 'last seen exactly 10 min ago', 'fas fa-user')">
            <div class="avatar" style="background:#f43f5e;"><i class="fas fa-user-alt" style="font-size:1.8rem;"></i></div>
            <div class="chat-info">
                <div class="chat-header">
                    <div class="chat-name">Priya</div>
                    <div class="chat-time">Yesterday</div>
                </div>
                <div class="chat-preview-box">
                    <div class="chat-preview"><i class="fas fa-camera" style="margin-right:5px;"></i> Photo</div>
                </div>
            </div>
        </div>
        
    </div>

    <div class="fab"><i class="fas fa-plus"></i></div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="chats.php" class="bottom-nav-item active">
            <div class="nav-icon-wrapper"><i class="fas fa-comment"></i></div>
            <span>Chats</span>
        </a>
        <a href="updates.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-dot-circle"></i></div>
            <span>Updates</span>
        </a>
        <a href="calls.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-phone-alt"></i></div>
            <span>Calls</span>
        </a>
    </nav>
</div>


<!-- CHAT SCREEN OVERLAY -->
<div id="personalChatScreen">
    <div class="chat-room-header">
        <i class="fas fa-arrow-left" style="font-size:1.2rem; cursor:pointer;" onclick="closeChat()"></i>
        <div class="avatar" style="width:40px; height:40px; margin-right:0;"><i class="fas fa-user" id="chatRoomIcon"></i></div>
        <div style="flex:1;">
            <div style="font-weight:600; font-size:1.1rem; color:white;" id="chatRoomName">Rahul</div>
            <div style="font-size:0.8rem; font-weight:400; color:rgba(255,255,255,0.8);" id="chatRoomStatus">online</div>
        </div>
        <div style="display:flex; gap:20px; font-size:1.2rem;">
            <i class="fas fa-phone-alt"></i>
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </div>
    
    <div class="chat-room-body" id="chatMessageCanvas">
        <div style="text-align:center; margin: 10px 0;"><span style="background:rgba(255,255,255,0.8); box-shadow:0 1px 1px rgba(0,0,0,0.05); border-radius:8px; padding:6px 12px; font-size:0.75rem; color:#54656f;">Today</span></div>
        
        <div class="chat-bubble chat-bubble-received">Hello! 👋</div>
        <div class="chat-bubble chat-bubble-sent">Hi, how are you?</div>
        <div class="chat-bubble chat-bubble-received">I am good.</div>
    </div>
    
    <div class="chat-room-footer">
        <i class="far fa-face-smile" style="font-size:1.5rem; color:#54656f; cursor:pointer; padding:5px;"></i>
        <i class="fas fa-paperclip" style="font-size:1.3rem; color:#54656f; margin-left:10px;"></i>
        <div class="message-input-wrapper">
            <input type="text" id="typeMsgInput" placeholder="Type a message...">
        </div>
        <div class="send-btn" onclick="sendMsg()"><i class="fas fa-microphone" id="sendIconBtn"></i></div>
    </div>
</div>

<script>
    document.getElementById('typeMsgInput').addEventListener('input', function() {
        const icon = document.getElementById('sendIconBtn');
        if(this.value.trim().length > 0) { icon.className = 'fas fa-paper-plane'; } 
        else { icon.className = 'fas fa-microphone'; }
    });

    function openChat(name, status, iconClass) {
        document.getElementById('chatListScreen').style.display = 'none';
        document.getElementById('personalChatScreen').style.display = 'flex';
        document.getElementById('chatRoomName').innerText = name;
        document.getElementById('chatRoomStatus').innerText = status;
        document.getElementById('chatRoomIcon').className = iconClass;
        
        // Reset purely for UI testing rendering map
        if(name === 'Rahul') {
            document.getElementById('chatMessageCanvas').innerHTML = `
                <div style="text-align:center; margin: 10px 0;"><span style="background:rgba(255,255,255,0.8); border-radius:8px; padding:6px 12px; font-size:0.75rem;">Today</span></div>
                <div class="chat-bubble chat-bubble-received">Hello! 👋</div>
                <div class="chat-bubble chat-bubble-sent">Hi, how are you?</div>
                <div class="chat-bubble chat-bubble-received">I am good.</div>
            `;
        } else {
            document.getElementById('chatMessageCanvas').innerHTML = `
                <div style="text-align:center; margin: 10px 0;"><span style="background:rgba(255,255,255,0.8); border-radius:8px; padding:6px 12px; font-size:0.75rem;">Today</span></div>
            `;
        }
    }

    function closeChat() {
        document.getElementById('personalChatScreen').style.display = 'none';
        document.getElementById('chatListScreen').style.display = 'block';
    }

    let ws;

    // Securely Bind Engine V2 WebSockets
    function initWebSocket() {
        ws = new WebSocket('ws://localhost:8080');
        
        ws.onopen = function() {
            console.log('KSR GURU JI v2.0 Live Transmission Linked');
            const ind = document.getElementById('liveStatusIndicator');
            const txt = document.getElementById('liveStatusText');
            if (ind) ind.style.color = '#25d366'; // Green connected ping
            if (txt) txt.innerText = 'Online';
        };

        ws.onmessage = function(e) {
            const data = JSON.parse(e.data);
            if (data.type === 'chat_message') {
                const canvas = document.getElementById('chatMessageCanvas');
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble chat-bubble-received';
                bubble.innerText = data.content;
                canvas.appendChild(bubble);
                canvas.scrollTop = canvas.scrollHeight;
            }
        };

        ws.onclose = function() {
            const ind = document.getElementById('liveStatusIndicator');
            const txt = document.getElementById('liveStatusText');
            if (ind) ind.style.color = '#dc2626'; // Disconnect red ping
            if (txt) txt.innerText = 'Disconnected';
            setTimeout(initWebSocket, 3000); // Reconnection heartbeat
        };
    }
    
    // Boot socket
    initWebSocket();

    function sendMsg() {
        const input = document.getElementById('typeMsgInput');
        const text = input.value.trim();
        if(!text) return;
        
        // Render UI locally immediately (Optimistic UI)
        const canvas = document.getElementById('chatMessageCanvas');
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble chat-bubble-sent';
        bubble.innerText = text;
        canvas.appendChild(bubble);
        
        input.value = '';
        document.getElementById('sendIconBtn').className = 'fas fa-microphone';
        canvas.scrollTop = canvas.scrollHeight;
        
        // 1. Array Execution directly through V2 WebSockets (Zero Latency)
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'v2_realtime_chat',
                sender_id: 8421, // Auth ID Mock
                content: text
            }));
        }
        
        // 2. Heavy Engine Database Persistence Sync (REST Gateway)
        fetch('../api/messages/send.php', {
            method: 'POST',
            body: JSON.stringify({
                sender_id: 8421,
                conversation_id: 1, 
                content: text
            }),
            headers: { 'Content-Type': 'application/json' }
        })
        .catch(err => console.error(err));
    }
</script>
</body>
</html>
