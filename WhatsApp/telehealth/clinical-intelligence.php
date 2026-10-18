<?php
require_once 'helper.php';
// Simulated Clinical Intelligence, AI-Assisted Decision Support & Safety Center
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Intelligence Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized AI Navbar */
        .ai-nav { background: #2E1065; border-bottom: 2px solid #4C1D95; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .ai-nav .navbar-brand { color: #C4B5FD; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .ai-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .ai-nav-links::-webkit-scrollbar { display: none; }
        .ai-nav-links a { color: #A78BFA; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .ai-nav-links a:hover { color: white; background: #5B21B6; }
        .ai-nav-links a.active { color: white; background: #6D28D9; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Two Column Layout */
        .queue-col { flex: 1.2; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
        .detail-col { flex: 1.8; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; cursor: pointer; }
        .data-table tr.selected { background: rgba(139, 92, 246, 0.1); border-left: 3px solid #8B5CF6; }
        .data-table tr.selected td:first-child { padding-left: 13px; }

        .dt-primary { font-weight: 800; color: white; display: flex; align-items: center; gap: 8px; }
        .dt-secondary { font-size: 0.75rem; color: #94A3B8; margin-top: 4px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-danger { background: rgba(239, 68, 68, 0.1); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.1); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        /* AI Detail View */
        .ai-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #334155; }
        
        .ai-source-box { background: #0F172A; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .ai-source-title { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-bottom: 12px; display: flex; justify-content: space-between; }
        
        .ai-finding { background: rgba(239, 68, 68, 0.05); border-left: 3px solid #EF4444; padding: 16px; border-radius: 4px; margin-bottom: 24px; }
        .ai-finding-title { font-weight: 900; color: #FCA5A5; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .ai-finding-desc { font-size: 0.95rem; color: #E2E8F0; line-height: 1.5; }

        /* Human Review Actions */
        .review-box { background: #020617; border: 1px solid #334155; border-radius: 8px; padding: 20px; }
        .review-title { font-size: 0.9rem; font-weight: 900; color: white; margin-bottom: 16px; }
        
        .review-actions { display: flex; gap: 12px; margin-bottom: 16px; }
        .rad-btn { flex: 1; padding: 12px; border: 1px solid #475569; border-radius: 6px; background: #1E293B; color: white; font-weight: 800; text-align: center; cursor: pointer; transition: 0.2s; }
        .rad-btn:hover { background: #334155; }
        .rad-btn.accept:hover { background: rgba(16, 185, 129, 0.1); border-color: #10B981; color: #34D399; }
        .rad-btn.override:hover { background: rgba(245, 158, 11, 0.1); border-color: #F59E0B; color: #FBBF24; }
        .rad-btn.dismiss:hover { background: rgba(100, 116, 139, 0.1); border-color: #94A3B8; color: #CBD5E1; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: #8B5CF6; border-color: #7C3AED; color: white; }
        .btn-primary:hover { background: #7C3AED; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="ai-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-brain"></i> CLINICAL INTELLIGENCE CENTER
        </div>
        <div class="ai-nav-links">
            <a href="#"  class="active">Safety Queue</a>
            <a href="#" >OCR Validation</a>
            <a href="#" >Model Evaluations</a>
            <a href="#" >Governance & Rules</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #A78BFA;"> Clinical Reviewer
            </div>
        </div>
    </div>
</div>

<div class="app-container">

    <!-- Queue Column -->
    <div class="queue-col">
        <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-inbox" style="color:#A78BFA;"></i> AI Safety Queue</h1>
        <div style="font-size:0.85rem; color:#94A3B8; margin-top:-16px; margin-bottom:8px;">Human-in-the-loop validation requests.</div>

        <div class="panel">
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Signal Context</th>
                            <th>Priority</th>
                            <th>State</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="selected">
                            <td>
                                <div class="dt-primary">Rahul (AP-9922)</div>
                                <div class="dt-secondary">Drug Interaction Risk</div>
                            </td>
                            <td><span class="badge bg-danger">HIGH</span></td>
                            <td><span class="badge bg-purple">PENDING</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div class="dt-primary">Anita (OCR-81)</div>
                                <div class="dt-secondary">OCR Low Confidence</div>
                            </td>
                            <td><span class="badge bg-warning">MEDIUM</span></td>
                            <td><span class="badge bg-purple">PENDING</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div class="dt-primary">Suresh (PM-102)</div>
                                <div class="dt-secondary">Duplicate Therapy</div>
                            </td>
                            <td><span class="badge bg-danger">HIGH</span></td>
                            <td><span class="badge bg-purple">PENDING</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detail Column -->
    <div class="detail-col">
        <div class="panel" style="min-height: 600px;">
            <div class="panel-body">
                
                <div class="ai-header">
                    <div>
                        <h2 style="margin:0; font-size:1.4rem; font-weight:900; color:white;">Potential Medication Interaction</h2>
                        <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">
                            Patient: <strong>Rahul Kumar (MRN-8842)</strong> | Consultation: AP-9922
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="badge bg-danger" style="font-size: 0.8rem; padding: 6px 12px;"><i class="fas fa-exclamation-triangle"></i> HIGH PRIORITY</div>
                        <div style="font-size:0.75rem; color:#64748B; margin-top:6px; font-family:monospace;">Model: RxSafe-v4.2.1 | Conf: 96%</div>
                    </div>
                </div>

                <div class="ai-source-box">
                    <div class="ai-source-title">
                        <span><i class="fas fa-database"></i> Source Traceability</span>
                        <a href="#"  style="color:#A78BFA; text-decoration:none;"><i class="fas fa-external-link-alt"></i> View Original Records</a>
                    </div>
                    <div style="display:flex; gap:16px;">
                        <div style="flex:1; background:#1E293B; padding:12px; border-radius:6px; border:1px solid #334155;">
                            <div style="font-size:0.7rem; color:#94A3B8; font-weight:800; margin-bottom:4px;">Draft Prescription (Current Session)</div>
                            <div style="font-weight:700; color:white;">Warfarin 5mg</div>
                            <div style="font-size:0.8rem; color:#CBD5E1;">1 tab Oral Daily</div>
                        </div>
                        <div style="display:flex; align-items:center; color:#64748B;"><i class="fas fa-plus"></i></div>
                        <div style="flex:1; background:#1E293B; padding:12px; border-radius:6px; border:1px solid #334155;">
                            <div style="font-size:0.7rem; color:#94A3B8; font-weight:800; margin-bottom:4px;">Active Medication (Timeline: 12 Aug)</div>
                            <div style="font-weight:700; color:white;">Aspirin 81mg</div>
                            <div style="font-size:0.8rem; color:#CBD5E1;">Recorded by: <?php echo htmlspecialchars($docName); ?></div>
                        </div>
                    </div>
                </div>

                <div class="ai-finding">
                    <div class="ai-finding-title"><i class="fas fa-robot"></i> AI Engine Finding</div>
                    <div class="ai-finding-desc">
                        Major interaction detected between <strong>Warfarin</strong> and <strong>Aspirin</strong>. 
                        Concurrent use increases the risk of significant bleeding. 
                        <br><br>
                        <em>Evidence Source: Standard Clinical Interaction Database (v2026.4)</em>
                    </div>
                </div>

                <!-- Human Review Enforcer -->
                <div class="review-box">
                    <div class="review-title">Clinician Decision Checkpoint</div>
                    
                    <div class="review-actions">
                        <div class="rad-btn accept"><i class="fas fa-check-circle"></i> Accept & Modify Rx</div>
                        <div class="rad-btn override"><i class="fas fa-shield-alt"></i> Override (Clinical Reason)</div>
                        <div class="rad-btn dismiss"><i class="fas fa-times-circle"></i> Dismiss (False Positive)</div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.8rem; font-weight:800; color:#94A3B8; margin-bottom:8px; text-transform:uppercase;">Clinical Audit Rationale (Required for Override):</label>
                        <textarea placeholder="Enter clinical justification for overriding or dismissing this AI safety signal..." style="width:100%; height:80px; background:#0F172A; border:1px solid #334155; color:white; padding:12px; border-radius:6px; resize:vertical; font-family:inherit;"></textarea>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.75rem; color:#64748B;"><i class="fas fa-lock"></i> Decision will be cryptographically logged to the Audit Engine.</span>
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Commit Clinical Decision</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

</body>
</html>
