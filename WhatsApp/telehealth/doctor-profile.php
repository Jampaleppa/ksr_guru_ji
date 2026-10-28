<?php
require_once 'helper.php';

$pdo = getAssistDB();
$docId = $_GET['id'] ?? 1;

// Fetch Doctor Info
$stmt = $pdo->prepare("SELECT d.*, u.name as user_name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$docId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback logic for demo purposes if doctor not in DB
if (!$doctor) {
    if ($docId == 2) {
        $doctor = [
            'id' => 2,
            'doctor_name' => 'DR. D C Rathod',
            'specialty' => 'Nurology with Paralieses Specialist',
            'experience_years' => 3,
            'qualification' => 'MBBS, DO',
            'clinic_hospital_details' => 'KSR Main Clinic',
            'verification_status' => 'VERIFIED',
            'languages' => 'Kannada • English',
            'status' => 'Available Today',
            'status_class' => 'status-today',
            'about' => 'DR. D C Rathod is a trusted Nurology with Paralieses care specialist focused on elderly care, managing chronic conditions, and improving the quality of life for senior citizens.'
        ];
    } else {
        $doctor = [
            'id' => 1,
            'doctor_name' => 'Dr.ShivukumarRathod',
            'specialty' => 'pardiatrics',
            'experience_years' => 3,
            'qualification' => 'MBBS,MD',
            'clinic_hospital_details' => 'KMC:131985 (Max time: 5 mins)',
            'verification_status' => 'VERIFIED',
            'languages' => 'Kannada • English • Hindi',
            'status' => 'Available Now',
            'status_class' => 'status-now',
            'about' => 'Dr.ShivukumarRathod is an experienced pediatrician specializing in child health. Video/audio consultations have a strict max duration of 5 minutes.'
        ];
    }
} else {
    // Fill in default UI fields if missing in DB
    $doctor['languages'] = $doctor['languages'] ?? 'English • Hindi • Kannada';
    $doctor['status'] = $doctor['status'] ?? 'Available Now';
    $doctor['status_class'] = $doctor['status_class'] ?? 'status-now';
    $doctor['about'] = $doctor['about'] ?? 'Highly experienced medical professional dedicated to providing the best patient-centric care.';
}

$rating = 4.8;
$reviewsCount = 250;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($doctor['doctor_name']); ?> - Profile | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --clr-status-now: #16A34A;
            --clr-status-today: #2563EB;
            --clr-status-limited: #F59E0B;
            --clr-status-offline: #64748B;
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

        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 32px;
            margin-bottom: 80px;
            position: relative;
            align-items: start;
        }

        /* Profile Header */
        .profile-header-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 32px;
            display: flex;
            gap: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-sm);
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: var(--radius-md);
            background: var(--clr-primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--clr-primary);
            flex-shrink: 0;
            position: relative;
        }

        .profile-info {
            flex: 1;
        }

        .doc-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--clr-primary-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .verified-badge {
            color: var(--clr-status-now);
            font-size: 1.2rem;
        }

        .doc-specialty {
            color: var(--clr-text-secondary);
            font-size: 1.1rem;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .doc-stats {
            display: flex;
            gap: 24px;
            color: var(--clr-text-primary);
            font-size: 1rem;
            margin-bottom: 24px;
        }

        .doc-stats i {
            margin-right: 8px;
        }

        .status-badge-inline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-now-bg {
            background: #DCFCE7;
            color: #16A34A;
        }

        .status-today-bg {
            background: #DBEAFE;
            color: #2563EB;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Tabs */
        .profile-tabs {
            display: flex;
            gap: 32px;
            border-bottom: 1px solid var(--clr-border);
            margin-bottom: 32px;
        }

        .tab {
            padding: 16px 0;
            font-weight: 600;
            color: var(--clr-text-secondary);
            cursor: pointer;
            position: relative;
        }

        .tab.active {
            color: var(--clr-primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--clr-primary);
            border-radius: 3px 3px 0 0;
        }

        /* Overview Section */
        .section-block {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 32px;
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 12px;
        }

        .about-text {
            font-size: 1rem;
            color: var(--clr-text-primary);
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 32px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-icon {
            margin-top: 4px;
            color: var(--clr-primary);
            font-size: 1.1rem;
        }

        .info-content {
            color: var(--clr-text-primary);
        }

        /* Consultation Options */
        .consult-select-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 32px;
        }

        .consult-box {
            border: 2px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 24px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            position: relative;
            overflow: hidden;
        }

        .consult-box:hover {
            border-color: var(--clr-primary-light);
            background: #F8FAFC;
        }

        .consult-box.selected {
            border-color: var(--clr-primary);
            background: var(--clr-primary-light);
        }

        .consult-box.selected::after {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 16px;
            right: 16px;
            color: var(--clr-primary);
            font-size: 1.25rem;
        }

        .box-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 1.05rem;
        }

        .box-header i {
            font-size: 1.25rem;
        }

        .box-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
            margin-bottom: 8px;
        }

        .box-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            border-top: 1px dashed var(--clr-border);
            padding-top: 12px;
            margin-top: 8px;
        }

        .btn-select-type {
            width: 100%;
            margin-top: 20px;
            text-align: center;
            font-weight: 600;
            color: var(--clr-primary);
            border: 1px solid var(--clr-primary);
            border-radius: var(--radius-sm);
            padding: 10px;
            transition: 0.3s;
        }

        .consult-box.selected .btn-select-type {
            background: var(--clr-primary);
            color: white;
        }

        /* Slots */
        .date-slider {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            scrollbar-width: none;
        }

        .date-slider::-webkit-scrollbar {
            display: none;
        }

        .date-chip {
            padding: 12px 20px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            min-width: 100px;
            transition: 0.2s;
        }

        .date-chip.active {
            background: var(--clr-primary);
            color: white;
            border-color: var(--clr-primary);
        }

        .date-chip span {
            display: block;
            font-size: 0.8rem;
            margin-top: 4px;
            opacity: 0.8;
        }

        .time-section {
            margin-bottom: 24px;
        }

        .time-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .time-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .time-slot {
            padding: 10px 16px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            color: var(--clr-text-primary);
            transition: 0.2s;
        }

        .time-slot:hover {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        .time-slot.selected {
            background: var(--clr-primary);
            color: white;
            border-color: var(--clr-primary);
        }

        .time-slot.disabled {
            background: var(--clr-bg-main);
            color: #94A3B8;
            cursor: not-allowed;
            text-decoration: line-through;
            border-color: var(--clr-border);
        }

        /* Sticky Summary */
        .sticky-summary {
            position: sticky;
            top: 100px;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            padding: 24px;
            box-shadow: var(--shadow-lg);
        }

        .summary-title {
            font-size: 1.1rem;
            font-weight: 700;
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .summary-doc {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
        }

        .summary-row.total {
            border-top: 1px solid var(--clr-border);
            padding-top: 16px;
            font-weight: 700;
            color: var(--clr-text-primary);
            font-size: 1.15rem;
        }

        .summary-dt {
            background: var(--clr-bg-main);
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
        }

        .summary-dt p {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            color: var(--clr-text-primary);
            font-weight: 500;
        }

        .summary-dt p:last-child {
            margin-bottom: 0;
        }

        .summary-dt i {
            color: var(--clr-primary);
        }

        /* Reviews */
        .rating-overview {
            display: flex;
            align-items: center;
            gap: 32px;
            margin-bottom: 32px;
        }

        .rating-big {
            font-size: 3rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 1;
        }

        .rating-big span {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--clr-text-secondary);
            margin-top: 8px;
        }

        .rating-bars {
            flex: 1;
        }

        .rating-bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: var(--clr-text-secondary);
        }

        .rating-bar-track {
            flex: 1;
            height: 8px;
            background: var(--clr-border);
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: #F59E0B;
            border-radius: 4px;
        }

        .review-card {
            padding: 24px 0;
            border-bottom: 1px solid var(--clr-border);
        }

        .review-card:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--clr-text-primary);
        }

        .review-stars {
            color: #F59E0B;
            font-size: 0.85rem;
        }

        .review-text {
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
            font-style: italic;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .review-verified {
            font-size: 0.8rem;
            color: #16A34A;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Mobile Bottom Nav/Action */
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
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .sticky-summary {
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

        @media (max-width: 768px) {
            .profile-header-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .doc-stats {
                flex-direction: column;
                gap: 12px;
                align-items: center;
            }

            .consult-select-grid {
                grid-template-columns: 1fr;
            }

            .rating-overview {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .profile-header-card {
                padding: 18px 14px;
                border-radius: var(--radius-md);
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2.2rem;
            }

            .doc-name {
                font-size: 1.4rem;
            }

            .doc-specialty {
                font-size: 0.95rem;
            }

            .doc-meta {
                flex-direction: column;
                gap: 6px;
                font-size: 0.85rem;
            }

            .doc-badges {
                flex-wrap: wrap;
                justify-content: center;
            }

            .consult-section {
                padding: 16px 14px;
            }

            .time-slot {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .date-chip {
                padding: 10px 14px;
                min-width: 70px;
            }

            .mobile-bottom-action {
                padding: 12px 16px;
            }

            .mobile-bottom-action .btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }

            .rating-big {
                font-size: 2.4rem;
            }
        }

        @media (max-width: 360px) {
            .doc-name {
                font-size: 1.25rem;
            }

            .date-chip {
                padding: 8px 10px;
                min-width: 60px;
            }

            .time-slot {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .mobile-bottom-action {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }

            .mobile-bottom-action .btn {
                width: 100%;
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

    <div class="container" style="padding-top: 30px;">
        <a href="find-doctor.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Doctors</a>

        <div class="profile-layout">
            <!-- Main Content -->
            <div>
                <!-- Header Card -->
                <div class="profile-header-card">
                    <div class="profile-avatar"><i class="fas fa-user-md"></i></div>
                    <div class="profile-info">
                        <h1 class="doc-name">
                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                            <?php if ($doctor['verification_status'] === 'VERIFIED'): ?>
                                <i class="fas fa-check-circle verified-badge" title="VERIFIED"></i>
                                <span style="font-size:0.8rem; color:var(--clr-status-now); font-weight:700;">VERIFIED</span>
                            <?php endif; ?>
                        </h1>
                        <div class="doc-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>

                        <div class="doc-stats">
                            <div><span style="color:#F59E0B;"><i class="fas fa-star"></i> <?php echo $rating; ?> Rating</span></div>
                            <div><i class="fas fa-comment"></i> <?php echo $reviewsCount; ?> Reviews</div>
                            <div><i class="fas fa-history"></i> <?php echo $doctor['experience_years']; ?>+ Years</div>
                        </div>

                        <div class="status-badge-inline status-now-bg">
                            <div class="status-dot" style="background:currentColor;"></div>
                            <?php echo $doctor['status']; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="profile-tabs">
                    <div class="tab active" onclick="switchTab('overview')">Overview</div>
                    <div class="tab" onclick="switchTab('experience')">Experience</div>
                    <div class="tab" onclick="switchTab('reviews')">Reviews</div>
                </div>

                <!-- Overview Section -->
                <div id="tab-overview">
                    <div class="section-block">
                        <h2 class="section-title">About The Doctor</h2>
                        <p class="about-text"><?php echo htmlspecialchars($doctor['about']); ?></p>

                        <h2 class="section-title">Education & Qualifications</h2>
                        <div class="info-list">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-graduation-cap"></i></div>
                                <div class="info-content" style="font-weight: 500;"><?php echo htmlspecialchars($doctor['qualification']); ?></div>
                            </div>
                        </div>

                        <h2 class="section-title">Languages</h2>
                        <div class="info-list">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-globe"></i></div>
                                <div class="info-content" style="font-weight: 500; font-size: 1.05rem; letter-spacing: 0.05em;"><?php echo htmlspecialchars($doctor['languages']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Consultation Selection -->
                    <div class="section-block" id="booking-section">
                        <h2 class="section-title">Choose Your Consultation</h2>

                        <div class="consult-select-grid">
                            <div class="consult-box" id="box-audio" onclick="selectConsultation('audio')">
                                <div class="box-header"><i class="fas fa-headphones-alt" style="color:var(--clr-primary);"></i> AUDIO CONSULTATION</div>
                                <div style="font-size: 0.85rem; color: var(--clr-text-secondary); margin-bottom: 16px; line-height: 1.5; margin-top:-8px;">Clear, secure voice calling. Best for follow-ups and rapid consultations.</div>
                                <div class="box-row"><span>Doctor Fee</span><span>₹100</span></div>
                                <div class="box-row"><span>Platform Fee</span><span>₹15</span></div>
                                <div class="box-total"><span>Total</span><span>₹115</span></div>
                                <div class="btn-select-type" id="btn-audio">Select Audio</div>
                            </div>

                            <div class="consult-box selected" id="box-video" onclick="selectConsultation('video')">
                                <div class="box-header"><i class="fas fa-video" style="color:var(--clr-primary);"></i> VIDEO CONSULTATION</div>
                                <div style="font-size: 0.85rem; color: var(--clr-text-secondary); margin-bottom: 16px; line-height: 1.5; margin-top:-8px;">Face-to-face encrypted video call with the doctor for detailed diagnosis.</div>
                                <div class="box-row"><span>Doctor Fee</span><span>₹125</span></div>
                                <div class="box-row"><span>Platform Fee</span><span>₹15</span></div>
                                <div class="box-total"><span>Total</span><span>₹140</span></div>
                                <div class="btn-select-type" id="btn-video">Select Video</div>
                            </div>
                        </div>

                        <!-- Date & Time Slots -->
                        <h2 class="section-title" style="margin-top: 48px;">Select Available Slot</h2>

                        <div class="date-slider">
                            <div class="date-chip active" onclick="selectDate(this, 'Today, Sep 06')">Today <span>Sep 06</span></div>
                            <div class="date-chip" onclick="selectDate(this, 'Tomorrow, Sep 07')">Tomorrow <span>Sep 07</span></div>
                            <div class="date-chip" onclick="selectDate(this, 'Wed, Sep 08')">Wed <span>Sep 08</span></div>
                            <div class="date-chip" onclick="selectDate(this, 'Thu, Sep 09')">Thu <span>Sep 09</span></div>
                        </div>

                        <div class="time-section">
                            <div class="time-label"><i class="fas fa-sun"></i> Morning</div>
                            <div class="time-grid">
                                <div class="time-slot disabled">09:00 AM</div>
                                <div class="time-slot" onclick="selectTime(this, '09:30 AM')">09:30 AM</div>
                                <div class="time-slot" onclick="selectTime(this, '10:00 AM')">10:00 AM</div>
                            </div>
                        </div>

                        <div class="time-section">
                            <div class="time-label"><i class="fas fa-cloud-sun"></i> Afternoon</div>
                            <div class="time-grid">
                                <div class="time-slot" onclick="selectTime(this, '02:00 PM')">02:00 PM</div>
                                <div class="time-slot selected" onclick="selectTime(this, '02:30 PM')">02:30 PM</div>
                                <div class="time-slot" onclick="selectTime(this, '03:00 PM')">03:00 PM</div>
                            </div>
                        </div>

                        <div class="time-section">
                            <div class="time-label"><i class="fas fa-moon"></i> Evening</div>
                            <div class="time-grid">
                                <div class="time-slot" onclick="selectTime(this, '06:00 PM')">06:00 PM</div>
                                <div class="time-slot" onclick="selectTime(this, '06:30 PM')">06:30 PM</div>
                                <div class="time-slot disabled">07:00 PM</div>
                            </div>
                        </div>

                    </div>

                    <!-- Reviews -->
                    <div class="section-block" id="reviews-block">
                        <h2 class="section-title">Patient Reviews</h2>

                        <div class="rating-overview">
                            <div class="rating-big">
                                4.8
                                <span>Based on verified reviews</span>
                            </div>
                            <div class="rating-bars">
                                <div class="rating-bar-row">
                                    <span style="width:40px;">5 ★</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 85%;"></div>
                                    </div>
                                    <span style="width:30px; text-align:right;">85%</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span style="width:40px;">4 ★</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 10%;"></div>
                                    </div>
                                    <span style="width:30px; text-align:right;">10%</span>
                                </div>
                                <div class="rating-bar-row">
                                    <span style="width:40px;">3 ★</span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="width: 3%;"></div>
                                    </div>
                                    <span style="width:30px; text-align:right;">3%</span>
                                </div>
                            </div>
                        </div>

                        <div class="review-card">
                            <div class="review-header">
                                <span class="reviewer-name">Rahul Sharma</span>
                                <span class="review-stars">★★★★★</span>
                            </div>
                            <p class="review-text">"Doctor explained the problem clearly and the consultation was very helpful. Excellent experience via video call."</p>
                            <div class="review-verified"><i class="fas fa-check-circle"></i> Verified Consultation</div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Sticky Summary -->
            <div>
                <div class="sticky-summary">
                    <div class="summary-title">APPOINTMENT SUMMARY</div>

                    <div class="summary-doc">
                        <div style="width: 50px; height: 50px; border-radius: 8px; background: var(--clr-primary-light); color:var(--clr-primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem;"><i class="fas fa-user-md"></i></div>
                        <div>
                            <div style="font-weight: 700; color: var(--clr-primary-dark); font-size: 1.05rem;"><?php echo htmlspecialchars($doctor['doctor_name']); ?></div>
                            <div style="font-size: 0.9rem; color: var(--clr-text-secondary);"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
                        </div>
                    </div>

                    <div class="summary-dt">
                        <p><i class="fas fa-video" id="sum-type-icon"></i> <span id="sum-type-txt">Video Consultation</span></p>
                        <p><i class="fas fa-calendar-alt"></i> <span id="sum-date">Today, Sep 06</span></p>
                        <p><i class="fas fa-clock"></i> <span id="sum-time">02:30 PM</span></p>
                    </div>

                    <div class="summary-row">
                        <span>Doctor Fee</span>
                        <span id="sum-doc-fee">₹125</span>
                    </div>
                    <div class="summary-row">
                        <span>Platform Fee</span>
                        <span>₹15</span>
                    </div>
                    <div class="summary-row total">
                        <span>TOTAL</span>
                        <span id="sum-total">₹140</span>
                    </div>

                    <button class="btn-primary" style="width:100%; margin-top: 24px; justify-content:center; padding:16px;" onclick="continueToBooking()">Continue to Booking <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

        </div>
    </div>

    <!-- Mobile Bottom Action -->
    <div class="mobile-bottom-action">
        <div>
            <div style="font-size: 0.85rem; color: var(--clr-text-secondary);">Total Payable</div>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--clr-primary-dark);" id="mob-total">₹140</div>
        </div>
        <button class="btn-primary" onclick="continueToBooking()">Book Now <i class="fas fa-arrow-right"></i></button>
    </div>

    <!-- JS Logic for interaction -->
    <script>
        let currentType = 'video';
        let currentDate = 'Today, Sep 06';
        let currentTime = '02:30 PM';
        const docId = <?php echo $doctor['id']; ?>;

        function selectConsultation(type) {
            currentType = type;

            // Update UI Boxes
            document.getElementById('box-audio').classList.remove('selected');
            document.getElementById('box-video').classList.remove('selected');

            if (type === 'audio') {
                document.getElementById('box-audio').classList.add('selected');
                document.getElementById('sum-type-icon').className = 'fas fa-headphones-alt';
                document.getElementById('sum-type-txt').innerText = 'Audio Consultation';
                document.getElementById('sum-doc-fee').innerText = '₹100';
                document.getElementById('sum-total').innerText = '₹115';
                document.getElementById('mob-total').innerText = '₹115';
            } else {
                document.getElementById('box-video').classList.add('selected');
                document.getElementById('sum-type-icon').className = 'fas fa-video';
                document.getElementById('sum-type-txt').innerText = 'Video Consultation';
                document.getElementById('sum-doc-fee').innerText = '₹125';
                document.getElementById('sum-total').innerText = '₹140';
                document.getElementById('mob-total').innerText = '₹140';
            }
        }

        function selectDate(element, dateStr) {
            document.querySelectorAll('.date-chip').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            currentDate = dateStr;
            document.getElementById('sum-date').innerText = currentDate;
        }

        function selectTime(element, timeStr) {
            if (element.classList.contains('disabled')) return;
            document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            currentTime = timeStr;
            document.getElementById('sum-time').innerText = currentTime;
        }

        function continueToBooking() {
            const queryParams = new URLSearchParams({
                doc_id: docId,
                type: currentType,
                date: currentDate,
                time: currentTime
            });
            window.location.href = 'patient-details.php?' + queryParams.toString();
        }
    </script>

</body>

</html>