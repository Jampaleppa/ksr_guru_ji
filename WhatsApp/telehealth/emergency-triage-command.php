<?php
require_once 'helper.php';
// Simulated Emergency, Triage & Urgent Care Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency & Triage Command | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Emergency Navbar */
        .emerg-nav { background: #450A0A; border-bottom: 2px solid #7F1D1D; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .emerg-nav .navbar-brand { color: #FECACA; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .emerg-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .emerg-nav-links::-webkit-scrollbar { display: none; }
        .emerg-nav-links a { color: #FCA5A5; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .emerg-nav-links a:hover { color: white; background: #991B1B; }
        .emerg-nav-links a.active { color: white; background: #DC2626; box-shadow: 0 0 10px rgba(220, 38, 38, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; overflow-y:auto; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .dt-primary { font-weight: 900; color: white; display: flex; align-items: center; gap: 8px; font-size:0.95rem; }
        .dt-secondary { font-size: 0.8rem; color: #94A3B8; margin-top: 6px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-critical { background: rgba(220, 38, 38, 0.1); color: #F87171; border-color: rgba(220, 38, 38, 0.3); }
        .bg-urgent { background: rgba(249, 115, 22, 0.1); color: #FB923C; border-color: rgba(249, 115, 22, 0.3); }
        .bg-semi { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Escalation Tracker */
        .escalation-box { background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.2); border-left: 4px solid #EF4444; border-radius: 8px; padding: 16px; margin-bottom:16px;}
        .esc-title {font-weight:900; font-size:1.1rem; color:#FCA5A5; margin-bottom:8px;}
        
        .timeline-step { display:flex; gap:16px; margin-bottom:16px; }
        .tl-icon { width:32px; height:32px; border-radius:50%; background:#334155; color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.85rem;}
        .tl-icon.done { background:#10B981; }
        .tl-icon.active { background:#DC2626; box-shadow:0 0 10px rgba(220,38,38,0.5); animation: pulse 2s infinite;}
        .tl-content h4 { margin:0 0 4px 0; font-size:0.95rem; color:white; }
        .tl-content p { margin:0; font-size:0.8rem; color:#94A3B8;}

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-danger { background: #DC2626; border-color: #B91C1C; color: white; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="emerg-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-ambulance"></i> EMERGENCY & URGENT CARE COMMAND
        </div>
        <div class="emerg-nav-links">
            <a href="emergency-triage-command.php" class="active">Live Triage Queue</a>
            <a href="ambulance-acute-care.php">Critical Escalations</a>
            <a href="logistics-command.php">Facility Routing</a>
            <a href="master-ecosystem-watchtower.php">Ecosystem Watchtower</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <a href="doctor-dashboard.php" style="color:white; text-decoration:none; display:flex; align-items:center; gap:8px;"><i class="fas fa-user-md" style="color:#EF4444;"></i> Dr. D C Rathod</a>
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-siren-on" style="color:#EF4444;"></i> Independent Triage & Escalation Engine</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating state decoupling: Triage Event ≠ Emergency Room ≠ Scheduled Appointment.</div>
        </div>
    </div>

    <!-- Active Pipeline KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">In AI Safety Screening</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">05</div>
            <div class="kpi-lbl">Pending Doctor Assign</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F97316;">
            <div class="kpi-val">02</div>
            <div class="kpi-lbl">Urgent Consults Active</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(220,38,38,0.1);">
            <div class="kpi-val" style="color:#FCA5A5;">01</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Critical Handover</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ol"></i> Live Triage Classification Queue</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient Request</th>
                            <th>Wait / State</th>
                            <th>Priority Class</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary">Anita P</div>
                                <div class="dt-secondary">Severe Chest Pain / Left Arm Numbness</div>
                            </td>
                            <td>
                                <div class="dt-primary" style="color:#F87171;">01:45 Min</div>
                                <div class="dt-secondary">Unassigned</div>
                            </td>
                            <td>
                                <span class="badge bg-critical">CRITICAL (L2)</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger">Take Case</button>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="dt-primary">Suresh K</div>
                                <div class="dt-secondary">Deep cut on hand / Bleeding controlled</div>
                            </td>
                            <td>
                                <div class="dt-primary">08:12 Min</div>
                                <div class="dt-secondary">Doctor Assigned</div>
                            </td>
                            <td>
                                <span class="badge bg-urgent">URGENT (L3)</span>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#0F172A;">Monitor</button>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <div class="dt-primary">Rahul Kumar</div>
                                <div class="dt-secondary">Persistent high fever / Cough</div>
                            </td>
                            <td>
                                <div class="dt-primary" style="color:#FBBF24;">14:30 Min</div>
                                <div class="dt-secondary">Screening Done</div>
                            </td>
                            <td>
                                <span class="badge bg-semi">SEMI-URGENT (L4)</span>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#0F172A;">Route to Queue</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Escalation Detail -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-exclamation-triangle"></i> Emergency Clinical Handover Workspace</div>
            </div>
            <div class="panel-body">
                
                <div class="escalation-box">
                    <div class="esc-title">Case: Anita P (CRITICAL LEVEL 2)</div>
                    <div style="font-size:0.85rem; color:white; line-height:1.5;">
                        <strong>Symptom Start:</strong> 45 minutes ago<br>
                        <strong>AI Observation:</strong> Keywords trigger MI Protocol. Patient advised not to drive.
                    </div>
                </div>
                
                <h3 style="font-size:0.9rem; font-weight:800; color:white; margin-top:20px; border-bottom:1px solid #334155; padding-bottom:8px;">Decoupled Emergency Workflow</h3>
                
                <div class="timeline-step">
                    <div class="tl-icon done"><i class="fas fa-check"></i></div>
                    <div class="tl-content">
                        <h4>1. AI Safety Screening (COMPLETED)</h4>
                        <p>Patient evaluated. Symptoms mapped to Level 2 Priority. Flagged for immediate doctor assignment.</p>
                    </div>
                </div>
                
                <div class="timeline-step">
                    <div class="tl-icon done"><i class="fas fa-user-md"></i></div>
                    <div class="tl-content">
                        <h4>2. Urgent Consult (IN_PROGRESS)</h4>
                        <p>Dr. Available joined at 10:15 AM. Determined case requires physical ER facility.</p>
                    </div>
                </div>
                
                <div class="timeline-step">
                    <div class="tl-icon active"><i class="fas fa-hospital-symbol"></i></div>
                    <div class="tl-content">
                        <h4 style="color:#FCA5A5;">3. Facility Routing & Handover (REQUIRED)</h4>
                        <p>Dispatching clinical summary to City General Hospital ER. Awaiting receiving facility ACK.</p>
                    </div>
                </div>

                <div class="timeline-step">
                    <div class="tl-icon"><i class="fas fa-file-medical"></i></div>
                    <div class="tl-content">
                        <h4>4. Emergency Contact Notification (PENDING)</h4>
                        <p>Waiting for handover ACK to notify authorized family contact.</p>
                    </div>
                </div>
                
                <div style="display:flex; justify-content:space-between; gap:12px; margin-top:24px;">
                    <button class="btn" style="flex:1;"><i class="fas fa-phone-alt"></i> Override Dispatch</button>
                    <button class="btn btn-danger" style="flex:1;"><i class="fas fa-paper-plane"></i> Push Clinical Handover</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
