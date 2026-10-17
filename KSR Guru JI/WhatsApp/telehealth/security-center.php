<?php
require_once 'helper.php';
// Simulated Security & Audit Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Security Navbar - Deep Charcoal / Red theme */
        .sec-nav { background: #18181B; border-bottom: 2px solid #27272A; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .sec-nav .navbar-brand { color: #F87171; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .sec-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .sec-nav-links::-webkit-scrollbar { display: none; }
        .sec-nav-links a { color: #A1A1AA; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .sec-nav-links a:hover { color: white; background: #27272A; }
        .sec-nav-links a.active { color: white; background: #3F3F46; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px; }
        
        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;}
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: var(--clr-text-primary); }
        .kpi-sub { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); margin-top: 4px; display:flex; align-items:center; gap:6px;}
        
        /* Panel Blocks */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }

        /* DataTable */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; background: #F1F5F9; font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-primary); }
        .data-table tbody tr:hover { background: #F8FAFC; }
        
        /* Status Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-success { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
        .bg-warn { background: #FFF7ED; color: #D97706; border-color: #FFEDD5; }
        .bg-danger { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
        .bg-info { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #F1F5F9; }
        .btn-primary { background: #18181B; color: white; border-color: #000; }
        .btn-primary:hover { background: #27272A; }
        .btn-danger { background: #DC2626; color: white; border-color: #B91C1C; }
        .btn-danger:hover { background: #B91C1C; }

        /* Immutable Log Style */
        .audit-log { font-family: monospace; font-size: 0.8rem; background: #1E293B; color: #A7F3D0; padding: 12px; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid #10B981; line-height: 1.5; }
        .audit-log.denied { color: #FECACA; border-left-color: #EF4444; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="sec-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-shield-alt"></i> SECURITY COMMAND
        </div>
        <div class="sec-nav-links">
            <a href="#"  class="active">Dashboard</a>
            <a href="#" >Identity & Access</a>
            <a href="#" >Devices & Sessions</a>
            <a href="#" >Audit Logs</a>
            <a href="#" >Threat Detection <span style="background:#EF4444; color:white; padding:2px 6px; border-radius:12px; font-size:0.75rem; margin-left:4px;">12</span></a>
            <a href="#" >Policies</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #EF4444;"> Chief Security Officer
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-lock text-danger"></i> Platform Zero-Trust Architecture</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-file-export"></i> Security Report</button>
                <button class="btn btn-primary"><i class="fas fa-search-plus"></i> Investigate Alias</button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card" style="border-top: 3px solid #10B981;">
                <div class="kpi-title">Active Sessions <i class="fas fa-users text-success"></i></div>
                <div class="kpi-val">1,842</div>
                <div class="kpi-sub">Across 2,614 Verified Devices</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                <div class="kpi-title">MFA Enforcement <i class="fas fa-fingerprint text-warning"></i></div>
                <div class="kpi-val">94.8%</div>
                <div class="kpi-sub">100% Admin Coverage</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #EF4444;">
                <div class="kpi-title">Active Threats <i class="fas fa-biohazard text-danger"></i></div>
                <div class="kpi-val" style="color:#DC2626;">12</div>
                <div class="kpi-sub">Suspicious Auth Patterns</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #3B82F6;">
                <div class="kpi-title">Audit Engine Health <i class="fas fa-server text-info"></i></div>
                <div class="kpi-val">99.9%</div>
                <div class="kpi-sub">Immutable Storage Intact</div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <div style="flex:2; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-bolt"></i> Live Security Events Matrix</div>
                        <div style="display:flex; gap:8px;">
                            <input type="text" placeholder="Search IP, User, Event..." style="padding:6px 12px; border:1px solid var(--clr-border); border-radius:6px; outline:none; font-family:inherit; font-size:0.85rem;">
                        </div>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Actor</th>
                                    <th>Event Hook</th>
                                    <th>Risk Vector</th>
                                    <th>Action State</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>13:42:01</td>
                                    <td><span style="font-weight:800; color:#4F46E5;">DOC-102</span></td>
                                    <td><i class="fas fa-laptop-medical"></i> Clinical Sandbox Access</td>
                                    <td><span class="badge bg-success">LOW</span></td>
                                    <td><span style="color:#16A34A; font-weight:800;">AUTHORIZED</span></td>
                                </tr>
                                <tr>
                                    <td>13:41:55</td>
                                    <td><span style="font-weight:800; color:#4F46E5;">ADM-001</span></td>
                                    <td><i class="fas fa-user-shield"></i> RBAC Policy Modification</td>
                                    <td><span class="badge bg-danger">HIGH</span></td>
                                    <td><span style="color:#2563EB; font-weight:800;">STEP-UP MFA VERIFIED</span></td>
                                </tr>
                                <tr style="background:#FEF2F2;">
                                    <td>13:38:12</td>
                                    <td><span style="font-weight:800; color:#4F46E5;">PAT-882</span></td>
                                    <td><i class="fas fa-key"></i> Bruteforce Trigger (7 Fails)</td>
                                    <td><span class="badge bg-danger">CRITICAL</span></td>
                                    <td><span style="color:#DC2626; font-weight:900;">ACCOUNT LOCKED</span></td>
                                </tr>
                                <tr>
                                    <td>13:35:44</td>
                                    <td><span style="font-weight:800; color:#4F46E5;">PH-018</span></td>
                                    <td><i class="fas fa-file-prescription"></i> Cross-Tenant Rx Request</td>
                                    <td><span class="badge bg-warn">MEDIUM</span></td>
                                    <td><span style="color:#D97706; font-weight:800;">DENIED (ABAC)</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-terminal"></i> Immutable Audit Trail Buffer (Live)</div>
                    </div>
                    <div class="panel-body" style="background:#0F172A; padding:16px;">
                        
                        <div class="audit-log">
                            [TIMESTAMP] 2026-09-05T13:42:01Z <br>
                            [ACTOR] DOC-102 (IP: 192.168.1.45) <br>
                            [ACTION] DOC_READ | [RESOURCE] Patient_Document_882 <br>
                            [CONTEXT] Active Consultation (Appt: APP-9921) <br>
                            [RESULT] ALLOWED | [HASH] 8f9a2b4c1...
                        </div>
                        
                        <div class="audit-log denied">
                            [TIMESTAMP] 2026-09-05T13:35:44Z <br>
                            [ACTOR] PH-018 (IP: 10.0.4.22) <br>
                            [ACTION] RX_READ | [RESOURCE] Prescription_B29 <br>
                            [CONTEXT] Direct URI Access Attempt (No Active Order Correlation) <br>
                            [RESULT] DENIED (IDOR Blocked) | [HASH] c4b71a9...
                        </div>

                    </div>
                </div>

            </div>

            <!-- Right Column: Security Controls -->
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel" style="border-color:#FECACA;">
                    <div class="panel-head" style="background:#FEF2F2; border-bottom-color:#FECACA;">
                        <div class="panel-title" style="color:#B91C1C;"><i class="fas fa-radiation"></i> Incident Command</div>
                    </div>
                    <div class="panel-body">
                        
                        <div style="border:1px solid #FECACA; background:white; padding:16px; border-radius:6px; margin-bottom:16px;">
                            <div style="font-size:0.8rem; font-weight:800; color:#B91C1C; margin-bottom:8px; text-transform:uppercase;">Threat Vector Detected</div>
                            <div style="font-size:0.9rem; font-weight:700;">Account: PAT-882</div>
                            
                            <div style="margin-top:12px; font-size:0.85rem; color:var(--clr-text-secondary); line-height:1.5;">
                                7 sequential OTP failures triggering automated protective lock. High likelihood of credential stuffing attack.
                            </div>

                            <div style="display:flex; gap:8px; margin-top:16px;">
                                <button class="btn btn-danger" style="flex:1; justify-content:center; padding:6px;">Quarantine IP</button>
                                <button class="btn" style="flex:1; justify-content:center; padding:6px;">Review Logs</button>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-cogs"></i> Zero-Trust Policy Engine</div>
                    </div>
                    <div class="panel-body">
                        <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase; margin-bottom:12px;">Active Authorization Rules</div>
                        
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
                            <span style="font-size:0.85rem; font-weight:700;">Financial Mutation Locks</span>
                            <span class="badge bg-success">ENFORCED</span>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
                            <span style="font-size:0.85rem; font-weight:700;">Clinical IDOR Boundaries</span>
                            <span class="badge bg-success">ENFORCED</span>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #E2E8F0; padding-bottom:8px;">
                            <span style="font-size:0.85rem; font-weight:700;">Admin MFA Step-Up Gate</span>
                            <span class="badge bg-success">ENFORCED</span>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <span style="font-size:0.85rem; font-weight:700;">API Rate Limiting</span>
                            <span class="badge bg-success">ENFORCED</span>
                        </div>
                    </div>
                </div>

            </div>
            
        </div>

    </div>

</div>

</body>
</html>
