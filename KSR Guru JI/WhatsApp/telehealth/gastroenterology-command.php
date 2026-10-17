<?php
require_once 'helper.php';
// Simulated Gastroenterology, Hepatology & Endoscopy Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastroenterology & Endoscopy Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized GI Navbar */
        .gi-nav { background: #854D0E; border-bottom: 2px solid #A16207; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .gi-nav .navbar-brand { color: #FEF08A; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .gi-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .gi-nav-links::-webkit-scrollbar { display: none; }
        .gi-nav-links a { color: #FDE047; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .gi-nav-links a:hover { color: white; background: #CA8A04; }
        .gi-nav-links a.active { color: white; background: #EAB308; box-shadow: 0 0 10px rgba(234, 179, 8, 0.5); }

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
        .bg-lime { background: rgba(132, 204, 22, 0.1); color: #A3E635; border-color: rgba(132, 204, 22, 0.3); }
        .bg-fuchsia { background: rgba(217, 70, 239, 0.1); color: #E879F9; border-color: rgba(217, 70, 239, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-yellow { background: #CA8A04; border-color: #A16207; color: white; }
        .btn-yellow:hover { background: #A16207; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="gi-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-stomach"></i> GASTROENTEROLOGY & HEPATOLOGY COMMAND
        </div>
        <div class="gi-nav-links">
            <a href="#"  class="active">GI & Liver Consults</a>
            <a href="#" >Endoscopy / Colonoscopy</a>
            <a href="#" >Pathology Reviews</a>
            <a href="#" >Nutrition & Care</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-md" style="color:#FEF08A;"></i> Gastroenterologist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-procedures" style="color:#EAB308;"></i> Digestive Care Operations Center</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Coordinating Endoscopy procedures, Pathology tracking, Hepatology, and acute GI bleeding arrays.</div>
        </div>
        <div>
            <button class="btn btn-yellow"><i class="fas fa-notes-medical"></i> New GI Consult</button>
        </div>
    </div>

    <!-- Active KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #EAB308;">
            <div class="kpi-val">318</div>
            <div class="kpi-lbl">Active GI Cases</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #84CC16;">
            <div class="kpi-val">142</div>
            <div class="kpi-lbl">Hepatology (Liver) Tracking</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #D946EF;">
            <div class="kpi-val">16</div>
            <div class="kpi-lbl">Endoscopies Scheduled</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val pulse-red" style="color:#FCA5A5;">11</div>
            <div class="kpi-lbl" style="color:#FCA5A5;"><i class="fas fa-microscope"></i> Pending Pathology</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live GI Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ol"></i> GI & Hepatology Queue</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Location</th>
                            <th>Pathway / Issue</th>
                            <th>Procedure / Diagnostics</th>
                            <th>Action & Disposition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- GI Bleed Alert -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Sullivan, P. (Age 59)</div>
                                <div style="font-size:0.75rem; color:#FCA5A5; margin-top:4px;"><i class="fas fa-ambulance"></i> ED Resus Bed 2</div>
                            </td>
                            <td>
                                <span class="badge bg-critical"><i class="fas fa-tint"></i> ACUTE UPPER GI BLEED</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Hematemesis, Tachycardia</div>
                            </td>
                            <td>
                                <span class="badge bg-warning"><i class="fas fa-procedures"></i> URGENT ENDOSCOPY PENDING</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-yellow">Accept ED Consult</button>
                            </td>
                        </tr>
                        <!-- Liver / Hepatology -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Chang, W. (Age 48)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-clinic-medical"></i> OPD Hepatology</div>
                            </td>
                            <td>
                                <span class="badge bg-lime"><i class="fas fa-leaf"></i> LIVER CIRRHOSIS</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Decompensated, Ascites check</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#84CC16; color:#A3E635;"><i class="fas fa-images"></i> ABDOMINAL US PENDING</span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#94A3B8;">Awaiting Radiology</span>
                            </td>
                        </tr>
                        <!-- Colonoscopy Pathology Result -->
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Evans, M. (Age 62)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;"><i class="fas fa-hospital-user"></i> Endoscopy Follow-up</div>
                            </td>
                            <td>
                                <span class="badge bg-fuchsia">ROUTINE COLONOSCOPY</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Polypectomy done 4 days ago</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#D946EF; color:#E879F9;"><i class="fas fa-folder-open"></i> PATHOLOGY VERIFIED</span>
                            </td>
                            <td>
                                <button class="btn btn-sm"><i class="fas fa-file-signature"></i> Final GI Review</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Specimen & Pathology Handoff Hub -->
        <div class="panel">
            <div class="panel-head" style="background:rgba(217, 70, 239, 0.1);">
                <div class="panel-title" style="color:#E879F9;"><i class="fas fa-microscope"></i> Pathology Inter-domain Hub</div>
            </div>
            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="font-weight:900; color:white; font-size:1.1rem;">Evans, M. (Pathology Returned)</div>
                    <div style="font-size:0.8rem; color:#E879F9;">Source: Central Laboratory • Verified by Pathologist</div>
                </div>

                <div style="background:#0F172A; border:1px solid #334155; padding:16px; border-radius:8px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                        <span style="font-weight:800; color:#E879F9;">Specimen #1 (Ascending Colon)</span>
                        <span style="font-weight:900; color:white;">Tubular Adenoma</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                        <span style="font-weight:800; color:#E879F9;">Specimen #2 (Transverse Colon)</span>
                        <span style="font-weight:900; color:white;">Hyperplastic Polyp</span>
                    </div>
                    
                    <div style="margin-top:16px; font-size:0.85rem; color:#94A3B8; border-top:1px solid #334155; padding-top:12px;">
                        <i class="fas fa-check-circle" style="color:#10B981;"></i> Laboratory Diagnosis: Margins clear. No high-grade dysplasia.
                        <br><br>
                        <i class="fas fa-robot" style="color:#CA8A04;"></i> AI Guidance: Based on ASGE guidelines, surveillance colonoscopy is suggested in 5 years. Awaiting your formal recommendation to update Care Plan.
                    </div>
                </div>

                <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                    <button class="btn btn-sm btn-yellow" style="flex:1;"><i class="fas fa-calendar-check"></i> Append into Care Plan</button>
                    <button class="btn btn-sm" style="flex:1;"><i class="fas fa-share-square"></i> Release to Patient</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
