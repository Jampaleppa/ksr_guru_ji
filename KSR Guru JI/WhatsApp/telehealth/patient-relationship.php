<?php
require_once 'helper.php';
// Simulated Patient Relationship, Family, Caregiver & Consent Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Relationship & Consent | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Consent Navbar */
        .rel-nav { background: #1E1B4B; border-bottom: 2px solid #312E81; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .rel-nav .navbar-brand { color: #E0E7FF; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .rel-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .rel-nav-links::-webkit-scrollbar { display: none; }
        .rel-nav-links a { color: #818CF8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .rel-nav-links a:hover { color: white; background: #3730A3; }
        .rel-nav-links a.active { color: white; background: #6366F1; }

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
        .side-item.active { background: rgba(99, 102, 241, 0.2); color: #818CF8; border-left: 3px solid #818CF8; border-radius: 0 6px 6px 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Connection Card */
        .connection-card { background: #0F172A; border: 1px solid #334155; border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .conn-identity { display: flex; align-items: center; gap: 16px; }
        .conn-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #6366F1; }
        .conn-info { display: flex; flex-direction: column; gap: 4px; }
        .conn-name { font-weight: 900; color: white; font-size: 1rem; }
        .conn-role { font-size: 0.8rem; font-weight: 700; color: #818CF8; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
        .conn-status { font-size: 0.75rem; color: #94A3B8; }
        
        .conn-scopes { display: flex; gap: 6px; }
        .scope-badge { font-size: 0.7rem; font-weight: 800; padding: 4px 8px; border-radius: 4px; display: flex; align-items: center; gap: 4px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warn { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.2); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-neutral { background: rgba(148, 163, 184, 0.2); color: #94A3B8; border-color: rgba(148, 163, 184, 0.3); }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #6366F1; border-color: #4F46E5; color: white; }
        .btn-primary:hover { background: #4F46E5; }
        .btn-danger { background: rgba(239, 68, 68, 0.1); border-color: #EF4444; color: #EF4444; }
        .btn-danger:hover { background: #EF4444; color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="rel-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-network-wired"></i> PATIENT RELATIONSHIP & CONSENT
        </div>
        <div class="rel-nav-links">
            <a href="#"  class="active">Family & Caregivers</a>
            <a href="#" >Delegated Access</a>
            <a href="#" >Consent Ledger</a>
            <a href="#" >Emergency Safety</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=32" style="width:28px; height:28px; border-radius:50%; border:2px solid #818CF8;"> Privacy Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="sidebar">
        <div class="panel">
            <div class="side-nav">
                <div class="side-title">Entities</div>
                <a href="#"  class="side-item active"><i class="fas fa-users" style="width:20px;"></i> Network Graph</a>
                <a href="#"  class="side-item"><i class="fas fa-child" style="width:20px;"></i> Dependents</a>
                <a href="#"  class="side-item"><i class="fas fa-hands-helping" style="width:20px;"></i> Caregivers</a>
                
                <div class="side-title">Authorization</div>
                <a href="#"  class="side-item"><i class="fas fa-key" style="width:20px;"></i> Access Scopes</a>
                <a href="#"  class="side-item"><i class="fas fa-file-signature" style="width:20px;"></i> Active Consents</a>
                <a href="#"  class="side-item"><i class="fas fa-clock" style="width:20px;"></i> Temporary Access</a>
                
                <div class="side-title">Governance</div>
                <a href="#"  class="side-item"><i class="fas fa-shield-alt" style="width:20px;"></i> Safety Preference</a>
                <a href="#"  class="side-item"><i class="fas fa-history" style="width:20px;"></i> Audit Trail</a>
            </div>
        </div>
    </div>

    <div class="main-col">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-user-shield" style="color:#6366F1;"></i> Authorization Scopes</h1>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Patient ID: PAT-000842 | Rajan Sharma</div>
            </div>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> New Delegation</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
                <i class="fas fa-users kpi-icon"></i>
                <div class="kpi-val">4</div>
                <div class="kpi-lbl">Family Members</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <i class="fas fa-key kpi-icon"></i>
                <div class="kpi-val">3</div>
                <div class="kpi-lbl">Active Delegations</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <i class="fas fa-exclamation-triangle kpi-icon"></i>
                <div class="kpi-val">1</div>
                <div class="kpi-lbl">Expiring Consent</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <i class="fas fa-ban kpi-icon"></i>
                <div class="kpi-val">1</div>
                <div class="kpi-lbl">Revoked Access</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-project-diagram"></i> Relationship & Access Fabric</div>
            </div>
            <div class="panel-body">
                <p style="font-size:0.85rem; color:#94A3B8; margin-top:0; margin-bottom:20px; line-height:1.5;">
                    This interface visually enforces the security principle: <strong>Relationship ≠ Clinical Data Access</strong>. The system separates the structural relationship from the applied Consent/Delegation scopes.
                </p>

                <!-- Dependent Model -->
                <div class="connection-card">
                    <div class="conn-identity">
                        <img src="https://i.pravatar.cc/150?img=5" class="conn-avatar" alt="Dependent">
                        <div class="conn-info">
                            <div class="conn-name">Priya Sharma</div>
                            <div class="conn-role"><i class="fas fa-child"></i> Dependent (Minor)</div>
                            <div class="conn-status">Patient is registered as Legal Guardian</div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94A3B8; margin-bottom:4px; font-weight:800; text-transform:uppercase;">Delegated Scopes:</div>
                        <div class="conn-scopes">
                            <span class="scope-badge bg-healthy"><i class="fas fa-check"></i> Full Clinical Access</span>
                            <span class="scope-badge bg-healthy"><i class="fas fa-check"></i> Appt Mgmt</span>
                            <span class="scope-badge bg-healthy"><i class="fas fa-check"></i> Pharmacy</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-sm">Edit</button>
                    </div>
                </div>

                <!-- Family Member (No Access) -->
                <div class="connection-card">
                    <div class="conn-identity">
                        <img src="https://i.pravatar.cc/150?img=11" class="conn-avatar" style="border-color:#94A3B8;" alt="Parent">
                        <div class="conn-info">
                            <div class="conn-name">Ramesh Sharma</div>
                            <div class="conn-role" style="color:#94A3B8;"><i class="fas fa-users"></i> Family (Father)</div>
                            <div class="conn-status">Relationship Active</div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94A3B8; margin-bottom:4px; font-weight:800; text-transform:uppercase;">Delegated Scopes:</div>
                        <div class="conn-scopes">
                            <span class="scope-badge bg-neutral"><i class="fas fa-ban"></i> Zero Clinical Access</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-sm btn-primary">Grant Access</button>
                    </div>
                </div>

                <!-- Caregiver (Partial Access) -->
                <div class="connection-card">
                    <div class="conn-identity">
                        <img src="https://i.pravatar.cc/150?img=47" class="conn-avatar" style="border-color:#F59E0B;" alt="Caregiver">
                        <div class="conn-info">
                            <div class="conn-name">Sunita (Nurse Aid)</div>
                            <div class="conn-role" style="color:#F59E0B;"><i class="fas fa-hands-helping"></i> Caregiver</div>
                            <div class="conn-status"><span style="color:#FBBF24;"><i class="fas fa-clock"></i> Access Expires: 30-Oct-2026</span></div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94A3B8; margin-bottom:4px; font-weight:800; text-transform:uppercase;">Delegated Scopes:</div>
                        <div class="conn-scopes">
                            <span class="scope-badge bg-healthy"><i class="fas fa-check"></i> Pharmacy Ordering</span>
                            <span class="scope-badge bg-healthy"><i class="fas fa-check"></i> Appt Mgmt</span>
                            <span class="scope-badge bg-danger"><i class="fas fa-times"></i> Clinical Notes</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-sm btn-danger">Revoke</button>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="connection-card" style="border-color: rgba(239, 68, 68, 0.3);">
                    <div class="conn-identity">
                        <div style="width:48px; height:48px; border-radius:50%; background:rgba(239, 68, 68, 0.1); color:#EF4444; display:flex; justify-content:center; align-items:center; font-size:1.5rem;"><i class="fas fa-ambulance"></i></div>
                        <div class="conn-info">
                            <div class="conn-name">Anita Sharma</div>
                            <div class="conn-role" style="color:#EF4444;"><i class="fas fa-star-of-life"></i> Emergency Contact (Spouse)</div>
                            <div class="conn-status">Priority 1 Contact</div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94A3B8; margin-bottom:4px; font-weight:800; text-transform:uppercase;">Delegated Scopes:</div>
                        <div class="conn-scopes">
                            <span class="scope-badge bg-warn"><i class="fas fa-bell"></i> Emergency Comms Only</span>
                            <span class="scope-badge bg-danger"><i class="fas fa-times"></i> No Clinical Data</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-sm">Edit</button>
                    </div>
                </div>

            </div>
        </div>
        
    </div>
</div>

</body>
</html>
