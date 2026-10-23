<?php
require_once 'helper.php';

$pdo = getAssistDB();

// Gather POST details from patient-details.php mock default fallbacks
$docId = $_POST['doc_id'] ?? 1;
$consultType = $_POST['type'] ?? 'video';
$appDate = $_POST['date'] ?? 'September 6, 2026';
$appTime = $_POST['time'] ?? '02:30 PM';
$totalFee = $_POST['total_amount'] ?? 140;

$patientName = $_POST['patient_name'] ?? 'Jampaleppa';
$patientMobile = !empty($_POST['patient_mobile']) ? trim((string)$_POST['patient_mobile']) : (!empty($_SESSION['user_mobile']) ? $_SESSION['user_mobile'] : '6362079192');
$patientGender = $_POST['patient_gender'] ?? 'Male';
$patientDob = $_POST['patient_dob'] ?? '1994-05-15';
$symptoms = $_POST['symptoms'] ?? 'Headache and fever';

// Derived mock age
$age = date_diff(date_create($patientDob), date_create('today'))->y;
if ($age == 0) $age = 32;

// Fetch Doctor Info
$stmt = $pdo->prepare("SELECT doctor_name, specialty, experience_years FROM doctors WHERE id = ?");
$stmt->execute([$docId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $docNameFallback = $_SESSION['user_name'] ?? TELEHEALTH_LEAD_DOCTOR_NAME;
    if ($docId == 2) {
        $doctor = [
            'doctor_name' => 'Dr. Shivukumar',
            'specialty' => 'Pediatrics & General Medicine',
            'experience_years' => 5
        ];
    } else {
        $doctor = [
            'doctor_name' => $docNameFallback,
            'specialty' => TELEHEALTH_LEAD_DOCTOR_SPECIALTY,
            'experience_years' => TELEHEALTH_LEAD_DOCTOR_EXPERIENCE
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Appointment | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .flow-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
            margin-bottom: 120px;
            align-items: start;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--clr-text-secondary);
            font-weight: 500;
            margin-bottom: 24px;
            text-decoration: none;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--clr-primary);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--clr-primary-dark);
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 1.05rem;
            color: var(--clr-text-secondary);
            margin-bottom: 24px;
        }

        .step-tracker {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 32px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
        }

        .dots {
            display: flex;
            gap: 6px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--clr-border);
        }

        .dot.active {
            background: var(--clr-primary);
        }

        .dot.passed {
            background: var(--clr-primary-light);
        }

        /* General Card Style */
        .card-panel {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-edit {
            background: transparent;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-edit:hover {
            background: var(--clr-primary-light);
            color: var(--clr-primary);
            border-color: var(--clr-primary-light);
        }

        /* Doctor Block */
        .doc-df {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .doc-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-sm);
            background: var(--clr-primary-light);
            color: var(--clr-primary);
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .doc-info h3 {
            font-size: 1.25rem;
            color: var(--clr-text-primary);
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .verified-badge {
            color: #16A34A;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .doc-info p {
            color: var(--clr-text-secondary);
            margin-bottom: 8px;
        }

        .doc-meta {
            display: flex;
            gap: 16px;
            font-size: 0.9rem;
            color: var(--clr-text-primary);
            font-weight: 500;
        }

        .doc-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .doc-meta i {
            color: #F59E0B;
        }

        /* Consult & Patient Data */
        .data-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 1rem;
            color: var(--clr-text-primary);
        }

        .data-row i {
            margin-top: 4px;
            width: 20px;
            text-align: center;
            color: var(--clr-primary);
        }

        .data-note {
            margin-top: 16px;
            font-size: 0.85rem;
            color: var(--clr-text-secondary);
            font-style: italic;
        }

        .patient-block {
            border-left: 3px solid var(--clr-primary);
            padding-left: 16px;
            margin-left: 8px;
        }

        .p-name {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--clr-text-primary);
            margin-bottom: 4px;
        }

        .p-rel {
            color: var(--clr-text-secondary);
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .p-meta {
            display: flex;
            gap: 24px;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--clr-text-primary);
        }

        .p-concern {
            background: #F8FAFC;
            padding: 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--clr-border);
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
        }

        /* Documents */
        .doc-file {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .doc-file i {
            color: var(--clr-primary);
            font-size: 1.25rem;
        }

        .doc-status {
            color: #16A34A;
            font-size: 0.8rem;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .privacy-lock {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            padding: 12px;
            border-radius: var(--radius-sm);
            color: #166534;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
        }

        /* Important Info Section */
        .notice-card {
            background: #E0F2FE;
            border: 1px solid #BAE6FD;
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
        }

        .notice-card h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0369A1;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .notice-card ul {
            margin-left: 24px;
            color: #0C4A6E;
            font-size: 0.95rem;
            margin-bottom: 16px;
        }

        .notice-card ul li {
            margin-bottom: 8px;
        }

        .emergency-warning {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            padding: 12px;
            border-radius: var(--radius-sm);
            color: #991B1B;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Terms Consent */
        .consent-card {
            border: 1px solid var(--clr-border);
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 32px;
        }

        .consent-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .consent-row:last-child {
            margin-bottom: 0;
        }

        .consent-row input {
            margin-top: 2px;
            width: 18px;
            height: 18px;
            accent-color: var(--clr-primary);
        }

        .consent-row label {
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
            line-height: 1.5;
            font-weight: 500;
            cursor: pointer;
        }

        .consent-row label span {
            color: var(--clr-primary);
        }

        /* Sticky Payment Summary */
        .payment-card {
            position: sticky;
            top: 100px;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 32px;
            box-shadow: var(--shadow-lg);
        }

        .payment-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--clr-primary-dark);
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 12px;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            font-size: 1rem;
            color: var(--clr-text-secondary);
        }

        .bill-row.strong {
            color: var(--clr-text-primary);
            font-weight: 600;
        }

        .bill-total {
            border-top: 1px dashed var(--clr-text-secondary);
            padding-top: 20px;
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--clr-primary-dark);
        }

        /* Mobile Action */
        .mobile-bottom-action {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: white;
            border-top: 1px solid var(--clr-border);
            padding: 16px 24px;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }

        @media (max-width: 900px) {
            .flow-layout {
                grid-template-columns: 1fr;
            }

            .payment-card {
                display: none;
            }

            .mobile-bottom-action {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            body {
                padding-bottom: 80px;
            }
        }
    </style>
</head>

<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = '';
    $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php'; ?>






    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
        </div>
    </nav>

    <div class="container" style="padding-top: 40px;">

        <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>

        <div class="flow-layout">
            <!-- Main Form Content -->
            <div>
                <h1 class="page-title">Review Your Appointment</h1>
                <p class="page-subtitle">Please check all details before proceeding to secure payment.</p>

                <div class="step-tracker">
                    Step 5 of 6
                    <div class="dots">
                        <div class="dot passed"></div>
                        <div class="dot passed"></div>
                        <div class="dot passed"></div>
                        <div class="dot passed"></div>
                        <div class="dot active"></div>
                        <div class="dot"></div>
                    </div>
                </div>

                <!-- Doctor Card -->
                <div class="card-panel">
                    <div class="card-header">
                        <div class="card-title"></div>
                        <button class="btn-edit" onclick="history.back()">Edit Doctor</button>
                    </div>
                    <div class="doc-df">
                        <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                        <div class="doc-info">
                            <h3><?php echo htmlspecialchars($doctor['doctor_name']); ?> <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span></h3>
                            <p><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            <div class="doc-meta">
                                <span><i class="fas fa-star"></i> 4.8</span>
                                <span><?php echo htmlspecialchars($doctor['experience_years']); ?>+ Years Experience</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Consultation Details -->
                <div class="card-panel">
                    <div class="card-header">
                        <div class="card-title">CONSULTATION DETAILS</div>
                        <button class="btn-edit" onclick="history.back()">Edit</button>
                    </div>
                    <div class="data-row"><i class="fas <?php echo $consultType === 'audio' ? 'fa-headphones-alt' : 'fa-video'; ?>"></i> <span><?php echo ucfirst($consultType); ?> Consultation</span></div>
                    <div class="data-row"><i class="fas fa-calendar-alt"></i> <span><?php echo htmlspecialchars($appDate); ?></span></div>
                    <div class="data-row"><i class="fas fa-clock"></i> <span><?php echo htmlspecialchars($appTime); ?></span></div>
                    <div class="data-row"><i class="fas fa-stopwatch"></i> <span>Consultation duration: 15–30 minutes*</span></div>
                </div>

                <!-- Patient Details -->
                <div class="card-panel">
                    <div class="card-header">
                        <div class="card-title">PATIENT</div>
                        <button class="btn-edit" onclick="history.back()">Edit Patient</button>
                    </div>
                    <div class="patient-block">
                        <div class="p-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($patientName); ?></div>
                        <div class="p-rel">Self</div>
                        <div class="p-meta">
                            <span>Age: <?php echo $age; ?></span>
                            <span>Gender: <?php echo htmlspecialchars($patientGender); ?></span>
                        </div>
                        <div class="p-concern">
                            <strong>Concern:</strong> <?php echo htmlspecialchars($symptoms); ?>
                        </div>
                    </div>
                </div>

                <!-- Medical Documents -->
                <div class="card-panel">
                    <div class="card-header">
                        <div class="card-title">MEDICAL DOCUMENTS</div>
                        <button class="btn-edit">View Documents</button>
                    </div>
                    <div class="doc-file">
                        <i class="fas fa-file-pdf"></i> Blood_Test_Report.pdf
                        <div class="doc-status"><i class="fas fa-check"></i> Uploaded</div>
                    </div>
                    <!-- Mock dynamic secondary document -->
                    <div class="doc-file">
                        <i class="fas fa-file-pdf"></i> Previous_Prescription.pdf
                        <div class="doc-status"><i class="fas fa-check"></i> Uploaded</div>
                    </div>
                    <div class="privacy-lock">
                        <i class="fas fa-lock"></i> Documents are securely shared with the selected doctor for this consultation.
                    </div>
                </div>

                <!-- Important Info -->
                <div class="notice-card">
                    <h3><i class="fas fa-info-circle"></i> BEFORE YOU CONTINUE</h3>
                    <ul>
                        <li>Please join the consultation a few minutes early.</li>
                        <li>Ensure your microphone/camera permissions are enabled for video or audio consultation.</li>
                        <li>Keep relevant medical documents available.</li>
                        <li>Consultation availability depends on the doctor's schedule.</li>
                    </ul>
                    <div class="emergency-warning">
                        <i class="fas fa-exclamation-triangle" style="margin-top: 4px;"></i>
                        <span><strong>EMERGENCY WARNING:</strong> If you are experiencing severe chest pain, breathing difficulties, or a medical emergency, please visit the nearest emergency room or dial emergency services immediately.</span>
                    </div>
                </div>

                <!-- Terms & Consent -->
                <div class="consent-card">
                    <div class="consent-row">
                        <input type="checkbox" id="chk-correct" required checked>
                        <label for="chk-correct">I confirm that the appointment details are correct.</label>
                    </div>
                    <div class="consent-row">
                        <input type="checkbox" id="chk-terms" required checked>
                        <label for="chk-terms">I agree to the Doctor Assist <span>Terms & Privacy Policy</span>.</label>
                    </div>
                    <div class="consent-row">
                        <input type="checkbox" id="chk-warn" required checked>
                        <label for="chk-warn">I understand that online consultation may not be suitable for emergency medical situations.</label>
                    </div>
                </div>

                <button type="button" class="btn-primary" style="width: 100%; padding: 16px; font-size: 1.15rem;" id="desktop-submit-btn" onclick="continueToPayment()">🔒 Proceed to Secure Payment →</button>

            </div>

            <!-- Sticky Payment Card (Desktop) -->
            <div>
                <div class="payment-card">
                    <div class="payment-title">Payment Summary</div>

                    <div class="bill-row strong">
                        <span>Doctor Consultation</span>
                        <span>₹<?php echo ($totalFee - 15); ?></span>
                    </div>
                    <div class="bill-row">
                        <span>Platform Charge</span>
                        <span>₹15</span>
                    </div>

                    <div class="bill-total">
                        <span>TOTAL</span>
                        <span>₹<?php echo $totalFee; ?></span>
                    </div>

                    <form id="payment-form" method="POST" action="payment.php" style="display:none;">
                        <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($docId); ?>">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($consultType); ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($appDate); ?>">
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($appTime); ?>">
                        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($totalFee); ?>">
                        <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($patientName); ?>">
                        <input type="hidden" name="patient_mobile" value="<?php echo htmlspecialchars($patientMobile); ?>">
                    </form>

                    <button type="button" class="btn-primary" style="width:100%; margin-top:24px; padding: 16px; font-size: 1.1rem; justify-content:center;" onclick="continueToPayment()">🔒 Proceed Payment</button>
                </div>
            </div>

        </div>
    </div>

    <!-- Mobile Bottom Action -->
    <div class="mobile-bottom-action">
        <div>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--clr-primary-dark); line-height: 1;">₹<?php echo $totalFee; ?></div>
            <div style="font-size: 0.85rem; color: var(--clr-text-secondary); margin-top: 4px;">Total Payable</div>
        </div>
        <button type="button" class="btn-primary" onclick="continueToPayment()">🔒 Proceed to Payment →</button>
    </div>

    <script>
        function continueToPayment() {
            const c1 = document.getElementById('chk-correct');
            const c2 = document.getElementById('chk-terms');
            const c3 = document.getElementById('chk-warn');
            if (!c1.checked || !c2.checked || !c3.checked) {
                alert('Please check all consent boxes before proceeding.');
                return;
            }
            document.getElementById('payment-form').submit();
        }
    </script>

</body>

</html>