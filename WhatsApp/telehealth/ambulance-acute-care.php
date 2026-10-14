<?php
require_once 'helper.php';
// Simulated Emergency Department, Triage, Ambulance & Acute Care Management Center
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency & Acute Care Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0F172A;
            margin: 0;
            padding-bottom: 40px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #F8FAFC;
        }

        /* Specialized Emergency Navbar */
        .ed-nav {
            background: #7F1D1D;
            border-bottom: 2px solid #991B1B;
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 0 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }

        .ed-nav .navbar-brand {
            color: #FECACA;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ed-nav-links {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            padding: 12px 0;
        }

        .ed-nav-links::-webkit-scrollbar {
            display: none;
        }

        .ed-nav-links a {
            color: #FCA5A5;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: 0.2s;
        }

        .ed-nav-links a:hover {
            color: white;
            background: #DC2626;
        }

        .ed-nav-links a.active {
            color: white;
            background: #EF4444;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        .app-container {
            display: flex;
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 24px;
            gap: 24px;
        }

        /* Layout Grid */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            width: 100%;
            align-items: stretch;
        }

        /* Panel Standard */
        .panel {
            background: #1E293B;
            border-radius: var(--radius-md);
            border: 1px solid #334155;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px solid #334155;
            background: #0F172A;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
        }

        .panel-title {
            font-weight: 900;
            font-size: 1.05rem;
            color: #E0E7FF;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Unified Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            color: #94A3B8;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 800;
            border-bottom: 2px solid #334155;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #334155;
            vertical-align: middle;
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .data-table tr:hover {
            background: #0F172A;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            gap: 4px;
            border: 1px solid transparent;
            letter-spacing: 0.5px;
        }

        .bg-critical {
            background: rgba(239, 68, 68, 0.1);
            color: #F87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .bg-urgent {
            background: rgba(245, 158, 11, 0.1);
            color: #FBBF24;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .bg-success {
            background: rgba(16, 185, 129, 0.1);
            color: #34D399;
            border-color: rgba(52, 211, 153, 0.3);
        }

        .bg-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #60A5FA;
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Ambulance Card */
        .amb-card {
            background: #0F172A;
            border: 1px solid #334155;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .amb-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amb-id {
            font-weight: 900;
            font-size: 1rem;
            color: white;
        }

        .amb-details {
            font-size: 0.8rem;
            color: #94A3B8;
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: #1E293B;
            border-radius: var(--radius-md);
            border: 1px solid #334155;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .kpi-val {
            font-size: 1.8rem;
            font-weight: 900;
            color: white;
            margin-bottom: 4px;
        }

        .kpi-lbl {
            font-size: 0.8rem;
            color: #94A3B8;
            font-weight: 800;
            text-transform: uppercase;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            border: 1px solid #475569;
            background: #334155;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            background: #475569;
        }

        .btn-red {
            background: #EF4444;
            border-color: #DC2626;
            color: white;
        }

        .btn-red:hover {
            background: #DC2626;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = '';
    $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php'; ?>

    <div class="ed-nav">
        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
            <div class="navbar-brand">
                <i class="fas fa-ambulance"></i> ED, AMBULANCE & ACUTE CARE
            </div>
            <div class="ed-nav-links">
                <a href="#" class="active">Ambulance Pre-Arrival</a>
                <a href="#" >Triage Queue</a>
                <a href="#" >Resuscitation Bays</a>
                <a href="#" >ED Trackboard</a>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                    <i class="fas fa-user-md" style="color:#FCA5A5;"></i> ED Attending
                </div>
            </div>
        </div>
    </div>

    <div class="app-container" style="flex-direction:column;">

        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-heartbeat" style="color:#EF4444;"></i> ED & Pre-Hospital Operations</h1>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Connecting Pre-Hospital Ambulance telematics and initial Emergency Triage decoupling.</div>
            </div>
            <div>
                <button class="btn btn-red"><i class="fas fa-siren-on"></i> Activate Trauma Team</button>
            </div>
        </div>

        <!-- Active ED KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <div class="kpi-val">42</div>
                <div class="kpi-lbl">Total ED Census</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <div class="kpi-val">3</div>
                <div class="kpi-lbl">Inbound Ambulances</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
                <div class="kpi-val">5</div>
                <div class="kpi-lbl">Waiting Admit / Transfer</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
                <div class="kpi-val" style="color:#FCA5A5;">14m</div>
                <div class="kpi-lbl" style="color:#FCA5A5;">Avg Triage Wait Time</div>
            </div>
        </div>

        <div class="grid-layout">

            <!-- Pre-Arrival Ambulance Telemetry -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-truck-medical"></i> Ambulance Pre-Arrival & Telemetry</div>
                </div>
                <div class="panel-body">

                    <div class="amb-card" style="border-color:#EF4444; background:rgba(239,68,68,0.05);">
                        <div class="amb-header">
                            <span class="amb-id"><i class="fas fa-truck-medical" style="color:#EF4444;"></i> ALS-Unit 401</span>
                            <span class="badge bg-critical">ETA: 4 MIN</span>
                        </div>
                        <div class="amb-details" style="display:flex; justify-content:space-between; margin-top:8px;">
                            <div>
                                <div style="color:white; font-weight:800; margin-bottom:4px;">STEMI Alert / Cardiac Arrest</div>
                                <div>Patient: Jane Doe (Approx 55F)</div>
                                <div>Vitals: <span style="color:#F87171; font-weight:bold;">HR 140 / BP 80/50 / O2 88%</span></div>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-red"><i class="fas fa-walkie-talkie"></i> Comms</button>
                            </div>
                        </div>
                        <div style="margin-top:8px; display:flex; gap:8px;">
                            <span class="badge bg-blue">CATH LAB ALERTED</span>
                            <span class="badge bg-urgent">TRAUMA BAY 1 CONFIRMED</span>
                        </div>
                    </div>

                    <div class="amb-card">
                        <div class="amb-header">
                            <span class="amb-id"><i class="fas fa-truck-medical" style="color:#3B82F6;"></i> BLS-Unit 112</span>
                            <span class="badge bg-urgent">ETA: 12 MIN</span>
                        </div>
                        <div class="amb-details" style="display:flex; justify-content:space-between; margin-top:8px;">
                            <div>
                                <div style="color:white; font-weight:800; margin-bottom:4px;">RTA / Motor Vehicle Accident</div>
                                <div>Patient: Unknown Male</div>
                                <div>Vitals: HR 110 / BP 110/80 / O2 96%</div>
                            </div>
                        </div>
                    </div>

                    <div class="amb-card">
                        <div class="amb-header">
                            <span class="amb-id"><i class="fas fa-truck-medical" style="color:#10B981;"></i> Transfer-Unit 03</span>
                            <span class="badge bg-success">ETA: 25 MIN</span>
                        </div>
                        <div class="amb-details" style="display:flex; justify-content:space-between; margin-top:8px;">
                            <div>
                                <div style="color:white; font-weight:800; margin-bottom:4px;">Inter-Facility Transfer (ICU)</div>
                                <div>Patient: Gupta, R.</div>
                                <div>Awaiting direct admission to Bed A-02</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Electronic Triage & Acuity Queue -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-clipboard-list"></i> ED Trackboard & Clinical Acuity</div>
                </div>
                <div class="panel-body" style="padding:0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bed/Loc</th>
                                <th>Patient / Issue</th>
                                <th>Acuity (ESI)</th>
                                <th>Next Action / Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span style="font-weight:900; color:#F87171;">T-BAY 2</span></td>
                                <td>
                                    <div style="font-weight:900; color:white;">Singh, P.</div>
                                    <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Acute Respiratory Distress</div>
                                </td>
                                <td>
                                    <span class="badge bg-critical">LEVEL 1 (RESUS)</span>
                                </td>
                                <td><button class="btn btn-sm btn-red">STAT Chest X-Ray</button></td>
                            </tr>
                            <tr>
                                <td><span style="font-weight:900; color:white;">ED-04</span></td>
                                <td>
                                    <div style="font-weight:900; color:white;">Kumar, A.</div>
                                    <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Severe Abdominal Pain</div>
                                </td>
                                <td>
                                    <span class="badge bg-urgent">LEVEL 2 (EMERGENT)</span>
                                </td>
                                <td><button class="btn btn-sm">Review CT Scan</button></td>
                            </tr>
                            <tr>
                                <td><span style="font-weight:900; color:#94A3B8;">WR-12</span></td>
                                <td>
                                    <div style="font-weight:900; color:white;">Verma, S.</div>
                                    <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Ankle Sprain (R)</div>
                                </td>
                                <td>
                                    <span class="badge bg-success">LEVEL 4 (LESS URGENT)</span>
                                </td>
                                <td><span class="badge" style="background:#1E293B; border-color:#334155;">Waiting MD</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

</body>

</html>