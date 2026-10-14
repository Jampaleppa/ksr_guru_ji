<?php
/**
 * AI OCR Prescription Ingestion Pipeline
 * Receives physical prescription images from the patient interface,
 * simulates OCR (Optical Character Recognition) text extraction, 
 * packages the raw text into structured JSON, and inserts it into the 
 * pharmacist verification queue.
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../includes/db.php';
$pdo = getDBConnection();

// Simulate extracting file from request
$hasImage = isset($_FILES['prescription_image']) || isset($_POST['simulate_image']);

if (!$hasImage) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image provided for AI OCR Pipeline']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Mock Patient Context (In production, pulled from $_SESSION or upload context)
    $patientId = 3; 
    
    // 2. Simulated OCR Engine Delay & Extraction
    // Simulating Cloud Vision / Tesseract reading handwriting
    $extractedText = "RX\nDOLO 650mg 1-0-1 5 days\nAmoxicillin 500mg 1-1-1 3 Days (after food)";
    
    // 3. Simulated AI NLP Structuring
    // Simulating OpenAI / NLP Model parsing the OCR text into JSON
    $aiMedicines = [
        ['name' => 'Dolo 650', 'strength' => '650mg', 'dose' => '1 Tab', 'freq' => '1-0-1', 'dur' => '5 Days', 'type' => 'Tablet', 'confidence' => 0.98],
        ['name' => 'Amoxicillin', 'strength' => '500mg', 'dose' => '1 Cap', 'freq' => '1-1-1', 'dur' => '3 Days', 'type' => 'Capsule', 'confidence' => 0.91]
    ];

    // 4. Create AI-Origin Consultation Record (assigned to designated or System Doctor)
    $docId = $_POST['doc_id'] ?? 16; // 16 is Dr. D C Rathod
    $stmtC = $pdo->prepare("INSERT INTO consultations (patient_id, doctor_id, symptoms, doctor_assessment, diagnosis) VALUES (?, ?, ?, ?, ?)");
    $stmtC->execute([$patientId, $docId, 'AI OCR Ingestion', 'AI Processed Upload', 'Pending Review']);
    $consultationId = $pdo->lastInsertId();

    // 5. Create PENDING Prescription Record (For Pharmacist Queue)
    // verification_status = MANUAL_REVIEW because it was AI ingested
    $stmtRx = $pdo->prepare("INSERT INTO prescriptions (consultation_id, patient_id, prescription_date, status, verification_status) VALUES (?, ?, CURDATE(), 'ACTIVE', 'MANUAL_REVIEW')");
    $stmtRx->execute([$consultationId, $patientId]);
    $prescriptionId = $pdo->lastInsertId();

    // 6. Append AI Mapped Medicines
    $stmtRxM = $pdo->prepare("INSERT INTO prescription_medicines (prescription_id, medicine_name, dosage_form, strength, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($aiMedicines as $med) {
        $stmtRxM->execute([
            $prescriptionId,
            $med['name'],
            $med['type'],
            $med['strength'],
            $med['freq'],
            $med['dur'],
            'AI Conf: ' . ($med['confidence'] * 100) . '%'
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Image successfully processed by AI OCR Pipeline.',
        'job_id' => 'AI-OCR-' . strtoupper(substr(md5($prescriptionId), 0, 8)),
        'pharmacist_queue' => 'MANUAL_REVIEW',
        'extracted' => $aiMedicines
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
