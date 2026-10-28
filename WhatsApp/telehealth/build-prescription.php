<?php
/**
 * build-prescription.php
 * ---------------------------------------------------------------------
 * Prescription builder for the Doctor Assist (telehealth) module.
 *
 *  • Patients, doctors and prescriptions come from the Doctor Assist database.
 *  • Medicine search is powered by the tablet.php catalogue
 *    (ksr_guru_ji.medicine_catalogue — 3,689 medicines).
 *  • ?rx=<id> renders the printable prescription document.
 *
 * Author:  Dr. D C Rathod · 3 yrs · Neuro Care, Cardiology · audio 6362079192
 */
require_once 'helper.php';

$pdo           = getAssistDB();
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

$doctor = telehealthDoctorProfile(
    $pdo,
    isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : null,
    $currentUserId
);

$patients       = telehealthPatients($pdo);
$recent         = telehealthPrescriptions($pdo, 0, 'all', 8);
$stats          = telehealthStats($pdo, 0);
$catalogueCount = telehealthCatalogueCount();
$catalogueReady = telehealthCatalogueReady();
$catalogueDb    = telehealthCatalogueDbName() . '.medicine_catalogue';

$rx           = null;
$rxItems      = [];
$error        = null;
$notice       = null;
$prefillFrom  = '';
$prefillNote  = null;

/* ---------------------------------------------------------------------
   SAVE  (server-side → works with and without JavaScript)
   --------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $medicines = [];

    foreach ((array) ($_POST['med_name'] ?? []) as $i => $name) {
        $medicines[] = [
            'name'         => $name,
            'strength'     => $_POST['med_strength'][$i] ?? '',
            'dosage_form'  => $_POST['med_form'][$i] ?? '',
            'frequency'    => $_POST['med_freq'][$i] ?? '',
            'duration'     => $_POST['med_dur'][$i] ?? '',
            'instructions' => $_POST['med_note'][$i] ?? '',
        ];
    }

    try {
        $result = telehealthSavePrescription($pdo, [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'doctor_id'  => (int) ($_POST['doctor_id'] ?? $doctor['id']),
            'diagnosis'  => $_POST['diagnosis'] ?? '',
            'symptoms'   => $_POST['symptoms'] ?? '',
            'notes'      => $_POST['notes'] ?? '',
            'follow_up'  => $_POST['follow_up'] ?? '',
            'medicines'  => $medicines,
        ]);

        header('Location: build-prescription.php?rx=' . $result['prescription_id'] . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* ---------------------------------------------------------------------
   PRINT / VIEW an existing prescription
   --------------------------------------------------------------------- */
$rxId = isset($_GET['rx']) ? (int) $_GET['rx'] : 0;

if ($rxId > 0) {
    $rx = telehealthPrescription($pdo, $rxId);

    if ($rx) {
        $rxItems = telehealthPrescriptionItems($pdo, $rxId);
    } else {
        $error = 'Prescription ' . telehealthRxCode($rxId) . ' could not be found.';
    }
}

if (isset($_GET['saved'])) {
    $notice = 'Prescription saved and queued for pharmacist verification.';
}

$rxDoctor = $rx ? telehealthDoctorProfile($pdo, (int) ($rx['doctor_id'] ?? 0) ?: null) : $doctor;

/* ---------------------------------------------------------------------
   MEDICINE ROWS  —  survive a validation error, and support the
   tablet.php deep link  build-prescription.php?med=<medicine name>
   --------------------------------------------------------------------- */
$presetRows = [];

foreach ((array) ($_POST['med_name'] ?? []) as $i => $name) {
    $presetRows[] = [
        'name'     => (string) $name,
        'strength' => (string) ($_POST['med_strength'][$i] ?? ''),
        'form'     => (string) ($_POST['med_form'][$i] ?? 'Tablet'),
        'freq'     => (string) ($_POST['med_freq'][$i] ?? ''),
        'dur'      => (string) ($_POST['med_dur'][$i] ?? ''),
        'note'     => (string) ($_POST['med_note'][$i] ?? ''),
    ];
}

$requestedMed = trim((string) ($_GET['med'] ?? ''));

if (!$presetRows && $requestedMed !== '' && $catalogueReady) {
    $hit = null;

    foreach (telehealthSearchMedicines($requestedMed, 1) as $candidate) {
        if (mb_strtolower((string) $candidate['medicine_name']) === mb_strtolower($requestedMed)) {
            $hit = $candidate;
            break;
        }
    }

    if (!$hit) {
        $matches = telehealthSearchMedicines($requestedMed, 1);
        $hit     = $matches[0] ?? null;
    }

    if ($hit) {
        $presetRows[] = [
            'name'     => (string) $hit['medicine_name'],
            'strength' => (string) ($hit['strength'] ?? ''),
            'form'     => (string) ($hit['dosage_form'] ?: 'Tablet'),
            'freq'     => '1-0-1',
            'dur'      => '5 Days',
            'note'     => 'After food · complete the full course',
        ];

        $prefillFrom = (string) $hit['medicine_name'];
        $prefillNote = 'Prefilled from the tablet.php catalogue: ' . $prefillFrom
            . ($hit['composition'] ? ' (' . $hit['composition'] . ')' : '')
            . ' — adjust the dosage and add more medicines below.';
    } else {
        $presetRows[] = [
            'name' => $requestedMed, 'strength' => '', 'form' => 'Tablet',
            'freq' => '1-0-1', 'dur' => '5 Days', 'note' => '',
        ];

        $prefillFrom = $requestedMed;
        $prefillNote = '"' . $requestedMed . '" was not found in the tablet.php catalogue — '
            . 'the name was added manually, please double-check it.';
    }
}

if (!$presetRows) {
    $presetRows[] = ['name' => '', 'strength' => '', 'form' => 'Tablet', 'freq' => '', 'dur' => '', 'note' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../../favicon.ico">
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../../favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../../icon-192.png">
    <link rel="apple-touch-icon" href="../../icon-192.png">
    <title><?= $rx ? h($rx['rx_code']) . ' | Prescription' : 'Prescription Builder' ?> | Doctor Assist</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0, 0, 0, .1);
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
        }

        body { background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); font-family: 'Inter', sans-serif; padding-bottom: 60px; }

        /* ---------- doctor navbar ---------- */
        .doc-nav { background: #0F172A; }
        .doc-nav .navbar-brand { color: #fff; }
        .doc-nav .navbar-links a { color: #94A3B8; font-weight: 700; }
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: #fff; }
        .call-chip { display: inline-flex; align-items: center; gap: 8px; background: #16A34A; color: #fff !important; padding: 8px 16px; border-radius: 50px; font-weight: 800; font-size: .85rem; text-decoration: none; box-shadow: 0 6px 16px rgba(22,163,74,.35); }
        .call-chip:hover { background: #15803D; transform: translateY(-1px); }
        .call-chip.plain { background: #1E293B; color: #E2E8F0 !important; box-shadow: none; }

        .wrap { max-width: 1400px; margin: 32px auto; padding: 0 20px; }

        /* ---------- header ---------- */
        .page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-head h1 { font-size: 1.7rem; font-weight: 800; color: var(--clr-primary-dark); letter-spacing: -.4px; }
        .page-head p { color: var(--clr-text-secondary); font-weight: 500; margin-top: 6px; }

        .c-box { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); border-radius: 20px; box-shadow: var(--shadow-glass); padding: 24px; }
        .c-box + .c-box { margin-top: 20px; }
        .cb-head { font-weight: 800; color: var(--clr-primary-dark); text-transform: uppercase; letter-spacing: .5px; font-size: .95rem; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px dashed rgba(0,0,0,.08); padding-bottom: 14px; }

        .grid-2 { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }

        /* ---------- form ---------- */
        label.fld { display: block; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--clr-text-secondary); margin-bottom: 6px; }
        .fld-input { width: 100%; background: rgba(248,250,252,.9); border: 1px solid var(--clr-border); border-radius: 10px; padding: 11px 13px; font-family: inherit; font-size: .92rem; font-weight: 600; color: var(--clr-text-primary); outline: none; transition: .2s; }
        .fld-input:focus { background: #fff; border-color: var(--clr-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        textarea.fld-input { resize: vertical; min-height: 74px; font-weight: 500; }
        .row-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
        .row-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }

        /* ---------- medicine rows ---------- */
        .med-row { background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 16px; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
        .med-top { display: grid; grid-template-columns: minmax(0, 2.2fr) 1fr 1.1fr 1fr 1fr 42px; gap: 10px; align-items: end; }
        .med-input { width: 100%; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 9px 11px; font-family: inherit; font-size: .9rem; font-weight: 600; color: var(--clr-text-primary); outline: none; background: #F8FAFC; }
        .med-input:focus { background: #fff; border: 1px solid var(--clr-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .med-input.small { font-weight: 500; font-size: .85rem; }
        .med-mini { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--clr-text-secondary); display: block; margin-bottom: 4px; }
        .med-del { border: none; background: #FEF2F2; color: #DC2626; border-radius: 8px; height: 38px; cursor: pointer; font-size: 1rem; }
        .med-del:hover { background: #FEE2E2; }
        .med-pos { position: relative; }
        .med-suggest { position: absolute; z-index: 40; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--clr-border); border-radius: 10px; box-shadow: 0 18px 40px rgba(15,23,42,.16); max-height: 260px; overflow-y: auto; display: none; }
        .med-suggest.open { display: block; }
        .med-opt { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(0,0,0,.04); }
        .med-opt:last-child { border-bottom: none; }
        .med-opt:hover { background: var(--clr-primary-light); }
        .med-opt b { display: block; color: var(--clr-text-primary); font-size: .92rem; }
        .med-opt span { color: var(--clr-text-secondary); font-size: .78rem; }

        /* ---------- buttons ---------- */
        .btn { border: 1px solid var(--clr-border); background: #fff; color: var(--clr-text-primary); padding: 11px 18px; border-radius: 10px; font-weight: 800; font-size: .9rem; cursor: pointer; transition: .22s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,.1); color: var(--clr-primary); }
        .btn-primary { background: var(--gradient-primary); border-color: transparent; color: #fff; box-shadow: 0 8px 20px rgba(37,99,235,.3); }
        .btn-primary:hover { color: #fff; box-shadow: 0 12px 26px rgba(37,99,235,.4); }
        .btn-block { width: 100%; justify-content: center; }
        .btn-row { display: flex; gap: 12px; flex-wrap: wrap; justify-content: flex-end; margin-top: 20px; }

        /* ---------- alerts ---------- */
        .alert { border-radius: 12px; padding: 14px 18px; font-weight: 700; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start; }
        .alert-err { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .alert-ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .alert-info { background: #EFF6FF; color: #1E3A8A; border: 1px solid #BFDBFE; }

        /* ---------- side cards ---------- */
        .mini-list { display: flex; flex-direction: column; gap: 10px; }
        .mini-item { border: 1px solid rgba(0,0,0,.06); border-radius: 12px; padding: 12px 14px; background: #fff; text-decoration: none; color: inherit; display: block; transition: .2s; }
        .mini-item:hover { border-color: var(--clr-primary-light); box-shadow: 0 8px 20px rgba(37,99,235,.08); transform: translateY(-1px); }
        .mini-item .mi-top { display: flex; justify-content: space-between; gap: 10px; font-weight: 800; font-size: .92rem; }
        .mini-item .mi-sub { color: var(--clr-text-secondary); font-size: .8rem; margin-top: 3px; }
        .chip { display: inline-flex; align-items: center; gap: 6px; background: #F1F5F9; color: var(--clr-text-secondary); border-radius: 50px; padding: 5px 12px; font-size: .72rem; font-weight: 800; text-transform: uppercase; }
        .chip.green { background: #F0FDF4; color: #16A34A; }
        .chip.amber { background: #FEF9C3; color: #CA8A04; }
        .chip.blue { background: #EFF6FF; color: #2563EB; }
        .chip.red { background: #FEF2F2; color: #DC2626; }
        .doc-strip { display: flex; gap: 14px; align-items: center; }
        .doc-strip .av { width: 54px; height: 54px; border-radius: 16px; background: var(--clr-primary-light); color: var(--clr-primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .stat-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-box { background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 14px 16px; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
        .stat-box .sb-val { font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); line-height: 1; }
        .stat-box .sb-lab { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--clr-text-secondary); margin-top: 6px; }

        /* ---------- print view ---------- */
        .rx-paper { background: #fff; border-radius: 20px; box-shadow: 0 20px 50px rgba(15,23,42,.12); padding: 34px; max-width: 920px; margin: 0 auto; }
        .rx-paper-head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 3px solid var(--clr-primary); padding-bottom: 18px; flex-wrap: wrap; }
        .rx-paper-head h2 { font-size: 1.5rem; color: var(--clr-primary-dark); }
        .rx-paper-head .clinic { color: var(--clr-text-secondary); font-size: .88rem; font-weight: 600; margin-top: 4px; }
        .rx-sheet-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 24px; margin: 22px 0; font-size: .92rem; }
        .rx-sheet-grid .k { color: var(--clr-text-secondary); font-weight: 800; text-transform: uppercase; font-size: .72rem; letter-spacing: .4px; }
        .rx-sheet-grid .v { font-weight: 700; }
        table.rx-items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.rx-items th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; color: var(--clr-text-secondary); border-bottom: 2px solid var(--clr-border); padding: 10px 8px; }
        table.rx-items td { padding: 12px 8px; border-bottom: 1px solid var(--clr-border); font-size: .92rem; vertical-align: top; }
        .rx-sign { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-top: 30px; flex-wrap: wrap; }
        .rx-sign .line { border-top: 1px solid var(--clr-text-secondary); padding-top: 6px; font-size: .78rem; font-weight: 700; color: var(--clr-text-secondary); min-width: 200px; text-align: center; }
        .rx-note { background: #F8FAFC; border-left: 4px solid var(--clr-primary); border-radius: 10px; padding: 14px 16px; font-size: .9rem; color: var(--clr-text-primary); margin-top: 18px; }

        @media (max-width: 1080px) {
            .grid-2 { grid-template-columns: minmax(0, 1fr); }
            .med-top { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .row-3, .row-2 { grid-template-columns: minmax(0, 1fr); }
            .rx-sheet-grid { grid-template-columns: minmax(0, 1fr); }
            .stat-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 768px) {
            .wrap { padding: 0 14px; margin: 20px auto; }
            .c-box { padding: 18px 14px; border-radius: 14px; }
            .med-top { grid-template-columns: 1fr 1fr; }
            .rx-paper { padding: 20px 16px; border-radius: 14px; }
            .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
            .btn-row { flex-direction: column; }
            .btn-row .btn { width: 100%; justify-content: center; }
            .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table.rx-items { min-width: 500px; }
        }

        @media (max-width: 480px) {
            .wrap { padding: 0 10px; margin: 14px auto; }
            .page-head h1 { font-size: 1.35rem; }
            .stat-strip { grid-template-columns: 1fr; gap: 8px; }
            .med-top { grid-template-columns: 1fr; gap: 8px; }
            .med-del { width: 100%; height: 36px; }
            .rx-sign { flex-direction: column; align-items: center; }
            .rx-sign .line { width: 100%; }
        }

        @media (max-width: 360px) {
            .page-head h1 { font-size: 1.2rem; }
            .stat-box .sb-val { font-size: 1.3rem; }
        }

        @media print {
            body { background: #fff; }
            .doc-nav, .no-print, .page-head, .c-box.side { display: none !important; }
            .rx-paper { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
            .wrap { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0;
include __DIR__ . '/partials/brandbar.php';
$navActive = 'new-rx';
include __DIR__ . '/partials/doctor-nav.php'; ?>






<!-- ============================ NAV ============================ -->


<div class="wrap">

    <div class="page-head">
        <div>
            <h1>
                <i class="fas fa-file-prescription"></i>
                <?= $rx ? 'Prescription ' . h($rx['rx_code']) : 'Prescription Builder' ?>
            </h1>
            <p>
                <?= $rx
                    ? 'Printable prescription issued on ' . h(date('d M Y', strtotime((string) $rx['prescription_date'])))
                    : 'Search the pharmacy catalogue, configure the dosage and issue a verified digital prescription.' ?>
            </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="medicine/tablet.php"><i class="fas fa-tablets"></i> Open tablet.php catalogue</a>
            <?php if ($rx): ?>
                <a class="btn" href="build-prescription.php"><i class="fas fa-plus"></i> New prescription</a>
            <?php else: ?>
                <a class="btn" href="doctor-prescriptions.php"><i class="fas fa-list"></i> All prescriptions</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-err"><i class="fas fa-exclamation-triangle"></i><span><?= h($error) ?></span></div>
    <?php endif; ?>

    <?php if ($notice !== null): ?>
        <div class="alert alert-ok"><i class="fas fa-check-circle"></i><span><?= h($notice) ?></span></div>
    <?php endif; ?>

    <?php if (!$rx && $prefillNote !== null): ?>
        <div class="alert alert-info">
            <i class="fas fa-capsules"></i>
            <span><?= h($prefillNote) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$catalogueReady): ?>
        <div class="alert alert-info">
            <i class="fas fa-database"></i>
            <span>
                Catalogue <strong><?= h($catalogueDb) ?></strong> is not reachable yet — run
                <a href="seed-telehealth-data.php">seed-telehealth-data.php</a> to import the 3,689-record
                master used by <a href="medicine/tablet.php">tablet.php</a>.
            </span>
        </div>
    <?php endif; ?>

<?php if (!$rx): /* ============================================================
   BUILDER VIEW
   ============================================================ */ ?>

    <div class="stat-strip">
        <div class="stat-box"><div class="sb-val"><?= (int) $stats['total'] ?></div><div class="sb-lab">Prescriptions</div></div>
        <div class="stat-box"><div class="sb-val"><?= (int) $stats['active'] ?></div><div class="sb-lab">Active</div></div>
        <div class="stat-box"><div class="sb-val"><?= (int) $stats['pending'] ?></div><div class="sb-lab">Awaiting pharmacist</div></div>
        <div class="stat-box"><div class="sb-val"><?= (int) $stats['medicines'] ?></div><div class="sb-lab">Medicines prescribed</div></div>
    </div>

    <div class="grid-2">
        <div>
            <form method="post" action="build-prescription.php" id="rxForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="doctor_id" value="<?= (int) $doctor['id'] ?>">

                <div class="c-box">
                    <div class="cb-head">
                        <span><i class="fas fa-user-injured"></i> Consultation context</span>
                        <span class="chip blue"><?= count($patients) ?> patients on file</span>
                    </div>

                    <div class="row-2">
                        <div>
                            <label class="fld" for="patient_id">Patient *</label>
                            <select class="fld-input" name="patient_id" id="patient_id" required>
                                <option value="">— select patient —</option>
                                <?php foreach ($patients as $p): ?>
                                    <?php
                                    $pAge    = $p['age'] !== null ? $p['age'] . ' yrs' : 'age n/a';
                                    $pGender = $p['gender'] ?: 'n/a';
                                    ?>
                                    <option value="<?= (int) $p['id'] ?>"
                                        <?= ((int) ($_POST['patient_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
                                        <?= h($p['patient_name']) ?> · <?= h($pAge) ?> · <?= h($pGender) ?><?= $p['mobile_number'] ? ' · ' . h($p['mobile_number']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="fld" for="diagnosis">Primary diagnosis *</label>
                            <input class="fld-input" type="text" name="diagnosis" id="diagnosis"
                                   placeholder="e.g. Viral upper respiratory infection"
                                   value="<?= h($_POST['diagnosis'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row-3">
                        <div>
                            <label class="fld" for="symptoms">Symptoms / chief complaint</label>
                            <input class="fld-input" type="text" name="symptoms" id="symptoms"
                                   placeholder="Fever, dry cough, fatigue"
                                   value="<?= h($_POST['symptoms'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="fld" for="notes">Clinical assessment</label>
                            <input class="fld-input" type="text" name="notes" id="notes"
                                   placeholder="Lungs clear to auscultation"
                                   value="<?= h($_POST['notes'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="fld" for="follow_up">Follow-up advice</label>
                            <input class="fld-input" type="text" name="follow_up" id="follow_up"
                                   placeholder="Review after 5 days"
                                   value="<?= h($_POST['follow_up'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="c-box">
                    <div class="cb-head">
                        <span><i class="fas fa-prescription-bottle-alt"></i> Medicines</span>
                        <span class="chip green">
                            <i class="fas fa-capsules"></i> <?= number_format($catalogueCount) ?> catalogue medicines
                        </span>
                    </div>

                    <div id="medRows"></div>

                    <button type="button" class="btn" onclick="addRow()">
                        <i class="fas fa-plus"></i> Add medicine
                    </button>

                    <div class="btn-row">
                        <a class="btn" href="doctor-prescriptions.php">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Save &amp; issue prescription
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ===================== SIDE COLUMN ===================== -->
        <div>
            <div class="c-box side">
                <div class="cb-head"><span><i class="fas fa-user-md"></i> Authorising doctor</span></div>
                <div class="doc-strip">
                    <div class="av"><i class="fas fa-user-md"></i></div>
                    <div>
                        <div style="font-weight:800; color:var(--clr-primary-dark);"><?= h($doctor['name']) ?></div>
                        <div style="color:var(--clr-text-secondary); font-weight:600; font-size:.88rem;"><?= h($doctor['specialty']) ?></div>
                    </div>
                </div>
                <div style="margin-top:14px; display:flex; flex-wrap:wrap; gap:8px;">
                    <span class="chip green"><?= (int) $doctor['experience_years'] ?>+ yrs experience</span>
                    <span class="chip blue"><?= h($doctor['verification_status']) ?></span>
                    <?php if ($doctor['registration_number'] !== ''): ?>
                        <span class="chip">Reg <?= h($doctor['registration_number']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:14px; font-size:.88rem; color:var(--clr-text-secondary);">
                    <?= h($doctor['qualification']) ?>
                </div>
                <div style="margin-top:16px;">
                    <a class="btn btn-primary btn-block" href="tel:<?= h($doctor['audio_call']) ?>">
                        <i class="fas fa-phone-alt"></i> Audio call <?= h($doctor['audio_call']) ?>
                    </a>
                </div>
            </div>

            <div class="c-box side">
                <div class="cb-head"><span><i class="fas fa-capsules"></i> Pharmacy catalogue</span></div>
                <p style="color:var(--clr-text-secondary); font-size:.9rem; margin-bottom:12px;">
                    Medicine search is powered by <strong>tablet.php</strong> —
                    <?= number_format($catalogueCount) ?> active medicines.
                </p>
                <a class="btn btn-block" href="medicine/tablet.php"><i class="fas fa-store"></i> Browse tablet.php</a>
                <div style="margin-top:10px; font-size:.78rem; color:var(--clr-text-secondary); font-family:monospace;">
                    <?= h($catalogueDb) ?>
                </div>
            </div>

            <div class="c-box side">
                <div class="cb-head"><span><i class="fas fa-history"></i> Recent prescriptions</span></div>
                <?php if (!$recent): ?>
                    <p style="color:var(--clr-text-secondary); font-size:.9rem;">No prescriptions issued yet.</p>
                <?php else: ?>
                    <div class="mini-list">
                        <?php foreach ($recent as $r): ?>
                            <a class="mini-item" href="build-prescription.php?rx=<?= (int) $r['id'] ?>">
                                <div class="mi-top">
                                    <span><?= h($r['rx_code']) ?></span>
                                    <span><?= (int) $r['item_count'] ?> med</span>
                                </div>
                                <div class="mi-sub">
                                    <?= h($r['patient_name'] ?: 'Patient #' . $r['patient_id']) ?>
                                    · <?= h($r['diagnosis'] ?: 'No diagnosis') ?>
                                </div>
                                <div class="mi-sub">
                                    <?= h(date('d M Y', strtotime((string) $r['prescription_date']))) ?>
                                    <?php if ($r['is_expired']): ?>
                                        <span class="chip red" style="margin-left:6px;">Expired</span>
                                    <?php else: ?>
                                        <span class="chip green" style="margin-left:6px;"><?= h($r['verification_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /grid-2 -->

<?php else: /* ============================================================
   PRINTABLE PRESCRIPTION
   ============================================================ */ ?>

    <div class="rx-paper">
        <div class="rx-paper-head">
            <div>
                <h2><i class="fas fa-heartbeat"></i> KSR GURU JI · Doctor Assist</h2>
                <div class="clinic">Telehealth · Pharmacy · Diagnostics network</div>
                <div class="clinic" style="margin-top:8px;">
                    <strong><?= h($rxDoctor['name']) ?></strong> · <?= h($rxDoctor['specialty']) ?>
                    · <?= (int) $rxDoctor['experience_years'] ?> yrs experience<?= ($rxDoctor['registration_number'] ?? '') !== '' ? ' · Reg ' . h($rxDoctor['registration_number']) : '' ?>
                </div>
                <div class="clinic">
                    <i class="fas fa-phone-alt"></i> Audio consultation:
                    <strong><?= h($rxDoctor['audio_call']) ?></strong>
                </div>
            </div>
            <div style="text-align:right;">
                <div class="chip <?= $rx['is_expired'] ? 'red' : 'green' ?>">
                    <?= $rx['is_expired'] ? 'Expired' : 'Active' ?>
                </div>
                <div style="font-family:monospace; font-weight:800; color:var(--clr-primary); font-size:1.15rem; margin-top:8px;">
                    <?= h($rx['rx_code']) ?>
                </div>
                <div style="color:var(--clr-text-secondary); font-size:.82rem; font-weight:700;">
                    <?= h(date('d M Y', strtotime((string) $rx['prescription_date']))) ?>
                </div>
                <div style="color:var(--clr-text-secondary); font-size:.82rem;">
                    Pharmacist: <?= h($rx['verification_status']) ?>
                </div>
            </div>
        </div>

        <div class="rx-sheet-grid">
            <div><div class="k">Patient</div><div class="v"><?= h($rx['patient_name'] ?: '—') ?></div></div>
            <div><div class="k">Age / Sex</div><div class="v"><?= h($rx['age'] !== null ? $rx['age'] . ' yrs' : '—') ?> / <?= h($rx['gender'] ?: '—') ?></div></div>
            <div><div class="k">Mobile</div><div class="v"><?= h($rx['mobile_number'] ?: '—') ?></div></div>
            <div><div class="k">Consultation date</div><div class="v"><?= h(date('d M Y, H:i', strtotime((string) ($rx['consultation_date'] ?: $rx['prescription_date'])))) ?></div></div>
            <div><div class="k">Diagnosis</div><div class="v"><?= h($rx['diagnosis'] ?: '—') ?></div></div>
            <div><div class="k">Symptoms</div><div class="v"><?= h($rx['symptoms'] ?: '—') ?></div></div>
            <div><div class="k">Known allergies</div><div class="v" style="color:#B91C1C;"><?= h($rx['allergies'] ?: 'None recorded') ?></div></div>
            <div><div class="k">Validity</div><div class="v"><?= (int) TELEHEALTH_RX_VALIDITY_MONTHS ?> months from issue</div></div>
        </div>

        <div style="font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--clr-primary-dark);">
            <i class="fas fa-prescription-bottle-alt"></i> Rx — <?= count($rxItems) ?> medicine(s)
        </div>

        <table class="rx-items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Instructions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rxItems as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= h($item['medicine_name']) ?></strong>
                            <div style="color:var(--clr-text-secondary); font-size:.8rem;">
                                <?= h($item['strength']) ?> · <?= h($item['dosage_form']) ?>
                            </div>
                        </td>
                        <td><?= h($item['frequency']) ?></td>
                        <td><?= h($item['duration']) ?></td>
                        <td><?= h($item['instructions']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($rx['doctor_assessment'])): ?>
            <div class="rx-note">
                <strong>Clinical assessment / advice:</strong> <?= h($rx['doctor_assessment']) ?>
            </div>
        <?php endif; ?>

        <div class="rx-note" style="border-left-color:#16A34A;">
            <strong>Pharmacy note:</strong> dispense strictly against this prescription.
            <a href="medicine/tablet.php">Verify or order in the tablet.php catalogue</a>
            (<?= number_format($catalogueCount) ?> medicines) · audio help
            <?= h($rxDoctor['audio_call']) ?>.
        </div>

        <div class="rx-sign">
            <div>
                <div class="chip <?= $rx['verification_status'] === 'APPROVED' ? 'green' : 'amber' ?>">
                    <?= $rx['verification_status'] === 'APPROVED' ? 'Pharmacist verified' : 'Awaiting pharmacist verification' ?>
                </div>
                <div style="font-size:.78rem; color:var(--clr-text-secondary); margin-top:8px;">
                    Electronically generated by Doctor Assist · <?= h($rx['rx_code']) ?>
                </div>
            </div>
            <div>
                <div class="line">
                    <?= h($rxDoctor['name']) ?><br>
                    <?= h($rxDoctor['qualification']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="btn-row no-print" style="max-width:920px; margin:20px auto 0; justify-content:center;">
        <a class="btn" href="doctor-prescriptions.php"><i class="fas fa-list"></i> All prescriptions</a>
        <a class="btn" href="medicine/tablet.php"><i class="fas fa-store"></i> tablet.php pharmacy</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
    </div>

<?php endif; ?>

</div><!-- /wrap -->

<script>
    /* =====================================================================
       MEDICINE ROWS  (catalogue-powered autocomplete over api_medicines.php)
       ===================================================================== */
    const rowsHost = document.getElementById('medRows');
    const FORMS = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Drops', 'Cream', 'Ointment', 'Inhaler', 'Powder'];

    function escapeAttr(v) {
        return String(v === undefined || v === null ? '' : v)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeHtml(v) { return escapeAttr(v); }

    function rowMarkup(p) {
        p = p || {};
        return '<div class="med-row">' +
            '<div class="med-top">' +
                '<div><span class="med-mini">Medicine (pharmacy catalogue)</span>' +
                    '<div class="med-pos">' +
                        '<input class="med-input" name="med_name[]" autocomplete="off" placeholder="Type at least 2 letters\u2026" ' +
                            'oninput="searchMedicine(this)" value="' + escapeAttr(p.name) + '">' +
                        '<div class="med-suggest"></div>' +
                    '</div>' +
                '</div>' +
                '<div><span class="med-mini">Strength</span>' +
                    '<input class="med-input" name="med_strength[]" placeholder="500mg" value="' + escapeAttr(p.strength) + '"></div>' +
                '<div><span class="med-mini">Form</span>' +
                    '<select class="med-input" name="med_form[]">' +
                        FORMS.map(function (f) {
                            return '<option' + (p.form === f ? ' selected' : '') + '>' + f + '</option>';
                        }).join('') +
                    '</select></div>' +
                '<div><span class="med-mini">Frequency</span>' +
                    '<input class="med-input" name="med_freq[]" placeholder="1-0-1" value="' + escapeAttr(p.freq) + '"></div>' +
                '<div><span class="med-mini">Duration</span>' +
                    '<input class="med-input" name="med_dur[]" placeholder="5 Days" value="' + escapeAttr(p.dur) + '"></div>' +
                '<div><span class="med-mini">&nbsp;</span>' +
                    '<button type="button" class="med-del" title="Remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></div>' +
            '</div>' +
            '<div style="margin-top:10px;"><span class="med-mini">Instructions</span>' +
                '<input class="med-input small" name="med_note[]" placeholder="After food \u00b7 plenty of fluids" value="' + escapeAttr(p.note) + '"></div>' +
        '</div>';
    }

    function addRow(p) {
        if (!rowsHost) { return; }
        rowsHost.insertAdjacentHTML('beforeend', rowMarkup(p || {}));
    }

    function removeRow(btn) {
        if (!rowsHost) { return; }

        const rows = rowsHost.querySelectorAll('.med-row');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('input').forEach(function (i) { i.value = ''; });
            return;
        }
        btn.closest('.med-row').remove();
    }

    /* ---------------- tablet.php catalogue autocomplete ---------------- */
    const searchTimers = new WeakMap();

    function searchMedicine(input) {
        const box = input.parentElement.querySelector('.med-suggest');
        const q   = input.value.trim();

        clearTimeout(searchTimers.get(input));

        if (q.length < 2) {
            box.classList.remove('open');
            box.innerHTML = '';
            return;
        }

        searchTimers.set(input, setTimeout(function () {
            fetch('api_medicines.php?limit=8&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok || !data.items || !data.items.length) {
                        box.innerHTML = '<div class="med-opt"><b>No match in the tablet.php catalogue</b>' +
                            '<span>You can still type the medicine name manually.</span></div>';
                        box.classList.add('open');
                        return;
                    }

                    box.innerHTML = data.items.map(function (item) {
                        const sub = [
                            item.dosage_form || '',
                            item.composition ? item.composition.substring(0, 70) : '',
                            item.stock_label || ''
                        ].filter(Boolean).join(' \u00b7 ');

                        return '<div class="med-opt" data-name="' + escapeAttr(item.medicine_name) + '" ' +
                            'data-strength="' + escapeAttr(item.strength) + '" ' +
                            'data-form="' + escapeAttr(item.dosage_form) + '" onclick="pickMedicine(this)">' +
                            '<b>' + escapeHtml(item.label) + '</b>' +
                            '<span>' + escapeHtml(sub) + ' \u00b7 ' + escapeHtml(item.rx_label) + '</span></div>';
                    }).join('');

                    box.classList.add('open');
                })
                .catch(function () { box.classList.remove('open'); });
        }, 250));
    }

    function pickMedicine(el) {
        const row = el.closest('.med-row');

        row.querySelector('input[name="med_name[]"]').value     = el.dataset.name || '';
        row.querySelector('input[name="med_strength[]"]').value = el.dataset.strength || '';

        const formSel = row.querySelector('select[name="med_form[]"]');
        Array.prototype.forEach.call(formSel.options, function (o) {
            if (o.value.toLowerCase() === (el.dataset.form || '').toLowerCase()) { formSel.value = o.value; }
        });

        const note = row.querySelector('input[name="med_note[]"]');
        if (!note.value) { note.value = 'After food \u00b7 complete the full course'; }

        row.querySelector('.med-suggest').classList.remove('open');
        row.querySelector('input[name="med_freq[]"]').focus();
    }

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.med-suggest.open').forEach(function (box) {
            if (!box.parentElement.contains(e.target)) { box.classList.remove('open'); }
        });
    });

    /* ---------------- initial rows (values survive a validation error) ---------- */
<?php if (!$rx): ?>
    <?php foreach ($presetRows as $presetRow): ?>
        addRow(<?= json_encode($presetRow, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    <?php endforeach; ?>
<?php endif; ?>
</script>

</body>
</html>
