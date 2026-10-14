<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Updates - KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #ffffff; --text-main: #111b21; --text-muted: #667781; --border: #e9edef; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 70px; color: var(--text-main); }
        
        .app-header { background: var(--primary); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .nav-icons { display: flex; gap: 20px; font-size: 1.2rem; }
        
        .section-title { font-size: 1rem; font-weight: 600; padding: 15px 20px 5px; color: var(--text-main); }
        
        /* Status List Node */
        .status-node { display: flex; align-items: center; padding: 12px 20px; cursor: pointer; transition: background 0.2s; position: relative; }
        .status-node:active { background: #f5f6f6; }
        .status-node:after { content: ''; position: absolute; bottom: 0; right: 0; width: calc(100% - 85px); border-bottom: 1px solid var(--border); }
        
        /* Outline ring around avatar mimicking WhatsApp unread / read status logic */
        .avatar-ring { width: 56px; height: 56px; border-radius: 50%; padding: 3px; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; }
        .ring-unread { background: conic-gradient(#25d366 100%, transparent 0); } /* Green full */
        .ring-read { background: conic-gradient(#d1dbdf 100%, transparent 0); } /* Gray full */
        
        .avatar { width: 100%; height: 100%; border-radius: 50%; background: #dfe5e7; display: flex; align-items: center; justify-content: center; color: white; border: 2px solid white; overflow: hidden; position: relative; }
        
        /* 'My Status' floating Add Box */
        .add-badge { position: absolute; bottom: 0; right: 0; background: var(--primary); color: white; border-radius: 50%; border: 2px solid white; font-size: 0.6rem; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; }

        .status-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
        .status-name { font-weight: 600; font-size: 1.05rem; margin-bottom: 3px; }
        .status-time { font-size: 0.85rem; color: var(--text-muted); }
        
        .fab { position: fixed; bottom: 85px; right: 20px; width: 54px; height: 54px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); cursor: pointer; z-index: 90; transition: transform 0.2s; }
        .fab-text { position: fixed; bottom: 155px; right: 26px; width: 42px; height: 42px; background: #e9edef; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-main); font-size: 1.1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s; }

        /* Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; display: flex; justify-content: space-around; align-items: center; border-top: 1px solid var(--border); z-index: 100; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; gap: 4px; padding: 8px 0; width: 60px; }
        .bottom-nav-item.active { color: var(--primary); font-weight: 700; }
        .nav-icon-wrapper { font-size: 1.5rem; }
    </style>
</head>
<body>

    <header class="app-header">
        <h1>KSR GURU JI</h1>
        <div class="nav-icons">
            <i class="fas fa-camera"></i>
            <i class="fas fa-search"></i>
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </header>
    
    <div class="section-title">Status</div>

    <!-- My Status Controller -->
    <div class="status-node" onclick="triggerStatusUpload()">
        <div class="avatar-ring" style="background:transparent;">
            <div class="avatar" style="background: var(--text-muted); border:none;">
                <i class="fas fa-user" style="font-size:1.5rem;"></i>
                <div class="add-badge"><i class="fas fa-plus"></i></div>
            </div>
        </div>
        <div class="status-info">
            <div class="status-name">My status</div>
            <div class="status-time">Tap to add status update</div>
        </div>
    </div>
    
    <div style="font-size: 0.85rem; font-weight: 600; padding: 15px 20px 5px; color: var(--text-muted);">Recent updates</div>
    
    <!-- Recent Status 1 (Unread) -->
    <div class="status-node">
        <div class="avatar-ring ring-unread">
            <div class="avatar" style="background: #10b981;">
                <i class="fas fa-user"></i>
            </div>
        </div>
        <div class="status-info">
            <div class="status-name">Priya @ksr_priya</div>
            <div class="status-time">Just now</div>
        </div>
    </div>
    
    <!-- Recent Status 2 (Unread) -->
    <div class="status-node">
        <div class="avatar-ring ring-unread">
            <div class="avatar" style="background: #0284c7;">
                <i class="fas fa-user"></i>
            </div>
        </div>
        <div class="status-info">
            <div class="status-name">Rahul Sharma</div>
            <div class="status-time">45 minutes ago</div>
        </div>
    </div>

    <div style="font-size: 0.85rem; font-weight: 600; padding: 15px 20px 5px; color: var(--text-muted);">Viewed updates</div>

    <!-- Viewed Status (Read) -->
    <div class="status-node">
        <div class="avatar-ring ring-read">
            <div class="avatar" style="background: #ef4444;">
                <i class="fas fa-user-md"></i>
            </div>
        </div>
        <div class="status-info">
            <div class="status-name">Dr. Rathod</div>
            <div class="status-time">Yesterday, 9:20 PM</div>
        </div>
    </div>

    <!-- Camera / Status Actions -->
    <div class="fab-text"><i class="fas fa-pen"></i></div>
    <div class="fab"><i class="fas fa-camera"></i></div>

    <!-- Unified Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="chats.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="far fa-comment"></i></div>
            <span>Chats</span>
        </a>
        <a href="updates.php" class="bottom-nav-item active">
            <div class="nav-icon-wrapper"><i class="fas fa-dot-circle"></i></div>
            <span>Updates</span>
        </a>
        <a href="calls.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-phone-alt"></i></div>
            <span>Calls</span>
        </a>
    </nav>
    
    <script>
        function triggerStatusUpload() {
            alert('Trigger 24-Hour Ephemeral Status logic mapped to POST /api/status/upload');
        }
    </script>
</body>
</html>
