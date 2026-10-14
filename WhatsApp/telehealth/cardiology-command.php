<?php
require_once 'helper.php';
// Simulated Cardiology, Cardiac Diagnostics, Arrhythmia Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardiology Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Cardiology Navbar */
        .cardio-nav { background: #7F1D1D; border-bottom: 2px solid #991B1B; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .cardio-nav .navbar-brand { color: #FECACA; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .cardio-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .cardio-nav-links::-webkit-scrollbar { display: none; }
        .cardio-nav-links a { color: #F87171; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .cardio-nav-links a:hover { color: white; background: #DC2626; }
        .cardio-nav-links a.active { color: white; background: #EF4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; overflow-y:auto; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0B1120; }
        .data-table tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-critical { background: rgba(239, 68, 68, 0.1); color: #F87171; border-color: rgba(239, 68, 68, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-red { background: #DC2626; border-color: #B91C1C; color: white; }
        .btn-red:hover { background: #B91C1C; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        .ecg-waveform { width: 100%; height: 60px; background: url('data:image/svg+xml;utf8,<svg width="100" height="40" xmlns="http://www.w3.org/2000/svg"><path d="M 0 20 L 10 20 L 15 10 L 20 35 L 25 5 L 30 20 L 40 20 L 45 15 L 50 20 L 100 20" stroke="%23EF4444" fill="none" stroke-width="2"/></svg>') repeat-x; background-size: 100px 100%; opacity: 0.8; margin-top: 12px; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="cardio-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-heartbeat"></i> CARDIOLOGY COMMAND
        </div>
        <div class="cardio-nav-links">
            <a href="cardiology-command.php" class="active">Cardiology Consults</a>
            <a href="diagnostic-command.php">ECG & Diagnostics</a>
            <a href="consultation-command.php">Consultations</a>
            <a href="master-ecosystem-watchtower.php">Watchtower</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <a href="doctor-dashboard.php" style="color:white; text-decoration:none;"><i class="fas fa-user-md" style="color:#FECACA;"></i> Dr. D C Rathod</a>
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-wave-square" style="color:#EF4444;"></i> Cardiovascular Operations Center</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Arrhythmia validation, ECG integration, Echo reviews, and Heart Failure pathways.</div>
        </div>
        <div>
            <button class="btn btn-red" onclick="window.location.href='doctor-consultation-room.php'"><i class="fas fa-notes-medical"></i> New Cardiac Consult</button>
        </div>
    </div>

    <!-- Active KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">74</div>
            <div class="kpi-lbl">Active Cardiac Cases</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">18</div>
            <div class="kpi-lbl">Active Holter Monitors</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">26</div>
            <div class="kpi-lbl">Pending Diagnostics (ECG/Echo)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val pulse-red" style="color:#FCA5A5;">4</div>
            <div class="kpi-lbl" style="color:#FCA5A5;"><i class="fas fa-exclamation-triangle"></i> Arrhythmia Alerts</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Cardiology Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ol"></i> Priority Cardiac Queue</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Location</th>
                            <th>Pathway / Issue</th>
                            <th>Pending Diagnostics</th>
                            <th>Status & Disposition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Cardiac Alert / Arrhythmia -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Martinez, R. (Age 55)</div>
                                <div style="font-size:0.75rem; color:#FCA5A5; margin-top:4px;"><i class="fas fa-ambulance"></i> ED Bed 4</div>
                            </td>
                            <td>
                                <span class="badge bg-critical"><i class="fas fa-heartbeat"></i> NEW AFIB WITH RVR</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">HR 145 bpm, symptomatic</div>
                            </td>
                            <td>
                                <span class="badge bg-blue"><i class="fas fa-file-medical-alt"></i> 12-LEAD ECG DONE</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-red">Urgent Consult Review</button>
                            </td>
                        </tr>
                        <!-- Holter Monitor Review -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Taylor, S. (Age 42)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-home"></i> Remote Monitor</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">PALPITATIONS EVAL</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">48hr Holter Returned</div>
                            </td>
                            <td>
                                <span class="badge bg-purple"><i class="fas fa-microchip"></i> DATA UPLOADED</span>
                            </td>
                            <td>
                                <button class="btn btn-sm">Clinical Interpretation</button>
                            </td>
                        </tr>
                        <!-- Routine Heart Failure OPD -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Brown, K. (Age 73)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-clinic-medical"></i> OPD Cardiology</div>
                            </td>
                            <td>
                                <span class="badge bg-success">CHF MANAGEMENT</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Routine Follow-up</div>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">TTE Echo Pending</span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Awaiting Diagnostics</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Arrhythmia Alert Resolution Workspace -->
        <div class="panel">
            <div class="panel-head" style="background:rgba(239, 68, 68, 0.1);">
                <div class="panel-title" style="color:#FCA5A5;"><i class="fas fa-heart-broken"></i> Arrhythmia Action Hub</div>
            </div>
            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="font-weight:900; color:white; font-size:1.1rem;">Martinez, R. (ED Referral)</div>
                    <div style="font-size:0.8rem; color:#FCA5A5;">Device: ED GE-Mac 12-Lead • Uploaded 5 mins ago</div>
                </div>

                <div style="background:#0F172A; border:1px solid #334155; padding:16px; border-radius:8px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                        <span style="font-weight:800; color:#FCA5A5;">AI Pre-Read</span>
                        <span style="font-weight:900; color:white;">Atrial Fibrillation, Rapid Ventricular Response</span>
                    </div>
                    
                    <div class="ecg-waveform"></div>
                    
                    <div style="margin-top:16px; font-size:0.85rem; color:#94A3B8;">
                        <i class="fas fa-exclamation-triangle" style="color:#F59E0B;"></i> Machine Interpretation indicates AFib. Note: This requires formal cardiologist over-read before ED disposition.
                    </div>
                </div>

                <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-sm btn-red" style="flex:1;"><i class="fas fa-check"></i> Verify AFib</button>
                    <button class="btn btn-sm" style="flex:1;"><i class="fas fa-exchange-alt"></i> Route to ED / Rx</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
