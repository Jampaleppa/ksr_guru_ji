<?php
require_once 'helper.php';

// Dynamic Doctor State
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pdo = getAssistDB();
$doctorId = telehealthResolveDoctorId($pdo, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
$doc = telehealthDoctorProfile($pdo, $doctorId);
$docName = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : $doc['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-xl: 20px;
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
        }

        body { 
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 80px; 
            min-height: 100vh;
        }
        
        /* Specialized Doctor Navbar overriding base */
        .doc-nav { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.1); position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.2);}
        .doc-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 10px; font-weight: 900;}
        .doc-nav .navbar-links a { color: #94A3B8; font-weight: 700; transition: 0.3s;}
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; transform: translateY(-1px);}
        .doc-nav .profile-badge { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); transition: 0.3s;}
        .doc-nav .profile-badge:hover { background: rgba(255,255,255,0.2); }

        .layout-grid { max-width: 1400px; margin: 40px auto; padding: 0 24px; display: grid; grid-template-columns: 1fr 400px; gap: 32px; align-items: start; }

        /* Headers & Metrics */
        .dash-header { background: var(--glass-bg); backdrop-filter: blur(12px); padding: 24px 32px; border-radius: var(--radius-xl); border: 1px solid var(--glass-border); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-glass); }
        .dh-title { font-size: 1.5rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px;}
        .metric-pills { display: flex; gap: 16px; }
        .mp-pill { padding: 10px 20px; border-radius: var(--radius-md); font-weight: 800; font-size: 0.95rem; text-align: center; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5), 0 4px 12px rgba(0,0,0,0.05); border: 1px solid rgba(255,255,255,0.8);}
        
        /* Component Boxes */
        .c-box { background: var(--glass-bg); backdrop-filter: blur(16px); border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); padding: 28px; margin-bottom: 24px; transition: 0.3s;}
        .c-box:hover { box-shadow: 0 20px 50px rgba(0,0,0,0.1); transform: translateY(-2px); border-color: rgba(255,255,255,0.8); }
        .cb-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px dashed rgba(0,0,0,0.1); padding-bottom: 16px; font-weight: 900; font-size: 1.15rem; color: var(--clr-primary-dark); text-transform: uppercase; letter-spacing: 0.5px;}
        
        /* Tabs */
        .status-tabs { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 24px; border-bottom: 2px solid rgba(0,0,0,0.05); }
        .st-btn { background: transparent; border: none; font-weight: 800; font-size: 1rem; color: var(--clr-text-secondary); padding: 8px 16px; margin-bottom: -2px; border-bottom: 3px solid transparent; cursor: pointer; white-space: nowrap; transition: 0.3s; }
        .st-btn.active { color: var(--clr-primary); border-bottom-color: var(--clr-primary); text-shadow: 0 2px 4px rgba(37,99,235,0.2); }
        .st-btn:hover:not(.active) { color: var(--clr-text-primary); transform: translateY(-1px);}

        /* Data Tables */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table th { text-align: left; padding: 16px; font-weight: 800; color: var(--clr-text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); letter-spacing: 0.5px;}
        .data-table td { padding: 20px 16px; border-bottom: 1px dashed rgba(0,0,0,0.05); vertical-align: middle; transition: 0.2s;}
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.6); }

        .time-cell { font-family: monospace; font-weight: 900; font-size: 1.15rem; color: var(--clr-text-primary); }
        .pat-cell { font-weight: 800; color: var(--clr-text-primary); font-size: 1.05rem;}
        .type-cell { font-size: 0.95rem; color: var(--clr-text-secondary); font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .reason-cell { font-size: 0.95rem; color: var(--clr-text-secondary); font-weight: 500;}
        
        /* Status Badges */
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);}
        .sb-done { background: rgba(240, 253, 244, 0.8); color: #16A34A; border: 1px solid rgba(22, 163, 74, 0.2);}
        .sb-wait { background: rgba(254, 249, 195, 0.8); color: #CA8A04; border: 1px solid rgba(202, 138, 4, 0.2);}
        .sb-next { background: rgba(238, 242, 255, 0.8); color: var(--clr-primary); border: 1px solid rgba(37, 99, 235, 0.2);}
        .sb-up { background: rgba(241, 245, 249, 0.8); color: var(--clr-text-secondary); border: 1px solid rgba(148, 163, 184, 0.2);}

        /* Actions */
        .action-flex { display: flex; gap: 12px; }
        .btn-action { background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.1); padding: 10px 16px; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: 0.3s; color: var(--clr-text-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.02);}
        .btn-action:hover { background: white; border-color: var(--clr-primary-light); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.1);}
        .btn-primary { background: var(--gradient-primary); color: white; border: none; box-shadow: 0 4px 12px rgba(37,99,235,0.3);}
        .btn-primary:hover { background: var(--gradient-primary); color: white; box-shadow: 0 6px 16px rgba(37,99,235,0.4);}

        /* Waiting Room Side Panel */
        .waiting-room { background: rgba(255, 251, 235, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(254, 240, 138, 0.8); border-radius: var(--radius-xl); padding: 28px; margin-bottom: 24px; box-shadow: var(--shadow-glass);}
        .wr-head { display: flex; justify-content: space-between; align-items: center; color: #B45309; font-weight: 900; margin-bottom: 20px; font-size: 1.15rem; border-bottom: 1px dashed rgba(254, 240, 138, 0.8); padding-bottom: 12px; letter-spacing: 0.5px;}
        .wr-card { background: rgba(255,255,255,0.9); border: 1px solid rgba(253, 224, 71, 0.5); padding: 20px; border-radius: var(--radius-md); margin-bottom: 16px; box-shadow: 0 4px 12px rgba(202, 138, 4, 0.05); transition: 0.3s;}
        .wr-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(202, 138, 4, 0.1);}
        
        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; backdrop-filter: blur(8px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(24px); width: 100%; max-width: 650px; border-radius: var(--radius-xl); box-shadow: 0 24px 64px rgba(0,0,0,0.2), inset 0 0 0 1px rgba(255,255,255,0.6); overflow: hidden; transform: translateY(30px) scale(0.95); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .m-head { padding: 24px 32px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; font-weight: 900; font-size: 1.35rem; color: var(--clr-primary-dark); background: rgba(248, 250, 252, 0.5); }
        .m-body { padding: 32px; overflow-y: auto; }
        .m-foot { padding: 24px 32px; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; gap: 16px; background: rgba(248, 250, 252, 0.5); }

        .kv-grid { display: grid; grid-template-columns: auto 1fr; gap: 12px; row-gap: 12px; column-gap: 32px; font-size: 1rem; margin-bottom: 32px; }
        .kv-key { color: var(--clr-text-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; margin-top:2px;}
        .kv-val { color: var(--clr-text-primary); font-weight: 800; }

        @media (max-width: 1100px) {
            .layout-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'appointments'; $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php';
    $navActive = 'appointments';
    include __DIR__ . '/partials/doctor-nav.php'; ?>

<div class="layout-grid">
    
    <!-- LEFT PILLAR (Main Appointment Table) -->
    <div>
        <div class="dash-header">
            <div class="dh-title"><i class="fas fa-calendar-alt"></i> Appointments</div>
            <div class="metric-pills">
                <div class="mp-pill" style="background:#EEF2FF; color:var(--clr-primary);">Today: 12</div>
                <div class="mp-pill" style="background:#FEF9C3; color:#CA8A04;">Waiting: 2</div>
                <div class="mp-pill" style="background:#F0FDF4; color:#16A34A;">Completed: 7</div>
                <div class="mp-pill" style="background:#F1F5F9; color:var(--clr-text-secondary);">Canceled: 1</div>
            </div>
        </div>

        <div class="c-box">
            <div class="status-tabs">
                <button class="st-btn active" onclick="filterAppointments('today', this)">Today (12)</button>
                <button class="st-btn" onclick="filterAppointments('upcoming', this)">Upcoming (14)</button>
                <button class="st-btn" onclick="filterAppointments('waiting', this)">Waiting (2)</button>
                <button class="st-btn" onclick="filterAppointments('completed', this)">Completed (42)</button>
                <button class="st-btn" onclick="filterAppointments('cancelled', this)">Cancelled (1)</button>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Completed -->
                        <tr style="opacity:0.6;" data-status="completed">
                            <td class="time-cell">09:30</td>
                            <td class="pat-cell">Patient A</td>
                            <td class="type-cell"><i class="fas fa-phone-alt"></i> Audio</td>
                            <td class="reason-cell">Follow-up</td>
                            <td><span class="status-badge sb-done"><i class="fas fa-check"></i> Completed</span></td>
                            <td><button class="btn-action" onclick="window.location.href='doctor-patients.php'">View</button></td>
                        </tr>
                        <!-- Next/Scheduled -->
                        <tr style="background:#F0FDF4;" data-status="upcoming next">
                            <td class="time-cell">04:30</td>
                            <td class="pat-cell">Jampaleppa Rathod</td>
                            <td class="type-cell"><i class="fas fa-video"></i> Video</td>
                            <td class="reason-cell">Consultation</td>
                            <td><span class="status-badge sb-next"><i class="fas fa-arrow-right"></i> Next</span></td>
                            <td class="action-flex">
                                <button class="btn-action" onclick="openModal('review-modal')"><i class="fas fa-search"></i></button>
                                <button class="btn-action btn-primary" onclick="window.location.href='doctor-consultation-room.php'">Start</button>
                            </td>
                        </tr>
                        <!-- Waiting -->
                        <tr style="background:#FFFAEB;" data-status="waiting">
                            <td class="time-cell">11:30</td>
                            <td class="pat-cell">Patient C</td>
                            <td class="type-cell"><i class="fas fa-video"></i> Video</td>
                            <td class="reason-cell">Consultation</td>
                            <td><span class="status-badge sb-wait"><i class="fas fa-hourglass-half"></i> Waiting</span></td>
                            <td class="action-flex">
                                <button class="btn-action btn-primary" style="background:#CA8A04;" onclick="window.location.href='doctor-consultation-room.php'">Join</button>
                            </td>
                        </tr>
                        <!-- Upcoming -->
                        <tr data-status="upcoming">
                            <td class="time-cell">14:00</td>
                            <td class="pat-cell">Patient D</td>
                            <td class="type-cell"><i class="fas fa-phone-alt"></i> Audio</td>
                            <td class="reason-cell">Fever</td>
                            <td><span class="status-badge sb-up">○ Upcoming</span></td>
                            <td class="action-flex">
                                <button class="btn-action" onclick="openModal('details-modal')">Details</button>
                                <button class="btn-action" onclick="openModal('reschedule-modal')" style="color:#CA8A04;"><i class="fas fa-calendar-times"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- RIGHT PILLAR (Side panels) -->
    <div>
        
        <!-- 7. Waiting Room (High Priority) -->
        <div class="waiting-room">
            <div class="wr-head">
                <div><i class="fas fa-chair"></i> WAITING ROOM</div>
                <div style="background:#FEF08A; color:#B45309; padding:2px 8px; border-radius:12px; font-size:0.8rem;">2 Patients</div>
            </div>
            
            <div class="wr-card">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <div style="font-weight:800; color:var(--clr-text-primary);">#1 Patient A</div>
                    <div style="font-weight:800; color:#B45309;">Wait: 04m</div>
                </div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:12px;">Video · Follow-up</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-action" style="flex:1;" onclick="window.location.href='doctor-patients.php'">View</button>
                    <button class="btn-action btn-primary" style="flex:1; background:#CA8A04;" onclick="window.location.href='doctor-consultation-room.php'">Start</button>
                </div>
            </div>

            <div class="wr-card">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <div style="font-weight:800; color:var(--clr-text-primary);">#2 Patient B</div>
                    <div style="font-weight:800; color:#B45309;">Wait: 01m</div>
                </div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:12px;">Audio · New Consultation</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-action" style="flex:1;" onclick="window.location.href='doctor-patients.php'">View</button>
                    <button class="btn-action btn-primary" style="flex:1; background:#CA8A04;" onclick="window.location.href='doctor-consultation-room.php'">Start</button>
                </div>
            </div>
        </div>

        <!-- 10. Calendar View -->
        <div class="c-box">
            <div class="cb-head"><i class="fas fa-calendar-day"></i> Availability</div>
            
            <div style="text-align:center; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <button class="btn-action"><i class="fas fa-chevron-left"></i></button>
                    <div style="font-weight:800; font-size:1.1rem; color:var(--clr-primary-dark);">September 2026</div>
                    <button class="btn-action"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                    <thead>
                        <tr style="color:var(--clr-text-secondary);">
                            <th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th><th>Su</th>
                        </tr>
                    </thead>
                    <tbody style="font-weight:700; color:var(--clr-text-primary);">
                        <tr>
                            <td style="padding:8px;">31</td>
                            <td style="padding:8px;">1</td>
                            <td style="padding:8px;">2</td>
                            <td style="padding:8px;">3</td>
                            <td style="padding:8px;">4</td>
                            <td style="padding:8px;">5</td>
                            <td style="padding:8px; color:var(--clr-text-secondary);">6</td>
                        </tr>
                        <tr>
                            <td style="padding:8px; background:var(--clr-primary); color:white; border-radius:50%;">7</td> <!-- Selected today -->
                            <td style="padding:8px; position:relative;">8<div style="position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; background:var(--clr-primary);"></div></td>
                            <td style="padding:8px; color:#DC2626;">9</td>
                            <td style="padding:8px;">10</td>
                            <td style="padding:8px; position:relative;">11<div style="position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; background:var(--clr-primary);"></div></td>
                            <td style="padding:8px; position:relative;">12<div style="position:absolute; bottom:2px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; background:var(--clr-primary);"></div></td>
                            <td style="padding:8px; color:var(--clr-text-secondary);">13</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="border-top:1px dashed var(--clr-border); padding-top:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.85rem;">
                    <div style="font-weight:700;"><i class="fas fa-clock"></i> Mon, 07 Sep</div>
                    <div style="color:#16A34A; font-weight:700;">🟢 Available</div>
                </div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary);">09:00 AM – 01:00 PM <br> 04:00 PM – 07:00 PM</div>
                <button class="btn-action" style="width:100%; margin-top:12px;">Edit Schedule</button>
            </div>
        </div>

    </div>

</div>

<!-- 5. APPOINTMENT DETAILS MODAL -->
<div class="modal-overlay" id="details-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-clipboard-list"></i> Appointment Details</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('details-modal')">&times;</button>
        </div>
        <div class="m-body">
            <div style="font-family:monospace; font-weight:800; color:var(--clr-primary); font-size:1.1rem; margin-bottom:16px;">AP-2026-00452</div>
            
            <div class="kv-grid">
                <div class="kv-key">Patient</div><div class="kv-val">Patient D · 42 years · Male</div>
                <div class="kv-key">Date</div><div class="kv-val">07 Sep 2026</div>
                <div class="kv-key">Time</div><div class="kv-val">02:00 PM</div>
                <div class="kv-key">Type</div><div class="kv-val"><i class="fas fa-phone-alt"></i> Audio Consultation</div>
                <div class="kv-key">Status</div><div class="kv-val" style="color:#16A34A;">🟢 Confirmed</div>
                <div class="kv-key">Payment</div><div class="kv-val" style="color:#16A34A;">✓ Paid (₹115)</div>
            </div>

            <div style="font-weight:800; color:var(--clr-text-secondary); font-size:0.9rem; margin-bottom:8px;">Reason for Consultation</div>
            <div style="background:#F1F5F9; padding:12px; border-radius:var(--radius-sm); font-size:0.95rem;">Fever and mild cough for past 2 days. Requesting audio review.</div>
        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('details-modal')" style="color:#DC2626; border-color:#DC2626;">Cancel Appt</button>
            <div>
                <button class="btn-action" onclick="window.location.href='doctor-patients.php'">Open Patient</button>
                <button class="btn-action btn-primary" onclick="closeModal('details-modal')">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 6. PRE-CONSULTATION REVIEW MODAL -->
<div class="modal-overlay" id="review-modal">
    <div class="modal-card">
        <div class="m-head" style="background:linear-gradient(90deg, #F0FDF4 0%, #ffffff 100%);">
            <div><i class="fas fa-search-plus" style="color:#16A34A;"></i> Pre-Consultation Review</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('review-modal')">&times;</button>
        </div>
        <div class="m-body">
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
                <div style="width:64px; height:64px; border-radius:50%; background:var(--clr-primary-light); color:var(--clr-primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800;">JR</div>
                <div>
                    <div style="font-weight:800; font-size:1.2rem; color:var(--clr-text-primary);">Jampaleppa Rathod</div>
                    <div style="color:var(--clr-text-secondary); font-weight:600;">42 years · Male · O+</div>
                </div>
            </div>

            <div class="kv-grid">
                <div class="kv-key">Allergies</div><div class="kv-val" style="color:#16A34A;">None recorded</div>
                <div class="kv-key">Medicines</div><div class="kv-val">2 active medicines</div>
                <div class="kv-key">History</div><div class="kv-val">5 Previous Visits</div>
            </div>

            <div style="font-weight:800; color:var(--clr-text-secondary); font-size:0.9rem; margin-bottom:8px;">Recent Documents</div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div style="border:1px solid var(--clr-border); padding:8px 12px; border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:0.95rem; font-weight:700;"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Blood Report.pdf</div>
                    <a href="#"  style="font-size:0.85rem; font-weight:700; color:var(--clr-primary); text-decoration:none;">View</a>
                </div>
            </div>
            
        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('review-modal')">Close</button>
            <button class="btn-action btn-primary" style="background:#16A34A; border-color:#16A34A;" onclick="window.location.href='doctor-consultation-room.php'"><i class="fas fa-video"></i> START CONSULTATION</button>
        </div>
    </div>
</div>

<!-- 8. RESCHEDULE MODAL -->
<div class="modal-overlay" id="reschedule-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-calendar-times" style="color:#CA8A04;"></i> Reschedule Appointment</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('reschedule-modal')">&times;</button>
        </div>
        <div class="m-body">
            <div class="kv-grid" style="margin-bottom:16px;">
                <div class="kv-key">Patient</div><div class="kv-val">Patient D</div>
                <div class="kv-key">Current</div><div class="kv-val" style="color:#DC2626; text-decoration:line-through;">07 Sep · 02:00 PM</div>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; font-size:0.9rem; color:var(--clr-text-secondary); display:block; margin-bottom:8px;">Select New Date</label>
                <input type="date" value="2026-09-08" style="width:100%; padding:10px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-family:inherit;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-weight:700; font-size:0.9rem; color:var(--clr-text-secondary); display:block; margin-bottom:8px;">Available Slots</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <button class="btn-action" style="flex:1 1 30%;">09:30 AM</button>
                    <button class="btn-action" style="flex:1 1 30%;">10:00 AM</button>
                    <button class="btn-action" style="flex:1 1 30%; background:var(--clr-primary-light); color:var(--clr-primary); border-color:var(--clr-primary);">02:00 PM</button>
                </div>
            </div>

            <div>
                <label style="font-weight:700; font-size:0.9rem; color:var(--clr-text-secondary); display:block; margin-bottom:8px;">Reason</label>
                <input type="text" placeholder="e.g. Doctor unavailable at original time" style="width:100%; padding:10px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-family:inherit;">
            </div>
        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('reschedule-modal')">Cancel</button>
            <button class="btn-action btn-primary" style="background:#CA8A04; border-color:#CA8A04;">Confirm Reschedule</button>
        </div>
    </div>
</div>


<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    function filterAppointments(status, btn) {
        document.querySelectorAll('.st-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        const rows = document.querySelectorAll('.data-table tbody tr');
        rows.forEach(r => {
            const s = r.getAttribute('data-status') || '';
            if (status === 'all' || status === 'today') {
                r.style.display = '';
            } else if (s.indexOf(status) !== -1) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>
