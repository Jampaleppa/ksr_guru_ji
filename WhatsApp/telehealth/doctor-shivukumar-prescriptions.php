<?php
require_once 'helper.php';
// Pull from session if available
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'Dr. Shivukumar Rathod';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
            --radius-xl: 16px;
        }
        
        body { background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); padding-bottom: 80px; font-family: 'Inter', sans-serif; min-height: 100vh; }
        
        /* Doctor Navbar */
        .doc-nav { background: #0F172A; }
        .doc-nav .navbar-brand { color: white; }
        .doc-nav .navbar-links a { color: #94A3B8; }
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; }
        .doc-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .container-main { max-width: 1400px; margin: 40px auto; padding: 0 20px; }

        /* Headers & Metrics */
        .dash-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; }
        .dh-title { font-size: 1.8rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; letter-spacing: -0.5px; }
        .dh-sub { color: var(--clr-text-secondary); font-size: 1rem; font-weight: 500; }
        
        .search-bar { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); padding: 14px 20px; border-radius: 50px; display: flex; align-items: center; gap: 12px; width: 400px; box-shadow: var(--shadow-glass); transition: all 0.3s; }
        .search-bar:focus-within { box-shadow: 0 0 0 3px rgba(37,99,235,0.2); border-color: var(--clr-primary); width: 450px; }
        .search-bar input { border: none; outline: none; width: 100%; font-size: 0.95rem; font-family: inherit; background: transparent; }

        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
        .metric-card { background: var(--glass-bg); backdrop-filter: blur(12px); padding: 24px; border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); display: flex; align-items: center; gap: 20px; transition: transform 0.3s, box-shadow 0.3s; }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .mc-icon { width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
        .mc-val { font-size: 2rem; font-weight: 800; color: var(--clr-text-primary); line-height: 1; margin-bottom: 6px; }
        .mc-label { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Tabs */
        .status-tabs { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 24px; border-bottom: 2px solid rgba(0,0,0,0.05); }
        .st-btn { background: transparent; border: none; font-weight: 700; font-size: 0.95rem; color: var(--clr-text-secondary); padding: 8px 16px; margin-bottom: -2px; border-bottom: 3px solid transparent; cursor: pointer; white-space: nowrap; transition: 0.2s; }
        .st-btn:hover { color: var(--clr-primary-dark); }
        .st-btn.active { color: var(--clr-primary); border-bottom-color: var(--clr-primary); }

        /* Data Tables */
        .c-box { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); padding: 28px; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table th { text-align: left; padding: 16px; font-weight: 700; color: var(--clr-text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); letter-spacing: 0.5px; }
        .data-table td { padding: 18px 16px; border-bottom: 1px solid rgba(0,0,0,0.05); vertical-align: middle; transition: background 0.2s; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody tr:hover { background: rgba(255,255,255,0.5); transform: scale(1.005); box-shadow: 0 4px 12px rgba(0,0,0,0.02); }

        .id-cell { font-family: monospace; font-weight: 800; font-size: 1.05rem; color: var(--clr-primary); }
        .pat-cell { font-weight: 700; color: var(--clr-text-primary); font-size: 1.05rem; }
        
        .status-badge { padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .sb-issued { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
        .sb-review { background: #FEF9C3; color: #CA8A04; border: 1px solid #FEF08A; }
        .sb-verified { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .sb-draft { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

        .btn-action { background: rgba(255,255,255,0.8); backdrop-filter: blur(4px); border: 1px solid var(--clr-border); padding: 8px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.25s ease; color: var(--clr-text-primary); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-action:hover { background: white; border-color: var(--clr-primary); color: var(--clr-primary); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.08); }
        .btn-primary { background: var(--gradient-primary); color: white; border: none; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-primary:hover { background: linear-gradient(135deg, var(--clr-primary-dark), #1e3a8a); color: white; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37,99,235,0.4); }

        /* Modals (Premium Glassmorphism) */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; backdrop-filter: blur(8px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(24px); width: 100%; max-width: 800px; border-radius: 24px; box-shadow: 0 24px 64px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,0.5) inset; overflow: hidden; transform: translateY(40px) scale(0.95); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .m-head { padding: 24px 28px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.3rem; color: var(--clr-primary-dark); background: rgba(248, 250, 252, 0.5); }
        .m-body { padding: 28px; overflow-y: auto; }
        .m-foot { padding: 20px 28px; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; gap: 16px; background: rgba(248, 250, 252, 0.7); }
        
        .kv-grid { display: grid; grid-template-columns: auto 1fr; gap: x-12px; row-gap: 12px; column-gap: 24px; font-size: 0.95rem; margin-bottom: 24px; }
        .kv-key { color: var(--clr-text-secondary); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .kv-val { color: var(--clr-text-primary); font-weight: 800; }

        .rx-med-card { border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; padding: 20px; margin-bottom: 16px; background: rgba(255,255,255,0.8); box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .rx-med-card:hover { border-color: var(--clr-primary-light); box-shadow: 0 8px 24px rgba(37,99,235,0.08); transform: translateY(-2px); background: #ffffff; }
        .rmc-head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(0,0,0,0.1); padding-bottom: 12px; margin-bottom: 16px; }
        .rmc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; font-size: 0.9rem; }
        
        .custom-input { background: rgba(241,245,249,0.5); border: 1px solid rgba(0,0,0,0.1); padding: 10px 14px; border-radius: 8px; font-family: inherit; transition: all 0.2s; outline: none; width: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.01) inset; cursor: pointer; }
        .custom-input:focus { background: white; border-color: var(--clr-primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.15), 0 2px 4px rgba(0,0,0,0.02) inset; cursor: text; }

        /* Smooth scrollbar for modal body */
        .m-body::-webkit-scrollbar { width: 8px; }
        .m-body::-webkit-scrollbar-track { background: transparent; }
        .m-body::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        .m-body::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar doc-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="doctor-shivukumar-dashboard.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> DOCTOR PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="doctor-shivukumar-dashboard.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="doctor-shivukumar-appointments.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="doctor-shivukumar-patients.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-users"></i> Patients</a>
                <a href="doctor-shivukumar-prescriptions.php" class="active" style="font-weight:600;"><i class="fas fa-file-prescription"></i> Prescriptions</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="notifications.php" style="color:#94A3B8; font-size:1.2rem;"><i class="fas fa-bell"></i></a>
            <a href="doctor-shivukumar-profile-settings.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></a>
        </div>
    </div>
</nav>

<div class="container-main">
    
    <div class="dash-header">
        <div>
            <div class="dh-title"><i class="fas fa-pills"></i> Prescription Management</div>
            <div class="dh-sub">Manage, review, authorize and track patient prescriptions</div>
        </div>
        <div class="search-bar">
            <i class="fas fa-search" style="color:var(--clr-text-secondary);"></i>
            <input type="text" placeholder="Search RX ID, Patient Name...">
        </div>
    </div>

    <!-- METRICS -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="mc-icon" style="background:#F1F5F9; color:#64748B;"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="mc-val">08</div><div class="mc-label">Drafts</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="mc-icon" style="background:#FEF9C3; color:#CA8A04;"><i class="fas fa-eye"></i></div>
            <div>
                <div class="mc-val">03</div><div class="mc-label">Review</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="mc-icon" style="background:#F0FDF4; color:#16A34A;"><i class="fas fa-check-double"></i></div>
            <div>
                <div class="mc-val">124</div><div class="mc-label">Issued</div>
            </div>
        </div>
        <div class="metric-card">
            <div class="mc-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-sync-alt"></i></div>
            <div>
                <div class="mc-val">17</div><div class="mc-label">Follow-up</div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="c-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div class="status-tabs" style="margin:0; border:none; padding:0;">
                <button class="st-btn active">All</button>
                <button class="st-btn">Draft</button>
                <button class="st-btn">Review</button>
                <button class="st-btn">Issued</button>
                <button class="st-btn">Verified</button>
                <button class="st-btn">Expired</button>
            </div>
            <button class="btn-action btn-primary" onclick="openModal('builder-modal')"><i class="fas fa-plus"></i> New Prescription</button>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>RX ID</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Diagnosis</th>
                    <th>Status</th>
                    <th>Pharmacist</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="id-cell">RX-1042</td>
                    <td class="pat-cell">Rahul Kumar</td>
                    <td>05 Sep</td>
                    <td>Viral Infection</td>
                    <td><span class="status-badge sb-issued"><i class="fas fa-check-circle"></i> Issued</span></td>
                    <td style="color:var(--clr-text-secondary);"><i class="fas fa-clock"></i> Pending Review</td>
                    <td><button class="btn-action" onclick="openModal('view-modal')">View</button></td>
                </tr>
                <tr>
                    <td class="id-cell">RX-1041</td>
                    <td class="pat-cell">Anil Kumar</td>
                    <td>05 Sep</td>
                    <td>Hypertension Review</td>
                    <td><span class="status-badge sb-review"><i class="fas fa-exclamation-circle"></i> Review</span></td>
                    <td>-</td>
                    <td><button class="btn-action btn-primary" onclick="openModal('auth-modal')" style="background:#CA8A04; border-color:#CA8A04;">Authorize</button></td>
                </tr>
                <tr>
                    <td class="id-cell">RX-1040</td>
                    <td class="pat-cell">Priya Devi</td>
                    <td>04 Sep</td>
                    <td>Migraine</td>
                    <td><span class="status-badge sb-verified"><i class="fas fa-shield-alt"></i> Verified</span></td>
                    <td style="color:#16A34A; font-weight:700;"><i class="fas fa-user-check"></i> Pharm. Roy</td>
                    <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                    <td class="id-cell" style="color:var(--clr-text-secondary);">RX-1038</td>
                    <td class="pat-cell">Jampaleppa Rathod</td>
                    <td>01 Sep</td>
                    <td>General Wellness</td>
                    <td><span class="status-badge sb-draft"><i class="fas fa-edit"></i> Draft</span></td>
                    <td>-</td>
                    <td><button class="btn-action">Edit Draft</button></td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<!-- 1. PRESCRIPTION BUILDER MODAL -->
<div class="modal-overlay" id="builder-modal">
    <div class="modal-card" style="max-width: 900px;">
        <div class="m-head">
            <div><i class="fas fa-prescription"></i> Prescription Builder</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('builder-modal')">&times;</button>
        </div>
        <div class="m-body" style="background:#F8FAFC;">
            
            <div class="kv-grid" style="background:white; padding:16px; border-radius:var(--radius-sm); border:1px solid var(--clr-border);">
                <div class="kv-key">Patient</div><div class="kv-val">Anil Kumar · PT-000182</div>
                <div class="kv-key">Context</div><div class="kv-val">CON-20260905-1041 (Hypertension Review)</div>
            </div>

            <div style="font-weight:800; color:var(--clr-text-primary); margin:20px 0 12px 0;">Search Master Catalog</div>
            <div class="search-bar" style="width:100%; margin-bottom:24px; border:2px solid var(--clr-primary-light);">
                <i class="fas fa-search text-primary"></i>
                <input type="text" placeholder="Search Generic or Brand Name...">
            </div>

            <div style="font-weight:800; color:var(--clr-text-primary); margin-bottom:12px;">Dosage Configuration</div>
            
            <div id="medicines-container">
            <div class="rx-med-card" style="border-left:4px solid var(--clr-primary);">
                <div class="rmc-head">
                    <div style="flex:1; margin-right: 16px;">
                        <input type="text" class="custom-input med-name-input" placeholder="Enter Tablet / Medicine Name..." style="font-weight:800; font-size:1.1rem; color:var(--clr-primary-dark); width:100%; margin-bottom:4px; border: 1px solid var(--clr-primary-light);">
                        <input type="text" class="custom-input med-type-input" placeholder="Form (e.g. Tablet, Capsule, Syrup)..." value="Tablet" style="font-size:0.85rem; color:var(--clr-text-secondary); font-weight:600; width:100%; border: 1px dashed var(--clr-border);">
                    </div>
                    <button type="button" class="btn-action" style="color:#DC2626; border-color:#FCA5A5;" onclick="this.closest('.rx-med-card').remove(); return false;"><i class="fas fa-trash"></i> Remove</button>
                </div>
                
                <div class="rmc-grid">
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); display:block; margin-bottom:6px;">ROUTE</label>
                        <select class="custom-input"><option>Oral</option><option>Intravenous</option><option>Topical</option></select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); display:block; margin-bottom:6px;">DOSE</label>
                        <select class="custom-input"><option>1 Tablet</option><option>2 Tablets</option><option>0.5 Tablet</option></select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); display:block; margin-bottom:6px;">FREQ</label>
                        <select class="custom-input"><option>1-0-0</option><option>1-0-1</option><option>1-1-1</option><option>0-0-1</option><option>SOS</option></select>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); display:block; margin-bottom:6px;">DURATION</label>
                        <select class="custom-input"><option>30 Days</option><option>15 Days</option><option>7 Days</option><option>5 Days</option><option>3 Days</option></select>
                    </div>
                </div>
                
                <div style="margin-top:16px;">
                    <input type="text" class="custom-input" placeholder="Instructions: e.g., Take after breakfast">
                </div>
            </div>
            </div>

            <button type="button" onclick="addMedicine()" style="border:1px dashed var(--clr-primary); background:rgba(37,99,235,0.05); color:var(--clr-primary); width:100%; padding:12px; border-radius:var(--radius-sm); font-weight:700; cursor:pointer;">+ Add Another Medicine</button>

        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('builder-modal')">Save Draft</button>
            <button class="btn-action btn-primary" onclick="closeModal('builder-modal'); openModal('auth-modal');">Continue to Review <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<!-- 2. CLINICAL AUTHORIZATION MODAL -->
<div class="modal-overlay" id="auth-modal">
    <div class="modal-card">
        <div class="m-head" style="background:#FFFBEB;">
            <div><i class="fas fa-shield-alt" style="color:#B45309;"></i> Prescription Authorization</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('auth-modal')">&times;</button>
        </div>
        <div class="m-body">
            
            <div style="font-weight:800; font-size:1.2rem; margin-bottom:8px;">RX-1041</div>
            <div class="kv-grid" style="margin-bottom:16px;">
                <div class="kv-key">Patient</div><div class="kv-val">Anil Kumar</div>
                <div class="kv-key">Diagnosis</div><div class="kv-val">Hypertension Review</div>
            </div>

            <div style="background:#F8FAFC; border:1px solid var(--clr-border); border-radius:var(--radius-sm); padding:16px; margin-bottom:24px;">
                <div style="font-weight:800; color:var(--clr-text-secondary); font-size:0.85rem; margin-bottom:12px; text-transform:uppercase;">Medicines</div>
                
                <div style="display:flex; gap:12px; margin-bottom:8px;">
                    <i class="fas fa-check" style="color:#16A34A; margin-top:2px;"></i>
                    <div>
                        <div style="font-weight:800;">Amlodipine 5mg Tablet</div>
                        <div style="font-size:0.9rem; color:var(--clr-text-secondary);">Oral · 1 Tablet · 1-0-0 · 30 Days</div>
                    </div>
                </div>
            </div>

            <div style="font-weight:800; color:var(--clr-text-secondary); font-size:0.85rem; margin-bottom:12px; text-transform:uppercase;">Review Checklist</div>
            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:24px; font-size:0.95rem;">
                <label style="display:flex; gap:8px;"><i class="fas fa-check-square" style="color:var(--clr-primary);"></i> Patient identity verified</label>
                <label style="display:flex; gap:8px;"><i class="fas fa-check-square" style="color:var(--clr-primary);"></i> Diagnosis reviewed</label>
                <label style="display:flex; gap:8px;"><i class="fas fa-check-square" style="color:var(--clr-primary);"></i> Dose and frequency reviewed</label>
                <label style="display:flex; gap:8px; color:#DC2626; font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Allergy information reviewed</label>
            </div>

            <div style="padding:16px; border:1px solid #FEF08A; background:#FEF9C3; border-radius:var(--radius-sm);">
                <label style="display:flex; align-items:flex-start; gap:12px; font-size:0.9rem; font-weight:800; color:#92400E; cursor:pointer;">
                    <input type="checkbox" style="width:20px; height:20px; margin-top:2px;">
                    I confirm that I have reviewed and authorized this prescription for this patient.
                </label>
            </div>
            
        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('auth-modal'); openModal('builder-modal');"><i class="fas fa-arrow-left"></i> Edit Rx</button>
            <button class="btn-action btn-primary" style="background:#B45309; border-color:#B45309;" onclick="closeModal('auth-modal'); openModal('view-modal');"><i class="fas fa-signature"></i> Authorize & Issue</button>
        </div>
    </div>
</div>

<!-- 3. ISSUED DIGITAL PRESCRIPTION MODAL -->
<div class="modal-overlay" id="view-modal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="m-head" style="background:#F0FDF4;">
            <div><i class="fas fa-check-circle" style="color:#16A34A;"></i> Digital Prescription Issued</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('view-modal')">&times;</button>
        </div>
        <div class="m-body" style="background:#F8FAFC; padding:40px;">
            
            <div style="background:white; border:1px solid var(--clr-border); box-shadow:0 10px 30px rgba(0,0,0,0.05); padding:40px;">
                <div style="text-align:center; margin-bottom:32px;">
                    <i class="fas fa-heartbeat" style="font-size:2rem; color:var(--clr-primary);"></i>
                    <div style="font-weight:900; font-size:1.5rem; color:var(--clr-primary-dark); margin-top:8px; letter-spacing:1px;">DOCTOR ASSIST</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); letter-spacing:2px; text-transform:uppercase;">Digital Prescription</div>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:32px; font-size:0.9rem; border-bottom:2px solid var(--clr-primary); padding-bottom:16px;">
                    <div>
                        <div style="font-weight:800; color:var(--clr-text-primary); font-size:1.1rem;"><?php echo htmlspecialchars($docName); ?></div>
                        <div style="color:var(--clr-text-secondary);">Pediatrics</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-family:monospace; font-weight:800; color:var(--clr-primary); font-size:1.1rem;">RX-1042</div>
                        <div style="color:var(--clr-text-secondary);">Issued: 05 Sep 2026</div>
                    </div>
                </div>

                <div class="kv-grid" style="margin-bottom:32px;">
                    <div class="kv-key">Patient</div><div class="kv-val">Rahul Kumar (PT-000124)</div>
                    <div class="kv-key">Diagnosis</div><div class="kv-val">Viral Infection</div>
                </div>

                <table class="data-table" style="margin-bottom:24px; border:1px solid var(--clr-border);">
                    <thead style="background:#F8FAFC;">
                        <tr>
                            <th>Medicine</th>
                            <th>Dose</th>
                            <th>Freq</th>
                            <th>Dur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight:800;">Paracetamol 500mg</td>
                            <td>1 Tab</td>
                            <td>1-0-1</td>
                            <td>3 Days</td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-bottom:40px;">
                    <div style="font-weight:800; font-size:0.85rem; color:var(--clr-text-secondary); text-transform:uppercase; margin-bottom:4px;">Instructions</div>
                    <div style="font-size:0.95rem; font-style:italic;">Take after food. Ensure adequate hydration.</div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                    <div>
                        <div style="color:#16A34A; font-weight:800; border:2px solid #16A34A; padding:4px 12px; border-radius:4px; display:inline-block; margin-bottom:8px;">✓ CLINICALLY AUTHORIZED</div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary);">Valid for 6 months (Policy DA-6M)</div>
                    </div>
                    <div style="text-align:center;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/Signature_of_John_Hancock.svg" style="width:120px; opacity:0.6; filter: hue-rotate(200deg);">
                        <div style="border-top:1px solid var(--clr-text-secondary); font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); padding-top:4px;">Digital Signature</div>
                    </div>
                </div>
            </div>
            
        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('view-modal')">Close</button>
            <div>
                <button class="btn-action"><i class="fas fa-print"></i> Print</button>
                <button class="btn-action btn-primary"><i class="fas fa-download"></i> Download PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    // -- LocalStorage Persistence for Prescription Draft --
    let rxDraft = JSON.parse(localStorage.getItem('da_rx_draft')) || [];

    function saveRxDraft() {
        const container = document.getElementById('medicines-container');
        const items = container.querySelectorAll('.med-name-input');
        rxDraft = [];
        items.forEach(item => {
            if(item.value) rxDraft.push({ name: item.value });
        });
        localStorage.setItem('da_rx_draft', JSON.stringify(rxDraft));
    }

    function addMedicine() {
        const container = document.getElementById('medicines-container');
        if (container && container.children.length > 0) {
            const clone = container.children[0].cloneNode(true);
            
            const inputs = clone.querySelectorAll('input');
            inputs.forEach(input => {
                if (input.classList.contains('med-type-input')) {
                    input.value = 'Tablet'; // default
                } else {
                    input.value = '';
                }
                input.addEventListener('change', saveRxDraft);
            });
            
            container.appendChild(clone);
            saveRxDraft();
        }
    }

    // Initialize observers for inputs to auto-save
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('#medicines-container input').forEach(input => {
            input.addEventListener('change', saveRxDraft);
        });
        if(rxDraft.length > 1) { // 1 is default
            for(let i=1; i < rxDraft.length; i++) {
                addMedicine();
            }
        }
    });
</script>

</body>
</html>
