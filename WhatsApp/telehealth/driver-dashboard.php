<?php
require_once 'helper.php';
// Simulated driver dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Operations | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; padding-bottom: 90px; }
        
        /* Driver Navbar */
        .drv-nav { background: #0F172A; }
        .drv-nav .navbar-brand { color: white; }
        .drv-nav .navbar-links a { color: #94A3B8; }
        .drv-nav .navbar-links a:hover, .drv-nav .navbar-links a.active { color: white; }
        .drv-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .container-main { max-width: 800px; margin: 24px auto; padding: 0 16px; }
        
        /* Mobile-First Adjustments for Driver */
        .dr-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .dr-title { font-size: 1.25rem; font-weight: 800; color: var(--clr-primary-dark); }
        
        /* Availability Toggles */
        .status-toggle { background: white; border-radius: var(--radius-lg); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .st-info { display: flex; align-items: center; gap: 12px; }
        .st-indicator { width: 12px; height: 12px; border-radius: 50%; background: #16A34A; box-shadow: 0 0 0 4px #DCFCE7; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }
        .st-text { font-weight: 800; font-size: 1.1rem; color: #16A34A; }
        
        /* Switch */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #16A34A; }
        input:checked + .slider:before { transform: translateX(22px); }

        /* Metrics */
        .metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
        .m-card { background: white; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); text-align: center; }
        .m-val { font-size: 1.5rem; font-weight: 900; color: var(--clr-text-primary); margin-bottom: 4px; }
        .m-label { font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; }

        /* Active Job Card */
        .job-card { background: white; border-radius: var(--radius-lg); border: 2px solid var(--clr-primary); box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1); overflow: hidden; margin-bottom: 24px; position:relative; }
        .jc-head { background: var(--clr-primary); color: white; padding: 16px; display: flex; justify-content: space-between; align-items: center; }
        .jc-type { font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .jc-id { font-family: monospace; font-size: 1.1rem; font-weight: bold; }
        .jc-body { padding: 20px; }
        
        .route-path { position: relative; padding-left: 24px; margin-bottom: 24px; }
        .route-path::before { content: ''; position: absolute; left: 6px; top: 8px; bottom: 8px; width: 2px; background: #CBD5E1; border-left: 2px dashed #94A3B8; }
        .rp-point { position: relative; margin-bottom: 20px; }
        .rp-point:last-child { margin-bottom: 0; }
        .rp-point::before { content: ''; position: absolute; left: -24px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: white; border: 3px solid var(--clr-primary); }
        .rp-point.done::before { background: var(--clr-primary); }
        .rp-point.dest::before { border-color: #F59E0B; background: white; }
        
        .rp-label { font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 2px; }
        .rp-val { font-size: 1.05rem; font-weight: 800; color: var(--clr-text-primary); }
        .rp-meta { font-size: 0.85rem; color: var(--clr-text-secondary); margin-top: 2px; }
        
        .jc-actions { display: flex; gap: 12px; padding: 16px 20px; background: #F8FAFC; border-top: 1px solid var(--clr-border); }
        .btn-full { flex: 1; padding: 14px 16px; border-radius: 8px; font-weight: 800; font-size: 1rem; border: none; cursor: pointer; text-align: center; }
        .btn-primary { background: var(--clr-primary); color: white; }
        .btn-green { background: #16A34A; color: white; }
        .btn-outline { background: white; border: 2px solid var(--clr-border); color: var(--clr-text-primary); }

        /* General List Items */
        .list-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); display: flex; align-items: flex-end; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 0; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 600px; border-radius: 24px 24px 0 0; box-shadow: 0 -10px 40px rgba(0,0,0,0.3); transform: translateY(100%); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1); padding: 24px; padding-bottom: max(24px, env(safe-area-inset-bottom)); }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        
        .otp-inputs { display: flex; gap: 12px; justify-content: center; margin: 24px 0; }
        .otp-inputs input { width: 50px; height: 60px; font-size: 2rem; text-align: center; font-weight: 900; border: 2px solid var(--clr-border); border-radius: 8px; font-family: monospace; }
        .otp-inputs input:focus { border-color: var(--clr-primary); outline: none; }
        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            .container-main {
                padding: 0 10px;
                margin-top: 14px;
            }
            .jc-actions {
                flex-direction: column;
                gap: 8px;
            }
            .btn-full {
                width: 100%;
            }
            .otp-inputs {
                gap: 8px;
            }
            .otp-inputs input {
                width: 44px;
                height: 52px;
                font-size: 1.5rem;
            }
            .modal-card {
                padding: 18px 16px;
            }
        }

        @media (max-width: 360px) {
            .dr-title {
                font-size: 1.1rem;
            }
            .m-val {
                font-size: 1.25rem;
            }
            .otp-inputs input {
                width: 38px;
                height: 48px;
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar drv-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="driver-dashboard.php" class="navbar-brand"><i class="fas fa-motorcycle"></i> DRIVER PORTAL</a>
        </div>
        <div style="display:flex; align-items:center; gap:16px;">
            <a href="#"  class="profile-badge" style="padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:700;"><i class="fas fa-bars"></i></a>
        </div>
    </div>
</nav>

<div class="container-main">
    
    <div class="dr-header">
        <div class="dr-title">Today's Shift</div>
        <div style="font-weight:700; color:var(--clr-text-secondary);">05 Sep 2026</div>
    </div>

    <!-- Toggle Online/Offline -->
    <div class="status-toggle">
        <div class="st-info">
            <div class="st-indicator"></div>
            <div>
                <div class="st-text">ONLINE</div>
                <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:2px;">Receiving assignments</div>
            </div>
        </div>
        <label class="switch">
            <input type="checkbox" checked>
            <span class="slider"></span>
        </label>
    </div>

    <div class="metrics-grid">
        <div class="m-card">
            <div class="m-val">24</div>
            <div class="m-label">Delivered</div>
        </div>
        <div class="m-card">
            <div class="m-val" style="color:#16A34A;">₹1,850</div>
            <div class="m-label">Earnings</div>
        </div>
    </div>

    <!-- ACTIVE JOB -->
    <div style="font-weight:800; font-size:1.1rem; color:var(--clr-primary-dark); margin-bottom:16px;"><i class="fas fa-route"></i> Active Delivery</div>
    
    <div class="job-card">
        <div class="jc-head">
            <div class="jc-type"><i class="fas fa-box"></i> OUT FOR DELIVERY</div>
            <div class="jc-id">ORD-90021</div>
        </div>
        <div class="jc-body">
            
            <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
                <div>
                    <div style="font-size:1.5rem; font-weight:900; color:var(--clr-text-primary);">14 min</div>
                    <div style="font-size:0.85rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Est. Time</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.5rem; font-weight:900; color:var(--clr-text-primary);">3.6 km</div>
                    <div style="font-size:0.85rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Distance</div>
                </div>
            </div>

            <div class="route-path">
                <div class="rp-point done">
                    <div class="rp-label">Pickup Point • <span style="color:#16A34A;">Done 14:10</span></div>
                    <div class="rp-val">Verified Pharmacy, Block A</div>
                    <div class="rp-meta">Package: 1 (Sealed)</div>
                </div>
                <div class="rp-point dest">
                    <div class="rp-label">Delivery Destination</div>
                    <div class="rp-val">Patient Area, Sector 4</div>
                    <div class="rp-meta">Instructions: "Call upon arrival."</div>
                </div>
            </div>
            
            <div style="background:#F1F5F9; border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:800; font-size:0.9rem;">Patient #PAT-4821</div>
                    <div style="font-size:0.85rem; color:var(--clr-text-secondary);">Phone: ••••••4821</div>
                </div>
                <button style="border:none; background:white; color:var(--clr-primary); border:1px solid var(--clr-border); width:40px; height:40px; border-radius:50%; box-shadow:var(--shadow-sm);"><i class="fas fa-phone-alt"></i></button>
            </div>
        </div>
        <div class="jc-actions">
            <button class="btn-full btn-outline"><i class="fas fa-location-arrow"></i> Navigate</button>
            <button class="btn-full btn-green" onclick="openModal('otp-modal')"><i class="fas fa-shield-alt"></i> Verify OTP</button>
        </div>
    </div>

    <!-- PENDING PICKUPS -->
    <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-secondary); margin-top:32px; margin-bottom:16px;">Pending Pickups (1)</div>
    
    <div class="list-card">
        <div>
            <div style="font-weight:800; color:var(--clr-text-primary);">ORD-90022</div>
            <div style="font-size:0.85rem; color:var(--clr-text-secondary);">Pharmacy B • 1.2 km away</div>
        </div>
        <button class="btn-full btn-primary" style="width:auto; padding:8px 16px; font-size:0.9rem;">Pickup</button>
    </div>

</div>

<!-- OTP Verification Modal -->
<div class="modal-overlay" id="otp-modal">
    <div class="modal-card">
        <div style="text-align:center; margin-bottom:16px;">
            <div style="width:60px; height:60px; background:#F0FDF4; color:#16A34A; font-size:2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <i class="fas fa-lock"></i>
            </div>
            <div style="font-size:1.4rem; font-weight:900; color:var(--clr-primary-dark);">Delivery Verification</div>
            <div style="font-size:0.95rem; color:var(--clr-text-secondary); margin-top:8px;">Ask the patient for the 4-digit Delivery OTP to securely complete order ORD-90021.</div>
        </div>

        <div class="otp-inputs">
            <input type="text" maxlength="1" value="4">
            <input type="text" maxlength="1" value="8">
            <input type="text" maxlength="1" value="2">
            <input type="text" maxlength="1" value="1">
        </div>

        <div style="display:flex; justify-content:center; gap:8px; margin-bottom:32px;">
            <i class="fas fa-shield-check text-success"></i> <span style="font-size:0.85rem; font-weight:700; color:#16A34A;">Payment Status: PAID</span>
        </div>

        <div style="display:flex; gap:16px;">
            <button class="btn-full btn-outline" style="flex:0.4;" onclick="closeModal('otp-modal')">Cancel</button>
            <button class="btn-full btn-green" onclick="closeModal('otp-modal')">Confirm Delivery ✓</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
</script>

</body>
</html>
