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
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #f0f2f5; --bg-screen: #ffffff; --text-main: #111b21; --text-muted: #667781; --border: #e9edef; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 70px; color: var(--text-main); }
        
        .app-header { background: var(--primary); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .nav-icons { display: flex; gap: 20px; font-size: 1.2rem; }
        
        .container { background: var(--bg-screen); min-height: calc(100vh - 60px); }
        .section-title { font-size: 1rem; font-weight: 600; padding: 15px 20px 5px; color: var(--text-main); }
        
        /* Create Call Link Header */
        .call-link-node { display: flex; align-items: center; padding: 15px 20px; cursor: pointer; transition: background 0.2s; position: relative; border-bottom: 8px solid var(--bg-body); }
        .call-link-icon { width: 50px; height: 50px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; flex-shrink: 0; margin-right: 15px; }
        
        /* Standard Call Log Node */
        .call-node { display: flex; align-items: center; padding: 12px 20px; cursor: pointer; transition: background 0.2s; position: relative; }
        .call-node:active { background: #f5f6f6; }
        .call-node:after { content: ''; position: absolute; bottom: 0; right: 0; width: calc(100% - 85px); border-bottom: 1px solid var(--border); }
        
        .avatar { width: 50px; height: 50px; border-radius: 50%; background: #dfe5e7; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; flex-shrink: 0; margin-right: 15px; overflow: hidden; }
        
        .call-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
        .call-name { font-weight: 600; font-size: 1.05rem; margin-bottom: 3px; color: var(--text-main); }
        .call-missed { color: #dc2626; }
        .call-time { font-size: 0.85rem; color: var(--text-muted); display:flex; align-items:center; gap:5px; }
        
        .type-icon { font-size: 1.2rem; color: var(--primary); padding: 10px; cursor: pointer; }
        
        .fab { position: fixed; bottom: 85px; right: 20px; width: 54px; height: 54px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); cursor: pointer; z-index: 90; transition: transform 0.2s; }

        /* Bottom Nav */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; display: flex; justify-content: space-around; align-items: center; border-top: 1px solid var(--border); z-index: 100; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; gap: 4px; padding: 8px 0; width: 60px; }
        .bottom-nav-item.active { color: var(--primary); font-weight: 700; }
        .nav-icon-wrapper { font-size: 1.5rem; }
    </style>
</head>
<body>

    <header class="app-header">
        <h1>Calls</h1>
        <div class="nav-icons">
            <i class="fas fa-search"></i>
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </header>
    
    <div class="container">
        
        <!-- Create Call Link Vector -->
        <div class="call-link-node">
            <div class="call-link-icon"><i class="fas fa-link"></i></div>
            <div class="call-info">
                <div class="call-name">Create call link</div>
                <div class="call-time">Share a link for your KSR GURU JI call</div>
            </div>
        </div>

        <div class="section-title">Recent</div>
        
        <!-- Missed Video Call -->
        <div class="call-node">
            <div class="avatar" style="background: #f43f5e;"><i class="fas fa-user-alt"></i></div>
            <div class="call-info">
                <div class="call-name call-missed">Priya</div>
                <div class="call-time"><i class="fas fa-arrow-down" style="color:#dc2626;"></i> Yesterday, 10:45 AM</div>
            </div>
            <div class="type-icon"><i class="fas fa-video"></i></div>
        </div>

        <!-- Outgoing Audio Call -->
        <div class="call-node">
            <div class="avatar" style="background: #0284c7;"><i class="fas fa-user"></i></div>
            <div class="call-info">
                <div class="call-name">Rahul</div>
                <div class="call-time"><i class="fas fa-arrow-up" style="color:#25d366;"></i> Yesterday, 9:20 AM</div>
            </div>
            <div class="type-icon"><i class="fas fa-phone-alt"></i></div>
        </div>
        
        <!-- Incoming Audio Call (Answered) -->
        <div class="call-node">
            <div class="avatar" style="background: #4f46e5;"><i class="fas fa-building"></i></div>
            <div class="call-info">
                <div class="call-name">KSR Enterprise</div>
                <div class="call-time"><i class="fas fa-arrow-down" style="color:#25d366;"></i> Monday, 3:15 PM</div>
            </div>
            <div class="type-icon"><i class="fas fa-phone-alt"></i></div>
        </div>

    </div>

    <!-- Floating Action Button -->
    <div class="fab"><i class="fas fa-phone-alt"></i><i class="fas fa-plus" style="font-size: 0.8rem; margin-top:-10px; margin-left: 2px;"></i></div>

    <!-- Unified Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="chats.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="far fa-comment"></i></div>
            <span>Chats</span>
        </a>
        <a href="updates.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-dot-circle"></i></div>
            <span>Updates</span>
        </a>
        <a href="calls.php" class="bottom-nav-item active">
            <div class="nav-icon-wrapper"><i class="fas fa-phone-alt"></i></div>
            <span>Calls</span>
        </a>
        <a href="services.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-th-large"></i></div>
            <span>Services</span>
        </a>
        <a href="profile.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="far fa-user-circle"></i></div>
            <span>Profile</span>
        </a>
    </nav>
    
</body>
</html>
