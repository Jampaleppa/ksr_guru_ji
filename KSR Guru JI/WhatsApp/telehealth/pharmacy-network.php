<?php
require_once 'helper.php';
// Simulated Pharmacy Network & Medication Supply Chain Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Network Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Pharmacy Navbar */
        .ph-nav { background: #064E3B; border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .ph-nav .navbar-brand { color: #34D399; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .ph-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .ph-nav-links::-webkit-scrollbar { display: none; }
        .ph-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .ph-nav-links a:hover { color: white; background: #059669; }
        .ph-nav-links a.active { color: white; background: #10B981; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; width: 100%; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .dt-primary { font-weight: 900; color: white; display: flex; align-items: center; gap: 8px; }
        .dt-secondary { font-size: 0.8rem; color: #94A3B8; margin-top: 6px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.1); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.1); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; margin-top: 4px; }

        /* Workflow Steps */
        .workflow-box { background: #0F172A; border: 1px solid #334155; padding: 16px; border-radius: 8px; margin-top: 8px;}
        .wf-step { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .wf-step:last-child { margin-bottom: 0; }
        .wf-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 0.9rem; }
        .wf-active .wf-icon { background: #3B82F6; color: white; border: 2px solid #60A5FA; }
        .wf-done .wf-icon { background: rgba(16, 185, 129, 0.1); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .wf-pending .wf-icon { background: #1E293B; color: #64748B; border: 1px dashed #475569; }
        .wf-text { flex: 1; font-weight: 700; font-size: 0.85rem; color: #E2E8F0; }
        .wf-meta { font-size: 0.75rem; color: #94A3B8; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-primary { background: #10B981; border-color: #059669; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="ph-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-prescription-bottle-alt"></i> PHARMACY COMMAND CENTER
        </div>
        <div class="ph-nav-links">
            <a href="#"  class="active">Live Dashboard</a>
            <a href="#" >Verification Queue</a>
            <a href="#" >Inventory Reserves</a>
            <a href="#" >Picking & Packing</a>
            <a href="#" >Expiry & Core Logic</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=53" style="width:28px; height:28px; border-radius:50%; border:2px solid #10B981;"> Master Pharmacist
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-boxes" style="color:#10B981;"></i> Supply Chain & Order Fulfillment</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating state decoupling: Verification ≠ Reservation ≠ Picking ≠ Dispensed.</div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="btn"><i class="fas fa-barcode"></i> Scan Batch</button>
            <button class="btn btn-primary"><i class="fas fa-check-double"></i> Verification Queue</button>
        </div>
    </div>

    <!-- Active Pipeline KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">42</div>
            <div class="kpi-lbl">RX Pending Review</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">118</div>
            <div class="kpi-lbl">Verified / Payment Confirmed</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
            <div class="kpi-val">27</div>
            <div class="kpi-lbl">Active Picking Tasks</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">19</div>
            <div class="kpi-lbl">Packed & Waiting Dispatch</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
            <div class="kpi-val">14</div>
            <div class="kpi-lbl">Critical Low Stock / Alerts</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Live Fulfillment Pipeline -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-dolly"></i> Live Order Fulfillment Logistics</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order & Patient</th>
                            <th>Fulfillment State</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary">ORD-8942</div>
                                <div class="dt-secondary">Patient: Rahul Kumar | Items: 2</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">VERIFIED & RESERVED</span>
                                <div class="workflow-box" style="padding:12px; margin-top:8px;">
                                    <div class="wf-step wf-done">
                                        <div class="wf-icon"><i class="fas fa-check"></i></div>
                                        <div class="wf-text">1. Verification <span class="wf-meta">(Dr. Priya / Pharm. Anil)</span></div>
                                    </div>
                                    <div class="wf-step wf-done">
                                        <div class="wf-icon"><i class="fas fa-lock"></i></div>
                                        <div class="wf-text">2. Stock Locked <span class="wf-meta">(Mysuru Central)</span></div>
                                    </div>
                                    <div class="wf-step wf-active">
                                        <div class="wf-icon"><i class="fas fa-hand-paper"></i></div>
                                        <div class="wf-text">3. Picking Queue <span class="wf-meta">(Awaiting Staff)</span></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary">Start Pick</button>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="dt-primary">ORD-8941</div>
                                <div class="dt-secondary">Patient: Suresh P | Items: 1</div>
                            </td>
                            <td>
                                <span class="badge bg-info">PACKING & CHECKING</span>
                                <div class="workflow-box" style="padding:12px; margin-top:8px;">
                                    <div class="wf-step wf-done">
                                        <div class="wf-icon"><i class="fas fa-check"></i></div>
                                        <div class="wf-text">3. Picked <span class="wf-meta">(Batch B-402, FEFO Valid)</span></div>
                                    </div>
                                    <div class="wf-step wf-active">
                                        <div class="wf-icon"><i class="fas fa-box"></i></div>
                                        <div class="wf-text">4. QA Check & Pack</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#3B82F6; border-color:#2563EB;">QA Confirm</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Inventory Architecture Example -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-cubes"></i> Real-Time Inventory Matrix (Mysuru Central)</div>
            </div>
            <div class="panel-body">
                
                <h3 style="font-size:0.9rem; font-weight:800; color:white; margin-top:0; border-bottom:1px solid #334155; padding-bottom:8px;">SKU: Amlodipine 5mg (10s)</h3>
                
                <!-- Stock Formula -->
                <div style="display:flex; justify-content:space-between; text-align:center; background:#020617; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:20px;">
                    <div>
                        <div style="font-size:1.8rem; font-weight:900; color:white;">150</div>
                        <div style="font-size:0.7rem; color:#94A3B8; font-weight:800; text-transform:uppercase;">On Hand</div>
                    </div>
                    <div style="font-size:1.5rem; color:#64748B; font-weight:900; margin-top:4px;">-</div>
                    <div>
                        <div style="font-size:1.8rem; font-weight:900; color:#F59E0B;">20</div>
                        <div style="font-size:0.7rem; color:#94A3B8; font-weight:800; text-transform:uppercase; color:#FBBF24;">Reserved</div>
                    </div>
                    <div style="font-size:1.5rem; color:#64748B; font-weight:900; margin-top:4px;">-</div>
                    <div>
                        <div style="font-size:1.8rem; font-weight:900; color:#EF4444;">10</div>
                        <div style="font-size:0.7rem; color:#94A3B8; font-weight:800; text-transform:uppercase; color:#FCA5A5;">Unavailable (QA)</div>
                    </div>
                    <div style="font-size:1.5rem; color:#3B82F6; font-weight:900; margin-top:4px;">=</div>
                    <div>
                        <div style="font-size:1.8rem; font-weight:900; color:#34D399;">120</div>
                        <div style="font-size:0.7rem; color:#10B981; font-weight:800; text-transform:uppercase;">Available</div>
                    </div>
                </div>

                <h3 style="font-size:0.9rem; font-weight:800; color:white; border-bottom:1px solid #334155; padding-bottom:8px; margin-top:24px;">FEFO Batch Distribution</h3>
                <table class="data-table" style="background:#0F172A; border-radius:8px; overflow:hidden;">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="dt-primary" style="font-size:0.85rem;">B-1024</td>
                            <td style="color:#FBBF24; font-size:0.85rem; font-weight:700;">OCT 2026 (Picking)</td>
                            <td><span class="badge bg-success">ACTIVE</span></td>
                            <td><button class="btn btn-sm">Audit</button></td>
                        </tr>
                        <tr>
                            <td class="dt-primary" style="font-size:0.85rem;">B-3099</td>
                            <td style="font-size:0.85rem;">JAN 2027</td>
                            <td><span class="badge bg-success">ACTIVE</span></td>
                            <td><button class="btn btn-sm">Audit</button></td>
                        </tr>
                        <tr>
                            <td class="dt-primary" style="font-size:0.85rem;">B-0422</td>
                            <td style="font-size:0.85rem; color:#EF4444;"><i class="fas fa-exclamation-circle"></i> EXPIRED</td>
                            <td><span class="badge bg-danger">QUARANTINED</span></td>
                            <td><button class="btn btn-sm">Dispose</button></td>
                        </tr>
                    </tbody>
                </table>
                <div style="font-size:0.75rem; color:#64748B; margin-top:12px;">
                    <i class="fas fa-info-circle"></i> Batch B-0422 is quarantined and factored into the 'Unavailable' count above.
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
