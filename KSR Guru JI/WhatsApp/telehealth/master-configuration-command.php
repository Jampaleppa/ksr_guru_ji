<?php
require_once 'helper.php';
// Simulated Platform Administration, Organization & Master Configuration Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin Configuration | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Admin Navbar */
        .admin-nav { background: #4C1D95; border-bottom: 2px solid #5B21B6; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .admin-nav .navbar-brand { color: #DDD6FE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .admin-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .admin-nav-links::-webkit-scrollbar { display: none; }
        .admin-nav-links a { color: #C4B5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .admin-nav-links a:hover { color: white; background: #6D28D9; }
        .admin-nav-links a.active { color: white; background: #7C3AED; box-shadow: 0 0 10px rgba(124, 58, 237, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px; width: 100%; align-items: stretch; }

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
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Governance Matrix */
        .policy-row { display:flex; justify-content:space-between; align-items:center; padding:12px; background:#0F172A; border:1px solid #334155; border-radius:8px; margin-bottom:12px;}
        .policy-info h4 { margin:0 0 4px 0; color:white; font-size:0.95rem; font-weight:800; display:flex; align-items:center; gap:8px;}
        .policy-info p { margin:0; font-size:0.8rem; color:#94A3B8; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-purple { background: #7C3AED; border-color: #6D28D9; color: white; }
        .btn-purple:hover { background: #6D28D9; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="admin-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> PLATFORM ADMIN & MASTER CONFIG
        </div>
        <div class="admin-nav-links">
            <a href="#"  class="active">Master Directory</a>
            <a href="#" >Financial Rules</a>
            <a href="#" >SLA & Policies</a>
            <a href="#" >System Flags</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-crown" style="color:#A78BFA;"></i> Super Administrator
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-sliders-h" style="color:#A78BFA;"></i> Core Master Data Configuration</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Setting the ground rules, taxonomies, and fee structures that govern the entire Doctor Assist ecosystem.</div>
        </div>
        <div>
            <button class="btn btn-purple"><i class="fas fa-plus"></i> Add Master Entity</button>
        </div>
    </div>

    <!-- Active Admin KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">Tenant Organizations</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">48</div>
            <div class="kpi-lbl">Clinical Specialties</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">₹250</div>
            <div class="kpi-lbl">Base Platform Fee</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">3</div>
            <div class="kpi-lbl">Policy Reviews Pending</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Organization & Specialization Data -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-sitemap"></i> Organization Entity Registry</div>
                <button class="btn btn-sm"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Entity Name / Type</th>
                            <th>Integration Scope</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;"><i class="fas fa-hospital" style="color:#8B5CF6;"></i> City General Hospital</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Multi-Facility Hub</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Consulting</span>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Diagnostics</span>
                            </td>
                            <td><span class="badge bg-success">ACTIVE</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;"><i class="fas fa-pills" style="color:#8B5CF6;"></i> Apex Pharmacy Chain</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Vendor Partner ID: PH-2201</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Fulfillment</span>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Delivery</span>
                            </td>
                            <td><span class="badge bg-success">ACTIVE</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;"><i class="fas fa-heartbeat" style="color:#8B5CF6;"></i> Cardiology Master Speciality</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Taxonomy Code: C-14</div>
                            </td>
                            <td>
                                <span class="badge" style="background:#1E293B; border-color:#334155;">Global Taxonomy</span>
                            </td>
                            <td><span class="badge bg-success">PUBLISHED</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Global Policies & Constraints -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-gavel"></i> Global Governance & Rules Engine</div>
            </div>
            <div class="panel-body">
                
                <h3 style="margin:0 0 16px 0; font-size:0.95rem; color:white; border-bottom:1px solid #334155; padding-bottom:8px;">Financial & Workflow Config</h3>
                
                <div class="policy-row">
                    <div class="policy-info">
                        <h4><i class="fas fa-coins" style="color:#F59E0B;"></i> Platform Revenue Split</h4>
                        <p>Doctor 85% / Platform 15% | Active globally</p>
                    </div>
                    <button class="btn btn-sm"><i class="fas fa-edit"></i> Edit</button>
                </div>

                <div class="policy-row">
                    <div class="policy-info">
                        <h4><i class="fas fa-clock" style="color:#38BDF8;"></i> Auto-Cancel Unpaid Appointments</h4>
                        <p>Rule: Free up slot if payment pending > 15 minutes.</p>
                    </div>
                    <div class="badge bg-success">ENFORCED</div>
                </div>

                <div class="policy-row">
                    <div class="policy-info">
                        <h4><i class="fas fa-file-medical-alt" style="color:#10B981;"></i> Required Verification for Prescriptions</h4>
                        <p>All Draft Prescriptions require MFA re-authentication.</p>
                    </div>
                    <div class="badge bg-success">ENFORCED</div>
                </div>

                <div class="policy-row">
                    <div class="policy-info">
                        <h4><i class="fas fa-truck" style="color:#A78BFA;"></i> Master Delivery SLA Threshold</h4>
                        <p>Alert Operations Center if pickup unassigned > 30 mins.</p>
                    </div>
                    <button class="btn btn-sm"><i class="fas fa-edit"></i> Edit</button>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
