<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';

// Mock profile data for UI mapping
$ksrId = $_SESSION['ksr_id'] ?? '@ksr_johndoe8a4';
$displayName = $_SESSION['display_name'] ?? 'John Doe';
$mobile = '+91 98765 43210';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile - KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #f0f2f5; --bg-screen: #ffffff; --text-main: #111b21; --text-muted: #667781; --border: #e9edef; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 70px; color: var(--text-main); }
        
        .app-header { background: var(--primary); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        
        .profile-container { margin-top: 15px; }
        
        .avatar-section { background: var(--bg-screen); padding: 30px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .avatar-wrapper { position: relative; display: inline-block; }
        .avatar { width: 150px; height: 150px; border-radius: 50%; background: #dfe5e7; display: flex; align-items: center; justify-content: center; color: white; font-size: 4rem; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .camera-btn { position: absolute; bottom: 20px; right: 5px; width: 45px; height: 45px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .info-row { background: var(--bg-screen); padding: 15px 20px; display: flex; align-items: center; margin-top: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; }
        .info-icon { font-size: 1.4rem; color: var(--text-muted); width: 40px; display: flex; justify-content: center; margin-right: 15px; }
        .info-content { flex: 1; border-bottom: 1px solid var(--border); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .info-row:last-child .info-content { border-bottom: none; padding-bottom: 0; }
        
        .info-label { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px; }
        .info-value { font-size: 1.05rem; font-weight: 500; color: var(--text-main); }
        
        .qr-button { background: var(--bg-screen); text-align: center; color: var(--primary); font-weight: 600; padding: 15px; margin-top: 20px; font-size: 1.1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; }
        .logout-button { background: var(--bg-screen); text-align: center; color: #dc2626; font-weight: 600; padding: 15px; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; }

        /* Unified Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; display: flex; justify-content: space-around; align-items: center; border-top: 1px solid var(--border); z-index: 100; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; gap: 4px; padding: 8px 0; width: 60px; }
        .bottom-nav-item.active { color: var(--primary); font-weight: 700; }
        .nav-icon-wrapper { font-size: 1.5rem; }
    </style>
</head>
<body>

    <header class="app-header">
        <h1>Profile</h1>
    </header>
    
    <div class="profile-container">
        <!-- Avatar Block -->
        <div class="avatar-section">
            <div class="avatar-wrapper">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="camera-btn"><i class="fas fa-camera"></i></div>
            </div>
            <div style="font-size:1.4rem; font-weight:700; color:var(--text-main);"><?= htmlspecialchars($displayName) ?></div>
            <div style="font-size:0.95rem; color:var(--primary); font-weight:600; margin-top:5px;"><?= htmlspecialchars($ksrId) ?></div>
        </div>
        
        <!-- Editable User Info -->
        <div style="background:var(--bg-screen); margin-top:15px; padding-top:15px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <!-- Name Row -->
            <div class="info-row" style="margin-top:0; box-shadow:none;">
                <div class="info-icon"><i class="fas fa-user-circle"></i></div>
                <div class="info-content">
                    <div>
                        <div class="info-label">Name</div>
                        <div class="info-value"><?= htmlspecialchars($displayName) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:5px; line-height:1.3;">This is your public display name.</div>
                    </div>
                    <i class="fas fa-pen" style="color:var(--primary);"></i>
                </div>
            </div>
            
            <!-- About Row -->
            <div class="info-row" style="margin-top:0; box-shadow:none;">
                <div class="info-icon"><i class="fas fa-info-circle"></i></div>
                <div class="info-content">
                    <div>
                        <div class="info-label">About</div>
                        <div class="info-value">Available</div>
                    </div>
                    <i class="fas fa-pen" style="color:var(--primary);"></i>
                </div>
            </div>
            
            <!-- Phone Row -->
            <div class="info-row" style="margin-top:0; box-shadow:none;">
                <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="info-content" style="border-bottom:none;">
                    <div>
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?= htmlspecialchars($mobile) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Privacy / Settings Actions -->
        <div class="qr-button">
            <i class="fas fa-qrcode" style="margin-right:10px;"></i> Share KSR ID QR Code
        </div>
        
        <div class="logout-button" onclick="confirmLogout()">
            Log Out
        </div>
        
    </div>

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
        <a href="calls.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-phone-alt"></i></div>
            <span>Calls</span>
        </a>
        <a href="services.php" class="bottom-nav-item">
            <div class="nav-icon-wrapper"><i class="fas fa-th-large"></i></div>
            <span>Services</span>
        </a>
        <a href="profile.php" class="bottom-nav-item active">
            <div class="nav-icon-wrapper"><i class="fas fa-user-circle"></i></div>
            <span>Profile</span>
        </a>
    </nav>
    
    <script>
        function confirmLogout() {
            if(confirm('Are you sure you want to log out of KSR GURU JI?')) {
                // Clear simulated storage and route to logout
                window.location.href = 'index.php';
            }
        }
    </script>
</body>
</html>
