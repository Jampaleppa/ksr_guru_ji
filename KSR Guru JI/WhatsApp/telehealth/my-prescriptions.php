<?php
require_once 'helper.php';

// Mock Patient State
$patientName = 'Jampaleppa';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? TELEHEALTH_LEAD_DOCTOR_NAME;
$docSpec = TELEHEALTH_LEAD_DOCTOR_SPECIALTY;
$rxId = 'RX-2026-X84B9A2';
$issueDate = 'September 5, 2026';

$medicines = [
    [
        'name' => 'Pregabalin (75mg)',
        'dosage' => '1 Capsule',
        'frequency' => 'Twice daily',
        'duration' => '14 Days',
        'instruct' => 'Take after meals to manage nerve pain and stabilize neuropathy.'
    ],
    [
        'name' => 'Baclofen (20mg)',
        'dosage' => '1 Tablet',
        'frequency' => 'Three times daily',
        'duration' => '10 Days',
        'instruct' => 'Take exactly as prescribed to reduce post-paralysis muscle spasticity.'
    ],
    [
        'name' => 'Methylcobalamin (1500mcg)',
        'dosage' => '1 Tablet',
        'frequency' => 'Once daily',
        'duration' => '30 Days',
        'instruct' => 'Nerve health supplement. Take in the morning for optimal absorption.'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; }
        
        .dash-layout { max-width: 900px; margin: 40px auto; padding: 0 16px; margin-bottom: 100px; }

        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 2rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .page-sub { color: var(--clr-text-secondary); font-size: 1.05rem; }

        /* Tabs & Filters */
        .head-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .dash-tabs { display: flex; border-bottom: 2px solid var(--clr-border); width: 100%; max-width: 400px; }
        .tab { padding: 12px 24px; font-weight: 600; color: var(--clr-text-secondary); cursor: pointer; transition: 0.3s; margin-bottom: -2px; border-bottom: 2px solid transparent; }
        .tab:hover { color: var(--clr-text-primary); }
        .tab.active { color: var(--clr-primary); border-bottom-color: var(--clr-primary); }

        .rx-filters { display: flex; gap: 12px; }
        .filter-btn { padding: 8px 16px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-secondary); background: white; cursor: pointer; }

        /* Prescription Card */
        .rx-card { background: white; border-radius: var(--radius-lg); border: 2px solid #BBF7D0; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); transition: 0.3s; }
        .rx-card:hover { box-shadow: var(--shadow-md); }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; }
        .rx-badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-full); text-transform: uppercase; background: #DCFCE7; color: #16A34A; }
        
        .doc-info { display: flex; align-items: center; gap: 16px; }
        .doc-icon { width: 50px; height: 50px; background: var(--clr-primary-light); color: var(--clr-primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); }
        .doc-name { font-size: 1.15rem; font-weight: 700; color: var(--clr-text-primary); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .doc-spec { font-size: 0.9rem; color: var(--clr-text-secondary); }

        .rx-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
        .meta-box { background: #F8FAFC; padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--clr-border); }
        .meta-label { font-size: 0.75rem; color: var(--clr-text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .meta-val { font-size: 0.95rem; font-weight: 600; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }

        .card-actions { display: flex; gap: 16px; margin-top: 24px; }
        .btn-action { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: var(--radius-md); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.3s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); text-decoration: none; }
        .btn-action:hover { background: #F8FAFC; }
        .btn-primary { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }
        .btn-primary:hover { background: var(--clr-primary-dark); color: white; }
        .btn-success { background: #16A34A; color: white; border-color: #16A34A; }
        .btn-success:hover { background: #15803D; color: white; }

        /* Expired Card Override */
        .rx-expired { border-color: var(--clr-border); background: #f9fafb; opacity: 0.85; }
        .rx-expired .rx-badge { background: #FEE2E2; color: #DC2626; }
        .expired-notice { font-size: 0.9rem; color: #DC2626; margin-bottom: 16px; font-weight: 500; }

        /* DETAILS MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 16px; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 650px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; transform: translateY(20px); transition: 0.3s; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        
        .modal-header { padding: 24px; border-bottom: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; background: white; position: sticky; top: 0; z-index: 10; }
        .modal-title { font-size: 1.1rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 24px; border-bottom: 1px solid var(--clr-border); padding-bottom: 8px;}

        .modal-body { padding: 24px; flex: 1; }

        /* Medicine Lists */
        .med-card { border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 16px; background: white; }
        .med-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 1.1rem; font-weight: 700; color: var(--clr-primary-dark); }
        .med-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .med-prop { font-size: 0.9rem; color: var(--clr-text-primary); }
        .med-prop span { color: var(--clr-text-secondary); display: inline-block; width: 80px; }
        .med-instruct { background: #FFFBEB; border: 1px solid #FEF3C7; padding: 12px; border-radius: var(--radius-sm); font-size: 0.9rem; color: #92400E; display: flex; gap: 8px; }

        /* Clinical Notes */
        .clinical-box { background: #F8FAFC; border: 1px dashed var(--clr-border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .c-sect { margin-bottom: 16px; }
        .c-sect:last-child { margin-bottom: 0; }
        .c-title { font-size: 0.85rem; font-weight: 600; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--clr-border); padding-bottom: 4px; margin-bottom: 8px; }
        .c-content { font-size: 0.95rem; color: var(--clr-text-primary); line-height: 1.5; }

        /* Verification Status */
        .status-tracker { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .st-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-weight: 600; font-size: 0.95rem; color: #166534; }
        .st-item i { width: 20px; text-align: center; }
        .st-item.pending { color: #EA580C; }
        .st-item.future { color: var(--clr-text-secondary); opacity: 0.6; }

        /* Validity */
        .validity-block { display: flex; justify-content: space-between; align-items: center; background: white; border: 1px solid var(--clr-border); padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; }
        .val-tag { background: #DCFCE7; color: #16A34A; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.85rem; }

        /* Print Actions */
        .rx-actions { display: flex; gap: 12px; margin-top: 24px; }

        /* PHARMACY BRIDGE MODAL */
        #bridge-modal .modal-card { max-width: 500px; text-align: center; padding: 40px 32px; }
        .bridge-icon { font-size: 3rem; color: var(--clr-primary); margin-bottom: 24px; display: flex; justify-content: center; gap: 16px;}
        .bridge-icon i { background: var(--clr-primary-light); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        
        /* Navbar overrides */
        .navbar-links { display: flex; gap: 24px; align-items: center; }
        .navbar-links a { color: var(--clr-text-secondary); text-decoration: none; font-weight: 600; transition: 0.2s; }
        .navbar-links a:hover, .navbar-links a.active { color: var(--clr-primary); }

        @media (max-width: 768px) {
            .navbar-links { display: none; }
            .card-actions, .rx-actions { flex-direction: column; }
            .rx-meta-grid { grid-template-columns: 1fr; }
            .med-grid { grid-template-columns: 1fr; }
            .head-flex { flex-direction: column; align-items: flex-start; }
            .dash-tabs { max-width: 100%; overflow-x: auto; scrollbar-width: none; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'prescriptions'; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
            <div class="navbar-links">
                <a href="index.php">Home</a>
                <a href="find-doctor.php">Find Doctor</a>
                <a href="my-appointments.php">Appointments</a>
                <a href="pharmacy.php">Pharmacy</a>
                <a href="my-prescriptions.php" class="active">Prescriptions</a>
            </div>
        </div>
        <div class="navbar-links">
            <a href="notifications.php"><i class="fas fa-bell"></i></a>
            <a href="profile.php" style="background:var(--clr-primary-light); color:var(--clr-primary); padding:8px 16px; border-radius:20px;"><i class="fas fa-user-circle"></i> Profile</a>
        </div>
    </div>
</nav>

<div class="dash-layout">
    
    <div class="page-header">
        <h1 class="page-title">My Prescriptions</h1>
        <p class="page-sub">View, manage and order medicines from your prescriptions.</p>
    </div>

    <!-- 2. Tabs & Filters -->
    <div class="head-flex">
        <div class="dash-tabs">
            <div class="tab active" onclick="switchTab('active')" id="tab-active">Active</div>
            <div class="tab" onclick="switchTab('past')" id="tab-past">Past</div>
            <div class="tab" onclick="switchTab('expired')" id="tab-expired">Expired</div>
        </div>
        <div class="rx-filters">
            <button class="filter-btn">All Doctors ▼</button>
            <button class="filter-btn">Date ▼</button>
            <button class="filter-btn"><i class="fas fa-search"></i> Search Prescription</button>
        </div>
    </div>

    <!-- 3. ACTIVE PRESCRIPTIONS -->
    <div id="sec-active">
        <div class="rx-card">
            <div class="card-header">
                <div class="rx-badge">🟢 ACTIVE PRESCRIPTION</div>
            </div>
            
            <div class="doc-info">
                <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                <div>
                    <div class="doc-name">👨‍⚕️ <?php echo htmlspecialchars($docName); ?> <span style="color:#16A34A; font-size:0.85rem;"><i class="fas fa-check-circle"></i> Verified</span></div>
                    <div class="doc-spec"><?php echo htmlspecialchars($docSpec); ?></div>
                </div>
            </div>

            <div class="rx-meta-grid">
                <div class="meta-box">
                    <div class="meta-label">Issued Date</div>
                    <div class="meta-val"><i class="fas fa-calendar-alt"></i> <?php echo $issueDate; ?></div>
                </div>
                <div class="meta-box">
                    <div class="meta-label">Consultation Type</div>
                    <div class="meta-val"><i class="fas fa-video"></i> Video Booking</div>
                </div>
                <div class="meta-box" style="grid-column: 1 / -1;">
                    <div class="meta-label">Prescription ID</div>
                    <div class="meta-val" style="font-family: monospace; font-size:1.1rem; color:var(--clr-primary);"><?php echo $rxId; ?></div>
                </div>
            </div>

            <div style="font-weight:700; color:var(--clr-text-primary); margin-top:16px;">
                <i class="fas fa-pills" style="color:var(--clr-primary);"></i> <?php echo count($medicines); ?> Medicines
            </div>

            <div class="card-actions">
                <button class="btn-action" onclick="openDetails()"><i class="fas fa-file-medical"></i> View Prescription</button>
                <button class="btn-action btn-success" onclick="openBridge()"><i class="fas fa-shopping-cart"></i> 🛒 Order Medicines</button>
            </div>
        </div>
    </div>

    <!-- 12. EXPIRED PRESCRIPTIONS (Hidden map) -->
    <div id="sec-expired" style="display:none;">
        <div class="rx-card rx-expired">
            <div class="card-header">
                <div class="rx-badge">🔴 PRESCRIPTION EXPIRED</div>
            </div>
            
            <p class="expired-notice">This prescription is no longer eligible for pharmacy fulfillment under the configured 6-month validity policy.</p>

            <div class="doc-info">
                <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                <div>
                    <div class="doc-name">👨‍⚕️ Dr. Priya Sharma</div>
                    <div class="doc-spec">General Physician</div>
                </div>
            </div>

            <div class="card-actions">
                <button class="btn-action"><i class="fas fa-search"></i> Find Doctor</button>
                <button class="btn-action"><i class="fas fa-file-alt"></i> View Prescription</button>
            </div>
        </div>
    </div>

</div>

<!-- DETAILS MODAL (Sections 4, 5, 6, 7, 8, 10) -->
<div class="modal-overlay" id="rx-details">
    <div class="modal-card">
        <div class="modal-header">
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--clr-primary-dark);">PRESCRIPTION DETAILS</div>
            <button class="btn-close" style="background:transparent; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeModal('rx-details')">&times;</button>
        </div>
        
        <div class="modal-body">
            
            <!-- 4. Details Header -->
            <div style="border-bottom:1px solid var(--clr-border); padding-bottom:16px; margin-bottom:24px;">
                <div style="font-weight:700; font-size:1.15rem;">👨‍⚕️ <?php echo $docName; ?> <span style="color:#16A34A; font-size:0.9rem;">✓ VERIFIED</span></div>
                <div style="color:var(--clr-text-secondary); margin-bottom:12px;"><?php echo $docSpec; ?></div>
                
                <div style="font-size:0.95rem; font-weight:500;">
                    <div>Patient: <span style="color:var(--clr-text-secondary);"><?php echo $patientName; ?></span></div>
                    <div>Issued: <span style="color:var(--clr-text-secondary);"><?php echo $issueDate; ?></span></div>
                    <div style="margin-top:4px;">Prescription ID: <span style="font-family:monospace; color:var(--clr-primary); font-weight:700;"><?php echo $rxId; ?></span></div>
                </div>
            </div>

            <!-- 7. Prescription Verification -->
            <div class="modal-title">PRESCRIPTION STATUS</div>
            <div class="status-tracker">
                <div class="st-item"><i class="fas fa-check-circle"></i> Issued by Doctor</div>
                <div class="st-item"><i class="fas fa-check-circle"></i> Doctor Verified</div>
                <div class="st-item pending"><i class="fas fa-circle-notch fa-spin"></i> Awaiting Pharmacy Verification</div>
            </div>

            <!-- 8. Prescription Validity -->
            <div class="validity-block">
                <div>
                    <div style="font-size:0.85rem; color:var(--clr-text-secondary); font-weight:600; margin-bottom:4px;"><i class="fas fa-calendar"></i> PRESCRIPTION VALIDITY</div>
                    <div style="font-size:0.9rem; font-weight:500;">Valid according to Doctor Assist policy.</div>
                </div>
                <div class="val-tag">🟢 Valid</div>
            </div>

            <!-- 5. Medicines List -->
            <div class="modal-title">PRESCRIBED MEDICINES</div>
            
            <?php foreach($medicines as $med): ?>
            <div class="med-card">
                <div class="med-header"><i class="fas fa-capsules"></i> <?php echo htmlspecialchars($med['name']); ?></div>
                <div class="med-grid">
                    <div class="med-prop"><span>Dosage:</span> <?php echo htmlspecialchars($med['dosage']); ?></div>
                    <div class="med-prop"><span>Frequency:</span> <?php echo htmlspecialchars($med['frequency']); ?></div>
                    <div class="med-prop"><span>Duration:</span> <?php echo htmlspecialchars($med['duration']); ?></div>
                </div>
                <!-- Actual doctor instruction formatting -->
                <div class="med-instruct">
                    <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                    <div>
                        <strong>Instructions:</strong><br>
                        <?php echo htmlspecialchars($med['instruct']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- 6. Clinical Notes -->
            <div class="modal-title" style="margin-top:32px;">CLINICAL INFORMATION</div>
            <div class="clinical-box">
                <div class="c-sect">
                    <div class="c-title">Diagnosis</div>
                    <div class="c-content">Post-stroke peripheral neuropathy and unilateral muscle spasticity.</div>
                </div>
                <div class="c-sect">
                    <div class="c-title">Doctor's Instructions</div>
                    <div class="c-content">Strictly adhere to the Pregabalin schedule to prevent nerve flare-ups. Engage in daily physiotherapy as discussed.</div>
                </div>
                <div class="c-sect">
                    <div class="c-title">Follow-up</div>
                    <div class="c-content">Review via Virtual Consultation in exactly 14 days or immediately if numbness spreads.</div>
                </div>
            </div>

            <!-- 9. Order Medicine CTA -->
            <div style="background:var(--clr-primary-light); border: 1px solid var(--clr-primary); border-radius:var(--radius-lg); padding:24px; text-align:center; margin-top:32px;">
                <h3 style="color:var(--clr-primary-dark); font-size:1.25rem; font-weight:700; margin-bottom:8px;">🛒 Order Medicines</h3>
                <p style="color:var(--clr-primary); font-weight:500; margin-bottom:16px;">Get prescribed medicines delivered to you.</p>
                <button class="btn-action btn-primary" style="width:100%; border-radius:var(--radius-full);" onclick="openBridge()">Order Now &rarr;</button>
            </div>

            <!-- 10. Download Actions -->
            <div class="rx-actions">
                <button class="btn-action" style="flex:1;"><i class="fas fa-file-pdf"></i> Download PDF</button>
                <button class="btn-action" style="flex:1;"><i class="fas fa-print"></i> Print</button>
                <button class="btn-action" style="flex:1;"><i class="fas fa-share-alt"></i> Share (Secure)</button>
            </div>

        </div>
    </div>
</div>

<!-- 11. PHARMACY BRIDGE MODAL -->
<div class="modal-overlay" id="bridge-modal">
    <div class="modal-card">
        <div class="bridge-icon">
            <i class="fas fa-pills" style="color:var(--clr-primary);"></i>
            <i class="fas fa-exchange-alt" style="background:transparent; font-size:2rem; margin-top:20px; color:var(--clr-text-secondary);"></i>
            <i class="fas fa-clinic-medical" style="color:#16A34A; background:#DCFCE7;"></i>
        </div>
        <h2 style="font-size:1.5rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:8px;">PRESCRIPTION &rarr; PHARMACY</h2>
        <div style="font-size:1.1rem; font-weight:600; color:var(--clr-text-primary); margin-bottom:24px;">💊 <?php echo count($medicines); ?> Medicines Prescribed</div>
        
        <div style="text-align:left; background:#F8FAFC; border:1px solid var(--clr-border); padding:20px; border-radius:var(--radius-md); margin-bottom:24px;">
            <div style="color:#16A34A; font-weight:600; margin-bottom:12px; font-size:0.95rem;"><i class="fas fa-check"></i> Prescription available</div>
            <div style="color:#16A34A; font-weight:600; margin-bottom:12px; font-size:0.95rem;"><i class="fas fa-check"></i> Doctor verified</div>
            <div style="color:#EA580C; font-weight:600; font-size:0.95rem;"><i class="fas fa-circle"></i> Pharmacy verification pending checkout</div>
        </div>

        <button class="btn-action btn-success" style="width:100%; border-radius:var(--radius-full);" onclick="window.location.href='pharmacy.php?rx=true'">Continue to Pharmacy &rarr;</button>
        <button class="btn-action" style="width:100%; margin-top:12px; border:none;" onclick="closeModal('bridge-modal')">Cancel</button>

    </div>
</div>

<script>
    function switchTab(env) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + env).classList.add('active');
        
        document.getElementById('sec-active').style.display = 'none';
        document.getElementById('sec-expired').style.display = 'none';
        
        if(env === 'active') document.getElementById('sec-active').style.display = 'block';
        if(env === 'expired') document.getElementById('sec-expired').style.display = 'block';
        // Past is basically same as active layout in the spec
    }

    function openDetails() {
        document.getElementById('rx-details').classList.add('active');
    }

    function openBridge() {
        closeModal('rx-details');
        document.getElementById('bridge-modal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
</script>

</body>
</html>
