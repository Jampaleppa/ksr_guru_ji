<?php
require_once 'helper.php';
// Simulated Reports, Analytics & Business Intelligence Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intelligence | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Analytics Navbar - dark slate theme */
        .analytics-nav { background: #0F172A; border-bottom: 2px solid #1E293B; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .analytics-nav .navbar-brand { color: #38BDF8; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; }
        .analytics-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .analytics-nav-links::-webkit-scrollbar { display: none; }
        .analytics-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .analytics-nav-links a:hover { color: white; background: #1E293B; }
        .analytics-nav-links a.active { color: white; background: #334155; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px; }
        
        /* Filter Bar */
        .filter-bar { background: white; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; }
        .filter-input { padding: 8px 12px; border: 1px solid var(--clr-border); border-radius: 6px; font-family: inherit; font-size: 0.85rem; font-weight: 600; outline: none; background: #F8FAFC; }
        
        /* KPI Cards */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: var(--clr-text-primary); }
        .kpi-sub { font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 4px; }
        .trend-up { color: #10B981; }
        .trend-down { color: #EF4444; }

        /* Panel Blocks */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }

        /* Status Check */
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--clr-border); }
        .health-row:last-child { border-bottom: none; }
        .health-label { font-size: 0.9rem; font-weight: 700; color: var(--clr-text-primary); display:flex; align-items:center; gap:8px;}
        .health-status { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; display:inline-flex; align-items:center;}
        
        .status-exec { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
        .status-good { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .status-warn { background: #FFF7ED; color: #D97706; border-color: #FFEDD5; }
        
        /* Bar chart sim */
        .bar-chart-row { display: flex; align-items: center; margin-bottom: 12px; }
        .bar-label { width: 120px; font-size: 0.85rem; font-weight: 700; color: var(--clr-text-primary); }
        .bar-track { flex: 1; height: 16px; background: #F1F5F9; border-radius: 8px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 8px; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #F1F5F9; }
        .btn-primary { background: #0F172A; color: white; border-color: #000; }
        .btn-primary:hover { background: #1E293B; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="analytics-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-chart-pie"></i> BI & ANALYTICS
        </div>
        <div class="analytics-nav-links">
            <a href="analytics-dashboard.php" class="active">Executive Dashboard</a>
            <a href="clinical-intelligence.php">Clinical Ops</a>
            <a href="logistics-command.php">Pharmacy & Logistics</a>
            <a href="finance-center.php">Financial Intel</a>
            <a href="patient-relationship.php">Patient Growth</a>
            <a href="admin-dashboard.php">Custom Reports</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #38BDF8;"> Super Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-layer-group text-primary"></i> Platform Intelligence Center</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-magic"></i> AI Insights</button>
                <button class="btn"><i class="fas fa-download"></i> Export Data</button>
                <button class="btn btn-primary"><i class="fas fa-plus"></i> Custom Report</button>
            </div>
        </div>

        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Date Range</span>
                <select class="filter-input"><option>Today</option><option>Last 7 Days</option><option>This Month</option></select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Compare With</span>
                <select class="filter-input"><option>Previous Period</option><option>Last Year</option></select>
            </div>
            <div style="width: 1px; height: 32px; background: #E2E8F0; margin: 0 8px;"></div>
            <div class="filter-group">
                <span class="filter-label">Clinical Zone</span>
                <select class="filter-input"><option>All Regions</option><option>North</option></select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Specialization</span>
                <select class="filter-input"><option>All</option><option>General</option></select>
            </div>
            <div style="flex:1;"></div>
            <button class="btn" style="background:#F1F5F9;"><i class="fas fa-sync-alt"></i> Refresh Data</button>
        </div>

        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-title">👥 Active Patients</div>
                <div class="kpi-val">12,842</div>
                <div class="kpi-sub trend-up"><i class="fas fa-arrow-up"></i> 12.4% vs last period</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">🩺 Consultations</div>
                <div class="kpi-val">8,420</div>
                <div class="kpi-sub trend-up"><i class="fas fa-arrow-up"></i> 18.2% vs last period</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">💊 Pharmacy Orders</div>
                <div class="kpi-val">6,921</div>
                <div class="kpi-sub trend-up"><i class="fas fa-arrow-up"></i> 19.1% vs last period</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">💰 Gross Platform Revenue</div>
                <div class="kpi-val">₹28.4L</div>
                <div class="kpi-sub trend-up"><i class="fas fa-arrow-up"></i> 22.6% vs last period</div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <div style="flex:2; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-chart-line"></i> Revenue Composition Matrix</div>
                    </div>
                    <div class="panel-body">
                        
                        <div class="bar-chart-row">
                            <div class="bar-label">Consultations</div>
                            <div class="bar-track"><div class="bar-fill" style="width:75%; background:#3B82F6;"></div></div>
                            <div style="width:60px; text-align:right; font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary);">₹12.4L</div>
                        </div>
                        <div class="bar-chart-row">
                            <div class="bar-label">Pharmacy Retail</div>
                            <div class="bar-track"><div class="bar-fill" style="width:90%; background:#10B981;"></div></div>
                            <div style="width:60px; text-align:right; font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary);">₹14.2L</div>
                        </div>
                        <div class="bar-chart-row">
                            <div class="bar-label">Logistics/Delivery</div>
                            <div class="bar-track"><div class="bar-fill" style="width:15%; background:#F59E0B;"></div></div>
                            <div style="width:60px; text-align:right; font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary);">₹1.8L</div>
                        </div>

                    </div>
                </div>

                <div class="panel" style="border-color:#FCA5A5;">
                    <div class="panel-head" style="background:#FEF2F2; border-bottom-color:#FCA5A5;">
                        <div class="panel-title" style="color:#B91C1C;"><i class="fas fa-bell"></i> Operational Alerts & Friction Points</div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        
                        <div style="background:white; border:1px solid #FECACA; padding:12px; border-radius:6px; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div style="display:flex; gap:12px; align-items:center;">
                                <i class="fas fa-truck text-danger" style="font-size:1.5rem;"></i>
                                <div>
                                    <div style="font-weight:800; color:#B91C1C; font-size:0.9rem;">Delivery SLA Degradation</div>
                                    <div style="font-size:0.8rem; color:var(--clr-text-secondary);">Zone B delivery SLA fell below 90% in the last 4 hours.</div>
                                </div>
                            </div>
                            <button class="btn" style="font-size:0.75rem; border-color:#FECACA; color:#B91C1C;" onclick="window.location.href='logistics-command.php'">View Logistics Analytics</button>
                        </div>

                        <div style="background:white; border:1px solid #FDE68A; padding:12px; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; gap:12px; align-items:center;">
                                <i class="fas fa-capsules text-warning" style="font-size:1.5rem;"></i>
                                <div>
                                    <div style="font-weight:800; color:#D97706; font-size:0.9rem;">Pharmacy Fulfillment Delay</div>
                                    <div style="font-size:0.8rem; color:var(--clr-text-secondary);">PH-002 experiencing +7m fulfillment delays compared to baseline.</div>
                                </div>
                            </div>
                            <button class="btn" style="font-size:0.75rem; border-color:#FDE68A; color:#D97706;" onclick="window.location.href='pharmacy-dashboard.php'">View Pharmacy Analytics</button>
                        </div>

                    </div>
                </div>

            </div>
            
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-heartbeat"></i> Subsystem Health</div>
                    </div>
                    <div class="panel-body">
                        
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-stethoscope text-primary"></i> Clinical Ops</div>
                            <div class="health-status status-exec"><i class="fas fa-check-circle"></i> Excellent</div>
                        </div>
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-calendar-check text-success"></i> Appointments</div>
                            <div class="health-status status-exec"><i class="fas fa-check-circle"></i> Excellent</div>
                        </div>
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-prescription text-info"></i> Rx Processing</div>
                            <div class="health-status status-good"><i class="fas fa-check"></i> Good</div>
                        </div>
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-box text-warning"></i> Fulfillment</div>
                            <div class="health-status status-good"><i class="fas fa-check"></i> Good</div>
                        </div>
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-truck text-danger"></i> Delivery SLAs</div>
                            <div class="health-status status-warn"><i class="fas fa-exclamation-circle"></i> Attention</div>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head" style="background:#F0FDF4; border-bottom-color:#BBF7D0;">
                        <div class="panel-title" style="color:#16A34A;"><i class="fas fa-lock"></i> Analytics Policy Engine</div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        <div style="font-size:0.85rem; font-weight:700; color:#064E3B; line-height:1.6;">
                            Operational domains are strictly isolated. Analytics module extracts real-time read-only aggregations. No patient PII (Personally Identifiable Information) or sensitive clinical diagnoses are exposed in aggregate executive tables.
                        </div>
                    </div>
                </div>

            </div>
            
        </div>

    </div>

</div>

</body>
</html>
