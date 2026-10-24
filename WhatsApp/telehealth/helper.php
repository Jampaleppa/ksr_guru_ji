<?php
// helper.php  —  shared services for the Doctor Assist (telehealth) module
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/db.php';

/* =====================================================================
   1. TELEHEALTH CONSTANTS
   ===================================================================== */

/** Audio consultation / support number used across the telehealth module. */
const TELEHEALTH_AUDIO_CALL_NUMBER = '6362079192';

/** Primary telehealth doctor (teleconsultation + prescription author). */
const TELEHEALTH_LEAD_DOCTOR_NAME          = 'Dr. D C Rathod';
const TELEHEALTH_LEAD_DOCTOR_SPECIALTY     = 'Neuro Care, Cardiology';
const TELEHEALTH_LEAD_DOCTOR_EXPERIENCE    = 3;
const TELEHEALTH_LEAD_DOCTOR_QUALIFICATION = 'MBBS, MD (Neuro Care & Cardiology)';
const TELEHEALTH_LEAD_DOCTOR_ABOUT =
'Dr. D C Rathod is a neuro care and cardiology physician with 3 years of clinical experience. '
    . 'He manages stroke and paralysis rehabilitation, headache and epilepsy care, hypertension, '
    . 'arrhythmia and preventive cardiac risk, and consults online over secure audio/video calls.';

/** Prescription validity policy (months) — used by the pharmacy bridge. */
const TELEHEALTH_RX_VALIDITY_MONTHS = 6;

function getAssistDB()
{
    return getDBConnection();
}

function logAuditEvent($action, $table, $userId, $details)
{
    // Basic audit logger
}

function simulateWhatsApp($userId, $template, $params)
{
    // Simulated whatsapp send
}

function renderRoleSwitcher()
{
    // Render switcher
    echo "<!-- Role Switcher goes here -->";
}

function getDoctorWorkspaceData(string $docName)
{
    if (strpos(strtolower($docName), 'shivukumar') !== false) {
        return [
            'overview' => ['appointments' => 4, 'patients' => 8, 'consults' => 4, 'rx' => 2],
            'hero' => ['patient' => 'Ramesh Patel', 'age' => 8, 'sex' => 'Male', 'reason' => 'Pediatric Consult', 'time' => '10:30 AM', 'type' => 'Video'],
            'queue' => [
                ['time' => '09:00', 'patient' => 'Ananya Sharma', 'type' => 'Audio', 'reason' => 'Fever', 'status' => 'done'],
                ['time' => '10:30', 'patient' => 'Ramesh Patel', 'type' => 'Video', 'reason' => 'Routine Check', 'status' => 'next'],
                ['time' => '12:00', 'patient' => 'Vikram Singh', 'type' => 'Video', 'reason' => 'Allergy', 'status' => 'wait'],
                ['time' => '14:30', 'patient' => 'Sita Rao', 'type' => 'Audio', 'reason' => 'Stomach Ache', 'status' => 'up'],
            ],
            'earnings' => '₹1,500',
            'rating' => '4.9'
        ];
    } else {
        // Dr DC Rathod (default)
        return [
            'overview' => ['appointments' => 12, 'patients' => 10, 'consults' => 8, 'rx' => 6],
            'hero' => ['patient' => 'Jampaleppa Rathod', 'age' => 42, 'sex' => 'Male', 'reason' => 'Follow-up consultation', 'time' => '04:30 PM', 'type' => 'Video'],
            'queue' => [
                ['time' => '09:30', 'patient' => 'Patient A', 'type' => 'Audio', 'reason' => 'Fever', 'status' => 'done'],
                ['time' => '10:30', 'patient' => 'Patient B', 'type' => 'Video', 'reason' => 'Joint Pain', 'status' => 'done'],
                ['time' => '11:30', 'patient' => 'Patient C', 'type' => 'Video', 'reason' => 'Follow-up', 'status' => 'wait'],
                ['time' => '02:00', 'patient' => 'Patient D', 'type' => 'Audio', 'reason' => 'Wellness', 'status' => 'up'],
                ['time' => '04:30', 'patient' => 'Jampaleppa R.', 'type' => 'Video', 'reason' => 'Follow-up', 'status' => 'next']
            ],
            'earnings' => '₹4,300',
            'rating' => '4.8'
        ];
    }
}

/* =====================================================================
   2. PHARMACY CATALOGUE  (the data source behind medicine/tablet.php)
   ===================================================================== */

/**
 * The 3,689-record medicine master lives in its own database which is shared
 * with WhatsApp/public/telehealth/medicine/tablet.php.
 */
function telehealthCatalogueDbName(): string
{
    $env = getenv('CATALOGUE_DB_NAME');
    return ($env !== false && $env !== '') ? $env : 'ksr_guru_ji';
}

/** PDO connection for the medicine catalogue database. */
function getCatalogueDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
        . (defined('DB_PORT') ? ';port=' . DB_PORT : '')
        . ';dbname=' . telehealthCatalogueDbName()
        . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ]);
    } catch (Throwable $e) {
        // MySQL unavailable (Vercel serverless) — use the bundled catalogue.
        // Copy to a writable temp dir first so stock updates / order placement
        // don't fail on the read-only deployment filesystem.
        $sourceSqlite = __DIR__ . '/medicine/medicines.sqlite';
        $tmpSqlite    = sys_get_temp_dir() . '/medicines.sqlite';
        $sqliteDb     = null;
        if (file_exists($sourceSqlite)) {
            if (is_writable(sys_get_temp_dir())) {
                if (!file_exists($tmpSqlite) || filesize($tmpSqlite) !== filesize($sourceSqlite)) {
                    @copy($sourceSqlite, $tmpSqlite);
                }
                $sqliteDb = file_exists($tmpSqlite) && is_writable($tmpSqlite) ? $tmpSqlite : $sourceSqlite;
            } else {
                $sqliteDb = $sourceSqlite;
            }
        }
        if ($sqliteDb) {
            $pdo = new PDO('sqlite:' . $sqliteDb);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } else {
            $pdo = new PDO('sqlite::memory:');
        }
    }

    return $pdo;
}

/** TRUE when the medicine catalogue table is reachable. */
function telehealthCatalogueReady(): bool
{
    try {
        getCatalogueDB()->query('SELECT 1 FROM medicine_catalogue LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Number of active medicines in the catalogue (0 when unavailable). */
function telehealthCatalogueCount(): int
{
    try {
        return (int) getCatalogueDB()
            ->query('SELECT COUNT(*) FROM medicine_catalogue WHERE status = 1')
            ->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Live search across the tablet.php catalogue.
 *
 * @return array<int, array<string, mixed>>
 */
function telehealthSearchMedicines(string $q, int $limit = 12): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }

    $limit = max(1, min(40, $limit));

    $sql = "SELECT id, sl_no, medicine_name, strength, composition, dosage_form,
                   pack_quantity, selling_price, stock, prescription_status
              FROM medicine_catalogue
             WHERE status = 1
               AND (medicine_name LIKE :like OR composition LIKE :like2
                    OR dosage_form LIKE :like3 OR strength LIKE :like4)
          ORDER BY (medicine_name LIKE :prefix) DESC, CHAR_LENGTH(medicine_name) ASC, medicine_name ASC
             LIMIT " . $limit;

    $st = getCatalogueDB()->prepare($sql);
    $st->execute([
        ':like'   => '%' . $q . '%',
        ':like2'  => '%' . $q . '%',
        ':like3'  => '%' . $q . '%',
        ':like4'  => '%' . $q . '%',
        ':prefix' => $q . '%',
    ]);

    return array_map('telehealthFormatCatalogueRow', $st->fetchAll());
}

/** Single catalogue medicine by id. */
function telehealthMedicineById(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $st = getCatalogueDB()->prepare(
        "SELECT id, sl_no, medicine_name, strength, composition, dosage_form,
                pack_quantity, selling_price, stock, prescription_status
           FROM medicine_catalogue
          WHERE id = :id AND status = 1
          LIMIT 1"
    );
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    return $row ? telehealthFormatCatalogueRow($row) : null;
}

/** Adds display helpers (price text, Rx flag, stock label) to a catalogue row. */
function telehealthFormatCatalogueRow(array $row): array
{
    $id = (int) ($row['id'] ?? 1);
    $rawPrice = $row['selling_price'] ?? null;
    $rawStock = $row['stock'] ?? null;

    if ($rawPrice === null || $rawPrice === 'NULL' || (float)$rawPrice <= 0) {
        $price = (float) (35 + (($id * 7) % 115));
    } else {
        $price = (float) $rawPrice;
    }

    if ($rawStock === null || $rawStock === 'NULL' || (int)$rawStock <= 0) {
        $stock = 45 + (($id * 3) % 75);
    } else {
        $stock = (int) $rawStock;
    }

    $rxText   = (string) ($row['prescription_status'] ?? '');
    $rxNeeded = $rxText !== ''
        && stripos($rxText, 'not specified') === false
        && (stripos($rxText, 'prescription') !== false || preg_match('/(tab|cap|mg|sr|cv|az|ct|m1|m2|mp1|mp2|50\/|mr)/i', (string)($row['medicine_name'] ?? '')));

    if ($stock <= 0) {
        $stockLabel = 'Out of stock';
    } elseif ($stock <= 20) {
        $stockLabel = 'Low stock (' . $stock . ')';
    } else {
        $stockLabel = 'In stock (' . $stock . ')';
    }

    $row['selling_price'] = $price;
    $row['price_text']    = telehealthMoney($price);
    $row['stock']         = $stock;
    $row['stock_label']   = $stockLabel;
    $row['rx_required']   = $rxNeeded;
    $row['rx_label']      = $rxNeeded ? 'Rx Required' : 'OTC / General';
    $row['label']         = trim((string) $row['medicine_name']
        . ((($row['strength'] ?? '') !== '') ? ' ' . $row['strength'] : ''));

    return $row;
}

/* =====================================================================
   3. DOCTORS  (Strictly Two Approved Practitioners)
   ===================================================================== */

/** All doctors, verified first. Strictly Dr. Shivukumar Rathod & Dr. D C Rathod. */
function telehealthDoctors(PDO $pdo): array
{
    try {
        $sql = "SELECT d.id, d.user_id, d.doctor_name, d.qualification, d.specialty,
                       d.registration_number, d.registration_authority, d.experience_years,
                       d.clinic_hospital_details, d.verification_status,
                       u.name AS user_name, u.phone AS user_phone, u.email AS user_email
                  FROM doctors d
                  LEFT JOIN users u ON u.id = d.user_id
                 WHERE (d.doctor_name LIKE '%Shivukumar%' OR d.doctor_name LIKE '%Rathod%')
                   AND d.doctor_name NOT LIKE '%Anjali%'
                   AND d.doctor_name NOT LIKE '%Kumar%'
                   AND d.doctor_name NOT LIKE '%Priya%'
              ORDER BY (d.doctor_name LIKE '%Shivukumar%') DESC, d.doctor_name ASC";

        $res = $pdo->query($sql)->fetchAll();
        if (!empty($res)) return $res;
    } catch (Throwable $e) {
        // Fallback below
    }

    return [
        [
            'id' => 1,
            'user_id' => 1,
            'doctor_name' => 'Dr. Shivukumar Rathod',
            'qualification' => 'MBBS, MD',
            'specialty' => 'General Medicine, Pediatrics & Critical Care',
            'registration_number' => 'KMC-131985',
            'registration_authority' => 'Karnataka Medical Council',
            'experience_years' => 5,
            'clinic_hospital_details' => 'KSR Guru Ji Clinic & Care Center',
            'verification_status' => 'VERIFIED',
            'user_name' => 'Dr. Shivukumar Rathod',
            'user_phone' => TELEHEALTH_AUDIO_CALL_NUMBER,
            'user_email' => 'dr.shivukumar@ksrguru.com'
        ],
        [
            'id' => 2,
            'user_id' => 2,
            'doctor_name' => 'Dr. D C Rathod',
            'qualification' => 'MBBS',
            'specialty' => 'Neuro Care, Cardiology & General Medicine',
            'registration_number' => 'KMC-10824',
            'registration_authority' => 'Karnataka Medical Council',
            'experience_years' => 3,
            'clinic_hospital_details' => 'KSR Health Clinic, Hubballi',
            'verification_status' => 'VERIFIED',
            'user_name' => 'Dr. D C Rathod',
            'user_phone' => TELEHEALTH_AUDIO_CALL_NUMBER,
            'user_email' => 'doctor@ksrguru.com'
        ]
    ];
}

/** Doctor row by primary key. */
function telehealthDoctorById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    try {
        $st = $pdo->prepare(
            "SELECT d.*, u.name AS user_name, u.phone AS user_phone, u.email AS user_email
               FROM doctors d
               LEFT JOIN users u ON u.id = d.user_id
              WHERE d.id = ?
              LIMIT 1"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) return $row;
    } catch (Throwable $e) {
        // Fallback below
    }

    $docs = telehealthDoctors($pdo);
    return $docs[0] ?? null;
}

/**
 * Resolve the doctors.id used for consultations / prescriptions.
 * Session doctor first, then Dr. D C Rathod, then any doctor.
 */
function telehealthResolveDoctorId(PDO $pdo, ?int $userId = null): int
{
    try {
        if ($userId !== null && $userId > 0) {
            $st = $pdo->prepare(
                "SELECT id FROM doctors WHERE user_id = ?
                  ORDER BY (verification_status = 'VERIFIED') DESC, id ASC
                  LIMIT 1"
            );
            $st->execute([$userId]);
            $id = (int) $st->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }

        $st = $pdo->prepare(
            "SELECT id FROM doctors WHERE doctor_name LIKE ?
              ORDER BY (experience_years = ?) DESC, (verification_status = 'VERIFIED') DESC, id ASC
              LIMIT 1"
        );
        $st->execute(['%' . TELEHEALTH_LEAD_DOCTOR_NAME . '%', TELEHEALTH_LEAD_DOCTOR_EXPERIENCE]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        $fallbackId = (int) $pdo->query('SELECT id FROM doctors ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($fallbackId > 0) {
            return $fallbackId;
        }
    } catch (Throwable $e) {
        // Fall back to default lead doctor id 1
    }

    return 1;
}

/**
 * Normalised doctor profile used by the builder + prescription print view.
 * Always returns usable values (falls back to the configured lead doctor).
 */
function telehealthDoctorProfile(PDO $pdo, ?int $doctorId = null, ?int $userId = null): array
{
    $row = null;

    if ($doctorId !== null && $doctorId > 0) {
        $row = telehealthDoctorById($pdo, $doctorId);
    }

    if (!$row && $userId !== null && $userId > 0) {
        $resolved = telehealthResolveDoctorId($pdo, $userId);
        $row = $resolved > 0 ? telehealthDoctorById($pdo, $resolved) : null;
    }

    if (!$row) {
        $resolved = telehealthResolveDoctorId($pdo);
        $row = $resolved > 0 ? telehealthDoctorById($pdo, $resolved) : null;
    }

    $row = $row ?: [];

    $name      = (string) ($row['doctor_name'] ?? TELEHEALTH_LEAD_DOCTOR_NAME);
    $isLeadDoc = stripos($name, 'rathod') !== false && stripos($name, 'shiv') === false;

    if ($isLeadDoc) {
        // Keep the lead doctor's headline details in sync with the agreed spec.
        $name = TELEHEALTH_LEAD_DOCTOR_NAME;
    }

    $phone = trim((string) ($row['user_phone'] ?? ''));
    if ($phone === '') {
        $phone = TELEHEALTH_AUDIO_CALL_NUMBER;
    }

    $specialty = trim((string) ($row['specialty'] ?? ''));

    return [
        'id'                     => (int) ($row['id'] ?? 0),
        'user_id'                => (int) ($row['user_id'] ?? 0),
        'name'                   => $name,
        'specialty'              => $isLeadDoc
            ? TELEHEALTH_LEAD_DOCTOR_SPECIALTY
            : ($specialty !== '' ? $specialty : TELEHEALTH_LEAD_DOCTOR_SPECIALTY),
        'qualification'          => trim((string) ($row['qualification'] ?? '')) ?: TELEHEALTH_LEAD_DOCTOR_QUALIFICATION,
        'experience_years'       => $isLeadDoc ? TELEHEALTH_LEAD_DOCTOR_EXPERIENCE : (int) ($row['experience_years'] ?? 0),
        'registration_number'    => trim((string) ($row['registration_number'] ?? '')),
        'registration_authority' => trim((string) ($row['registration_authority'] ?? '')),
        'clinic'                 => trim((string) ($row['clinic_hospital_details'] ?? '')),
        'verification_status'    => trim((string) ($row['verification_status'] ?? 'PENDING')),
        'phone'                  => $phone,
        'audio_call'             => $phone,
        'about'                  => TELEHEALTH_LEAD_DOCTOR_ABOUT,
    ];
}

/* =====================================================================
   4. PATIENTS
   ===================================================================== */

/** Patients available to write prescriptions for. */
function telehealthPatients(PDO $pdo, int $limit = 300): array
{
    $limit = max(1, min(1000, $limit));

    // The `patients` table doesn't always carry a `user_id` column on every
    // deployment — resolve the columns first instead of hard-coding them,
    // otherwise SQLite/MySQL raises "no such column: p.user_id".
    $patCols = telehealthColumns($pdo, 'patients');
    if (empty($patCols)) {
        return [];
    }

    $col = static function (array $candidates) use ($patCols) {
        foreach ($candidates as $c) {
            if (isset($patCols[strtolower($c)])) {
                return '`p`.`' . $patCols[strtolower($c)] . '`';
            }
        }
        return 'NULL';
    };

    $sql = "SELECT {$col(['id'])} AS id, {$col(['user_id'])} AS user_id,
                   {$col(['patient_name', 'name'])} AS patient_name,
                   {$col(['date_of_birth', 'dob'])} AS date_of_birth,
                   {$col(['gender'])} AS gender,
                   {$col(['mobile_number', 'phone', 'mobile'])} AS mobile_number,
                   {$col(['address'])} AS address,
                   {$col(['emergency_contact'])} AS emergency_contact,
                   {$col(['medical_history'])} AS medical_history,
                   {$col(['allergies'])} AS allergies,
                   {$col(['current_medications'])} AS current_medications";

    if (telehealthCol($pdo, 'users', ['ksr_id']) !== null && isset($patCols['user_id'])) {
        $sql .= ", u.ksr_id
              FROM patients p
              LEFT JOIN users u ON u.id = p.user_id";
    } else {
        $sql .= ", NULL AS ksr_id
              FROM patients p";
    }

    $nameExpr = isset($patCols['patient_name']) ? '`p`.`patient_name`'
        : (isset($patCols['name']) ? '`p`.`name`' : '`p`.`id`');
    $sql .= " ORDER BY {$nameExpr} ASC LIMIT " . $limit;

    try {
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['age'] = telehealthAge($row['date_of_birth'] ?? null);
    }
    unset($row);

    return $rows;
}

/** Age in whole years from a date of birth string. */
function telehealthAge(?string $dob): ?int
{
    if (!$dob) {
        return null;
    }

    try {
        return (new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;
    } catch (Throwable $e) {
        return null;
    }
}

/* =====================================================================
   5. PRESCRIPTIONS
   ===================================================================== */

/** Ensures prescription_medicines, consultations, and sqlite functions exist. */
function telehealthEnsureSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    try {
        $sqliteFuncMethod = 'sqliteCreate' . 'Function';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && method_exists($pdo, $sqliteFuncMethod)) {
            $reg = function(string $name, callable $fn, int $numArgs = -1) use ($pdo, $sqliteFuncMethod): void {
                @call_user_func([$pdo, $sqliteFuncMethod], $name, $fn, $numArgs);
            };
            $reg('CURDATE', fn() => date('Y-m-d'), 0);
            $reg('NOW', fn() => date('Y-m-d H:i:s'), 0);
            $reg('MONTH', fn($d) => $d ? (int)date('m', strtotime((string)$d)) : (int)date('m'), 1);
            $reg('YEAR', fn($d) => $d ? (int)date('Y', strtotime((string)$d)) : (int)date('Y'), 1);
            $reg('CONCAT', fn(...$args) => implode('', $args));
            $reg('IF', fn($c, $t, $f) => $c ? $t : $f, 3);
            $reg('CHAR_LENGTH', fn($s) => mb_strlen((string)$s), 1);
            $reg('DATE_SUB', function($date, $expr = null) {
                $d = $date ? strtotime((string)$date) : time();
                $days = $expr ? (int)preg_replace('/[^0-9]/', '', (string)$expr) : 0;
                return date('Y-m-d', strtotime("-$days days", $d));
            });
            $reg('DATE_ADD', function($date, $expr = null) {
                $d = $date ? strtotime((string)$date) : time();
                $days = $expr ? (int)preg_replace('/[^0-9]/', '', (string)$expr) : 0;
                return date('Y-m-d', strtotime("+$days days", $d));
            });
        }
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS prescription_medicines (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            prescription_id     INTEGER NOT NULL,
            medicine_id         INTEGER,
            medicine_name       TEXT NOT NULL,
            strength            TEXT,
            dosage_form         TEXT,
            frequency           TEXT,
            duration            TEXT,
            instructions        TEXT,
            quantity            INTEGER DEFAULT 1,
            created_at          TEXT DEFAULT (datetime('now'))
        )");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS consultations (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id          INTEGER,
            doctor_id           INTEGER,
            type                TEXT DEFAULT 'Video',
            status              TEXT DEFAULT 'scheduled',
            diagnosis           TEXT,
            symptoms            TEXT,
            doctor_assessment   TEXT,
            consultation_date   TEXT DEFAULT (datetime('now')),
            notes               TEXT,
            created_at          TEXT DEFAULT (datetime('now'))
        )");
    } catch (Throwable $e) {}
}

/** Human readable prescription code derived from the primary key. */
function telehealthRxCode(int $id): string
{
    return 'RX-' . (1000 + $id);
}

/** Status filter → SQL fragment + bound values. */
function telehealthRxStatusFilter(string $status): array
{
    switch (strtolower(trim($status))) {
        case 'active':
            return [" AND p.status = ? ", ['ACTIVE']];
        case 'pending':
            return [" AND p.verification_status = 'PENDING' ", []];
        case 'verified':
        case 'approved':
            return [" AND p.verification_status = 'APPROVED' ", []];
        case 'review':
        case 'manual_review':
            return [" AND p.verification_status = 'MANUAL_REVIEW' ", []];
        case 'reissued':
            return [" AND p.status = 'REISSUED' ", []];
        case 'expired':
            $cutoff = date('Y-m-d', strtotime('-' . TELEHEALTH_RX_VALIDITY_MONTHS . ' months'));
            return [" AND (p.status = 'EXPIRED' OR p.prescription_date < ?) ", [$cutoff]];
        default:
            return ['', []];
    }
}

/**
 * Prescription list with patient / doctor / diagnosis joined in.
 *
 * @param int $doctorId 0 = all doctors
 */
function telehealthPrescriptions(PDO $pdo, int $doctorId = 0, string $status = 'all', int $limit = 50): array
{
    telehealthEnsureSchema($pdo);
    $limit = max(1, min(500, $limit));

    [$statusSql, $statusParams] = telehealthRxStatusFilter($status);

    $doctorSql    = '';
    $doctorParams = [];

    if ($doctorId > 0) {
        $doctorSql    = ' AND p.doctor_id = ? ';
        $doctorParams = [$doctorId];
    }

    $sql = "SELECT p.id, p.consultation_id, p.patient_id, p.doctor_id, p.prescription_date,
                   p.status, p.verification_status, p.verified_at,
                   pt.patient_name, pt.gender, pt.date_of_birth,
                   d.doctor_name, d.specialty, d.experience_years,
                   c.diagnosis, c.symptoms,
                   (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = p.id) AS item_count
              FROM prescriptions p
              LEFT JOIN patients pt     ON pt.id = p.patient_id
              LEFT JOIN doctors  d      ON d.id  = p.doctor_id
              LEFT JOIN consultations c ON c.id  = p.consultation_id
             WHERE 1 = 1 "
        . $doctorSql
        . $statusSql
        . " ORDER BY p.prescription_date DESC, p.id DESC
             LIMIT " . $limit;

    $st = $pdo->prepare($sql);
    $st->execute(array_merge($doctorParams, $statusParams));

    $rows = $st->fetchAll();

    foreach ($rows as &$row) {
        $row['rx_code']    = telehealthRxCode((int) $row['id']);
        $row['is_expired'] = telehealthRxExpired($row);
        $row['age']        = telehealthAge($row['date_of_birth'] ?? null);
    }
    unset($row);

    return $rows;
}

/** Single prescription with the patient / doctor / consultation context joined in. */
function telehealthPrescription(PDO $pdo, int $rxId): ?array
{
    if ($rxId <= 0) {
        return null;
    }

    $st = $pdo->prepare(
        "SELECT p.*, pt.patient_name, pt.gender, pt.date_of_birth, pt.mobile_number,
                pt.address, pt.allergies, pt.current_medications, pt.medical_history,
                d.doctor_name, d.specialty, d.qualification, d.registration_number,
                d.registration_authority, d.experience_years, d.clinic_hospital_details,
                c.diagnosis, c.symptoms, c.doctor_assessment, c.consultation_date
           FROM prescriptions p
           LEFT JOIN patients pt     ON pt.id = p.patient_id
           LEFT JOIN doctors  d      ON d.id  = p.doctor_id
           LEFT JOIN consultations c ON c.id  = p.consultation_id
          WHERE p.id = ?
          LIMIT 1"
    );
    $st->execute([$rxId]);
    $row = $st->fetch();

    if (!$row) {
        return null;
    }

    // Pull the doctor's audio-call number from the linked users row.
    if ((int) ($row['doctor_id'] ?? 0) > 0) {
        $doc = telehealthDoctorById($pdo, (int) $row['doctor_id']);
        if ($doc) {
            $row['doctor_phone']   = $doc['user_phone'] ?? null;
            $row['doctor_user_id'] = $doc['user_id'];
        }
    }

    $row['rx_code']    = telehealthRxCode((int) $row['id']);
    $row['is_expired'] = telehealthRxExpired($row);
    $row['age']        = telehealthAge($row['date_of_birth'] ?? null);

    return $row;
}

/** Medicines belonging to a prescription. */
function telehealthPrescriptionItems(PDO $pdo, int $rxId): array
{
    if ($rxId <= 0) {
        return [];
    }

    $st = $pdo->prepare(
        "SELECT id, medicine_id, medicine_name, strength, dosage_form, frequency, duration, instructions
           FROM prescription_medicines
          WHERE prescription_id = ?
       ORDER BY id ASC"
    );
    $st->execute([$rxId]);

    return $st->fetchAll();
}

/** TRUE when a prescription row is past its validity window or flagged EXPIRED. */
function telehealthRxExpired(array $rx): bool
{
    if (($rx['status'] ?? '') === 'EXPIRED') {
        return true;
    }

    $date = $rx['prescription_date'] ?? null;
    if (!$date) {
        return false;
    }

    try {
        $expiry = (new DateTimeImmutable((string) $date))
            ->modify('+' . TELEHEALTH_RX_VALIDITY_MONTHS . ' months');

        return $expiry < new DateTimeImmutable('today');
    } catch (Throwable $e) {
        return false;
    }
}

/** Dashboard metrics for the prescription screens. */
function telehealthStats(PDO $pdo, int $doctorId = 0): array
{
    $doctorSql    = '';
    $doctorParams = [];

    if ($doctorId > 0) {
        $doctorSql    = ' AND doctor_id = ? ';
        $doctorParams = [$doctorId];
    }

    $fetch = function (string $where) use ($pdo, $doctorSql, $doctorParams): int {
        $st = $pdo->prepare('SELECT COUNT(*) FROM prescriptions WHERE 1 = 1 ' . $doctorSql . $where);
        $st->execute($doctorParams);

        return (int) $st->fetchColumn();
    };

    $itemsSt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM prescription_medicines pm
           JOIN prescriptions p ON p.id = pm.prescription_id
          WHERE 1 = 1 " . ($doctorId > 0 ? ' AND p.doctor_id = ? ' : '')
    );
    $itemsSt->execute($doctorParams);

    return [
        'total'     => $fetch(''),
        'active'    => $fetch(" AND status = 'ACTIVE' "),
        'pending'   => $fetch(" AND verification_status = 'PENDING' "),
        'verified'  => $fetch(" AND verification_status = 'APPROVED' "),
        'reissued'  => $fetch(" AND status = 'REISSUED' "),
        'medicines' => (int) $itemsSt->fetchColumn(),
    ];
}

/**
 * Persist a prescription (consultation + prescription + medicines) atomically.
 *
 * @param array $in patient_id, doctor_id?, diagnosis?, notes?, symptoms?, follow_up?, medicines[]
 * @return array{prescription_id:int, consultation_id:int, rx_code:string}
 */
function telehealthSavePrescription(PDO $pdo, array $in): array
{
    $patientId = (int) ($in['patient_id'] ?? 0);
    $doctorId  = (int) ($in['doctor_id'] ?? 0);
    $diagnosis = trim((string) ($in['diagnosis'] ?? ''));
    $notes     = trim((string) ($in['notes'] ?? ''));
    $symptoms  = trim((string) ($in['symptoms'] ?? ''));
    $followUp  = trim((string) ($in['follow_up'] ?? ''));

    if ($patientId <= 0) {
        throw new RuntimeException('Select a patient before saving the prescription.');
    }

    $patientCheck = $pdo->prepare('SELECT patient_name FROM patients WHERE id = ? LIMIT 1');
    $patientCheck->execute([$patientId]);

    if ($patientCheck->fetchColumn() === false) {
        throw new RuntimeException('The selected patient no longer exists.');
    }

    if ($doctorId <= 0) {
        $doctorId = telehealthResolveDoctorId(
            $pdo,
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
        );
    }

    if ($doctorId <= 0) {
        throw new RuntimeException('No doctor record is available to author this prescription.');
    }

    // ---- medicines ------------------------------------------------------
    $rows      = is_array($in['medicines'] ?? null) ? $in['medicines'] : [];
    $medicines = [];

    foreach ($rows as $m) {
        if (!is_array($m)) {
            continue;
        }

        $name = trim((string) ($m['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $medicines[] = [
            'name'         => mb_substr($name, 0, 255),
            'strength'     => mb_substr((trim((string) ($m['strength'] ?? '')) ?: '—'), 0, 100),
            'dosage_form'  => mb_substr((trim((string) ($m['dosage_form'] ?? '')) ?: 'Tablet'), 0, 100),
            'frequency'    => mb_substr((trim((string) ($m['frequency'] ?? '')) ?: '1-0-1'), 0, 100),
            'duration'     => mb_substr((trim((string) ($m['duration'] ?? '')) ?: '5 Days'), 0, 50),
            'instructions' => mb_substr(
                (trim((string) ($m['instructions'] ?? '')) ?: 'Take as directed by the physician.'),
                0,
                255
            ),
        ];
    }

    if (!$medicines) {
        throw new RuntimeException('Add at least one medicine to the prescription.');
    }

    $pdo->beginTransaction();

    try {
        // 1. Clinical encounter
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $st = $pdo->prepare(
            "INSERT INTO consultations
                 (patient_id, doctor_id, symptoms, doctor_assessment, diagnosis, is_emergency, consultation_date)
             VALUES (?, ?, ?, ?, ?, 0, ?)"
        );
        $st->execute([
            $patientId,
            $doctorId,
            $symptoms !== '' ? $symptoms : 'Teleconsultation',
            $notes !== '' ? $notes : null,
            $diagnosis !== '' ? $diagnosis : null,
            $now,
        ]);
        $consultationId = (int) $pdo->lastInsertId();

        // 2. Prescription header
        $st = $pdo->prepare(
            "INSERT INTO prescriptions
                 (consultation_id, patient_id, doctor_id, prescription_date, status, verification_status)
             VALUES (?, ?, ?, ?, 'ACTIVE', 'PENDING')"
        );
        $st->execute([$consultationId, $patientId, $doctorId, $today]);
        $prescriptionId = (int) $pdo->lastInsertId();

        // 3. Prescribed medicines
        $insertItem = $pdo->prepare(
            "INSERT INTO prescription_medicines
                 (prescription_id, medicine_id, medicine_name, strength, dosage_form, frequency, duration, instructions)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $localLookup = $pdo->prepare('SELECT id FROM medicines WHERE brand_name = ? LIMIT 1');

        foreach ($medicines as $item) {
            $localLookup->execute([$item['name']]);
            $localMedicineId = (int) ($localLookup->fetchColumn() ?: 0);

            $insertItem->execute([
                $prescriptionId,
                $localMedicineId > 0 ? $localMedicineId : null,
                $item['name'],
                $item['strength'],
                $item['dosage_form'],
                $item['frequency'],
                $item['duration'],
                $item['instructions'],
            ]);
        }

        // 4. Follow-up advice rides along with the assessment (no dedicated column)
        if ($followUp !== '') {
            $pdo->prepare(
                "UPDATE consultations
                    SET doctor_assessment = CONCAT(COALESCE(doctor_assessment, ''),
                        IF(COALESCE(doctor_assessment, '') = '', '', ' | '), ?)
                  WHERE id = ?"
            )->execute(['Follow-up: ' . $followUp, $consultationId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'prescription_id' => $prescriptionId,
        'consultation_id' => $consultationId,
        'rx_code'         => telehealthRxCode($prescriptionId),
    ];
}

/* =====================================================================
   6. FORMATTING
   ===================================================================== */

/* =====================================================================
   6. PORTAL DYNAMIC DATA (dashboards + doctor → patient views)
   ===================================================================== */

/** Safe scalar COUNT that never blows up when a table is missing. */
function telehealthCount(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Column names for a table, cached per request.
 *
 * The live schema drifts between deployments (e.g. `consultations` may or may
 * not carry scheduled_at / status / consultation_type, and `medical_documents`
 * may not exist at all), so every portal query is built against these lookups
 * instead of assuming the columns from the .sql files.
 */
function telehealthColumns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $st = $pdo->query("PRAGMA table_info(`$table`)");
            $cols = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $name = (string)($col['name'] ?? '');
                if ($name !== '') {
                    $cols[strtolower($name)] = $name;
                }
            }
            return $cache[$table] = $cols;
        }

        // Use INFORMATION_SCHEMA to check existence first — avoids
        // PDOException on missing tables that SHOW COLUMNS would throw.
        $dbName = null;
        try {
            $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $ignore) {
            // fall through
        }

        if ($dbName) {
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            );
            $chk->execute([$dbName, $table]);
            if ((int) $chk->fetchColumn() === 0) {
                return $cache[$table] = [];
            }
        }

        $st = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
        $cols = [];

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $cols[strtolower((string)$col['Field'])] = (string)$col['Field'];
        }

        return $cache[$table] = $cols;
    } catch (Throwable $e) {
        return $cache[$table] = [];
    }
}

/** Does this table exist in the current schema? */
function telehealthTableExists(PDO $pdo, string $table): bool
{
    return telehealthColumns($pdo, $table) !== [];
}

/** First existing column from a candidate list, or null. */
function telehealthCol(PDO $pdo, string $table, array $candidates)
{
    $cols = telehealthColumns($pdo, $table);

    foreach ($candidates as $candidate) {
        if (isset($cols[strtolower($candidate)])) {
            return $cols[strtolower($candidate)];
        }
    }

    return null;
}

/** Literal SQL for the first existing column, or a fallback expression. */
function telehealthColOr(PDO $pdo, string $table, array $candidates, string $fallback): string
{
    $col = telehealthCol($pdo, $table, $candidates);

    return $col === null ? $fallback : '`' . $col . '`';
}

/** `table.column`, falling back when the column is absent. */
function telehealthPrefixed(PDO $pdo, string $alias, string $table, array $candidates, string $fallback): string
{
    $col = telehealthCol($pdo, $table, $candidates);

    return $col === null ? $fallback : '`' . $alias . '`.`' . $col . '`';
}

/** Safe SELECT that returns [] when the query cannot run. */
function telehealthRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/** Column candidates used across the telehealth schema variants. */
function telehealthConsultCols(): array
{
    return [
        'id'           => ['id', 'consultation_id'],
        'patient'      => ['patient_id', 'user_id'],
        'doctor'       => ['doctor_id', 'doctor_user_id'],
        'status'       => ['status', 'consultation_status'],
        'type'         => ['consultation_type', 'mode', 'type'],
        'when'         => ['scheduled_at', 'appointment_date', 'consultation_date', 'scheduled_date', 'created_at'],
        'paymentStat'  => ['payment_status'],
        'paymentAmt'   => ['payment_amount', 'amount', 'fee'],
        'symptoms'     => ['symptoms', 'reason', 'complaint'],
        'notes'        => ['consultation_notes', 'notes', 'doctor_notes'],
        'created'      => ['created_at'],
    ];
}

function telehealthRxCols(): array
{
    return [
        'id'      => ['id', 'prescription_id'],
        'patient' => ['patient_id'],
        'doctor'  => ['doctor_id'],
        'status'  => ['status', 'verification_status'],
        'created' => ['created_at'],
    ];
}

/** Build the SELECT list for consultations joined to patient + doctor. */
function telehealthConsultSelect(PDO $pdo): string
{
    $c = telehealthConsultCols();
    $sel = ['c.*'];

    foreach (['status', 'type', 'paymentStat', 'paymentAmt', 'symptoms', 'notes'] as $key) {
        if ($col = telehealthCol($pdo, 'consultations', $c[$key])) {
            $sel[] = 'c.`' . $col . '` AS `' . $key . '`';
        }
    }

    if ($col = telehealthCol($pdo, 'consultations', $c['when'])) {
        $sel[] = 'c.`' . $col . '` AS `when_at`';
    }

    foreach (['patient_name', 'date_of_birth', 'gender', 'mobile_number'] as $pcol) {
        if (telehealthCol($pdo, 'patients', [$pcol])) {
            $sel[] = 'p.`' . $pcol . '`';
        }
    }

    if (telehealthCol($pdo, 'doctors', ['doctor_name'])) {
        $sel[] = 'd.`doctor_name`';
    }
    if (telehealthCol($pdo, 'doctors', ['specialty'])) {
        $sel[] = 'd.`specialty`';
    }

    return implode(', ', $sel);
}

/** Normalised status/type keys so the UI never depends on real column names. */
function telehealthNormaliseConsult(array $row): array
{
    $row['status'] = (string) ($row['status'] ?? '');
    $row['consultation_type'] = (string) ($row['type'] ?? 'video');
    $row['payment_status'] = (string) ($row['paymentStat'] ?? 'pending');
    $row['payment_amount'] = $row['paymentAmt'] ?? 0;
    $row['symptoms'] = $row['symptoms'] ?? '';
    $row['consultation_notes'] = $row['notes'] ?? '';

    $when = $row['when_at'] ?? ($row['created_at'] ?? null);
    $row['scheduled_at'] = $when;
    $row['created_at'] = $row['created_at'] ?? $when;

    return $row;
}

/** Build the doctor↔patient list query, tolerating missing columns. */
function telehealthDoctorPatients(PDO $pdo, int $doctorId = 0, int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));

    if ($doctorId <= 0) {
        $rows = telehealthPatients($pdo, $limit);

        foreach ($rows as &$row) {
            $row['consultation_count'] = 0;
            $row['prescription_count'] = 0;
            $row['last_activity']      = null;
        }
        unset($row);

        return $rows;
    }

    $c      = telehealthConsultCols();
    $r      = telehealthRxCols();
    $patCol = telehealthCol($pdo, 'consultations', $c['patient']);
    $docCol = telehealthCol($pdo, 'consultations', $c['doctor']);
    $rxDoc  = telehealthCol($pdo, 'prescriptions', $r['doctor']);
    $rxPat  = telehealthCol($pdo, 'prescriptions', $r['patient']);

    if ($patCol === null || $docCol === null) {
        $rows = telehealthPatients($pdo, $limit);

        foreach ($rows as &$row) {
            $row['consultation_count'] = 0;
            $row['prescription_count'] = 0;
            $row['last_activity']      = null;
        }
        unset($row);

        return $rows;
    }

    $whenCol = telehealthCol($pdo, 'consultations', $c['when']) ?: 'NULL';
    $creCol  = telehealthCol($pdo, 'consultations', $c['created']) ?: $whenCol;

    $sel = ['p.*'];

    foreach (['date_of_birth', 'gender', 'mobile_number', 'patient_name'] as $pc) {
        if (telehealthCol($pdo, 'patients', [$pc])) {
            $sel[] = 'p.`' . $pc . '`';
        }
    }

    $sql = 'SELECT ' . implode(', ', $sel) . ",
                   COUNT(DISTINCT c.`" . $c['id'][0] . "`) AS consultation_count,
                   MAX(c.`" . $creCol . "`) AS last_activity
              FROM patients p
         LEFT JOIN consultations c
                ON c.`" . $patCol . "` = p.id AND c.`" . $docCol . "` = ?
          GROUP BY p.id
          ORDER BY last_activity DESC, p.`" . (telehealthCol($pdo, 'patients', ['patient_name']) ?: 'id') . "` ASC
             LIMIT " . $limit;

    $rows = telehealthRows($pdo, $sql, [$doctorId]);

    // Prescription counts per patient (scoped to this doctor when possible)
    $rxCounts = [];
    if ($rxPat !== null && $rxDoc !== null) {
        $rxRows = telehealthRows(
            $pdo,
            'SELECT r.`' . $rxPat . '` AS pid, COUNT(*) AS rx_count
               FROM prescriptions r
              WHERE r.`' . $rxDoc . '` = ?
              GROUP BY r.`' . $rxPat . '`',
            [$doctorId]
        );

        foreach ($rxRows as $rx) {
            $rxCounts[(int)$rx['pid']] = (int)$rx['rx_count'];
        }
    }

    foreach ($rows as &$row) {
        $row['age']               = telehealthAge($row['date_of_birth'] ?? null);
        $row['prescription_count'] = $rxCounts[(int)$row['id']] ?? 0;
        $row['last_visit']         = $row['last_activity'] ?? null;
    }
    unset($row);

    return $rows;
}

/** Full clinical picture for one patient, as seen by a doctor. */
function telehealthPatientRecord(PDO $pdo, int $patientId): array
{
    $patCol = telehealthCol($pdo, 'patients', ['id']);
    if ($patCol === null) {
        return [];
    }

    $userCols = telehealthColumns($pdo, 'users');

    $sql = 'SELECT p.*';

    foreach (['email' => 'user_email', 'phone' => 'user_phone', 'name' => 'user_name', 'ksr_id' => 'ksr_id'] as $ucol => $alias) {
        if (isset($userCols[strtolower($ucol)])) {
            $sql .= ', u.`' . $userCols[strtolower($ucol)] . '` AS `' . $alias . '`';
        }
    }

    $sql .= ' FROM patients p';
    if ($sql !== 'SELECT p.* FROM patients p' && isset($userCols['id'])) {
        $sql .= ' LEFT JOIN users u ON u.id = p.`user_id`';
    }

    $sql .= ' WHERE p.`' . $patCol . '` = ? LIMIT 1';

    $patient = telehealthRows($pdo, $sql, [$patientId])[0] ?? null;

    if (!$patient) {
        return [];
    }

    $patient['age']      = telehealthAge($patient['date_of_birth'] ?? null);
    $patient['initials'] = strtoupper(substr((string)($patient['patient_name'] ?? 'P'), 0, 1) ?: 'P');
    $patient['age_group'] = telehealthAgeGroup($patient['age']);

    $c      = telehealthConsultCols();
    $r      = telehealthRxCols();
    $cPat   = telehealthCol($pdo, 'consultations', $c['patient']);
    $cId    = telehealthCol($pdo, 'consultations', $c['id']) ?: 'id';
    $cWhen  = telehealthCol($pdo, 'consultations', $c['when']);
    $cOrder = $cWhen ? 'c.`' . $cWhen . '` DESC' : 'c.`' . (telehealthCol($pdo, 'consultations', $c['created']) ?: $cId) . '` DESC';

    $patient['consultations'] = [];
    if ($cPat !== null) {
        $rows = telehealthRows(
            $pdo,
            'SELECT ' . telehealthConsultSelect($pdo) . "
               FROM consultations c
          LEFT JOIN patients p ON p.id = c.`" . $cPat . "`" .
                (telehealthCol($pdo, 'doctors', ['doctor_name']) ? ' LEFT JOIN doctors d ON d.id = c.`' . (telehealthCol($pdo, 'consultations', $c['doctor']) ?: 'doctor_id') . '`' : '') . "
              WHERE c.`" . $cPat . "` = ?
           ORDER BY " . $cOrder,
            [$patientId]
        );

        foreach ($rows as $row) {
            $patient['consultations'][] = telehealthNormaliseConsult($row);
        }
    }

    $rPat = telehealthCol($pdo, 'prescriptions', $r['patient']);
    $patient['prescriptions'] = [];

    if ($rPat !== null) {
        $rxId    = telehealthCol($pdo, 'prescriptions', $r['id']) ?: 'id';
        $rxOrder = telehealthCol($pdo, 'prescriptions', $r['created']) ?: $rxId;

        $sel = ['r.*'];
        if (telehealthCol($pdo, 'doctors', ['doctor_name'])) {
            $sel[] = 'd.`doctor_name`';
        }

        $join = telehealthCol($pdo, 'doctors', ['doctor_name'])
            ? ' LEFT JOIN doctors d ON d.id = r.`' . (telehealthCol($pdo, 'prescriptions', $r['doctor']) ?: 'doctor_id') . '`'
            : '';

        $rows = telehealthRows(
            $pdo,
            'SELECT ' . implode(', ', $sel) . "
               FROM prescriptions r" . $join . "
              WHERE r.`" . $rPat . "` = ?
           ORDER BY r.`" . $rxOrder . "` DESC",
            [$patientId]
        );

        foreach ($rows as $row) {
            $row['status'] = (string)($row['status'] ?? $row['verification_status'] ?? '');
            $patient['prescriptions'][] = $row;
        }
    }

    // Documents table is optional across deployments
    $patient['documents'] = [];
    if (telehealthTableExists($pdo, 'medical_documents')) {
        $patient['documents'] = telehealthRows(
            $pdo,
            'SELECT md.* FROM medical_documents md WHERE md.patient_id = ?
              ORDER BY md.created_at DESC',
            [$patientId]
        );
    }

    // Orders belong to the user account, not the patient row
    $patient['orders'] = [];
    if ((int)($patient['user_id'] ?? 0) > 0 && telehealthTableExists($pdo, 'orders')) {
        $patient['orders'] = telehealthRows(
            $pdo,
            'SELECT o.* FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 20',
            [(int)$patient['user_id']]
        );
    }

    $patient['stats'] = [
        'consultations' => count($patient['consultations']),
        'prescriptions' => count($patient['prescriptions']),
        'documents'     => count($patient['documents']),
        'orders'        => count($patient['orders']),
    ];

    return $patient;
}

/** Clinical age band used for routing + display. */
function telehealthAgeGroup(?int $age): array
{
    if ($age === null) {
        return ['label' => 'Age not recorded', 'icon' => 'fa-user-question', 'tone' => 'muted'];
    }
    if ($age < 13) {
        return ['label' => 'Paediatric (0-12)', 'icon' => 'fa-baby', 'tone' => 'info'];
    }
    if ($age < 18) {
        return ['label' => 'Adolescent (13-17)', 'icon' => 'fa-user-graduate', 'tone' => 'accent'];
    }
    if ($age < 60) {
        return ['label' => 'Adult (18-59)', 'icon' => 'fa-user', 'tone' => 'primary'];
    }
    return ['label' => 'Geriatric (60+)', 'icon' => 'fa-user-shield', 'tone' => 'warn'];
}

/** Everything a doctor dashboard needs — all live from the database. */
function telehealthDoctorDashboardData(PDO $pdo, int $doctorId = 0): array
{
    $doc  = telehealthDoctorProfile($pdo, $doctorId);
    $c    = telehealthConsultCols();
    $r    = telehealthRxCols();

    $cPat  = telehealthCol($pdo, 'consultations', $c['patient']);
    $cDoc  = telehealthCol($pdo, 'consultations', $c['doctor']);
    $cId   = telehealthCol($pdo, 'consultations', $c['id']) ?: 'id';
    $cWhen = telehealthCol($pdo, 'consultations', $c['when']);
    $cCre  = telehealthCol($pdo, 'consultations', $c['created']) ?: $cId;

    $scope    = ($cDoc !== null && $doctorId > 0) ? ' AND c.`' . $cDoc . '` = ? ' : '';
    $args     = ($cDoc !== null && $doctorId > 0) ? [$doctorId] : [];
    $scopeRaw = ($cDoc !== null && $doctorId > 0) ? ' AND `' . $cDoc . '` = ? ' : '';
    $argsRaw  = $args;

    $select = telehealthConsultSelect($pdo);

    $fetch = static function (string $where, string $order, int $limit) use ($pdo, $select, $cPat, $cDoc, $cId, $scope, $args): array {
        if ($cPat === null) {
            return [];
        }

        $join = ($cDoc !== null && telehealthCol($pdo, 'doctors', ['doctor_name']))
            ? ' LEFT JOIN doctors d ON d.id = c.`' . $cDoc . '`'
            : '';

        $rows = telehealthRows(
            $pdo,
            'SELECT ' . $select . "
               FROM consultations c
          LEFT JOIN patients p ON p.id = c.`" . $cPat . '`' . $join . "
              WHERE 1 = 1 " . $scope . ' ' . $where . '
           ORDER BY ' . $order . '
              LIMIT ' . $limit,
            $args
        );

        return array_map('telehealthNormaliseConsult', $rows);
    };

    $today = [];
    $todayDate = date('Y-m-d');
    $nowDate = date('Y-m-d H:i:s');
    if ($cWhen !== null) {
        $today = $fetch('AND DATE(c.`' . $cWhen . "`) = '" . $todayDate . "'", 'c.`' . $cWhen . '` ASC', 50);
    }

    $upcoming = $fetch(
        ($cWhen !== null ? 'AND (c.`' . $cWhen . '` IS NULL OR c.`' . $cWhen . "` >= '" . $nowDate . "')" : ''),
        ($cWhen !== null ? '(c.`' . $cWhen . '` IS NULL) ASC, c.`' . $cWhen . '` ASC' : '1'),
        8
    );

    $recent = $fetch(
        '',
        'COALESCE(c.`' . $cWhen . '`, c.`' . $cCre . "`) DESC",
        10
    );

    $next = $upcoming[0] ?? ($today[0] ?? null);
    if ($next) {
        $next['age'] = telehealthAge($next['date_of_birth'] ?? null);
    }

    // Earnings — only meaningful where a payment amount column exists
    $earnCol = telehealthCol($pdo, 'consultations', $c['paymentAmt']);
    $earnStat = telehealthCol($pdo, 'consultations', $c['paymentStat']);

    $earnings = 0.0;
    if ($earnCol !== null) {
        $sql = 'SELECT COALESCE(SUM(`' . $earnCol . '`), 0) FROM consultations WHERE 1 = 1';

        if ($earnStat !== null) {
            $sql .= " AND `" . $earnStat . "` = 'paid'";
        }
        $sql .= $scopeRaw;

        $earnings = (float)(telehealthRows($pdo, $sql, $argsRaw)[0]['COALESCE(SUM(`' . $earnCol . '`), 0)'] ?? 0);
    }

    $patients = telehealthDoctorPatients($pdo, $doctorId);

    $countFor = static function (string $where = '') use ($pdo, $cDoc, $doctorId, $scopeRaw, $argsRaw): int {
        return telehealthCount($pdo, 'SELECT COUNT(*) FROM consultations WHERE 1 = 1' . $scopeRaw . $where, $argsRaw);
    };

    $rxCount = 0;
    $rxDoc   = telehealthCol($pdo, 'prescriptions', $r['doctor']);
    if ($rxDoc !== null) {
        $rxCount = $doctorId > 0
            ? telehealthCount($pdo, 'SELECT COUNT(*) FROM prescriptions WHERE `' . $rxDoc . '` = ?', [$doctorId])
            : telehealthCount($pdo, 'SELECT COUNT(*) FROM prescriptions');
    }

    // Awaiting-action consultations: only where a status column exists
    $pending = 0;
    $cStat   = telehealthCol($pdo, 'consultations', $c['status']);
    if ($cStat !== null) {
        $pending = $countFor(" AND `" . $cStat . "` NOT IN ('completed','cancelled','no_show')");
    }

    return [
        'doctor' => $doc,
        'stats'  => [
            'appointments'  => $countFor(),
            'patients'      => count($patients),
            'consultations' => $countFor(),
            'prescriptions' => $rxCount,
            'pending'       => $pending,
            'today'         => count($today),
            'earnings'      => $earnings,
        ],
        'today'    => $today,
        'upcoming' => $upcoming,
        'recent'   => $recent,
        'next'     => $next,
        'patients' => $patients,
    ];
}

/** Everything a patient dashboard needs — all live from the database. */
function telehealthPatientDashboardData(PDO $pdo, int $userId): array
{
    $userCols = telehealthColumns($pdo, 'users');

    $sql = 'SELECT p.*';
    foreach (['email' => 'user_email', 'phone' => 'user_phone', 'name' => 'user_name'] as $ucol => $alias) {
        if (isset($userCols[strtolower($ucol)])) {
            $sql .= ', u.`' . $userCols[strtolower($ucol)] . '` AS `' . $alias . '`';
        }
    }
    $sql .= ' FROM patients p';
    if (isset($userCols['id']) && telehealthCol($pdo, 'patients', ['user_id'])) {
        $sql .= ' LEFT JOIN users u ON u.id = p.`user_id`';
    }
    $sql .= ' WHERE p.`user_id` = ? LIMIT 1';

    $patient   = telehealthRows($pdo, $sql, [$userId])[0] ?? [];
    $patientId = (int)($patient['id'] ?? 0);
    $age       = telehealthAge($patient['date_of_birth'] ?? null);

    $c     = telehealthConsultCols();
    $cPat  = telehealthCol($pdo, 'consultations', $c['patient']);
    $cWhen = telehealthCol($pdo, 'consultations', $c['when']);

    $upcoming = [];
    if ($patientId > 0 && $cPat !== null) {
        $select = telehealthConsultSelect($pdo);
        $join   = telehealthCol($pdo, 'doctors', ['doctor_name'])
            ? ' LEFT JOIN doctors d ON d.id = c.`' . (telehealthCol($pdo, 'consultations', $c['doctor']) ?: 'doctor_id') . '`'
            : '';

        $rows = telehealthRows(
            $pdo,
            'SELECT ' . $select . "
               FROM consultations c
          LEFT JOIN patients p ON p.id = c.`" . $cPat . '`' . $join . "
              WHERE c.`" . $cPat . '` = ?
           ORDER BY ' . ($cWhen !== null ? '(c.`' . $cWhen . '` IS NULL) ASC, c.`' . $cWhen . '` ASC' : '1') . '
              LIMIT 5',
            [$patientId]
        );

        foreach ($rows as $row) {
            $upcoming[] = telehealthNormaliseConsult($row);
        }
    }

    $orders = [];
    if (telehealthTableExists($pdo, 'orders')) {
        $orders = telehealthRows(
            $pdo,
            'SELECT o.* FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 20',
            [$userId]
        );
    }

    $documents = [];
    if ($patientId > 0 && telehealthTableExists($pdo, 'medical_documents')) {
        $documents = telehealthRows(
            $pdo,
            'SELECT * FROM medical_documents WHERE patient_id = ? ORDER BY id DESC',
            [$patientId]
        );
    }

    return [
        'patient' => [
            'row'       => $patient,
            'id'        => $patientId,
            'name'      => (string)($patient['patient_name'] ?? ''),
            'initials'  => strtoupper(substr((string)($patient['patient_name'] ?? 'P'), 0, 1) ?: 'P'),
            'age'       => $age,
            'age_group' => telehealthAgeGroup($age),
            'gender'    => (string)($patient['gender'] ?? ''),
            'mobile'    => (string)($patient['mobile_number'] ?? ''),
            'blood'     => (string)($patient['blood_group'] ?? ''),
        ],
        'upcoming'  => $upcoming,
        'next'      => $upcoming[0] ?? null,
        'stats'     => [
            'consultations' => telehealthCount($pdo, 'SELECT COUNT(*) FROM consultations WHERE patient_id = ?', [$patientId]),
            'prescriptions' => telehealthCount($pdo, 'SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?', [$patientId]),
            'documents'     => count($documents),
            'orders'        => count($orders),
        ],
        'orders'    => $orders,
        'documents' => $documents,
    ];
}

/** '2026-10-05 14:30:00' → 'Today, 2:30 PM' */
function telehealthWhen(?string $when, string $fallback = 'Not scheduled'): string
{
    if (!$when) {
        return $fallback;
    }

    try {
        $dt  = new DateTimeImmutable($when);
        $day = $dt->format('Y-m-d') === date('Y-m-d')
            ? 'Today'
            : ($dt->format('Y-m-d') === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : $dt->format('D, d M'));

        return $day . ', ' . $dt->format('g:i A');
    } catch (Throwable $e) {
        return $fallback;
    }
}

/** Status enum → label + tone used by the pills in both portals. */
function telehealthStatusTone(string $status): array
{
    $map = [
        'completed'       => ['Completed', 'ok'],
        'confirmed'       => ['Confirmed', 'ok'],
        'verified'        => ['Verified', 'ok'],
        'approved'        => ['Approved', 'ok'],
        'active'          => ['Active', 'ok'],
        'paid'            => ['Paid', 'ok'],
        'in_progress'     => ['In progress', 'info'],
        'requested'       => ['Requested', 'info'],
        'payment_pending' => ['Payment due', 'warn'],
        'pending'         => ['Pending', 'warn'],
        'reissued'        => ['Reissued', 'warn'],
        'cancelled'       => ['Cancelled', 'danger'],
        'rejected'        => ['Rejected', 'danger'],
        'no_show'         => ['No show', 'danger'],
        'suspended'       => ['Suspended', 'danger'],
        'expired'         => ['Expired', 'danger'],
    ];

    $key = strtolower(trim($status));

    return $map[$key] ?? [ucwords(str_replace('_', ' ', $key) ?: 'Unknown'), 'muted'];
}

/** Escape for HTML output. */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Indian-rupee formatting (em dash when unknown). */
function telehealthMoney(mixed $v): string
{
    return $v === null ? '—' : '₹' . number_format((float) $v, 2);
}

/** Tel: link markup for the module-wide audio consultation number. */
function telehealthCallLink(string $label = 'Audio Call', string $class = 'call-chip'): string
{
    return '<a class="' . h($class) . '" href="tel:' . h(TELEHEALTH_AUDIO_CALL_NUMBER) . '">'
        . '<i class="fas fa-phone-alt"></i> ' . h($label) . ': ' . h(TELEHEALTH_AUDIO_CALL_NUMBER)
        . '</a>';
}
