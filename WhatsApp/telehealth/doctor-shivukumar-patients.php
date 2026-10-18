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
    <title>Clinical Records | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F1F5F9; padding-bottom: 80px; }
        
        /* Doctor Navbar */
        .doc-nav { background: #0F172A; }
        .doc-nav .navbar-brand { color: white; }
        .doc-nav .navbar-links a { color: #94A3B8; }
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; }
        .doc-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .container-main { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        .grid-layout { display: grid; grid-template-columns: 350px 1fr; gap: 32px; align-items: start; }

        /* Headers & Metrics (If needed full width, but here we focus on active patient) */
        .dash-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        
        .c-box { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 24px; }
        .cb-head { font-weight: 800; font-size: 1.05rem; color: var(--clr-primary-dark); text-transform: uppercase; margin-bottom: 16px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; display: flex; align-items: center; gap: 8px; justify-content: space-between; }

        /* Left Pillar - Patient Details */
        .pat-header { text-align: center; margin-bottom: 24px; }
        .pat-avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--clr-primary-light); color: var(--clr-primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 0 auto 12px auto; }
        .pat-name { font-weight: 800; font-size: 1.5rem; color: var(--clr-text-primary); }
        .pat-id { font-family: monospace; color: var(--clr-text-secondary); font-size: 0.95rem; }
        .care-badge { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; }

        .info-pill { background: #F8FAFC; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .info-pill i { width: 24px; text-align: center; font-size: 1.2rem; }
        
        /* Navigation Tabs */
        .nav-menu { display: flex; flex-direction: column; gap: 4px; margin-top: 24px; }
        .nav-btn { background: transparent; border: none; text-align: left; padding: 12px 16px; font-weight: 700; color: var(--clr-text-secondary); cursor: pointer; border-radius: var(--radius-sm); transition: 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .nav-btn.active { background: var(--clr-primary); color: white; }
        .nav-btn:hover:not(.active) { background: #F8FAFC; color: var(--clr-primary); }

        /* Right Pillar - Clinical Content */
        .soap-box { background: #F8FAFC; border: 1px solid var(--clr-border); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 16px; }
        .soap-head { background: white; padding: 12px 16px; border-bottom: 1px solid var(--clr-border); font-weight: 800; color: var(--clr-text-primary); display: flex; align-items: center; justify-content: space-between; }
        .soap-body { padding: 16px; }
        .soap-body textarea { width: 100%; border: 1px solid var(--clr-border); padding: 12px; border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; margin-bottom: 12px; background: white; min-height: 80px; resize: vertical; }

        .vitals-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .vital-card { background: white; border: 1px solid var(--clr-border); padding: 16px; border-radius: var(--radius-sm); text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .vc-icon { font-size: 1.5rem; color: var(--clr-primary); margin-bottom: 8px; }
        .vc-val { font-size: 1.5rem; font-weight: 800; color: var(--clr-text-primary); margin-bottom: 4px; }
        .vc-label { font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; }

        /* Timeline */
        .timeline { position: relative; margin-left: 20px; border-left: 2px solid var(--clr-border); padding-left: 32px; padding-bottom: 24px; }
        .tl-entry { margin-bottom: 32px; position: relative; }
        .tl-dot { position: absolute; left: -41px; top: 0; width: 20px; height: 20px; border-radius: 50%; background: white; border: 4px solid var(--clr-primary); }
        .tl-date { font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 12px; font-size: 1.1rem; }
        .tl-card { background: white; border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 16px; box-shadow: var(--shadow-sm); display: flex; gap: 16px; align-items: flex-start; margin-bottom: 12px; }
        .tl-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        
        .btn-primary { background: var(--clr-primary); color: white; border: none; padding: 12px 24px; border-radius: var(--radius-sm); font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: var(--clr-primary-dark); }
        
        @media (max-width: 1100px) {
            .grid-layout { grid-template-columns: 1fr; }
        }
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
                <a href="doctor-shivukumar-patients.php" class="active" style="font-weight:600;"><i class="fas fa-users"></i> Patients</a>
                <a href="doctor-shivukumar-prescriptions.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-file-prescription"></i> Prescriptions</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="background:rgba(255,255,255,0.1); border-radius:20px; padding:6px 16px; display:flex; align-items:center; gap:12px;">
                <i class="fas fa-search" style="color:#94A3B8;"></i>
                <input type="text" placeholder="Search Patients..." style="background:none; border:none; outline:none; color:white; font-family:inherit;">
            </div>
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="notifications.php" style="color:#94A3B8; font-size:1.2rem;"><i class="fas fa-bell"></i></a>
            <a href="doctor-shivukumar-profile-settings.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></a>
        </div>
    </div>
</nav>

<div class="container-main">
    
    <div class="grid-layout">
        
        <!-- LEFT PILLAR: Patient Core Details -->
        <div>
            <div class="c-box" style="position:sticky; top:24px;">
                <div class="pat-header">
                    <div class="pat-avatar">RK</div>
                    <div class="pat-name">Rahul Kumar</div>
                    <div class="pat-id">PT-000124 &nbsp;|&nbsp; 32 &nbsp;|&nbsp; Male</div>
                    <div class="care-badge"><i class="fas fa-circle" style="font-size:0.5em;"></i> Active Care</div>
                </div>

                <div class="info-pill" style="border-left:4px solid #DC2626;">
                    <i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i>
                    <div>
                        <div style="font-weight:800; font-size:0.85rem; color:var(--clr-text-secondary); text-transform:uppercase;">Allergies</div>
                        <div style="font-weight:700; color:var(--clr-text-primary);">No known allergies</div>
                    </div>
                </div>
                
                <div class="info-pill" style="border-left:4px solid #16A34A;">
                    <i class="fas fa-pills" style="color:#16A34A;"></i>
                    <div>
                        <div style="font-weight:800; font-size:0.85rem; color:var(--clr-text-secondary); text-transform:uppercase;">Active Medicines</div>
                        <div style="font-weight:700; color:var(--clr-text-primary);">2 medicines</div>
                    </div>
                </div>

                <div class="info-pill" style="border-left:4px solid #9333EA;">
                    <i class="fas fa-stethoscope" style="color:#9333EA;"></i>
                    <div>
                        <div style="font-weight:800; font-size:0.85rem; color:var(--clr-text-secondary); text-transform:uppercase;">Active Conditions</div>
                        <div style="font-weight:700; color:var(--clr-text-primary);">1 condition</div>
                    </div>
                </div>

                <div class="nav-menu">
                    <button class="nav-btn" onclick="switchTab('overview')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-columns"></i> Overview</span></button>
                    <button class="nav-btn active" onclick="switchTab('soap')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-edit"></i> Clinical Notes (SOAP)</span></button>
                    <button class="nav-btn" onclick="switchTab('vitals')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-heartbeat"></i> Vital Signs</span></button>
                    <button class="nav-btn" onclick="switchTab('timeline')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-stream"></i> Clinical Timeline</span></button>
                    <button class="nav-btn" onclick="switchTab('diagnoses')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-diagnoses"></i> Diagnoses</span> <span style="background:rgba(0,0,0,0.1); padding:2px 8px; border-radius:10px; font-size:0.8rem;">3</span></button>
                    <button class="nav-btn" onclick="switchTab('documents')"><span style="display:flex; align-items:center; gap:12px;"><i class="fas fa-file-pdf"></i> Documents</span> <span style="background:rgba(0,0,0,0.1); padding:2px 8px; border-radius:10px; font-size:0.8rem;">5</span></button>
                </div>
            </div>
        </div>

        <!-- RIGHT PILLAR: Dynamic Clinical Content -->
        <div>
            
            <!-- VIEW: OVERVIEW -->
            <div id="view-overview" class="c-box" style="display:none;">
                <div class="cb-head">
                    <div><i class="fas fa-columns" style="color:var(--clr-primary);"></i> Patient Overview</div>
                </div>
                <div style="padding:16px;">
                    <p style="color:var(--clr-text-secondary); font-size:0.95rem; line-height:1.6;">Welcome to the unified patient portal. This single-page application guarantees that all clinical modules for Rahul Kumar remain accessible without reloading or navigating to external pages.</p>
                </div>
            </div>

            <!-- VIEW: SOAP NOTES -->
            <div id="view-soap" class="c-box">
                <div class="cb-head">
                    <div><i class="fas fa-notes-medical" style="color:var(--clr-primary);"></i> Clinical Note (SOAP)</div>
                    <button style="border:1px solid #16A34A; background:#F0FDF4; color:#16A34A; padding:6px 12px; border-radius:20px; font-weight:700; cursor:pointer;"><i class="fas fa-history"></i> Amendment History</button>
                </div>

                <div class="soap-box">
                    <div class="soap-head">S — Subjective <span style="font-size:0.8rem; color:var(--clr-text-secondary); font-weight:600; font-family:monospace;">Patient-reported symptoms</span></div>
                    <div class="soap-body">
                        <textarea placeholder="Record patient symptoms, history, and concerns...">Patient reports moderate fever and persistent dry cough for the last 48 hours. Mentions mild fatigue.</textarea>
                    </div>
                </div>

                <div class="soap-box">
                    <div class="soap-head">O — Objective <span style="font-size:0.8rem; color:var(--clr-text-secondary); font-weight:600; font-family:monospace;">Examination & vitals</span></div>
                    <div class="soap-body">
                        <textarea placeholder="Observable findings, exam results...">Patient appears mildly fatigued. Lungs clear to auscultation. Vitals stable.</textarea>
                    </div>
                </div>

                <div class="soap-box">
                    <div class="soap-head" style="background:#FFFBEB;">A — Assessment <span style="font-size:0.8rem; color:var(--clr-text-secondary); font-weight:600; font-family:monospace;">Clinical diagnosis</span></div>
                    <div class="soap-body">
                        <textarea placeholder="Clinical assessment / diagnosis...">Suspected mild viral upper respiratory infection.</textarea>
                    </div>
                </div>

                <div class="soap-box">
                    <div class="soap-head" style="background:#F0FDF4;">P — Plan <span style="font-size:0.8rem; color:var(--clr-text-secondary); font-weight:600; font-family:monospace;">Treatment & advice</span></div>
                    <div class="soap-body">
                        <textarea placeholder="Treatment, medicines, follow-up...">Prescribed antipyretics and antihistamines for 3 days. Advised adequate hydration and rest. Follow-up in 1 week if symptoms persist.</textarea>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; padding-top:24px; border-top:1px solid var(--clr-border);">
                    <button style="background:white; border:1px solid var(--clr-border); padding:12px 24px; border-radius:var(--radius-sm); font-weight:700; cursor:pointer;">Save Draft</button>
                    <button class="btn-primary" style="background:#16A34A;"><i class="fas fa-lock"></i> Finalize Clinical Note</button>
                </div>
            </div>

            <!-- VIEW: VITALS -->
            <div id="view-vitals" class="c-box" style="display:none;">
                <div class="cb-head">
                    <div><i class="fas fa-heartbeat" style="color:#DC2626;"></i> Vital Signs</div>
                    <button class="btn-primary"><i class="fas fa-plus"></i> Record Vitals</button>
                </div>

                <div class="vitals-grid">
                    <div class="vital-card">
                        <div class="vc-icon" style="color:#DC2626;"><i class="fas fa-tint"></i></div>
                        <div class="vc-val">120/80</div>
                        <div class="vc-label">Blood Pressure</div>
                    </div>
                    <div class="vital-card">
                        <div class="vc-icon" style="color:#EA580C;"><i class="fas fa-heartbeat"></i></div>
                        <div class="vc-val">78</div>
                        <div class="vc-label">Pulse (bpm)</div>
                    </div>
                    <div class="vital-card">
                        <div class="vc-icon" style="color:#CA8A04;"><i class="fas fa-thermometer-half"></i></div>
                        <div class="vc-val">98.6</div>
                        <div class="vc-label">Temp (°F)</div>
                    </div>
                    <div class="vital-card">
                        <div class="vc-icon" style="color:#0284C7;"><i class="fas fa-lungs"></i></div>
                        <div class="vc-val">98%</div>
                        <div class="vc-label">SpO2</div>
                    </div>
                </div>

                <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-primary); margin:32px 0 16px 0;">Vital History</div>
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:#F8FAFC; text-align:left; font-size:0.85rem; color:var(--clr-text-secondary);">
                        <tr><th style="padding:12px;">Date</th><th>BP</th><th>Pulse</th><th>Temp</th><th>SpO2</th></tr>
                    </thead>
                    <tbody style="font-weight:700; color:var(--clr-text-primary);">
                        <tr style="border-bottom:1px solid var(--clr-border);">
                            <td style="padding:12px;">05 Sep 2026</td><td>120/80</td><td>78</td><td>98.6°F</td><td>98%</td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--clr-border);">
                            <td style="padding:12px;">02 Sep 2026</td><td>118/78</td><td>76</td><td>98.4°F</td><td>99%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- VIEW: CLINICAL TIMELINE -->
            <div id="view-timeline" class="c-box" style="display:none;">
                <div class="cb-head">
                    <div><i class="fas fa-stream" style="color:var(--clr-primary);"></i> Longitudinal Patient Timeline</div>
                </div>

                <div class="timeline">
                    
                    <!-- Day 1 -->
                    <div class="tl-entry">
                        <div class="tl-dot"></div>
                        <div class="tl-date">05 SEP 2026</div>
                        
                        <div class="tl-card">
                            <div class="tl-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-calendar-check"></i></div>
                            <div>
                                <div style="font-weight:800; font-size:1.05rem;">Video Consultation Completed</div>
                                <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-top:4px;">10:42 AM — 10:55 AM · <?php echo htmlspecialchars($docName); ?></div>
                            </div>
                        </div>

                        <div class="tl-card">
                            <div class="tl-icon" style="background:#FEF9C3; color:#CA8A04;"><i class="fas fa-diagnoses"></i></div>
                            <div>
                                <div style="font-weight:800; font-size:1.05rem;">Diagnosis Recorded</div>
                                <div style="font-size:0.9rem; margin-top:4px; font-weight:700; color:var(--clr-text-primary);">Primary: Viral Infection</div>
                            </div>
                        </div>

                        <div class="tl-card">
                            <div class="tl-icon" style="background:#F0FDF4; color:#16A34A;"><i class="fas fa-pills"></i></div>
                            <div>
                                <div style="display:flex; justify-content:space-between;">
                                    <div style="font-weight:800; font-size:1.05rem;">Prescription Issued</div>
                                    <button style="border:1px solid #16A34A; background:white; color:#16A34A; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.8rem; cursor:pointer;">View</button>
                                </div>
                                <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-top:4px; font-family:monospace;">RX-20260905-1042</div>
                            </div>
                        </div>
                    </div>

                    <!-- Day 2 -->
                    <div class="tl-entry">
                        <div class="tl-dot"></div>
                        <div class="tl-date">20 AUG 2026</div>
                        
                        <div class="tl-card">
                            <div class="tl-icon" style="background:#F3E8FF; color:#9333EA;"><i class="fas fa-file-pdf"></i></div>
                            <div>
                                <div style="display:flex; justify-content:space-between;">
                                    <div style="font-weight:800; font-size:1.05rem;">Medical Document Uploaded</div>
                                    <button style="border:1px solid #9333EA; background:white; color:#9333EA; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.8rem; cursor:pointer;">View</button>
                                </div>
                                <div style="font-size:0.9rem; margin-top:4px;"><i class="fas fa-flask" style="color:#DC2626;"></i> Blood_Test_Report.pdf</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- VIEW: DIAGNOSES -->
            <div id="view-diagnoses" class="c-box" style="display:none;">
                <div class="cb-head">
                    <div><i class="fas fa-diagnoses" style="color:#9333EA;"></i> Diagnoses</div>
                    <button class="btn-primary" style="background:#9333EA; padding:8px 16px;"><i class="fas fa-plus"></i> Add</button>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; margin-top:16px;">
                        <thead style="background:#F8FAFC; text-align:left; font-size:0.85rem; color:var(--clr-text-secondary);">
                            <tr><th style="padding:12px; border-bottom:1px solid var(--clr-border);">ICD-10 Code</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Diagnosis Name</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Date Recorded</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Status</th></tr>
                        </thead>
                        <tbody style="font-weight:600; color:var(--clr-text-primary); font-size:0.95rem;">
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; color:var(--clr-primary); font-family:monospace;">I10</td>
                                <td style="padding:12px;">Essential (primary) hypertension</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">15 Jan 2025</td>
                                <td style="padding:12px;"><span style="background:#F0FDF4; color:#16A34A; padding:4px 8px; border-radius:4px; font-size:0.8rem;">Active</span></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; color:var(--clr-primary); font-family:monospace;">J06.9</td>
                                <td style="padding:12px;">Acute upper respiratory infection</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">05 Sep 2026</td>
                                <td style="padding:12px;"><span style="background:#F0FDF4; color:#16A34A; padding:4px 8px; border-radius:4px; font-size:0.8rem;">Active</span></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; color:var(--clr-primary); font-family:monospace;">G43.909</td>
                                <td style="padding:12px;">Migraine, unspecified</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">10 Nov 2024</td>
                                <td style="padding:12px;"><span style="background:#F1F5F9; color:var(--clr-text-secondary); padding:4px 8px; border-radius:4px; font-size:0.8rem;">Resolved</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VIEW: DOCUMENTS -->
            <div id="view-documents" class="c-box" style="display:none;">
                <div class="cb-head">
                    <div><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Clinical Documents</div>
                    <button class="btn-primary" style="background:#DC2626; padding:8px 16px;"><i class="fas fa-upload"></i> Upload</button>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; margin-top:16px;">
                        <thead style="background:#F8FAFC; text-align:left; font-size:0.85rem; color:var(--clr-text-secondary);">
                            <tr><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Document Name</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Category</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Date</th><th style="padding:12px; border-bottom:1px solid var(--clr-border);">Action</th></tr>
                        </thead>
                        <tbody style="font-weight:600; color:var(--clr-text-primary); font-size:0.95rem;">
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Blood_Report_Sep.pdf</td>
                                <td style="padding:12px;">Lab Report</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">05 Sep 2026</td>
                                <td style="padding:12px;"><button style="background:transparent; border:1px solid var(--clr-primary); color:var(--clr-primary); padding:4px 12px; border-radius:4px; cursor:pointer;">View</button></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-image" style="color:#2563EB;"></i> Chest_XRay_Front.png</td>
                                <td style="padding:12px;">Imaging</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">01 Sep 2026</td>
                                <td style="padding:12px;"><button style="background:transparent; border:1px solid var(--clr-primary); color:var(--clr-primary); padding:4px 12px; border-radius:4px; cursor:pointer;">View</button></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-prescription" style="color:#16A34A;"></i> Old_Prescription_2025.pdf</td>
                                <td style="padding:12px;">Prescription</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">15 Jan 2025</td>
                                <td style="padding:12px;"><button style="background:transparent; border:1px solid var(--clr-primary); color:var(--clr-primary); padding:4px 12px; border-radius:4px; cursor:pointer;">View</button></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-medical-alt" style="color:#9333EA;"></i> Medical_History_Intake.pdf</td>
                                <td style="padding:12px;">Intake Form</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">10 Nov 2024</td>
                                <td style="padding:12px;"><button style="background:transparent; border:1px solid var(--clr-primary); color:var(--clr-primary); padding:4px 12px; border-radius:4px; cursor:pointer;">View</button></td>
                            </tr>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Neuro_Assessment.pdf</td>
                                <td style="padding:12px;">Evaluation</td>
                                <td style="padding:12px; color:var(--clr-text-secondary);">10 Nov 2024</td>
                                <td style="padding:12px;"><button style="background:transparent; border:1px solid var(--clr-primary); color:var(--clr-primary); padding:4px 12px; border-radius:4px; cursor:pointer;">View</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.c-box[id^="view-"]').forEach(el => {
            el.style.display = 'none';
        });
        
        document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');

        let target = document.getElementById('view-' + tab);
        if(target) target.style.display = 'block';
    }
</script>

</body>
</html>
