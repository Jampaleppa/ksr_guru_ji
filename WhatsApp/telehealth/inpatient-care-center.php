<?php
require_once 'helper.php';
// Simulated Hospital & Inpatient Care Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital & Inpatient Care Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Inpatient Navbar */
        .inpatient-nav { background: #831843; border-bottom: 2px solid #9D174D; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .inpatient-nav .navbar-brand { color: #FCE7F3; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .inpatient-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .inpatient-nav-links::-webkit-scrollbar { display: none; }
        .inpatient-nav-links a { color: #F9A8D4; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .inpatient-nav-links a:hover { color: white; background: #DB2777; }
        .inpatient-nav-links a.active { color: white; background: #BE185D; box-shadow: 0 0 10px rgba(190, 24, 93, 0.5); }

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
        .bg-critical { background: rgba(239, 68, 68, 0.1); color: #F87171; border-color: rgba(239, 68, 68, 0.3); }
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-pink { background: rgba(236, 72, 153, 0.1); color: #F472B6; border-color: rgba(236, 72, 153, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-pink { background: #BE185D; border-color: #9D174D; color: white; }
        .btn-pink:hover { background: #9D174D; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        /* Bed Representation */
        .bed-card { background:#0F172A; border:1px solid #334155; padding:12px; border-radius:8px; display:flex; flex-direction:column; gap:8px;}
        .bed-header { display:flex; justify-content:space-between; align-items:center;}
        .bed-loc { font-weight:900; font-size:1rem; color:white; }
        .bed-details { font-size:0.8rem; color:#94A3B8;}
        
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="inpatient-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-hospital-user"></i> HOSPITAL & INPATIENT CARE MANAGEMENT
        </div>
        <div class="inpatient-nav-links">
            <a href="#"  class="active">Ward & Bed Occupancy</a>
            <a href="#" >Nursing eMAR</a>
            <a href="#" >Inpatient Orders (CPOE)</a>
            <a href="#" >Discharge Planning</a>
            <a href="#" >ADT (Admit/Discharge/Transfer)</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-user-nurse" style="color:#F9A8D4;"></i> Charge Nurse
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-procedures" style="color:#F472B6;"></i> Inpatient Ward Operations</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Coordinating ADT Workflows, Medication Administration (eMAR), and Rounding.</div>
        </div>
        <div>
            <button class="btn btn-pink"><i class="fas fa-sign-in-alt"></i> Admit New Patient</button>
        </div>
    </div>

    <!-- Active Inpatient KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #EC4899;">
            <div class="kpi-val">102 / 120</div>
            <div class="kpi-lbl">Total Bed Occupancy</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">8</div>
            <div class="kpi-lbl">Pending Transfers</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">Planned Discharges Today</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">2</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Critical Vitals Flags</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Bed Management & ADT -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-bed"></i> Bed Management & Occupancy (ICU-A)</div>
                <button class="btn btn-sm"><i class="fas fa-exchange-alt"></i> Transfer Patient</button>
            </div>
            <div class="panel-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    
                    <div class="bed-card">
                        <div class="bed-header">
                            <span class="bed-loc">Bed A-01</span>
                            <span class="badge bg-critical">OCCUPIED</span>
                        </div>
                        <div class="bed-details">
                            <div style="color:white; font-weight:800; margin-bottom:4px;">Sharma, V. (Age 62)</div>
                            <div>Attending: Dr. M. Rathod</div>
                            <div>Admitted: Sep 02, 14:00</div>
                        </div>
                        <div style="margin-top:4px; display:flex; gap:8px;">
                            <button class="btn btn-sm btn-pink" style="flex:1;">Open Chart</button>
                        </div>
                    </div>

                    <div class="bed-card">
                        <div class="bed-header">
                            <span class="bed-loc">Bed A-02</span>
                            <span class="badge bg-success">AVAILABLE</span>
                        </div>
                        <div class="bed-details">
                            <div style="color:#94A3B8; margin-bottom:4px;">Cleaned & Ready</div>
                            <div>Room Type: Single ICU</div>
                        </div>
                        <div style="margin-top:4px; display:flex; gap:8px;">
                            <button class="btn btn-sm" style="flex:1;">Assign Patient</button>
                        </div>
                    </div>

                    <div class="bed-card">
                        <div class="bed-header">
                            <span class="bed-loc">Bed A-03</span>
                            <span class="badge bg-purple">CLEANING PENDING</span>
                        </div>
                        <div class="bed-details">
                            <div style="color:#94A3B8; margin-bottom:4px;">Discharged: 10:15 AM</div>
                            <div>Waiting for Environmental Services</div>
                        </div>
                        <div style="margin-top:4px; display:flex; gap:8px;">
                            <button class="btn btn-sm" style="flex:1;">Page EVS</button>
                        </div>
                    </div>

                    <div class="bed-card">
                        <div class="bed-header">
                            <span class="bed-loc">Bed A-04</span>
                            <span class="badge bg-critical">OCCUPIED</span>
                        </div>
                        <div class="bed-details">
                            <div style="color:white; font-weight:800; margin-bottom:4px;">Kumar, R. (Age 45)</div>
                            <div>Attending: Dr. A. Gupta</div>
                            <div>Admitted: Sep 04, 09:30</div>
                        </div>
                        <div style="margin-top:4px; display:flex; gap:8px;">
                            <button class="btn btn-sm btn-pink" style="flex:1;">Open Chart</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Electronic Medication Administration Record (eMAR) -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-pills"></i> Shift eMAR Queue & Inpatient Orders</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Bed</th>
                            <th>Medication / Order</th>
                            <th>Shift Schedule</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Sharma, V.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Bed A-01</div>
                            </td>
                            <td>
                                <div style="font-weight:900; color:white;">Heparin 5000 units</div>
                                <div style="font-size:0.75rem; color:#FBBF24; margin-top:4px;">Subcutaneous (DVT Prophylaxis)</div>
                            </td>
                            <td>
                                <span class="badge bg-critical">DUE NOW (14:00)</span>
                            </td>
                            <td><button class="btn btn-sm btn-pink">Administer</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Kumar, R.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Bed A-04</div>
                            </td>
                            <td>
                                <div style="font-weight:900; color:white;">Ceftriaxone 1g IV</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">STAT Order by Attending</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">PREPARING</span>
                            </td>
                            <td><button class="btn btn-sm">Scan Med</button></td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Ali, S.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Bed B-12 (Gen Ward)</div>
                            </td>
                            <td>
                                <div style="font-weight:900; color:white;">Discharge Summary</div>
                                <div style="font-size:0.75rem; color:#60A5FA; margin-top:4px;">Follow-up plan created</div>
                            </td>
                            <td>
                                <span class="badge bg-success">PLANNED (16:00)</span>
                            </td>
                            <td><button class="btn btn-sm btn-pink">Initiate D/C</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

</body>
</html>
