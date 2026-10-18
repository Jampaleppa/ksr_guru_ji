<?php
require_once 'helper.php';
// Simulated Medication, Formulary, & Drug Interaction Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Medication & Formulary Command | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Formulary Navbar */
        .med-nav { background: #1E3A8A; border-bottom: 2px solid #1E40AF; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .med-nav .navbar-brand { color: #DBEAFE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .med-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .med-nav-links::-webkit-scrollbar { display: none; }
        .med-nav-links a { color: #93C5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .med-nav-links a:hover { color: white; background: #2563EB; }
        .med-nav-links a.active { color: white; background: #3B82F6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }

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

        /* Formulary Tree */
        .formulary-tree { display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
        .f-node { display:flex; justify-content:space-between; align-items:center; background:#0F172A; border:1px solid #334155; padding:12px; border-radius:8px;}
        .f-primary { color:white; font-weight:900; font-size:0.95rem; }
        .f-secondary { color:#94A3B8; font-size:0.8rem; margin-top:2px; display:flex; align-items:center; gap:6px;}

        /* Interaction Engine */
        .interaction-alert { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-left: 4px solid #EF4444; border-radius: 8px; padding: 16px; margin-bottom:16px;}
        .interaction-alert h4 { margin:0 0 8px 0; color:#F87171; font-size:0.95rem; font-weight:900; display:flex; align-items:center; gap:8px;}
        .severity-pill { padding:2px 6px; font-size:0.7rem; font-weight:900; border-radius:4px; text-transform:uppercase;}

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
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="med-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-pills"></i> CLINICAL FORMULARY & MEDICATION MASTER
        </div>
        <div class="med-nav-links">
            <a href="#"  class="active">Drug Interaction Engine</a>
            <a href="#" >Master Formulary</a>
            <a href="#" >Allergy Matching</a>
            <a href="#" >Substitution Rules</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-microscope" style="color:#60A5FA;"></i> Clinical Pharmacologist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-prescription-bottle-alt" style="color:#60A5FA;"></i> Medication Safety Integration</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Ensuring prescriber intent matches pharmaceutical reality through centralized Drug/Allergy cross-checks.</div>
        </div>
        <div>
            <button class="btn btn-blue"><i class="fas fa-sync"></i> Sync Master Catalog (ICD-10/RxNorm)</button>
        </div>
    </div>

    <!-- Active Formulary KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">12,450</div>
            <div class="kpi-lbl">Active Formularies (SKUs)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">100%</div>
            <div class="kpi-lbl">AI Interaction Screened</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">18%</div>
            <div class="kpi-lbl">Generic Substitutions</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">2</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Severity 1 Alerts Trapped</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Clinical Interaction & Safety Gate -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-shield-virus"></i> Clinical Interaction & Allergy Matrix</div>
            </div>
            <div class="panel-body">
                
                <h3 style="margin:0 0 16px 0; font-size:0.95rem; color:white;">Patient PT-10025: Proposed Prescription Scan</h3>
                
                <div class="interaction-alert">
                    <h4>
                        <i class="fas fa-exclamation-triangle"></i> DRUG-ALLERGY ALERT TRAPPED
                        <span class="severity-pill" style="background:#EF4444; color:white; margin-left:auto;">SEVERITY 1: CONTRAINDICATED</span>
                    </h4>
                    <p style="font-size:0.85rem; color:white; line-height:1.5; margin:0 0 12px 0;">
                        Doctor prescribed: <strong>Amoxicillin 500mg</strong>.<br>
                        Patient Health Record indicates systemic allergy to: <strong>Penicillin (Class)</strong>.
                    </p>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-sm btn-blue"><i class="fas fa-ban"></i> Block Fulfillment</button>
                        <button class="btn btn-sm"><i class="fas fa-comment-medical"></i> Alert Prescriber</button>
                    </div>
                </div>

                <div style="background:#0F172A; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="font-weight:800; color:white; font-size:0.9rem;"><i class="fas fa-exchange-alt" style="color:#F59E0B;"></i> Drug-Drug Interaction Warning</div>
                        <span class="severity-pill" style="background:#F59E0B; color:#000;">SEVERITY 3: MONITOR</span>
                    </div>
                    <div style="font-size:0.8rem; color:#94A3B8;">
                        Proposed <strong>Clopidogrel</strong> interacts with existing active timeline medication <strong>Omeprazole</strong>. Omeprazole may reduce antiplatelet efficacy.
                    </div>
                </div>
                
                <div style="text-align:center; padding:12px; font-size:0.8rem; color:#10B981; font-weight:800; border:1px dashed #10B981; border-radius:6px; background:rgba(16,185,129,0.05);">
                    <i class="fas fa-check-circle"></i> Remaining 3 medications cleared clinical compliance.
                </div>

            </div>
        </div>

        <!-- Master Formulary & Generic Substitution -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-dna"></i> Master Formulary Taxonomy</div>
                <button class="btn btn-sm"><i class="fas fa-search"></i> Search Base</button>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Active Base (Molecule)</th>
                            <th>Brand / SKU Forms</th>
                            <th>Sub Rules</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Paracetamol 500mg</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Anti-pyretic, Analgesic</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Dolo 650 (Tab)</span>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Calpol 500 (Tab)</span>
                            </td>
                            <td>
                                <span class="badge bg-success">AUTO-SUB YES</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Levothyroxine 50mcg</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Thyroid Hormone</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Thyronorm 50</span>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Eltroxin 50</span>
                            </td>
                            <td>
                                <span class="badge bg-critical">NTI: DO NOT SUB</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Amoxicillin + Clavulanate (625mg)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Antibiotic (Penicillin class)</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Augmentin 625</span>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Moxikind-CV</span>
                            </td>
                            <td>
                                <span class="badge bg-warning">REQUIRE CONSENT</span>
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
