<?php
// ============================================================
// doctor-assist/index.php - Landing Page & Hub
// ============================================================

require_once 'helper.php';

$pdo = getAssistDB();

// ============================================================
// Registration & login now live on the dedicated portal pages:
//   patient-register.php / patient-login.php  (Patient Portal)
//   doctor-register.php  / doctor-login.php   (Doctor Portal)
// ============================================================

// Live counts straight from the database
$countDoctors       = telehealthCount($pdo, "SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'");
$countPatients      = telehealthCount($pdo, "SELECT COUNT(*) FROM patients");
// The `consultations` table does not carry a `status` column on every
// deployment (see helper.php -> telehealthConsultCols()), so resolve the
// column first instead of hard-coding it — otherwise MySQL raises
// "1054 Unknown column 'status' in 'where clause'".
$countConsultations = telehealthCount($pdo, "SELECT COUNT(*) FROM consultations");
$consultStatusCol   = telehealthCol($pdo, 'consultations', telehealthConsultCols()['status']);
if ($consultStatusCol !== null) {
    $countConsultations = telehealthCount(
        $pdo,
        "SELECT COUNT(*) FROM consultations WHERE `" . $consultStatusCol . "` = 'completed'"
    );
}

// Featured verified practitioners
$featuredDoctors = array_values(array_filter(telehealthDoctors($pdo), static function ($d) {
    return ($d['verification_status'] ?? '') === 'VERIFIED';
}));
if (!$featuredDoctors) {
    $featuredDoctors = telehealthDoctors($pdo);
}
$featuredDoctors = array_slice($featuredDoctors, 0, 4);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Assist Ecosystem Hub · KSR GURU JI</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'home'; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






    <?php renderRoleSwitcher(); ?>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <i class="fas fa-heartbeat"></i>
                Doctor Assist
            </div>
            <ul class="navbar-nav">
                <li><a href="find-doctor.php" class="nav-link">Find Doctor</a></li>
                <li><a href="pharmacy.php" class="nav-link">Pharmacy</a></li>
                <li><a href="my-appointments.php" class="nav-link">My Appointments</a></li>
                <li><button class="btn-emergency-outline" onclick="openEmergencyModal()"><i class="fas fa-ambulance"></i> Emergency Help</button></li>
            </ul>
            <div class="navbar-actions" style="margin-left: auto; display: flex; gap: 12px;">
                <button class="btn-secondary" onclick="window.location.href='doctor-login.php'" style="background: white; border: 1px solid var(--clr-border);"><i class="fas fa-user-md"></i> Doctor Login</button>
                <button class="btn-primary" onclick="window.location.href='../../../patient-login.php'"><i class="fas fa-user-circle"></i> Patient Login</button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="container">
        <div class="hero">
            <div class="hero-content">
                <div class="title-badge">
                    <i class="fas fa-shield-alt"></i> Prerana Healthcare Platform
                </div>
                <h1 class="hero-title">Your Health, Connected<br>to Expert Care</h1>
                <p class="hero-subtitle">Consult verified doctors from the comfort of your home through Video, Audio or Chat. Secure, private and built for your family.</p>

                <!-- Quick Actions embedded in Hero -->
                <div class="hero-actions">
                    <button class="btn-primary" onclick="window.location.href='find-doctor.php'"><i class="fas fa-search"></i> Find a Doctor</button>
                    <button class="btn-secondary" onclick="window.location.href='find-doctor.php'"><i class="fas fa-calendar-check"></i> Book Appointment</button>
                </div>

                <div class="trust-indicators">
                    <div class="trust-item"><i class="fas fa-lock"></i> Secure Platform</div>
                    <div class="trust-item"><i class="fas fa-check-circle"></i> Verified Doctors</div>
                    <div class="trust-item"><i class="fas fa-bolt"></i> Fast Care</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <div class="container">
        <div class="search-container">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by Doctor, Specialty or Symptoms...">
            </div>
            <div class="search-input-group" style="max-width: 250px;">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" placeholder="Online or Clinic">
            </div>
            <button class="btn-search">Search Provider</button>
        </div>
    </div>

    <!-- Consultation Pricing Options -->
    <section class="container" style="margin-top: -20px;">
        <div class="section-header">
            <h2 class="section-title">Consultation Options</h2>
            <p class="section-subtitle">Flexible and secure ways to connect with your specialist.</p>
        </div>

        <div class="consult-grid">
            <div class="consult-card">
                <div class="consult-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-video"></i></div>
                <h3>Video Consultation</h3>
                <div class="consult-price">₹125 <span>+ ₹15 Platform Charge</span></div>
                <p class="consult-desc">Face-to-face encrypted video call with the doctor for detailed diagnosis.</p>
            </div>
            <div class="consult-card">
                <div class="consult-icon" style="background:#FFF7ED; color:#F59E0B;"><i class="fas fa-phone-alt"></i></div>
                <h3>Audio Consultation</h3>
                <div class="consult-price">₹100 <span>+ ₹15 Platform Charge</span></div>
                <p class="consult-desc">Clear, secure voice calling. Best for follow-ups and rapid consultations.</p>
            </div>
            <div class="consult-card">
                <div class="consult-icon" style="background:#F0FDF4; color:#16A34A;"><i class="fas fa-comment-dots"></i></div>
                <h3>Secure Chat</h3>
                <div class="consult-price">Included <span>with calls</span></div>
                <p class="consult-desc">Encrypted texting for quick report uploads and prescription tracking.</p>
            </div>
        </div>
    </section>

    <!-- Specialist Categories -->
    <section class="container" style="background-color: white; border-radius: 24px; padding: 60px 40px; margin-bottom: 80px; box-shadow: var(--shadow-sm); border: 1px solid var(--clr-border);">
        <div class="section-header">
            <h2 class="section-title">Find the Right Specialist</h2>
            <p class="section-subtitle">Top-rated doctors across all specialties in the KSR Ecosystem.</p>
        </div>

        <div class="specialist-grid">
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">🫀</div>
                <div class="specialist-name">Cardiology</div>
            </div>
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">🧠</div>
                <div class="specialist-name">Neurology</div>
            </div>
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">👶</div>
                <div class="specialist-name">Pediatrics</div>
            </div>
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">🦷</div>
                <div class="specialist-name">Dental</div>
            </div>
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">👁</div>
                <div class="specialist-name">Eye Care</div>
            </div>
            <div class="specialist-card" onclick="openAuthModal()">
                <div class="specialist-icon">🧴</div>
                <div class="specialist-name">Dermatology</div>
            </div>
        </div>
    </section>

    <!-- Featured Doctors & Quick Links -->
    <section class="container">
        <div class="section-header">
            <h2 class="section-title">Ecosystem Dashboards</h2>
            <p class="section-subtitle">Access your designated clinical and operational environment.</p>
        </div>

        <div class="quick-actions">
            <!-- Dashboard shortcuts styled neatly -->
            <a href="patient-dashboard.php" class="dashboard-card" style="border-top: 4px solid var(--clr-primary);">
                <div class="dashboard-icon" style="color: var(--clr-primary);"><i class="fas fa-procedures"></i></div>
                <h3>Patient Portal</h3>
                <p>Register parameters, book appointments, and upload prescriptions.</p>
                <span class="btn-card">Open Portal <i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="doctor-dashboard.php" class="dashboard-card" style="border-top: 4px solid var(--clr-accent);">
                <div class="dashboard-icon" style="color: var(--clr-accent);"><i class="fas fa-user-md"></i></div>
                <h3>Doctor Workspace</h3>
                <p>Access patient charts, prescribe medications, and view history.</p>
                <span class="btn-card">Open Workspace <i class="fas fa-arrow-right"></i></span>
            </a>
            <a href="pharmacy-dashboard.php" class="dashboard-card" style="border-top: 4px solid var(--clr-warning);">
                <div class="dashboard-icon" style="color: var(--clr-warning);"><i class="fas fa-pills"></i></div>
                <h3>Pharmacist Dispensary</h3>
                <p>Audit AI OCR logs, verify scripts, and process medication orders.</p>
                <span class="btn-card">Open Dispensary <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>

        <!-- Live network metrics -->
        <div class="stat-strip">
            <div class="ss-item">
                <i class="fas fa-user-md"></i>
                <div>
                    <div class="ss-val"><?php echo $countDoctors; ?></div>
                    <div class="ss-lbl">Verified Doctors</div>
                </div>
            </div>
            <div class="ss-item">
                <i class="fas fa-users"></i>
                <div>
                    <div class="ss-val"><?php echo $countPatients; ?></div>
                    <div class="ss-lbl">Registered Patients</div>
                </div>
            </div>
            <div class="ss-item">
                <i class="fas fa-stethoscope"></i>
                <div>
                    <div class="ss-val"><?php echo $countConsultations; ?></div>
                    <div class="ss-lbl">Completed Consultations</div>
                </div>
            </div>
            <div class="ss-item">
                <i class="fas fa-shield-halved"></i>
                <div>
                    <div class="ss-val">NMC</div>
                    <div class="ss-lbl">Verified Licences</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Verified Doctors -->
    <section class="container">
        <div class="section-header">
            <h2 class="section-title">Featured Verified Doctors</h2>
            <p class="section-subtitle">Highly experienced and verified practitioners available for consults.</p>
        </div>

        <div class="doctor-grid">
<div class="doctor-grid">
            <?php if (!$featuredDoctors): ?>
                <div class="doctor-card" style="grid-column: 1 / -1; text-align:center; padding:48px 24px;">
                    <i class="fas fa-user-md" style="font-size:2.5rem; color:var(--clr-text-muted);"></i>
                    <h3 style="margin-top:12px;">No verified doctors yet</h3>
                    <p style="color:var(--clr-text-secondary);">Practitioner profiles appear here once KSR GURU JI verification is complete.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($featuredDoctors as $fd): ?>
                <?php
                $fdStatus = (string)($fd['verification_status'] ?? 'PENDING');
                $fdVerified = $fdStatus === 'VERIFIED';
                $fdYears    = (int)($fd['experience_years'] ?? 0);
                $fdSpecialty = trim((string)($fd['specialty'] ?? '')) ?: 'General Physician';
                $fdQual     = trim((string)($fd['qualification'] ?? ''));
                ?>
                <div class="doctor-card">
                    <div class="doctor-header">
                        <div class="doctor-avatar"><i class="fas fa-user-md"></i></div>
                        <div class="doctor-info">
                            <h3>
                                <?php echo h($fd['doctor_name'] ?? 'Doctor'); ?>
                                <?php if ($fdVerified): ?><i class="fas fa-check-circle verified-badge" title="KSR verified"></i><?php endif; ?>
                            </h3>
                            <div class="doctor-specialty"><?php echo h($fdSpecialty); ?></div>
                            <div class="doctor-meta">
                                <div class="doctor-meta-item"><i class="fas fa-briefcase"></i> <?php echo $fdYears; ?>+ Years</div>
                                <?php if ($fdQual): ?>
                                    <div class="doctor-meta-item"><i class="fas fa-graduation-cap"></i> <?php echo h($fdQual); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="doctor-body">
                        <div class="doctor-price-row">
                            <span class="price-label">Consultation Starting At</span>
                            <span class="price-value">&#8377;125</span>
                        </div>
                        <button class="btn-primary btn-block" onclick="window.location.href='../../../patient-login.php'">Book Appointment</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand"><i class="fas fa-heartbeat"></i> Doctor Assist</div>
                    <p class="footer-text">Connecting you to expert healthcare professionals instantly. Trustworthy, fast, and completely secure.</p>
                    <div style="display:flex; align-items:center; gap:8px; color:#10B981; font-size:0.9rem; font-weight:600;"><i class="fas fa-shield-check"></i> Standardized AI Security Framework</div>
                </div>
                <div>
                    <h4 class="footer-title">For Patients</h4>
                    <ul class="footer-links">
                        <li><a href="#" onclick="openAuthModal()">Search Doctors</a></li>
                        <li><a href="../../../patient-login.php">Login / Appointments</a></li>
                        <li><a href="../../../patient-register.php">Patient Registration</a></li>
                        <li><a href="pharmacy-dashboard.php">Order Medicines</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">For Doctors</h4>
                    <ul class="footer-links">
                        <li><a href="doctor-login.php">Doctor Portal Login</a></li>
                        <li><a href="../../../patient-login.php">Patient Portal Login</a></li>
                        <li><a href="doctor-register.php">Join Platform</a></li>
                        <li><a href="#" >Guidelines</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#" onclick="openEmergencyModal()" style="color:#F87171;">🚨 Emergency Assistance</a></li>
                        <li><a href="delivery-dashboard.php">Courier Dispatch</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; 2026 KSR GURU JI - Doctor Assist Platform. All rights reserved. Registered Telemedicine Services.
            </div>
        </div>
    </footer>

<!-- Portal Access Modal — routes to the dedicated login / register pages -->
    <div class="auth-modal" id="authModal">
        <div class="auth-container" style="max-width: 560px; padding: 38px;">
            <button class="auth-close" onclick="closeAuthModal()"><i class="fas fa-times"></i></button>

            <div style="text-align:center; margin-bottom: 28px;">
                <div style="width:64px; height:64px; border-radius:20px; margin:0 auto 16px; display:flex; align-items:center; justify-content:center; background:var(--clr-primary-light); color:var(--clr-primary); font-size:1.7rem;">
                    <i class="fas fa-heart-pulse"></i>
                </div>
                <h2 style="font-size:1.5rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:6px;">Access Your Healthcare Portal</h2>
                <p style="color:var(--clr-text-secondary); font-size:0.92rem;">Choose how you would like to continue.</p>
            </div>

            <div style="display:grid; gap:14px;">
                <a href="../../../patient-login.php" class="portal-choice">
                    <span class="pc-icon" style="background:var(--clr-primary-light); color:var(--clr-primary);"><i class="fas fa-user-circle"></i></span>
                    <span class="pc-body">
                        <span class="pc-title">Patient Portal</span>
                        <span class="pc-sub">Appointments, prescriptions, lab reports &amp; pharmacy orders</span>
                    </span>
                    <i class="fas fa-arrow-right pc-go"></i>
                </a>

                <a href="doctor-login.php" class="portal-choice">
                    <span class="pc-icon" style="background:var(--clr-accent-light); color:var(--clr-accent);"><i class="fas fa-user-md"></i></span>
                    <span class="pc-body">
                        <span class="pc-title">Doctor Portal</span>
                        <span class="pc-sub">Patient records, consultations, prescriptions &amp; earnings</span>
                    </span>
                    <i class="fas fa-arrow-right pc-go"></i>
                </a>
            </div>

            <div style="margin-top: 24px; padding-top: 20px; border-top:1px solid var(--clr-border); display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <a href="../../../patient-register.php" class="btn-soft"><i class="fas fa-user-plus"></i> Patient Register</a>
                <a href="doctor-register.php" class="btn-soft"><i class="fas fa-clipboard-check"></i> Doctor Register</a>
            </div>
        </div>
    </div>
    <!-- Emergency Modal -->
    <div class="auth-modal" id="emergencyModal">
        <div class="auth-container" style="text-align:center; max-width: 550px; padding: 48px;">
            <div style="font-size:3.5rem; color:var(--clr-emergency); margin-bottom:20px; animation: pulse 2s infinite;"><i class="fas fa-ambulance"></i></div>
            <h2 style="font-size: 1.8rem; color: var(--clr-emergency); margin-bottom: 16px; font-family: 'Poppins', sans-serif; font-weight: 800;">EMERGENCY DETECTOR API</h2>

            <p style="font-weight: 700; color: #991B1B; margin-bottom: 16px;">Do NOT use this online scheduling platform if you have life-threatening issues.</p>
            <p style="color: var(--clr-text-secondary); margin-bottom: 24px; font-size: 0.95rem;">If the patient is experiencing chest pain, sudden severe chest pressure, heavy bleeding, difficulty breathing, or severe head injury, bypass this portal.</p>

            <div style="background:#FEE2E2; padding:20px; border-radius:16px; font-size:1.4rem; font-weight:800; color:var(--clr-emergency); margin-bottom: 24px; border: 2px dashed var(--clr-emergency);">
                📞 DIAL 108 IMMEDIATELY
            </div>
            <button class="btn-secondary" style="width:100%;" onclick="closeEmergencyModal()">I Understand, Proceed to Non-Urgent Appt</button>
        </div>
    </div>

    <script>
        function openAuthModal() {
            document.getElementById('authModal').classList.add('active');
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.remove('active');
        }

        function openEmergencyModal() {
            document.getElementById('emergencyModal').classList.add('active');
        }

        function closeEmergencyModal() {
            document.getElementById('emergencyModal').classList.remove('active');
        }

        function initTriageParamCheck() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('triage') === '1') {
                openEmergencyModal();
            }
        }

        window.onclick = function(event) {
            var authModal = document.getElementById('authModal');
            var emModal = document.getElementById('emergencyModal');
            if (event.target == authModal) {
                closeAuthModal();
            }
            if (event.target == emModal) {
                closeEmergencyModal();
            }
        }

        if (document.readyState === 'loading') {
            window.addEventListener('DOMContentLoaded', initTriageParamCheck);
        } else {
            initTriageParamCheck();
        }
    </script>

</body>

</html>

