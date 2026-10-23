<?php
require_once 'helper.php';
// Pull doctor from session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';

// Dynamically generate or load patient from DB
$pdo = getAssistDB();
try { $stmt = $pdo->query("SELECT name, email, phone, created_at, ksr_id FROM users WHERE role = 'user' OR role = 'admin' LIMIT 1"); } catch (\Throwable $e) { $stmt = null; }
$patient = isset($stmt) && $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

// Map DB payload to workspace state (with safe fallbacks)
$patientName = $patient ? ($patient['name'] ?: 'Patient User') : 'Rahul Kumar';
$patientId = $patient ? ($patient['ksr_id'] ?: 'PT-' . strtoupper(substr(md5($patient['email']), 0, 6))) : 'PT-000124';
$patientAge = '32'; // Can be expanded with DOB parsing
$patientGender = 'Unknown'; 
$apptId = 'APT-' . date('Ymd-Hia');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Workspace | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-xl: 20px;
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
        }

        body { 
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 80px; 
            min-height: 100vh;
        }
        
        /* Specialized Doctor Navbar overriding base */
        .doc-nav { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.1); position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.2);}
        .doc-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 10px; font-weight: 900;}
        .doc-nav .navbar-links a { color: #94A3B8; font-weight: 700; transition: 0.3s;}
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; transform: translateY(-1px);}
        .doc-nav .profile-badge { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); transition: 0.3s;}
        .doc-nav .profile-badge:hover { background: rgba(255,255,255,0.2); }

        .workspace-grid { max-width: 1600px; margin: 32px auto; padding: 0 24px; display: grid; grid-template-columns: 340px 1fr 360px; gap: 32px; align-items: start; }
        
        .c-box { background: var(--glass-bg); backdrop-filter: blur(16px); border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); padding: 28px; transition: 0.3s;}
        .c-box:hover { box-shadow: 0 20px 50px rgba(0,0,0,0.1); transform: translateY(-2px); border-color: rgba(255,255,255,0.8); }
        .cb-head { font-weight: 900; font-size: 1.15rem; color: var(--clr-primary-dark); text-transform: uppercase; margin-bottom: 24px; border-bottom: 1px dashed rgba(0,0,0,0.1); padding-bottom: 16px; display: flex; align-items: center; justify-content: space-between; letter-spacing: 0.5px;}

        /* HEADER BANNER */
        .workspace-header { max-width: 1600px; margin: 32px auto 0 auto; padding: 0 24px; }
        .wh-card { background: linear-gradient(135deg, #0F172A, #1E293B); color: white; border-radius: var(--radius-xl); padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 16px 40px rgba(15,23,42,0.3); border: 1px solid rgba(255,255,255,0.1);}
        .wh-badge { background: rgba(34,197,94,0.15); color: #4ADE80; border: 1px solid rgba(74, 222, 128, 0.3); padding: 6px 16px; border-radius: var(--radius-full); font-size: 0.9rem; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);}

        /* LEFT PANEL: Patient Summary */
        .kv-grid { display: grid; grid-template-columns: auto 1fr; gap: 8px; row-gap: 12px; column-gap: 20px; font-size: 0.95rem; margin-bottom: 24px; }
        .kv-key { color: var(--clr-text-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; margin-top:2px;}
        .kv-val { color: var(--clr-text-primary); font-weight: 800; }
        
        .alert-box { background: rgba(254, 242, 242, 0.6); border-left: 4px solid #DC2626; padding: 16px; font-size: 0.9rem; color: #991B1B; margin-bottom: 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; box-shadow: var(--shadow-glass); }
        .info-box { background: rgba(240, 253, 244, 0.6); border-left: 4px solid #16A34A; padding: 16px; font-size: 0.9rem; color: #166534; margin-bottom: 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; box-shadow: var(--shadow-glass); }

        /* CENTER PANEL: Clinical Documentation */
        .stepper { display: flex; justify-content: space-between; margin-bottom: 40px; padding: 0 20px; position: relative; }
        .stepper::before { content: ''; position: absolute; top: 16px; left: 40px; right: 40px; height: 3px; background: rgba(0,0,0,0.05); z-index: 1; }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px; font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); transition: 0.3s;}
        .step-circle { width: 34px; height: 34px; border-radius: 50%; background: white; border: 3px solid rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s;font-size:1rem;}
        .step.done .step-circle { background: #16A34A; border-color: #16A34A; color: white; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);}
        .step.active .step-circle { background: var(--clr-primary); border-color: var(--clr-primary); color: white; box-shadow: 0 0 0 6px rgba(37,99,235,0.15); }
        .step.done { color: #16A34A; }
        .step.active { color: var(--clr-primary); transform: translateY(-2px);}

        .doc-section { margin-bottom: 40px; }
        .doc-title { font-weight: 900; font-size: 1.2rem; color: var(--clr-text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .input-box { background: rgba(255,255,255,0.5); border: 1px solid rgba(0,0,0,0.05); padding: 24px; border-radius: var(--radius-xl); box-shadow: inset 0 2px 10px rgba(0,0,0,0.02); }
        .input-box input[type="text"], .input-box textarea, .input-box select { width: 100%; border: 1px solid rgba(0,0,0,0.1); padding: 16px; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; margin-bottom: 16px; transition: 0.3s; background: rgba(255,255,255,0.8); }
        .input-box input:focus, .input-box textarea:focus, .input-box select:focus { outline: none; border-color: var(--clr-primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.2); background: white; }
        
        .tag { display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid rgba(0,0,0,0.05); padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: 700; margin-right: 10px; margin-bottom: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s;}
        .tag:hover { border-color: #DC2626; }
        .tag i { color: #DC2626; cursor: pointer; transition: 0.2s; }
        .tag i:hover { transform: scale(1.2); }

        .vitals-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 0; }
        .vital-box { background: white; border: 1px solid rgba(0,0,0,0.05); padding: 16px; border-radius: var(--radius-md); text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: 0.3s; }
        .vital-box:hover { transform: translateY(-2px); border-color: var(--clr-primary-light); box-shadow: 0 6px 16px rgba(37,99,235,0.1); }
        .vb-label { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;}
        .vb-val { font-size: 1.3rem; font-weight: 900; color: var(--clr-primary-dark); }
        
        .rx-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border: 1px solid rgba(0,0,0,0.05); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .rx-table th { background: rgba(241, 245, 249, 0.5); text-align: left; padding: 16px 20px; font-size: 0.9rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .rx-table td { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 1rem; }
        .rx-table tr:last-child td { border-bottom: none; }

        /* RIGHT PANEL: Actions */
        .action-menu { display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; }
        .btn-act { background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); padding: 16px 20px; border-radius: var(--radius-md); text-align: left; font-weight: 800; font-size: 1rem; color: var(--clr-text-primary); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .btn-act:hover { background: white; border-color: var(--clr-primary-light); color: var(--clr-primary); transform: translateX(4px); box-shadow: 0 6px 16px rgba(37,99,235,0.1); }
        .btn-act i { width: 24px; text-align: center; color: var(--clr-text-secondary); font-size: 1.1rem; transition: 0.3s;}
        .btn-act:hover i { color: var(--clr-primary); transform: scale(1.1); }

        .btn-primary { background: var(--gradient-primary); color: white; border: none; padding: 18px 28px; border-radius: 50px; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 4px 16px rgba(37,99,235,0.3); text-transform: uppercase; letter-spacing: 0.5px;}
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,0.4); }
        .btn-success { background: linear-gradient(135deg, #16A34A, #15803D); box-shadow: 0 4px 16px rgba(22, 163, 74, 0.3); }
        .btn-success:hover { background: linear-gradient(135deg, #15803D, #166534); box-shadow: 0 8px 24px rgba(22, 163, 74, 0.4); }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; backdrop-filter: blur(8px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(24px); width: 100%; max-width: 650px; border-radius: var(--radius-xl); box-shadow: 0 24px 64px rgba(0,0,0,0.2), inset 0 0 0 1px rgba(255,255,255,0.6); overflow: hidden; transform: translateY(30px) scale(0.95); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .m-head { padding: 24px 32px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; font-weight: 900; font-size: 1.35rem; color: var(--clr-primary-dark); background: rgba(248, 250, 252, 0.5); }
        .m-body { padding: 32px; overflow-y: auto; }
        
        @media (max-width: 1200px) {
            .workspace-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'consult'; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar doc-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="doctor-dashboard.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> DOCTOR PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="#"  style="font-weight:600; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="#"  class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></a>
        </div>
    </div>
</nav>

<div class="workspace-header">
    <div class="wh-card">
        <div>
            <div class="wh-badge"><i class="fas fa-circle" style="font-size:0.5rem;"></i> IN CONSULTATION</div>
            <div style="margin-top:12px; font-weight:800; font-size:1.5rem;">Patient: <?php echo $patientName; ?></div>
            <div style="color:#94A3B8; font-size:0.95rem; margin-top:4px;"><?php echo $patientAge; ?> yrs · <?php echo $patientGender; ?> &nbsp;|&nbsp; 🎥 Video (Started: 10:42 AM)</div>
        </div>
        <div style="text-align:right;">
            <div style="font-family:monospace; font-weight:800; color:#cbd5e1;"><?php echo $apptId; ?></div>
            <div style="margin-top:12px; display:flex; gap:12px;">
                <button style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:8px 16px; border-radius:20px; font-weight:700; cursor:pointer;"><i class="fas fa-folder-open"></i> Previous Records</button>
                <button style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:8px 16px; border-radius:20px; font-weight:700; cursor:pointer;" onclick="openModal('docs-modal')"><i class="fas fa-file-image"></i> Documents (3)</button>
            </div>
        </div>
    </div>
</div>

<div class="workspace-grid">
    
    <!-- LEFT PANEL -->
    <div class="c-box" style="position:sticky; top:24px;">
        <div class="cb-head"><i class="fas fa-id-card"></i> Patient Summary</div>
        
        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:72px; height:72px; border-radius:50%; background:var(--clr-primary-light); color:var(--clr-primary); display:flex; align-items:center; justify-content:center; font-size:1.8rem; font-weight:800; margin:0 auto 12px auto;">RK</div>
            <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-primary);"><?php echo $patientName; ?></div>
            <div style="font-family:monospace; color:var(--clr-text-secondary);"><?php echo $patientId; ?></div>
        </div>
        
        <div class="kv-grid">
            <div class="kv-key">Age/Sex</div><div class="kv-val">32 / Male</div>
            <div class="kv-key">Blood</div><div class="kv-val">O+</div>
            <div class="kv-key">Last Visit</div><div class="kv-val">02 Sep 2026</div>
        </div>

        <div class="alert-box">
            <div style="font-weight:800; text-transform:uppercase;"><i class="fas fa-exclamation-triangle"></i> Allergies</div>
            <div style="margin-top:4px;">Mild Penicillin Allergy</div>
        </div>

        <div class="info-box">
            <div style="font-weight:800; text-transform:uppercase;"><i class="fas fa-pills"></i> Current Meds</div>
            <div style="margin-top:4px;">Amlodipine (5mg)</div>
        </div>

        <div class="info-box" style="background:#F3E8FF; border-color:#9333EA; color:#6B21A8;">
            <div style="font-weight:800; text-transform:uppercase;"><i class="fas fa-stethoscope"></i> Conditions</div>
            <div style="margin-top:4px;">Mild Hypertension</div>
        </div>

        <button style="width:100%; padding:10px; background:white; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-weight:700; cursor:pointer;">[ View Full Profile ]</button>
    </div>

    <!-- CENTER PANEL -->
    <div class="c-box">
        
        <!-- Stepper -->
        <div class="stepper">
            <div class="step done"><div class="step-circle"><i class="fas fa-check"></i></div>Review</div>
            <div class="step active"><div class="step-circle">2</div>Notes</div>
            <div class="step"><div class="step-circle">3</div>Exam</div>
            <div class="step"><div class="step-circle">4</div>Diagnosis</div>
            <div class="step"><div class="step-circle">5</div>Rx</div>
            <div class="step"><div class="step-circle">6</div>Finish</div>
        </div>

        <div class="doc-section">
            <div class="doc-title"><i class="fas fa-comment-medical text-primary"></i> Chief Complaint</div>
            <div class="input-box">
                <textarea rows="3" placeholder="What is the patient's main concern? e.g. Patient reports headache for the last 3 days.">Patient reports moderate fever and persistent dry cough for the last 48 hours.</textarea>
                <div style="display:flex; gap:16px;">
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary);">DURATION</label>
                        <select><option>2 Days</option><option>1 Week</option></select>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary);">SEVERITY</label>
                        <select><option>Moderate</option><option>Mild</option><option>Severe</option></select>
                    </div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-title"><i class="fas fa-search text-primary"></i> Symptoms & Vitals</div>
            <div class="input-box">
                <div style="margin-bottom:12px;">
                    <div class="tag">Fever <i class="fas fa-times"></i></div>
                    <div class="tag">Dry Cough <i class="fas fa-times"></i></div>
                    <div class="tag">Fatigue <i class="fas fa-times"></i></div>
                </div>
                <input type="text" placeholder="🔍 Search and add symptoms...">
                
                <div style="margin-top:16px; border-top:1px dashed var(--clr-border); padding-top:16px;">
                    <div style="font-size:0.85rem; font-weight:700; color:var(--clr-text-secondary); margin-bottom:12px;">RECORDED VITALS</div>
                    <div class="vitals-grid">
                        <div class="vital-box"><div class="vb-label">Temp</div><div class="vb-val">100.2 °F</div></div>
                        <div class="vital-box"><div class="vb-label">BP</div><div class="vb-val">120/80</div></div>
                        <div class="vital-box"><div class="vb-label">SpO2</div><div class="vb-val">98%</div></div>
                        <div class="vital-box"><div class="vb-label">Pulse</div><div class="vb-val">82</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-title"><i class="fas fa-diagnoses text-primary"></i> Assessment & Diagnosis</div>
            <div class="input-box">
                <textarea rows="3" placeholder="Clinical Assessment">Mild viral upper respiratory infection. Lungs clear to auscultation.</textarea>
                
                <div style="border-top:1px solid var(--clr-border); margin-top:12px; padding-top:12px;">
                    <label style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary);">PRIMARY DIAGNOSIS</label>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:12px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); margin-top:8px;">
                        <div>
                            <div style="font-weight:800; color:var(--clr-primary-dark);">Viral Upper Respiratory Infection</div>
                            <div style="font-size:0.8rem; color:#16A34A; font-weight:700;">Confirmed</div>
                        </div>
                        <i class="fas fa-trash-alt" style="color:#DC2626; cursor:pointer;"></i>
                    </div>
                    <input type="text" placeholder="🔍 Search ICD/Diagnosis to add..." style="margin-top:12px;">
                </div>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-title"><i class="fas fa-prescription-bottle-alt text-primary"></i> Prescription Builder</div>
            <div class="input-box" style="border:2px solid var(--clr-primary-light);">
                
                <table class="rx-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dose</th>
                            <th>Freq</th>
                            <th>Dur</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="position:relative;">
                                <input type="text" value="Paracetamol" onkeyup="searchMedicine(this)" placeholder="Enter Medicine..." style="font-weight:700; width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px;">
                                <div class="med-results" style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #E2E8F0; max-height:200px; overflow-y:auto; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:4px;"></div>
                                <input type="text" value="500mg Tablet" placeholder="Type..." style="font-size:0.8rem; color:var(--clr-text-secondary); width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px; margin-top:2px;">
                            </td>
                            <td><input type="text" value="1 Tab" style="width:100%; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><input type="text" value="1-1-1" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><input type="text" value="3 Days" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><i class="fas fa-times-circle" style="color:#DC2626; cursor:pointer;" onclick="this.closest('tr').remove();"></i></td>
                        </tr>
                        <tr>
                            <td style="position:relative;">
                                <input type="text" value="Levocetirizine" onkeyup="searchMedicine(this)" placeholder="Enter Medicine..." style="font-weight:700; width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px;">
                                <div class="med-results" style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #E2E8F0; max-height:200px; overflow-y:auto; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:4px;"></div>
                                <input type="text" value="5mg Tablet" placeholder="Type..." style="font-size:0.8rem; color:var(--clr-text-secondary); width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px; margin-top:2px;">
                            </td>
                            <td><input type="text" value="1 Tab" style="width:100%; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><input type="text" value="0-0-1" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><input type="text" value="5 Days" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
                            <td><i class="fas fa-times-circle" style="color:#DC2626; cursor:pointer;" onclick="this.closest('tr').remove();"></i></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" onclick="addMedicineRow()" style="border:1px dashed var(--clr-border); background:white; padding:10px; width:100%; border-radius:var(--radius-sm); font-weight:700; color:var(--clr-primary); cursor:pointer; margin-top: 8px;">+ Add Medicine</button>
                
                <textarea rows="2" placeholder="General Rx Instructions (e.g., Take after food)" style="margin-top:12px; width: 100%; padding: 8px; border: 1px solid var(--clr-border); border-radius: 4px; font-family: 'Inter', sans-serif;"></textarea>
            </div>
        </div>

        <div class="doc-section">
            <div class="doc-title"><i class="fas fa-calendar-alt text-primary"></i> Follow-up</div>
            <div class="input-box">
                <div style="display:flex; gap:16px;">
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary);">DATE</label>
                        <input type="date" value="2026-09-12">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary);">TYPE</label>
                        <select><option>Video Consultation</option><option>Audio</option></select>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT PANEL -->
    <div>
        <div class="c-box" style="position:sticky; top:24px;">
            <div class="cb-head"><i class="fas fa-bolt"></i> Actions</div>
            
            <div class="action-menu">
                <button class="btn-act"><i class="fas fa-notes-medical"></i> Add Clinical Note</button>
                <button class="btn-act"><i class="fas fa-file-medical"></i> Order Lab Test</button>
                <button class="btn-act" onclick="openModal('docs-modal')"><i class="fas fa-images"></i> View Documents</button>
            </div>

            <hr style="border:none; border-top:1px dashed var(--clr-border); margin:24px 0;">

            <button class="btn-primary" style="margin-bottom:12px; background:white; color:var(--clr-text-primary); border:2px solid var(--clr-border);"><i class="fas fa-save"></i> Save Draft</button>
            <button class="btn-primary btn-success" onclick="openModal('complete-modal')" style="box-shadow: 0 10px 20px rgba(22,163,74,0.3);"><i class="fas fa-check-circle"></i> Complete Consultation</button>
        </div>
    </div>

</div>

<!-- DOCUMENTS MODAL -->
<div class="modal-overlay" id="docs-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-folder-open"></i> Patient Documents</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('docs-modal')">&times;</button>
        </div>
        <div class="m-body">
            <div style="background:#F0FDF4; color:#166534; padding:12px; border-radius:var(--radius-sm); font-size:0.85rem; font-weight:700; margin-bottom:16px;">
                <i class="fas fa-shield-alt"></i> Access scoped to Appointment <?php echo $apptId; ?>
            </div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); margin-bottom:8px; background:#F8FAFC;">
                <div>
                    <div style="font-weight:800; color:var(--clr-text-primary);"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Blood_Report.pdf</div>
                    <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">03 Sep 2026 · 👁 Read Only</div>
                </div>
                <button style="border:1px solid var(--clr-primary); background:white; color:var(--clr-primary); padding:6px 12px; border-radius:6px; font-weight:700; cursor:pointer;">View</button>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); margin-bottom:8px; background:#F8FAFC;">
                <div>
                    <div style="font-weight:800; color:var(--clr-text-primary);"><i class="fas fa-file-image" style="color:#0284C7;"></i> Skin_Rash_Aug.jpg</div>
                    <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:4px;">28 Aug 2026 · 👁 Read Only</div>
                </div>
                <button style="border:1px solid var(--clr-primary); background:white; color:var(--clr-primary); padding:6px 12px; border-radius:6px; font-weight:700; cursor:pointer;">View</button>
            </div>
        </div>
    </div>
</div>

<!-- COMPLETE CONSULTATION MODAL -->
<div class="modal-overlay" id="complete-modal">
    <div class="modal-card">
        <div class="m-head" style="background:#F0FDF4;">
            <div><i class="fas fa-check-circle" style="color:#16A34A;"></i> Complete Consultation</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('complete-modal')">&times;</button>
        </div>
        <div class="m-body">
            <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-primary); margin-bottom:16px;">Consultation Summary</div>
            
            <div style="background:#F8FAFC; border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:16px; margin-bottom:24px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; font-size:0.95rem; font-weight:700;">
                    <i class="fas fa-check" style="color:#16A34A;"></i> Clinical Notes Recorded
                </div>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; font-size:0.95rem; font-weight:700;">
                    <i class="fas fa-check" style="color:#16A34A;"></i> Diagnosis: Viral URI
                </div>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; font-size:0.95rem; font-weight:700;">
                    <i class="fas fa-check" style="color:#16A34A;"></i> Prescription Generated (2 Meds)
                </div>
                <div style="display:flex; align-items:center; gap:12px; font-size:0.95rem; font-weight:700;">
                    <i class="fas fa-check" style="color:#16A34A;"></i> Follow-up Scheduled (12 Sep)
                </div>
            </div>

            <div style="padding:16px; border:1px solid #FEF08A; background:#FFFBEB; border-radius:var(--radius-sm); margin-bottom:24px;">
                <div style="font-weight:800; color:#B45309; margin-bottom:8px;"><i class="fas fa-shield-alt"></i> Doctor Affirmation</div>
                <label style="display:flex; align-items:flex-start; gap:12px; font-size:0.9rem; font-weight:600; cursor:pointer;">
                    <input type="checkbox" style="width:20px; height:20px; margin-top:2px;" checked>
                    I confirm that I have reviewed the clinical information, diagnosis, and prescription safety warnings.
                </label>
            </div>
            
            <div id="api-feedback" style="display:none; padding:12px; margin-bottom:16px; border-radius:var(--radius-sm); font-weight:700;"></div>
            
            <button class="btn-primary btn-success" id="btn-submit-rx" style="width:100%;" onclick="submitConsultation()"><i class="fas fa-lock"></i> SIGN & COMPLETE CONSULTATION</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    function addMedicineRow() {
        const tbody = document.querySelector('.rx-table tbody');
        if(!tbody) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="position:relative;">
                <input type="text" placeholder="Enter Medicine..." onkeyup="searchMedicine(this)" style="font-weight:700; width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px;">
                <div class="med-results" style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #E2E8F0; max-height:200px; overflow-y:auto; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-radius:4px;"></div>
                <input type="text" placeholder="Type..." style="font-size:0.8rem; color:var(--clr-text-secondary); width:100%; border:1px dashed #E2E8F0; padding:4px; border-radius:4px; margin-top:2px;">
            </td>
            <td><input type="text" placeholder="Dose" style="width:100%; border:1px dashed #E2E8F0; padding:4px;"></td>
            <td><input type="text" placeholder="Freq" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
            <td><input type="text" placeholder="Dur" style="width:80px; border:1px dashed #E2E8F0; padding:4px;"></td>
            <td><i class="fas fa-times-circle" style="color:#DC2626; cursor:pointer;" onclick="this.closest('tr').remove();"></i></td>
        `;
        tbody.appendChild(tr);
    }
    
    let searchTimeout;
    async function searchMedicine(input) {
        clearTimeout(searchTimeout);
        const resultsDiv = input.nextElementSibling;
        
        if (input.value.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch('../../api/telehealth/api_inventory_search.php?q=' + encodeURIComponent(input.value));
                if (!res.ok) {
                    // Fallback to local path if api folder structure differs
                    const res2 = await fetch('api_inventory_search.php?q=' + encodeURIComponent(input.value));
                    var data = await res2.json();
                } else {
                    var data = await res.json();
                }
                
                resultsDiv.innerHTML = '';
                if (data.results && data.results.length > 0) {
                    data.results.forEach(item => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding:8px 12px; cursor:pointer; border-bottom:1px solid #F1F5F9; font-size:0.9rem; font-weight:600; display:flex; justify-content:space-between;';
                        
                        let dot = '#16A34A'; // Available
                        if (item.status === 'Out of Stock') dot = '#DC2626';
                        else if (item.status === 'Low Stock') dot = '#EAB308';
                        
                        div.innerHTML = `<span>${item.name}</span> <span style="font-size:0.75rem; color:${dot};">● ${item.status}</span>`;
                        div.onclick = () => {
                            input.value = item.name;
                            resultsDiv.style.display = 'none';
                        };
                        div.onmouseover = () => div.style.background = '#F8FAFC';
                        div.onmouseout = () => div.style.background = 'white';
                        resultsDiv.appendChild(div);
                    });
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.style.display = 'none';
                }
            } catch (e) {
                console.error("Search failed: ", e);
            }
        }, 300);
    }

    async function submitConsultation() {
        const btn = document.getElementById('btn-submit-rx');
        const feedback = document.getElementById('api-feedback');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SUBMITTING...';
        
        // 1. Scrape Medicines
        const medicines = [];
        document.querySelectorAll('.rx-table tbody tr').forEach(row => {
            const inputs = row.querySelectorAll('input');
            if (inputs.length >= 4 && inputs[0].value.trim() !== '') {
                medicines.push({
                    name: inputs[0].value.trim(),
                    type: inputs[1].value.trim(),
                    dose: inputs[2].value.trim(),
                    freq: inputs[3].value.trim(),
                    dur: inputs[4].value.trim()
                });
            }
        });

        // 2. Package Payload
        const payload = {
            patientId: '<?php echo $patientId; ?>',
            apptId: '<?php echo $apptId; ?>',
            medicines: medicines,
            notes: 'Test note',
            diagnosis: 'Test diagnosis'
        };

        // 3. Dispatch to Gateway
        try {
            const res = await fetch('../../api/telehealth/submit-prescription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            feedback.style.display = 'block';
            if (data.success) {
                feedback.style.background = '#F0FDF4';
                feedback.style.color = '#166534';
                feedback.innerText = 'Prescription successfully validated and committed (ID: ' + data.audit_id + '). Redirecting...';
                
                // Clear draft if any
                localStorage.removeItem('rx_draft');
                
                // Route safely back to their isolated dashboard
                setTimeout(() => {
                    const docName = "<?php echo addslashes($docName); ?>";
                    if(docName.toLowerCase().includes('shivu')) {
                        window.location.href = 'doctor-shivukumar-appointments.php';
                    } else {
                        window.location.href = 'doctor-appointments.php';
                    }
                }, 2000);
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            feedback.style.display = 'block';
            feedback.style.background = '#FEF2F2';
            feedback.style.color = '#991B1B';
            feedback.innerText = 'Error: ' + err.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> SIGN & COMPLETE CONSULTATION';
        }
    }
</script>

</body>
</html>
