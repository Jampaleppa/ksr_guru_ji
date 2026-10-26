<?php
require_once 'helper.php';
require_once dirname(__DIR__, 2) . '/patient-auth.php';

// Secure session handling: guests are sent to the Patient Portal login
if (!pp_is_logged_in()) {
    header('Location: ../../patient-login.php?next=' . urlencode('WhatsApp/telehealth/patient-dashboard.php'));
    exit;
}

$pdo    = getAssistDB();
$userId = (int)$_SESSION['user_id'];
$registrationSuccess = !empty($_SESSION['patient_registration_success']);
unset($_SESSION['patient_registration_success']);

// ── All dashboard data comes live from the database ──────────────
$data     = telehealthPatientDashboardData($pdo, $userId);
$patient  = $data['patient'];
$stats    = $data['stats'];
$next     = $data['next'];
$upcoming = $data['upcoming'];

$patientName = $patient['name'] !== '' ? $patient['name'] : ($_SESSION['user_name'] ?? 'Patient');
$ageGroup    = $patient['age_group'];
$greeting    = (date('H') < 12) ? 'Good Morning' : ((date('H') < 17) ? 'Good Afternoon' : 'Good Evening');

/** Icon per consultation type. */
function pp_consult_icon(string $type): string
{
    return [
        'video'     => 'fa-video',
        'audio'     => 'fa-phone-alt',
        'chat'      => 'fa-comment-dots',
        'in_person' => 'fa-user-md',
    ][$type] ?? 'fa-stethoscope';
}

/** Human label for a consultation type. */
function pp_consult_label(string $type): string
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Dashboard | KSR GURU JI Patient Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <style>
        body {
            background: var(--clr-bg-main);
            padding-bottom: 96px;
            font-family: 'Inter', system-ui, sans-serif;
            margin: 0;
        }

        /* ── App header ─────────────────────────────────── */
        .app-header {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--clr-border);
            box-shadow: 0 8px 24px -20px rgba(11, 31, 42, 0.6);
        }

        .ah-logo {
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--clr-primary-darker);
            display: flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
        }

        .ah-actions {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .ah-icon {
            font-size: 1.1rem;
            color: var(--clr-text-secondary);
            text-decoration: none;
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--clr-bg-soft);
            transition: var(--transition);
        }

        .ah-icon:hover {
            color: var(--clr-primary);
            background: var(--clr-primary-light);
        }

        .ah-avatar {
            width: 40px;
            height: 40px;
            border-radius: 13px;
            background: var(--grad-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            text-decoration: none;
            box-shadow: var(--shadow-glow);
        }

        .main-container {
            max-width: 860px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* ── Welcome ───────────────────────────────────── */
        .welcome-section {
            margin-bottom: 22px;
        }

        .registration-success {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 0 0 18px;
            padding: 14px 16px;
            border: 1px solid #86efac;
            border-radius: 12px;
            background: #f0fdf4;
            color: #166534;
        }

        .registration-success i {
            margin-top: 2px;
            font-size: 1.1rem;
        }

        .registration-success strong,
        .registration-success span {
            display: block;
        }

        .registration-success span {
            margin-top: 3px;
            font-size: 0.9rem;
        }

        .ws-greet {
            font-family: 'Poppins', sans-serif;
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            margin-bottom: 6px;
        }

        .ws-sub {
            font-size: 0.92rem;
            color: var(--clr-text-secondary);
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ws-age {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 13px;
            border-radius: var(--radius-full);
            font-size: 0.78rem;
            font-weight: 700;
        }

        .ws-age.tone-primary {
            background: var(--clr-primary-light);
            color: var(--clr-primary-darker);
        }

        .ws-age.tone-info {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .ws-age.tone-accent {
            background: var(--clr-accent-light);
            color: var(--clr-accent-dark);
        }

        .ws-age.tone-warn {
            background: var(--clr-warning-light);
            color: #92400E;
        }

        .ws-age.tone-muted {
            background: var(--clr-bg-soft);
            color: var(--clr-text-secondary);
        }

        /* ── Search ────────────────────────────────────── */
        .universal-search {
            position: relative;
            margin-bottom: 28px;
        }

        .universal-search input {
            width: 100%;
            background: white;
            border: 1.5px solid var(--clr-border);
            padding: 15px 20px 15px 50px;
            border-radius: var(--radius-full);
            font-size: 0.98rem;
            font-weight: 500;
            font-family: inherit;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .universal-search input:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 4px var(--clr-primary-light);
        }

        .universal-search i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-primary);
            font-size: 1.05rem;
        }

        /* ── Section heading ───────────────────────────── */
        .sec-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .sec-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--clr-text-primary);
        }

        .sec-link {
            font-size: 0.83rem;
            font-weight: 700;
            color: var(--clr-primary);
            text-decoration: none;
        }

        .sec-link:hover {
            color: var(--clr-primary-dark);
        }

        /* ── Quick actions ─────────────────────────────── */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }

        .qa-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 16px 12px;
            text-align: center;
            text-decoration: none;
            color: var(--clr-text-primary);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .qa-card:hover {
            transform: translateY(-3px);
            border-color: var(--clr-primary-soft);
            box-shadow: var(--shadow-hover);
        }

        .qa-icon {
            width: 46px;
            height: 46px;
            margin: 0 auto 10px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .qa-title {
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .qa-c-doc {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .qa-c-vid {
            background: var(--clr-primary-light);
            color: var(--clr-primary);
        }

        .qa-c-rx {
            background: var(--clr-success-light);
            color: #047857;
        }

        .qa-c-lab {
            background: #F3E8FF;
            color: #7E22CE;
        }

        .qa-c-img {
            background: #FFF7ED;
            color: #C2410C;
        }

        .qa-c-emg {
            background: var(--clr-danger-light);
            color: var(--clr-danger);
        }

        /* ── Upcoming appointment hero ─────────────────── */
        .hero-banner {
            background: var(--grad-primary);
            border-radius: var(--radius-xl);
            padding: 26px;
            color: #fff;
            margin-bottom: 30px;
            box-shadow: var(--shadow-glow);
            position: relative;
            overflow: hidden;
        }

        .hero-banner::after {
            content: '\f0f1';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -20px;
            bottom: -34px;
            font-size: 10rem;
            color: rgba(255, 255, 255, 0.08);
        }

        .hero-banner>* {
            position: relative;
            z-index: 1;
        }

        .hb-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.18);
            padding: 5px 13px;
            border-radius: 20px;
            font-size: 0.73rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 15px;
        }

        .hb-doc {
            font-family: 'Poppins', sans-serif;
            font-size: 1.28rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .hb-spec {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .hb-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 22px;
            background: rgba(0, 0, 0, 0.16);
            padding: 14px 18px;
            border-radius: var(--radius-sm);
        }

        .hb-k {
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 800;
            opacity: 0.8;
            margin-bottom: 4px;
            letter-spacing: 0.04em;
        }

        .hb-v {
            font-weight: 700;
            font-size: 1rem;
        }

        .hb-btn-group {
            display: flex;
            gap: 11px;
            flex-wrap: wrap;
        }

        .hb-btn {
            background: #fff;
            color: var(--clr-primary-darker);
            padding: 11px 22px;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            text-align: center;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .hb-btn:hover {
            background: #F0FDFA;
            transform: translateY(-2px);
        }

        .hb-btn-out {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .hb-btn-out:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        /* ── Empty state ───────────────────────────────── */
        .empty-state {
            background: white;
            border: 1px dashed var(--clr-border-strong);
            border-radius: var(--radius-lg);
            padding: 34px 24px;
            text-align: center;
            margin-bottom: 30px;
        }

        .empty-state i {
            font-size: 2rem;
            color: var(--clr-text-muted);
            margin-bottom: 12px;
            display: block;
        }

        .empty-state h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.02rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: var(--clr-text-secondary);
            font-size: 0.88rem;
            margin-bottom: 16px;
        }

        /* ── Stat cards ────────────────────────────────── */
        .scroll-tray {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }

        .health-card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border);
            padding: 18px;
            text-decoration: none;
            color: var(--clr-text-primary);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .health-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--clr-primary-soft);
        }

        .health-card i {
            font-size: 1.5rem;
            margin-bottom: 12px;
            display: block;
        }

        .hc-val {
            font-family: 'Poppins', sans-serif;
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 3px;
        }

        .hc-lbl {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
            line-height: 1.3;
        }

        /* ── Appointment rows ──────────────────────────── */
        .appt-row {
            background: white;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 15px 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .appt-row:hover {
            border-color: var(--clr-primary-soft);
            box-shadow: var(--shadow-md);
            transform: translateX(3px);
        }

        .ar-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: var(--clr-primary-light);
            color: var(--clr-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .ar-body {
            flex: 1;
            min-width: 0;
        }

        .ar-title {
            font-weight: 700;
            font-size: 0.97rem;
            margin-bottom: 3px;
        }

        .ar-sub {
            font-size: 0.82rem;
            color: var(--clr-text-secondary);
            font-weight: 500;
        }

        /* ── Service chips ─────────────────────────────── */
        .spec-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }

        .spec-chip {
            background: white;
            border: 1px solid var(--clr-border);
            padding: 13px 16px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--clr-text-primary);
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .spec-chip:hover {
            border-color: var(--clr-primary);
            background: #F7FDFC;
            transform: translateY(-2px);
        }

        .spec-chip>span {
            font-size: 1.25rem;
        }

        /* ── Bottom nav ────────────────────────────────── */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--clr-border);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            padding-bottom: calc(12px + env(safe-area-inset-bottom));
            box-shadow: 0 -8px 24px -22px rgba(11, 31, 42, 0.8);
            z-index: 1000;
        }

        .bn-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: var(--clr-text-muted);
            flex: 1;
            transition: var(--transition);
        }

        .bn-item i {
            font-size: 1.25rem;
        }

        .bn-item span {
            font-size: 0.68rem;
            font-weight: 700;
        }

        .bn-item:hover,
        .bn-item.active {
            color: var(--clr-primary);
        }

        .fab-care {
            width: 54px;
            height: 54px;
            background: var(--grad-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            box-shadow: var(--shadow-glow);
            margin-top: -28px;
            border: 4px solid var(--clr-bg-main);
        }

        @media (min-width: 768px) {
            .quick-actions {
                grid-template-columns: repeat(6, 1fr);
            }

            .spec-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .hero-banner {
                padding: 36px;
            }

            .hb-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .main-container {
                padding: 32px 24px;
            }
        }

        @media (max-width: 560px) {
            .scroll-tray {
                grid-template-columns: repeat(2, 1fr);
            }

            .hb-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php $ksr_active = '';
    $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php'; ?>

    <!-- Header -->
    <header class="app-header">
        <a href="patient-dashboard.php" class="ah-logo"><i class="fas fa-heart-pulse"></i> KSR GURU JI</a>
        <div class="ah-actions">
            <a href="notifications.php" class="ah-icon" title="Notifications"><i class="fas fa-bell"></i></a>
            <a href="../../../patient-logout.php" class="ah-icon" title="Sign out"><i class="fas fa-right-from-bracket"></i></a>
            <a href="profile.php" class="ah-avatar" title="My Profile"><?php echo h($patient['initials']); ?></a>
        </div>
    </header>

    <div class="main-container">

        <!-- Welcome -->
        <div class="welcome-section">
            <?php if ($registrationSuccess): ?>
                <div class="registration-success" role="status" aria-live="polite">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <div>
                        <strong>Registration complete</strong>
                        <span>Your mobile is verified and your patient dashboard is ready.</span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="ws-greet"><?php echo h($greeting); ?>, <?php echo h($patientName); ?> 👋</div>
            <div class="ws-sub">
                <span>Your complete healthcare platform</span>
                <?php if ($patient['age'] !== null): ?>
                    <span class="ws-age tone-<?php echo h($ageGroup['tone']); ?>">
                        <i class="fas <?php echo h($ageGroup['icon']); ?>"></i>
                        <?php echo (int)$patient['age']; ?> yrs &middot; <?php echo h($ageGroup['label']); ?>
                    </span>
                <?php endif; ?>
                <?php if ($patient['gender'] !== ''): ?>
                    <span class="ws-age tone-muted"><i class="fas fa-venus-mars"></i> <?php echo h($patient['gender']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search -->
        <div class="universal-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search doctors, medicines, services...">
        </div>

        <!-- Quick actions -->
        <div class="sec-header">
            <div class="sec-title">Quick Actions</div>
        </div>
        <div class="quick-actions">
            <a href="find-doctor.php" class="qa-card">
                <div class="qa-icon qa-c-doc"><i class="fas fa-stethoscope"></i></div>
                <div class="qa-title">Find Doctor</div>
            </a>
            <a href="my-appointments.php" class="qa-card">
                <div class="qa-icon qa-c-vid"><i class="fas fa-calendar-check"></i></div>
                <div class="qa-title">My<br>Appointments</div>
            </a>
            <a href="pharmacy.php" class="qa-card">
                <div class="qa-icon qa-c-rx"><i class="fas fa-pills"></i></div>
                <div class="qa-title">Pharmacy</div>
            </a>
            <a href="patient-master-record.php" class="qa-card">
                <div class="qa-icon qa-c-lab"><i class="fas fa-flask"></i></div>
                <div class="qa-title">Lab<br>Reports</div>
            </a>
            <a href="patient-master-record.php" class="qa-card">
                <div class="qa-icon qa-c-img"><i class="fas fa-file-medical-alt"></i></div>
                <div class="qa-title">Upload<br>Report</div>
            </a>
            <a href="ambulance-acute-care.php" class="qa-card" style="border-color:#FECACA;">
                <div class="qa-icon qa-c-emg"><i class="fas fa-ambulance"></i></div>
                <div class="qa-title" style="color:var(--clr-danger);">Emergency</div>
            </a>
        </div>

        <!-- Next appointment (dynamic) -->
        <?php if ($next): ?>
            <?php
            $nStatus = telehealthStatusTone((string)($next['status'] ?? 'requested'));
            $nType   = (string)($next['consultation_type'] ?? 'video');
            ?>
            <div class="hero-banner">
                <div class="hb-tag">
                    <i class="fas fa-circle" style="font-size:0.5rem;color:#86EFAC;"></i> UPCOMING APPOINTMENT
                </div>
                <div class="hb-doc"><?php echo h($next['doctor_name'] ?: 'Doctor assignment pending'); ?></div>
                <div class="hb-spec">
                    <?php echo h(pp_consult_label($nType)); ?>
                    &middot; <?php echo h($nStatus[0]); ?>
                </div>

                <div class="hb-grid">
                    <div>
                        <div class="hb-k">Date &amp; Time</div>
                        <div class="hb-v"><i class="far fa-calendar"></i> <?php echo h(telehealthWhen($next['scheduled_at'] ?? null, 'Awaiting slot')); ?></div>
                    </div>
                    <div>
                        <div class="hb-k">Format</div>
                        <div class="hb-v"><i class="fas <?php echo pp_consult_icon($nType); ?>"></i> <?php echo h(pp_consult_label($nType)); ?></div>
                    </div>
                </div>

                <div class="hb-btn-group">
                    <a href="consultation-room.php" class="hb-btn"><i class="fas fa-phone-alt"></i> Join Consultation</a>
                    <a href="my-appointments.php" class="hb-btn hb-btn-out"><i class="far fa-clock"></i> Reschedule</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-calendar"></i>
                <h3>No upcoming appointments</h3>
                <p>Book a consultation with a verified KSR GURU JI practitioner.</p>
                <a href="find-doctor.php" style="display:inline-flex;align-items:center;gap:8px;padding:11px 26px;border-radius:var(--radius-sm);background:var(--grad-primary);color:#fff;font-weight:700;font-size:0.9rem;text-decoration:none;box-shadow:var(--shadow-glow);">
                    <i class="fas fa-search"></i> Find a Doctor
                </a>
            </div>
        <?php endif; ?>

        <!-- My health workspace (dynamic counts) -->
        <div class="sec-header">
            <div class="sec-title">My Health Workspace</div>
            <a href="patient-master-record.php" class="sec-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="scroll-tray">
            <a href="my-appointments.php" class="health-card">
                <i class="fas fa-folder-open" style="color:var(--clr-primary);"></i>
                <div class="hc-val"><?php echo (int)$stats['consultations']; ?></div>
                <div class="hc-lbl">Consultations</div>
            </a>
            <a href="my-prescriptions.php" class="health-card">
                <i class="fas fa-prescription-bottle-alt" style="color:var(--clr-success);"></i>
                <div class="hc-val"><?php echo (int)$stats['prescriptions']; ?></div>
                <div class="hc-lbl">Prescriptions</div>
            </a>
            <a href="patient-master-record.php" class="health-card">
                <i class="fas fa-microscope" style="color:#7E22CE;"></i>
                <div class="hc-val"><?php echo (int)$stats['documents']; ?></div>
                <div class="hc-lbl">Lab Reports</div>
            </a>
            <a href="my-orders.php" class="health-card">
                <i class="fas fa-box" style="color:var(--clr-warning);"></i>
                <div class="hc-val"><?php echo (int)$stats['orders']; ?></div>
                <div class="hc-lbl">Pharmacy Orders</div>
            </a>
        </div>

        <!-- Upcoming appointments list -->
        <?php if ($upcoming): ?>
            <div class="sec-header">
                <div class="sec-title">Scheduled Appointments</div>
                <a href="my-appointments.php" class="sec-link">See all <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php foreach (array_slice($upcoming, 0, 4) as $ap): ?>
                <?php
                $aStatus = telehealthStatusTone((string)($ap['status'] ?? ''));
                $aType   = (string)($ap['consultation_type'] ?? 'video');
                ?>
                <a href="my-appointments.php" class="appt-row">
                    <div class="ar-icon"><i class="fas <?php echo pp_consult_icon($aType); ?>"></i></div>
                    <div class="ar-body">
                        <div class="ar-title"><?php echo h($ap['doctor_name'] ?: 'Doctor to be assigned'); ?></div>
                        <div class="ar-sub">
                            <?php echo h($ap['specialty'] ?: pp_consult_label($aType)); ?>
                            &middot; <?php echo h(telehealthWhen($ap['scheduled_at'] ?? null, 'Slot pending')); ?>
                        </div>
                    </div>
                    <span class="pill pill-<?php echo h($aStatus[1]); ?>"><?php echo h($aStatus[0]); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Specialist centres -->
        <div class="sec-header" style="margin-top:30px;">
            <div class="sec-title">Specialist Centers</div>
        </div>
        <div class="spec-grid">
            <a href="find-doctor.php?spec=cardiology" class="spec-chip"><span>❤️</span> Cardiology</a>
            <a href="find-doctor.php?spec=neurology" class="spec-chip"><span>🧠</span> Neurology</a>
            <a href="find-doctor.php?spec=pulmonology" class="spec-chip"><span>🫁</span> Pulmonology</a>
            <a href="find-doctor.php?spec=gastro" class="spec-chip"><span>🩺</span> Gastro</a>
            <a href="find-doctor.php?spec=pediatrics" class="spec-chip"><span>🧒</span> Pediatrics</a>
            <a href="find-doctor.php?spec=dental" class="spec-chip"><span>🦷</span> Dental Care</a>
        </div>

        <!-- My services -->
        <div class="sec-header">
            <div class="sec-title">My Services</div>
        </div>
        <div class="spec-grid">
            <a href="profile.php" class="spec-chip"><span>👤</span> Profile</a>
            <a href="my-appointments.php" class="spec-chip"><span>📅</span> Appointments</a>
            <a href="my-prescriptions.php" class="spec-chip"><span>💊</span> Prescriptions</a>
            <a href="pharmacy.php" class="spec-chip"><span>🛒</span> Pharmacy</a>
            <a href="patient-master-record.php" class="spec-chip"><span>🧪</span> Lab Reports</a>
            <a href="my-orders.php" class="spec-chip"><span>📦</span> Orders</a>
            <a href="payment.php" class="spec-chip"><span>💳</span> Payments</a>
            <a href="delivery-dashboard.php" class="spec-chip"><span>🚚</span> Delivery</a>
            <a href="notifications.php" class="spec-chip"><span>🔔</span> Notifications</a>
            <a href="support-helpdesk.php" class="spec-chip"><span>🎧</span> Support</a>
        </div>

        <div style="height:40px;"></div>
    </div>

    <!-- Bottom navigation -->
    <nav class="bottom-nav">
        <a href="patient-dashboard.php" class="bn-item active">
            <i class="fas fa-home"></i><span>Home</span>
        </a>
        <a href="find-doctor.php" class="bn-item">
            <i class="fas fa-user-md"></i><span>Doctors</span>
        </a>
        <a href="find-doctor.php" class="bn-item" style="position:relative; z-index:100; flex:0 0 60px;">
            <div class="fab-care"><i class="fas fa-plus"></i></div>
            <span style="margin-top:2px;">Care</span>
        </a>
        <a href="patient-master-record.php" class="bn-item">
            <i class="fas fa-notes-medical"></i><span>Records</span>
        </a>
        <a href="profile.php" class="bn-item">
            <i class="fas fa-user"></i><span>Profile</span>
        </a>
    </nav>
</body>

</html>