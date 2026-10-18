<?php
require_once 'helper.php';
// Simulated System Configuration & Policy Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F1F5F9; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Config Navbar */
        .cfg-nav { background: #1E293B; border-bottom: 2px solid #334155; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .cfg-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; }
        .cfg-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .cfg-nav-links::-webkit-scrollbar { display: none; }
        .cfg-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .cfg-nav-links a:hover { color: white; background: #334155; }
        .cfg-nav-links a.active { color: white; background: #475569; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Side Navigation */
        .sidebar { width: 260px; background: white; border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 16px; display: flex; flex-direction: column; gap: 4px; flex-shrink: 0; }
        .sb-head { font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin: 16px 0 8px 12px; }
        .sb-link { padding: 10px 12px; border-radius: 6px; text-decoration: none; color: var(--clr-text-primary); font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .sb-link i { color: var(--clr-text-secondary); width: 16px; text-align: center; }
        .sb-link:hover { background: #F8FAFC; color: var(--clr-primary-dark); }
        .sb-link.active { background: #EFF6FF; color: var(--clr-primary); font-weight: 800; }
        .sb-link.active i { color: var(--clr-primary); }

        /* Main Content */
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 20px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; }
        .kpi-left { display: flex; flex-direction: column; gap: 4px; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: var(--clr-text-primary); line-height: 1.2; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; }
        .kpi-icon { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; border-radius: 12px; }
        
        /* Panel */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md) var(--radius-md) 0 0; }
        .panel-body { padding: 20px; }
        .panel-footer { padding: 16px 20px; background: #F8FAFC; border-top: 1px solid var(--clr-border); border-radius: 0 0 var(--radius-md) var(--radius-md); display: flex; gap: 12px; justify-content: flex-end; }

        /* Forms */
        .form-row { display: grid; grid-template-columns: 200px 1fr; gap: 24px; align-items: center; padding: 16px 0; border-bottom: 1px dashed var(--clr-border); }
        .form-row:last-child { border-bottom: none; }
        .form-label { font-weight: 700; color: var(--clr-text-primary); font-size: 0.95rem; }
        .form-desc { font-size: 0.8rem; color: var(--clr-text-secondary); font-weight: normal; margin-top: 4px; }
        .form-control { width: 100%; max-width: 300px; padding: 10px 12px; border: 1px solid var(--clr-border); border-radius: 6px; font-family: inherit; font-size: 0.95rem; color: var(--clr-text-primary); }
        .form-control:focus { outline: none; border-color: var(--clr-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        /* Version Banner */
        .version-banner { background: #ECFDF5; border: 1px solid #10B981; border-radius: 6px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .vb-text { font-weight: 800; color: #065F46; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }

        /* Buttons */
        .btn { padding: 10px 16px; border-radius: 6px; font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: 0.2s; border: none; display: flex; align-items: center; gap: 8px; }
        .btn-outline { background: white; border: 1px solid var(--clr-border); color: var(--clr-text-primary); }
        .btn-outline:hover { background: #F1F5F9; }
        .btn-primary { background: var(--clr-primary); color: white; }
        .btn-primary:hover { background: var(--clr-primary-dark); }
        
        /* Toggles */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #16A34A; }
        input:checked + .slider:before { transform: translateX(20px); }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="cfg-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> CONFIGURATION
        </div>
        <div class="cfg-nav-links">
            <a href="#"  class="active">Dashboard</a>
            <a href="#" >Versions</a>
            <a href="#" >Approvals <span style="background:#EF4444; color:white; padding:2px 6px; border-radius:12px; font-size:0.75rem; margin-left:4px;">4</span></a>
            <a href="#" >Audit History</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="color: #10B981; font-weight: 800; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-server"></i> Configuration Service Healthy
            </div>
            <div style="color: white; font-weight: 700; background: #334155; padding: 6px 12px; border-radius: 6px; font-size:0.85rem;">
                <i class="fas fa-code-branch"></i> v42
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="#"  class="sb-link"><i class="fas fa-home"></i> Dashboard</a>
        
        <div class="sb-head">Financial Policy</div>
        <a href="#"  class="sb-link active"><i class="fas fa-rupee-sign"></i> Consultation Pricing</a>
        <a href="#"  class="sb-link"><i class="fas fa-truck"></i> Delivery Pricing</a>
        <a href="#"  class="sb-link"><i class="fas fa-undo"></i> Refund Rules</a>

        <div class="sb-head">Clinical Policy</div>
        <a href="#"  class="sb-link"><i class="fas fa-baby"></i> Age-Based Routing</a>
        <a href="#"  class="sb-link"><i class="fas fa-file-medical"></i> Prescription Rules</a>
        <a href="#"  class="sb-link"><i class="fas fa-store-alt"></i> Pharmacy Rules</a>

        <div class="sb-head">System & Security</div>
        <a href="#"  class="sb-link"><i class="fas fa-bell"></i> Notification Engine</a>
        <a href="#"  class="sb-link"><i class="fas fa-stopwatch"></i> SLA Configuration</a>
        <a href="#"  class="sb-link"><i class="fas fa-flag"></i> Feature Flags</a>
        <a href="#"  class="sb-link"><i class="fas fa-shield-alt"></i> Security & Access</a>
        <a href="#"  class="sb-link" style="color:#DC2626;"><i class="fas fa-power-off"></i> Emergency Controls</a>
    </div>

    <!-- Main Editor -->
    <div class="main-col">
        
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-left">
                    <div class="kpi-title">Active Policies</div>
                    <div class="kpi-val">28</div>
                </div>
                <div class="kpi-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-file-contract"></i></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <div class="kpi-title">Pending Approval</div>
                    <div class="kpi-val" style="color:#F59E0B;">4</div>
                </div>
                <div class="kpi-icon" style="background:#FFF7ED; color:#EA580C;"><i class="fas fa-clock"></i></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <div class="kpi-title">Pricing Configs</div>
                    <div class="kpi-val">12</div>
                </div>
                <div class="kpi-icon" style="background:#ECFDF5; color:#059669;"><i class="fas fa-tags"></i></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <div class="kpi-title">Feature Flags</div>
                    <div class="kpi-val">46</div>
                </div>
                <div class="kpi-icon" style="background:#F5F3FF; color:#8B5CF6;"><i class="fas fa-flag-checkered"></i></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div style="font-weight:900; font-size:1.1rem; color:var(--clr-primary-dark);"><i class="fas fa-rupee-sign"></i> Consultation Pricing Engine</div>
            </div>
            
            <div class="panel-body">
                
                <div class="version-banner">
                    <div class="vb-text">
                        <i class="fas fa-check-circle"></i> Currently editing policy on ACTIVE Version: v42
                    </div>
                    <button class="btn btn-outline" style="background:white; padding:6px 12px; font-size:0.8rem;">Change Version</button>
                </div>

                <div style="font-weight:800; font-size:1.05rem; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid var(--clr-border);">Audio Consultation Pricing</div>
                
                <div class="form-row">
                    <div>
                        <div class="form-label">Doctor Fee (₹)</div>
                        <div class="form-desc">Amount credited to doctor</div>
                    </div>
                    <div>
                        <input type="number" class="form-control" value="100">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <div class="form-label">Platform Fee (₹)</div>
                        <div class="form-desc">Platform retention charge</div>
                    </div>
                    <div>
                        <input type="number" class="form-control" value="15">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <div class="form-label">Effective From</div>
                        <div class="form-desc">When does this pricing take effect</div>
                    </div>
                    <div>
                        <input type="date" class="form-control" value="2026-10-01">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <div class="form-label">Apply to existing drafts?</div>
                        <div class="form-desc">Overrides pending payment states</div>
                    </div>
                    <div>
                        <label class="switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

            </div>
            
            <div class="panel-footer">
                <button class="btn btn-outline"><i class="fas fa-save"></i> Save Draft</button>
                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit for Approval</button>
            </div>
        </div>

    </div>

</div>

</body>
</html>
