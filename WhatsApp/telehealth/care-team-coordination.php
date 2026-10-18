<?php
require_once 'helper.php';
// Simulated Care Coordination, Referral, Case Management & Multi-Specialty Care Team Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Care Coordination & Multi-Specialty Teams | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Coordination Navbar */
        .coord-nav { background: #4C1D95; border-bottom: 2px solid #5B21B6; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .coord-nav .navbar-brand { color: #DDD6FE; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .coord-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .coord-nav-links::-webkit-scrollbar { display: none; }
        .coord-nav-links a { color: #C4B5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .coord-nav-links a:hover { color: white; background: #6D28D9; }
        .coord-nav-links a.active { color: white; background: #7C3AED; box-shadow: 0 0 10px rgba(124, 58, 237, 0.5); }

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
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-purple { background: #7C3AED; border-color: #6D28D9; color: white; }
        .btn-purple:hover { background: #6D28D9; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }

        .team-member { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; padding: 8px; background: #0F172A; border-radius: 6px; border: 1px solid #334155; }
        .team-av { width: 32px; height: 32px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.8rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="coord-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-sitemap"></i> CARE COORDINATION & MULTI-SPECIALTY TEAMS
        </div>
        <div class="coord-nav-links">
            <a href="#"  class="active">Longitudinal Care Plans</a>
            <a href="#" >Referral Network</a>
            <a href="#" >Tumor / Case Boards</a>
            <a href="#" >Chronic Disease Tracking</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-hand-holding-medical" style="color:#C4B5FD;"></i> Lead Care Coordinator
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-users-medical" style="color:#A78BFA;"></i> Multi-Specialty Care Journeys</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Connecting isolated clinical events (Consults, Labs, Rxs) into unified, goal-oriented Care Plans with cross-department communication.</div>
        </div>
        <div>
            <button class="btn btn-purple"><i class="fas fa-plus"></i> Initiate Multi-Disciplinary Case</button>
        </div>
    </div>

    <!-- Active Coordination KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
            <div class="kpi-val">412</div>
            <div class="kpi-lbl">Active Care Plans</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">28</div>
            <div class="kpi-lbl">Pending Inter-Specialty Referrals</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">84%</div>
            <div class="kpi-lbl">Patient Plan Adherence Goal</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #EF4444; background:rgba(239,68,68,0.05);">
            <div class="kpi-val" style="color:#FCA5A5;">2</div>
            <div class="kpi-lbl" style="color:#FCA5A5;">Overdue Specialist Reviews</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Referral Escalation Queue -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-share-square"></i> Integrated Referral & Handoff Queue</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient / Referring Dr</th>
                            <th>Target Specialty</th>
                            <th>Status / Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Patient: Singh, R.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">From: Dr. A. Kumar (General Med)</div>
                            </td>
                            <td><span class="badge bg-purple"><i class="fas fa-heartbeat"></i> CARDIOLOGY</span></td>
                            <td>
                                <span class="badge bg-warning">PENDING ACCEPTANCE</span>
                                <div style="font-size:0.75rem; color:#FBBF24; margin-top:4px;">Abnormal ECG Flagged</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Patient: Verma, A.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">From: AI Virtual Triage Alg.</div>
                            </td>
                            <td><span class="badge bg-blue"><i class="fas fa-brain"></i> NEUROLOGY</span></td>
                            <td>
                                <span class="badge bg-success">ACCEPTED - SCHEDULED</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Appt: Sep 06, 14:00</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:900; color:white;">Patient: Patel, N.</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">From: Cross-Hospital Partner B</div>
                            </td>
                            <td><span class="badge bg-critical"><i class="fas fa-bone"></i> ORTHOPEDICS (URGENT)</span></td>
                            <td>
                                <span class="badge bg-critical">WAITING SPECIALIST</span>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Post-Trauma Consult</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Case Management / Tumor Board Example -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-chalkboard-teacher"></i> Multi-Specialty Tumor/Case Board</div>
                <button class="btn btn-sm"><i class="fas fa-eye"></i> View Case File</button>
            </div>
            <div class="panel-body">
                <div style="background:#0F172A; border:1px solid #334155; border-radius:8px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <div style="font-size:1.1rem; font-weight:900; color:white;">Case: CB-29910-ONC</div>
                            <div style="font-size:0.8rem; color:#94A3B8;">Oncology Review Board (Sep 5)</div>
                        </div>
                        <span class="badge bg-warning">MEETING TOMORROW</span>
                    </div>

                    <div style="margin-bottom:16px;">
                        <div style="font-size:0.8rem; color:#94A3B8; text-transform:uppercase; font-weight:800; margin-bottom:8px;">Assigned Care Team</div>
                        
                        <div class="team-member">
                            <div class="team-av" style="background:#10B981;">SG</div>
                            <div>
                                <div style="font-weight:800; font-size:0.85rem; color:white;">Dr. S. Gupta</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">Primary Attending</div>
                            </div>
                        </div>
                        <div class="team-member">
                            <div class="team-av" style="background:#8B5CF6;">MR</div>
                            <div>
                                <div style="font-weight:800; font-size:0.85rem; color:white;">Dr. M. Rathod</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">Medical Oncologist</div>
                            </div>
                        </div>
                        <div class="team-member">
                            <div class="team-av" style="background:#3B82F6;">JN</div>
                            <div>
                                <div style="font-weight:800; font-size:0.85rem; color:white;">Dr. J. Nanda</div>
                                <div style="font-size:0.75rem; color:#94A3B8;">Radiologist (Interpreting MRI)</div>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px solid #334155; padding-top:12px; display:flex; gap:8px;">
                        <button class="btn btn-sm btn-purple" style="flex:1;"><i class="fas fa-video"></i> Join Board Meeting</button>
                        <button class="btn btn-sm" style="flex:1;"><i class="fas fa-folder-open"></i> Attach Lab Results</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
