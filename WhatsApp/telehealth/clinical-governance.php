<?php
require_once 'helper.php';
// Simulated Clinical Governance, Medical Quality & Safety Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Governance & Quality | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Clinical Governance Navbar - Clinical Blue theme */
        .gov-nav { background: #0891B2; border-bottom: 2px solid #0E7490; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .gov-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .gov-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .gov-nav-links::-webkit-scrollbar { display: none; }
        .gov-nav-links a { color: #CFFAFE; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .gov-nav-links a:hover { color: white; background: #0E7490; }
        .gov-nav-links a.active { color: #0891B2; background: white; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: #164E63; display: flex; align-items: center; gap: 12px; }
        
        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-card.alert-card { border: 2px solid #FECACA; background: #FEF2F2; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;}
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: var(--clr-text-primary); }
        .kpi-val.alert { color: #DC2626; }
        .kpi-sub { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); margin-top: 4px; display:flex; align-items:center; gap:6px;}

        /* Panel Blocks */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #164E63; display: flex; align-items: center; gap: 8px; }

        /* Status Check */
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; border-radius: 6px; margin-bottom: 8px; }
        .health-row:last-child { margin-bottom: 0; }
        .health-label { font-size: 0.9rem; font-weight: 700; color: var(--clr-text-primary); display:flex; align-items:center; gap:12px;}
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        
        .bg-success { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
        .bg-warn { background: #FFF7ED; color: #D97706; border-color: #FFEDD5; }
        .bg-danger { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
        .bg-info { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        
        /* Grid */
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* Policy List */
        .policy-item { padding: 12px; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 8px; background: white; display: flex; justify-content: space-between; align-items: center; }
        .policy-item:last-child { margin-bottom: 0; }
        .pi-title { font-weight: 800; font-size: 0.85rem; color: var(--clr-text-primary); }
        .pi-desc { font-size: 0.8rem; color: var(--clr-text-secondary); margin-top: 4px; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #F1F5F9; }
        .btn-primary { background: #0891B2; color: white; border-color: #06b6d4; }
        .btn-primary:hover { background: #0E7490; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="gov-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-stethoscope"></i> CLINICAL GOVERNANCE
        </div>
        <div class="gov-nav-links">
            <a href="#"  class="active">Quality Oversight</a>
            <a href="#" >Audit Protocols</a>
            <a href="#" >Compliance Policies</a>
            <a href="#" >AI Output Review</a>
            <a href="#" >Escalation Rules</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #CFFAFE;"> Chief Medical Officer
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-balance-scale text-primary"></i> Medical Quality & Compliance Hub</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-print"></i> Generate Audit Report</button>
                <button class="btn btn-primary"><i class="fas fa-cogs"></i> Adjust Protocols</button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card" style="border-left: 4px solid #0891B2;">
                <div class="kpi-title">Avg Consultation Time <i class="fas fa-clock text-info"></i></div>
                <div class="kpi-val">17m 42s</div>
                <div class="kpi-sub">Within Regulatory Standard</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #10B981;">
                <div class="kpi-title">AI Rx Accuracy (OCR) <i class="fas fa-robot text-success"></i></div>
                <div class="kpi-val">97.8%</div>
                <div class="kpi-sub">Human-Verified Correlation</div>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #F59E0B;">
                <div class="kpi-title">Consent Completion <i class="fas fa-file-contract text-warning"></i></div>
                <div class="kpi-val">100%</div>
                <div class="kpi-sub">Mandatory Gate Enforced</div>
            </div>
            <div class="kpi-card alert-card">
                <div class="kpi-title" style="color:#B91C1C;">Emergency Escalation <i class="fas fa-ambulance text-danger"></i></div>
                <div class="kpi-val alert">3</div>
                <div class="kpi-sub" style="color:#B91C1C;">Active Red Flag Triage</div>
            </div>
        </div>

        <div class="data-grid">
            
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-clipboard-check"></i> Enforced Clinical Protocol Status</div>
                </div>
                <div class="panel-body">
                    <div class="health-row">
                        <div class="health-label"><i class="fas fa-child text-primary"></i> Pediatrics Routing Rule</div>
                        <span class="badge bg-success">ENFORCED</span>
                    </div>
                    <div class="health-row">
                        <div class="health-label"><i class="fas fa-file-prescription text-primary"></i> Rx 6-Month Expiry Rule</div>
                        <span class="badge bg-success">ENFORCED</span>
                    </div>
                    <div class="health-row">
                        <div class="health-label"><i class="fas fa-video text-primary"></i> Video Consent Gateway</div>
                        <span class="badge bg-success">ENFORCED</span>
                    </div>
                    <div class="health-row">
                        <div class="health-label"><i class="fas fa-capsules text-danger"></i> Unsafe Substitution Block (Pharmacist)</div>
                        <span class="badge bg-success">ENFORCED</span>
                    </div>
                </div>
            </div>

            <div class="panel" style="border-color:#FDE68A;">
                <div class="panel-head" style="background:#FFFBEB; border-bottom-color:#FDE68A;">
                    <div class="panel-title" style="color:#D97706;"><i class="fas fa-search-plus"></i> Clinical Oversight Queue</div>
                </div>
                <div class="panel-body" style="background:#FAFAFA;">
                    <div class="policy-item">
                        <div>
                            <div class="pi-title">Consultation Duration Exception (Under 3 mins)</div>
                            <div class="pi-desc">Appt: APPT-812 | Dr. A. Sharma | Flags potential rushed care.</div>
                        </div>
                        <button class="btn" style="padding:4px 8px; font-size:0.75rem;">Review</button>
                    </div>
                    <div class="policy-item">
                        <div>
                            <div class="pi-title">AI OCR Confidence Drop</div>
                            <div class="pi-desc">Rx: RX-993 | Confidence: 62% | Image Quality Poor</div>
                        </div>
                        <button class="btn" style="padding:4px 8px; font-size:0.75rem;">Audit AI</button>
                    </div>
                    <div class="policy-item">
                        <div>
                            <div class="pi-title">Symptom Keyword Escalation</div>
                            <div class="pi-desc">Patient reported: "Severe Chest Pain" | Triage Protocol V3</div>
                        </div>
                        <button class="btn btn-danger" style="padding:4px 8px; font-size:0.75rem; color:white;">Track Escalation</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-sitemap"></i> Clinical Abstraction Governance Architecture</div>
            </div>
            <div class="panel-body">
                <div style="font-size:0.85rem; font-weight:600; color:var(--clr-text-secondary); line-height:1.6; margin-bottom:16px;">
                    This layer sits strictly above the operational clinical workflow. It ensures that <strong>AI does not diagnose</strong>, <strong>prescriptions do not bypass pharmacist review</strong>, and <strong>routing respects patient age constraints</strong>.
                </div>
                <div style="background:#1E293B; border-radius:6px; padding:16px; font-family:monospace; color:#A7F3D0; font-size:0.8rem; line-height:1.5;">
                    [RULE: AI_ASSIST]<br>
                    - Patient Symptom -> AI Intake Classifier -> (NEVER DB INSERT) -> Doctor Draft Queue<br>
                    - Patient Rx Upload -> AI OCR Extraction -> Pharmacy Queue (Confidence Gates Applied)<br><br>
                    
                    [RULE: CLINICAL_AUTHORITY]<br>
                    - Doctor = Sole Diagnosis & Rx Builder Authority.<br>
                    - Pharmacist = Dispensation Pipeline Authority. (No silent generic substitutions unless approved).
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
