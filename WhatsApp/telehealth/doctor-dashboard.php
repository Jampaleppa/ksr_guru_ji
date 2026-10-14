<?php
require_once 'helper.php';

$pdo = getAssistDB();

// ── Resolve the signed-in doctor (falls back to the lead doctor) ──
$doctorId  = telehealthResolveDoctorId($pdo, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
$doc       = telehealthDoctorProfile($pdo, $doctorId);

// ── Everything on this screen is read live from the database ─────
$data  = telehealthDoctorDashboardData($pdo, $doctorId);
$stats = $data['stats'];
$today = $data['today'];
$next  = $data['next'];
$recent = $data['recent'];
$patients = $data['patients'];

$docName = $doc['name'];
$docSpec = $doc['specialty'];
$docRating = telehealthDoctorById($pdo, $doctorId)['rating'] ?? 0;

$greeting = (date('H') < 12) ? 'Good Morning' : ((date('H') < 17) ? 'Good Afternoon' : 'Good Evening');

/** Icon per consultation type. */
function dd_consult_icon(string $type): string
{
    return [
        'video'     => 'fa-video',
        'audio'     => 'fa-phone-alt',
        'chat'      => 'fa-comment-dots',
        'in_person' => 'fa-user-md',
    ][$type] ?? 'fa-stethoscope';
}

/** Human label for a consultation type. */
function dd_consult_label(string $type): string
{
    return [
        'video'     => 'Video',
        'audio'     => 'Audio',
        'chat'      => 'Chat',
        'in_person' => 'Clinic',
    ][$type] ?? 'Consult';
}

/** Queue row accent by consultation status. */
function dd_queue_style(string $status): string
{
    return [
        'completed'   => 'background:#F0FDF4; border-left:4px solid var(--clr-success); opacity:0.75;',
        'in_progress' => 'background:#FEF9C3; border-left:4px solid var(--clr-warning);',
        'confirmed'   => 'background:var(--clr-primary-light); border-left:4px solid var(--clr-primary);',
    ][$status] ?? 'background:#F8FAFC; border-left:4px solid var(--clr-border);';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | KSR GURU JI Doctor Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <style>
        body {
            background: var(--clr-bg-main);
            padding-bottom: 40px;
            font-family: 'Inter', system-ui, sans-serif;
            margin: 0;
        }

        /* Doctor navbar — deep clinical ink */
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

        .doc-nav .nav-call {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--clr-success);
            color: #fff;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.84rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .doc-nav .nav-call:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .dashboard-grid {
            max-width: 1400px;
            margin: 32px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 28px;
            align-items: start;
        }

        /* ── Header card ───────────────────────────────── */
        .dash-header {
            background: var(--grad-surface);
            padding: 28px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .dh-greet {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--clr-primary-darker);
            margin-bottom: 6px;
        }

        .dh-sub {
            color: var(--clr-text-secondary);
            font-size: 0.98rem;
            font-weight: 500;
        }

        .dh-profile {
            display: flex;
            align-items: center;
            gap: 14px;
            background: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
        }

        .dh-avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--grad-accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .dh-name {
            font-weight: 700;
            color: var(--clr-text-primary);
            font-size: 1.02rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dh-spec {
            font-weight: 600;
            color: var(--clr-text-secondary);
            font-size: 0.86rem;
        }

        /* ── Metrics ───────────────────────────────────── */
        .metrics-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .m-card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border);
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .m-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--clr-accent);
        }

        .mc-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .mc-val {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            line-height: 1.1;
        }

        .mc-label {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* ── Next consultation hero ────────────────────── */
        .hero-consult {
            background: var(--grad-accent);
            color: #fff;
            border-radius: var(--radius-xl);
            padding: 30px;
            box-shadow: 0 18px 40px -20px rgba(79, 70, 229, 0.7);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .hero-consult::after {
            content: '\f0f0';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -24px;
            bottom: -40px;
            font-size: 11rem;
            color: rgba(255, 255, 255, 0.07);
        }

        .hero-consult>* {
            position: relative;
            z-index: 1;
        }

        .hc-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.16);
            padding: 7px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.82rem;
            margin-bottom: 16px;
        }

        .hc-pat {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .hc-meta {
            font-size: 0.98rem;
            opacity: 0.88;
            margin-bottom: 22px;
        }

        .hc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 26px;
            background: rgba(0, 0, 0, 0.2);
            padding: 16px 22px;
            border-radius: var(--radius-md);
        }

        .hc-k {
            font-size: 0.74rem;
            font-weight: 800;
            opacity: 0.8;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .hc-v {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .hc-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .hc-btn {
            background: #fff;
            color: var(--clr-accent-dark);
            padding: 12px 24px;
            border-radius: var(--radius-full);
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .hc-btn:hover {
            transform: translateY(-2px);
            background: #EEF2FF;
        }

        .hc-btn-outline {
            background: transparent;
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, 0.5);
        }

        .hc-btn-outline:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        /* ── Content boxes ─────────────────────────────── */
        .c-box {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            padding: 22px;
            margin-bottom: 20px;
        }

        .cb-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--clr-primary-darker);
            flex-wrap: wrap;
        }

        /* ── Queue rows ────────────────────────────────── */
        .queue-row {
            display: grid;
            grid-template-columns: 74px 1fr 120px 108px 116px;
            align-items: center;
            gap: 12px;
            padding: 14px 12px;
            border-bottom: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .queue-row:hover {
            background: #F7FDFC;
            transform: translateX(3px);
        }

        .queue-row:last-child {
            border-bottom: none;
        }

        .qr-time {
            font-weight: 800;
            color: var(--clr-text-primary);
            font-variant-numeric: tabular-nums;
            font-size: 1rem;
        }

        .qr-pat {
            font-weight: 700;
            color: var(--clr-text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .qr-pat span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .qr-type {
            color: var(--clr-text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── Search ────────────────────────────────────── */
        .search-bar {
            position: relative;
            margin-bottom: 20px;
        }

        .search-bar input {
            width: 100%;
            border: 1.5px solid var(--clr-border);
            padding: 14px 18px 14px 48px;
            border-radius: var(--radius-full);
            font-size: 0.98rem;
            font-family: inherit;
            font-weight: 500;
            background: white;
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 4px var(--clr-primary-light);
        }

        .search-bar i {
            position: absolute;
            left: 22px;
            top: 16px;
            color: var(--clr-text-muted);
            font-size: 1.05rem;
        }

        /* ── Stat lines ────────────────────────────────── */
        .stat-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px dashed var(--clr-border);
        }

        .stat-line:last-child {
            border-bottom: none;
        }

        .sl-label {
            font-weight: 600;
            color: var(--clr-text-secondary);
            font-size: 0.9rem;
        }

        .sl-val {
            font-weight: 800;
            color: var(--clr-text-primary);
        }

        /* ── Patient mini list ─────────────────────────── */
        .pt-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--clr-border);
            background: #FBFCFD;
            margin-bottom: 9px;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }

        .pt-row:hover {
            border-color: var(--clr-primary);
            background: var(--clr-primary-light);
        }

        .pt-avatar {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--clr-primary-light);
            color: var(--clr-primary-darker);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .pt-name {
            font-weight: 700;
            font-size: 0.92rem;
        }

        .pt-meta {
            font-size: 0.78rem;
            color: var(--clr-text-secondary);
            font-weight: 500;
        }

        /* ── Empty state ───────────────────────────────── */
        .empty {
            text-align: center;
            padding: 30px 16px;
            color: var(--clr-text-secondary);
        }

        .empty i {
            font-size: 1.8rem;
            color: var(--clr-text-muted);
            margin-bottom: 10px;
            display: block;
        }

        .empty p {
            font-size: 0.88rem;
            margin: 0;
        }

        @media (max-width: 1100px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .metrics-bar {
                grid-template-columns: 1fr 1fr;
            }

            .hc-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .queue-row {
                grid-template-columns: 64px 1fr;
                row-gap: 8px;
            }

            .queue-row .qr-type,
            .queue-row .pill {
                grid-column: 2;
                justify-self: start;
            }
        }

        @media (max-width: 560px) {
            .metrics-bar {
                grid-template-columns: 1fr;
            }

            .dh-greet {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php $ksr_active = '';
    $ksr_depth = 0;
    include __DIR__ . '/partials/brandbar.php'; ?>

    <nav class="navbar doc-nav">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; height:100%;">
            <div style="display:flex; align-items:center; gap:32px;">
                <a href="doctor-dashboard.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> DOCTOR PORTAL</a>
                <div class="navbar-links" style="display:flex; gap:22px;">
                    <a href="doctor-dashboard.php" class="active" style="font-weight:600;"><i class="fas fa-th-large"></i> Dashboard</a>
                    <a href="doctor-appointments.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-calendar-check"></i> Appointments</a>
                    <a href="doctor-patients.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-users"></i> Patients</a>
                    <a href="doctor-prescriptions.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-file-prescription"></i> Prescriptions</a>
                    <a href="build-prescription.php" style="font-weight:600; text-decoration:none;"><i class="fas fa-plus-circle"></i> New Rx</a>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:18px;">
                <a href="tel:<?php echo h(TELEHEALTH_AUDIO_CALL_NUMBER); ?>" class="nav-call">
                    <i class="fas fa-phone-alt"></i> <?php echo h(TELEHEALTH_AUDIO_CALL_NUMBER); ?>
                </a>
                <a href="index.php" style="color:#9FB3BE; font-size:1.05rem; text-decoration:none; font-weight:600;"><i class="fas fa-house"></i></a>
                <a href="notifications.php" style="color:#9FB3BE; font-size:1.1rem;"><i class="fas fa-bell"></i></a>
                <a href="doctor-profile-settings.php" class="profile-badge" style="padding:9px 15px; border-radius:20px; text-decoration:none; font-weight:600;">
                    <i class="fas fa-user-md"></i> <?php echo h($docName); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="dashboard-grid">

        <!-- LEFT: clinical workflow -->
        <div>

            <div class="dash-header">
                <div>
                    <h1 class="dh-greet"><?php echo h($greeting); ?>, <?php echo h($docName); ?> 👋</h1>
                    <p class="dh-sub">Here is your clinical overview for today.</p>
                </div>
                <div class="dh-profile">
                    <div class="dh-avatar"><i class="fas fa-user-md"></i></div>
                    <div>
                        <div class="dh-name">
                            <?php echo h($docName); ?>
                            <i class="fas fa-check-circle" style="color:var(--clr-success); font-size:0.85rem;"></i>
                        </div>
                        <div class="dh-spec"><?php echo h($docSpec); ?></div>
                        <div style="margin-top:6px;">
                            <span class="pill pill-ok"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Available</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live metrics -->
            <div class="metrics-bar">
                <a href="doctor-appointments.php" class="m-card" style="text-decoration:none;">
                    <div class="mc-icon" style="background:var(--clr-primary-light); color:var(--clr-primary);"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="mc-val"><?php echo (int)$stats['appointments']; ?></div>
                        <div class="mc-label">Appointments</div>
                    </div>
                </a>
                <a href="doctor-patients.php" class="m-card" style="text-decoration:none;">
                    <div class="mc-icon" style="background:var(--clr-success-light); color:#047857;"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="mc-val"><?php echo (int)$stats['patients']; ?></div>
                        <div class="mc-label">Patients</div>
                    </div>
                </a>
                <a href="doctor-appointments.php" class="m-card" style="text-decoration:none;">
                    <div class="mc-icon" style="background:var(--clr-warning-light); color:#92400E;"><i class="fas fa-stethoscope"></i></div>
                    <div>
                        <div class="mc-val"><?php echo (int)$stats['consultations']; ?></div>
                        <div class="mc-label">Consultations</div>
                    </div>
                </a>
                <a href="doctor-prescriptions.php" class="m-card" style="text-decoration:none;">
                    <div class="mc-icon" style="background:#F3E8FF; color:#7E22CE;"><i class="fas fa-pills"></i></div>
                    <div>
                        <div class="mc-val"><?php echo (int)$stats['prescriptions']; ?></div>
                        <div class="mc-label">Prescriptions</div>
                    </div>
                </a>
            </div>

            <!-- Next consultation -->
            <?php if ($next): ?>
                <?php
                $nType   = (string)($next['consultation_type'] ?? 'video');
                $nStatus = telehealthStatusTone((string)($next['status'] ?? 'requested'));
                $nPatientId = (int)($next['patient_id'] ?? 0);
                ?>
                <div class="hero-consult">
                    <div class="hc-badge">
                        <i class="fas fa-circle" style="font-size:0.5rem; color:#A5F3FC;"></i> UPCOMING PATIENT
                    </div>
                    <div class="hc-pat">
                        <i class="fas fa-user-circle"></i>
                        <?php echo h($next['patient_name'] ?: 'Unnamed patient'); ?>
                        <span class="pill pill-<?php echo h($nStatus[1]); ?>"><?php echo h($nStatus[0]); ?></span>
                    </div>
                    <div class="hc-meta">
                        <?php echo $next['age'] !== null ? 'Age ' . (int)$next['age'] : 'Age not recorded'; ?>
                        &middot; <?php echo h($next['gender'] ?: 'Not specified'); ?>
                        &middot; <?php echo h($next['mobile_number'] ?: 'No mobile on file'); ?>
                    </div>

                    <div class="hc-grid">
                        <div>
                            <div class="hc-k">Time</div>
                            <div class="hc-v"><i class="far fa-clock"></i> <?php echo h(telehealthWhen($next['scheduled_at'] ?? null, 'Slot pending')); ?></div>
                        </div>
                        <div>
                            <div class="hc-k">Format</div>
                            <div class="hc-v"><i class="fas <?php echo dd_consult_icon($nType); ?>"></i> <?php echo h(dd_consult_label($nType)); ?> Consultation</div>
                        </div>
                        <div>
                            <div class="hc-k">Payment</div>
                            <div class="hc-v"><?php echo h(telehealthStatusTone((string)($next['payment_status'] ?? 'pending'))[0]); ?></div>
                        </div>
                    </div>

                    <div class="hc-actions">
                        <a href="doctor-consultation-room.php?consultation=<?php echo (int)($next['id'] ?? 0); ?>" class="hc-btn">
                            <i class="fas fa-sign-in-alt"></i> START CONSULTATION
                        </a>
                        <a href="doctor-patients.php?patient=<?php echo $nPatientId; ?>" class="hc-btn hc-btn-outline">
                            <i class="fas fa-id-card"></i> View Patient Profile
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="c-box">
                    <div class="empty">
                        <i class="far fa-calendar-check"></i>
                        <p>No upcoming consultations scheduled. Patients appear here as soon as they book.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Today's queue -->
            <div class="c-box">
                <div class="cb-head">
                    <div><i class="fas fa-list"></i> Today's Appointment Queue</div>
                    <a href="doctor-appointments.php" style="font-size:0.83rem; text-decoration:none; color:var(--clr-primary); font-weight:700;">
                        View Calendar
                    </a>
                </div>

                <?php if ($today): ?>
                    <?php foreach ($today as $q): ?>
                        <?php
                        $qType   = (string)($q['consultation_type'] ?? 'video');
                        $qStatus = telehealthStatusTone((string)($q['status'] ?? 'requested'));
                        $qTime   = !empty($q['scheduled_at']) ? date('g:i A', strtotime((string)$q['scheduled_at'])) : '--:--';
                        ?>
                        <a href="doctor-patients.php?patient=<?php echo (int)($q['patient_id'] ?? 0); ?>" class="queue-row" style="<?php echo dd_queue_style((string)($q['status'] ?? '')); ?>">
                            <div class="qr-time"><?php echo h($qTime); ?></div>
                            <div class="qr-pat">
                                <i class="fas fa-user" style="color:var(--clr-text-muted); font-size:0.85rem;"></i>
                                <span><?php echo h($q['patient_name'] ?: 'Unnamed patient'); ?></span>
                            </div>
                            <div class="qr-type"><i class="fas <?php echo dd_consult_icon($qType); ?>"></i> <?php echo h(dd_consult_label($qType)); ?></div>
                            <div class="qr-type"><i class="fas fa-hashtag"></i> <?php echo h($q['id']); ?></div>
                            <div><span class="pill pill-<?php echo h($qStatus[1]); ?>"><?php echo h($qStatus[0]); ?></span></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">
                        <i class="far fa-calendar"></i>
                        <p>Nothing scheduled for today.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent activity -->
            <?php if ($recent): ?>
                <div class="c-box">
                    <div class="cb-head">
                        <div><i class="fas fa-clock-rotate-left"></i> Recent Consultation History</div>
                        <a href="doctor-appointments.php" style="font-size:0.83rem; text-decoration:none; color:var(--clr-primary); font-weight:700;">
                            See all
                        </a>
                    </div>
                    <?php foreach (array_slice($recent, 0, 8) as $r): ?>
                        <?php
                        $rStatus = telehealthStatusTone((string)($r['status'] ?? ''));
                        $rType   = (string)($r['consultation_type'] ?? 'video');
                        ?>
                        <a href="doctor-patients.php?patient=<?php echo (int)($r['patient_id'] ?? 0); ?>" class="queue-row">
                            <div class="qr-time" style="font-size:0.82rem;"><?php echo h(telehealthWhen($r['scheduled_at'] ?? $r['created_at'] ?? null, '—')); ?></div>
                            <div class="qr-pat">
                                <i class="fas fa-user" style="color:var(--clr-text-muted); font-size:0.85rem;"></i>
                                <span><?php echo h($r['patient_name'] ?: 'Unnamed patient'); ?></span>
                            </div>
                            <div class="qr-type"><i class="fas <?php echo dd_consult_icon($rType); ?>"></i> <?php echo h(dd_consult_label($rType)); ?></div>
                            <div class="qr-type"><i class="far fa-comment-dots"></i> Notes</div>
                            <div><span class="pill pill-<?php echo h($rStatus[1]); ?>"><?php echo h($rStatus[0]); ?></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT: search, stats, patients -->
        <div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search Patients by Name or ID...">
            </div>

            <div class="c-box">
                <div class="cb-head"><i class="fas fa-chart-line"></i> Quick Stats</div>
                <div class="stat-line">
                    <div class="sl-label">Pending Reviews</div>
                    <div class="sl-val" style="color:var(--clr-danger);"><?php echo (int)$stats['pending']; ?></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Consults Today</div>
                    <div class="sl-val"><?php echo (int)$stats['today']; ?></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Total Earnings</div>
                    <div class="sl-val" style="color:var(--clr-success);"><?php echo h(telehealthMoney($stats['earnings'])); ?></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Patient Rating</div>
                    <div class="sl-val"><i class="fas fa-star" style="color:#EAB308;"></i> <?php echo h(number_format((float)$docRating, 1)); ?>/5</div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Experience</div>
                    <div class="sl-val"><?php echo (int)$doc['experience_years']; ?> yrs</div>
                </div>
            </div>

            <div class="c-box">
                <div class="cb-head">
                    <div><i class="fas fa-users"></i> My Patients</div>
                    <a href="doctor-patients.php" style="font-size:0.83rem; text-decoration:none; color:var(--clr-primary); font-weight:700;">
                        View all <?php echo count($patients); ?>
                    </a>
                </div>

                <?php if ($patients): ?>
                    <?php foreach (array_slice($patients, 0, 6) as $pt): ?>
                        <a href="doctor-patients.php?patient=<?php echo (int)$pt['id']; ?>" class="pt-row">
                            <div class="pt-avatar"><?php echo h(strtoupper(substr((string)($pt['patient_name'] ?? 'P'), 0, 1) ?: 'P')); ?></div>
                            <div style="flex:1; min-width:0;">
                                <div class="pt-name"><?php echo h($pt['patient_name'] ?: 'Unnamed patient'); ?></div>
                                <div class="pt-meta">
                                    <?php echo $pt['age'] !== null ? (int)$pt['age'] . ' yrs' : 'Age n/a'; ?>
                                    &middot; <?php echo (int)$pt['consultation_count']; ?> consults
                                </div>
                            </div>
                            <i class="fas fa-chevron-right" style="color:var(--clr-text-muted); font-size:0.8rem;"></i>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">
                        <i class="fas fa-user-slash"></i>
                        <p>No patients linked to your account yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-box">
                <div class="cb-head"><i class="fas fa-user-shield"></i> Verification</div>
                <div class="stat-line">
                    <div class="sl-label">Licence Status</div>
                    <div><span class="pill pill-<?php echo h(telehealthStatusTone((string)$doc['verification_status'])[1]); ?>">
                            <?php echo h($doc['verification_status']); ?>
                        </span></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Registration No.</div>
                    <div class="sl-val" style="font-size:0.85rem;"><?php echo h($doc['registration_number'] ?: '—'); ?></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Authority</div>
                    <div class="sl-val" style="font-size:0.85rem;"><?php echo h($doc['registration_authority'] ?: '—'); ?></div>
                </div>
                <div class="stat-line">
                    <div class="sl-label">Qualification</div>
                    <div class="sl-val" style="font-size:0.85rem; text-align:right;"><?php echo h($doc['qualification'] ?: '—'); ?></div>
                </div>
            </div>
        </div>

    </div>
</body>

</html>