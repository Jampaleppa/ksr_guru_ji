<?php
require_once 'helper.php';

$pdo = getAssistDB();

// Handle search and filtering
$searchQuery = $_GET['q'] ?? '';
$specialtyFilter = $_GET['specialty'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$langFilter = $_GET['language'] ?? '';

// Fetch all verified/pending doctors for display
$sql = "SELECT d.*, u.name as user_name FROM doctors d LEFT JOIN users u ON d.user_id = u.id WHERE (d.doctor_name LIKE '%Shivukumar%' OR d.doctor_name LIKE '%Rathod%') AND d.doctor_name NOT LIKE '%Anjali%' AND d.doctor_name NOT LIKE '%Kumar%' AND d.doctor_name NOT LIKE '%Priya%'";
$conditions = [];
$params = [];

if ($searchQuery) {
    $conditions[] = "(d.doctor_name LIKE ? OR d.specialty LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if ($specialtyFilter) {
    $conditions[] = "d.specialty = ?";
    $params[] = $specialtyFilter;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback to simulated data if none exist natively yet
if (count($doctors) === 0) {
    $doctors = [
        [
            'id' => 1,
            'doctor_name' => 'Dr. Shivukumar Rathod',
            'specialty' => 'Pediatrics & General Medicine',
            'experience_years' => 5,
            'qualification' => 'MBBS, MD',
            'clinic_hospital_details' => 'KMC: 131985 (Max time: 5 mins)',
            'verification_status' => 'VERIFIED',
            'languages' => 'Kannada • English • Hindi',
            'status' => 'Available Now',
            'status_class' => 'status-now'
        ],
        [
            'id' => 2,
            'doctor_name' => 'Dr. D C Rathod',
            'specialty' => 'Neuro Care & Cardiology',
            'experience_years' => 3,
            'qualification' => 'MBBS',
            'clinic_hospital_details' => 'KMC: 10824',
            'verification_status' => 'VERIFIED',
            'languages' => 'Kannada • English • Hindi',
            'status' => 'Available Today',
            'status_class' => 'status-today'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctor - Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-xl: 20px;
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
            
            --clr-status-now: #16A34A;
            --clr-status-today: #2563EB;
            --clr-status-limited: #F59E0B;
            --clr-status-offline: #64748B;
        }
        
        body { 
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); 
            font-family: 'Inter', sans-serif; 
        }

        /* Override/Add new Nav elements */
        .navbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(16px); border-bottom: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); position: sticky; top: 0; z-index: 1000;}
        .navbar-nav { gap: 16px; }
        .nav-icon { font-size: 1.25rem; color: var(--clr-text-secondary); margin-left: 8px; cursor: pointer; transition: 0.3s; }
        .nav-icon:hover { color: var(--clr-primary); }

        .hero-banner {
            background: linear-gradient(135deg, var(--clr-primary-dark) 0%, var(--clr-primary) 100%);
            color: white;
            padding: 80px 0 100px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 0; right: 0;
            height: 100px;
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); 
            transform: skewY(-2deg);
        }

        .hero-banner h1 { color: white; font-size: 3rem; margin-bottom: 16px; font-weight: 900; letter-spacing: -0.5px; position:relative; z-index:2;}
        .hero-banner p { color: rgba(255,255,255,0.9); font-size: 1.15rem; max-width: 600px; margin: 0 auto 40px; position:relative; z-index:2;}

        .search-complex {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            padding: 24px;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.8);
            color: var(--clr-text-primary);
        }

        .search-main {
            display: flex;
            align-items: center;
            border: 1px dashed rgba(0,0,0,0.1);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 16px;
            background: rgba(255,255,255,0.6);
            transition: 0.3s;
        }
        .search-main:focus-within { border-color: var(--clr-primary-light); background: white; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);}
        .search-main i { color: var(--clr-text-secondary); margin-right: 16px; font-size: 1.35rem; }
        .search-main input { border: none; outline: none; width: 100%; font-size: 1.15rem; background: transparent; font-weight: 600; color: var(--clr-text-primary);}

        .search-filters-row {
            display: flex;
            gap: 16px;
        }
        
        .search-select-group {
            flex: 1;
            display: flex;
            align-items: center;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--radius-md);
            padding: 16px;
            background: rgba(248, 250, 252, 0.8);
            transition: 0.3s;
        }
        .search-select-group:focus-within { background: white; border-color: var(--clr-border); box-shadow: 0 4px 12px rgba(0,0,0,0.05);}
        .search-select-group i { color: var(--clr-primary); margin-right: 12px; font-size: 1.1rem;}
        .search-select-group select, .search-select-group input { border: none; outline: none; background: transparent; width: 100%; font-size: 1rem; font-weight: 600; color: var(--clr-text-secondary); cursor: pointer;}
        .search-select-group input::placeholder { color: var(--clr-text-secondary); font-weight: 600;}

        .btn-search-large {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 16px 36px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 1.05rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            box-shadow: 0 8px 20px rgba(37,99,235,0.3);
        }
        .btn-search-large:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(37,99,235,0.4); }

        /* Quick Specialties */
        .quick-specialties {
            display: flex;
            justify-content: center;
            gap: 24px;
            padding: 60px 0 40px;
            flex-wrap: wrap;
        }
        
        .spec-pill {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 24px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            transition: 0.3s;
            min-width: 140px;
            box-shadow: var(--shadow-glass);
        }
        
        .spec-pill .icon {
            font-size: 2.25rem;
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.8) 0%, rgba(255,255,255,1) 100%);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05), inset 0 2px 4px rgba(255,255,255,0.8);
        }

        .spec-pill:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: rgba(255,255,255,0.9);
            background: white;
        }

        .spec-pill:hover .icon {
            background: var(--clr-primary-light);
            color: var(--clr-primary);
            box-shadow: 0 10px 20px rgba(37,99,235,0.2);
            transform: scale(1.1);
        }

        .spec-pill span {
            font-weight: 800;
            color: var(--clr-text-primary);
            font-size: 1rem;
        }

        /* Layout Grid */
        .main-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
            margin-bottom: 80px;
        }

        /* Advanced Filters */
        .adv-filters {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            padding: 32px;
            height: fit-content;
            box-shadow: var(--shadow-glass);
        }

        .adv-filters-header {
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 28px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--clr-primary-dark);
            border-bottom: 1px dashed rgba(0,0,0,0.1);
            padding-bottom: 16px;
        }

        .filter-group { margin-bottom: 28px; }
        .filter-group h4 { font-size: 1.05rem; margin-bottom: 16px; color: var(--clr-text-primary); font-weight: 800;}
        .filter-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--clr-text-secondary);
            font-size: 0.95rem;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .filter-item:hover { color: var(--clr-text-primary); }
        .filter-item input { width: 20px; height: 20px; accent-color: var(--clr-primary); cursor: pointer;}
        
        .filter-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 24px;
            border-top: 1px dashed rgba(0,0,0,0.1);
        }
        
        .btn-filter-reset {
            background: transparent;
            color: var(--clr-text-secondary);
            border: 1px solid rgba(0,0,0,0.1);
            padding: 14px;
            border-radius: var(--radius-md);
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-filter-reset:hover { background: rgba(0,0,0,0.05); color: var(--clr-text-primary); }
        .btn-filter-apply {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: var(--radius-md);
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .btn-filter-apply:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(37,99,235,0.4);}

        /* Doctor Result Cards */
        .doc-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            padding: 32px;
            margin-bottom: 24px;
            transition: 0.3s;
            box-shadow: var(--shadow-glass);
        }
        .doc-card:hover {
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            border-color: rgba(255,255,255,0.9);
            transform: translateY(-2px);
            background: white;
        }

        .doc-card-top {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .doc-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.8) 0%, rgba(255,255,255,1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: var(--clr-primary-light);
            position: relative;
            box-shadow: 0 8px 16px rgba(0,0,0,0.05), inset 0 2px 4px rgba(255,255,255,0.8);
            border: 2px solid white;
        }
        
        .status-badge {
            position: absolute;
            top: 5px; right: 5px;
            width: 20px; height: 20px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-now { background: var(--clr-status-now); }
        .status-today { background: var(--clr-status-today); }
        .status-limited { background: var(--clr-status-limited); }
        .status-offline { background: var(--clr-status-offline); }

        .doc-info { flex: 1; }
        .doc-info h3 {
            font-size: 1.5rem;
            color: var(--clr-text-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
        }
        .verified-badge { color: var(--clr-status-now); font-size: 1.15rem; }
        .doc-specialty { color: var(--clr-primary); margin-bottom: 16px; font-weight: 800; font-size: 1.05rem; letter-spacing: 0.5px;}
        
        .doc-stats {
            display: flex;
            gap: 32px;
            color: var(--clr-text-primary);
            font-size: 1rem;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .doc-stats i { margin-right: 8px; }

        .doc-meta { font-size: 0.95rem; color: var(--clr-text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-weight: 600;}
        .doc-meta i { font-size: 1.1rem; width: 20px; text-align: center; color: var(--clr-text-secondary); }
        
        .doc-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(0,0,0,0.1) 50%, transparent 100%);
            margin: 28px 0;
        }

        .doc-booking-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .consultation-options {
            display: flex;
            gap: 40px;
        }
        
        .consult-fee {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .consult-fee .icon {
            background: rgba(238, 242, 255, 0.8);
            width: 48px; height: 48px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--clr-primary);
            font-size: 1.25rem;
            box-shadow: 0 4px 10px rgba(37,99,235,0.1), inset 0 2px 4px rgba(255,255,255,0.8);
        }
        .consult-fee-details {
            display: flex; flex-direction: column;
        }
        .consult-type-name { font-weight: 800; font-size: 1rem; color: var(--clr-text-primary); margin-bottom: 2px;}
        .consult-price { font-size: 1.1rem; font-weight: 900; color: var(--clr-primary-dark); }
        .consult-platform-fee { font-size: 0.75rem; color: var(--clr-text-secondary); font-weight: 600;}

        .doc-actions {
            display: flex;
            gap: 16px;
        }

        .btn-secondary {
            background: transparent;
            color: var(--clr-primary);
            border: 2px solid var(--clr-primary);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-secondary:hover { background: rgba(37,99,235,0.05); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.1);}

        /* Mobile Bottom Nav */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0; left: 0; width: 100%;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--glass-border);
            z-index: 1000;
            padding: 12px 16px 16px;
            justify-content: space-around;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
        }
        .mobile-nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            color: var(--clr-text-secondary); font-size: 0.75rem; font-weight: 700;
            text-decoration: none;
            transition: 0.3s;
        }
        .mobile-nav-item i { font-size: 1.35rem; }
        .mobile-nav-item.active { color: var(--clr-primary); }
        .mobile-nav-item:hover { color: var(--clr-primary-dark); }

        @media (max-width: 900px) {
            .main-layout { grid-template-columns: 1fr; }
            .adv-filters { display: none; } /* Could be put in a drawer */
            .search-filters-row { flex-direction: column; }
            .doc-card-top { flex-direction: column; align-items: center; text-align: center; }
            .doc-info { display: flex; flex-direction: column; align-items: center; }
            .doc-stats { justify-content: center; flex-wrap: wrap; gap: 16px; }
            .doc-meta { justify-content: center; }
            .doc-booking-section { flex-direction: column; align-items: stretch; gap: 24px;}
            .consultation-options { flex-direction: column; gap: 16px; align-items: stretch;}
            .consult-fee { justify-content: center;}
            .doc-actions { flex-direction: column; }
        }

        @media (max-width: 768px) {
            .navbar-nav { display: none; }
            .mobile-bottom-nav { display: flex; }
            body { padding-bottom: 80px; }
            .hero-banner h1 { font-size: 2.25rem; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<!-- Professional Header -->
<nav class="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand">
            <i class="fas fa-heartbeat"></i> DOCTOR ASSIST
        </a>
        <ul class="navbar-nav" style="margin-left: 24px; flex: 1;">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="find-doctor.php" class="nav-link" style="color:var(--clr-primary); font-weight:600;">Find Doctor</a></li>
            <li><a href="my-appointments.php" class="nav-link">My Appointments</a></li>
            <li><a href="pharmacy.php" class="nav-link">Pharmacy</a></li>
            <li><a href="my-prescriptions.php" class="nav-link">My Prescriptions</a></li>
        </ul>
        <div class="navbar-actions" style="display:flex; align-items:center;">
            <i class="fas fa-bell nav-icon"></i>
            <i class="fas fa-user-circle nav-icon" style="margin-left:20px; font-size: 1.5rem;" onclick="location.href='profile.php'"></i>
        </div>
    </div>
</nav>

<!-- Page Hero -->
<div class="hero-banner">
    <div class="container">
        <h1>Find the Right Doctor for Your Health Needs</h1>
        <p>Search verified doctors by specialty, symptoms, experience, language, or availability.</p>
        
        <form class="search-complex" method="GET" action="find-doctor.php">
            <div class="search-main">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Doctor, Specialty or Symptoms..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="search-filters-row">
                <div class="search-select-group">
                    <i class="fas fa-map-marker-alt"></i>
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($locationFilter); ?>">
                </div>
                <div class="search-select-group">
                    <i class="fas fa-stethoscope"></i>
                    <select name="specialty">
                        <option value="">Specialty</option>
                        <option value="General Physician" <?php if($specialtyFilter=='General Physician') echo 'selected'; ?>>General Physician</option>
                        <option value="Cardiology" <?php if($specialtyFilter=='Cardiology') echo 'selected'; ?>>Cardiologist</option>
                        <option value="Dermatology" <?php if($specialtyFilter=='Dermatology') echo 'selected'; ?>>Dermatologist</option>
                        <option value="Pediatrics" <?php if($specialtyFilter=='Pediatrics') echo 'selected'; ?>>Pediatrician</option>
                    </select>
                </div>
                <div class="search-select-group">
                    <i class="fas fa-globe"></i>
                    <select name="language">
                        <option value="">Language</option>
                        <option value="English">English</option>
                        <option value="Kannada">Kannada</option>
                        <option value="Hindi">Hindi</option>
                    </select>
                </div>
                <button class="btn-search-large" type="submit"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Specialty Selection -->
<div class="container">
    <div class="quick-specialties">
        <div class="spec-pill" onclick="window.location='?specialty=Cardiology'">
            <div class="icon">❤️</div>
            <span>Cardiology</span>
        </div>
        <div class="spec-pill" onclick="window.location='?specialty=Neurology'">
            <div class="icon">🧠</div>
            <span>Neurology</span>
        </div>
        <div class="spec-pill" onclick="window.location='?specialty=Pediatrics'">
            <div class="icon">👶</div>
            <span>Pediatrics</span>
        </div>
        <div class="spec-pill" onclick="window.location='?specialty=Dermatology'">
            <div class="icon">🧴</div>
            <span>Dermatology</span>
        </div>
        <div class="spec-pill" onclick="window.location='?specialty=Eye Care'">
            <div class="icon">👁</div>
            <span>Eye Care</span>
        </div>
        <div class="spec-pill" onclick="window.location='?specialty=Dental'">
            <div class="icon">🦷</div>
            <span>Dental</span>
        </div>
    </div>
</div>

<div class="container main-layout">
    <!-- Advanced Filters -->
    <aside class="adv-filters">
        <div class="adv-filters-header">Filter Doctors</div>
        
        <form action="" method="GET">
            <div class="filter-group">
                <h4>Specialization</h4>
                <label class="filter-item"><input type="checkbox"> General Physician</label>
                <label class="filter-item"><input type="checkbox"> Cardiologist</label>
                <label class="filter-item"><input type="checkbox"> Dermatologist</label>
                <label class="filter-item"><input type="checkbox"> Pediatrician</label>
            </div>

            <div class="filter-group">
                <h4>Consultation Type</h4>
                <label class="filter-item"><input type="checkbox" checked> 📹 Video</label>
                <label class="filter-item"><input type="checkbox" checked> 🎧 Audio</label>
                <label class="filter-item"><input type="checkbox"> 💬 Chat</label>
            </div>

            <div class="filter-group">
                <h4>Experience</h4>
                <label class="filter-item"><input type="radio" name="exp" checked> Any Experience</label>
                <label class="filter-item"><input type="radio" name="exp"> 1–5 Years</label>
                <label class="filter-item"><input type="radio" name="exp"> 5–10 Years</label>
                <label class="filter-item"><input type="radio" name="exp"> 10+ Years</label>
            </div>

            <div class="filter-group">
                <h4>Language</h4>
                <label class="filter-item"><input type="checkbox" checked> English</label>
                <label class="filter-item"><input type="checkbox"> Kannada</label>
                <label class="filter-item"><input type="checkbox"> Hindi</label>
                <label class="filter-item"><input type="checkbox"> Telugu</label>
                <label class="filter-item"><input type="checkbox"> Tamil</label>
            </div>

            <div class="filter-group">
                <h4>Availability</h4>
                <label class="filter-item"><input type="checkbox"> Available Now</label>
                <label class="filter-item"><input type="checkbox"> Today</label>
                <label class="filter-item"><input type="checkbox"> Tomorrow</label>
            </div>

            <div class="filter-actions">
                <button type="reset" class="btn-filter-reset">Reset</button>
                <button type="submit" class="btn-filter-apply">Apply Filters</button>
            </div>
        </form>
    </aside>

    <!-- Doctor Result Cards -->
    <main>
        <div style="margin-bottom: 24px; color: var(--clr-text-secondary);">
            Showing <strong><?php echo count($doctors); ?></strong> results based on your search
        </div>

        <?php foreach ($doctors as $doc): ?>
            <?php 
                $exp = $doc['experience_years'] ?? rand(5, 20);
                $rating = (float)(rand(45, 50) / 10);
                $reviews = rand(50, 500);
                $qual = $doc['qualification'] ?? 'MBBS, MD';
                $langs = $doc['languages'] ?? 'Kannada • English • Hindi';
                $status = $doc['status'] ?? 'Available Now';
                $statusClass = $doc['status_class'] ?? 'status-now';
                $isVerified = ($doc['verification_status'] ?? 'VERIFIED') === 'VERIFIED';
            ?>
            <div class="doc-card">
                <div class="doc-card-top">
                    <div class="doc-photo">
                        <i class="fas fa-user-md"></i>
                        <div class="status-badge <?php echo $statusClass; ?>" title="<?php echo $status; ?>"></div>
                    </div>
                    <div class="doc-info">
                        <h3>
                            <?php echo htmlspecialchars($doc['doctor_name']); ?> 
                            <?php if ($isVerified): ?>
                                <i class="fas fa-check-circle verified-badge" title="VERIFIED"></i><span style="font-size:0.75rem; color:var(--clr-status-now); font-weight:700;">VERIFIED</span>
                            <?php endif; ?>
                        </h3>
                        <div class="doc-specialty"><?php echo htmlspecialchars($doc['specialty']); ?></div>
                        
                        <div class="doc-stats">
                            <div><span style="color:#F59E0B;"><i class="fas fa-star"></i> <?php echo $rating; ?></span> (<?php echo $reviews; ?> Reviews)</div>
                            <div><i class="fas fa-hospital-user"></i> <?php echo $exp; ?>+ Years Experience</div>
                        </div>

                        <div class="doc-meta"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($qual); ?></div>
                        <div class="doc-meta"><i class="fas fa-globe"></i> <?php echo htmlspecialchars($langs); ?></div>
                    </div>
                </div>

                <div class="doc-divider"></div>

                <div class="doc-booking-section">
                    <div class="consultation-options">
                        <div class="consult-fee">
                            <div class="icon"><i class="fas fa-headphones-alt"></i></div>
                            <div class="consult-fee-details">
                                <div class="consult-type-name">Audio Consultation</div>
                                <div class="consult-price">₹100</div>
                                <div class="consult-platform-fee">+ ₹15 Platform Fee</div>
                            </div>
                        </div>
                        <div class="consult-fee">
                            <div class="icon"><i class="fas fa-video"></i></div>
                            <div class="consult-fee-details">
                                <div class="consult-type-name">Video Consultation</div>
                                <div class="consult-price">₹125</div>
                                <div class="consult-platform-fee">+ ₹15 Platform Fee</div>
                            </div>
                        </div>
                    </div>

                    <div class="doc-actions">
                        <a href="doctor-profile.php?id=<?php echo $doc['id']; ?>" class="btn-secondary" style="border: 1px solid var(--clr-primary);">View Profile</a>
                        <a href="patient-details.php?doc_id=<?php echo $doc['id']; ?>" class="btn-primary">Book Appointment</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </main>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
    <a href="index.php" class="mobile-nav-item">
        <i class="fas fa-home"></i>
        Home
    </a>
    <a href="find-doctor.php" class="mobile-nav-item active">
        <i class="fas fa-search"></i>
        Doctor
    </a>
    <a href="my-appointments.php" class="mobile-nav-item">
        <i class="fas fa-calendar-alt"></i>
        Appts
    </a>
    <a href="pharmacy.php" class="mobile-nav-item">
        <i class="fas fa-pills"></i>
        Pharmacy
    </a>
    <a href="profile.php" class="mobile-nav-item">
        <i class="fas fa-user"></i>
        Profile
    </a>
</nav>

<!-- Footer -->
<footer class="footer" style="padding-bottom: 90px;">
    <!-- Reduced footer for brevity as per strict UI template -->
    <div class="container text-center" style="color:var(--clr-text-secondary); text-align:center; padding: 24px 0;">
        &copy; 2026 KSR GURU JI - Doctor Assist. Verified Medical Ecosystem.
    </div>
</footer>

</body>
</html>
