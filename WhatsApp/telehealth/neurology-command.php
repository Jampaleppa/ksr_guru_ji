<?php
require_once 'helper.php';
// Simulated Neurology, Stroke, Neurocritical Care & Neurological Care Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neurology & Stroke Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Neurology Navbar */
        .neuro-nav { background: #312E81; border-bottom: 2px solid #4338CA; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .neuro-nav .navbar-brand { color: #C7D2FE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .neuro-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .neuro-nav-links::-webkit-scrollbar { display: none; }
        .neuro-nav-links a { color: #818CF8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .neuro-nav-links a:hover { color: white; background: #4F46E5; }
        .neuro-nav-links a.active { color: white; background: #6366F1; box-shadow: 0 0 10px rgba(99, 102, 241, 0.5); }

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
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-purple { background: #6366F1; border-color: #4F46E5; color: white; }
        .btn-purple:hover { background: #4F46E5; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Stroke Timeline */
        .stroke-timeline { border-left: 2px solid #334155; padding-left: 16px; margin: 10px 0; }
        .st-event { position: relative; margin-bottom: 12px; }
        .st-event::before { content: ''; position: absolute; width: 10px; height: 10px; background: #6366F1; border-radius: 50%; left: -22px; top: 4px; box-shadow: 0 0 0 2px #1E293B; }
        .st-event.st-pending::before { background: #475569; }
        .st-time { font-size: 0.75rem; color: #94A3B8; font-weight: 800; }
        .st-desc { font-size: 0.85rem; color: white; font-weight: 600; margin-top:2px; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="neuro-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-brain"></i> NEUROLOGY & STROKE COMMAND
        </div>
        <div class="neuro-nav-links">
            <a href="neurology-command.php" class="active">Neurology OPD & Consults</a>
            <a href="emergency-triage-command.php">Acute Stroke Pathway</a>
            <a href="diagnostic-command.php">Seizure / EEG</a>
            <a href="consultation-command.php">Neuro Consults & Rehab</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <a href="doctor-dashboard.php" style="color:white; text-decoration:none;"><i class="fas fa-user-md" style="color:#A78BFA;"></i> Dr. D C Rathod</a>
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-network-wired" style="color:#818CF8;"></i> Neurological Operations Center</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Orchestrating time-critical stroke responses, EEG diagnostics, and outpatient neuro-care.</div>
        </div>
        <div>
            <button class="btn btn-purple" onclick="window.location.href='emergency-triage-command.php'"><i class="fas fa-bolt"></i> Declare Code Stroke</button>
        </div>
    </div>

    <!-- Active KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">42</div>
            <div class="kpi-lbl">Active Neuro Cases</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val pulse-red" style="color:#FCA5A5;">2</div>
            <div class="kpi-lbl" style="color:#FCA5A5;"><i class="fas fa-siren-on"></i> Acute Stroke Pathways</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">17</div>
            <div class="kpi-lbl">Pending Neuro Consults</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">8</div>
            <div class="kpi-lbl">Rehab Follow-ups Due</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Neurology Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ol"></i> Priority Neurological Queue</div>
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
                        <!-- Code Stroke -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Doe, John (Age 64)</div>
                                <div style="font-size:0.75rem; color:#FCA5A5; margin-top:4px;"><i class="fas fa-ambulance"></i> ED Resus-01</div>
                            </td>
                            <td>
                                <span class="badge bg-critical"><i class="fas fa-brain"></i> ACUTE STROKE</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Left-sided hemiparesis, onset < 2 hrs</div>
                            </td>
                            <td>
                                <span class="badge bg-purple"><i class="fas fa-x-ray"></i> CT PERFUSION DONE</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-purple">Review Treatment Eligibility</button>
                            </td>
                        </tr>
                        <!-- Seizure Routine -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Gomez, M. (Age 29)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-bed"></i> Inpatient Ward 4B</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">SEIZURE EVENT</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">New onset tonic-clonic</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#F59E0B; color:#FBBF24;"><i class="fas fa-wave-square"></i> EEG PENDING</span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Awaiting Specialist Review</span>
                            </td>
                        </tr>
                        <!-- Outpatient Follow up -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Zhang, L. (Age 71)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-clinic-medical"></i> OPD Telehealth</div>
                            </td>
                            <td>
                                <span class="badge bg-success">NEURODEGENERATIVE</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Parkinson's Disease Mgmt</div>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">None Ordered</span>
                            </td>
                            <td>
                                <button class="btn btn-sm"><i class="fas fa-video"></i> Init Consult</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stroke Pathway Timeline -->
        <div class="panel">
            <div class="panel-head" style="background:rgba(239, 68, 68, 0.1);">
                <div class="panel-title" style="color:#FCA5A5;"><i class="fas fa-stopwatch"></i> Active Stroke Timeline</div>
            </div>
            <div class="panel-body">
                <div style="margin-bottom:12px;">
                    <div style="font-weight:900; color:white; font-size:1.1rem;">Doe, John</div>
                    <div style="font-size:0.8rem; color:#FCA5A5;">Target: Door-to-Needle < 60 mins</div>
                </div>

                <div class="stroke-timeline">
                    <div class="st-event">
                        <div class="st-time">10:02 AM</div>
                        <div class="st-desc">Emergency Arrival & Triage</div>
                    </div>
                    <div class="st-event">
                        <div class="st-time">10:07 AM</div>
                        <div class="st-desc" style="color:#A78BFA;">Code Stroke Pathway Activated</div>
                    </div>
                    <div class="st-event">
                        <div class="st-time">10:15 AM</div>
                        <div class="st-desc">NIHSS Assessment Completed (Score: 14)</div>
                    </div>
                    <div class="st-event">
                        <div class="st-time">10:28 AM</div>
                        <div class="st-desc">CT Head / CTA Completed (via Diagnostics)</div>
                    </div>
                    <div class="st-event">
                        <div class="st-time">10:35 AM</div>
                        <div class="st-desc">Radiologist Interpretation Verified</div>
                    </div>
                    <div class="st-event st-pending">
                        <div class="st-time">Pending Neurologist</div>
                        <div class="st-desc" style="color:#94A3B8;">Treatment Eligibility Decision</div>
                    </div>
                </div>

                <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-sm btn-purple" style="flex:1;"><i class="fas fa-file-medical"></i> Review Hub</button>
                    <button class="btn btn-sm" style="flex:1;"><i class="fas fa-exchange-alt"></i> Disposition</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
