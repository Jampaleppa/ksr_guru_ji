<?php
require_once 'helper.php';
// Simulated Neuro-Rehabilitation & Seizure Monitoring Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuro-Rehabilitation Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Neuro-Rehab Navbar */
        .rehab-nav { background: #4C1D95; border-bottom: 2px solid #5B21B6; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .rehab-nav .navbar-brand { color: #DDD6FE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .rehab-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .rehab-nav-links::-webkit-scrollbar { display: none; }
        .rehab-nav-links a { color: #A78BFA; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .rehab-nav-links a:hover { color: white; background: #6D28D9; }
        .rehab-nav-links a.active { color: white; background: #7C3AED; box-shadow: 0 0 10px rgba(124, 58, 237, 0.5); }

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
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }
        .bg-emerald { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(16, 185, 129, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-purple { background: #7C3AED; border-color: #5B21B6; color: white; }
        .btn-purple:hover { background: #6D28D9; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Goal Progress */
        .rehab-progress { height: 8px; background: rgba(255, 255, 255, 0.1); border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .rehab-filled { height: 100%; background: #A78BFA; border-radius: 4px; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="rehab-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-brain"></i> NEURO-REHABILITATION & SEIZURE CENTER
        </div>
        <div class="rehab-nav-links">
            <a href="#"  class="active">Post-Stroke Rehab</a>
            <a href="#" >Seizure Event Logging</a>
            <a href="#" >Cognitive Assessment</a>
            <a href="#" >EEG/EMG Handoffs</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-running" style="color:#DDD6FE;"></i> Neuro-Rehab Specialist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-dumbbell" style="color:#A78BFA;"></i> Recovery & Monitoring Hub</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Coordinating physical therapy, cognitive assessments, and post-seizure telemetry.</div>
        </div>
        <div>
            <button class="btn btn-purple"><i class="fas fa-clipboard-list"></i> Create Care Plan</button>
        </div>
    </div>

    <!-- Active KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #7C3AED;">
            <div class="kpi-val">54</div>
            <div class="kpi-lbl">Active Rehab Programs</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">37</div>
            <div class="kpi-lbl">Stroke Follow-ups</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">68</div>
            <div class="kpi-lbl">Seizure Monitoring</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B; background:rgba(245,158,11,0.05);">
            <div class="kpi-val" style="color:#FBBF24;">19</div>
            <div class="kpi-lbl" style="color:#FBBF24;"><i class="fas fa-wave-square"></i> EEG Pending Rx Review</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Neurological Care Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ul"></i> Recovery & Event Queue</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Referral</th>
                            <th>Pathway / Issue</th>
                            <th>Diagnostics / Monitor</th>
                            <th>Action & Disposition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Post Stroke Rehab -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Miller, T. (Age 71)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-hospital-user"></i> Discharged from Stroke Unit</div>
                            </td>
                            <td>
                                <span class="badge bg-purple"><i class="fas fa-walking"></i> MOTOR REHABILITATION</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Left hemiparesis recovery</div>
                            </td>
                            <td>
                                <span class="badge bg-emerald"><i class="fas fa-check"></i> PT ASSESSMENT COMPLETED</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-purple">Approve Plan</button>
                            </td>
                        </tr>
                        <!-- Seizure Event -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Chen, L. (Age 24)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-mobile-alt"></i> Patient App Report</div>
                            </td>
                            <td>
                                <span class="badge bg-critical"><i class="fas fa-bolt"></i> SEIZURE EVENT LOGGED</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Reported via Caregiver</div>
                            </td>
                            <td>
                                <span class="badge bg-warning"><i class="fas fa-pills"></i> MEDICATION REVIEW PENDING</span>
                            </td>
                            <td>
                                <button class="btn btn-sm"><i class="fas fa-file-medical"></i> Review Entry</button>
                            </td>
                        </tr>
                        <!-- Cognitive Follow-up -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Davis, R. (Age 82)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-clinic-medical"></i> Memory Clinic</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#8B5CF6; color:#A78BFA;">COGNITIVE ASSESSMENT</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">MoCA testing required</div>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Previous Score: 18/30</span>
                            </td>
                            <td>
                                <button class="btn btn-sm"><i class="fas fa-brain"></i> Start Eval</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seizure Action / PT Tracker Hub -->
        <div class="panel">
            <div class="panel-head" style="background:rgba(139, 92, 246, 0.1);">
                <div class="panel-title" style="color:#A78BFA;"><i class="fas fa-chart-bar"></i> Motor Recovery Tracker</div>
            </div>
            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="font-weight:900; color:white; font-size:1.1rem;">Miller, T. (Post-Stroke Care Plan)</div>
                    <div style="font-size:0.8rem; color:#A78BFA;">Source: Physiotherapy Dept • 4 Weeks Post-Discharge</div>
                </div>

                <div style="background:#0F172A; border:1px solid #334155; padding:16px; border-radius:8px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; align-items:center;">
                        <span style="font-weight:800; color:#A78BFA;">Lower Extremity Motor Score (LEMS)</span>
                        <span style="font-weight:900; color:white;">40 / 50</span>
                    </div>
                    <div class="rehab-progress"><div class="rehab-filled" style="width: 80%;"></div></div>
                    
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px; margin-top:16px; align-items:center;">
                        <span style="font-weight:800; color:#A78BFA;">Gait & Balance Integration</span>
                        <span style="font-weight:900; color:white;">Moderate Assist</span>
                    </div>
                    <div class="rehab-progress"><div class="rehab-filled" style="width: 50%; background:#FBBF24;"></div></div>
                    
                    <div style="margin-top:16px; font-size:0.85rem; color:#94A3B8; border-top:1px solid #334155; padding-top:12px;">
                        <i class="fas fa-robot" style="color:#7C3AED;"></i> AI Insight: Upper extremity strength plateauing. Patient missed 2 outpatient sessions last week. Suggesting Care Coordinator follow-up for adherence. 
                    </div>
                </div>

                <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-sm btn-purple" style="flex:1;"><i class="fas fa-sync"></i> Extend Therapy Order</button>
                    <button class="btn btn-sm" style="flex:1;"><i class="fas fa-phone-alt"></i> Contact Coordinator</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
