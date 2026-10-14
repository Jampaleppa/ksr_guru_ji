<?php
require_once 'helper.php';

// Mock Doctor State
$docName = 'Dr. Shivukumar Rathod';
$docSpec = 'Pediatrics';
$docRating = '4.8';
$docEarnings = '₹4,300';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F1F5F9; padding-bottom: 80px; }
        
        /* Specialized Doctor Navbar overriding base */
        .doc-nav { background: #0F172A; } /* Dark professional theme */
        .doc-nav .navbar-brand { color: white; }
        .doc-nav .navbar-links a { color: #94A3B8; }
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; }
        .doc-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .dashboard-grid { max-width: 1400px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 380px; gap: 32px; align-items: start; }

        /* Header UI */
        .dash-header { background: white; padding: 32px; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .dh-greet { font-size: 2.25rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .dh-sub { color: var(--clr-text-secondary); font-size: 1.1rem; font-weight: 500; }
        .dh-profile { display: flex; align-items: center; gap: 16px; background: #F8FAFC; padding: 16px 24px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); }
        .dh-avatar { width: 56px; height: 56px; border-radius: 50%; background: var(--clr-primary-light); color: var(--clr-primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; }

        /* Overview Metrics */
        .metrics-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .m-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); padding: 20px; display: flex; align-items: center; gap: 16px; transition: 0.2s; box-shadow: var(--shadow-sm); }
        .m-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--clr-primary); }
        .mc-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .mc-val { font-size: 1.8rem; font-weight: 800; color: var(--clr-text-primary); line-height: 1.1; }
        .mc-label { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; }

        /* Next Consultation Hero Hook */
        .hero-consult { background: linear-gradient(135deg, var(--clr-primary-dark) 0%, var(--clr-primary) 100%); color: white; border-radius: var(--radius-lg); padding: 32px; box-shadow: 0 10px 30px rgba(37,99,235,0.3); margin-bottom: 24px; position: relative; overflow: hidden; }
        .hc-badge { position: absolute; top: 32px; right: 32px; background: rgba(255,255,255,0.2); backdrop-filter: blur(4px); padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .hc-badge.urgent { background: rgba(220,38,38,0.8); }
        .hc-pat { font-size: 2rem; font-weight: 800; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .hc-meta { font-size: 1.1rem; opacity: 0.9; margin-bottom: 24px; }
        .hc-grid { display: grid; grid-template-columns: repeat(3, auto); gap: 32px; justify-content: flex-start; margin-bottom: 32px; background: rgba(0,0,0,0.2); padding: 16px 24px; border-radius: var(--radius-md); }
        .hc-actions { display: flex; gap: 16px; }
        .hc-btn { background: white; color: var(--clr-primary-dark); padding: 12px 24px; border-radius: var(--radius-full); font-weight: 800; border: none; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 1.05rem; }
        .hc-btn:hover { background: #F8FAFC; transform: scale(1.02); }
        .hc-btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.5); }
        .hc-btn-outline:hover { background: rgba(255,255,255,0.1); }

        /* General Card structure */
        .c-box { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 24px; }
        .cb-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; font-weight: 800; font-size: 1.1rem; color: var(--clr-primary-dark); text-transform: uppercase; }
        
        /* Queue Table row */
        .queue-row { display: grid; grid-template-columns: 80px 1fr 140px 140px 120px; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--clr-border); transition: 0.2s; }
        .queue-row:hover { background: #F8FAFC; padding-left: 8px; margin-left: -8px; padding-right: 8px; margin-right: -8px; border-radius: var(--radius-sm); }
        .queue-row:last-child { border-bottom: none; }
        .qr-time { font-weight: 800; color: var(--clr-text-primary); font-family: monospace; font-size: 1.1rem; }
        .qr-pat { font-weight: 700; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .qr-type { color: var(--clr-text-secondary); font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .sp-done { background: #F0FDF4; color: #16A34A; }
        .sp-wait { background: #FEF9C3; color: #CA8A04; }
        .sp-next { background: #EEF2FF; color: var(--clr-primary); }
        .sp-up { background: #F1F5F9; color: var(--clr-text-secondary); }

        /* Search Bar */
        .search-bar { position: relative; margin-bottom: 24px; }
        .search-bar input { width: 100%; border: 2px solid var(--clr-border); padding: 16px 20px 16px 50px; border-radius: var(--radius-full); font-size: 1.05rem; font-weight: 500; transition: 0.3s; }
        .search-bar input:focus { outline: none; border-color: var(--clr-primary); box-shadow: 0 0 0 4px var(--clr-primary-light); }
        .search-bar i { position: absolute; left: 24px; top: 18px; color: var(--clr-text-secondary); font-size: 1.2rem; }

        /* Secondary Stack (Right side) */
        .stat-line { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--clr-border); }
        .stat-line:last-child { border-bottom: none; }
        .sl-label { font-weight: 600; color: var(--clr-text-secondary); }
        .sl-val { font-weight: 800; color: var(--clr-text-primary); }
        
        @media (max-width: 1000px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .metrics-bar { grid-template-columns: 1fr 1fr; }
            .hc-grid { grid-template-columns: 1fr; gap: 12px; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar doc-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="doctor-shivukumar-dashboard.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> DOCTOR PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="doctor-shivukumar-dashboard.php" class="active" style="font-weight:600;"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="doctor-shivukumar-appointments.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="doctor-shivukumar-patients.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-users"></i> Patients</a>
                <a href="doctor-shivukumar-prescriptions.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-file-prescription"></i> Prescriptions</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="notifications.php" style="color:#94A3B8; font-size:1.2rem;"><i class="fas fa-bell"></i></a>
            <a href="doctor-shivukumar-profile-settings.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-md"></i> Dr. Shivukumar Rathod</a>
        </div>
    </div>
</nav>

<div class="dashboard-grid">
    
    <!-- LEFT PILLAR (Primary clinical workflow) -->
    <div>
        
        <div class="dash-header">
            <div>
                <h1 class="dh-greet">Good Afternoon, <?php echo $docName; ?> 👋</h1>
                <p class="dh-sub">Here is your clinical overview for today.</p>
            </div>
            <div class="dh-profile">
                <div class="dh-avatar">DR</div>
                <div>
                    <div style="font-weight:800; color:var(--clr-text-primary); font-size:1.1rem; display:flex; align-items:center; gap:6px;">
                        <?php echo $docName; ?> <i class="fas fa-check-circle" style="color:#16A34A; font-size:0.9rem;"></i>
                    </div>
                    <div style="font-weight:600; color:var(--clr-text-secondary); font-size:0.9rem;"><?php echo $docSpec; ?></div>
                    <div style="margin-top:8px; display:flex; gap:6px; font-size:0.75rem; font-weight:700;"><span style="background:#DCFCE7; color:#166534; padding:2px 8px; border-radius:12px;">🟢 Available</span></div>
                </div>
            </div>
        </div>

        <div class="metrics-bar">
            <div class="m-card">
                <div class="mc-icon" style="background:#EEF2FF; color:var(--clr-primary);"><i class="fas fa-calendar-alt"></i></div>
                <div><div class="mc-val">12</div><div class="mc-label">Appointments</div></div>
            </div>
            <div class="m-card">
                <div class="mc-icon" style="background:#F0FDF4; color:#16A34A;"><i class="fas fa-users"></i></div>
                <div><div class="mc-val">10</div><div class="mc-label">Patients</div></div>
            </div>
            <div class="m-card">
                <div class="mc-icon" style="background:#FEF9C3; color:#CA8A04;"><i class="fas fa-video"></i></div>
                <div><div class="mc-val">8</div><div class="mc-label">Consultations</div></div>
            </div>
            <div class="m-card">
                <div class="mc-icon" style="background:#F3E8FF; color:#9333EA;"><i class="fas fa-pills"></i></div>
                <div><div class="mc-val">6</div><div class="mc-label">Prescriptions</div></div>
            </div>
        </div>

        <!-- 3. Next Consultation (High Priority Banner) -->
        <div class="hero-consult">
            <div class="hc-badge"><i class="fas fa-circle" style="font-size:0.5rem; color:#86EFAC;"></i> UPCOMING PATIENT</div>
            <div class="hc-pat">👤 Patient: Jampaleppa Rathod</div>
            <div class="hc-meta">Age: 42 · Male · Follow-up consultation</div>
            
            <div class="hc-grid">
                <div><div style="font-size:0.85rem; font-weight:700; opacity:0.8; margin-bottom:4px; text-transform:uppercase;">Time</div><div style="font-weight:800; font-size:1.2rem;"><i class="far fa-clock"></i> Today, 04:30 PM</div></div>
                <div><div style="font-size:0.85rem; font-weight:700; opacity:0.8; margin-bottom:4px; text-transform:uppercase;">Format</div><div style="font-weight:800; font-size:1.2rem;"><i class="fas fa-video"></i> Video Consultation</div></div>
                <div><div style="font-size:0.85rem; font-weight:700; opacity:0.8; margin-bottom:4px; text-transform:uppercase;">Status</div><div style="font-weight:800; font-size:1.2rem; color:#86EFAC;">Starts in: 12 mins</div></div>
            </div>

            <div class="hc-actions">
                <a href="doctor-shivukumar-consultation-room.php" class="hc-btn"><i class="fas fa-sign-in-alt"></i> START CONSULTATION</a>
                <a href="doctor-shivukumar-patients.php" class="hc-btn hc-btn-outline"><i class="fas fa-id-card"></i> View Patient Profile</a>
            </div>
        </div>

        <div class="c-box">
            <div class="cb-head"><div><i class="fas fa-list"></i> Today's Appointment Queue</div> <a href="doctor-shivukumar-appointments.php"  style="font-size:0.85rem; text-decoration:none; color:var(--clr-primary);">View Calendar</a></div>
            
            <div style="display:flex; gap:8px; margin-bottom:20px; overflow-x:auto; padding-bottom:4px;">
                <button style="background:var(--clr-primary-dark); color:white; border:none; padding:6px 16px; border-radius:20px; font-weight:600; cursor:pointer;">All</button>
                <button style="background:#F1F5F9; color:var(--clr-text-secondary); border:none; padding:6px 16px; border-radius:20px; font-weight:600; cursor:pointer;">Waiting (1)</button>
                <button style="background:#F1F5F9; color:var(--clr-text-secondary); border:none; padding:6px 16px; border-radius:20px; font-weight:600; cursor:pointer;">Completed (2)</button>
            </div>

            <div class="queue-row" style="opacity:0.6;">
                <div class="qr-time">09:30</div>
                <div class="qr-pat">Patient A</div>
                <div class="qr-type"><i class="fas fa-head-side-cough"></i> Fever</div>
                <div class="qr-type"><i class="fas fa-phone-alt"></i> Audio</div>
                <div><span class="status-pill sp-done"><i class="fas fa-check"></i> Completed</span></div>
            </div>
            
            <div class="queue-row" style="opacity:0.6;">
                <div class="qr-time">10:30</div>
                <div class="qr-pat">Patient B</div>
                <div class="qr-type"><i class="fas fa-bone"></i> Joint Pain</div>
                <div class="qr-type"><i class="fas fa-video"></i> Video</div>
                <div><span class="status-pill sp-done"><i class="fas fa-check"></i> Completed</span></div>
            </div>

            <div class="queue-row" style="background:#FFFAEB; border-left:4px solid #CA8A04;">
                <div class="qr-time" style="padding-left:12px;">11:30</div>
                <div class="qr-pat">Patient C</div>
                <div class="qr-type"><i class="fas fa-sync-alt"></i> Follow-up</div>
                <div class="qr-type"><i class="fas fa-video"></i> Video</div>
                <div><span class="status-pill sp-wait"><i class="fas fa-hourglass-half"></i> Waiting</span></div>
            </div>

            <div class="queue-row">
                <div class="qr-time">02:00</div>
                <div class="qr-pat">Patient D</div>
                <div class="qr-type"><i class="fas fa-stethoscope"></i> Wellness</div>
                <div class="qr-type"><i class="fas fa-phone-alt"></i> Audio</div>
                <div><span class="status-pill sp-up">○ Upcoming</span></div>
            </div>

            <div class="queue-row" style="background:#F0FDF4; border-left:4px solid #16A34A;">
                <div class="qr-time" style="padding-left:12px;">04:30</div>
                <div class="qr-pat">Jampaleppa R.</div>
                <div class="qr-type"><i class="fas fa-sync-alt"></i> Follow-up</div>
                <div class="qr-type"><i class="fas fa-video"></i> Video</div>
                <div><span class="status-pill sp-next"><i class="fas fa-arrow-right"></i> Next</span></div>
            </div>
        </div>

    </div>

    <!-- RIGHT PILLAR (Search, Stats, Logs) -->
    <div>
        <!-- 6. Patient Search -->
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Patients by Name/ID...">
        </div>
        
        <div class="c-box">
            <div class="cb-head"><i class="fas fa-chart-line"></i> Quick Stats</div>
            <div class="stat-line"><div class="sl-label">Pending Medical Reviews</div> <div class="sl-val" style="color:#DC2626;">3</div></div>
            <div class="stat-line"><div class="sl-label">Follow-ups Due Today</div> <div class="sl-val">4</div></div>
            <div class="stat-line"><div class="sl-label">Today's Earnings</div> <div class="sl-val" style="color:#16A34A;"><?php echo $docEarnings; ?></div></div>
            <div class="stat-line"><div class="sl-label">Patient Rating</div> <div class="sl-val"><i class="fas fa-star" style="color:#EAB308;"></i> <?php echo $docRating; ?>/5</div></div>
        </div>

        <div class="c-box">
            <div class="cb-head"><i class="fas fa-users"></i> Patient Queue Status</div>
            
            <div style="margin-bottom:16px;">
                <div style="font-weight:700; color:#CA8A04; font-size:0.95rem; margin-bottom:4px;">🟡 Checked In & Waiting</div>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#F8FAFC; padding:12px; border:1px solid var(--clr-border); border-radius:var(--radius-sm);">
                    <div>
                        <div style="font-weight:800; color:var(--clr-text-primary);">Patient C</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">Follow-up (11:30 AM)</div>
                    </div>
                    <button onclick="window.location.href='doctor-shivukumar-patients.php'" style="border:1px solid var(--clr-border); background:white; font-weight:700; padding:6px 12px; border-radius:4px; font-size:0.8rem; cursor:pointer;">View</button>
                </div>
            </div>

            <div>
                <div style="font-weight:700; color:#16A34A; font-size:0.95rem; margin-bottom:4px;">🟢 Scheduled (Next)</div>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#F8FAFC; padding:12px; border:1px solid var(--clr-border); border-radius:var(--radius-sm);">
                    <div>
                        <div style="font-weight:800; color:var(--clr-text-primary);">Jampaleppa R.</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">Video (04:30 PM)</div>
                    </div>
                    <button onclick="window.location.href='doctor-shivukumar-consultation-room.php'" style="border:1px solid var(--clr-border); background:white; font-weight:700; padding:6px 12px; border-radius:4px; font-size:0.8rem; cursor:pointer;">View</button>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
