<?php
require_once 'helper.php';
// Simulated Identity, Authentication, Authorization, Consent & Privacy Command Center
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity & Security Command | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Security Navbar */
        .sec-nav { background: #020617; border-bottom: 2px solid #334155; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .sec-nav .navbar-brand { color: #F8FAFC; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .sec-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .sec-nav-links::-webkit-scrollbar { display: none; }
        .sec-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .sec-nav-links a:hover { color: white; background: #1E293B; }
        .sec-nav-links a.active { color: white; background: #334155; box-shadow: 0 0 10px rgba(51, 65, 85, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; width: 100%; align-items: stretch; }

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
        .bg-critical { background: rgba(220, 38, 38, 0.1); color: #F87171; border-color: rgba(220, 38, 38, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Security Access Flow */
        .access-flow { background: #0F172A; border: 1px dashed #475569; border-radius: 8px; padding: 16px; margin-bottom:16px; display:flex; flex-direction:column; gap:12px;}
        .af-rule { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#1E293B; border-radius:6px; border:1px solid #334155;}
        .af-rule-name { font-weight:800; font-size:0.85rem; color:#E2E8F0; }
        
        .consent-box { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-left: 4px solid #10B981; border-radius: 8px; padding: 16px; margin-bottom:16px;}
        .consent-box h4 { margin:0 0 8px 0; color:#34D399; font-size:0.95rem; font-weight:900; display:flex; align-items:center; gap:8px;}

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-danger { background: #DC2626; border-color: #B91C1C; color: white; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
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
            <i class="fas fa-shield-alt"></i> IDENTITY & ACCESS SECURITY COMMAND
        </div>
        <div class="sec-nav-links">
            <a href="#"  class="active">Live Authorization Engine</a>
            <a href="#" >RBAC/ABAC Policies</a>
            <a href="#" >Patient Consent Matrix</a>
            <a href="#" >Break-Glass Audit</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-shield" style="color:#34D399;"></i> System Security Officer
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-lock" style="color:#A1A1AA;"></i> Zero-Trust Authorization Matrix</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating pure separation: Identity ≠ Professional Profile. Role ≠ Unrestricted Access.</div>
        </div>
        <div>
            <button class="btn"><i class="fas fa-cog"></i> Configure MFA Policy</button>
        </div>
    </div>

    <!-- Active Security KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #34D399;">
            <div class="kpi-val">100%</div>
            <div class="kpi-lbl">Doctor MFA Compliance</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">14,240</div>
            <div class="kpi-lbl">Active Valid Sessions</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">02</div>
            <div class="kpi-lbl">Pending Access Reviews</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(220,38,38,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">01</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Break-Glass Emergency active</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Authorization Engine -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-project-diagram"></i> Live Decision Policy Engine</div>
            </div>
            <div class="panel-body">
                
                <h3 style="margin:0 0 16px 0; font-size:0.95rem; color:white;">Transaction: Dr. Priya requesting Lab Result (LAB-1025)</h3>
                
                <div class="access-flow">
                    
                    <div class="af-rule">
                        <div class="af-rule-name">1. Identity & MFA Verification</div>
                        <span class="badge bg-success">PASS</span>
                    </div>
                    
                    <div class="af-rule">
                        <div class="af-rule-name">2. Organization Check (Clinic A)</div>
                        <span class="badge bg-success">PASS</span>
                    </div>
                    
                    <div class="af-rule">
                        <div class="af-rule-name">3. Active Patient Relationship (ReBAC)</div>
                        <span class="badge bg-success">PASS</span>
                    </div>

                    <div class="af-rule" style="border-color:#F59E0B;">
                        <div class="af-rule-name">4. Resource Scope / Consent (ABAC)</div>
                        <span class="badge bg-success">PASS</span>
                    </div>
                    
                    <div style="text-align:center; margin-top:8px;">
                        <span class="badge bg-info" style="font-size:0.85rem; padding:6px 12px; border-radius:8px;">DECISION: AUTHORIZED ALLOW</span>
                    </div>
                </div>

                <div class="consent-box">
                    <h4><i class="fas fa-handshake"></i> Patient Consent Matrix</h4>
                    <p style="font-size:0.85rem; color:white; line-height:1.5; margin:0 0 12px 0;">
                        Patient PT-100245 has explicitly granted: <strong>CONSULTATION_SUMMARY</strong> and <strong>RELEVANT_DIAGNOSTICS</strong> to Dr. Priya.<br><br>
                        <em>Unrelated psychiatric history remains BLOCKED by policy.</em>
                    </p>
                </div>

            </div>
        </div>

        <!-- Suspicious Login & Device Center -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-laptop-medical"></i> Risk Engine & Audit Alerts</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Security Event / User</th>
                            <th>Risk Assessment</th>
                            <th>Action Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user-md"></i> Identity: <?php echo htmlspecialchars($docName); ?></div>
                                <div class="dt-secondary">Break-Glass Emergency Request: EA-1001</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">ENHANCED AUDIT</span>
                            </td>
                            <td>
                                <div style="font-size:0.8rem; color:white;">Access Granted (30m)</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-laptop"></i> Identity: Admin_Delivery</div>
                                <div class="dt-secondary">Unusual IP Geolocation (Out of Country)</div>
                            </td>
                            <td>
                                <span class="badge bg-critical">HIGH RISK</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger">Force Revoke Session</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user"></i> Identity: Patient RX-402</div>
                                <div class="dt-secondary">Failed OTP (x4 attempts)</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">MEDIUM RISK</span>
                            </td>
                            <td>
                                <div style="font-size:0.8rem; color:white;">Step-up MFA Triggered</div>
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
