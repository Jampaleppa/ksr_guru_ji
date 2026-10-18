<?php
require_once 'helper.php';
// Simulated Pulmonology, Respiratory Care, Sleep Medicine Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulmonology & Respiratory Care | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Pulmonology Navbar */
        .pulmo-nav { background: #064E3B; border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .pulmo-nav .navbar-brand { color: #A7F3D0; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .pulmo-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .pulmo-nav-links::-webkit-scrollbar { display: none; }
        .pulmo-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .pulmo-nav-links a:hover { color: white; background: #059669; }
        .pulmo-nav-links a.active { color: white; background: #10B981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }

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
        .bg-teal { background: rgba(20, 184, 166, 0.1); color: #2DD4BF; border-color: rgba(20, 184, 166, 0.3); }
        .bg-indigo { background: rgba(99, 102, 241, 0.1); color: #818CF8; border-color: rgba(99, 102, 241, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-teal { background: #0D9488; border-color: #0F766E; color: white; }
        .btn-teal:hover { background: #0F766E; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="pulmo-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-lungs"></i> PULMONOLOGY & SLEEP MEDICINE
        </div>
        <div class="pulmo-nav-links">
            <a href="#"  class="active">Respiratory Consults</a>
            <a href="#" >PFT & Diagnostics</a>
            <a href="#" >Sleep Medicine</a>
            <a href="#" >Pulmonary Rehab</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-md" style="color:#A7F3D0;"></i> Pulmonologist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-wind" style="color:#10B981;"></i> Respiratory Operations Center</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Orchestrating Asthma, COPD, Sleep Studies, and Spirometry Diagnostics mapping to long-term patient records.</div>
        </div>
        <div>
            <button class="btn btn-teal"><i class="fas fa-notes-medical"></i> New Respiratory Consult</button>
        </div>
    </div>

    <!-- Active KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">56</div>
            <div class="kpi-lbl">Active Pulmo Cases</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">18</div>
            <div class="kpi-lbl">PFT / Diagnostics Pending</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #6366F1;">
            <div class="kpi-val">7</div>
            <div class="kpi-lbl">Sleep Studies (Awaiting Read)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val pulse-red" style="color:#FCA5A5;">4</div>
            <div class="kpi-lbl" style="color:#FCA5A5;"><i class="fas fa-exclamation-circle"></i> Exacerbation Alerts</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Pulmonology Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ol"></i> Pulmonary & Sleep Queue</div>
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
                        <!-- Respiratory Alert -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Wilson, M. (Age 68)</div>
                                <div style="font-size:0.75rem; color:#FCA5A5; margin-top:4px;"><i class="fas fa-bed"></i> Ward 3 (General)</div>
                            </td>
                            <td>
                                <span class="badge bg-critical"><i class="fas fa-lungs-virus"></i> COPD EXACERBATION</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Oxygen Saturation dropping</div>
                            </td>
                            <td>
                                <span class="badge bg-warning"><i class="fas fa-vial"></i> ABG PENDING</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-teal">Review Oxygen Escalation</button>
                            </td>
                        </tr>
                        <!-- Outpatient PFT -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Smith, J. (Age 34)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-clinic-medical"></i> OPD Pulmonology</div>
                            </td>
                            <td>
                                <span class="badge bg-success">ASTHMA EVALUATION</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Assess bronchodilator reversibility</div>
                            </td>
                            <td>
                                <span class="badge bg-teal"><i class="fas fa-lungs"></i> SPIROMETRY DONE</span>
                            </td>
                            <td>
                                <button class="btn btn-sm">Clinical Interpretation</button>
                            </td>
                        </tr>
                        <!-- Sleep Study -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Chen, D. (Age 52)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-moon"></i> Sleep Lab Room 1</div>
                            </td>
                            <td>
                                <span class="badge bg-indigo"><i class="fas fa-bed"></i> SLEEP APNEA (OSA)</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Polysomnography Night 1</div>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Study in Progress</span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Awaiting Tech Review</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PFT Interpretation Workspace -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title" style="color:#A7F3D0;"><i class="fas fa-microscope"></i> PFT / Spirometry Review</div>
            </div>
            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="font-weight:900; color:white; font-size:1.1rem;">Smith, J. (Asthma Eval)</div>
                    <div style="font-size:0.8rem; color:#94A3B8;">Spirometry Pre/Post Bronchodilator</div>
                </div>

                <div style="background:#0F172A; border:1px solid #334155; padding:16px; border-radius:8px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                        <span style="font-weight:800; color:#A7F3D0;">FEV1</span>
                        <span style="font-weight:900; color:white;">65% Pred (Pre) → 82% Pred (Post)</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                        <span style="font-weight:800; color:#A7F3D0;">FVC</span>
                        <span style="font-weight:900; color:white;">85% Pred (Pre) → 90% Pred (Post)</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:800; color:#A7F3D0;">FEV1/FVC</span>
                        <span style="font-weight:900; color:#FCA5A5;">0.62 (Pre)</span>
                    </div>
                    
                    <div style="margin-top:16px; font-size:0.85rem; color:#94A3B8;">
                        <i class="fas fa-check-circle" style="color:#10B981;"></i> Technical Quality: Acceptable (Grade A). 
                        Clinician interpretation required to formally verify reversibility.
                    </div>
                </div>

                <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-sm btn-teal" style="flex:1;"><i class="fas fa-check"></i> Verify Result</button>
                    <button class="btn btn-sm" style="flex:1;"><i class="fas fa-capsules"></i> Prescribe Inhaler</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
