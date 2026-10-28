<?php
require_once 'helper.php';
// Live Administrator Telemetry Protocol
$pdo = getAssistDB();

// Execute Live Telemetry Queries safely
$safeCount = function(string $sql, int $default = 0) use ($pdo): int {
    try {
        $val = $pdo->query($sql)->fetchColumn();
        return $val !== false ? (int)$val : $default;
    } catch (Throwable $e) {
        return $default;
    }
};

$t_patients = $safeCount("SELECT COUNT(*) FROM patients");
$t_doctors = $safeCount("SELECT COUNT(*) FROM doctors");
$t_pharmacies = 1; // Base assumption for MVP
$t_drivers = 3;    // Base assumption for MVP

$t_appointments = $safeCount("SELECT COUNT(*) FROM appointments");
if ($t_appointments === 0) {
    // Fallback to consultations if appointments table is empty/absent
    $t_appointments = $safeCount("SELECT COUNT(*) FROM consultations");
}

$t_prescriptions = $safeCount("SELECT COUNT(*) FROM prescriptions");
$t_prescriptions_pending = $safeCount("SELECT COUNT(*) FROM prescriptions WHERE verification_status = 'MANUAL_REVIEW'");
$t_prescriptions_verified = max(0, $t_prescriptions - $t_prescriptions_pending);

$t_orders = $safeCount("SELECT COUNT(*) FROM orders");
$t_orders_pending = $safeCount("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$t_orders_shipped = $safeCount("SELECT COUNT(*) FROM orders WHERE status = 'shipped'");

try {
    $t_revenue = $pdo->query("SELECT SUM(total) FROM orders")->fetchColumn();
    $t_revenue = $t_revenue !== false && $t_revenue !== null ? (float)$t_revenue : 0;
} catch (Throwable $e) {
    $t_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(15, 23, 42, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --shadow-glass: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
            --radius-md: 12px;
            --radius-xl: 20px;
        }

        body {
            background: #020617;
            margin: 0;
            padding-bottom: 40px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #F8FAFC;
            font-family: 'Inter', system-ui, sans-serif;
            background-image:
                radial-gradient(circle at 15% 50%, rgba(37, 99, 235, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.05), transparent 25%);
        }

        /* Admin Navbar - Dark Glass Theme */
        .admin-nav {
            background: rgba(2, 6, 23, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 0 16px;
            box-shadow: var(--shadow-glass);
        }

        .admin-nav .navbar-brand {
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .admin-nav-links {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            padding: 12px 0;
        }

        .admin-nav-links::-webkit-scrollbar {
            display: none;
        }

        .admin-nav-links a {
            color: #94A3B8;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: 0.3s;
        }

        .admin-nav-links a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .admin-nav-links a.active {
            color: white;
            background: rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(37, 99, 235, 0.3);
            box-shadow: inset 0 0 10px rgba(37, 99, 235, 0.1);
        }

        .badge-danger {
            background: #EF4444;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 900;
            margin-left: 4px;
        }

        .app-container {
            display: flex;
            flex: 1;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
            padding: 24px;
            gap: 24px;
        }

        /* Main Layout */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-width: 0;
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .kpi-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-glass);
            display: flex;
            align-items: center;
            border-left: 4px solid var(--clr-primary);
            transition: 0.3s;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(255, 255, 255, 0.2)
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-right: 16px;
            background: rgba(255, 255, 255, 0.05);
            color: #94A3B8;
        }

        .kpi-data {
            flex: 1;
        }

        .kpi-val {
            font-size: 1.6rem;
            font-weight: 900;
            color: white;
            line-height: 1.2;
        }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: #94A3B8;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .kpi-trend {
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up {
            color: #34D399;
        }

        /* Panel Blocks */
        .panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--radius-md);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glass);
            overflow: hidden;
        }

        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-weight: 800;
            font-size: 1.05rem;
            color: #60A5FA;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-body {
            padding: 20px;
        }

        /* System Alerts */
        .alert-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 6px;
            margin-bottom: 12px;
            border-left-width: 4px;
            background: rgba(0, 0, 0, 0.2);
        }

        .alert-high {
            border-left-color: #EF4444;
        }

        .alert-med {
            border-left-color: #F59E0B;
        }

        .alert-low {
            border-left-color: #3B82F6;
        }

        .btn-alert {
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: transparent;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            color: white;
            transition: 0.2s;
        }

        .btn-alert:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Live Operations Board */
        .ops-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        .ops-col {
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.1);
        }

        .ops-col-head {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            font-size: 0.8rem;
            font-weight: 800;
            color: #94A3B8;
            text-transform: uppercase;
            text-align: center;
            border-bottom: 1px solid var(--glass-border);
        }

        .ops-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ops-list li {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
            cursor: pointer;
        }

        .ops-list li:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .ops-list li:last-child {
            border-bottom: none;
        }

        .ops-num {
            font-weight: 900;
            color: white;
        }

        /* Emergency Controls */
        .emergency-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .btn-emergency {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
            padding: 12px;
            border-radius: 6px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-emergency:hover {
            background: #EF4444;
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        @media (max-width: 992px) {
            .app-container {
                flex-direction: column !important;
                padding: 16px !important;
            }
            .app-container > div:last-child {
                width: 100% !important;
            }
        }

        @media (max-width: 768px) {
            html, body {
                overflow-x: hidden;
            }
            .admin-nav > div {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
            }
            .admin-nav-links {
                width: 100% !important;
                overflow-x: auto !important;
            }
            .main-content > div:first-child {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px !important;
            }
            .ops-grid {
                grid-template-columns: 1fr !important;
            }
            .alert-row {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 8px !important;
            }
        }

        @media (max-width: 480px) {
            .app-container {
                padding: 10px !important;
            }
            .kpi-grid {
                grid-template-columns: 1fr !important;
            }
            .emergency-grid {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 360px) {
            .kpi-val {
                font-size: 1.3rem !important;
            }
            .panel-head {
                padding: 12px 14px !important;
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

    <div class="admin-nav">
        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; width: 100%;">
            <div class="navbar-brand">
                <i class="fas fa-shield-alt text-primary"></i> DOCTOR ASSIST ADMIN
            </div>
            <div class="admin-nav-links">
                <a href="admin-dashboard.php" class="active">Dashboard</a>
                <a href="master-patient-index.php">Users</a>
                <a href="find-doctor.php">Doctors <span class="badge-danger">7</span></a>
                <a href="pharmacy-dashboard.php">Pharmacy</a>
                <a href="logistics-command.php">Logistics</a>
                <a href="finance-center.php">Finance <span class="badge-danger">⚠</span></a>
                <a href="system-configuration.php">Settings</a>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="color: #10B981; font-weight: 800; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-circle" style="font-size: 0.5rem; animation: pulse 2s infinite;"></i> Operational
                </div>
                <a href="admin-dashboard.php" style="color: white; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; background: #1E293B; padding: 6px 12px; border-radius: 6px;">
                    <i class="fas fa-user-shield"></i> Super Admin
                </a>
            </div>
        </div>
    </div>

    <div class="app-container">

        <div class="main-content">

            <!-- Headers -->
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div>
                    <h1 style="margin:0 0 4px 0; font-size:1.8rem; font-weight:900; color:var(--clr-primary-dark);">Command Center</h1>
                    <div style="color:var(--clr-text-secondary); font-weight:600;">Complete platform operations, governance, security & monitoring</div>
                </div>
                <div style="font-size:0.85rem; font-weight:700; color:var(--clr-text-secondary);"><i class="fas fa-clock"></i> Last updated: 10:42 AM <button class="btn-alert" style="margin-left:12px;"><i class="fas fa-sync-alt"></i></button></div>
            </div>

            <!-- 8 KPI Metrics -->
            <div class="kpi-grid">
                <div class="kpi-card" style="border-left-color: #3B82F6;">
                    <div class="kpi-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fas fa-users"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Patients</div>
                        <div class="kpi-val"><?php echo number_format($t_patients); ?></div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Live Sync</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #2563EB;">
                    <div class="kpi-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fas fa-user-md"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Doctors</div>
                        <div class="kpi-val"><?php echo number_format($t_doctors); ?></div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Live Sync</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #16A34A;">
                    <div class="kpi-icon" style="background: #F0FDF4; color: #16A34A;"><i class="fas fa-prescription-bottle-alt"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Pharmacies</div>
                        <div class="kpi-val">12</div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Static Base</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #0F172A;">
                    <div class="kpi-icon" style="background: #F1F5F9; color: #0F172A;"><i class="fas fa-motorcycle"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Drivers</div>
                        <div class="kpi-val">28</div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Static Base</div>
                    </div>
                </div>

                <div class="kpi-card" style="border-left-color: #8B5CF6;">
                    <div class="kpi-icon" style="background: #F5F3FF; color: #8B5CF6;"><i class="fas fa-calendar-check"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Appointments</div>
                        <div class="kpi-val"><?php echo number_format($t_appointments); ?></div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Live Sync</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #EA580C;">
                    <div class="kpi-icon" style="background: #FFF7ED; color: #EA580C;"><i class="fas fa-box-open"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Pharmacy Orders</div>
                        <div class="kpi-val"><?php echo number_format($t_orders); ?></div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Live Sync</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #059669;">
                    <div class="kpi-icon" style="background: #ECFDF5; color: #059669;"><i class="fas fa-rupee-sign"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Revenue (Live)</div>
                        <div class="kpi-val">₹<?php echo number_format($t_revenue, 2); ?></div>
                        <div class="kpi-trend trend-up"><i class="fas fa-satellite-dish"></i> Gross GMV</div>
                    </div>
                </div>
                <div class="kpi-card" style="border-left-color: #DC2626;">
                    <div class="kpi-icon" style="background: #FEF2F2; color: #DC2626;"><i class="fas fa-file-signature"></i></div>
                    <div class="kpi-data">
                        <div class="kpi-label">Prescriptions</div>
                        <div class="kpi-val" style="color:white;"><?php echo number_format($t_prescriptions); ?></div>
                        <div class="kpi-trend" style="color:#64748B;"><i class="fas fa-satellite-dish"></i> Active Log</div>
                    </div>
                </div>
            </div>

            <!-- System Alerts -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-bell"></i> System Alerts</div>
                    <span class="badge-danger">3 Critical</span>
                </div>
                <div class="panel-body">
                    <div class="alert-row alert-high">
                        <div>
                            <span style="font-weight:900; color:#EF4444; font-size:0.8rem; margin-right:12px;">HIGH RISK</span>
                            <span style="font-weight:700;">Payment webhook payload failures detected (4 in last hr)</span>
                        </div>
                        <button class="btn-alert">Investigate</button>
                    </div>
                    <div class="alert-row alert-med">
                        <div>
                            <span style="font-weight:900; color:#F59E0B; font-size:0.8rem; margin-right:12px;">MEDIUM ALERT</span>
                            <span style="font-weight:700;">18 prescriptions waiting for pharmacist review timeout</span>
                        </div>
                        <button class="btn-alert">View Queue</button>
                    </div>
                    <div class="alert-row alert-low">
                        <div>
                            <span style="font-weight:900; color:#3B82F6; font-size:0.8rem; margin-right:12px;">OPERATIONAL ALERT</span>
                            <span style="font-weight:700;">3 pharmacies have reported out-of-stock anomalies</span>
                        </div>
                        <button class="btn-alert">Inventory</button>
                    </div>
                </div>
            </div>

            <!-- Live Operations Board -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-project-diagram"></i> Live Operations Pipeline</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">End-to-End Tracking</div>
                </div>
                <div class="panel-body">
                    <div class="ops-grid">
                        <div class="ops-col">
                            <div class="ops-col-head"><i class="fas fa-calendar-check text-primary"></i> Appointments</div>
                            <ul class="ops-list">
                                <li><span>Total Base</span> <span class="ops-num"><?php echo $t_appointments; ?></span></li>
                                <li><span>Waiting Room</span> <span class="ops-num text-primary" style="color:var(--clr-primary);">0</span></li>
                                <li><span>No-Show</span> <span class="ops-num" style="color:#F59E0B;">0</span></li>
                                <li><span>Cancelled</span> <span class="ops-num">0</span></li>
                            </ul>
                        </div>

                        <div class="ops-col">
                            <div class="ops-col-head"><i class="fas fa-video" style="color:#8B5CF6;"></i> Consultations</div>
                            <ul class="ops-list">
                                <li><span>Active LIVE</span> <span class="ops-num" style="color:#10B981;"><i class="fas fa-circle" style="font-size:0.6rem; animation:pulse 2s infinite;"></i> 0</span></li>
                                <li><span>Connecting</span> <span class="ops-num">0</span></li>
                                <li><span>Failed/Dropped</span> <span class="ops-num" style="color:#EF4444;">0</span></li>
                                <li><span>Escalations</span> <span class="ops-num" style="color:#EF4444;">0</span></li>
                            </ul>
                        </div>

                        <div class="ops-col">
                            <div class="ops-col-head"><i class="fas fa-file-signature text-primary"></i> Prescriptions</div>
                            <ul class="ops-list">
                                <li><span>Pending Review</span> <span class="ops-num" style="color:#F59E0B;"><?php echo $t_prescriptions_pending; ?></span></li>
                                <li><span>Verified</span> <span class="ops-num" style="color:#16A34A;"><?php echo $t_prescriptions_verified; ?></span></li>
                                <li><span>Total Scanned</span> <span class="ops-num text-primary"><?php echo $t_prescriptions; ?></span></li>
                                <li><span>Rejected</span> <span class="ops-num" style="color:#DC2626;">0</span></li>
                            </ul>
                        </div>

                        <div class="ops-col">
                            <div class="ops-col-head"><i class="fas fa-box-open" style="color:#EA580C;"></i> Pharmacy Fulfillment</div>
                            <ul class="ops-list">
                                <li><span>Total Orders</span> <span class="ops-num"><?php echo $t_orders; ?></span></li>
                                <li><span>Pending Setup</span> <span class="ops-num text-primary"><?php echo $t_orders_pending; ?></span></li>
                                <li><span>Shipped</span> <span class="ops-num" style="color:#16A34A;"><?php echo $t_orders_shipped; ?></span></li>
                                <li><span>Delivered</span> <span class="ops-num">0</span></li>
                            </ul>
                        </div>

                        <div class="ops-col">
                            <div class="ops-col-head"><i class="fas fa-motorcycle" style="color:#0F172A;"></i> Delivery Logistics</div>
                            <ul class="ops-list">
                                <li><span>Active Fleets</span> <span class="ops-num">126</span></li>
                                <li><span>Out for Delivery</span> <span class="ops-num text-primary">89</span></li>
                                <li><span>Attempted</span> <span class="ops-num" style="color:#F59E0B;">7</span></li>
                                <li><span>Returns Req.</span> <span class="ops-num" style="color:#DC2626;">4</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Sidebar -->
        <div style="width: 320px; display: flex; flex-direction: column; gap: 24px;">

            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-users-cog"></i> Admin Approvals</div>
                </div>
                <div class="panel-body" style="padding: 12px;">
                    <div style="display:flex; justify-content:space-between; padding:12px; border-bottom:1px solid var(--clr-border);">
                        <div style="font-weight:700; font-size:0.9rem;">Doctor Verification</div>
                        <div style="font-weight:900; color:#EF4444;">27</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:12px; border-bottom:1px solid var(--clr-border);">
                        <div style="font-weight:700; font-size:0.9rem;">Pharmacy Onboarding</div>
                        <div style="font-weight:900; color:#F59E0B;">4</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:12px; border-bottom:1px solid var(--clr-border);">
                        <div style="font-weight:700; font-size:0.9rem;">Driver Background</div>
                        <div style="font-weight:900; color:var(--clr-text-primary);">14</div>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:12px;">
                        <div style="font-weight:700; font-size:0.9rem;">Patient Refunds</div>
                        <div style="font-weight:900; color:var(--clr-text-primary);">18</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-sliders-h"></i> Operational Overrides</div>
                </div>
                <div class="panel-body" style="padding: 16px;">
                    <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase; margin-bottom:12px;">Active Policies</div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                        <span style="font-weight:700;">Prescription Validity</span>
                        <span style="font-weight:800;">6 Months</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                        <span style="font-weight:700;">Platform Video Fee</span>
                        <span style="font-weight:800;">₹15</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-size:0.9rem;">
                        <span style="font-weight:700;">Routing Scheme</span>
                        <span style="font-weight:800;">Age-Based (v4)</span>
                    </div>

                    <button class="btn-alert" style="width:100%; border-color:var(--clr-primary); color:var(--clr-primary);"><i class="fas fa-cog"></i> Configure Platform</button>
                </div>
            </div>

            <div class="panel" style="border-color:#FECACA;">
                <div class="panel-head" style="background:#FEF2F2;">
                    <div class="panel-title" style="color:#DC2626;"><i class="fas fa-power-off"></i> Emergency Controls</div>
                </div>
                <div class="panel-body">
                    <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-bottom:16px;">These actions require elevated Super Admin authorization and biometric override.</div>

                    <div class="emergency-grid">
                        <button class="btn-emergency" title="Pause all new appointments"><i class="fas fa-calendar-times"></i> Bookings</button>
                        <button class="btn-emergency" title="Pause pharmacy ingestion"><i class="fas fa-store-slash"></i> Orders</button>
                        <button class="btn-emergency" title="Disable driver assignment"><i class="fas fa-truck-loading"></i> Logistics</button>
                        <button class="btn-emergency" title="Halt payouts"><i class="fas fa-hand-holding-usd"></i> Finance</button>
                    </div>
                </div>
            </div>

        </div>

    </div>

</body>

</html>