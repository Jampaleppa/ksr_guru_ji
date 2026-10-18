<?php
require_once 'helper.php';
// Simulated Clinical Laboratory Quality, Pathology, Radiology & Diagnostic Provider Network Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Provider Network & Pathology | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Diagnostic Navbar */
        .diag-nav { background: #064E3B; border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .diag-nav .navbar-brand { color: #A7F3D0; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .diag-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .diag-nav-links::-webkit-scrollbar { display: none; }
        .diag-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .diag-nav-links a:hover { color: white; background: #059669; }
        .diag-nav-links a.active { color: white; background: #10B981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }

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
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-critical { background: rgba(239, 68, 68, 0.1); color: #F87171; border-color: rgba(239, 68, 68, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-green { background: #10B981; border-color: #059669; color: white; }
        .btn-green:hover { background: #059669; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="diag-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-microscope"></i> DIAGNOSTIC PROVIDER NETWORK & PATHOLOGY
        </div>
        <div class="diag-nav-links">
            <a href="#"  class="active">Pathology & Review Queue</a>
            <a href="#" >QC & Equipment Status</a>
            <a href="#" >Specimen Logistics</a>
            <a href="#" >DICOM/PACS Bridge</a>
            <a href="#" >Partner Network</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-md" style="color:#6EE7B7;"></i> Lead Pathologist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-network-wired" style="color:#10B981;"></i> Multi-Facility Diagnostic Core</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Centralized governance for Pathology validation, critical escalations, and automated analyzer interfaces.</div>
        </div>
        <div>
            <button class="btn btn-green"><i class="fas fa-satellite-dish"></i> Sync Modality Worklist (MWL)</button>
        </div>
    </div>

    <!-- Active Diagnostics KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">1,204</div>
            <div class="kpi-lbl">Samples in Processing</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">84</div>
            <div class="kpi-lbl">Pending MD/Pathologist Verify</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">100%</div>
            <div class="kpi-lbl">Eq. Calibrations Passed</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">3</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Critical Alerts (Escalated)</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Pathologist Review & Critical Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-clipboard-check"></i> Pathology & MD Authorization Queue</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Accession / Order</th>
                            <th>AI Preliminary</th>
                            <th>Status / SLA</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">DX-88219 (MRI Brain)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Patient: Sharma, K.</div>
                            </td>
                            <td><span class="badge bg-critical"><i class="fas fa-brain"></i> ABNORMAL FINDING</span></td>
                            <td>
                                <span class="badge bg-warning">VERIFY REQ.</span>
                                <div style="font-size:0.75rem; color:#F87171; margin-top:4px; font-weight:800;">STAT: -5m SLA</div>
                            </td>
                            <td><button class="btn btn-sm btn-green">Launch PACS</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">LAB-4491 (Histopathology)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Patient: Gupta, R.</div>
                            </td>
                            <td><span class="badge bg-warning"><i class="fas fa-search-plus"></i> MARGIN REVIEW</span></td>
                            <td><span class="badge bg-blue">PATHOLOGIST REQ.</span></td>
                            <td><button class="btn btn-sm">Review Slide</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">LAB-8812 (CBC Panel)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Patient: Singh, A.</div>
                            </td>
                            <td><span class="badge bg-success"><i class="fas fa-check"></i> WNL (NORMAL)</span></td>
                            <td><span class="badge bg-success">AUTO-RELEASED</span></td>
                            <td><button class="btn btn-sm">View Record</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analyzer Machine QC & Equipment Logistics -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-cogs"></i> Analyzer Connectivity & QC</div>
                <button class="btn btn-sm"><i class="fas fa-list"></i> View All Assets</button>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device Identifier</th>
                            <th>Modality</th>
                            <th>QC Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Sysmex XN-1000</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Hematology | Core Lab A</div>
                            </td>
                            <td><span class="badge" style="background:#1E293B; border-color:#334155;">HL7 / LIS</span></td>
                            <td>
                                <span class="badge bg-success">QC PASSED (08:00)</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Roche Cobas 6000</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Biochemistry | Partner Lab B</div>
                            </td>
                            <td><span class="badge" style="background:#1E293B; border-color:#334155;">HL7 / LIS</span></td>
                            <td>
                                <span class="badge bg-success">QC PASSED (08:15)</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Siemens Magnetom</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">MRI 3T | Radiology Dept</div>
                            </td>
                            <td><span class="badge" style="background:#1E293B; border-color:#334155;">DICOM C-STORE</span></td>
                            <td>
                                <span class="badge bg-warning">CALIB. REQ @ 18:00</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

</body>
</html>
