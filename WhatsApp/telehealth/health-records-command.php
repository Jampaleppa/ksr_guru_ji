<?php
require_once 'helper.php';
// Simulated Health Records, Medical Documents & Clinical Timeline Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized HR Navbar */
        .hr-nav { background: #1E3A8A; border-bottom: 2px solid #1D4ED8; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .hr-nav .navbar-brand { color: #BFDBFE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .hr-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .hr-nav-links::-webkit-scrollbar { display: none; }
        .hr-nav-links a { color: #93C5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .hr-nav-links a:hover { color: white; background: #2563EB; }
        .hr-nav-links a.active { color: white; background: #3B82F6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; width: 100%; align-items: stretch; }

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
        .bg-primary-soft { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Timeline Engine */
        .hr-timeline { position: relative; margin-left:16px; padding-left: 20px; border-left: 2px solid #334155; display:flex; flex-direction:column; gap:20px; }
        .hr-node { position: relative; }
        .hr-dot { position: absolute; left: -27px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #0F172A; background:#64748B;}
        
        .type-consult .hr-dot { background: #3B82F6; }
        .type-rx .hr-dot { background: #F59E0B; }
        .type-lab .hr-dot { background: #10B981; }
        
        .hr-date { font-size: 0.75rem; color:#94A3B8; font-weight:800; margin-bottom:4px;}
        .hr-content { background: rgba(255,255,255,0.02); border: 1px solid #334155; border-radius:8px; padding:12px; }
        .hr-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;}
        .hr-title { font-weight:800; font-size:0.95rem; color:white; display:flex; align-items:center; gap:8px;}
        .hr-desc { font-size:0.85rem; color:#A1A1AA; line-height:1.4;}
        
        .hr-links { margin-top:12px; border-top:1px dashed #334155; padding-top:12px; display:flex; gap:8px;}

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-primary { background: #3B82F6; border-color: #2563EB; color: white; }
        .btn-primary:hover { background: #2563EB; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="hr-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-file-medical-alt"></i> MASTER HEALTH RECORDS
        </div>
        <div class="hr-nav-links">
            <a href="#"  class="active">Longitudinal Timeline</a>
            <a href="#" >Diagnostic Pointers</a>
            <a href="#" >Shared Resources</a>
            <a href="#" >Access Audit Log</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=12" style="width:28px; height:28px; border-radius:50%; border:2px solid #3B82F6;"> Dr. Priya
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-history" style="color:#3B82F6;"></i> Patient Timeline Aggregator</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating architectural purity: This module reads pointers from the independent domains. It does not duplicate data.</div>
        </div>
    </div>

    <!-- Patient Context -->
    <div style="background:#1E293B; border:1px solid #334155; border-radius:12px; padding:20px; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:20px;">
            <img src="https://i.pravatar.cc/150?img=11" style="width:64px; height:64px; border-radius:50%;">
            <div>
                <h2 style="margin:0; font-weight:900; font-size:1.4rem; color:white;">Rahul Kumar <span class="badge bg-success" style="vertical-align:middle; margin-left:8px;">ACTIVE RECORD</span></h2>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">PT-100245 | Secure Storage Vault Node active.</div>
            </div>
        </div>
        <div style="display:flex; gap:20px;">
            <div style="text-align:center;">
                <div style="font-size:1.4rem; font-weight:900; color:#3B82F6;">18</div>
                <div style="font-size:0.75rem; text-transform:uppercase; font-weight:700; color:#94A3B8;">Consults</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.4rem; font-weight:900; color:#F59E0B;">09</div>
                <div style="font-size:0.75rem; text-transform:uppercase; font-weight:700; color:#94A3B8;">Scripts</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.4rem; font-weight:900; color:#10B981;">14</div>
                <div style="font-size:0.75rem; text-transform:uppercase; font-weight:700; color:#94A3B8;">Labs</div>
            </div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Timeline Aggregate View -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-stream"></i> Master Clinical Timeline</div>
                <button class="btn btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </div>
            <div class="panel-body">
                
                <h3 style="margin:0 0 20px 0; font-size:1rem; color:white; border-bottom:1px solid #334155; padding-bottom:12px;">September 2026</h3>

                <div class="hr-timeline">
                    
                    <!-- Follow-up Event (Care Management Domain) -->
                    <div class="hr-node type-lab">
                        <div class="hr-dot"></div>
                        <div class="hr-date">05 Sep 2026 • 11:30 AM</div>
                        <div class="hr-content">
                            <div class="hr-head">
                                <div class="hr-title"><i class="fas fa-vial" style="color:#10B981;"></i> CBC Result Verified</div>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">SRC: DIAGNOSTIC DOMAIN</span>
                            </div>
                            <div class="hr-desc">Finalized by Laboratory. Source Document ID: LAB-1001.</div>
                        </div>
                    </div>

                    <!-- Prescription Event (Pharmacy Domain) -->
                    <div class="hr-node type-rx">
                        <div class="hr-dot"></div>
                        <div class="hr-date">05 Sep 2026 • 10:25 AM</div>
                        <div class="hr-content">
                            <div class="hr-head">
                                <div class="hr-title"><i class="fas fa-pills" style="color:#F59E0B;"></i> Prescription Issued</div>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">SRC: PHARMACY DOMAIN</span>
                            </div>
                            <div class="hr-desc">Authorized 3 medications. RX-1021. Signed by Dr. Priya.</div>
                        </div>
                    </div>

                    <!-- Consultation Event (Clinical Encounter Domain) -->
                    <div class="hr-node type-consult">
                        <div class="hr-dot"></div>
                        <div class="hr-date">05 Sep 2026 • 10:00 AM</div>
                        <div class="hr-content">
                            <div class="hr-head">
                                <div class="hr-title"><i class="fas fa-video" style="color:#3B82F6;"></i> Tele-Consultation Completed</div>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">SRC: CLINICAL ENCOUNTER</span>
                            </div>
                            <div class="hr-desc">CON-10245. Primary Diagnosis: Active Observation.</div>
                            <div class="hr-links">
                                <button class="btn btn-sm" style="background:#0F172A;"><i class="fas fa-external-link-alt"></i> Open Original Consultation Record</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Documents & Pointers -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-folder-open"></i> External Document Storage</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document Metadata</th>
                            <th>Pointer Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-file-pdf" style="color:#EF4444;"></i> Cardiac Discharge Summary</div>
                                <div class="dt-secondary">12 Aug 2026 | Uploaded by Patient</div>
                            </td>
                            <td>
                                <span class="badge bg-purple">EXTERNAL RECORD</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-file-pdf" style="color:#EF4444;"></i> Allergy Blood Panel</div>
                                <div class="dt-secondary">01 Mar 2025 | City Lab XYZ</div>
                            </td>
                            <td>
                                <span class="badge bg-purple">EXTERNAL RECORD</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div style="padding:20px;">
                    <button class="btn" style="width:100%; border-style:dashed;"><i class="fas fa-cloud-upload-alt"></i> Ingest New External Record via AI Processing</button>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
