<?php
require_once 'helper.php';
// Simulated Critical Care, ICU, High-Dependency Unit & Continuous Patient Monitoring Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critical Care & ICU Monitoring | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized ICU Navbar */
        .icu-nav { background: #0F172A; border-bottom: 2px solid #1E293B; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .icu-nav .navbar-brand { color: #60A5FA; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .icu-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .icu-nav-links::-webkit-scrollbar { display: none; }
        .icu-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .icu-nav-links a:hover { color: white; background: #334155; }
        .icu-nav-links a.active { color: white; background: #3B82F6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0B1120; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; overflow-y:auto; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-critical { background: rgba(239, 68, 68, 0.1); color: #F87171; border-color: rgba(239, 68, 68, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-blue { background: #3B82F6; border-color: #2563EB; color: white; }
        .btn-blue:hover { background: #2563EB; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        .vital-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .vital-box { background: #0F172A; padding: 10px; border-radius: 6px; border: 1px solid #334155; text-align: center; }
        .vital-name { font-size: 0.7rem; color: #94A3B8; text-transform: uppercase; font-weight: 800; margin-bottom:4px; }
        .vital-value { font-size: 1.2rem; font-weight: 900; color: white; }
        
        .pulse-red { animation: pulseRed 2s infinite; color: #EF4444; }
        @keyframes pulseRed { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="icu-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-heart-pulse"></i> CRITICAL CARE & ICU MONITORING
        </div>
        <div class="icu-nav-links">
            <a href="#"  class="active">ICU Flowsheets & Monitors</a>
            <a href="#" >Ventilator / Devices</a>
            <a href="#" >Infusion Titration</a>
            <a href="#" >Multidisciplinary Rounds</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-md" style="color:#60A5FA;"></i> Critical Care Attending
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-desktop" style="color:#3B82F6;"></i> Continuous High-Acuity Monitoring</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Integration of Device Telemetry, Automated Flowsheets, and Infusion Management safely decoupled from static clinical records.</div>
        </div>
        <div>
            <button class="btn btn-blue"><i class="fas fa-sync-alt"></i> Refresh Device Bridges</button>
        </div>
    </div>

    <!-- Active ICU KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">18 / 20</div>
            <div class="kpi-lbl">ICU Census</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">6</div>
            <div class="kpi-lbl">Patients on Ventilator</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">Active IV Infusions</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val pulse-red">2</div>
            <div class="kpi-lbl pulse-red">Deterioration Alerts (NEWS)</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live ICU Central Station -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-wave-square"></i> Continuous Vital Telemetry (Central Station)</div>
                <span class="badge bg-success" style="font-size:0.5rem;"><i class="fas fa-wifi"></i> HL7 CONNECTED</span>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Bed</th>
                            <th>Continuous Vitals</th>
                            <th>Device Connectivity</th>
                            <th>Clinical Escalations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Rao, S. (Age 58)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">ICU Bed 01</div>
                            </td>
                            <td>
                                <div class="vital-grid">
                                    <div class="vital-box" style="border-color:rgba(239,68,68,0.5);">
                                        <div class="vital-name">HR</div>
                                        <div class="vital-value pulse-red">135</div>
                                    </div>
                                    <div class="vital-box" style="border-color:rgba(239,68,68,0.5);">
                                        <div class="vital-name">BP</div>
                                        <div class="vital-value" style="color:#EF4444;">85/50</div>
                                    </div>
                                    <div class="vital-box">
                                        <div class="vital-name">SpO2</div>
                                        <div class="vital-value" style="color:#A7F3D0;">98%</div>
                                    </div>
                                    <div class="vital-box">
                                        <div class="vital-name">RR</div>
                                        <div class="vital-value">18</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#3B82F6; color:#60A5FA;">Philips IntelliVue</span>
                            </td>
                            <td><span class="badge bg-critical">HEMODYNAMIC INSTABILITY</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Menon, K. (Age 42)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">ICU Bed 04</div>
                            </td>
                            <td>
                                <div class="vital-grid">
                                    <div class="vital-box">
                                        <div class="vital-name">HR</div>
                                        <div class="vital-value">88</div>
                                    </div>
                                    <div class="vital-box">
                                        <div class="vital-name">BP</div>
                                        <div class="vital-value">110/70</div>
                                    </div>
                                    <div class="vital-box" style="border-color:rgba(245,158,11,0.5);">
                                        <div class="vital-name">SpO2</div>
                                        <div class="vital-value" style="color:#FBBF24;">91%</div>
                                    </div>
                                    <div class="vital-box">
                                        <div class="vital-name">RR</div>
                                        <div class="vital-value">22</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#3B82F6; color:#60A5FA;">GE Carescape</span>
                            </td>
                            <td><span class="badge bg-warning">VENT PARAMETER OFF-TARGET</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Infusion Management & Medications -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-syringe"></i> High-Risk Infusion Titration</div>
            </div>
            <div class="panel-body">
                
                <div style="background:#0F172A; border:1px solid rgba(239, 68, 68, 0.3); border-radius:8px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <div style="font-size:1.1rem; font-weight:900; color:white;">Noradrenaline (Norepinephrine)</div>
                            <div style="font-size:0.8rem; color:#FCA5A5;">Continuous IV Infusion via Central Line</div>
                        </div>
                        <span class="badge bg-critical">HIGH RISK MED</span>
                    </div>

                    <div style="margin-bottom:16px;">
                        <div style="font-size:0.8rem; color:#94A3B8; margin-bottom:4px;">Patient: Rao, S. (Bed 01)</div>
                        <div style="font-weight:800; color:white; font-size:0.9rem;">Target MAP: > 65 mmHg</div>
                        <div style="margin-top:12px; background:#1E293B; border:1px solid #334155; padding:8px; border-radius:6px;">
                            <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
                                <span style="color:#94A3B8;">Current Rate:</span>
                                <span style="font-weight:bold; color:white;">0.05 mcg/kg/min</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-top:4px;">
                                <span style="color:#94A3B8;">Current MAP:</span>
                                <span style="font-weight:bold; color:#EF4444;">61 mmHg (Low)</span>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                        <button class="btn btn-sm" style="flex:1;"><i class="fas fa-edit"></i> Record Titration Event</button>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
