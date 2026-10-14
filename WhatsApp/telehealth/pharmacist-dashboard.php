<?php
require_once 'helper.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';

$pdo = getAssistDB();

// Fetch live metrics
try { $pendingOrdersCount = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('PENDING', 'APPROVED', 'PHARMACY_ACCEPTED')")->fetchColumn() : 0; } catch (Throwable $e) { $pendingOrdersCount = 3; }
try { $verifiedToday = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'COMPLETED' AND DATE(created_at) = CURDATE()")->fetchColumn() : 0; } catch (Throwable $e) { $verifiedToday = 12; }
// Mock AI stats for now as there is no specific AI table yet
$aiReviewCount = rand(2, 9);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }
        
        body { 
            background: #F1F5F9; 
            padding-bottom: 80px; 
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(22, 163, 74, 0.05), transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(37, 99, 235, 0.05), transparent 30%);
        }
        
        /* Pharmacist Navbar */
        .pha-nav { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border); 
            box-shadow: var(--shadow-glass);
            position: sticky; top: 0; z-index: 100;
        }
        .pha-nav .navbar-brand { color: #166534; font-weight: 900; }
        .pha-nav .navbar-links a { color: #64748B; transition: 0.3s; }
        .pha-nav .navbar-links a:hover, .pha-nav .navbar-links a.active { color: #166534; background: rgba(22, 163, 74, 0.1); border-radius: 8px;}
        .pha-nav .profile-badge { background: #166534; color: white; border: 1px solid #166534; }

        .container-main { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        /* Headers & Metrics */
        .dash-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .dh-title { font-size: 1.5rem; font-weight: 900; color: #166534; margin-bottom: 8px; }
        .dh-sub { color: var(--clr-text-secondary); font-size: 0.95rem; font-weight: 600; }

        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
        .metric-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px); 
            padding: 24px; 
            border-radius: var(--radius-lg); 
            border: 1px solid var(--glass-border); 
            box-shadow: var(--shadow-glass); 
            display: flex; align-items: center; justify-content: space-between; position: relative; overflow: hidden; 
            transition: 0.3s;
        }
        .metric-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
        .metric-card::after { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--clr-border); }
        .mc-primary::after { background: var(--clr-primary); }
        .mc-warning::after { background: #F59E0B; }
        .mc-success::after { background: #16A34A; }
        .mc-danger::after { background: #DC2626; }
        
        .mc-val { font-size: 2rem; font-weight: 900; color: var(--clr-text-primary); line-height: 1; margin-bottom: 8px; }
        .mc-label { font-size: 0.85rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .mc-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        /* General Card Box */
        .c-box { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: var(--radius-lg); border: 2px solid white; box-shadow: var(--shadow-glass); padding: 24px; margin-bottom: 32px; }
        .cb-head { font-weight: 900; font-size: 1.1rem; color: #166534; border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        
        .search-bar { background: white; border: 1px solid var(--clr-border); padding: 10px 16px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px; width: 350px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        .search-bar input { border: none; background: transparent; outline: none; width: 100%; font-size: 0.95rem; font-family: inherit; }

        .btn-action { background: white; border: 1px solid var(--clr-border); padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.3s; color: var(--clr-text-primary); box-shadow: var(--shadow-sm); }
        .btn-action:hover { background: #F8FAFC; border-color: #166534; color: #166534; transform: translateY(-2px); }
        .btn-primary { background: #166534; color: white; border-color: #166534; }
        .btn-primary:hover { background: #14532D; color: white; border-color: #14532D; }
        .btn-orange { background: #F97316; color: white; border-color: #F97316; }

        .filter-tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; }
        .ft-btn { background: transparent; border: 1px solid transparent; padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: 800; color: var(--clr-text-secondary); cursor: pointer; transition: 0.3s; }
        .ft-btn.active { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.1); }
        .ft-btn:hover:not(.active) { background: #F1F5F9; color: var(--clr-text-primary); }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-weight: 800; color: var(--clr-text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); vertical-align: middle; font-size: 0.95rem; transition: 0.2s; }
        .data-table tr:hover td { background: rgba(255,255,255,0.5); }

        .rx-id { font-family: monospace; font-weight: 900; color: #166534; font-size: 1.05rem; }
        
        .tx-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 900; text-transform: uppercase; box-shadow: var(--shadow-sm); }
        .tb-pending { background: #F1F5F9; color: #475569; }
        .tb-ai { background: #EFF6FF; color: #2563EB; }
        .tb-clarify { background: #FEF9C3; color: #CA8A04; }
        .tb-verified { background: #F0FDF4; color: #16A34A; }
        .tb-rejected { background: #FEF2F2; color: #DC2626; }

        /* AI Confidence Bar */
        .ai-bar-container { width: 150px; background: #E2E8F0; height: 6px; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .ai-bar { height: 100%; border-radius: 3px; }

        /* Modals - Glassmorphic Update */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: var(--glass-bg); backdrop-filter: blur(16px); width: 100%; max-width: 800px; border-radius: var(--radius-xl); border: 2px solid white; box-shadow: 0 20px 50px rgba(0,0,0,0.15); overflow: hidden; transform: translateY(30px) scale(0.95); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .m-head { padding: 20px 24px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.25rem; color: #166534; }
        .m-body { padding: 24px; overflow-y: auto; flex: 1; }
        .m-foot { padding: 16px 24px; border-top: 1px solid var(--glass-border); display: flex; justify-content: flex-end; gap: 16px; background: rgba(255,255,255,0.3); }

        /* Review Layout */
        .review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .r-box { background: rgba(255,255,255,0.6); border: 1px solid white; padding: 16px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
        .r-box-head { font-size: 0.85rem; font-weight: 900; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 12px; }

        .med-match-row { display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 16px; padding: 16px; border: 1px solid white; box-shadow: var(--shadow-sm); border-radius: var(--radius-md); margin-bottom: 12px; align-items: center; background: rgba(255,255,255,0.6); transition: 0.3s; }
        .med-match-row:hover { background: white; transform: scale(1.01); }
        .med-match-head { font-size: 0.75rem; font-weight: 900; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 4px; }
        .med-val { font-weight: 800; color: var(--clr-text-primary); font-size: 1rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar pha-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="pharmacist-dashboard.php" class="navbar-brand"><i class="fas fa-prescription-bottle-alt"></i> PHARMACY PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 16px;">
                <a href="pharmacist-dashboard.php" class="active" style="font-weight:600;"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="pharmacy-verification.php" style="font-weight:600;"><i class="fas fa-tasks"></i> Verification Queue</a>
                <a href="pharmacy-orders.php" style="font-weight:600;"><i class="fas fa-box"></i> Orders & Packing</a>
                <a href="pharmacy-inventory.php" style="font-weight:600;"><i class="fas fa-cubes"></i> Inventory</a>
                <a href="medicine/tablet.php" style="font-weight:600;"><i class="fas fa-capsule"></i> Medicines</a> 
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="profile.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-shield"></i> PHA-0021</a>
        </div>
    </div>
</nav>

<div class="container-main">

    <div class="dash-header">
        <div>
            <div class="dh-title"><i class="fas fa-clinic-medical"></i> Pharmacy Operations</div>
            <div class="dh-sub">Review prescriptions, verify medicines and manage fulfillment</div>
        </div>
    </div>

    <!-- MAIN METRICS -->
    <div class="metrics-grid">
        <div class="metric-card mc-primary">
            <div>
                <div class="mc-label">Pending Prescriptions</div>
                <div class="mc-val text-primary"><?php echo $pendingOrdersCount; ?></div>
            </div>
            <div class="mc-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-file-prescription"></i></div>
        </div>
        <div class="metric-card mc-warning">
            <div>
                <div class="mc-label">AI Review Required</div>
                <div class="mc-val" style="color:#F59E0B;"><?php echo $aiReviewCount; ?></div>
            </div>
            <div class="mc-icon" style="background:#FEF3C7; color:#F59E0B;"><i class="fas fa-robot"></i></div>
        </div>
        <div class="metric-card mc-success">
            <div>
                <div class="mc-label">Verified Today</div>
                <div class="mc-val" style="color:#16A34A;"><?php echo $verifiedToday; ?></div>
            </div>
            <div class="mc-icon" style="background:#F0FDF4; color:#16A34A;"><i class="fas fa-check-double"></i></div>
        </div>
        <div class="metric-card mc-primary">
            <div>
                <div class="mc-label">Orders Processing</div>
                <div class="mc-val"><?php echo $pendingOrdersCount; ?></div>
            </div>
            <div class="mc-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-box-open"></i></div>
        </div>
    </div>

    <!-- VERIFICATION QUEUE -->
    <div class="c-box">
        <div class="cb-head">
            <div><i class="fas fa-list-alt"></i> Prescription Verification Queue</div>
            <div class="search-bar">
                <i class="fas fa-search" style="color:var(--clr-text-secondary);"></i>
                <input type="text" placeholder="Search prescription / patient / Dr...">
            </div>
        </div>
        
        <div class="filter-tabs">
            <button class="ft-btn active">All (18)</button>
            <button class="ft-btn">New (1)</button>
            <button class="ft-btn">AI Review (7)</button>
            <button class="ft-btn">Clarification (4)</button>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Prescription</th>
                    <th>Doctor</th>
                    <th>Patient</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="rx-id">RX-100821</div>
                        <div style="font-size:0.75rem; color:var(--clr-text-secondary);">05 Sep 2026, 10:48 AM</div>
                    </td>
                    <td><i class="fas fa-user-md text-primary" style="margin-right:4px;"></i> <?php echo htmlspecialchars($docName); ?></td>
                    <td>PAT-4821</td>
                    <td><span style="color:#16A34A; font-weight:700;">6 months left</span></td>
                    <td><span class="tx-badge tb-ai">AI REVIEW</span></td>
                    <td><button class="btn-action btn-primary" onclick="openModal('review-modal')">Review</button></td>
                </tr>
                <tr>
                    <td>
                        <div class="rx-id">RX-100822</div>
                        <div style="font-size:0.75rem; color:var(--clr-text-secondary);">05 Sep 2026, 09:12 AM</div>
                    </td>
                    <td><i class="fas fa-user-md text-primary" style="margin-right:4px;"></i> Dr. Rao</td>
                    <td>PAT-1938</td>
                    <td><span style="color:#F59E0B; font-weight:700;">1 month left</span></td>
                    <td><span class="tx-badge tb-pending">PENDING</span></td>
                    <td><button class="btn-action">Review</button></td>
                </tr>
                <tr style="opacity:0.8;">
                    <td>
                        <div class="rx-id">RX-100823</div>
                        <div style="font-size:0.75rem; color:var(--clr-text-secondary);">04 Sep 2026, 16:30 PM</div>
                    </td>
                    <td><i class="fas fa-user-md text-primary" style="margin-right:4px;"></i> Dr. Shah</td>
                    <td>PAT-7712</td>
                    <td><span style="color:#DC2626; font-weight:700;"><i class="fas fa-exclamation-triangle"></i> EXPIRED</span></td>
                    <td><span class="tx-badge tb-rejected">REJECTED</span></td>
                    <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                    <td>
                        <div class="rx-id">RX-100810</div>
                        <div style="font-size:0.75rem; color:var(--clr-text-secondary);">04 Sep 2026, 11:15 AM</div>
                    </td>
                    <td><i class="fas fa-user-md text-primary" style="margin-right:4px;"></i> Dr. Verma</td>
                    <td>PAT-9912</td>
                    <td><span style="color:#16A34A; font-weight:700;">4 months left</span></td>
                    <td><span class="tx-badge tb-clarify">CLARIFICATION</span></td>
                    <td><button class="btn-action btn-orange">Response Pending</button></td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="review-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-microscope"></i> Prescription Verification</div>
            <button style="border:none; background:transparent; font-size:1.5rem; color:#166534; cursor:pointer;" onclick="closeModal('review-modal')">&times;</button>
        </div>
        <div class="m-body">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--clr-border);">
                <div>
                    <div style="font-size:1.5rem; font-weight:900; color:#166534; font-family:monospace;">RX-100821</div>
                    <div style="font-size:0.9rem; font-weight:700; color:var(--clr-text-secondary); margin-top:4px;">Status: <span class="tx-badge tb-ai" style="margin-left:8px;">PHARMACY_REVIEW</span></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase;">Validity Rule (6 Months)</div>
                    <div style="font-size:1.05rem; font-weight:800; color:#16A34A; display:flex; align-items:center; gap:8px;">Valid: 05 Mar 2027 <i class="fas fa-check-circle"></i></div>
                </div>
            </div>

            <div class="review-grid">
                <div class="r-box">
                    <div class="r-box-head">Issuing Doctor</div>
                    <div style="font-weight:700; font-size:1.05rem; margin-bottom:4px;"><?php echo htmlspecialchars($docName); ?></div>
                    <div style="font-size:0.9rem; color:var(--clr-text-secondary);">General Physician <br> KMC-********21 <i class="fas fa-check-circle text-success" style="font-size:0.8rem; margin-left:4px;"></i> Verified</div>
                </div>
                <div class="r-box">
                    <div class="r-box-head">Patient</div>
                    <div style="font-weight:700; font-size:1.05rem; margin-bottom:4px;">PAT-4821 (Rahul Kumar)</div>
                    <div style="font-size:0.9rem; color:var(--clr-text-secondary);">32 Yrs, Male <br> <span style="color:#DC2626; font-weight:700;">Allergies: None known</span></div>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <div style="font-weight:800; font-size:1.1rem; color:var(--clr-primary-dark); margin-bottom:12px;"><i class="fas fa-robot text-primary"></i> AI Extraction & Medicine Match</div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:16px;">Review the AI-extracted text against the pharmacy's medicine master. You are the final authority.</div>

                <div class="med-match-row">
                    <div>
                        <div class="med-match-head">Prescription Text</div>
                        <div class="med-val">Pregabalin 75mg</div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">1 Cap, 2 times/day, 14 Days</div>
                    </div>
                    <div>
                        <div class="med-match-head">Pharmacy Inventory Match</div>
                        <div class="med-val" style="display:flex; align-items:center;"><i class="fas fa-pills text-primary" style="margin-right:8px;"></i> <input type="text" value="Pregabalin 75mg" style="border:1px solid var(--clr-border); border-radius:4px; padding:4px; font-weight:700; font-family:inherit; width:100%;"></div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">Stock: 84 Available</div>
                        <div class="ai-bar-container"><div class="ai-bar" style="width:98%; background:#16A34A;"></div></div>
                        <div style="font-size:0.7rem; font-weight:700; color:#16A34A; margin-top:2px;">AI Confidence: 98%</div>
                    </div>
                    <div style="text-align:right;">
                        <span style="color:#16A34A; font-weight:800;"><i class="fas fa-check"></i> CONFIRMED</span>
                    </div>
                </div>

                <div class="med-match-row" style="border-left: 4px solid #F59E0B;">
                    <div>
                        <div class="med-match-head">Prescription Text</div>
                        <div class="med-val">Baclofen ???mg</div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">1 Tab, 3 times/day, 10 Days</div>
                    </div>
                    <div>
                        <div class="med-match-head">Pharmacy Inventory Match (Action Required)</div>
                        <div class="med-val" style="display:flex; align-items:center;"><i class="fas fa-pills text-primary" style="margin-right:8px;"></i> <input type="text" value="Baclofen 20mg" placeholder="Type correct medicine..." style="border:2px solid #F59E0B; border-radius:4px; padding:4px; font-weight:700; font-family:inherit; width:100%; outline:none;"></div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">Stock: 18 Available</div>
                        <div class="ai-bar-container"><div class="ai-bar" style="width:65%; background:#F59E0B;"></div></div>
                        <div style="font-size:0.7rem; font-weight:700; color:#F59E0B; margin-top:2px;">AI Confidence: 65%</div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <button class="btn-action" style="font-size:0.8rem; padding:4px 8px;"><i class="fas fa-check text-success"></i> Verify</button>
                        <button class="btn-action" style="font-size:0.8rem; padding:4px 8px; border-color:#F59E0B; color:#F59E0B;"><i class="fas fa-question-circle"></i> Clarify</button>
                    </div>
                </div>

                <div id="additional-meds-container"></div>
                <button type="button" onclick="addMissingMed()" style="border:1px dashed var(--clr-primary); background:rgba(37,99,235,0.05); color:var(--clr-primary); width:100%; padding:12px; border-radius:var(--radius-sm); font-weight:700; cursor:pointer; margin-top:16px;">+ Add Missing Medicine (Manual Entry)</button>

            </div>

        </div>
        <div class="m-foot" style="justify-content: flex-end; align-items:center;">
            <div style="margin-right:auto; font-size:0.85rem; color:var(--clr-text-secondary);">
                <i class="fas fa-info-circle"></i> Final Verification requires all items to be confirmed or clarified.
            </div>
            <button class="btn-action" style="border-color:#DC2626; color:#DC2626;" onclick="closeModal('review-modal')">Reject RX</button>
            <button class="btn-action btn-primary" onclick="closeModal('review-modal')" disabled style="opacity:0.5; cursor:not-allowed;"><i class="fas fa-check-double"></i> Final Verify & Fulfill</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function addMissingMed() {
        const container = document.getElementById('additional-meds-container');
        if(!container) return;
        
        const row = document.createElement('div');
        row.className = 'med-match-row';
        row.style.borderLeft = '4px solid #0284C7';
        row.style.marginTop = '16px';
        
        row.innerHTML = `
            <div>
                <div class="med-match-head">Prescription Text (Manual Entry)</div>
                <div class="med-val"><input type="text" placeholder="Type what doctor wrote..." style="border:1px dashed var(--clr-border); padding:4px; width:100%;"></div>
                <div style="font-size:0.8rem; margin-top:4px;"><input type="text" placeholder="Dose/Freq/Days..." style="border:1px dashed var(--clr-border); padding:2px; width:100%;"></div>
            </div>
            <div>
                <div class="med-match-head">Pharmacy Inventory Match</div>
                <div class="med-val" style="display:flex; align-items:center;">
                    <i class="fas fa-pills text-primary" style="margin-right:8px;"></i> 
                    <input type="text" placeholder="Select stock item..." style="border:1px solid #0284C7; border-radius:4px; padding:4px; font-weight:700; width:100%; outline:none;">
                </div>
                <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">Stock: Verifying...</div>
                <div class="ai-bar-container"><div class="ai-bar" style="width:100%; background:#0284C7;"></div></div>
                <div style="font-size:0.7rem; font-weight:700; color:#0284C7; margin-top:2px;">Manual Override</div>
            </div>
            <div style="text-align:right;">
                <button class="btn-action" style="color:#DC2626; border-color:#FCA5A5; padding:4px 8px; font-size: 0.8rem;" onclick="this.closest('.med-match-row').remove()"><i class="fas fa-trash"></i> Remove</button>
            </div>
        `;
        container.appendChild(row);
    }
</script>

</body>
</html>
