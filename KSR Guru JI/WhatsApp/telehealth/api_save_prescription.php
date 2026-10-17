<?php
/**
 * api_save_prescription.php
 * ---------------------------------------------------------------------
 * Writes a teleconsultation prescription to the Doctor Assist database:
 * consultations → prescriptions → prescription_medicines (single transaction).
 *
 * POST JSON:
 * {
 *   "patient_id": 3,
 *   "doctor_id": 16,                 // optional, resolved from the session otherwise
 *   "diagnosis": "Viral URI",
 *   "symptoms": "Fever, dry cough",
 *   "notes": "Lungs clear",
 *   "follow_up": "Review after 5 days",
 *   "medicines": [
 *      {"name":"Paracetamol","strength":"500mg","dosage_form":"Tablet",
 *       "frequency":"1-1-1","duration":"3 Days","instructions":"After food"}
 *   ]
 * }
 *
 * The same payload shape is accepted as form-encoded POST data
 * (medicines[] = name|strength|form|frequency|duration|instructions).
 */
declare(strict_types=1);

require_once 'helper.php';

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

/* Form-encoded fallback: medicines[] = "name|strength|form|freq|duration|instruction" */
if (empty($payload['medicines']) && !empty($_POST['medicines'])) {
    $payload['medicines'] = array_map(
        static function ($line): array {
            $parts = array_pad(explode('|', (string) $line), 6, '');

            return [
                'name'         => trim($parts[0]),
                'strength'     => trim($parts[1]),
                'dosage_form'  => trim($parts[2]),
                'frequency'    => trim($parts[3]),
                'duration'     => trim($parts[4]),
                'instructions' => trim($parts[5]),
            ];
        },
        (array) $_POST['medicines']
    );
}

try {
    $pdo    = getAssistDB();
    $result = telehealthSavePrescription($pdo, $payload);

    echo json_encode([
        'ok'              => true,
        'success'         => true,
        'prescription_id' => $result['prescription_id'],
        'consultation_id' => $result['consultation_id'],
        'audit_id'        => $result['rx_code'],
        'rx_code'         => $result['rx_code'],
        'message'         => 'Prescription ' . $result['rx_code']
            . ' saved and queued for pharmacist verification.',
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
