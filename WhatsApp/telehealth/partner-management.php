<?php
require_once 'helper.php';
// Simulated Partner / Provider Onboarding & Marketplace Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Partner Navbar */
        .partner-nav { background: #451A03; border-bottom: 2px solid #78350F; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .partner-nav .navbar-brand { color: #FCD34D; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .partner-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .partner-nav-links::-webkit-scrollbar { display: none; }
        .partner-nav-links a { color: #FDE68A; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .partner-nav-links a:hover { color: white; background: #92400E; }
        .partner-nav-links a.active { color: white; background: #B45309; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        .sidebar { width: 280px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
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
        .side-item.active { background: rgba(245, 158, 11, 0.2); color: #F59E0B; border-left: 3px solid #F59E0B; border-radius: 0 6px 6px 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }

        /* Data Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #334155; }
        .data-table th { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; background: #0F172A; }
        .data-table td { font-size: 0.9rem; color: #E2E8F0; font-weight: 500; }
        .data-table tr:hover td { background: #1E293B; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-pending { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-suspended { background: rgba(239, 68, 68, 0.2); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-info { background: rgba(59, 130, 246, 0.2); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }

        /* Partner State Diagram */
        .state-flow { display: flex; align-items: center; gap: 12px; margin-top: 16px; padding: 12px; background: #0F172A; border-radius: 6px; border: 1px solid #334155; overflow-x: auto; }
        .state-node { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 100px; }
        .state-icon { width: 32px; height: 32px; border-radius: 50%; background: #334155; display: flex; justify-content: center; align-items: center; color: #94A3B8; font-size: 0.8rem; border: 2px solid transparent; }
        .node-active .state-icon { background: rgba(16, 185, 129, 0.2); color: #10B981; border-color: #10B981; }
        .node-pending .state-icon { background: rgba(245, 158, 11, 0.2); color: #F59E0B; border-color: #F59E0B; }
        .state-lbl { font-size: 0.7rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; text-align: center; }
        .state-arrow { color: #475569; font-size: 0.8rem; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #F59E0B; border-color: #D97706; color: #1E293B; }
        .btn-primary:hover { background: #FCD34D; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="partner-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-handshake"></i> PARTNER MANAGEMENT
        </div>
        <div class="partner-nav-links">
            <a href="#"  class="active">Ecosystem</a>
            <a href="#" >Onboarding</a>
            <a href="#" >Contracts & SLAs</a>
            <a href="#" >Marketplace Integrations</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=60" style="width:28px; height:28px; border-radius:50%; border:2px solid #FCD34D;"> Platform Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="sidebar">
        <div class="panel">
            <div class="side-nav">
                <div class="side-title">Partners</div>
                <a href="#"  class="side-item active"><i class="fas fa-th-large" style="width:20px;"></i> Executive Dashboard</a>
                <a href="#"  class="side-item"><i class="fas fa-building" style="width:20px;"></i> All Partners</a>
                <a href="#"  class="side-item"><i class="fas fa-clipboard-list" style="width:20px;"></i> Pending Applications</a>
                
                <div class="side-title">Provider Types</div>
                <a href="#"  class="side-item"><i class="fas fa-user-md" style="width:20px;"></i> Doctors</a>
                <a href="#"  class="side-item"><i class="fas fa-prescription-bottle-alt" style="width:20px;"></i> Pharmacies</a>
                <a href="#"  class="side-item"><i class="fas fa-hospital" style="width:20px;"></i> Hospitals</a>
                <a href="#"  class="side-item"><i class="fas fa-microscope" style="width:20px;"></i> Diagnostics</a>
                <a href="#"  class="side-item"><i class="fas fa-truck" style="width:20px;"></i> Delivery Network</a>
                
                <div class="side-title">Compliance</div>
                <a href="#"  class="side-item"><i class="fas fa-id-card" style="width:20px;"></i> KYC / KYB</a>
                <a href="#"  class="side-item"><i class="fas fa-file-contract" style="width:20px;"></i> Contracts</a>
                <a href="#"  class="side-item"><i class="fas fa-balance-scale" style="width:20px;"></i> SLA & Settlement</a>
            </div>
        </div>
    </div>

    <div class="main-col">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-network-wired" style="color:#F59E0B;"></i> Partner Network Overview</h1>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> New Application</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-val">1,284</div>
                <div class="kpi-lbl">Total Partners</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <div class="kpi-val">1,106</div>
                <div class="kpi-lbl">Active Network</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <div class="kpi-val">94</div>
                <div class="kpi-lbl">Pending KYC/Docs</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <div class="kpi-val">31</div>
                <div class="kpi-lbl">Suspended/Offboarded</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-cogs"></i> Strictly Separated Aggregate States</div>
            </div>
            <div class="panel-body">
                <p style="font-size:0.85rem; color:#94A3B8; margin-top:0; margin-bottom:12px; line-height:1.5;">
                    This architecture ensures that <strong>Onboarding ≠ Verification ≠ Activation ≠ Connectivity ≠ Settlement</strong>. A provider can be technically integrated (API healthy) but financially suspended, or their contract can expire without destroying their entity record.
                </p>
                <div class="state-flow">
                    <div class="state-node node-active">
                        <div class="state-icon"><i class="fas fa-check"></i></div>
                        <div class="state-lbl">App Approval</div>
                    </div>
                    <div class="state-arrow"><i class="fas fa-chevron-right"></i></div>
                    <div class="state-node node-active">
                        <div class="state-icon"><i class="fas fa-check"></i></div>
                        <div class="state-lbl">KYC & Docs</div>
                    </div>
                    <div class="state-arrow"><i class="fas fa-chevron-right"></i></div>
                    <div class="state-node node-active">
                        <div class="state-icon"><i class="fas fa-file-signature"></i></div>
                        <div class="state-lbl">Contract V1 Active</div>
                    </div>
                    <div class="state-arrow"><i class="fas fa-chevron-right"></i></div>
                    <div class="state-node node-active">
                        <div class="state-icon"><i class="fas fa-link"></i></div>
                        <div class="state-lbl">Integration Check</div>
                    </div>
                    <div class="state-arrow"><i class="fas fa-chevron-right"></i></div>
                    <div class="state-node node-pending">
                        <div class="state-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div class="state-lbl">Activation Config</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ul"></i> Partner Entity Registry</div>
                <div style="display:flex; gap:12px;">
                    <button class="btn btn-sm"><i class="fas fa-filter"></i> Filter</button>
                    <button class="btn btn-sm"><i class="fas fa-download"></i> Export</button>
                </div>
            </div>
            <div class="panel-body" style="padding:0; overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Partner Entity</th>
                            <th>Identity / Type</th>
                            <th>Verification State</th>
                            <th>Contract State</th>
                            <th>Operational Status</th>
                            <th>Risk</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Apollo City Pharmacy</div>
                                <div style="font-size:0.75rem; color:#94A3B8; font-family:monospace;">PRT-004882</div>
                            </td>
                            <td><span class="badge bg-info"><i class="fas fa-prescription-bottle-alt"></i> Pharmacy</span></td>
                            <td><span class="badge bg-healthy"><i class="fas fa-check-double"></i> Verified</span></td>
                            <td><span class="badge bg-healthy">Active (v2)</span></td>
                            <td><span class="badge bg-healthy">Online</span></td>
                            <td><span class="badge bg-healthy">Low</span></td>
                            <td><button class="btn btn-sm">Manage</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Dr. Ramesh Kumar P.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; font-family:monospace;">PRT-009112</div>
                            </td>
                            <td><span class="badge bg-info"><i class="fas fa-user-md"></i> Doctor</span></td>
                            <td><span class="badge bg-healthy"><i class="fas fa-check-double"></i> Verified</span></td>
                            <td><span class="badge bg-healthy">Active (v1)</span></td>
                            <td><span class="badge bg-suspended">Offline (Leave)</span></td>
                            <td><span class="badge bg-healthy">Low</span></td>
                            <td><button class="btn btn-sm">Manage</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Sunrise Diagnostics</div>
                                <div style="font-size:0.75rem; color:#94A3B8; font-family:monospace;">PRT-002341</div>
                            </td>
                            <td><span class="badge bg-info"><i class="fas fa-microscope"></i> Diagnostic</span></td>
                            <td><span class="badge bg-pending"><i class="fas fa-file-medical"></i> Reviewing Docs</span></td>
                            <td><span class="badge bg-pending">Drafting</span></td>
                            <td><span class="badge bg-pending">Onboarding</span></td>
                            <td><span class="badge bg-pending">TBD</span></td>
                            <td><button class="btn btn-sm">Manage</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:white;">Swift Medical Couriers</div>
                                <div style="font-size:0.75rem; color:#94A3B8; font-family:monospace;">PRT-007765</div>
                            </td>
                            <td><span class="badge bg-info"><i class="fas fa-truck"></i> Delivery</span></td>
                            <td><span class="badge bg-healthy"><i class="fas fa-check-double"></i> Verified</span></td>
                            <td><span class="badge bg-suspended">Expired</span></td>
                            <td><span class="badge bg-suspended">Suspended</span></td>
                            <td><span class="badge bg-suspended">High</span></td>
                            <td><button class="btn btn-sm">Manage</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>
