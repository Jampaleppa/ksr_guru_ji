<?php
require_once 'helper.php';
// Simulated Workforce HR, Credentialing, Training & Competency Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workforce Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized HR Navbar */
        .wf-nav { background: #312E81; border-bottom: 2px solid #3730A3; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .wf-nav .navbar-brand { color: #A78BFA; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .wf-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .wf-nav-links::-webkit-scrollbar { display: none; }
        .wf-nav-links a { color: #C4B5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .wf-nav-links a:hover { color: white; background: #4338CA; }
        .wf-nav-links a.active { color: white; background: #4F46E5; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        .sidebar { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        /* Sidebar Navigation */
        .side-nav { display: flex; flex-direction: column; gap: 4px; }
        .side-title { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px 4px 16px; }
        .side-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: #CBD5E1; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .side-item:hover { background: #334155; color: white; }
        .side-item.active { background: rgba(99, 102, 241, 0.2); color: #6366F1; border-left: 3px solid #6366F1; border-radius: 0 6px 6px 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; display: flex; align-items: baseline; gap: 8px; }
        .kpi-val span { font-size: 0.9rem; font-weight: 700; color: #94A3B8; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Profile Banner */
        .profile-banner { display: flex; align-items: flex-start; gap: 20px; padding: 20px; background: #0F172A; border-radius: var(--radius-md); border: 1px solid #334155; }
        .pb-avatar { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; border: 2px solid #4F46E5; }
        .pb-info { flex: 1; }
        .pb-name { font-size: 1.4rem; font-weight: 900; color: white; margin-bottom: 4px; }
        .pb-role { font-size: 0.9rem; color: #94A3B8; margin-bottom: 12px; font-weight: 600; }
        .pb-meta { display: flex; gap: 24px; font-size: 0.85rem; color: #E2E8F0; }
        .pb-meta-item { display: flex; flex-direction: column; gap: 4px; }
        .pb-meta-lbl { color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; }

        /* State Matrix */
        .state-matrix { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 16px; }
        .matrix-cell { background: #1E293B; border: 1px solid #334155; border-radius: 6px; padding: 12px; display: flex; flex-direction: column; gap: 8px; text-align: center; }
        .mc-title { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; }
        .mc-status { font-size: 0.9rem; font-weight: 700; color: white; display: flex; justify-content: center; align-items: center; gap: 8px; }
        
        /* Data Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #334155; }
        .data-table th { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; background: #0F172A; }
        .data-table td { font-size: 0.9rem; color: #E2E8F0; font-weight: 500; }
        .data-table tr:hover td { background: #1E293B; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warn { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.2); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #6366F1; border-color: #4F46E5; color: white; }
        .btn-primary:hover { background: #4F46E5; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="wf-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-users-cog"></i> WORKFORCE MANAGEMENT
        </div>
        <div class="wf-nav-links">
            <a href="#"  class="active">Lifecycle Hub</a>
            <a href="#" >Credentialing Engine</a>
            <a href="#" >Clinical Privileges</a>
            <a href="#" >Scheduling & Leave</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=12" style="width:28px; height:28px; border-radius:50%; border:2px solid #8B5CF6;"> HR Director
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="sidebar">
        <div class="panel">
            <div class="side-nav">
                <div class="side-title">People & HR</div>
                <a href="#"  class="side-item active"><i class="fas fa-id-card-alt" style="width:20px;"></i> Profiles Matrix</a>
                <a href="#"  class="side-item"><i class="fas fa-user-plus" style="width:20px;"></i> Onboarding</a>
                <a href="#"  class="side-item"><i class="fas fa-history" style="width:20px;"></i> Transfers & Leaves</a>
                
                <div class="side-title">Credentialing</div>
                <a href="#"  class="side-item"><i class="fas fa-file-medical" style="width:20px;"></i> Licenses & Docs</a>
                <a href="#"  class="side-item"><i class="fas fa-clock" style="width:20px;"></i> Expiry Monitor</a>
                <a href="#"  class="side-item"><i class="fas fa-check-double" style="width:20px;"></i> Verification Queue</a>
                
                <div class="side-title">Clinical Compliance</div>
                <a href="#"  class="side-item"><i class="fas fa-user-shield" style="width:20px;"></i> Privileges</a>
                <a href="#"  class="side-item"><i class="fas fa-graduation-cap" style="width:20px;"></i> Training Center</a>
                <a href="#"  class="side-item"><i class="fas fa-star" style="width:20px;"></i> Competency Matrix</a>
            </div>
        </div>
    </div>

    <div class="main-col">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-clipboard-list" style="color:#6366F1;"></i> Workforce Integrity Command</h1>
            <button class="btn btn-primary"><i class="fas fa-bolt"></i> Run Expiry Sweep</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <i class="fas fa-users kpi-icon"></i>
                <div class="kpi-val">8,942 <span>Staff</span></div>
                <div class="kpi-lbl">Total Workforce</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #6366F1;">
                <i class="fas fa-certificate kpi-icon"></i>
                <div class="kpi-val">97.8%</div>
                <div class="kpi-lbl">Credentials Valid</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <i class="fas fa-exclamation-triangle kpi-icon"></i>
                <div class="kpi-val">84</div>
                <div class="kpi-lbl">Expiring < 30 Days</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <i class="fas fa-ban kpi-icon"></i>
                <div class="kpi-val">19</div>
                <div class="kpi-lbl">Privileges Suspended</div>
            </div>
        </div>

        <!-- Independent Identity Model Example -->
        <div class="profile-banner">
            <img src="https://i.pravatar.cc/150?img=33" class="pb-avatar" alt="Dr Profile">
            <div class="pb-info">
                <div class="pb-name">Dr. Aravind Sharma</div>
                <div class="pb-role">Senior General Physician | WF-000482</div>
                <div class="pb-meta">
                    <div class="pb-meta-item">
                        <span class="pb-meta-lbl">Organization</span>
                        <span>Multi-Care Hospital Group</span>
                    </div>
                    <div class="pb-meta-item">
                        <span class="pb-meta-lbl">Department</span>
                        <span>Mysuru Central - Cardiology</span>
                    </div>
                    <div class="pb-meta-item">
                        <span class="pb-meta-lbl">Employment</span>
                        <span>Full-Time Contract</span>
                    </div>
                </div>

                <!-- Independent States Demonstrated -->
                <div class="state-matrix">
                    <div class="matrix-cell">
                        <div class="mc-title">HR Employment</div>
                        <div class="mc-status"><i class="fas fa-check-circle" style="color:#10B981;"></i> Active</div>
                    </div>
                    <div class="matrix-cell" style="border-color:#F59E0B;">
                        <div class="mc-title">Prof. Credential</div>
                        <div class="mc-status" style="color:#FBBF24;"><i class="fas fa-hourglass-half"></i> Expiring (12d)</div>
                    </div>
                    <div class="matrix-cell">
                        <div class="mc-title">Clinical Privileges</div>
                        <div class="mc-status"><i class="fas fa-shield-alt" style="color:#10B981;"></i> Standard</div>
                    </div>
                    <div class="matrix-cell">
                        <div class="mc-title">Scheduling</div>
                        <div class="mc-status"><i class="fas fa-calendar-check" style="color:#10B981;"></i> Available</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-shield-check"></i> Actionable Alerts & Privilege Engine</div>
            </div>
            <div class="panel-body" style="padding:0; overflow-x:auto;">
                <p style="font-size:0.85rem; color:#94A3B8; margin: 16px 20px; line-height:1.5;">
                    The platform calculates clinical privileges automatically based on <strong>underlying credentials</strong>. If a credential expires, HR Employment remains Active, but Clinical Privileges drop to Suspended automatically to protect patient safety.
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Workforce Member</th>
                            <th>Trigger Event</th>
                            <th>Credential State</th>
                            <th>Privilege State (Effect)</th>
                            <th>Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Dr. Rakesh Singh</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">WF-008211 | Pediatrician</div>
                            </td>
                            <td><span style="color:#FBBF24; font-weight:700;"><i class="fas fa-clock"></i> Cert. Expiring</span></td>
                            <td><span class="badge bg-warn">Due: Oct 12, 2026</span></td>
                            <td><span class="badge bg-healthy">Active (Warning)</span></td>
                            <td><button class="btn btn-sm">Request Upload</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Dr. Maya Patel</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">WF-003441 | General Physician</div>
                            </td>
                            <td><span style="color:#EF4444; font-weight:700;"><i class="fas fa-times-circle"></i> License Expired</span></td>
                            <td><span class="badge bg-danger">Expired: Yesterday</span></td>
                            <td><span class="badge bg-danger"><i class="fas fa-ban"></i> Suspended</span></td>
                            <td><button class="btn btn-sm">Review Override</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Sunil Kumar (Pharm)</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">WF-009941 | Pharmacist</div>
                            </td>
                            <td><span style="color:#EF4444; font-weight:700;"><i class="fas fa-graduation-cap"></i> Training Failed</span></td>
                            <td><span class="badge bg-danger">Fails Saftey Protocol</span></td>
                            <td><span class="badge bg-warn"><i class="fas fa-eye"></i> Pending Review</span></td>
                            <td><button class="btn btn-sm">Assign Training</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Dr. James T.</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">WF-001224 | Neurologist</div>
                            </td>
                            <td><span style="color:#10B981; font-weight:700;"><i class="fas fa-plane-departure"></i> Approved Leave</span></td>
                            <td><span class="badge bg-healthy">Active</span></td>
                            <td><span class="badge bg-healthy">Active</span></td>
                            <td><button class="btn btn-sm">Block Schedule</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>
