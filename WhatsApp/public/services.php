<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Services - KSR GURU JI v3.0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #f0f2f5; --bg-screen: #ffffff; --text-main: #111b21; --text-muted: #667781; --border: #e9edef; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; padding-bottom: 70px; color: var(--text-main); }
        
        .app-header { background: var(--primary); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        
        .section-header { font-size: 0.85rem; font-weight: 600; padding: 25px 20px 10px; color: var(--text-muted); text-transform: uppercase; }
        
        .service-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; padding: 0 15px; }
        
        .service-card { background: white; border-radius: 12px; padding: 20px 15px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); transition: transform 0.2s; cursor: pointer; text-decoration: none; color: var(--text-main); }
        .service-card:active { transform: scale(0.97); }
        
        .s-icon { width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px; color: white; }
        .s-doctor { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .s-pharmacy { background: linear-gradient(135deg, #10b981, #059669); }
        .s-shop { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .s-pay { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .s-delivery { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .s-business { background: linear-gradient(135deg, #475569, #334155); }
        
        .service-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; }
        .service-badge { font-size: 0.65rem; padding: 3px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
        .b-coming { background: #fef08a; color: #854d0e; }
        .b-live { background: #dcfce7; color: #166534; }

        /* Unified Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; display: flex; justify-content: space-around; align-items: center; border-top: 1px solid var(--border); z-index: 100; }
        .bottom-nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; gap: 4px; padding: 8px 0; width: 60px; }
        .bottom-nav-item.active { color: var(--primary); font-weight: 700; }
        .nav-icon-wrapper { font-size: 1.5rem; }
    </style>
</head>
<body>

    <header class="app-header">
        <h1>KSR GURU JI <span style="font-size:0.7rem; background:rgba(255,255,255,0.2); padding:3px 6px; border-radius:5px;">v3.0 Engine</span></h1>
        <div class="nav-icons">
            <i class="fas fa-qrcode"></i>
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </header>
    

    <div class="section-header">Telehealth Sub-System</div>
    <div class="service-grid">
        <a href="telehealth/index.php" class="service-card">
            <div class="s-icon s-doctor"><i class="fas fa-user-md"></i></div>
            <div class="service-title">Doctor Assist</div>
            <div class="service-badge b-live">Active</div>
        </a>
        <a href="telehealth/pharmacy-dashboard.php" class="service-card">
            <div class="s-icon s-pharmacy"><i class="fas fa-pills"></i></div>
            <div class="service-title">e-Pharmacy</div>
            <div class="service-badge b-live">Active</div>
        </a>
    </div>

    <div class="section-header">Marketplace & Finance</div>
    <div class="service-grid">
        <a href="#" class="service-card">
            <div class="s-icon s-shop"><i class="fas fa-store"></i></div>
            <div class="service-title">Shopping</div>
            <div class="service-badge b-coming">v3.0</div>
        </a>
        <a href="#" class="service-card">
            <div class="s-icon s-pay"><i class="fas fa-wallet"></i></div>
            <div class="service-title">Payments</div>
            <div class="service-badge b-coming">Secure API</div>
        </a>
    </div>

    <div class="section-header">Business Operations</div>
    <div class="service-grid">
        <a href="#" class="service-card">
            <div class="s-icon s-delivery"><i class="fas fa-truck"></i></div>
            <div class="service-title">Delivery Hub</div>
            <div class="service-badge b-coming">v3.1</div>
        </a>
        <a href="#" class="service-card">
            <div class="s-icon s-business"><i class="fas fa-briefcase"></i></div>
            <div class="service-title">Business Acct.</div>
            <div class="service-badge b-coming">Enterprise</div>
        </a>
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
        <a href="services.php" class="bottom-nav-item active">
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
