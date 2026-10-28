<?php
require_once 'helper.php';

$pdo = getAssistDB();

// Gather params from URL
$docId = $_GET['doc_id'] ?? 1;
$consultType = $_GET['type'] ?? 'video';
$appDate = $_GET['date'] ?? 'Today, Sep 06';
$appTime = $_GET['time'] ?? '02:30 PM';

// Mock calculations
$docFee = ($consultType === 'audio') ? 100 : 125;
$platformFee = 15;
$totalFee = $docFee + $platformFee;

// Fetch Doctor Info
$stmt = $pdo->prepare("SELECT doctor_name, specialty FROM doctors WHERE id = ?");
$stmt->execute([$docId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    if ($docId == 2) {
        $doctor = ['doctor_name' => 'Dr. Shivukumar Rathod', 'specialty' => 'Pediatrics & General Medicine'];
    } else {
        $doctor = ['doctor_name' => TELEHEALTH_LEAD_DOCTOR_NAME, 'specialty' => TELEHEALTH_LEAD_DOCTOR_SPECIALTY];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .flow-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
            margin-bottom: 80px;
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
        .back-link:hover { color: var(--clr-primary); }

        .page-title { font-size: 1.8rem; font-weight: 700; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .page-subtitle { font-size: 1.05rem; color: var(--clr-text-secondary); margin-bottom: 20px; }
        
        .step-tracker { display: flex; align-items: center; gap: 8px; margin-bottom: 32px; font-size: 0.95rem; font-weight: 600; color: var(--clr-text-secondary); }
        .dots { display: flex; gap: 6px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--clr-border); }
        .dot.active { background: var(--clr-primary); }
        .dot.passed { background: var(--clr-primary-light); }

        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-sm);
        }

        .section-header { font-size: 1.15rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 24px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; }

        /* Patient Selection */
        .patient-toggle-wrap { display: flex; gap: 16px; margin-bottom: 24px; }
        .patient-box {
            flex: 1; border: 2px solid var(--clr-border); border-radius: var(--radius-md);
            padding: 24px; text-align: left; cursor: pointer; transition: var(--transition);
            display: flex; gap: 16px; align-items: center; position: relative;
        }
        .patient-box:hover { border-color: var(--clr-primary-light); background: #F8FAFC; }
        .patient-box.selected { border-color: var(--clr-primary); background: var(--clr-primary-light); }
        .patient-box.selected::after { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 16px; top: 16px; color: var(--clr-primary); font-size: 1.25rem; }
        
        .p-icon { width: 50px; height: 50px; border-radius: 50%; background: var(--clr-bg-main); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--clr-text-secondary); }
        .patient-box.selected .p-icon { background: var(--clr-primary); color: white; }
        .p-name { font-weight: 600; font-size: 1.05rem; color: var(--clr-text-primary); margin-bottom: 4px; }
        .p-type { font-size: 0.85rem; color: var(--clr-text-secondary); }

        .btn-outline { background: transparent; border: 1px solid var(--clr-primary); color: var(--clr-primary); padding: 12px 24px; border-radius: var(--radius-full); font-weight: 600; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline:hover { background: var(--clr-primary-light); }

        /* Forms */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .form-group label { display: block; font-weight: 500; font-size: 0.95rem; color: var(--clr-text-secondary); margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 14px 16px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-family: inherit; font-size: 1rem; color: var(--clr-text-primary); transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--clr-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        /* Reason & Symptoms */
        .symptom-chips { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
        .chip { padding: 8px 16px; border: 1px solid var(--clr-border); border-radius: var(--radius-full); font-size: 0.9rem; color: var(--clr-text-secondary); cursor: pointer; transition: var(--transition); }
        .chip:hover { border-color: var(--clr-primary); color: var(--clr-primary); }
        .chip.selected { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }

        /* Medical Info Lists */
        .medical-list-item { background: #F8FAFC; border: 1px dashed var(--clr-border); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 12px; font-weight: 500; display: flex; align-items: center; justify-content: space-between; }
        .medical-list-item .btn-add { color: var(--clr-primary); cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; }

        /* File Upload */
        .upload-zone { border: 2px dashed var(--clr-primary); background: var(--clr-primary-light); border-radius: var(--radius-md); padding: 40px; text-align: center; cursor: pointer; transition: var(--transition); margin-bottom: 24px; }
        .upload-zone:hover { background: #DBEAFE; }
        .upload-zone i { font-size: 3rem; color: var(--clr-primary); margin-bottom: 16px; }
        .upload-title { font-weight: 600; color: var(--clr-primary-dark); font-size: 1.1rem; margin-bottom: 8px; }
        .upload-desc { font-size: 0.9rem; color: var(--clr-text-secondary); }

        .uploaded-file-card { display: flex; align-items: center; justify-content: space-between; padding: 16px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); margin-bottom: 12px; background: white; }
        .uploaded-file-info { display: flex; align-items: center; gap: 12px; font-weight: 500; font-size: 0.95rem; }
        .uploaded-file-info i { color: var(--clr-primary); font-size: 1.25rem; }
        .file-status { color: #16A34A; font-size: 0.85rem; font-weight: 600; margin-left: 12px; }
        .file-delete { color: #EF4444; cursor: pointer; }

        /* Consent / Privacy */
        .privacy-box { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-sm); padding: 16px; margin-bottom: 24px; color: #166534; font-size: 0.95rem; font-weight: 500; display: flex; align-items: center; gap: 12px; }
        .checkbox-group { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .checkbox-group input { margin-top: 4px; width: 18px; height: 18px; accent-color: var(--clr-primary); }
        .checkbox-group label { font-size: 0.95rem; color: var(--clr-text-secondary); line-height: 1.5; }

        /* Sticky Summary */
        .sticky-summary { position: sticky; top: 100px; background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 32px; box-shadow: var(--shadow-lg); }
        .summary-title { font-size: 1.1rem; font-weight: 700; border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; margin-bottom: 24px; }
        .summary-doc { display: flex; gap: 16px; margin-bottom: 24px; }
        .summary-doc .doc-icon { width: 50px; height: 50px; border-radius: var(--radius-sm); background: var(--clr-primary-light); color:var(--clr-primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 1rem; color: var(--clr-text-secondary); }
        .summary-row.total { border-top: 1px dashed var(--clr-border); padding-top: 20px; font-weight: 700; color: var(--clr-text-primary); font-size: 1.25rem; }
        .summary-dt { background: #F8FAFC; padding: 20px; border-radius: var(--radius-sm); margin-bottom: 24px; border: 1px solid var(--clr-border); }
        .summary-dt p { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; color: var(--clr-text-primary); font-weight: 600; }
        .summary-dt p:last-child { margin-bottom: 0; }
        .summary-dt i { color: var(--clr-primary); font-size: 1.1rem; width: 24px; text-align: center; }

        /* Mobile Bottom Nav/Action */
        .mobile-bottom-action { display: none; position: fixed; bottom: 0; left: 0; width: 100%; background: white; border-top: 1px solid var(--clr-border); padding: 16px 24px; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); z-index: 1000; }

        @media (max-width: 900px) {
            .flow-layout { grid-template-columns: 1fr; }
            .sticky-summary { display: none; }
            .mobile-bottom-action { display: flex; justify-content: space-between; align-items: center; }
            body { padding-bottom: 80px; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .patient-toggle-wrap { flex-direction: column; }
        }
        @media (max-width: 768px) {
            html, body { overflow-x: hidden; }
            .flow-layout { grid-template-columns: 1fr !important; }
            .vitals-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .vitals-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .patient-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
        @media (max-width: 360px) {
            .container { padding: 0 8px; }
            .vitals-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
    </div>
</nav>

<div class="container" style="padding-top: 40px;">
    
    <a href="doctor-profile.php?id=<?php echo $docId; ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Profile</a>

    <div class="flow-layout">
        <!-- Main Form Content -->
        <div>
            <h1 class="page-title">Complete Patient Details</h1>
            <p class="page-subtitle">Please provide the patient's information for this consultation.</p>

            <div class="step-tracker">
                Step 4 of 6
                <div class="dots">
                    <div class="dot passed"></div>
                    <div class="dot passed"></div>
                    <div class="dot passed"></div>
                    <div class="dot active"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
            </div>

            <form action="booking-review.php" method="POST" id="patientForm">
                
                <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($docId); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($consultType); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($appDate); ?>">
                <input type="hidden" name="time" value="<?php echo htmlspecialchars($appTime); ?>">
                <input type="hidden" name="total_amount" value="<?php echo htmlspecialchars($totalFee); ?>">

                <!-- Selection -->
                <div class="form-card">
                    <h2 class="section-title">Who is this consultation for?</h2>
                    
                    <div class="patient-toggle-wrap">
                        <div class="patient-box selected" id="box-myself" onclick="selectPatient('myself')">
                            <div class="p-icon"><i class="fas fa-user"></i></div>
                            <div>
                                <div class="p-name">Myself</div>
                                <div class="p-type" id="my-name">Jampaleppa</div>
                            </div>
                        </div>
                        <div class="patient-box" id="box-family" onclick="selectPatient('family')">
                            <div class="p-icon"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="p-name">Family Member</div>
                                <div class="p-type">Select Patient →</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-outline"><i class="fas fa-plus"></i> Add New Patient</button>
                </div>

                <!-- Patient Information -->
                <div class="form-card">
                    <h2 class="section-title">Patient Information</h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="patient_name" id="field-name" value="Jampaleppa" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="patient_dob" id="field-dob" value="1980-05-15" required>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="patient_gender" id="field-gender" required>
                                <option value="Male" selected>Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Blood Group (Optional)</label>
                            <select name="patient_blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+</option>
                                <option value="B+">B+</option>
                                <option value="O+">O+</option>
                                <option value="AB+">AB+</option>
                                <option value="A-">A-</option>
                                <option value="B-">B-</option>
                                <option value="O-">O-</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Reason for Consult -->
                <div class="form-card">
                    <h2 class="section-title">What would you like to consult about?</h2>
                    
                    <div class="form-group">
                        <textarea name="symptoms" id="symptoms-input" rows="4" placeholder="Describe your health concern...&#10;&#10;Example:&#10;&quot;I have been experiencing fever and headache for the last two days.&quot;" required></textarea>
                    </div>

                    <div style="font-size: 0.9rem; color: var(--clr-text-secondary); margin-top: 16px; margin-bottom: 8px;">Quick Symptoms:</div>
                    <div class="symptom-chips">
                        <div class="chip" onclick="toggleChip(this, 'Fever')">Fever</div>
                        <div class="chip" onclick="toggleChip(this, 'Headache')">Headache</div>
                        <div class="chip" onclick="toggleChip(this, 'Cough')">Cough</div>
                        <div class="chip" onclick="toggleChip(this, 'Cold')">Cold</div>
                        <div class="chip" onclick="toggleChip(this, 'Stomach Pain')">Stomach Pain</div>
                        <div class="chip" onclick="toggleChip(this, 'Skin Problem')">Skin Problem</div>
                        <div class="chip" onclick="toggleChip(this, 'Other')">Other</div>
                    </div>
                </div>

                <!-- Medical Info -->
                <div class="form-card">
                    <h2 class="section-title">Medical Information (Optional)</h2>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label>Do you have any existing medical conditions?</label>
                        <div class="medical-list-item">
                            <span class="btn-add"><i class="fas fa-plus"></i> Add Medical Information</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label>Currently taking any medicines?</label>
                        <div class="medical-list-item">
                            <span class="btn-add"><i class="fas fa-plus"></i> Add Medicine</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Any allergies?</label>
                        <div class="medical-list-item">
                            <span class="btn-add"><i class="fas fa-plus"></i> Add Allergy</span>
                        </div>
                    </div>
                </div>

                <!-- Medical Docs -->
                <div class="form-card">
                    <h2 class="section-title">Medical Documents (Optional)</h2>
                    
                    <div class="upload-zone" onclick="document.getElementById('docs-upload').click()">
                        <i class="fas fa-file-medical-alt"></i>
                        <div class="upload-title">Upload Prescription, Report<br>or Relevant Medical Document</div>
                        <div class="upload-desc">Supports PDF, JPG, PNG</div>
                        
                        <input type="file" id="docs-upload" style="display:none;" onchange="showMockUpload()">
                    </div>

                    <div id="uploaded-files-container" style="display: none;">
                        <div class="uploaded-file-card">
                            <div class="uploaded-file-info">
                                <i class="fas fa-file-pdf"></i>
                                <span>Blood_Test_Report.pdf <span class="file-status">✓ Uploaded successfully</span></span>
                            </div>
                            <div class="file-delete"><i class="fas fa-trash-alt"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Consent -->
                <div class="form-card" style="background: transparent; box-shadow: none; border: none; padding: 0;">
                    <div class="privacy-box">
                        <i class="fas fa-lock"></i> Your health information is strictly private and HIPAA compliant.
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="confirm_accurate" required>
                        <label for="confirm_accurate">I confirm that the information provided is accurate to the best of my knowledge.</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="confirm_consent" required>
                        <label for="confirm_consent">I consent to sharing these details and relevant documents with the selected doctor for consultation.</label>
                    </div>
                </div>

                <!-- Only desktop submit, mobile has sticky nav -->
                <button type="submit" class="btn-primary" style="width: 100%; padding: 16px; font-size: 1.15rem;" id="desktop-submit-btn">Continue to Review <i class="fas fa-arrow-right"></i></button>

            </form>
        </div>

        <!-- Sticky Summary Column (Desktop) -->
        <div>
            <div class="sticky-summary">
                <div class="summary-title">APPOINTMENT SUMMARY</div>
                
                <div class="summary-doc">
                    <div class="doc-icon"><i class="fas fa-user-md"></i></div>
                    <div>
                        <div style="font-weight: 700; color: var(--clr-primary-dark); font-size: 1.1rem;"><?php echo htmlspecialchars($doctor['doctor_name']); ?></div>
                        <div style="font-size: 0.95rem; color: var(--clr-text-secondary);"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                    </div>
                </div>

                <div class="summary-dt">
                    <p>
                        <i class="fas <?php echo $consultType === 'audio' ? 'fa-headphones-alt' : 'fa-video'; ?>"></i> 
                        <span><?php echo ucfirst($consultType); ?> Consultation</span>
                    </p>
                    <p><i class="fas fa-calendar-alt"></i> <span><?php echo htmlspecialchars($appDate); ?></span></p>
                    <p><i class="fas fa-clock"></i> <span><?php echo htmlspecialchars($appTime); ?></span></p>
                </div>

                <div class="summary-row total" style="margin-bottom: 24px;">
                    <span>TOTAL</span>
                    <span>₹<?php echo $totalFee; ?></span>
                </div>

                <button class="btn-primary" style="width:100%; padding:16px;" onclick="document.getElementById('patientForm').submit();">Continue to Review <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

    </div>
</div>

<!-- Mobile Bottom Action -->
<div class="mobile-bottom-action">
    <div>
        <div style="font-size: 0.85rem; color: var(--clr-text-secondary);">Total</div>
        <div style="font-size: 1.25rem; font-weight: 700; color: var(--clr-primary-dark);">₹<?php echo $totalFee; ?></div>
    </div>
    <button class="btn-primary" onclick="document.getElementById('patientForm').submit();">Continue <i class="fas fa-arrow-right"></i></button>
</div>

<script>
    function selectPatient(type) {
        document.getElementById('box-myself').classList.remove('selected');
        document.getElementById('box-family').classList.remove('selected');
        
        if (type === 'myself') {
            document.getElementById('box-myself').classList.add('selected');
            document.getElementById('field-name').value = 'Jampaleppa'; 
            document.getElementById('field-dob').value = '1980-05-15';
            document.getElementById('field-gender').value = 'Male';
        } else {
            document.getElementById('box-family').classList.add('selected');
            document.getElementById('field-name').value = ''; 
            document.getElementById('field-dob').value = '';
            document.getElementById('field-gender').value = '';
        }
    }

    function toggleChip(chipElement, symptomName) {
        chipElement.classList.toggle('selected');
        const input = document.getElementById('symptoms-input');
        
        if (chipElement.classList.contains('selected')) {
            if (input.value && !input.value.endsWith(' ')) {
                input.value += ', ' + symptomName;
            } else {
                input.value += symptomName;
            }
        }
        // Basic toggle logic, can be refined to remove tokens securely.
    }

    function showMockUpload() {
        const fileInput = document.getElementById('docs-upload');
        let fileToUpload = null;

        if (fileInput.files.length > 0) {
            fileToUpload = fileInput.files[0];
        } else {
            // Automated Testing Fallback (Generates Fake Image Blob)
            const mockContent = 'Mock physical prescription image bytes...';
            fileToUpload = new File([mockContent], "test_prescription_scan.jpg", {type: "image/jpeg"});
        }
        
        // Show loading state
        const container = document.getElementById('uploaded-files-container');
        container.style.display = 'block';
        container.innerHTML = `
            <div class="uploaded-file-card" style="border-color: #60A5FA; background: #EFF6FF;">
                <div class="uploaded-file-info">
                    <i class="fas fa-microchip" style="color:#2563EB;"></i>
                    <span>AI Engine analyzing layout... <span class="file-status" style="color:#2563EB;">Scanning OCR</span></span>
                </div>
                <div class="spinner" style="width:16px; height:16px; border:2px solid #2563EB; border-top-color:transparent; border-radius:50%; animation:spin 1s linear infinite;"></div>
            </div>`;

        // Create Payload
        const formData = new FormData();
        formData.append('simulate_image', 'true');
        formData.append('prescription_image', fileToUpload);
        formData.append('doc_id', '<?php echo $docId; ?>');

        // Dispatch to AI Pipeline
        fetch('../../api/telehealth/ai-ingestion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => {
                    container.innerHTML = `
                        <div class="uploaded-file-card">
                            <div class="uploaded-file-info">
                                <i class="fas fa-file-pdf"></i>
                                <span>${fileToUpload.name} <span class="file-status">✓ Processed (${data.job_id})</span></span>
                            </div>
                            <div class="file-delete"><i class="fas fa-trash-alt"></i></div>
                        </div>
                        <div style="font-size:0.8rem; color:#64748B; margin-top:-8px; padding-left:16px; margin-bottom:12px;">
                            <i class="fas fa-robot text-primary"></i> Extracted ${data.extracted.length} medicines. Sent to Pharmacist Review.
                        </div>`;
                }, 1500); // Simulated AI delay for visual effect
            } else {
                container.innerHTML = `<div style="color:red; font-weight:bold;">${data.message || 'Error'}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = `<div style="color:red; font-weight:bold;">OCR Pipeline Error</div>`;
        });
    }
</script>

</body>
</html>
