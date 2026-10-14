<?php
/**
 * seed-telehealth-data.php
 * ---------------------------------------------------------------------
 * Ensures the Doctor Assist (telehealth) folder has ALL of its data:
 *
 *   1. Medicine catalogue  → ksr_guru_ji.medicine_catalogue, the 3,689-record
 *      master consumed by medicine/tablet.php and by the prescription builder
 *      (imported from public/telehealth/data.sql when the table is empty).
 *   2. Doctors             → Dr. D C Rathod is normalised to 3 years of
 *      experience, "Neuro Care, Cardiology" specialties, VERIFIED status and
 *      the audio-consultation number 6362079192.
 *   3. Patients            → at least one patient so prescriptions can be written.
 *
 * Safe to re-run: every step is idempotent.
 *
 * Usage:  php seed-telehealth-data.php                          (CLI)
 *         /WhatsApp/public/telehealth/seed-telehealth-data.php  (browser)
 */
require_once 'helper.php';

$log   = [];
$error = null;

/* =====================================================================
   HELPERS
   ===================================================================== */

/** Split a .sql dump into individual statements (string / comment aware). */
function seedSplitSql(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $inString   = false;
    $quote      = '';
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];

        if ($inString) {
            $buffer .= $ch;

            if ($ch === '\\' && $i + 1 < $length) {           // escaped character
                $buffer .= $sql[++$i];
                continue;
            }

            if ($ch === $quote) {
                if ($i + 1 < $length && $sql[$i + 1] === $quote) { // doubled quote
                    $buffer .= $sql[++$i];
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true;
            $quote    = $ch;
            $buffer  .= $ch;
            continue;
        }

        if (($ch === '-' && $i + 1 < $length && $sql[$i + 1] === '-') || $ch === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }

        if ($ch === ';') {
            $statements[] = trim($buffer);
            $buffer       = '';
            continue;
        }

        $buffer .= $ch;
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return array_values(array_filter($statements, static fn ($s) => $s !== ''));
}

/**
 * Import a catalogue dump into the catalogue database.
 *
 * @return array{imported:int, statements:int, skipped:int}
 */
function seedImportCatalogue(string $sqlFile): array
{
    $pdo = getCatalogueDB();

    if (!is_readable($sqlFile)) {
        throw new RuntimeException('Catalogue dump not found: ' . $sqlFile);
    }

    $statements = seedSplitSql((string) file_get_contents($sqlFile));
    $skipped    = 0;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            // Structural / session statements may be no-ops on a live database.
            $head = strtoupper(substr(ltrim($statement), 0, 12));
            if (str_starts_with($head, 'SET')
                || str_starts_with($head, 'DROP TABLE')
                || str_starts_with($head, 'CREATE TABLE')
                || str_starts_with($head, 'LOCK TABLE')
                || str_starts_with($head, 'UNLOCK TABLE')
            ) {
                $skipped++;
                continue;
            }
            throw new RuntimeException('Catalogue import failed: ' . $e->getMessage());
        }
    }

    return [
        'imported'   => telehealthCatalogueCount(),
        'statements' => count($statements),
        'skipped'    => $skipped,
    ];
}

/* =====================================================================
   1. MEDICINE CATALOGUE  (the data behind medicine/tablet.php)
   ===================================================================== */
try {
    $catalogueDb = telehealthCatalogueDbName();
    $count       = telehealthCatalogueCount();

    if ($count > 0) {
        $log[] = "Catalogue  : $catalogueDb.medicine_catalogue already holds $count active medicines.";
    } else {
        $result = seedImportCatalogue(__DIR__ . '/data.sql');
        $log[]  = "Catalogue  : imported {$result['imported']} medicines from data.sql"
            . " ({$result['statements']} statements, {$result['skipped']} structural statements skipped).";
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

/* =====================================================================
   2. DOCTORS  →  Dr. D C Rathod (3 yrs · Neuro Care, Cardiology · audio 6362079192)
   ===================================================================== */
try {
    $pdo = getAssistDB();

    $clinicDetails = 'KSR GURU JI Telehealth Clinic · Audio call: ' . TELEHEALTH_AUDIO_CALL_NUMBER
        . ' · Video/Audio consults 9:00 AM – 9:00 PM';

    $st = $pdo->prepare(
        "SELECT d.id, d.user_id
           FROM doctors d
           LEFT JOIN users u ON u.id = d.user_id
          WHERE (d.doctor_name LIKE '%Rathod%' AND d.doctor_name NOT LIKE '%shiv%')
             OR d.doctor_name = 'D C'
             OR u.name = 'D C'"
    );
    $st->execute();
    $leadDoctors = $st->fetchAll();

    $updateDoctor = $pdo->prepare(
        "UPDATE doctors
            SET doctor_name = ?, qualification = ?, specialty = ?, experience_years = ?,
                verification_status = 'VERIFIED', clinic_hospital_details = ?,
                uploaded_docs_json = COALESCE(uploaded_docs_json, JSON_OBJECT()),
                updated_at = NOW()
          WHERE id = ?"
    );

    $updateUserPhone = $pdo->prepare('UPDATE users SET phone = ?, mobile = ? WHERE id = ?');

    $touched = 0;

    foreach ($leadDoctors as $doctor) {
        $updateDoctor->execute([
            TELEHEALTH_LEAD_DOCTOR_NAME,
            TELEHEALTH_LEAD_DOCTOR_QUALIFICATION,
            TELEHEALTH_LEAD_DOCTOR_SPECIALTY,
            TELEHEALTH_LEAD_DOCTOR_EXPERIENCE,
            $clinicDetails,
            (int) $doctor['id'],
        ]);

        if ((int) $doctor['user_id'] > 0) {
            $updateUserPhone->execute([
                TELEHEALTH_AUDIO_CALL_NUMBER,
                TELEHEALTH_AUDIO_CALL_NUMBER,
                (int) $doctor['user_id'],
            ]);
        }

        $touched++;
    }

    if ($touched === 0) {
        // No D C Rathod record yet → create the user (when needed) and the doctor row.
        $st = $pdo->prepare(
            "SELECT id FROM users
              WHERE role = 'doctor' AND name LIKE '%Rathod%' AND name NOT LIKE '%shiv%'
              LIMIT 1"
        );
        $st->execute();
        $userId = (int) $st->fetchColumn();

        if ($userId <= 0) {
            $pdo->prepare(
                "INSERT INTO users (name, email, password, role, phone, mobile, account_status)
                 VALUES (?, ?, ?, 'doctor', ?, ?, 'ACTIVE')"
            )->execute([
                TELEHEALTH_LEAD_DOCTOR_NAME,
                'dcrathod@ksr.com',
                password_hash('DCRathod@406', PASSWORD_DEFAULT),
                TELEHEALTH_AUDIO_CALL_NUMBER,
                TELEHEALTH_AUDIO_CALL_NUMBER,
            ]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $updateUserPhone->execute([
                TELEHEALTH_AUDIO_CALL_NUMBER,
                TELEHEALTH_AUDIO_CALL_NUMBER,
                $userId,
            ]);
        }

        $pdo->prepare(
            "INSERT INTO doctors
                 (user_id, doctor_name, qualification, specialty, registration_number,
                  registration_authority, experience_years, clinic_hospital_details,
                  verification_status, uploaded_docs_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'VERIFIED', JSON_OBJECT())"
        )->execute([
            $userId,
            TELEHEALTH_LEAD_DOCTOR_NAME,
            TELEHEALTH_LEAD_DOCTOR_QUALIFICATION,
            TELEHEALTH_LEAD_DOCTOR_SPECIALTY,
            'KMC-DCR-0003',
            'Karnataka Medical Council',
            TELEHEALTH_LEAD_DOCTOR_EXPERIENCE,
            $clinicDetails,
        ]);

        $log[] = "Doctors    : created '" . TELEHEALTH_LEAD_DOCTOR_NAME . "' (user #$userId).";
    } else {
        $log[] = "Doctors    : normalised $touched Dr. D C Rathod record(s) → "
            . TELEHEALTH_LEAD_DOCTOR_EXPERIENCE . ' yrs · ' . TELEHEALTH_LEAD_DOCTOR_SPECIALTY
            . ' · audio ' . TELEHEALTH_AUDIO_CALL_NUMBER . '.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

/* =====================================================================
   3. PATIENTS  →  at least one writeable patient record
   ===================================================================== */
try {
    $pdo = getAssistDB();

    $patientCount = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

    if ($patientCount === 0) {
        $pdo->prepare(
            "INSERT INTO users (name, email, password, role, phone, mobile, account_status)
             VALUES (?, ?, ?, 'patient', ?, ?, 'ACTIVE')"
        )->execute([
            'Jampaleppa K Rathod',
            'jampaleppa.patient@ksrguruji.com',
            password_hash('Patient@406', PASSWORD_DEFAULT),
            '8088584421',
            '8088584421',
        ]);
        $patientUserId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO patients
                 (user_id, patient_name, date_of_birth, gender, mobile_number, address,
                  emergency_contact, medical_history, allergies, current_medications)
             VALUES (?, ?, ?, 'Male', ?, ?, ?, ?, ?, ?)"
        )->execute([
            $patientUserId,
            'Jampaleppa K Rathod',
            '1996-05-01',
            '8088584421',
            'Laxmi Nagar, Koppal, Karnataka',
            '8762474406',
            'Mild hypertension (2026)',
            'Penicillin (mild rash)',
            'Amlodipine 5mg — once daily',
        ]);

        $log[] = 'Patients   : created 1 teleconsultation patient (user #' . $patientUserId . ').';
    } else {
        $log[] = "Patients   : $patientCount patient record(s) available.";
    }

    /* =====================================================================
       4. VERIFY  →  report the resulting state
       ===================================================================== */
    $lead = telehealthDoctorProfile($pdo);

    $log[] = 'Lead doctor   : ' . $lead['name']
        . ' · ' . $lead['experience_years'] . ' yrs'
        . ' · ' . $lead['specialty']
        . ' · audio ' . $lead['audio_call']
        . ' · ' . $lead['verification_status'];

    $stats = telehealthStats($pdo);

    $log[] = 'Prescriptions : ' . $stats['total'] . ' total ('
        . $stats['active'] . ' active, ' . $stats['pending'] . ' pending, '
        . $stats['verified'] . ' verified, ' . $stats['medicines'] . ' medicines).';

    $log[] = 'Catalogue     : ' . telehealthCatalogueCount() . ' active medicines searchable in the builder.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

/* =====================================================================
   OUTPUT
   ===================================================================== */
if (PHP_SAPI === 'cli') {
    echo "=== TELEHEALTH DATA SEED ===\n";
    foreach ($log as $line) {
        echo ' - ' . $line . "\n";
    }
    if ($error !== null) {
        echo 'ERROR: ' . $error . "\n";
        exit(1);
    }
    echo "Done.\n";
    exit(0);
}

http_response_code($error === null ? 200 : 500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telehealth Data Seed | Doctor Assist</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding: 40px 16px; background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); }
        .seed-card { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 20px; padding: 32px; box-shadow: 0 20px 50px rgba(15,23,42,.12); }
        .seed-card h1 { font-size: 1.5rem; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .seed-card p.sub { color: var(--clr-text-secondary); margin-bottom: 24px; }
        .seed-line { border-left: 3px solid var(--clr-primary); background: #F8FAFC; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; font-family: ui-monospace, Consolas, monospace; font-size: .9rem; }
        .seed-err { border-left-color: #DC2626; background: #FEF2F2; color: #991B1B; }
        .seed-actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .seed-actions a { padding: 10px 18px; border-radius: 8px; font-weight: 700; text-decoration: none; background: var(--clr-primary); color: #fff; }
        .seed-actions a.ghost { background: #fff; color: var(--clr-primary); border: 1px solid var(--clr-border); }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>





    <div class="seed-card">
        <h1><i class="fas fa-database"></i> Telehealth data seed</h1>
        <p class="sub">Medicine catalogue, doctors and patients for the Doctor Assist telehealth folder.</p>

        <?php foreach ($log as $line): ?>
            <div class="seed-line"><?= h($line) ?></div>
        <?php endforeach; ?>

        <?php if ($error !== null): ?>
            <div class="seed-line seed-err">ERROR: <?= h($error) ?></div>
        <?php endif; ?>

        <div class="seed-actions">
            <a href="seed-telehealth-data.php">Run again</a>
            <a class="ghost" href="build-prescription.php">Open prescription builder</a>
            <a class="ghost" href="medicine/tablet.php">Medicine catalogue (tablet.php)</a>
        </div>
    </div>
</body>
</html>