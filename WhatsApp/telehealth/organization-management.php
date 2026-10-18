<?php
require_once 'helper.php';
// Simulated Healthcare Organization, Facility & Department Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Org Navbar */
        .org-nav { background: #0F172A; border-bottom: 2px solid #334155; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .org-nav .navbar-brand { color: #F8FAFC; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .org-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .org-nav-links::-webkit-scrollbar { display: none; }
        .org-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .org-nav-links a:hover { color: white; background: #334155; }
        .org-nav-links a.active { color: white; background: #475569; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; margin-bottom:24px;}
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .dt-primary { font-weight: 900; color: white; display: flex; align-items: center; gap: 8px; font-size:1rem;}
        .dt-secondary { font-size: 0.8rem; color: #94A3B8; margin-top: 6px; display:flex; gap:12px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }
        
        .h-tree { display:flex; gap:12px; font-size:0.85rem; color:#A3E635; margin-top:12px; padding:12px; background:rgba(0,0,0,0.2); border-radius:6px; border-left:2px solid #84CC16;}
        
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="org-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-sitemap"></i> ORGANIZATION COMMAND CENTER
        </div>
        <div class="org-nav-links">
            <a href="#"  class="active">Executive Dashboard</a>
            <a href="#" >Facilities</a>
            <a href="#" >Departments</a>
            <a href="#" >Workforce & Privileges</a>
            <a href="#" >Pharmacy & Telemedicine</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=33" style="width:28px; height:28px; border-radius:50%; border:2px solid #94A3B8;"> Global Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-building" style="color:#38BDF8;"></i> Enterprise Architecture Matrix</h1>
    <div style="font-size:0.85rem; color:#94A3B8; margin-top:-16px; margin-bottom:8px;">Maintaining multi-tenant absolute state isolation across the ecosystem.</div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #F8FAFC;">
            <i class="fas fa-network-wired kpi-icon"></i>
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">Organizations (Tenants)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <i class="fas fa-hospital kpi-icon"></i>
            <div class="kpi-val">28</div>
            <div class="kpi-lbl">Physical / Virtual Facilities</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <i class="fas fa-layer-group kpi-icon"></i>
            <div class="kpi-val">96</div>
            <div class="kpi-lbl">Active Departments</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <i class="fas fa-user-md kpi-icon"></i>
            <div class="kpi-val">482</div>
            <div class="kpi-lbl">Total Clinicians & Workforce</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="panel-title"><i class="fas fa-map-marker-alt"></i> Tenant Execution: Doctor Assist Healthcare Group</div>
            <button class="btn"><i class="fas fa-plus"></i> Register Facility</button>
        </div>
        <div class="panel-body" style="padding: 0; overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Facility Designation</th>
                        <th>Architecture Breakdown</th>
                        <th>Operations & SLAs</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="dt-primary"><i class="fas fa-hospital" style="color:#38BDF8;"></i> Mysuru Medical Center</div>
                            <div class="dt-secondary"><span>Tenant ID: ORG-101</span> <span>Code: MYS-01</span></div>
                        </td>
                        <td>
                            <div style="color:white; font-size:0.85rem;"><i class="fas fa-stethoscope" style="color:#94A3B8;"></i> 6 Departments | <i class="fas fa-user-nurse" style="color:#94A3B8;"></i> 48 Providers | <i class="fas fa-bed" style="color:#94A3B8;"></i> 12 Rooms</div>
                            <div class="h-tree">
                                <i class="fas fa-code-branch"></i> Core Depts: Gen Med, Pediatrics, Cardiology, Pharmacy
                            </div>
                        </td>
                        <td>
                            <div class="dt-secondary" style="flex-direction:column; gap:4px;">
                                <span><strong style="color:white;">324</strong> Appointments Today</span>
                                <span><strong style="color:white;">296</strong> Consultations</span>
                            </div>
                        </td>
                        <td><span class="badge bg-success">ACTIVE</span></td>
                        <td><button class="btn btn-sm"><i class="fas fa-cog"></i> Config</button></td>
                    </tr>

                    <tr>
                        <td>
                            <div class="dt-primary"><i class="fas fa-clinic-medical" style="color:#38BDF8;"></i> Bengaluru Specialty Clinic</div>
                            <div class="dt-secondary"><span>Tenant ID: ORG-101</span> <span>Code: BLR-02</span></div>
                        </td>
                        <td>
                            <div style="color:white; font-size:0.85rem;"><i class="fas fa-stethoscope" style="color:#94A3B8;"></i> 3 Departments | <i class="fas fa-user-nurse" style="color:#94A3B8;"></i> 62 Providers | <i class="fas fa-bed" style="color:#94A3B8;"></i> 8 Rooms</div>
                            <div class="h-tree">
                                <i class="fas fa-code-branch"></i> Core Depts: Gen Med, Dermatology, Pharmacy
                            </div>
                        </td>
                        <td>
                            <div class="dt-secondary" style="flex-direction:column; gap:4px;">
                                <span><strong style="color:white;">418</strong> Appointments Today</span>
                                <span><strong style="color:white;">381</strong> Consultations</span>
                            </div>
                        </td>
                        <td><span class="badge bg-success">ACTIVE</span></td>
                        <td><button class="btn btn-sm"><i class="fas fa-cog"></i> Config</button></td>
                    </tr>

                    <tr>
                        <td>
                            <div class="dt-primary"><i class="fas fa-satellite-dish" style="color:#A78BFA;"></i> Platform Telemedicine Hub</div>
                            <div class="dt-secondary"><span>Tenant ID: ORG-101</span> <span>Code: TELE-01</span></div>
                        </td>
                        <td>
                            <div style="color:white; font-size:0.85rem;"><i class="fas fa-stethoscope" style="color:#94A3B8;"></i> 8 Departments | <i class="fas fa-user-nurse" style="color:#94A3B8;"></i> 85 Providers | <i class="fas fa-server" style="color:#94A3B8;"></i> Virtual Cloud</div>
                            <div class="h-tree" style="color:#C4B5FD; border-color:#A78BFA;">
                                <i class="fas fa-code-branch"></i> Core Depts: Video Consultation, Audio Consult, E-Pharmacy
                            </div>
                        </td>
                        <td>
                            <div class="dt-secondary" style="flex-direction:column; gap:4px;">
                                <span><strong style="color:white;">562</strong> Appointments Today</span>
                                <span><strong style="color:white;">527</strong> Consultations</span>
                            </div>
                        </td>
                        <td><span class="badge bg-success">ACTIVE</span></td>
                        <td><button class="btn btn-sm"><i class="fas fa-cog"></i> Config</button></td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>

    <!-- Governance Note -->
    <div style="background:#1E293B; border:1px solid #3B82F6; border-left:4px solid #3B82F6; padding:16px; border-radius:6px; color:#E2E8F0; font-size:0.9rem; line-height:1.5;">
        <i class="fas fa-shield-alt" style="color:#3B82F6;"></i> <strong>Data Isolation Enforcement:</strong> 
        Dr. Priya belongs to the <em>Mysuru Medical Center</em> (Facility Level) but holds clinical privileges under the <em>General Physician</em> department. 
        Her schedule generates bookable slots locked strictly to <code>Facility ID: MYS-01</code>. The API strictly forbids reading patient data from <code>BLR-02</code> without an explicit cross-facility referral. Multi-tenant boundaries are impenetrable.
    </div>

</div>

</body>
</html>
