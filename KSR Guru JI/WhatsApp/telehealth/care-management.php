<?php
require_once 'helper.php';
// Simulated Care Management, Follow-Up & Referral Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Care & Follow-Up Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Care Navbar */
        .care-nav { background: #064E3B; border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .care-nav .navbar-brand { color: #A7F3D0; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .care-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .care-nav-links::-webkit-scrollbar { display: none; }
        .care-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .care-nav-links a:hover { color: white; background: #059669; }
        .care-nav-links a.active { color: white; background: #10B981; }

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
        
        .dt-primary { font-weight: 900; color: white; display: flex; align-items: center; gap: 8px; font-size:0.95rem; }
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
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Care Plan Structure visually */
        .cp-box { background: #0F172A; border: 1px solid #334155; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .cp-title { font-size: 1.1rem; font-weight: 900; color: white; border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;}
        .section-header { font-size: 0.85rem; font-weight: 900; color: #10B981; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; border-left: 2px solid #10B981; padding-left: 8px; }
        
        .prog-bar { height: 8px; background: #334155; border-radius: 4px; overflow: hidden; margin-top: 8px; width:100%; }
        .prog-fill { height: 100%; background: #10B981; }

        .intervention-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 6px; margin-bottom: 8px; border: 1px solid transparent; }
        
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






<div class="care-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-heartbeat"></i> CARE MANAGEMENT CENTER
        </div>
        <div class="care-nav-links">
            <a href="#"  class="active">Priority Care Queue</a>
            <a href="#" >Active Care Plans</a>
            <a href="#" >Follow-Up Orchestration</a>
            <a href="#" >Referral Network</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=60" style="width:28px; height:28px; border-radius:50%; border:2px solid #10B981;"> Dr. Care Coordinator
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-route" style="color:#10B981;"></i> Longitudinal Continuous Care Matrix</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating state decoupling: Care Plan ≠ Follow-Up Task ≠ Specialist Referral.</div>
        </div>
    </div>

    <!-- Active Pipeline KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">342</div>
            <div class="kpi-lbl">Active Care Plans</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">86</div>
            <div class="kpi-lbl">Follow-Ups Due Today</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
            <div class="kpi-val">21</div>
            <div class="kpi-lbl">Overdue Follow-Ups</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #A78BFA;">
            <div class="kpi-val">34</div>
            <div class="kpi-lbl">Pending Referrals</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F43F5E;">
            <div class="kpi-val">7</div>
            <div class="kpi-lbl">Clinical Escalations</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Priority Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-list-ul"></i> Priority Workflow & Task Orchestration</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient Entity</th>
                            <th>Clinical Action / Workflow Task</th>
                            <th>Priority & Due Info</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user-circle" style="color:#94A3B8;"></i> Rahul Kumar</div>
                                <div class="dt-secondary">Plan: Type 2 Diabetes Management</div>
                            </td>
                            <td>
                                <div class="dt-primary"><i class="fas fa-tasks" style="color:#F59E0B;"></i> Lab Review Follow-up</div>
                                <div class="dt-secondary">Event: HbA1c Results verified in Diagnostics</div>
                            </td>
                            <td>
                                <span class="badge bg-danger">HIGH RISK</span>
                                <div style="font-size:0.75rem; color:#EF4444; font-weight:800; margin-top:8px;">DUE TODAY</div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary">Review Care</button>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user-circle" style="color:#94A3B8;"></i> Anita P</div>
                                <div class="dt-secondary">Plan: Post-Surgical Recovery</div>
                            </td>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user-md" style="color:#A78BFA;"></i> Specialist Referral</div>
                                <div class="dt-secondary">Routing to Orthopedics Queue</div>
                            </td>
                            <td>
                                <span class="badge bg-warning">NORMAL</span>
                                <div style="font-size:0.75rem; color:#94A3B8; font-weight:800; margin-top:8px;">OVERDUE: 2 DAYS</div>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#0F172A;">Track <i class="fas fa-arrow-right"></i></button>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <div class="dt-primary"><i class="fas fa-user-circle" style="color:#94A3B8;"></i> Suresh K</div>
                                <div class="dt-secondary">Plan: Hypertension Baseline</div>
                            </td>
                            <td>
                                <div class="dt-primary"><i class="fas fa-chart-line" style="color:#10B981;"></i> Monitoring Alert</div>
                                <div class="dt-secondary">Trigger: BP trend upward detected by AI</div>
                            </td>
                            <td>
                                <span class="badge bg-info">ESCALATION</span>
                                <div style="font-size:0.75rem; color:#94A3B8; font-weight:800; margin-top:8px;">REVIEW PENDING</div>
                            </td>
                            <td>
                                <button class="btn btn-sm" style="background:#0F172A;">Assess <i class="fas fa-arrow-right"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Care Plan Detail Demonstration -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-file-medical-alt"></i> Continuous Care Plan (Rahul Kumar)</div>
            </div>
            <div class="panel-body">
                
                <div class="cp-box">
                    <div class="cp-title">
                        <span><i class="fas fa-shield-alt" style="color:#3B82F6;"></i> Standard Diabetes Care Protocol</span>
                        <span class="badge bg-success">ACTIVE</span>
                    </div>
                    
                    <div class="section-header">Target Goals</div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:white; font-weight:700;">
                        <span>Target: HbA1c &lt; 7.0%</span>
                        <span style="color:#94A3B8;">Current: 7.8%</span>
                    </div>
                    <div class="prog-bar"><div class="prog-fill" style="width:65%;"></div></div>
                    
                    <div class="section-header" style="margin-top:20px; border-color:#8B5CF6; color:#A78BFA;">Clinical Interventions</div>
                    
                    <!-- Intervention: Medication -->
                    <div class="intervention-item" style="border-color:#334155; border-left:3px solid #F59E0B;">
                        <i class="fas fa-pills" style="margin-top:2px; color:#F59E0B;"></i>
                        <div>
                            <div style="font-weight:800; font-size:0.9rem; color:white;">Medication Plan Maintenance</div>
                            <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Metformin 500mg BD. Tied strictly to Prescription Domain.</div>
                            <div style="margin-top:6px;"><span class="badge" style="background:#1E293B; border-color:#334155;">PHARMACY LINKED</span></div>
                        </div>
                    </div>
                    
                    <!-- Intervention: Diagnostics -->
                    <div class="intervention-item" style="border-color:#334155; border-left:3px solid #38BDF8;">
                        <i class="fas fa-flask" style="margin-top:2px; color:#38BDF8;"></i>
                        <div>
                            <div style="font-weight:800; font-size:0.9rem; color:white;">Diagnostic Monitoring</div>
                            <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Order Fasting Blood Glucose every 6 weeks.</div>
                            <div style="margin-top:6px;"><span class="badge bg-warning">DUE NEXT WEEK</span></div>
                        </div>
                    </div>
                    
                    <!-- Intervention: Follow-up -->
                    <div class="intervention-item" style="border-color:#334155; border-left:3px solid #10B981;">
                        <i class="fas fa-calendar-check" style="margin-top:2px; color:#10B981;"></i>
                        <div>
                            <div style="font-weight:800; font-size:0.9rem; color:white;">Clinical Follow-Up Action</div>
                            <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Teleconsultation to review numbers. Triggering appointment engine.</div>
                            <div style="margin-top:6px;"><span class="badge bg-danger">OVERDUE</span></div>
                        </div>
                    </div>
                    
                </div>
                
                <div style="display:flex; justify-content:space-between; gap:12px;">
                    <button class="btn" style="flex:1;"><i class="fas fa-file-medical"></i> Clinical Review Note</button>
                    <button class="btn btn-primary" style="flex:1;"><i class="fas fa-plus"></i> Issue New Referral</button>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
