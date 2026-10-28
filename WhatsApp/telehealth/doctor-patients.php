<?php
require_once 'helper.php';

$pdo = getAssistDB();

$doctorId = telehealthResolveDoctorId($pdo, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
$doc      = telehealthDoctorProfile($pdo, $doctorId);

// ── Live patient list for this doctor ────────────────────────────
$query    = trim((string)($_GET['q'] ?? ''));
$needle   = mb_strtolower($query);
$patients = telehealthDoctorPatients($pdo, $doctorId);

if ($query !== '') {
    /** @var string $needle */
    /** @var string $query */
    $patients = array_values(array_filter($patients, static function (array $p) use ($needle, $query): bool {
        return strpos(mb_strtolower((string)($p['patient_name'] ?? '')), $needle) !== false
            || strpos((string)($p['mobile_number'] ?? ''), $query) !== false
            || (string)($p['id'] ?? '') === $query;
    }));
}

// ── Selected patient full record ─────────────────────────────────
$selectedId = (int)($_GET['patient'] ?? 0);
$record     = $selectedId > 0 ? telehealthPatientRecord($pdo, $selectedId) : [];

// Default to the most recently active patient
if (!$record && $patients) {
    $record = telehealthPatientRecord($pdo, (int)$patients[0]['id']);
}

/** Icon per consultation type. */
function dp_consult_icon(string $type): string
{
    return [
        'video'     => 'fa-video',
        'audio'     => 'fa-phone-alt',
        'chat'      => 'fa-comment-dots',
        'in_person' => 'fa-user-md',
    ][$type] ?? 'fa-stethoscope';
}

/** Human label for a consultation type. */
function dp_consult_label(string $type): string
{
    return [
        'video'     => 'Video Consultation',
        'audio'     => 'Audio Consultation',
        'chat'      => 'Secure Chat',
        'in_person' => 'In-Person Visit',
    ][$type] ?? 'Consultation';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records | KSR GURU JI Doctor Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <style>
        body {
            background: var(--clr-bg-main);
            font-family: 'Inter', system-ui, sans-serif;
            margin: 0;
            padding-bottom: 40px;
        }

        .doc-nav {
            background: linear-gradient(135deg, #0B1F2A 0%, #16303D 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .doc-nav .navbar-brand {
            color: #fff;
        }

        .doc-nav .navbar-links a {
            color: #9FB3BE;
        }

        .doc-nav .navbar-links a:hover,
        .doc-nav .navbar-links a.active {
            color: #fff;
        }

        .doc-nav .profile-badge {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .container-main {
            max-width: 1400px;
            margin: 28px auto;
            padding: 0 20px;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 26px;
            align-items: start;
        }

        .c-box {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            padding: 22px;
            margin-bottom: 20px;
        }

        .cb-head {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--clr-primary-darker);
            margin-bottom: 16px;
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 9px;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        /* ── Patient list ──────────────────────────────── */
        .pl-search {
            position: relative;
            margin-bottom: 14px;
        }

        .pl-search input {
            width: 100%;
            border: 1.5px solid var(--clr-border);
            padding: 12px 16px 12px 44px;
            border-radius: var(--radius-full);
            font-size: 0.92rem;
            font-family: inherit;
            font-weight: 500;
            transition: var(--transition);
        }

        .pl-search input:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 4px var(--clr-primary-light);
        }

        .pl-search i {
            position: absolute;
            left: 20px;
            top: 14px;
            color: var(--clr-text-muted);
        }

        .pl-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--clr-border);
            background: #FBFCFD;
            margin-bottom: 9px;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }

        .pl-row:hover {
            border-color: var(--clr-primary);
            background: var(--clr-primary-light);
        }

        .pl-row.active {
            border-color: var(--clr-primary);
            background: var(--clr-primary-light);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
        }

        .pl-avatar {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: var(--grad-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
        }

        .pl-name {
            font-weight: 700;
            font-size: 0.93rem;
        }

        .pl-meta {
            font-size: 0.78rem;
            color: var(--clr-text-secondary);
            font-weight: 500;
        }

        /* ── Patient header ────────────────────────────── */
        .pat-header {
            text-align: center;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--clr-border);
            margin-bottom: 22px;
        }

        .pat-avatar {
            width: 84px;
            height: 84px;
            border-radius: 26px;
            background: var(--grad-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            margin: 0 auto 14px;
            box-shadow: var(--shadow-glow);
        }

        .pat-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.45rem;
            color: var(--clr-text-primary);
        }

        .pat-id {
            font-family: ui-monospace, monospace;
            color: var(--clr-text-secondary);
            font-size: 0.9rem;
        }

        .pat-badges {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        /* ── Vitals ────────────────────────────────────── */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .vital-card {
            background: white;
            border: 1px solid var(--clr-border);
            padding: 16px;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .vc-icon {
            font-size: 1.35rem;
            color: var(--clr-primary);
            margin-bottom: 8px;
        }

        .vc-val {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            line-height: 1.1;
        }

        .vc-label {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* ── Info pills ────────────────────────────────── */
        .info-pill {
            background: #FBFCFD;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-pill i {
            width: 22px;
            text-align: center;
            font-size: 1rem;
            color: var(--clr-primary);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .ip-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 3px;
        }

        .ip-value {
            font-size: 0.93rem;
            font-weight: 600;
            color: var(--clr-text-primary);
            word-break: break-word;
        }

        /* ── Tabs ──────────────────────────────────────── */
        .tab-bar {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid var(--clr-border);
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--clr-text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            font-family: inherit;
            transition: var(--transition);
        }

        .tab-btn:hover {
            color: var(--clr-primary);
        }

        .tab-btn.active {
            color: var(--clr-primary-darker);
            border-bottom-color: var(--clr-primary);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* ── Timeline ──────────────────────────────────── */
        .timeline {
            position: relative;
            margin-left: 18px;
            border-left: 2px solid var(--clr-border);
            padding-left: 28px;
        }

        .tl-entry {
            margin-bottom: 24px;
            position: relative;
        }

        .tl-dot {
            position: absolute;
            left: -37px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: white;
            border: 4px solid var(--clr-primary);
        }

        .tl-date {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: var(--clr-primary-darker);
            margin-bottom: 8px;
            font-size: 0.98rem;
        }

        .tl-card {
            background: white;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 15px 17px;
            box-shadow: var(--shadow-sm);
        }

        .tl-card h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
        }

        .tl-body {
            font-size: 0.88rem;
            color: var(--clr-text-secondary);
            line-height: 1.6;
        }

        .tl-note {
            margin-top: 10px;
            padding: 11px 13px;
            background: var(--clr-primary-light);
            border: 1px solid var(--clr-primary-soft);
            border-radius: var(--radius-sm);
            font-size: 0.86rem;
            color: var(--clr-primary-darker);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--grad-primary);
            color: #fff;
            padding: 12px 22px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            box-shadow: var(--shadow-glow);
            transition: var(--transition);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-accent {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--grad-accent);
            color: #fff;
            padding: 12px 22px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            box-shadow: 0 12px 30px -12px rgba(79, 70, 229, 0.5);
            transition: var(--transition);
        }

        .btn-accent:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .actions-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .empty {
            text-align: center;
            padding: 34px 16px;
            color: var(--clr-text-secondary);
        }

        .empty i {
            font-size: 1.9rem;
            color: var(--clr-text-muted);
            margin-bottom: 10px;
            display: block;
        }

        .empty p {
            font-size: 0.88rem;
            margin: 0;
        }

        @media (max-width: 1100px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }

            .vitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container-main {
                padding: 16px;
            }

            .c-box {
                padding: 18px 14px;
                border-radius: var(--radius-md);
            }

            .actions-row {
                flex-direction: column;
            }

            .btn-primary,
            .btn-accent {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .vitals-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .container-main {
                padding: 10px;
            }

            .c-box {
                padding: 14px 10px;
            }

            .pat-avatar {
                width: 64px;
                height: 64px;
                font-size: 1.6rem;
                border-radius: 18px;
            }

            .pat-name {
                font-size: 1.25rem;
            }

            .vc-val {
                font-size: 1.3rem;
            }

            .pl-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.85rem;
            }

            .pl-name {
                font-size: 0.88rem;
            }
        }

        @media (max-width: 360px) {
            .pat-name {
                font-size: 1.1rem;
            }

            .btn-primary,
            .btn-accent {
                padding: 10px 14px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <?php $ksr_active = '';
    $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php';
    $navActive = 'patients';
    include __DIR__ . '/partials/doctor-nav.php'; ?>

    

    <div class="container-main">
        <div class="grid-layout">

            <!-- LEFT: patient list -->
            <div>
                <div class="c-box" style="position:sticky; top:20px;">
                    <div class="cb-head">
                        <div><i class="fas fa-users"></i> My Patients</div>
                        <span class="pill pill-info"><?php echo count($patients); ?></span>
                    </div>

                    <form method="GET" class="pl-search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" value="<?php echo h($query); ?>" placeholder="Search by name, mobile or ID...">
                    </form>

                    <?php if ($patients): ?>
                        <?php foreach ($patients as $pl): ?>
                            <?php $isActive = $record && (int)$record['id'] === (int)$pl['id']; ?>
                            <a href="doctor-patients.php?patient=<?php echo (int)$pl['id']; ?><?= $query !== '' ? '&q=' . urlencode($query) : ''; ?>"
                                class="pl-row <?php echo $isActive ? 'active' : ''; ?>">
                                <div class="pl-avatar"><?php echo h(strtoupper(substr((string)($pl['patient_name'] ?? 'P'), 0, 1) ?: 'P')); ?></div>
                                <div style="flex:1; min-width:0;">
                                    <div class="pl-name"><?php echo h($pl['patient_name'] ?: 'Unnamed patient'); ?></div>
                                    <div class="pl-meta">
                                        <?php echo $pl['age'] !== null ? (int)$pl['age'] . ' yrs' : 'Age n/a'; ?>
                                        &middot; <?php echo h($pl['gender'] ?: '—'); ?>
                                        &middot; <?php echo (int)$pl['consultation_count']; ?> consults
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right" style="color:var(--clr-text-muted); font-size:0.78rem;"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-user-slash"></i>
                            <p><?= $query !== '' ? 'No patients match your search.' : 'No patients linked to your account yet.' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: full patient record -->
            <div>
                <?php if ($record): ?>
                    <div class="c-box">
                        <div class="pat-header">
                            <div class="pat-avatar"><?php echo h($record['initials']); ?></div>
                            <div class="pat-name"><?php echo h($record['patient_name'] ?: 'Unnamed patient'); ?></div>
                            <div class="pat-id">PID-<?php echo str_pad((string)$record['id'], 5, '0', STR_PAD_LEFT); ?></div>
                            <div class="pat-badges">
                                <?php if ($record['age'] !== null): ?>
                                    <span class="pill pill-info"><i class="fas <?php echo h($record['age_group']['icon']); ?>"></i> <?php echo (int)$record['age']; ?> yrs</span>
                                <?php endif; ?>
                                <span class="pill pill-muted"><i class="fas fa-venus-mars"></i> <?php echo h($record['gender'] ?: 'Not specified'); ?></span>
                                <?php if (!empty($record['blood_group'])): ?>
                                    <span class="pill pill-danger"><i class="fas fa-tint"></i> <?php echo h($record['blood_group']); ?></span>
                                <?php endif; ?>
                                <span class="pill pill-ok"><i class="fas fa-shield-halved"></i> Verified</span>
                            </div>
                        </div>

                        <!-- Vitals summary -->
                        <div class="vitals-grid">
                            <div class="vital-card">
                                <div class="vc-icon"><i class="fas fa-stethoscope"></i></div>
                                <div class="vc-val"><?php echo (int)$record['stats']['consultations']; ?></div>
                                <div class="vc-label">Consultations</div>
                            </div>
                            <div class="vital-card">
                                <div class="vc-icon" style="color:var(--clr-success);"><i class="fas fa-file-prescription"></i></div>
                                <div class="vc-val"><?php echo (int)$record['stats']['prescriptions']; ?></div>
                                <div class="vc-label">Prescriptions</div>
                            </div>
                            <div class="vital-card">
                                <div class="vc-icon" style="color:#7E22CE;"><i class="fas fa-folder-open"></i></div>
                                <div class="vc-val"><?php echo (int)$record['stats']['documents']; ?></div>
                                <div class="vc-label">Documents</div>
                            </div>
                            <div class="vital-card">
                                <div class="vc-icon" style="color:var(--clr-warning);"><i class="fas fa-box"></i></div>
                                <div class="vc-val"><?php echo (int)$record['stats']['orders']; ?></div>
                                <div class="vc-label">Orders</div>
                            </div>
                        </div>

                        <div class="actions-row">
                            <a href="build-prescription.php?patient=<?php echo (int)$record['id']; ?>" class="btn-accent">
                                <i class="fas fa-file-prescription"></i> Write Prescription
                            </a>
                            <a href="doctor-consultation-room.php?patient=<?php echo (int)$record['id']; ?>" class="btn-primary">
                                <i class="fas fa-video"></i> Start Consultation
                            </a>
                            <?php if (!empty($record['mobile_number'])): ?>
                                <a href="tel:<?php echo h($record['mobile_number']); ?>" class="btn-primary" style="background:#0B1F2A; box-shadow:none;">
                                    <i class="fas fa-phone-alt"></i> Call Patient
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Tabs -->
                        <div class="tab-bar">
                            <button class="tab-btn active" data-tab="overview"><i class="fas fa-user-circle"></i> Overview</button>
                            <button class="tab-btn" data-tab="history"><i class="fas fa-clock-rotate-left"></i> Consultation History</button>
                            <button class="tab-btn" data-tab="meds"><i class="fas fa-pills"></i> Prescriptions</button>
                            <button class="tab-btn" data-tab="docs"><i class="fas fa-folder-open"></i> Documents</button>
                        </div>

                        <!-- Overview -->
                        <div class="tab-panel active" id="tab-overview">
                            <div class="cb-head"><i class="fas fa-id-card"></i> Demographics &amp; Contact</div>
                            <div class="info-pill">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <div class="ip-label">Mobile Number</div>
                                    <div class="ip-value"><?php echo h($record['mobile_number'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <div class="ip-label">Email / Username</div>
                                    <div class="ip-value"><?php echo h($record['user_email'] ?: $record['user_phone'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill">
                                <i class="far fa-calendar"></i>
                                <div>
                                    <div class="ip-label">Date of Birth</div>
                                    <div class="ip-value"><?php echo h($record['date_of_birth'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill">
                                <i class="fas fa-location-dot"></i>
                                <div>
                                    <div class="ip-label">Address</div>
                                    <div class="ip-value"><?php echo h($record['address'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill">
                                <i class="fas fa-kit-medical"></i>
                                <div>
                                    <div class="ip-label">Emergency Contact</div>
                                    <div class="ip-value"><?php echo h($record['emergency_contact'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>

                            <div class="cb-head" style="margin-top:24px;"><i class="fas fa-notes-medical"></i> Clinical Summary</div>
                            <div class="info-pill" style="border-color:#FDE68A; background:#FFFBEB;">
                                <i class="fas fa-clock-rotate-left" style="color:var(--clr-warning);"></i>
                                <div>
                                    <div class="ip-label">Medical History</div>
                                    <div class="ip-value"><?php echo h($record['medical_history'] ?: 'No history recorded'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill" style="border-color:#FECACA; background:#FEF2F2;">
                                <i class="fas fa-triangle-exclamation" style="color:var(--clr-danger);"></i>
                                <div>
                                    <div class="ip-label">Allergies</div>
                                    <div class="ip-value"><?php echo h($record['allergies'] ?: 'None known'); ?></div>
                                </div>
                            </div>
                            <div class="info-pill" style="border-color:#A7F3D0; background:#F0FDF4;">
                                <i class="fas fa-pills" style="color:var(--clr-success);"></i>
                                <div>
                                    <div class="ip-label">Current Medications</div>
                                    <div class="ip-value"><?php echo h($record['current_medications'] ?: 'None active'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Consultation history -->
                        <div class="tab-panel" id="tab-history">
                            <div class="cb-head"><i class="fas fa-clock-rotate-left"></i> Consultation Timeline</div>
                            <?php if ($record['consultations']): ?>
                                <div class="timeline">
                                    <?php foreach ($record['consultations'] as $c): ?>
                                        <?php
                                        $cType   = (string)($c['consultation_type'] ?? 'video');
                                        $cStatus = telehealthStatusTone((string)($c['status'] ?? ''));
                                        ?>
                                        <div class="tl-entry">
                                            <div class="tl-dot"></div>
                                            <div class="tl-date"><?php echo h(telehealthWhen($c['scheduled_at'] ?? $c['created_at'] ?? null, 'Date not recorded')); ?></div>
                                            <div class="tl-card">
                                                <h4>
                                                    <i class="fas <?php echo dp_consult_icon($cType); ?>" style="color:var(--clr-primary);"></i>
                                                    <?php echo h(dp_consult_label($cType)); ?>
                                                    <span class="pill pill-<?php echo h($cStatus[1]); ?>"><?php echo h($cStatus[0]); ?></span>
                                                </h4>
                                                <div class="tl-body">
                                                    <strong>Doctor:</strong> <?php echo h($c['doctor_name'] ?: 'Assigned clinician'); ?>
                                                    &middot; <strong>Payment:</strong> <?php echo h(telehealthStatusTone((string)($c['payment_status'] ?? 'pending'))[0]); ?>
                                                    <?php if (!empty($c['payment_amount'])): ?>
                                                        &middot; <strong>Amount:</strong> <?php echo h(telehealthMoney($c['payment_amount'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($c['symptoms'])): ?>
                                                    <div class="tl-note"><strong>Reported symptoms:</strong> <?php echo h($c['symptoms']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($c['consultation_notes'])): ?>
                                                    <div class="tl-note"><strong>Clinical notes:</strong> <?php echo h($c['consultation_notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty">
                                    <i class="far fa-clock"></i>
                                    <p>No consultations recorded for this patient yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Prescriptions -->
                        <div class="tab-panel" id="tab-meds">
                            <div class="cb-head"><i class="fas fa-file-prescription"></i> Prescription History</div>
                            <?php if ($record['prescriptions']): ?>
                                <?php foreach ($record['prescriptions'] as $rx): ?>
                                    <?php $rxStatus = telehealthStatusTone((string)($rx['status'] ?? $rx['verification_status'] ?? '')); ?>
                                    <div class="info-pill" style="align-items:center;">
                                        <i class="fas fa-prescription-bottle-medical"></i>
                                        <div style="flex:1;">
                                            <div class="ip-label"><?php echo h(telehealthRxCode((int)$rx['id'])); ?> &middot; <?php echo h($rx['doctor_name'] ?: 'Clinician'); ?></div>
                                            <div class="ip-value" style="font-weight:500; font-size:0.87rem; color:var(--clr-text-secondary);">
                                                <?php echo h(telehealthWhen($rx['created_at'] ?? null, 'Date unknown')); ?>
                                            </div>
                                        </div>
                                        <span class="pill pill-<?php echo h($rxStatus[1]); ?>"><?php echo h($rxStatus[0]); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty">
                                    <i class="fas fa-file-prescription"></i>
                                    <p>No prescriptions issued to this patient yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Documents -->
                        <div class="tab-panel" id="tab-docs">
                            <div class="cb-head"><i class="fas fa-folder-open"></i> Medical Documents</div>
                            <?php if ($record['documents']): ?>
                                <?php foreach ($record['documents'] as $docRow): ?>
                                    <?php $dStatus = !empty($docRow['is_verified']) ? ['Verified', 'ok'] : ['Unverified', 'warn']; ?>
                                    <div class="info-pill" style="align-items:center;">
                                        <i class="fas fa-file-medical"></i>
                                        <div style="flex:1;">
                                            <div class="ip-label"><?php echo h(ucwords(str_replace('_', ' ', (string)($docRow['doc_type'] ?? 'document')))); ?></div>
                                            <div class="ip-value" style="font-weight:600;"><?php echo h($docRow['doc_name'] ?? 'Untitled'); ?></div>
                                            <div style="font-size:0.78rem;color:var(--clr-text-secondary);">
                                                <?php echo h(telehealthWhen($docRow['created_at'] ?? null, '')); ?>
                                            </div>
                                        </div>
                                        <span class="pill pill-<?php echo h($dStatus[1]); ?>"><?php echo h($dStatus[0]); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No lab reports or scans uploaded for this patient.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="c-box">
                        <div class="empty">
                            <i class="fas fa-user-slash"></i>
                            <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:8px;">No patient selected</h3>
                            <p>Select a patient from the list to view their full clinical record.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                document.querySelectorAll('.tab-panel').forEach(function(p) {
                    p.classList.remove('active');
                });

                btn.classList.add('active');
                var panel = document.getElementById('tab-' + btn.dataset.tab);
                if (panel) panel.classList.add('active');
            });
        });
    </script>
</body>

</html>