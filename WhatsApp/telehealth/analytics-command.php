<?php
require_once 'helper.php';
// Simulated Analytics, Intelligence & Executive Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Analytics Command | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Analytics Navbar */
        .analytics-nav { background: #18181B; border-bottom: 2px solid #27272A; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .analytics-nav .navbar-brand { color: #F4F4F5; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .analytics-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .analytics-nav-links::-webkit-scrollbar { display: none; }
        .analytics-nav-links a { color: #A1A1AA; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .analytics-nav-links a:hover { color: white; background: #3F3F46; }
        .analytics-nav-links a.active { color: white; background: #52525B; box-shadow: 0 0 10px rgba(82, 82, 91, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

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
        .bg-critical { background: rgba(220, 38, 38, 0.1); color: #F87171; border-color: rgba(220, 38, 38, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #27272A; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* KPI Sparkline mock */
        .sparkline { height: 40px; display:flex; align-items:flex-end; gap:4px; margin-top:12px; }
        .spark-bar { width: 12px; background: rgba(255,255,255,0.1); border-radius: 2px 2px 0 0; }
        .spark-bar:nth-child(even) { background: rgba(255,255,255,0.2); }
        
        .ai-insight { background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-left: 4px solid #6366F1; border-radius: 8px; padding: 16px; margin-bottom:16px;}
        .ai-insight h4 { margin:0 0 8px 0; color:#A5B4FC; font-size:0.95rem; font-weight:900; display:flex; align-items:center; gap:8px;}

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-primary { background: #52525B; border-color: #3F3F46; color: white; }
        .btn-primary:hover { background: #3F3F46; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
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
            <i class="fas fa-chart-line"></i> EXECUTIVE ANALYTICS COMMAND
        </div>
        <div class="analytics-nav-links">
            <a href="#"  class="active">Executive Overview</a>
            <a href="#" >Clinical Operations</a>
            <a href="#" >Pharmacy Fulfillment</a>
            <a href="#" >Financial Settlement</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=68" style="width:28px; height:28px; border-radius:50%; border:2px solid #A1A1AA;"> Chief Operations Officer
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-project-diagram" style="color:#A1A1AA;"></i> Event-Driven Intelligence Matrix</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating architectural purity: This module aggregates cross-domain events. It does not manipulate original clinical truth.</div>
        </div>
        <div>
            <button class="btn btn-primary"><i class="fas fa-download"></i> Export C-Suite Report</button>
        </div>
    </div>

    <!-- Active Pipeline KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-val">12,540</div>
            <div class="kpi-lbl">Active Patients (YTD)</div>
            <div class="sparkline">
                <div class="spark-bar" style="height:30%"></div>
                <div class="spark-bar" style="height:40%"></div>
                <div class="spark-bar" style="height:60%"></div>
                <div class="spark-bar" style="height:45%"></div>
                <div class="spark-bar" style="height:80%"></div>
                <div class="spark-bar" style="height:100%; background:#34D399;"></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val">3,240</div>
            <div class="kpi-lbl">Consultations (MTD)</div>
            <div class="sparkline">
                <div class="spark-bar" style="height:20%"></div>
                <div class="spark-bar" style="height:30%"></div>
                <div class="spark-bar" style="height:50%"></div>
                <div class="spark-bar" style="height:80%"></div>
                <div class="spark-bar" style="height:65%"></div>
                <div class="spark-bar" style="height:90%; background:#3B82F6;"></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val" style="color:#A78BFA;">₹4.8L</div>
            <div class="kpi-lbl">Gross Platform Settlement</div>
            <div class="sparkline">
                <div class="spark-bar" style="height:50%"></div>
                <div class="spark-bar" style="height:60%"></div>
                <div class="spark-bar" style="height:40%"></div>
                <div class="spark-bar" style="height:70%"></div>
                <div class="spark-bar" style="height:85%"></div>
                <div class="spark-bar" style="height:100%; background:#A78BFA;"></div>
            </div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(220,38,38,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">04</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Operations SLA Breaches</div>
            <div style="font-size:0.75rem; color:#F87171; font-weight:800; margin-top:12px;">ACTION REQUIRED</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Cross-Domain Alert Center -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-bell"></i> Live Operations Alert Center</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sub-Domain</th>
                            <th>Alert Definition / Metric Breach</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white;"><i class="fas fa-clinic-medical" style="color:#F87171;"></i> Clinical Operations</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Diagnostic Safety Center</div>
                            </td>
                            <td>
                                <div style="font-size:0.85rem; color:white; font-weight:700;">Critical Review SLA Breached</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">2 Critical labs pending acknowledgment > 30 mins</div>
                            </td>
                            <td>
                                <span class="badge bg-critical">CRITICAL</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white;"><i class="fas fa-shipping-fast" style="color:#FBBF24;"></i> Logistics Delivery</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Fleet Dispatch Engine</div>
                            </td>
                            <td>
                                <div style="font-size:0.85rem; color:white; font-weight:700;">Assignment Delay At Risk</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">4 Pharmacy orders waiting > 45 minutes for driver.</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">WARNING</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white;"><i class="fas fa-calendar-check" style="color:#34D399;"></i> Scheduling</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Consultation Engine</div>
                            </td>
                            <td>
                                <div style="font-size:0.85rem; color:white; font-weight:700;">Wait-Time Efficiency Stabilized</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Avg wait is 06 min (Healthy)</div>
                            </td>
                            <td>
                                <span class="badge bg-success">HEALTHY</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AI Insights & Report Center -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-brain"></i> AI Operations Intelligence</div>
            </div>
            <div class="panel-body">
                
                <div class="ai-insight">
                    <h4><i class="fas fa-robot"></i> Pharmacist Backlog Prediction</h4>
                    <p style="font-size:0.85rem; color:white; line-height:1.5; margin:0 0 12px 0;">
                        Consultation volume has spiked <strong>+24%</strong> in the last hour. Based on historical conversion rates (65% consultation-to-prescription ratio), the Pharmacy Verification Queue will experience a critical backlog in approximately 45 minutes.
                    </p>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; font-weight:800; color:#A5B4FC; text-transform:uppercase;">Data Src: Consult Events + Pharmacy SLA</span>
                        <button class="btn btn-sm" style="background:#4F46E5; border-color:#4338CA;">Route Additional Pharmacist <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <div class="ai-insight" style="background:rgba(20, 184, 166, 0.05); border-color:rgba(20, 184, 166, 0.3); border-left-color:#14B8A6;">
                    <h4 style="color:#5EEAD4;"><i class="fas fa-search-dollar"></i> Finance Settlement Assurance</h4>
                    <p style="font-size:0.85rem; color:white; line-height:1.5; margin:0 0 12px 0;">
                        All payments processing normally. No mismatch detected between recorded Consultations and generated Invoices across the last 72 hours. Escrow transfers align with SLA.
                    </p>
                    <span style="font-size:0.75rem; font-weight:800; color:#5EEAD4; text-transform:uppercase;">Data Src: Payment Gateway Webhooks</span>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
