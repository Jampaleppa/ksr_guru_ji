<?php
require_once 'helper.php';
// Simulated Longitudinal Patient Health Record, Clinical Timeline & Care Management Center
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'timeline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Clinical Record | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(15, 23, 42, 0.6);
            --glass-border: rgba(255, 255, 255, 0.1);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.5);
            --radius-md: 12px;
            --radius-xl: 20px;
            --clr-text-glow: 0 0 10px rgba(16, 185, 129, 0.4);
        }

        body { 
            background: linear-gradient(135deg, #020617 0%, #0F172A 100%); 
            margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; 
            font-family: 'Inter', sans-serif;
            background-attachment: fixed;
        }
        
        /* Specialized Clinical Record Navbar */
        .mr-nav { 
            background: rgba(6, 78, 59, 0.6); backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(16, 185, 129, 0.2); 
            position: sticky; top: 0; z-index: 100; padding: 0 16px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); 
        }
        .mr-nav .navbar-brand { color: #A7F3D0; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .mr-nav-links { display: flex; gap: 8px; overflow-x: auto; padding: 16px 0; }
        .mr-nav-links::-webkit-scrollbar { display: none; }
        .mr-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 16px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.3s; background: rgba(5, 150, 105, 0.1); border: 1px solid transparent; }
        .mr-nav-links a:hover { color: white; background: rgba(5, 150, 105, 0.4); border-color: rgba(16, 185, 129, 0.3); transform: translateY(-1px); }
        .mr-nav-links a.active { color: white; background: linear-gradient(135deg, #10B981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 32px 24px; gap: 32px; flex-wrap: wrap; }
        
        /* Column Layout */
        .sidebar { flex: 0.8; display: flex; flex-direction: column; gap: 24px; min-width: 300px; }
        .main-timeline { flex: 1.5; display: flex; flex-direction: column; gap: 24px; min-width: 400px; }
        .visit-col { flex: 1.2; display: flex; flex-direction: column; gap: 24px; min-width: 300px; }

        /* Panel Standard - Premium Dark Glass */
        .panel { 
            background: var(--glass-bg); backdrop-filter: blur(16px);
            border-radius: var(--radius-xl); border: 1px solid var(--glass-border); 
            overflow: hidden; display: flex; flex-direction: column; 
            box-shadow: var(--shadow-glass);
            transition: all 0.3s ease;
        }
        .panel:hover { border-color: rgba(255,255,255,0.2); box-shadow: 0 15px 40px rgba(0,0,0,0.6); transform: translateY(-2px); }
        .panel-head { padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(2, 6, 23, 0.4); display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 24px; flex: 1; }
        .panel-title { font-weight: 800; font-size: 1.1rem; color: #E0E7FF; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Data Badges & Provenance */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 6px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .prov-clinician { background: rgba(59, 130, 246, 0.15); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .prov-patient { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .prov-ai { background: rgba(139, 92, 246, 0.15); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* Summary Chips */
        .health-chip { padding: 12px 16px; background: rgba(2, 6, 23, 0.5); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .health-chip:hover { background: rgba(30, 41, 59, 0.8); transform: translateX(4px); }
        .hc-title { font-weight: 800; color: white; display:flex; align-items:center; gap:8px;}
        .hc-meta { font-size: 0.75rem; color: #94A3B8; font-weight: 600; display:flex; flex-direction:column; align-items:flex-end; gap: 4px;}
        
        /* Timeline Feed */
        .timeline-feed { position: relative; padding-left: 24px; border-left: 2px solid rgba(51, 65, 85, 0.5); margin-left: 10px; }
        .tl-item { position: relative; margin-bottom: 32px; }
        .tl-dot { position: absolute; left: -31px; width: 12px; height: 12px; border-radius: 50%; background: #10B981; border: 2px solid #0F172A; box-shadow: 0 0 10px #10B981; }
        .tl-date { font-size: 0.85rem; font-weight: 900; color: #10B981; margin-bottom: 8px; text-transform: uppercase; letter-spacing:1px; }
        .tl-content { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 16px; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); transition: 0.3s; }
        .tl-content:hover { background: rgba(30, 41, 59, 0.7); }
        .tl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 14px;}
        .tl-title { font-size: 1.05rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 10px; }
        
        .tl-sub-item { display: flex; align-items: center; gap: 12px; margin-top: 10px; padding-top: 10px; font-size: 0.85rem; color: #CBD5E1; }
        .tl-sub-icon { color: #94A3B8; width: 16px; text-align: center; }

        /* Consult Panel Form */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 800; color: #94A3B8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input { width: 100%; padding: 12px 14px; background: rgba(2, 6, 23, 0.6); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 12px; font-family: inherit; font-size: 0.95rem; transition: 0.3s; outline: none; }
        .form-input:focus { border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); background: rgba(15, 23, 42, 0.8); }
        textarea.form-input { min-height: 90px; resize: vertical; }
        
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: 0.3s; border: 1px solid rgba(255,255,255,0.1); background: rgba(51, 65, 85, 0.5); color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: rgba(71, 85, 105, 0.8); transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg, #10B981, #059669); border: none; color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .btn-primary:hover { background: linear-gradient(135deg, #059669, #047857); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); transform: translateY(-2px); }
        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            .categories-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 8px;
            }
            .rx-banner {
                flex-direction: column;
                gap: 12px;
            }
            .rx-banner-content { width: 100%; }
            .section-title {
                font-size: 0.95rem;
            }
            .product-card {
                min-width: 140px;
                max-width: 140px;
            }
        }
        @media (max-width: 360px) {
            .categories-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .product-card {
                min-width: 125px;
                max-width: 125px;
            }
            .app-header {
                padding: 14px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="mr-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-heartbeat"></i> MASTER CLINICAL RECORD
        </div>
        <div class="mr-nav-links">
            <a href="?tab=timeline" class="<?php echo $activeTab == 'timeline' ? 'active' : ''; ?>">Longitudinal Timeline</a>
            <a href="?tab=conditions" class="<?php echo $activeTab == 'conditions' ? 'active' : ''; ?>">Conditions & Allergies</a>
            <a href="?tab=medications" class="<?php echo $activeTab == 'medications' ? 'active' : ''; ?>">Medication Reconciliations</a>
            <a href="?tab=careplans" class="<?php echo $activeTab == 'careplans' ? 'active' : ''; ?>">Care Plans</a>
            <a href="?tab=documents" class="<?php echo $activeTab == 'documents' ? 'active' : ''; ?>">Clinical Documents</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=68" style="width:28px; height:28px; border-radius:50%; border:2px solid #10B981;"> Patient: Rahul K.
            </div>
        </div>
    </div>
</div>

<div class="app-container">

    <!-- Patient Context Summary -->
    <div class="sidebar">
        <div>
            <h1 style="margin:0; font-size:1.4rem; font-weight:900; color:white;">Rahul Kumar</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">MRN: 9942-A1 | 34 Years | Male</div>
        </div>

        <div class="panel">
            <div class="panel-head"><div class="panel-title"><i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Allergies</div></div>
            <div class="panel-body" style="padding: 12px;">
                <div class="health-chip" style="border-left: 3px solid #EF4444;">
                    <div class="hc-title">Penicillin <span class="badge" style="background:#EF4444; color:white;">HIGH</span></div>
                    <div class="hc-meta"><span>Active</span> <span class="badge prov-clinician">Clinician Verified</span></div>
                </div>
                <div class="health-chip" style="border-left: 3px solid #F59E0B;">
                    <div class="hc-title">Peanuts </div>
                    <div class="hc-meta"><span>Active</span> <span class="badge prov-patient">Patient Reported</span></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><div class="panel-title"><i class="fas fa-clipboard-list" style="color:#3B82F6;"></i> Active Conditions</div></div>
            <div class="panel-body" style="padding: 12px;">
                <div class="health-chip" style="border-left: 3px solid #3B82F6;">
                    <div class="hc-title">Peripheral Neuropathy</div>
                    <div class="hc-meta"><span>Since: Mar 2024</span> <span class="badge prov-clinician">Clinician Verified</span></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><div class="panel-title"><i class="fas fa-pills" style="color:#8B5CF6;"></i> Current Medications</div></div>
            <div class="panel-body" style="padding: 12px;">
                <div class="health-chip" style="border-left: 3px solid #8B5CF6;">
                    <div class="hc-title">Pregabalin 75mg</div>
                    <div class="hc-meta"><span>Active</span> <span class="badge prov-clinician"><?php echo htmlspecialchars($docName); ?></span></div>
                </div>
                <div class="health-chip" style="border-left: 3px solid #8B5CF6;">
                    <div class="hc-title">Methylcobalamin</div>
                    <div class="hc-meta"><span>Active</span> <span class="badge prov-patient">Patient Reported</span></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($activeTab == 'timeline'): ?>
    <!-- Longitudinal Timeline -->
    <div class="main-timeline">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
            <h2 style="margin:0; font-size:1.3rem; font-weight:900;"><i class="fas fa-stream" style="color:#10B981;"></i> Clinical Timeline</h2>
            <div style="display:flex; gap:8px;">
                <span style="font-size:0.8rem; color:#94A3B8; font-weight:800; background:#1E293B; padding:6px 12px; border-radius:12px; border:1px solid #334155; cursor:pointer;">All Types <i class="fas fa-caret-down"></i></span>
            </div>
        </div>

        <div class="panel" style="flex:1;">
            <div class="panel-body" style="overflow-y:auto;">
                <div class="timeline-feed">
                    
                    <!-- Event 1 (Today) -->
                    <div class="tl-item">
                        <div class="tl-dot" style="background:#3B82F6; border-color:#0F172A;"></div>
                        <div class="tl-date" style="color:#3B82F6;">05 SEP 2026</div>
                        <div class="tl-content" style="border-left: 3px solid #3B82F6;">
                            <div class="tl-header">
                                <div class="tl-title"><i class="fas fa-video"></i> Video Consultation Completed</div>
                                <span class="badge prov-clinician"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></span>
                            </div>
                            <div style="font-size:0.9rem; color:#E2E8F0; margin-bottom:12px; line-height:1.5;">
                                Patient presented with left arm muscle spasticity and loss of sensation. Reviewed recent nerve conduction studies. Adjusted medication dosage and requested physiotherapy follow-up.
                            </div>
                            <!-- Nested artifacts belonging to this encounter -->
                            <div class="tl-sub-item"><i class="fas fa-stethoscope tl-sub-icon"></i> <strong>Diagnosis Updated:</strong> Peripheral Neuropathy</div>
                            <div class="tl-sub-item"><i class="fas fa-file-prescription tl-sub-icon"></i> <strong>Prescription Issued:</strong> Pregabalin 75mg (RX-2026-X84B9A2)</div>
                            <div class="tl-sub-item"><i class="fas fa-calendar-alt tl-sub-icon"></i> <strong>Follow-up Scheduled:</strong> 19 Sep 2026 (Neurology)</div>
                        </div>
                    </div>

                    <!-- Event 2 -->
                    <div class="tl-item">
                        <div class="tl-dot" style="background:#8B5CF6; border-color:#0F172A;"></div>
                        <div class="tl-date" style="color:#8B5CF6;">20 AUG 2026</div>
                        <div class="tl-content" style="border-left: 3px solid #8B5CF6;">
                            <div class="tl-header">
                                <div class="tl-title"><i class="fas fa-flask"></i> Laboratory Report Added</div>
                                <span class="badge prov-ai"><i class="fas fa-robot"></i> System Derived</span>
                            </div>
                            <div class="tl-sub-item"><i class="fas fa-file-pdf tl-sub-icon"></i> <strong>Uploaded:</strong> Comprehensive Metabolic Panel</div>
                            <div class="tl-sub-item" style="color:#FCA5A5;"><i class="fas fa-exclamation-triangle tl-sub-icon" style="color:#EF4444;"></i> <strong>AI Note:</strong> Elevated LDL detected. Requires clinician review.</div>
                        </div>
                    </div>

                    <!-- Event 3 -->
                    <div class="tl-item">
                        <div class="tl-dot" style="background:#F59E0B; border-color:#0F172A;"></div>
                        <div class="tl-date" style="color:#F59E0B;">12 MAR 2024</div>
                        <div class="tl-content" style="border-left: 3px solid #F59E0B;">
                            <div class="tl-header">
                                <div class="tl-title"><i class="fas fa-hospital"></i> Orthopedic Consultation</div>
                                <span class="badge prov-clinician">Dr. Ravi (External)</span>
                            </div>
                            <div class="tl-sub-item"><i class="fas fa-x-ray tl-sub-icon"></i> <strong>Imaging:</strong> Knee MRI Report</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Active Encounter Documentation Sandbox -->
    <div class="visit-col">
        <h2 style="margin:0; font-size:1.1rem; font-weight:900; margin-bottom:8px;"><i class="fas fa-edit" style="color:#38BDF8;"></i> Active Encounter Panel</h2>
        
        <div class="panel">
            <div class="panel-head" style="background:#020617;">
                <div class="panel-title"><i class="fas fa-file-medical"></i> Draft Clinical Note</div>
                <div class="badge bg-warning">IN_PROGRESS</div>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="form-label">Subjective / Chief Complaint</label>
                    <textarea class="form-input"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Objective / Vitals</label>
                    <textarea class="form-input" style="min-height:50px;"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Assessment / Diagnosis</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" class="form-input" placeholder="Search ICD-10 or custom...">
                        <button class="btn"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                
                <hr style="border:0; border-top:1px solid #334155; margin: 20px 0;">
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="font-size:0.75rem; color:#94A3B8; text-align:justify;">
                        <i class="fas fa-lock" style="color:#F59E0B;"></i> <strong>Governance Rule:</strong> Finalizing this note will permanently lock it into the Longitudinal Timeline as Version 1. Any future changes will legally require an "Amendment Request" workflow.
                    </div>
                    <button class="btn btn-primary" style="width:100%;"><i class="fas fa-save"></i> Finalize Encounter & Sign</button>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Sub-module Views -->
    <div class="main-timeline" style="flex: 2.7;">
        <div class="panel" style="height: 100%; display: flex; align-items: center; justify-content: center;">
            <?php
            $tabTitles = [
                'conditions' => 'Conditions & Allergies',
                'medications' => 'Medication Reconciliations',
                'careplans' => 'Care Plans',
                'documents' => 'Clinical Documents'
            ];
            $displayTitle = isset($tabTitles[$activeTab]) ? $tabTitles[$activeTab] : ucfirst($activeTab);
            ?>
            <div style="text-align: center; color: #64748B;">
                <i class="fas fa-cogs" style="font-size: 3rem; margin-bottom: 16px;"></i>
                <h2>Module Active: <?php echo htmlspecialchars($displayTitle); ?></h2>
                <p>Detailed view panels for <?php echo htmlspecialchars(strtolower($displayTitle)); ?> data are locked under strict Data Governance APIs.</p>
                <a href="?tab=timeline" class="btn btn-primary" style="margin-top:20px; display:inline-flex;">Return to Timeline</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
