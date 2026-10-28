<?php
require_once 'helper.php';

// Mock Patient session
$patientName = 'Jampaleppa';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';

// In a real system, these would fetch from the DB:
// SELECT * FROM appointments WHERE patient_id = X
// For now, we mock the specific states the layout demands.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; }
        
        .dash-layout { max-width: 900px; margin: 40px auto; padding: 0 16px; margin-bottom: 100px; }

        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 2rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .page-sub { color: var(--clr-text-secondary); font-size: 1.05rem; }

        /* Tabs */
        .dash-tabs { display: flex; border-bottom: 2px solid var(--clr-border); margin-bottom: 32px; overflow-x: auto; scrollbar-width: none; }
        .dash-tabs::-webkit-scrollbar { display: none; }
        .tab { padding: 12px 24px; font-weight: 600; color: var(--clr-text-secondary); cursor: pointer; transition: 0.3s; white-space: nowrap; margin-bottom: -2px; border-bottom: 2px solid transparent; }
        .tab:hover { color: var(--clr-text-primary); }
        .tab.active { color: var(--clr-primary); border-bottom-color: var(--clr-primary); }

        /* Cards */
        .appt-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); position: relative; }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; }
        .appt-status-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-full); text-transform: uppercase; letter-spacing: 0.05em; }
        .status-today { background: #FEE2E2; color: #DC2626; }
        .status-upcoming { background: #DBEAFE; color: #2563EB; }
        .status-completed { background: #DCFCE7; color: #16A34A; }
        
        .doc-info { display: flex; align-items: center; gap: 16px; }
        .doc-icon { width: 50px; height: 50px; background: var(--clr-primary-light); color: var(--clr-primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); }
        .doc-name { font-size: 1.15rem; font-weight: 700; color: var(--clr-text-primary); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .doc-spec { font-size: 0.9rem; color: var(--clr-text-secondary); }

        .appt-meta { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 20px; font-size: 0.95rem; color: var(--clr-text-primary); font-weight: 500; }
        .appt-meta div { display: flex; align-items: center; gap: 8px; }
        .appt-meta i { color: var(--clr-primary); width: 16px; text-align: center; }

        /* Today Special Block */
        .countdown-box { background: #FFF1F2; border: 1px dashed #FDA4AF; border-radius: var(--radius-md); padding: 16px; text-align: center; margin-top: 24px; }
        .countdown-label { font-size: 0.9rem; color: #BE123C; font-weight: 600; margin-bottom: 8px; }
        .countdown-time { font-family: monospace; font-size: 2rem; font-weight: 800; color: #E11D48; letter-spacing: 2px; }

        /* Card Actions */
        .card-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; border-top: 1px solid var(--clr-border); padding-top: 24px; align-items: center; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.3s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-secondary); text-decoration: none; }
        .btn-action:hover { border-color: var(--clr-primary); color: var(--clr-primary); }
        .btn-primary { background: var(--clr-primary); color: white; border: none; }
        .btn-primary:hover { background: var(--clr-primary-dark); color: white; }
        .btn-danger { color: #DC2626; border-color: #FECACA; background: #FEF2F2; }
        .btn-danger:hover { background: #FEE2E2; }
        
        .price-badge { font-weight: 700; color: #16A34A; margin-left: auto; font-size: 1.05rem; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 16px; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 550px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; transform: translateY(20px); transition: 0.3s; max-height: 90vh; overflow-y: auto; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        
        .modal-header { padding: 24px; border-bottom: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 10; }
        .modal-title { font-size: 1.25rem; font-weight: 700; color: var(--clr-primary-dark); }
        .btn-close { background: transparent; border: none; font-size: 1.5rem; color: var(--clr-text-secondary); cursor: pointer; }

        .modal-body { padding: 24px; }
        
        /* Timeline */
        .timeline { margin: 24px 0; border-left: 2px solid var(--clr-border); padding-left: 24px; position: relative; }
        .tl-item { margin-bottom: 24px; position: relative; }
        .tl-item:last-child { margin-bottom: 0; }
        .tl-dot { position: absolute; left: -32px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: white; border: 2px solid var(--clr-border); }
        .tl-item.done .tl-dot { background: #16A34A; border-color: #16A34A; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; content: "\f00c"; font-family: 'Font Awesome 6 Free'; font-weight: 900; }
        .tl-item.active .tl-dot { border-color: var(--clr-primary); background: var(--clr-primary); box-shadow: 0 0 0 4px var(--clr-primary-light); }
        .tl-text { font-weight: 600; color: var(--clr-text-secondary); }
        .tl-item.done .tl-text { color: #16A34A; }
        .tl-item.active .tl-text { color: var(--clr-primary-dark); }

        /* Checklist */
        .checklist-box { background: #F8FAFC; border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .cli { display: flex; gap: 12px; margin-bottom: 12px; font-size: 0.95rem; color: var(--clr-text-primary); font-weight: 500; }
        .cli i { color: #16A34A; margin-top: 3px; }

        /* Reschedule Grid */
        .r-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .r-chip { padding: 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); text-align: center; cursor: pointer; font-size: 0.95rem; font-weight: 500; transition: 0.2s; }
        .r-chip:hover { border-color: var(--clr-primary); color: var(--clr-primary); }
        .r-chip.active { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 4rem; color: var(--clr-text-secondary); opacity: 0.5; margin-bottom: 24px; }
        .empty-title { font-size: 1.5rem; font-weight: 700; color: var(--clr-text-primary); margin-bottom: 8px; }
        .empty-sub { color: var(--clr-text-secondary); margin-bottom: 32px; font-size: 1.05rem; }

        /* NavBar specific override for Dashboard */
        .navbar-links { display: flex; gap: 24px; align-items: center; }
        .navbar-links a { color: var(--clr-text-secondary); text-decoration: none; font-weight: 600; transition: 0.2s; }
        .navbar-links a:hover, .navbar-links a.active { color: var(--clr-primary); }

        @media (max-width: 768px) {
            .navbar-brand { font-size: 1.25rem; }
            .navbar-links { display: none; } /* Mobile bottom nav takes over */
            .card-actions { flex-direction: column; align-items: stretch; gap: 10px; }
            .btn-action { min-height: 44px; width: 100%; justify-content: center; }
            .price-badge { margin-left: 0; text-align: center; padding: 12px 0; border-top: 1px dashed var(--clr-border); }
            .appt-card { padding: 20px 16px; border-radius: var(--radius-md); }
        }

        @media (max-width: 480px) {
            .container { padding: 0 12px; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .doc-info { gap: 12px; }
            .doc-icon { width: 44px; height: 44px; font-size: 1.25rem; }
            .doc-name { font-size: 1.05rem; }
            .appt-meta { gap: 12px; font-size: 0.88rem; }
            .appt-meta div { width: 100%; }
            .countdown-time { font-size: 1.6rem; }
            .tab { padding: 10px 16px; font-size: 0.9rem; }
            .modal-header, .modal-body { padding: 16px; }
        }

        @media (max-width: 360px) {
            .container { padding: 0 8px; }
            .appt-card { padding: 16px 12px; }
            .doc-name { font-size: 0.98rem; }
            .countdown-time { font-size: 1.35rem; }
            .btn-action { font-size: 0.88rem; padding: 8px 14px; }
            .tab { padding: 8px 12px; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'appointments'; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
            <div class="navbar-links">
                <a href="index.php">Home</a>
                <a href="find-doctor.php">Find Doctor</a>
                <a href="my-appointments.php" class="active">My Appointments</a>
                <a href="pharmacy.php">Pharmacy</a>
                <a href="my-prescriptions.php">My Prescriptions</a>
            </div>
        </div>
        <div class="navbar-links">
            <a href="#" ><i class="fas fa-bell"></i></a>
            <a href="profile.php" style="background:var(--clr-primary-light); color:var(--clr-primary); padding:8px 16px; border-radius:20px;"><i class="fas fa-user-circle"></i> Profile</a>
        </div>
    </div>
</nav>

<div class="dash-layout">
    
    <div class="page-header">
        <h1 class="page-title">My Appointments</h1>
        <p class="page-sub">Manage your upcoming and previous consultations.</p>
    </div>

    <!-- Tabs -->
    <div class="dash-tabs">
        <div class="tab" onclick="switchSection('upcoming')" id="tab-upcoming">Upcoming</div>
        <div class="tab active" onclick="switchSection('today')" id="tab-today">Today</div>
        <div class="tab" onclick="switchSection('completed')" id="tab-completed">Completed</div>
        <div class="tab" onclick="switchSection('cancelled')" id="tab-cancelled">Cancelled</div>
    </div>

    <!-- TODAY SECTION -->
    <div id="sec-today">
        <div class="appt-card" style="border: 2px solid #FECACA; background: #FEF2F2;">
            <div class="card-header" style="border-bottom-color: #FECACA;">
                <div class="appt-status-badge status-today">🔴 TODAY</div>
            </div>
            
            <div class="doc-info">
                <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                <div>
                    <div class="doc-name"><?php echo htmlspecialchars($docName); ?> <span style="color:#16A34A; font-size:0.85rem;"><i class="fas fa-check-circle"></i> VERIFIED</span></div>
                    <div class="doc-spec">General Physician</div>
                </div>
            </div>

            <div class="appt-meta">
                <div><i class="fas fa-video"></i> VIDEO CONSULTATION</div>
                <div><i class="fas fa-calendar-day"></i> September 5, 2026</div>
                <div><i class="fas fa-clock"></i> 03:30 PM</div>
            </div>

            <div class="countdown-box">
                <div class="countdown-label">Consultation starts in</div>
                <div class="countdown-time">00:18:42</div>
            </div>

            <div class="card-actions">
                <button class="btn-action btn-primary" style="flex:1;" onclick="window.location.href='consultation-room.php'"><i class="fas fa-video"></i> Join Consultation</button>
                <button class="btn-action" onclick="openModal('details-modal')">View Details</button>
            </div>
        </div>
    </div>

    <!-- UPCOMING SECTION -->
    <div id="sec-upcoming" style="display:none;">
        <div class="appt-card">
            <div class="card-header">
                <div class="appt-status-badge status-upcoming">UPCOMING</div>
            </div>
            
            <div class="doc-info">
                <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                <div>
                    <div class="doc-name">Dr. Priya Sharma <span style="color:#16A34A; font-size:0.85rem;"><i class="fas fa-check-circle"></i> VERIFIED</span></div>
                    <div class="doc-spec">General Physician</div>
                </div>
            </div>

            <div class="appt-meta">
                <div><i class="fas fa-headphones-alt"></i> AUDIO CONSULTATION</div>
                <div><i class="fas fa-calendar-alt"></i> September 8, 2026</div>
                <div><i class="fas fa-clock"></i> 11:30 AM</div>
            </div>

            <div class="card-actions">
                <button class="btn-action" onclick="openModal('details-modal')">View Details</button>
                <button class="btn-action" onclick="openModal('reschedule-modal')">Reschedule</button>
                <button class="btn-action btn-danger" onclick="openModal('cancel-modal')">Cancel</button>
                <div class="price-badge">₹115 PAID</div>
            </div>
        </div>
    </div>

    <!-- COMPLETED SECTION -->
    <div id="sec-completed" style="display:none;">
        <div class="appt-card">
            <div class="card-header">
                <div class="appt-status-badge status-completed"><i class="fas fa-check"></i> COMPLETED</div>
            </div>
            
            <div class="doc-info">
                <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                <div>
                    <div class="doc-name"><?php echo htmlspecialchars($docName); ?></div>
                    <div class="doc-spec">General Physician</div>
                </div>
            </div>

            <div class="appt-meta">
                <div><i class="fas fa-video"></i> Video Consultation</div>
                <div>September 2, 2026 • 02:30 PM</div>
            </div>

            <div class="card-actions">
                <button class="btn-action" style="flex:1;" onclick="window.location.href='consultation-room.php'">View Consultation</button>
                <button class="btn-action btn-primary" style="flex:1;" onclick="window.location.href='my-prescriptions.php'"><i class="fas fa-prescription-bottle-alt"></i> View Prescription</button>
                <button class="btn-action" onclick="openModal('details-modal')"><i class="fas fa-file-invoice"></i> Invoice</button>
                <button class="btn-action" style="color:#F59E0B; border-color:#FDE68A;"><i class="fas fa-star"></i> Rate Doctor</button>
            </div>
        </div>
    </div>

    <!-- CANCELLED SECTION (Empty State) -->
    <div id="sec-cancelled" style="display:none;">
        <div class="empty-state">
            <i class="fas fa-calendar-times empty-icon"></i>
            <h3 class="empty-title">No Cancelled Appointments</h3>
            <p class="empty-sub">You have no cancelled bookings in your history.</p>
        </div>
    </div>

</div>

<!-- DETAILS MODAL -->
<div class="modal-overlay" id="details-modal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Appointment Details</div>
            <button class="btn-close" onclick="closeModal('details-modal')">&times;</button>
        </div>
        <div class="modal-body">
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                <div>
                    <div style="font-weight: 700; font-size: 1.15rem; color: var(--clr-primary-dark);">👨‍⚕️ <?php echo htmlspecialchars($docName); ?></div>
                    <div style="color: var(--clr-text-secondary); font-size: 0.95rem;">General Physician • ✓ Verified</div>
                </div>
                <div style="background:var(--clr-primary-light); color:var(--clr-primary); padding:6px 12px; border-radius:12px; font-size:0.85rem; font-weight:700;">● CONFIRMED</div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom: 24px; font-weight:500; font-size:0.95rem; color:var(--clr-text-primary);">
                <div><i class="fas fa-video" style="color:var(--clr-primary); width:20px;"></i> Video Consultation</div>
                <div><i class="fas fa-user" style="color:var(--clr-primary); width:20px;"></i> Patient: <?php echo htmlspecialchars($patientName); ?></div>
                <div><i class="fas fa-calendar-alt" style="color:var(--clr-primary); width:20px;"></i> September 6, 2026</div>
                <div><i class="fas fa-clock" style="color:var(--clr-primary); width:20px;"></i> 02:30 PM</div>
            </div>

            <div style="background:#F8FAFC; padding:12px; border-radius:var(--radius-sm); font-family:monospace; margin-bottom:24px;">
                <div style="font-size:0.8rem; color:var(--clr-text-secondary); font-family:sans-serif; margin-bottom:4px;">APPOINTMENT ID</div>
                <div style="font-size:1.1rem; color:var(--clr-primary); font-weight:700;">DA-2026-X84B9A2</div>
                <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--clr-border); font-family:sans-serif; font-size:0.9rem; font-weight: 600; color:#16A34A;"> Payment Status: ✓ PAID</div>
            </div>
            
            <hr class="divider">

            <!-- Timeline -->
            <h4 style="margin-bottom: 16px; color:var(--clr-text-secondary); text-transform:uppercase; font-size:0.9rem;">Status Timeline</h4>
            <div class="timeline">
                <div class="tl-item done"><div class="tl-dot"></div><div class="tl-text">Appointment Booked</div></div>
                <div class="tl-item done"><div class="tl-dot"></div><div class="tl-text">Payment Confirmed</div></div>
                <div class="tl-item done"><div class="tl-dot"></div><div class="tl-text">Doctor Confirmed</div></div>
                <div class="tl-item active"><div class="tl-dot"></div><div class="tl-text">Ready to Join</div></div>
                <div class="tl-item"><div class="tl-dot"></div><div class="tl-text" style="color:#94A3B8;">Consultation</div></div>
                <div class="tl-item"><div class="tl-dot"></div><div class="tl-text" style="color:#94A3B8;">Completed</div></div>
            </div>

            <hr class="divider">

            <!-- Prep -->
            <div class="checklist-box">
                <h4 style="margin-bottom: 16px; color:var(--clr-primary-dark); font-size:1rem;"><i class="fas fa-stethoscope"></i> GET READY FOR YOUR CONSULTATION</h4>
                <div class="cli"><i class="fas fa-check-circle"></i> Check your internet connection</div>
                <div class="cli"><i class="fas fa-check-circle"></i> Allow microphone permission</div>
                <div class="cli"><i class="fas fa-check-circle"></i> Allow camera permission for video</div>
                <div class="cli"><i class="fas fa-check-circle"></i> Keep relevant medical documents ready</div>
                <div class="cli"><i class="fas fa-check-circle"></i> Join from a private and quiet location</div>
                
                <button class="btn-action w-100" style="width:100%; justify-content:center; margin-top:16px;">Test Audio & Video</button>
            </div>

            <!-- Modal footer actions -->
            <div style="display:flex; gap:12px; margin-top:32px;">
                <button class="btn-action" style="flex:1;"><i class="fas fa-file-invoice"></i> View Invoice</button>
                <button class="btn-action" style="flex:1;" onclick="closeModal('details-modal'); openModal('reschedule-modal');">Reschedule</button>
            </div>

        </div>
    </div>
</div>

<!-- RESCHEDULE MODAL -->
<div class="modal-overlay" id="reschedule-modal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Reschedule Appointment</div>
            <button class="btn-close" onclick="closeModal('reschedule-modal')">&times;</button>
        </div>
        <div class="modal-body">
            
            <h4 style="margin-bottom: 12px; color:var(--clr-text-secondary); font-size:0.9rem;">Select New Date</h4>
            <div class="r-grid">
                <div class="r-chip active">Sep 07</div>
                <div class="r-chip">Sep 08</div>
                <div class="r-chip">Sep 09</div>
                <div class="r-chip">Sep 10</div>
            </div>

            <h4 style="margin-bottom: 12px; color:var(--clr-text-secondary); font-size:0.9rem;">Available Times</h4>
            <div class="r-grid">
                <div class="r-chip">09:00 AM</div>
                <div class="r-chip active">10:30 AM</div>
                <div class="r-chip">02:00 PM</div>
                <div class="r-chip">04:30 PM</div>
                <div class="r-chip">06:00 PM</div>
            </div>

            <button class="btn-primary" style="width:100%; padding:16px; border-radius:var(--radius-md); font-size:1.1rem; font-weight:700; margin-top:24px;" onclick="alert('Appointment Rescheduled successfully!'); closeModal('reschedule-modal');">Confirm New Time</button>

        </div>
    </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal-overlay" id="cancel-modal">
    <div class="modal-card" style="max-width: 450px;">
        <div class="modal-body" style="text-align: center; padding: 40px 32px;">
            <i class="fas fa-exclamation-circle" style="font-size:4rem; color:#DC2626; margin-bottom:20px;"></i>
            <h3 style="font-size:1.5rem; color:var(--clr-text-primary); margin-bottom:12px; font-weight:700;">Cancel Appointment?</h3>
            <p style="color:var(--clr-text-secondary); margin-bottom:24px;">Are you sure you want to cancel this consultation?</p>

            <div style="background:#F8FAFC; border:1px solid var(--clr-border); padding:16px; border-radius:var(--radius-sm); text-align:left; margin-bottom:24px;">
                <div style="font-weight:700; margin-bottom:4px;">👨‍⚕️ Dr. Priya Sharma</div>
                <div style="color:var(--clr-text-secondary); font-size:0.9rem;">September 8 • 11:30 AM</div>
            </div>

            <div style="text-align:left; margin-bottom:32px;">
                <label style="font-size:0.85rem; font-weight:600; color:var(--clr-text-secondary); display:block; margin-bottom:8px;">Reason (Optional)</label>
                <select style="width:100%; padding:12px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-family:inherit;">
                    <option>Select reason ▼</option>
                    <option>I have recovered</option>
                    <option>Need a different time</option>
                    <option>Booked by mistake</option>
                    <option>Other</option>
                </select>
                <div style="font-size:0.8rem; color:#DC2626; margin-top:12px;"><i class="fas fa-info-circle"></i> Cancellation is subject to the refund policy. You will receive a refund within 5–7 business days.</div>
            </div>

            <div style="display:flex; gap:12px;">
                <button class="btn-action" style="flex:1;" onclick="closeModal('cancel-modal')">Keep Appointment</button>
                <button class="btn-action btn-danger" style="flex:1;" onclick="alert('Appointment Cancelled.'); closeModal('cancel-modal');">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<script>
    function switchSection(secId) {
        // Tabs UI
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + secId).classList.add('active');
        
        // Sections display
        ['today', 'upcoming', 'completed', 'cancelled'].forEach(s => {
            document.getElementById('sec-' + s).style.display = 'none';
        });
        document.getElementById('sec-' + secId).style.display = 'block';
    }

    function openModal(id) {
        document.getElementById(id).classList.add('active');
        if (id === 'details-modal') {
             // Mock countdown updating if we had a live script
        }
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    
    // Chip logic in Reschedule
    document.querySelectorAll('.r-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            // Find parent grid to isolate selection
            let parent = this.parentElement;
            parent.querySelectorAll('.r-chip').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });
</script>

</body>
</html>
