<?php
require_once 'helper.php';
// Simulated Diagnostic Services & Clinical Investigations Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Diag Navbar */
        .diag-nav { background: #1E1B4B; border-bottom: 2px solid #312E81; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .diag-nav .navbar-brand { color: #818CF8; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .diag-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .diag-nav-links::-webkit-scrollbar { display: none; }
        .diag-nav-links a { color: #A5B4FC; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .diag-nav-links a:hover { color: white; background: #3730A3; }
        .diag-nav-links a.active { color: white; background: #4F46E5; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .dt-primary { font-weight: 900; color: white; display: flex; align-items: center; gap: 8px; font-size:1rem; }
        .dt-secondary { font-size: 0.8rem; color: #94A3B8; margin-top: 6px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.1); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Workflow Steps */
        .workflow-timeline { border-left: 2px solid #334155; margin-left: 12px; padding-left: 20px; display:flex; flex-direction:column; gap:20px; margin-top:20px;}
        .wf-node { position: relative; }
        .wf-dot { position: absolute; left: -27px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #0F172A; background:#334155;}
        .wf-node.done .wf-dot { background: #10B981; }
        .wf-node.active .wf-dot { background: #3B82F6; box-shadow: 0 0 10px rgba(59,130,246,0.5); }
        .wf-title { font-weight: 800; font-size: 0.9rem; color: white; display: flex; align-items: center; gap: 8px;}
        .wf-meta { font-size: 0.8rem; color: #94A3B8; margin-top: 4px; }
        
        .critical-badge { display:inline-block; border-radius:8px; padding:12px; background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239, 68, 68, 0.3); border-left:4px solid #EF4444; width:100%; box-sizing:border-box;}
        
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-primary { background: #4F46E5; border-color: #4338CA; color: white; }
        .btn-primary:hover { background: #4338CA; }
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
            <i class="fas fa-microscope"></i> DIAGNOSTIC COMMAND CENTER
        </div>
        <div class="diag-nav-links">
            <a href="#"  class="active">Live Dashboard</a>
            <a href="#" >Orders & Specimens</a>
            <a href="#" >Imaging Worklist</a>
            <a href="#" >Critical Escalations</a>
            <a href="#" >Quality Control</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=12" style="width:28px; height:28px; border-radius:50%; border:2px solid #818CF8;"> Chief Pathologist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-flask" style="color:#818CF8;"></i> Clinical Investigations Pipeline</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating state decoupling: Clinical Order ≠ Specimen Identity ≠ Verified Result.</div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="btn btn-primary"><i class="fas fa-barcode"></i> Accession Specimen</button>
        </div>
    </div>

    <!-- Active Pipeline KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">186</div>
            <div class="kpi-lbl">Clinical Orders</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F87171;">
            <div class="kpi-val">42</div>
            <div class="kpi-lbl">Awaiting Collection / Imaging</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">91</div>
            <div class="kpi-lbl">Active Processing</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">53</div>
            <div class="kpi-lbl">Results Pending Review</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239, 68, 68, 0.05);">
            <div class="kpi-val" style="color:#EF4444;"><i class="fas fa-exclamation-triangle"></i> 04</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Critical Escalations</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Order Pipeline -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-vial"></i> Master Accession & Result Entry Queue</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Identities (Order + Specimen)</th>
                            <th>Diagnostic Test/Study</th>
                            <th>Domain State</th>
                            <th>Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary" style="color:#C4B5FD;">LAB-8924</div>
                                <div class="dt-secondary" style="margin-top:2px; font-family:monospace; font-weight:900;"><i class="fas fa-barcode"></i> SP-10294-A</div>
                                <div class="dt-secondary">Patient: Rahul Kumar | Src: Dr. Priya</div>
                            </td>
                            <td>
                                <div class="dt-primary"><i class="fas fa-tint" style="color:#EF4444;"></i> CBC (Whole Blood)</div>
                                <div class="dt-secondary">Priority: <span class="badge bg-warning">URGENT</span></div>
                            </td>
                            <td>
                                <span class="badge bg-info">PROCESSING</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:8px;">Accessioned at 10:14 AM</div>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#0F172A;">Input Result <i class="fas fa-arrow-right"></i></button>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="dt-primary" style="color:#6EE7B7;">IMG-9011</div>
                                <div class="dt-secondary" style="margin-top:2px; font-family:monospace; font-weight:900;"><i class="fas fa-film"></i> ST-5242-X</div>
                                <div class="dt-secondary">Patient: Suresh P | Src: Dr. Kumar</div>
                            </td>
                            <td>
                                <div class="dt-primary"><i class="fas fa-x-ray" style="color:#38BDF8;"></i> Chest X-Ray PA View</div>
                                <div class="dt-secondary">Priority: <span class="badge" style="background:#334155; color:white;">ROUTINE</span></div>
                            </td>
                            <td>
                                <span class="badge bg-warning">REPORT DRAFTING</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:8px;">Acquired at 09:30 AM</div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary">Review Study <i class="fas fa-arrow-right"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Truth Trace & Critical Escalation -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-project-diagram"></i> Clinical Truth Decoupling</div>
            </div>
            <div class="panel-body">
                
                <h3 style="font-size:0.9rem; font-weight:800; color:white; margin-top:0;">Example: Potassium Investigation</h3>
                <div style="font-size:0.8rem; color:#94A3B8; margin-bottom:16px;">Following an order strictly through its independent domain states.</div>
                
                <div class="workflow-timeline">
                    <div class="wf-node done">
                        <div class="wf-dot"></div>
                        <div class="wf-title"><i class="fas fa-stethoscope"></i> 1. Clinical Order (ACCEPTED)</div>
                        <div class="wf-meta">Authorized by Dr. Priya via Clinical Timeline.</div>
                    </div>
                    
                    <div class="wf-node done">
                        <div class="wf-dot"></div>
                        <div class="wf-title"><i class="fas fa-vial"></i> 2. Specimen (ACCEPTED)</div>
                        <div class="wf-meta">Blood collected, labelled, and barcoded as <code>SP-9921</code>.</div>
                    </div>
                    
                    <div class="wf-node done">
                        <div class="wf-dot"></div>
                        <div class="wf-title"><i class="fas fa-microscope"></i> 3. Raw Result (ENTERED)</div>
                        <div class="wf-meta">Analyzer successfully returned K+ = 1.9 mEq/L.</div>
                    </div>
                    
                    <div class="wf-node active">
                        <div class="wf-dot"></div>
                        <div class="wf-title"><i class="fas fa-exclamation-triangle" style="color:#EF4444;"></i> 4. Clinical Safety Gate (CRITICAL ESCALATION)</div>
                        <div class="wf-meta">
                            <div class="critical-badge" style="margin-top:8px;">
                                <div style="font-weight:900; color:#FCA5A5; font-size:0.75rem; text-transform:uppercase; margin-bottom:4px;">Rule Triggered: Potassium &lt; 2.5</div>
                                <div style="color:white; font-size:0.9rem;"><strong>1.9 mEq/L (Critical Low)</strong></div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                                    <span style="font-size:0.8rem; font-weight:700;">Awaiting Acknowledgement</span>
                                    <button class="btn btn-sm" style="background:#EF4444; border-color:#DC2626;"><i class="fas fa-phone-alt"></i> Call Doctor</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wf-node">
                        <div class="wf-dot"></div>
                        <div class="wf-title"><i class="fas fa-file-medical-alt"></i> 5. Verified Report (FINAL)</div>
                        <div class="wf-meta">Locked pending Critical Ack resolution. Cannot be pushed to patient timeline yet.</div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
