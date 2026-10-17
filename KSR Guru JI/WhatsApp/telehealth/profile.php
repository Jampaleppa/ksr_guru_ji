<?php
require_once 'helper.php';

// Mock Patient State
$patientName = 'Jampaleppa Rathod';
$patientId = 'DA-789324';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
$patientDob = '12 Aug 1985';
$patientBlood = 'O+';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; padding-bottom: 100px; }
        
        .profile-layout { max-width: 1200px; margin: 40px auto; padding: 0 16px; display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start; }

        /* Sidebar Navigation */
        .sidebar { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 20px 0; position: sticky; top: 24px; }
        .sb-user { padding: 0 20px 20px 20px; border-bottom: 1px solid var(--clr-border); margin-bottom: 12px; display: flex; align-items: center; gap: 16px; }
        .sb-avatar { width: 48px; height: 48px; background: var(--clr-primary-light); color: var(--clr-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; }
        
        .sb-link { display: flex; align-items: center; gap: 12px; padding: 12px 24px; font-weight: 600; color: var(--clr-text-secondary); text-decoration: none; transition: 0.2s; border-left: 3px solid transparent; }
        .sb-link:hover { background: #F8FAFC; color: var(--clr-text-primary); }
        .sb-link.active { background: var(--clr-primary-light); color: var(--clr-primary); border-left-color: var(--clr-primary); }
        .sb-icon { width: 20px; text-align: center; }

        .sb-divider { border-top: 1px dashed var(--clr-border); margin: 16px 0; }

        /* Main Content */
        .dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .dh-title { font-size: 2rem; font-weight: 800; color: var(--clr-primary-dark); }
        .dh-sub { font-size: 1.1rem; color: var(--clr-text-secondary); margin-top: 4px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); padding: 24px; text-align: center; box-shadow: var(--shadow-sm); transition: 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--clr-primary); }
        .st-icon { font-size: 2rem; color: var(--clr-primary); margin-bottom: 12px; }
        .st-num { font-size: 2.25rem; font-weight: 800; color: var(--clr-text-primary); line-height: 1.2; }
        .st-label { font-size: 0.95rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 1px; }

        /* Modular Grid */
        .mod-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }

        .m-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; display: flex; flex-direction: column; }
        .mc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; }
        .mc-title { font-weight: 800; font-size: 1.05rem; color: var(--clr-text-primary); text-transform: uppercase; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }
        .mc-action { background: #F8FAFC; border: 1px solid var(--clr-border); color: var(--clr-text-primary); padding: 6px 12px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 700; cursor: pointer; }

        /* Card Interiors */
        .kv-grid { display: grid; grid-template-columns: auto 1fr; gap: x-12px; row-gap: 8px; column-gap: 24px; font-size: 0.95rem; }
        .kv-key { color: var(--clr-text-secondary); font-weight: 600; }
        .kv-val { color: var(--clr-text-primary); font-weight: 700; }

        .appt-card { background: #F8FAFC; border: 1px solid #E2E8F0; padding: 16px; border-radius: var(--radius-sm); margin-bottom: 16px; }
        .ac-doc { font-weight: 800; font-size: 1.1rem; color: var(--clr-text-primary); margin-bottom: 4px; }
        .ac-spec { font-size: 0.9rem; color: var(--clr-text-secondary); margin-bottom: 12px; }
        .ac-time { display: flex; gap: 16px; font-size: 0.95rem; font-weight: 600; color: var(--clr-primary-dark); }
        .ac-countdown { background: #DCFCE7; color: #166534; display: inline-block; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.85rem; font-weight: 700; margin-top: 12px; }

        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--clr-border); }
        .list-item:last-child { border-bottom: none; }
        .li-main { font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 12px; }
        .li-sub { font-size: 0.85rem; color: var(--clr-text-secondary); font-weight: normal; margin-top: 2px; }
        
        .btn-foot { margin-top: auto; padding-top: 20px; text-align: center; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 16px; }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 600px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; transform: translateY(20px); transition: 0.3s; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        .m-head { padding: 20px 24px; border-bottom: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.25rem; color: var(--clr-primary-dark); }
        
        .input-group { margin-bottom: 16px; }
        .input-group label { display: block; font-weight: 700; font-size: 0.9rem; color: var(--clr-text-secondary); margin-bottom: 8px; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-family: inherit; font-size: 0.95rem; }
        
        /* Security UI */
        .sec-box { border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; }
        .sec-title { font-weight: 700; font-size: 0.95rem; color: var(--clr-text-primary); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }

        @media (max-width: 950px) {
            .profile-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; display: none; } /* On mobile, move to a hamburger menu or bottom nav */
            .mod-grid { grid-template-columns: 1fr; gap: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="find-doctor.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Doctors</a>
                <a href="my-appointments.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Appointments</a>
                <a href="pharmacy.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Pharmacy</a>
                <a href="notifications.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">🔔 <span style="background:#EF4444; color:white; border-radius:10px; padding:0 6px; font-size:0.75rem;">3</span></a>
            </div>
        </div>
        <a href="profile.php" style="background:var(--clr-primary); color:white; padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-circle"></i> Profile</a>
    </div>
</nav>

<div class="profile-layout">
    
    <!-- 11. SIDEBAR MENU -->
    <div class="sidebar">
        <div class="sb-user">
            <div class="sb-avatar">JR</div>
            <div>
                <div style="font-weight:800; color:var(--clr-text-primary);"><?php echo $patientName; ?></div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary); font-weight:600;">Patient ID: <?php echo $patientId; ?></div>
            </div>
        </div>

        <a href="#" class="sb-link active" onclick="openModal('edit-modal')"><span class="sb-icon"><i class="fas fa-user"></i></span> Personal Information</a>
        <a href="#family-members" class="sb-link"><span class="sb-icon"><i class="fas fa-users"></i></span> Family Members</a>
        <a href="#medical-info" class="sb-link"><span class="sb-icon"><i class="fas fa-file-medical-alt"></i></span> Medical Information</a>
        <a href="#health-documents" class="sb-link"><span class="sb-icon"><i class="fas fa-folder-open"></i></span> Health Documents</a>
        
        <div class="sb-divider"></div>
        
        <a href="my-appointments.php" class="sb-link"><span class="sb-icon"><i class="fas fa-calendar-check"></i></span> My Appointments</a>
        <a href="my-prescriptions.php" class="sb-link"><span class="sb-icon"><i class="fas fa-prescription-bottle-alt"></i></span> My Prescriptions</a>
        <a href="my-orders.php" class="sb-link"><span class="sb-icon"><i class="fas fa-box"></i></span> My Orders</a>
        
        <div class="sb-divider"></div>
        
        <a href="#" class="sb-link" onclick="openModal('payment-modal')"><span class="sb-icon"><i class="fas fa-credit-card"></i></span> Payments & Invoices</a>
        <a href="notifications.php" class="sb-link"><span class="sb-icon"><i class="fas fa-bell"></i></span> Notifications</a>
        <a href="#" class="sb-link" onclick="openModal('security-modal')"><span class="sb-icon"><i class="fas fa-shield-alt"></i></span> Privacy & Security</a>
        <a href="#"  class="sb-link"><span class="sb-icon"><i class="fas fa-cog"></i></span> Settings</a>
        
        <div class="sb-divider"></div>
        
        <a href="index.php" class="sb-link" style="color:#DC2626;"><span class="sb-icon"><i class="fas fa-sign-out-alt"></i></span> Logout</a>
    </div>

    <!-- MAIN DASHBOARD -->
    <div>
        <div class="dash-header">
            <div>
                <h1 class="dh-title">Good Afternoon 👋</h1>
                <p class="dh-sub">Welcome back to your health dashboard.</p>
            </div>
            <div>
                <button class="mc-action" onclick="openModal('edit-modal')"><i class="fas fa-edit"></i> Edit Profile</button>
            </div>
        </div>

        <!-- 2. Health Stats -->
        <div class="stats-grid">
            <div class="stat-card" onclick="location.href='my-appointments.php'">
                <div class="st-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="st-num">2</div>
                <div class="st-label">Upcoming Appointments</div>
            </div>
            <div class="stat-card" style="border-color:#E9D5FF;" onclick="location.href='my-prescriptions.php'">
                <div class="st-icon" style="color:#9333EA;"><i class="fas fa-prescription"></i></div>
                <div class="st-num">3</div>
                <div class="st-label">Active Prescriptions</div>
            </div>
            <div class="stat-card" style="border-color:#FEF08A;" onclick="location.href='my-orders.php'">
                <div class="st-icon" style="color:#CA8A04;"><i class="fas fa-box-open"></i></div>
                <div class="st-num">1</div>
                <div class="st-label">Active Medicine Order</div>
            </div>
        </div>

        <div class="mod-grid">
            
            <!-- 3. Profile Brief -->
            <div class="m-card">
                <div class="mc-header">
                    <div class="mc-title"><i class="fas fa-id-card"></i> Patient Profile</div>
                </div>
                <div class="kv-grid">
                    <div class="kv-key">Full Name</div><div class="kv-val"><?php echo $patientName; ?></div>
                    <div class="kv-key">Patient ID</div><div class="kv-val" style="font-family:monospace; color:var(--clr-primary);"><?php echo $patientId; ?></div>
                    <div class="kv-key">DOB</div><div class="kv-val"><?php echo $patientDob; ?></div>
                    <div class="kv-key">Gender</div><div class="kv-val">Male</div>
                    <div class="kv-key">Mobile</div><div class="kv-val">+91 98765 43210</div>
                    <div class="kv-key">Email</div><div class="kv-val">user@example.com</div>
                </div>
            </div>

            <!-- 4. Next Appointment -->
            <div class="m-card">
                <div class="mc-header">
                    <div class="mc-title"><i class="fas fa-calendar-check"></i> Next Appointment</div>
                </div>
                <div class="appt-card">
                    <div class="ac-doc"><?php echo htmlspecialchars($docName); ?> <span style="font-size:0.85rem; color:#16A34A;"><i class="fas fa-check-circle"></i></span></div>
                    <div class="ac-spec">General Physician · Video Consultation</div>
                    <div class="ac-time">
                        <div><i class="far fa-calendar"></i> 06 Sep 2026</div>
                        <div><i class="far fa-clock"></i> 10:30 AM</div>
                    </div>
                    <div class="ac-countdown">Starts in: 18h 02m</div>
                </div>
                <div class="btn-foot" style="display:flex; gap:12px;">
                    <button class="mc-action" style="flex:1;">View Details</button>
                    <button class="mc-action" style="flex:1; border-color:var(--clr-primary); color:var(--clr-primary);" onclick="openCameraTest()"><i class="fas fa-video"></i> Test Camera</button>
                </div>
            </div>

            <!-- 8. Medical Info -->
            <div class="m-card" id="medical-info">
                <div class="mc-header">
                    <div class="mc-title"><i class="fas fa-heartbeat"></i> Medical Info</div>
                    <button class="mc-action">Edit</button>
                </div>
                <div class="kv-grid">
                    <div class="kv-key">Blood Group</div><div class="kv-val" style="color:#DC2626; font-size:1.1rem;"><?php echo $patientBlood; ?></div>
                    <div class="kv-key">Allergies</div><div class="kv-val" style="color:var(--clr-text-secondary); font-weight:normal;">Not provided</div>
                    <div class="kv-key">Conditions</div><div class="kv-val" style="color:var(--clr-text-secondary); font-weight:normal;">Not provided</div>
                    <div class="kv-key" style="grid-column:1/-1; margin-top:12px; border-top:1px dashed var(--clr-border); padding-top:8px;">Emergency Contact</div>
                    <div class="kv-key">Name</div><div class="kv-val">[Hidden]</div>
                    <div class="kv-key">Relation</div><div class="kv-val">[Hidden]</div>
                </div>
            </div>

            <!-- 9. Health Documents -->
            <div class="m-card" id="health-documents">
                <div class="mc-header">
                    <div class="mc-title"><i class="fas fa-folder-open"></i> Health Documents</div>
                    <button class="mc-action" onclick="alert('Opening File Selection Dialog...')">+ Upload</button>
                </div>
                
                <div class="list-item" style="cursor:pointer;" onclick="alert('Opening Prescription_RX-001245.pdf...')">
                    <div class="li-main"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> <div>Prescription_RX-001245<div class="li-sub">05 Sep 2026</div></div></div>
                </div>
                <div class="list-item" style="cursor:pointer;" onclick="alert('Opening Blood_Report.pdf...')">
                    <div class="li-main"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> <div>Blood_Report<div class="li-sub">02 Sep 2026</div></div></div>
                </div>
                <div class="list-item" style="cursor:pointer;" onclick="alert('Opening Skin_Rash_Scan.jpg...')">
                    <div class="li-main"><i class="fas fa-file-image" style="color:#0284C7;"></i> <div>Skin_Rash_Scan.jpg<div class="li-sub">Shared w/ Doctor Only</div></div></div>
                </div>

                <div class="btn-foot">
                    <button class="mc-action" style="width:100%;" onclick="alert('Opening Document Vault...')">View All Documents</button>
                </div>
            </div>

            <!-- 7. Family Members -->
            <div class="m-card" id="family-members" style="grid-column: 1 / -1; display:block;">
                <div class="mc-header">
                    <div class="mc-title"><i class="fas fa-users"></i> Family & Patient Profiles</div>
                    <button class="mc-action">+ Add Member</button>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:16px;">
                    
                    <div style="border:1px solid var(--clr-primary); border-radius:var(--radius-sm); padding:16px; background:var(--clr-primary-light);">
                        <div style="font-weight:800; color:var(--clr-primary-dark); font-size:1.1rem; display:flex; justify-content:space-between;">Self <i class="fas fa-check-circle" style="color:var(--clr-primary);"></i></div>
                        <div style="font-size:0.9rem; color:var(--clr-primary); margin-top:4px;">Primary Patient • Active</div>
                    </div>

                    <div style="border:1px solid var(--clr-border); border-radius:var(--radius-sm); padding:16px; background:white;">
                        <div style="font-weight:800; color:var(--clr-text-primary); font-size:1.1rem; display:flex; justify-content:space-between;">Shreya Rathod</div>
                        <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-top:4px;">Child • Active</div>
                    </div>

                    <div style="border:1px dashed var(--clr-border); border-radius:var(--radius-sm); padding:16px; background:#F8FAFC; text-align:center; display:flex; align-items:center; justify-content:center; cursor:pointer;" class="mc-action">
                        + Add Family Member
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- 12. EDIT PROFILE MODAL -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal-card">
        <div class="m-head">
            <div>Edit Personal Information</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('edit-modal')">&times;</button>
        </div>
        <div style="padding:24px; overflow-y:auto;">
            <div style="text-align:center; margin-bottom:24px;">
                <div class="sb-avatar" style="width:80px; height:80px; font-size:2rem; margin:0 auto 12px auto;">JR</div>
                <button class="mc-action">Change Photo</button>
            </div>
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" value="<?php echo $patientName; ?>">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="input-group">
                    <label>Date of Birth</label>
                    <input type="text" value="<?php echo $patientDob; ?>">
                </div>
                <div class="input-group">
                    <label>Gender</label>
                    <select><option selected>Male</option><option>Female</option><option>Other</option></select>
                </div>
            </div>
            <div class="input-group">
                <label>Mobile Number</label>
                <input type="text" value="+91 9876543210" disabled style="background:#F1F5F9;">
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" value="user@example.com">
            </div>
            <div style="display:flex; gap:12px; margin-top:24px;">
                <button style="flex:1; padding:12px; background:#F8FAFC; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-weight:700; cursor:pointer;" onclick="closeModal('edit-modal')">Cancel</button>
                <button style="flex:1; padding:12px; background:var(--clr-primary); color:white; border:none; border-radius:var(--radius-sm); font-weight:700; cursor:pointer;" onclick="closeModal('edit-modal')">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- 13. PRIVACY & SECURITY MODAL -->
<div class="modal-overlay" id="security-modal">
    <div class="modal-card">
        <div class="m-head" style="background:#F8FAFC;">
            <div><i class="fas fa-shield-alt" style="color:var(--clr-primary);"></i> Privacy & Security</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('security-modal')">&times;</button>
        </div>
        <div style="padding:24px; overflow-y:auto;">
            
            <div class="sec-box">
                <div class="sec-title">Login Security</div>
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.95rem;">
                    <div>Two-Factor Authentication (2FA) <br><span style="font-size:0.85rem; color:var(--clr-text-secondary);">Secure your account via SMS OTP.</span></div>
                    <div style="background:#16A34A; color:white; padding:4px 12px; border-radius:20px; font-weight:700; font-size:0.8rem;">ON</div>
                </div>
            </div>

            <div class="sec-box">
                <div class="sec-title">Active Sessions</div>
                <div class="list-item" style="padding-top:0;">
                    <div class="li-main"><i class="fas fa-desktop"></i> <div>Windows · Chrome<div class="li-sub">IP: 192.168.1.1</div></div></div>
                    <div style="font-size:0.8rem; font-weight:700; color:#16A34A;">Current Session</div>
                </div>
                <div class="list-item">
                    <div class="li-main"><i class="fas fa-mobile-alt"></i> <div>Android · App<div class="li-sub">Last active: 2 hours ago</div></div></div>
                </div>
                <button class="mc-action" style="width:100%; margin-top:16px;">Sign Out Of Other Devices</button>
            </div>

            <div class="sec-box" style="border-color:#FECACA; background:#FEF2F2;">
                <div class="sec-title" style="color:#DC2626;">Account Danger Zone</div>
                <div style="font-size:0.85rem; color:#991B1B; margin-bottom:12px;">Deactivating your account will scrub access to your medical history.</div>
                <button style="border:1px solid #DC2626; background:transparent; color:#DC2626; padding:8px 16px; border-radius:var(--radius-sm); font-weight:700; cursor:pointer; width:100%;">Deactivate Account</button>
            </div>

        </div>
    </div>
</div>

<!-- 14. PAYMENTS & INVOICES MODAL -->
<div class="modal-overlay" id="payment-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-credit-card"></i> Payments & Invoices</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('payment-modal')">&times;</button>
        </div>
        <div style="padding:24px; overflow-y:auto; background:#F8FAFC;">
            
            <!-- Invoice 1 -->
            <div class="m-card" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <div>
                        <div style="font-family:monospace; font-weight:800; color:var(--clr-primary-dark);">INV-2026-00152</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">05 Sep 2026</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-primary);">₹140</div>
                        <div style="font-size:0.85rem; color:#16A34A; font-weight:700;"><i class="fas fa-check"></i> Paid via UPI</div>
                    </div>
                </div>
                <div style="font-weight:700; color:var(--clr-text-secondary); font-size:0.95rem; margin-bottom:16px;">Video Consultation (<?php echo htmlspecialchars($docName); ?>)</div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button class="mc-action"><i class="fas fa-eye"></i> View</button>
                    <button class="mc-action"><i class="fas fa-download"></i> PDF Report</button>
                </div>
            </div>

            <!-- Invoice 2 -->
            <div class="m-card" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                    <div>
                        <div style="font-family:monospace; font-weight:800; color:var(--clr-primary-dark);">INV-2026-00151</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">05 Sep 2026</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:800; font-size:1.1rem; color:var(--clr-text-primary);">₹315</div>
                        <div style="font-size:0.85rem; color:#16A34A; font-weight:700;"><i class="fas fa-check"></i> Paid via Credit Card</div>
                    </div>
                </div>
                <div style="font-weight:700; color:var(--clr-text-secondary); font-size:0.95rem; margin-bottom:16px;">Medicine Order (MED-260905-1024)</div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button class="mc-action"><i class="fas fa-eye"></i> View</button>
                    <button class="mc-action"><i class="fas fa-download"></i> Pharm Receipt</button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- 15. CAMERA TEST MODAL -->
<div class="modal-overlay" id="camera-modal">
    <div class="modal-card">
        <div class="m-head" style="background:#F8FAFC;">
            <div><i class="fas fa-video" style="color:var(--clr-primary);"></i> Systems Check</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeCameraTest()">&times;</button>
        </div>
        <div style="padding:24px; text-align:center;">
            <div style="background:#0F172A; border-radius:var(--radius-md); overflow:hidden; position:relative; aspect-ratio:16/9; margin-bottom:16px;">
                <video id="camera-preview" autoplay muted playsinline style="width:100%; height:100%; object-fit:cover;"></video>
                <div id="camera-status" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:0.95rem;">Requesting hardware access...</div>
            </div>
            
            <div style="display:flex; justify-content:center; gap:16px; margin-bottom:16px;">
                <div style="padding:12px; border:1px solid #16A34A; background:#F0FDF4; border-radius:var(--radius-sm); color:#166534; font-weight:700; font-size:0.9rem; flex:1;">
                    <i class="fas fa-check-circle"></i> Video OK
                </div>
                <div style="padding:12px; border:1px solid #16A34A; background:#F0FDF4; border-radius:var(--radius-sm); color:#166534; font-weight:700; font-size:0.9rem; flex:1;">
                    <i class="fas fa-check-circle"></i> Audio OK
                </div>
            </div>
            
            <button class="mc-action" style="width:100%; padding: 12px; background:var(--clr-primary); color:white; border:none; font-size:0.95rem;" onclick="closeCameraTest()">Looks Good!</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    let cameraStream = null;

    function openCameraTest() {
        openModal('camera-modal');
        const video = document.getElementById('camera-preview');
        const status = document.getElementById('camera-status');
        
        status.style.display = 'block';
        status.textContent = 'Requesting hardware access...';
        
        navigator.mediaDevices.getUserMedia({ video: true, audio: true })
            .then(stream => {
                cameraStream = stream;
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                    status.style.display = 'none';
                };
            })
            .catch(err => {
                console.error("Error accessing media devices.", err);
                status.textContent = 'Hardware access denied or unavailable.';
                status.style.color = '#EF4444';
            });
    }

    function closeCameraTest() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        document.getElementById('camera-preview').srcObject = null;
        closeModal('camera-modal');
    }
</script>

</body>
</html>
