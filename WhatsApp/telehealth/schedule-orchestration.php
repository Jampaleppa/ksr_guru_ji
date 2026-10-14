<?php
require_once 'helper.php';
// Simulated Enterprise Appointment, Scheduling, Slot, Queue & Resource Orchestration Central Engine
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduling Engine & Live Queue | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Scheduling Navbar */
        .sch-nav { background: #042F2E; border-bottom: 2px solid #115E59; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .sch-nav .navbar-brand { color: #5EEAD4; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .sch-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .sch-nav-links::-webkit-scrollbar { display: none; }
        .sch-nav-links a { color: #99F6E4; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .sch-nav-links a:hover { color: white; background: #0F766E; }
        .sch-nav-links a.active { color: white; background: #0D9488; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Two Column Content Area */
        .cal-col { flex: 1.6; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
        .queue-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Dynamic Calendar Engine View */
        .schedule-grid { border: 1px solid #334155; border-radius: 8px; overflow: hidden; background: #0F172A; }
        .sg-header { display: flex; text-align: center; background: #1E293B; border-bottom: 1px solid #334155; font-size: 0.8rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; }
        .sg-row { display: flex; border-bottom: 1px solid #1E293B; min-height: 48px; }
        .sg-row:last-child { border-bottom: none; }
        
        .sg-time { width: 80px; flex-shrink: 0; padding: 12px; border-right: 1px solid #334155; display: flex; justify-content: center; align-items: center; font-family: monospace; color: #64748B; font-weight: 900; font-size: 0.9rem; }
        
        .sg-slot-cell { flex: 1; padding: 4px; display: flex; gap: 4px; background: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255,255,255,0.02) 10px, rgba(255,255,255,0.02) 20px); }
        
        /* Slot States */
        .slot { flex: 1; border-radius: 4px; padding: 8px; font-size: 0.8rem; font-weight: 700; color: white; display: flex; flex-direction: column; justify-content: center; border-left: 3px solid transparent; cursor: default; }
        .slot-available { background: rgba(16, 185, 129, 0.1); border-left-color: #10B981; color: #34D399; align-items: center;}
        .slot-reserved { background: rgba(245, 158, 11, 0.15); border-left-color: #F59E0B; }
        .slot-booked { background: rgba(59, 130, 246, 0.2); border-left-color: #3B82F6; }
        .slot-leave { background: rgba(239, 68, 68, 0.15); border-left-color: #EF4444; align-items: center; color:#EF4444; }
        
        .slot-meta { font-size: 0.7rem; font-weight: 600; opacity: 0.8; margin-top: 2px; display:flex; justify-content:space-between; align-items:center; }

        /* Live Queue Display */
        .queue-box { background: #020617; border: 1px solid #334155; border-radius: 8px; overflow: hidden; margin-bottom: 20px;}
        .qb-header { background: #1E293B; padding: 12px 16px; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .qb-name { font-weight: 900; font-size: 0.95rem; color: white; }
        .qb-now-serving { font-size: 0.8rem; color: #34D399; font-weight: 800; background: rgba(16, 185, 129, 0.1); padding: 4px 8px; border-radius: 4px; }
        
        .qb-list { padding: 8px; display: flex; flex-direction: column; gap: 4px; }
        .qb-item { display: flex; align-items: center; padding: 12px; background: #0F172A; border: 1px solid #1E293B; border-radius: 6px; }
        .qb-token { width: 60px; font-family: monospace; font-size: 1.1rem; font-weight: 900; color: #93C5FD; }
        .qb-info { flex: 1; }
        .qb-target { font-size: 0.9rem; color: white; font-weight: 700; margin-bottom: 2px; }
        .qb-meta { font-size: 0.75rem; color: #64748B; font-weight: 600; display:flex; gap:12px; }
        
        /* Badges */
        .q-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; }
        .q-waiting { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
        .q-in-room { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .q-checkin { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #0D9488; border-color: #0F766E; color: white; }
        .btn-primary:hover { background: #0F766E; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="sch-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-calendar-alt"></i> SCHEDULING & ORCHESTRATION ENGINE
        </div>
        <div class="sch-nav-links">
            <a href="#"  class="active">Live Operations</a>
            <a href="#" >Fleet & Room Capacity</a>
            <a href="#" >Waitlist Allocation</a>
            <a href="#" >Leave & Policy Maps</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #5EEAD4;"> Scheduling Lead
            </div>
        </div>
    </div>
</div>

<div class="app-container">

    <div class="cal-col">
        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-calendar-check" style="color:#5EEAD4;"></i> Today's Schedule Execution</h1>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Date: 05 SEP 2026 | Timezone: IST (UTC+5:30) | Mysuru Medical Center </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn"><i class="fas fa-search"></i> Find Slot</button>
                <button class="btn btn-primary"><i class="fas fa-ban"></i> Block Room/Doctor</button>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
                <i class="fas fa-calendar-day kpi-icon"></i>
                <div class="kpi-val">286</div>
                <div class="kpi-lbl">Total Appointments Active</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <i class="fas fa-check-circle kpi-icon"></i>
                <div class="kpi-val">126</div>
                <div class="kpi-lbl">Completed / Finished</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <i class="fas fa-lock kpi-icon"></i>
                <div class="kpi-val">18</div>
                <div class="kpi-lbl">Slots Held in Payment (Reserved)</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <i class="fas fa-user-slash kpi-icon"></i>
                <div class="kpi-val">4</div>
                <div class="kpi-lbl">Doctors on Leave</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-stream"></i> Cardiology Department - Doctor Availability Matrix</div>
                <div style="display:flex; gap:12px; font-size:0.75rem; font-weight:800; color:#94A3B8;">
                    <span style="color:#3B82F6;"><i class="fas fa-square"></i> Booked</span>
                    <span style="color:#F59E0B;"><i class="fas fa-square"></i> Reserved (Payment)</span>
                    <span style="color:#34D399;"><i class="fas fa-square"></i> Available</span>
                    <span style="color:#EF4444;"><i class="fas fa-square"></i> Leave / Block</span>
                </div>
            </div>
            <div class="panel-body" style="padding: 0;">
                
                <div class="schedule-grid">
                    <div class="sg-header">
                        <div class="sg-time">TIME</div>
                        <div style="flex:1; padding:12px; border-right: 1px solid #334155;">Dr. Aravind Sharma</div>
                        <div style="flex:1; padding:12px;">Dr. Sunita Patel</div>
                    </div>

                    <div class="sg-row">
                        <div class="sg-time">09:00</div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-booked">
                                <div>PA-00123 <span class="badge" style="padding:0 4px; background:rgba(0,0,0,0.3); float:right;">Video</span></div>
                                <div class="slot-meta"><span>Consultation Confirm</span> <span><i class="fas fa-check"></i></span></div>
                            </div>
                        </div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-booked" style="border-left-color:#8B5CF6; background:rgba(139,92,246,0.15);">
                                <div>Emergency Triage Block <span class="badge" style="padding:0 4px; background:rgba(0,0,0,0.3); float:right;">ER</span></div>
                                <div class="slot-meta"><span>Diverted by Policy</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="sg-row">
                        <div class="sg-time">09:30</div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-reserved">
                                <div>Booking in Progress...</div>
                                <div class="slot-meta"><span style="color:#FBBF24;"><i class="fas fa-spinner fa-spin"></i> Awaiting Payment Gateway</span></div>
                            </div>
                        </div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-available">
                                <i class="fas fa-plus"></i>
                                <span style="font-size:0.7rem; margin-top:2px;">Available Slot (30m)</span>
                            </div>
                        </div>
                    </div>

                    <div class="sg-row">
                        <div class="sg-time">10:00</div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-available">
                                <i class="fas fa-plus"></i>
                                <span style="font-size:0.7rem; margin-top:2px;">Available Slot (30m)</span>
                            </div>
                        </div>
                        <div class="sg-slot-cell">
                            <div class="slot slot-leave">
                                <i class="fas fa-ban"></i>
                                <span style="font-size:0.7rem; margin-top:2px;">Approved Administrative Leave</span>
                            </div>
                        </div>
                    </div>

                </div>

                <div style="padding: 16px 20px; font-size: 0.8rem; color: #94A3B8; background: #0F172A; border-top: 1px solid #334155;">
                    <i class="fas fa-shield-alt"></i> <strong>Engine Rule:</strong> Dr. Sunita's 10:00 AM slot is blocked by Layer 8 (Leave Event). It is physically impossible for the Slot Engine to map an appointment into this space, regardless of the Room availability.
                </div>
            </div>
        </div>
    </div>

    <!-- Live Queue Column -->
    <div class="queue-col">
        <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-users" style="color:#3B82F6;"></i> Live Patient Queues</h1>
        <div style="font-size:0.85rem; color:#94A3B8; margin-top:-20px;">Queue states are fully decoupled from Appointment configurations.</div>

        <div class="queue-box">
            <div class="qb-header">
                <div class="qb-name"><i class="fas fa-building"></i> Physical: Cardiology Room 12</div>
                <div class="qb-now-serving"><i class="fas fa-headset"></i> Serving: TK-402</div>
            </div>
            <div class="qb-list">
                
                <div class="qb-item" style="border-left: 3px solid #10B981; background: rgba(16, 185, 129, 0.05);">
                    <div class="qb-token">TK-402</div>
                    <div class="qb-info">
                        <div class="qb-target">Consultation In Progress</div>
                        <div class="qb-meta"><span>Appt: AP-9912</span> <span>Joined: 09:05 AM</span></div>
                    </div>
                    <div class="q-badge q-in-room">IN ROOM</div>
                </div>

                <div class="qb-item" style="border-left: 3px solid #F59E0B;">
                    <div class="qb-token">TK-403</div>
                    <div class="qb-info">
                        <div class="qb-target">Patient Checked-In & Waiting</div>
                        <div class="qb-meta"><span>Appt: AP-9915 (09:30 AM)</span> <span>Queue Time: 12m</span></div>
                    </div>
                    <div class="q-badge q-waiting">WAITING</div>
                </div>

                <div class="qb-item" style="border-left: 3px solid #3B82F6;">
                    <div class="qb-token">TK-404</div>
                    <div class="qb-info">
                        <div class="qb-target">Identity Verification Pending</div>
                        <div class="qb-meta"><span>Appt: AP-9918 (10:00 AM)</span> <span>Kiosk 2</span></div>
                    </div>
                    <div class="q-badge q-checkin">CHECK-IN</div>
                </div>

            </div>
        </div>

        <div class="queue-box">
            <div class="qb-header">
                <div class="qb-name"><i class="fas fa-video"></i> Virtual: Telemedicine Hub Alpha</div>
                <div class="qb-now-serving" style="background: rgba(139, 92, 246, 0.2); color:#C4B5FD;"><i class="fas fa-satellite-dish"></i> 2 Streams Active</div>
            </div>
            <div class="qb-list">
                
                <div class="qb-item" style="border-left: 3px solid #8B5CF6; background: rgba(139, 92, 246, 0.05);">
                    <div class="qb-token">VR-118</div>
                    <div class="qb-info">
                        <div class="qb-target">Patient in Secure Virtual Lobby</div>
                        <div class="qb-meta"><span>Appt: AP-9922</span> <span>Hardware Check: Passed</span></div>
                    </div>
                    <div class="q-badge" style="background:rgba(139,92,246,0.1); color:#A78BFA;">LOBBY</div>
                </div>

            </div>
            <div style="padding: 12px; font-size: 0.75rem; color: #94A3B8; text-align: center; border-top: 1px solid #1E293B;">
                Virtual queues track hardware states independent of physical boundaries.
            </div>
        </div>

    </div>

</div>

</body>
</html>
