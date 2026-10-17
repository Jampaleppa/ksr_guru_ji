<?php
require_once 'helper.php';
// Simulated doctor profile
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'Dr. Shivukumar Rathod';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile & Verification | Doctor Assist</title>
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

        .container-main { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 350px 1fr; gap: 32px; }
        
        /* Left Column: Fixed Profile Info */
        .left-col { display: flex; flex-direction: column; gap: 24px; }
        
        .profile-hero { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; text-align: center; position: relative; }
        .ph-photo { width: 120px; height: 120px; border-radius: 50%; background: #E2E8F0; margin: 0 auto 16px; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #94A3B8; overflow: hidden; position: relative; }
        .ph-photo img { width: 100%; height: 100%; object-fit: cover; }
        .ph-verify-badge { position: absolute; bottom: 8px; right: 8px; background: #16A34A; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; border: 2px solid white; }
        
        .ph-name { font-size: 1.4rem; font-weight: 800; color: var(--clr-text-primary); margin-bottom: 4px; }
        .ph-spec { font-size: 0.95rem; font-weight: 600; color: var(--clr-text-secondary); margin-bottom: 12px; }
        .ph-meta { display: flex; justify-content: center; gap: 16px; font-size: 0.85rem; font-weight: 600; color: var(--clr-text-secondary); margin-bottom: 24px; }
        
        .btn-action { background: white; border: 1px solid var(--clr-border); padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; color: var(--clr-text-primary); display: inline-block; text-decoration: none; }
        .btn-action:hover { background: #F8FAFC; border-color: var(--clr-primary); color: var(--clr-primary); }
        .btn-primary { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }
        .btn-primary:hover { background: var(--clr-primary-dark); color: white; border-color: var(--clr-primary-dark); }

        .status-box { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 24px; }
        .sb-head { display: flex; align-items: center; gap: 8px; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 16px; font-size: 1.1rem; }
        
        .verify-list { list-style: none; padding: 0; margin: 0; }
        .verify-list li { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--clr-border); font-size: 0.95rem; }
        .verify-list li:last-child { border-bottom: none; }
        .vs-badge { color: #16A34A; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; }

        .progress-bar-container { background: #E2E8F0; height: 8px; border-radius: 4px; margin: 12px 0; overflow: hidden; }
        .progress-bar { background: var(--clr-primary); height: 100%; width: 92%; border-radius: 4px; }

        /* Right Column: Settings Sections */
        .right-col { display: flex; flex-direction: column; gap: 24px; }
        
        .sec-box { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .sb-titlebar { background: #F8FAFC; padding: 16px 24px; border-bottom: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; font-weight: 800; color: var(--clr-primary-dark); font-size: 1.1rem; }
        .sb-content { padding: 24px; }

        .data-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .dg-item label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 4px; }
        .dg-item .val { font-size: 1rem; font-weight: 600; color: var(--clr-text-primary); }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-weight: 700; color: var(--clr-text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); vertical-align: middle; font-size: 0.95rem; }
        
        .badge-verified { background: #F0FDF4; color: #16A34A; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; }

        /* Document Center specific */
        .doc-row { display: flex; justify-content: space-between; align-items: center; padding: 16px; border: 1px solid var(--clr-border); border-radius: 8px; margin-bottom: 12px; }
        
        /* Consultation Settings */
        .consult-toggle { display: flex; justify-content: space-between; align-items: center; padding: 16px; border: 1px solid var(--clr-border); border-radius: 8px; margin-bottom: 16px; }
        .fee-breakdown { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 16px; background: #F8FAFC; padding: 16px; border-radius: 8px; }
        
        /* Toggle Switch */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #16A34A; }
        input:checked + .slider:before { transform: translateX(20px); }

        .hidden-mask { letter-spacing: 2px; }
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
            <div class="navbar-links" style="display: flex; gap: 16px;">
                <a href="doctor-shivukumar-dashboard.php" style="font-weight:600;"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="doctor-shivukumar-appointments.php" style="font-weight:600;"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="doctor-shivukumar-patients.php" style="font-weight:600;"><i class="fas fa-users"></i> Patients</a>
                <a href="doctor-shivukumar-earnings.php" style="font-weight:600;"><i class="fas fa-wallet"></i> Earnings</a>
                <a href="doctor-shivukumar-profile-settings.php" class="active" style="font-weight:600;"><i class="fas fa-id-card"></i> Profile</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="doctor-shivukumar-profile-settings.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></a>
        </div>
    </div>
</nav>

<div class="container-main">

    <!-- LEFT COLUMN -->
    <div class="left-col">
        
        <div class="profile-hero">
            <div class="ph-photo">
                <i class="fas fa-user-md" style="opacity: 0.3;"></i>
                <div class="ph-verify-badge" title="Verified Professional"><i class="fas fa-check"></i></div>
            </div>
            
            <div class="ph-name"><?php echo htmlspecialchars($docName); ?></div>
            <div class="ph-spec">MBBS, MD — Pediatrics</div>
            
            <div class="ph-meta">
                <span>⭐ 4.8 (324)</span>
                <span>🩺 12 Yrs Exp</span>
            </div>

            <div style="display:flex; gap:12px; justify-content:center;">
                <button class="btn-action" style="flex:1;"><i class="fas fa-pencil-alt"></i> Edit</button>
                <button class="btn-action btn-primary" style="flex:1;"><i class="fas fa-eye"></i> Preview</button>
            </div>
        </div>

        <div class="status-box">
            <div class="sb-head"><i class="fas fa-shield-alt text-primary"></i> Verification Status</div>
            <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:8px; padding:12px; text-align:center; margin-bottom:16px;">
                <span style="color:#166534; font-weight:800; font-size:1.1rem;"><i class="fas fa-check-circle"></i> VERIFIED PLATFORM DOCTOR</span>
            </div>
            <ul class="verify-list">
                <li><span>Identity Verification</span> <span class="vs-badge"><i class="fas fa-check"></i> Verified</span></li>
                <li><span>Medical Registration</span> <span class="vs-badge"><i class="fas fa-check"></i> Verified</span></li>
                <li><span>Qualifications</span> <span class="vs-badge"><i class="fas fa-check"></i> Verified</span></li>
            </ul>
        </div>

        <div class="status-box">
            <div class="sb-head">Profile Completion</div>
            <div style="font-size:2rem; font-weight:900; color:var(--clr-text-primary);">92%</div>
            <div class="progress-bar-container">
                <div class="progress-bar"></div>
            </div>
            <ul class="verify-list" style="margin-top:16px;">
                <li><span>Personal Information</span> <i class="fas fa-check-circle text-success" style="color:#16A34A;"></i></li>
                <li><span>Qualifications</span> <i class="fas fa-check-circle text-success" style="color:#16A34A;"></i></li>
                <li><span>Clinic Information</span> <i class="far fa-circle text-secondary" style="color:#94A3B8;"></i></li>
            </ul>
            <button class="btn-action" style="width:100%; margin-top:16px;">Complete Remaining 8%</button>
        </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="right-col">
        
        <!-- Personal Information -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="far fa-user"></i> Personal Information</span>
                <button class="btn-action" style="padding:4px 12px; font-size:0.8rem;">Edit</button>
            </div>
            <div class="sb-content">
                <div class="data-grid">
                    <div class="dg-item">
                        <label>Full Name</label>
                        <div class="val"><?php echo htmlspecialchars($docName); ?></div>
                    </div>
                    <div class="dg-item">
                        <label>Gender & DOB</label>
                        <div class="val">Male • **/**/****</div>
                    </div>
                    <div class="dg-item">
                        <label>Registered Mobile</label>
                        <div class="val">+91 ******4821</div>
                    </div>
                    <div class="dg-item">
                        <label>Registered Email</label>
                        <div class="val">doctor@example.com</div>
                    </div>
                </div>
                <div style="margin-top:16px; font-size:0.8rem; color:var(--clr-text-secondary);"><i class="fas fa-lock"></i> Sensitive information does not appear on your public profile.</div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="fas fa-stethoscope"></i> Professional Information</span>
                <button class="btn-action" style="padding:4px 12px; font-size:0.8rem;">Edit</button>
            </div>
            <div class="sb-content">
                <div class="data-grid">
                    <div class="dg-item">
                        <label>Primary Specialty</label>
                        <div class="val">Pediatrics</div>
                    </div>
                    <div class="dg-item">
                        <label>Experience</label>
                        <div class="val">12 Years</div>
                    </div>
                    <div class="dg-item" style="grid-column: span 2;">
                        <label>Languages Spoken</label>
                        <div class="val">English, Kannada, Hindi</div>
                    </div>
                    <div class="dg-item" style="grid-column: span 2;">
                        <label>Professional Bio</label>
                        <div class="val" style="font-weight:400; line-height:1.6;"><?php echo htmlspecialchars($docName); ?> is a trusted Neurology and paralysis specialist focusing on neuro-rehabilitation, stroke recovery, and advanced nerve therapies... <a href="#"  style="color:var(--clr-primary);">Read more</a></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Registration -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="fas fa-certificate"></i> Medical Registration</span>
                <span class="badge-verified"><i class="fas fa-check-double"></i> VERIFIED</span>
            </div>
            <div class="sb-content">
                <div class="data-grid">
                    <div class="dg-item" style="grid-column: span 2;">
                        <label>Registration Authority (Council)</label>
                        <div class="val">Karnataka Medical Council</div>
                    </div>
                    <div class="dg-item">
                        <label>Registration Number</label>
                        <div class="val hidden-mask">KMC-********21</div>
                    </div>
                    <div class="dg-item">
                        <label>Registration Type</label>
                        <div class="val">Permanent</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Qualifications -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="fas fa-graduation-cap"></i> Qualifications</span>
                <button class="btn-action btn-primary" style="padding:4px 12px; font-size:0.8rem;"><i class="fas fa-plus"></i> Add</button>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Degree</th>
                        <th>Institution</th>
                        <th>Year</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight:800;">MBBS</td>
                        <td>Rajiv Gandhi University of Health Sciences</td>
                        <td>2011</td>
                        <td><span class="badge-verified">VERIFIED</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight:800;">MD (Gen Med)</td>
                        <td>Bangalore Medical College</td>
                        <td>2014</td>
                        <td><span class="badge-verified">VERIFIED</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Consultation Settings -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="fas fa-sliders-h"></i> Consultation Settings</span>
            </div>
            <div class="sb-content">
                
                <!-- Video Setting -->
                <div class="consult-toggle">
                    <div>
                        <div style="font-size:1.1rem; font-weight:800;"><i class="fas fa-video text-primary"></i> Video Consultations</div>
                        <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-top:4px;">Allow patients to book live video appointments.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="fee-breakdown">
                    <div>
                        <div style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Doctor Fee</div>
                        <div style="font-size:1.1rem; font-weight:800;">₹125 <button style="border:none; background:transparent; color:var(--clr-primary); cursor:pointer;"><i class="fas fa-edit"></i></button></div>
                    </div>
                    <div>
                        <div style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Platform Charge</div>
                        <div style="font-size:1.1rem; font-weight:800;">+ ₹15 <i class="fas fa-lock" style="font-size:0.7rem; color:var(--clr-text-secondary);" title="Fixed by platform"></i></div>
                    </div>
                    <div style="border-left:1px solid #CBD5E1; padding-left:16px;">
                        <div style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Patient Pays</div>
                        <div style="font-size:1.2rem; font-weight:900; color:var(--clr-text-primary);">₹140</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Security -->
        <div class="sec-box">
            <div class="sb-titlebar">
                <span><i class="fas fa-lock"></i> Security & Sessions</span>
            </div>
            <div class="sb-content">
                <div class="doc-row" style="background:#F8FAFC;">
                    <div>
                        <div style="font-weight:700;">Two-Factor Authentication</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">Protect your account with an extra layer of security.</div>
                    </div>
                    <div>
                        <span class="badge-verified" style="margin-right:12px;">ENABLED</span>
                        <button class="btn-action">Manage</button>
                    </div>
                </div>
                <div class="doc-row">
                    <div>
                        <div style="font-weight:700;">Active Sessions (3)</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">You are currently logged into 3 devices.</div>
                    </div>
                    <button class="btn-action">View All</button>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
