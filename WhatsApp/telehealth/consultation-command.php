<?php
require_once 'helper.php';
// Simulated Real-Time Consultation, Video, Audio, Chat & Clinical Session Control Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Consultation Navbar */
        .cons-nav { background: #020617; border-bottom: 2px solid #1E293B; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .cons-nav .navbar-brand { color: #38BDF8; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .cons-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .cons-nav-links::-webkit-scrollbar { display: none; }
        .cons-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .cons-nav-links a:hover { color: white; background: #334155; }
        .cons-nav-links a.active { color: white; background: #0EA5E9; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.75rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .dt-primary { font-weight: 800; color: white; display: flex; align-items: center; gap: 8px; }
        .dt-secondary { font-size: 0.75rem; color: #94A3B8; margin-top: 4px; }
        
        /* Badges & Tags */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.1); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }
        .bg-neutral { background: rgba(148, 163, 184, 0.1); color: #94A3B8; border-color: rgba(148, 163, 184, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* Hardware Icons */
        .hw-icon { font-size: 1rem; color: #94A3B8; margin-right: 4px; }
        .hw-ok { color: #10B981; }
        .hw-err { color: #EF4444; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #0EA5E9; border-color: #0284C7; color: white; }
        .btn-primary:hover { background: #0284C7; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="cons-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-video"></i> CONSULTATION COMMAND CENTER
        </div>
        <div class="cons-nav-links">
            <a href="#"  class="active">Live Operations</a>
            <a href="#" >Waiting Room</a>
            <a href="#" >Technical Monitoring</a>
            <a href="#" >Emergency Escalations</a>
            <a href="#" >Recording Governance</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #0EA5E9;"> Clinical Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction: column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-satellite-dish" style="color:#0EA5E9;"></i> Live Session Orchestration</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Monitoring decoupled states: Appointment ≠ Waiting Room ≠ Media Session ≠ Clinical Documentation</div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="btn btn-primary"><i class="fas fa-search"></i> Search Active Sessions</button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <i class="fas fa-door-open kpi-icon"></i>
            <div class="kpi-val">18</div>
            <div class="kpi-lbl">Clients in Waiting Rooms</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <i class="fas fa-headset kpi-icon"></i>
            <div class="kpi-val">11</div>
            <div class="kpi-lbl">Doctor Ready (Pending Admit)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <i class="fas fa-video kpi-icon"></i>
            <div class="kpi-val">27</div>
            <div class="kpi-lbl">Active Media Sessions</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #64748B;">
            <i class="fas fa-clipboard-check kpi-icon"></i>
            <div class="kpi-val">146</div>
            <div class="kpi-lbl">Clinically Completed Today</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
            <i class="fas fa-exclamation-triangle kpi-icon"></i>
            <div class="kpi-val">3</div>
            <div class="kpi-lbl">Technical Anomalies</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title"><i class="fas fa-list-ul"></i> Global Connection Ledger</div>
            <div style="display:flex; gap:8px;">
                <input type="text" placeholder="Filter by Doctor/Patient..." style="background:#0F172A; border:1px solid #334155; color:white; padding:6px 12px; border-radius:4px; font-size:0.8rem;">
            </div>
        </div>
        <div class="panel-body" style="padding: 0; overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant Matrix</th>
                        <th>Type & Schedule</th>
                        <th>Admission Flags</th>
                        <th>Media Session State</th>
                        <th>Clinical State</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1: Ready to admit -->
                    <tr>
                        <td>
                            <div class="dt-primary">Dr. Priya (GP) <i class="fas fa-arrow-right" style="color:#64748B; font-size:0.7rem;"></i> Rahul Kumar</div>
                            <div class="dt-secondary">AP-9922 | Facility: Alpha-Virtual</div>
                        </td>
                        <td>
                            <div class="badge bg-info"><i class="fas fa-video"></i> Video</div>
                            <div class="dt-secondary">10:30 AM (Exp: 10:45)</div>
                        </td>
                        <td>
                            <!-- Admission Checks mapping to Patient Device/Identity -->
                            <div style="display:flex; gap:6px;">
                                <i class="fas fa-shield-check hw-icon hw-ok" title="Identity Verified"></i>
                                <i class="fas fa-camera hw-icon hw-ok" title="Camera Ready"></i>
                                <i class="fas fa-microphone hw-icon hw-ok" title="Microphone Ready"></i>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-warning">LOBBY / WAITING</span>
                            <div class="dt-secondary">Connection: Stable (22ms)</div>
                        </td>
                        <td>
                            <span class="badge bg-neutral">PRE-CONSULT</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-sign-in-alt"></i> Force Admit</button>
                        </td>
                    </tr>

                    <!-- Row 2: Active Video Session with Technical Degradation -->
                    <tr>
                        <td>
                            <div class="dt-primary">Dr. Ravi (Ortho) <i class="fas fa-arrow-right" style="color:#64748B; font-size:0.7rem;"></i> Suresh P</div>
                            <div class="dt-secondary">AP-9981 | Facility: Ortho-TeleHub</div>
                        </td>
                        <td>
                            <div class="badge bg-info"><i class="fas fa-video"></i> Video</div>
                            <div class="dt-secondary">11:00 AM (Ongoing 14m)</div>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <i class="fas fa-shield-check hw-icon hw-ok"></i>
                                <i class="fas fa-camera hw-icon hw-err" title="Camera Dropped"></i>
                                <i class="fas fa-microphone hw-icon hw-ok"></i>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-danger">DEGRADED / AUDIO-FALLBACK</span>
                            <div class="dt-secondary" style="color:#EF4444;"><i class="fas fa-exclamation-triangle"></i> Dropped Frames / Jitter 140ms</div>
                        </td>
                        <td>
                            <span class="badge bg-success">IN_CONSULTATION</span>
                        </td>
                        <td>
                            <button class="btn btn-sm" style="border-color:#EF4444; color:#EF4444;"><i class="fas fa-satellite-dish"></i> Ops Ping</button>
                        </td>
                    </tr>

                    <!-- Row 3: Call ended, but Clinical Documentation Pending -->
                    <tr style="background: rgba(139, 92, 246, 0.05);">
                        <td>
                            <div class="dt-primary">Dr. Aravind (Cardio) <i class="fas fa-arrow-right" style="color:#64748B; font-size:0.7rem;"></i> Anitha R</div>
                            <div class="dt-secondary">AP-9904 | Facility: Beta-Virtual</div>
                        </td>
                        <td>
                            <div class="badge bg-purple"><i class="fas fa-phone-alt"></i> Audio Only</div>
                            <div class="dt-secondary">10:45 AM (Archived)</div>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <i class="fas fa-shield-check hw-icon hw-ok"></i>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-neutral">TERMINATED (ENDED)</span>
                            <div class="dt-secondary">Duration: 18m 10s</div>
                        </td>
                        <td>
                            <span class="badge bg-warning">CLINICAL_DOC_PENDING</span>
                            <div class="dt-secondary" style="color:#FBBF24;">Rx Not Finalized</div>
                        </td>
                        <td>
                            <button class="btn btn-sm"><i class="fas fa-eye"></i> Audit Log</button>
                        </td>
                    </tr>
                    
                    <!-- Row 4: Recording Governance -->
                    <tr>
                        <td>
                            <div class="dt-primary">Dr. Kumar (Psych) <i class="fas fa-arrow-right" style="color:#64748B; font-size:0.7rem;"></i> Manoj V</div>
                            <div class="dt-secondary">AP-9999 | Facility: Gamma-Virtual</div>
                        </td>
                        <td>
                            <div class="badge bg-info"><i class="fas fa-video"></i> Video</div>
                            <div class="dt-secondary">11:15 AM (Ongoing 2m)</div>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <i class="fas fa-shield-check hw-icon hw-ok"></i>
                                <i class="fas fa-camera hw-icon hw-ok"></i>
                                <i class="fas fa-microphone hw-icon hw-ok"></i>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-success">ACTIVE & STABLE</span>
                            <div class="dt-secondary" style="color:#34D399;"><i class="fas fa-circle" style="font-size:0.6rem;"></i> RECORDING (Consent: Granted)</div>
                        </td>
                        <td>
                            <span class="badge bg-success">IN_CONSULTATION</span>
                        </td>
                        <td>
                            <button class="btn btn-sm"><i class="fas fa-eye"></i> Monitor</button>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
        <div style="padding: 16px 20px; font-size: 0.8rem; color: #94A3B8; background: #0F172A; border-top: 1px solid #334155;">
            <i class="fas fa-shield-alt"></i> <strong>Architectural State Governance:</strong> Row 3 proves that terminating a media session does <strong>not</strong> complete a consultation. The clinical engine remains active until the doctor formally finalizes the assessment and generated prescriptions.
        </div>
    </div>

</div>

</body>
</html>
