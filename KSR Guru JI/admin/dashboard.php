<?php
// ============================================================
// admin/dashboard.php - KSR GURU Admin Dashboard (Merged)
// ============================================================
// dashboard.php also hosts the Doctor Assist sub-pages, each of
// which is gated by its own permission (see inc/page_permissions.php).
$currentPage = (isset($_GET['page']) && $_GET['page'] !== '')
    ? preg_replace('/[^a-z_]/', '', strtolower((string) $_GET['page']))
    : 'dashboard';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// ============================================================
// --- DOCTOR ASSIST INTEGRATION HANDLERS & DATA ---
// ============================================================
$doctorActionMsg = '';
$doctorActionSuccess = false;

// Helper: Log Audit Event
if (!function_exists('logDoctorAuditEvent')) {
    function logDoctorAuditEvent(PDO $pdo, string $action, string $table, ?int $recordId, ?string $details): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table, record_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $stmt->execute([$userId, $action, $table, $recordId, $details, $ip, $ua]);
        } catch (Exception $e) {
            // Fail silently
        }
    }
}

// Helper: Simulate WhatsApp Notification
if (!function_exists('simulateDoctorWhatsApp')) {
    function simulateDoctorWhatsApp(PDO $pdo, ?int $userId, string $templateName, array $payload): bool {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications_log (user_id, notification_type, template_name, payload_json, dispatch_status) VALUES (?, 'WHATSAPP', ?, ?, 'SENT')");
            $stmt->execute([$userId, $templateName, json_encode($payload)]);
            $logId = $pdo->lastInsertId();
            
            $pdo->exec("UPDATE notifications_log SET dispatch_status = 'DELIVERED' WHERE id = $logId");
            logDoctorAuditEvent($pdo, 'WHATSAPP_SIMULATED', 'notifications_log', $logId, "Template: $templateName sent to user $userId");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// 1. Doctor status update
if (isset($_GET['verify_doctor_id']) && isset($_GET['set_status'])) {
    try {
        $docId = (int)$_GET['verify_doctor_id'];
        $newStatus = $_GET['set_status'];
        
        $allowed = ['PENDING', 'UNDER_REVIEW', 'VERIFIED', 'REJECTED', 'SUSPENDED'];
        if (in_array($newStatus, $allowed)) {
            $stmt = $pdo->prepare("UPDATE doctors SET verification_status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $docId]);

            // Get doctor name and user_id for notifications
            $stmtD = $pdo->prepare("SELECT user_id, doctor_name FROM doctors WHERE id = ?");
            $stmtD->execute([$docId]);
            $docObj = $stmtD->fetch();

            logDoctorAuditEvent($pdo, 'DOCTOR_VERIFICATION_CHANGE', 'doctors', $docId, "Dr. {$docObj['doctor_name']} certification state updated to $newStatus");
            
            // Dispatch WhatsApp Alert
            simulateDoctorWhatsApp($pdo, $docObj['user_id'], 'doctor_verification_result', [
                'doctor_name' => $docObj['doctor_name'],
                'verification_status' => $newStatus
            ]);

            $_SESSION['doctor_action_success'] = true;
            $_SESSION['doctor_action_msg'] = "Dr. {$docObj['doctor_name']} verification set to {$newStatus}. Doctor notified via WhatsApp.";
            
            header("Location: dashboard.php?page=doctors");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['doctor_action_success'] = false;
        $_SESSION['doctor_action_msg'] = "Verification shift failed: " . $e->getMessage();
        header("Location: dashboard.php?page=doctors");
        exit;
    }
}

// 2. Policy threshold update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_policy'])) {
    try {
        $days = (int)($_POST['threshold_days'] ?? 180);
        if ($days <= 0) {
            $_SESSION['doctor_action_success'] = false;
            $_SESSION['doctor_action_msg'] = 'Threshold must be at least 1 day.';
        } else {
            $stmtSet = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'prescription_recency_threshold_days'");
            $stmtSet->execute([$days]);
            
            logDoctorAuditEvent($pdo, 'CLINICAL_POLICY_UPDATED', 'settings', 0, "Updated prescription recency threshold to $days Days");
            
            $_SESSION['doctor_action_success'] = true;
            $_SESSION['doctor_action_msg'] = "Verification Threshold updated dynamically to {$days} Days.";
        }
        header("Location: dashboard.php?page=policies");
        exit;
    } catch (Exception $e) {
        $_SESSION['doctor_action_success'] = false;
        $_SESSION['doctor_action_msg'] = "Policy update failed: " . $e->getMessage();
        header("Location: dashboard.php?page=policies");
        exit;
    }
}

// 3. Add SKU to Doctor Assist medicine catalog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    try {
        $brand = trim($_POST['brand_name'] ?? '');
        $generic = trim($_POST['generic_name'] ?? '');
        $form = $_POST['dosage_form'] ?? 'Tablet';
        $strength = trim($_POST['strength'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');

        if (!$brand || !$generic || !$strength) {
            $_SESSION['doctor_action_success'] = false;
            $_SESSION['doctor_action_msg'] = 'Please complete all required fields for medicine catalog.';
        } else {
            $stmtM = $pdo->prepare("INSERT INTO medicines (brand_name, generic_name, dosage_form, strength, manufacturer) VALUES (?, ?, ?, ?, ?)");
            $stmtM->execute([$brand, $generic, $form, $strength, $manufacturer]);
            $medId = $pdo->lastInsertId();

            // Seed formulation table if necessary
            $stmtF = $pdo->prepare("INSERT INTO medicine_formulations (medicine_id, packaging_details, drug_schedule) VALUES (?, 'Blister strip of 10 tablets', 'Schedule H')");
            $stmtF->execute([$medId]);

            logDoctorAuditEvent($pdo, 'MEDICINE_CATALOG_NEW', 'medicines', $medId, "Added new SKU: $brand - $strength ($generic)");
            
            $_SESSION['doctor_action_success'] = true;
            $_SESSION['doctor_action_msg'] = "Medicine {$brand} ({$strength}) has been cataloged successfully.";
        }
        header("Location: dashboard.php?page=policies");
        exit;
    } catch (Exception $e) {
        $_SESSION['doctor_action_success'] = false;
        $_SESSION['doctor_action_msg'] = "Medicine addition failed: " . $e->getMessage();
        header("Location: dashboard.php?page=policies");
        exit;
    }
}

// Retrieve action session states
if (isset($_SESSION['doctor_action_msg'])) {
    $doctorActionMsg = $_SESSION['doctor_action_msg'];
    $doctorActionSuccess = $_SESSION['doctor_action_success'] ?? false;
    unset($_SESSION['doctor_action_msg'], $_SESSION['doctor_action_success']);
}

// Fetch current policy threshold setting
$settingsStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'prescription_recency_threshold_days'");
$settingsStmt->execute();
$currentThreshold = (int)($settingsStmt->fetchColumn() ?: 180);

// Fetch doctors — wrapped in try/catch (table may not exist yet)
try { $doctorsList = $pdo->query("SELECT * FROM doctors ORDER BY verification_status DESC")->fetchAll(); } catch(Exception $e) { $doctorsList = []; }

// Fetch medicines master list
try { $medicinesList = $pdo->query("SELECT m.* FROM medicines m ORDER BY m.brand_name ASC LIMIT 50")->fetchAll(); } catch(Exception $e) { $medicinesList = []; }

// Fetch patients
try { $patientsList = $pdo->query("SELECT p.*, u.email FROM patients p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 50")->fetchAll(); } catch(Exception $e) { $patientsList = []; }

// Fetch consultations (newest 30)
try { $consultationsList = $pdo->query("SELECT c.*, d.doctor_name, p.patient_name FROM consultations c LEFT JOIN doctors d ON c.doctor_id = d.id LEFT JOIN patients p ON c.patient_id = p.id ORDER BY c.id DESC LIMIT 30")->fetchAll(); } catch(Exception $e) { $consultationsList = []; }

// Fetch security audit logs (try both table names)
try {
    $auditLogs = $pdo->query("SELECT a.*, u.name as operator_name FROM audit_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 40")->fetchAll();
} catch(Exception $e) {
    try { $auditLogs = $pdo->query("SELECT a.*, u.name as operator_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 40")->fetchAll(); } catch(Exception $e2) { $auditLogs = []; }
}

// Fetch notifications log dispatcher
try { $notificationLogs = $pdo->query("SELECT n.*, u.name as patient_user_name FROM notifications_log n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.sent_at DESC LIMIT 20")->fetchAll(); } catch(Exception $e) { $notificationLogs = []; }

// --- FETCH STATS ---
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(total) FROM orders WHERE payment_status = 'paid'")->fetchColumn() ?: 0;
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < 5")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// --- CHART DATA: Last 7 days revenue ---
$chartData = [];
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $days[$date] = date('D', strtotime($date));
}

$startDate = min(array_keys($days)) . ' 00:00:00';
$endDate = max(array_keys($days)) . ' 23:59:59';

$stmt = $pdo->prepare("
    SELECT DATE(created_at) as order_date, COALESCE(SUM(total), 0) as total 
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid'
    GROUP BY DATE(created_at)
");
$stmt->execute([$startDate, $endDate]);
$results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($days as $date => $dayName) {
    $chartData['labels'][] = $dayName;
    $chartData['values'][] = (float)($results[$date] ?? 0.0);
}

// --- RECENT ORDERS ---
$recentOrders = $pdo->query("SELECT o.*, COALESCE(u.name, o.name, 'Guest') as customer FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 6")->fetchAll();

// --- TOP PRODUCTS ---
$topProducts = $pdo->query("
    SELECT p.name, SUM(oi.quantity) as total_sold 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    GROUP BY p.id 
    ORDER BY total_sold DESC LIMIT 5
")->fetchAll();

// --- RECENT ACTIVITY (simulated, you can replace with real logs) ---
$recentActivity = [
    ['icon' => 'success', 'iconClass' => 'fa-check-circle', 'text' => 'Order <strong>#ORD-7842</strong> delivered', 'time' => '2 mins ago'],
    ['icon' => 'info', 'iconClass' => 'fa-user-plus', 'text' => 'New customer <strong>Rajesh Kumar</strong> registered', 'time' => '15 mins ago'],
    ['icon' => 'warning', 'iconClass' => 'fa-exclamation-triangle', 'text' => 'Product <strong>Fresh Apples</strong> low stock', 'time' => '1 hour ago']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>KSR GURU | Unified Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* ===== GLOBAL RESET & VARIABLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --sidebar-active: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --accent: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #8b5cf6;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --radius-sm: 0.5rem;
            --transition: 0.3s ease;
        }
        body.dark {
            --sidebar-bg: #020617;
            --main-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-light: #334155;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            transition: background 0.3s, color 0.2s;
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; font-family: inherit; }
        input, select, textarea { font-family: inherit; }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--main-bg); }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08);
        }
        .sidebar.collapsed { width: 85px; }
        .sidebar.collapsed .admin-logo div,
        .sidebar.collapsed .profile-info,
        .sidebar.collapsed .nav-item span:not(.badge),
        .sidebar.collapsed .nav-section h4,
        .sidebar.collapsed .system-status .status-text,
        .sidebar.collapsed .logout-btn span { display: none; }
        .sidebar.collapsed .nav-item { justify-content: center; padding: 12px 0; }
        .sidebar.collapsed .admin-profile { justify-content: center; padding: 20px 0; }
        .sidebar.collapsed .sidebar-footer { align-items: center; }
        .sidebar.collapsed .badge { display: none !important; }
        .sidebar-header {
            padding: 20px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-logo { display: flex; align-items: center; gap: 12px; }
        .admin-logo i { font-size: 28px; color: var(--accent); }
        .admin-logo h2 { font-size: 1.3rem; font-weight: 700; color: white; line-height: 1.2; }
        .admin-logo p { font-size: 0.65rem; color: #94a3b8; letter-spacing: 0.5px; }
        .sidebar-toggle {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar-toggle:hover { background: rgba(255,255,255,0.08); }
        .admin-profile {
            padding: 18px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .profile-image i { font-size: 42px; color: #94a3b8; }
        .profile-info h3 { font-size: 0.95rem; font-weight: 600; color: white; }
        .profile-info p { font-size: 0.7rem; color: #94a3b8; }
        .status-indicator {
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            position: absolute;
            right: 18px;
            top: 35px;
            border: 2px solid var(--sidebar-bg);
        }
        .sidebar-nav { flex: 1; padding: 12px 0 8px 0; }
        .nav-section { margin-bottom: 20px; }
        .nav-section h4 {
            padding: 0 20px;
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 9px 20px;
            color: var(--sidebar-text);
            transition: 0.2s;
            position: relative;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        .nav-item i { width: 22px; font-size: 1.1rem; text-align: center; flex-shrink: 0; }
        .nav-item .badge {
            background: #334155;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-left: auto;
            font-weight: 500;
            color: #e2e8f0;
        }
        .nav-item .badge-primary { background: var(--accent); color: white; }
        .nav-item.active {
            background: rgba(59,130,246,0.15);
            border-left-color: var(--accent);
            color: white;
        }
        .nav-item.active i { color: var(--accent); }
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .sidebar-footer {
            padding: 16px 18px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .system-status { margin-bottom: 14px; }
        .status-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            margin-right: 6px;
        }
        .logout-btn {
            width: 100%;
            background: rgba(239,68,68,0.15);
            border: none;
            padding: 10px;
            border-radius: 12px;
            color: #f87171;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.2s;
            font-size: 0.85rem;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.3); }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            transition: margin-left var(--transition);
            padding: 20px 28px;
            min-height: 100vh;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 85px; }

        /* ===== TOP HEADER ===== */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .header-left h1 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-left h1 i { color: var(--accent); }
        .breadcrumb {
            color: var(--text-secondary);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .breadcrumb i { font-size: 0.6rem; }
        .breadcrumb .active { color: var(--text-primary); font-weight: 500; }
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .search-box {
            background: var(--card-bg);
            border-radius: 40px;
            padding: 7px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 0.85rem;
            min-width: 180px;
        }
        .search-box i { color: var(--text-secondary); }
        .header-actions {
            display: flex;
            gap: 8px;
        }
        .header-btn {
            background: var(--card-bg);
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-primary);
            position: relative;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .header-btn:hover { transform: scale(1.05); }
        .notification-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: white;
            border-radius: 20px;
            font-size: 0.6rem;
            padding: 0 6px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card-bg);
            padding: 4px 14px 4px 10px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        .user-avatar i { font-size: 28px; color: var(--accent); }
        .user-info .user-name { font-weight: 600; font-size: 0.85rem; }
        .user-info .user-role { font-size: 0.65rem; color: var(--text-secondary); display: block; }

        /* ===== SECTION PAGES ===== */
        .page-section { display: none; animation: fadeIn 0.3s ease; }
        .page-section.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== DASHBOARD STYLES ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-icon.revenue { background: #dbeafe; color: #3b82f6; }
        .stat-icon.orders { background: #dcfce7; color: #10b981; }
        .stat-icon.customers { background: #fff3e3; color: #f97316; }
        .stat-icon.products { background: #f3e8ff; color: #a855f7; }
        .stat-info h3 { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
        .stat-info p { font-size: 0.8rem; color: var(--text-secondary); }
        .stat-trend { font-size: 0.7rem; display: flex; align-items: center; gap: 4px; margin-top: 4px; }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }
        .chart-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: var(--shadow);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .chart-header h3 { font-size: 1rem; font-weight: 600; }
        .chart-wrapper canvas { max-height: 260px; width: 100%; }
        .chart-period {
            padding: 4px 12px;
            border-radius: 30px;
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 0.75rem;
        }

        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .table-header h3 { font-size: 1rem; font-weight: 600; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th,
        .data-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        .data-table th {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .delivered { background: #dcfce7; color: #15803d; }
        .processing { background: #fff3e3; color: #c2410c; }
        .pending { background: #ffedd5; color: #b45309; }
        .shipped { background: #dbeafe; color: #1e40af; }
        .cancelled { background: #fee2e2; color: #b91c1c; }
        .active-status { background: #dcfce7; color: #15803d; }
        .inactive-status { background: #fee2e2; color: #b91c1c; }
        .view-btn {
            background: transparent;
            border: none;
            color: var(--accent);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .view-btn:hover { background: rgba(59,130,246,0.1); }

        .quick-section {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .action-card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: var(--shadow);
        }
        .action-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        .action-card i { font-size: 1.6rem; margin-bottom: 6px; display: block; color: var(--accent); }
        .action-card span { font-size: 0.75rem; font-weight: 500; }
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 8px;
        }
        .activity-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            background: var(--card-bg);
            padding: 12px 14px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow);
        }
        .activity-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9rem;
        }
        .activity-icon.success { background: #dcfce7; color: #15803d; }
        .activity-icon.info { background: #dbeafe; color: #2563eb; }
        .activity-icon.warning { background: #fff3e3; color: #ea580c; }
        .activity-content p { font-size: 0.85rem; }
        .activity-content span { font-size: 0.7rem; color: var(--text-secondary); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                width: 280px;
            }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar.collapsed { width: 280px; transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .sidebar.collapsed .admin-logo div,
            .sidebar.collapsed .profile-info,
            .sidebar.collapsed .nav-item span:not(.badge),
            .sidebar.collapsed .nav-section h4,
            .sidebar.collapsed .system-status .status-text,
            .sidebar.collapsed .logout-btn span { display: block; }
            .sidebar.collapsed .nav-item { justify-content: flex-start; padding: 9px 20px; }
            .sidebar.collapsed .admin-profile { justify-content: flex-start; padding: 18px 18px; }
            .sidebar.collapsed .badge { display: inline-block !important; }
            .main-content { margin-left: 0 !important; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-left h1 { font-size: 1.3rem; }
            .search-box input { min-width: 120px; }
            .user-info .user-name { font-size: 0.75rem; }
            .user-info .user-role { display: none; }
            .user-menu { padding: 4px 10px; }
            .actions-grid { grid-template-columns: 1fr 1fr; }
            .charts-section { grid-template-columns: 1fr; }
            .tables-section { grid-template-columns: 1fr; }
            .quick-section { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .header-right .search-box { width: 100%; }
            .header-right .search-box input { width: 100%; min-width: unset; }
            .top-header { flex-direction: column; align-items: stretch; }
            .header-right { flex-wrap: wrap; justify-content: stretch; }
            .user-menu { width: 100%; justify-content: center; }
            .header-actions { justify-content: center; }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>
    <!-- ===== SIDEBAR ===== -->
    <?php 
    $allowedPages = ['dashboard','doctors','policies','patients','consultations','audit'];
    $currentPage  = 'dashboard';
    if (isset($_GET['page']) && in_array($_GET['page'], $allowedPages)) {
        $currentPage = $_GET['page'];
    }
    include 'inc/sidebar.php';
    ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1 id="pageTitle"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="breadcrumb"><span>Admin</span><i class="fas fa-chevron-right"></i><span class="active" id="breadcrumbActive">Dashboard</span></div>
            </div>
            <div class="header-right">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Search..." id="globalSearch"></div>
                <div class="header-actions">
                    <button class="header-btn" id="notifBtn"><i class="fas fa-bell"></i><span class="notification-count">5</span></button>
                    <button class="header-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
                    <button class="header-btn" id="fullscreenBtn"><i class="fas fa-expand"></i></button>
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Super Admin</span></div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- ============================================================ -->
        <!-- PAGE: DASHBOARD -->
        <!-- ============================================================ -->
        <div class="page-section active" id="page-dashboard">
            <section class="stats-grid" id="dashStats">
                <div class="stat-card">
                    <div class="stat-icon revenue"><i class="fas fa-rupee-sign"></i></div>
                    <div class="stat-info">
                        <h3>₹<?= number_format($totalRevenue, 2) ?></h3>
                        <p>Total Revenue</p>
                        <div class="stat-trend positive"><i class="fas fa-arrow-up"></i><span>12.5%</span></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orders"><i class="fas fa-shopping-bag"></i></div>
                    <div class="stat-info">
                        <h3><?= $totalOrders ?></h3>
                        <p>Total Orders</p>
                        <div class="stat-trend positive"><i class="fas fa-arrow-up"></i><span>8.2%</span></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon customers"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= $totalCustomers ?></h3>
                        <p>Total Customers</p>
                        <div class="stat-trend positive"><i class="fas fa-arrow-up"></i><span>5.7%</span></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon products"><i class="fas fa-box-open"></i></div>
                    <div class="stat-info">
                        <h3><?= $totalProducts ?></h3>
                        <p>Active Products</p>
                        <div class="stat-trend negative"><i class="fas fa-arrow-down"></i><span>2.1%</span></div>
                    </div>
                </div>
            </section>

            <section class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Revenue Overview</h3>
                        <select class="chart-period" id="revenuePeriod">
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                    <div class="chart-wrapper"><canvas id="revenueChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Order Statistics</h3>
                        <select class="chart-period" id="orderPeriod">
                            <option value="month">This Month</option>
                            <option value="last">Last Month</option>
                        </select>
                    </div>
                    <div class="chart-wrapper"><canvas id="orderChart"></canvas></div>
                </div>
            </section>

            <section class="tables-section">
                <div class="table-container">
                    <div class="table-header"><h3>Recent Orders</h3><a href="#" class="view-all" style="font-size:0.8rem;color:var(--accent);">View All</a></div>
                    <table class="data-table">
                        <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody id="dashOrdersTable">
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($o['order_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($o['customer'] ?? 'Guest') ?></td>
                                        <td><?= date('d M, h:i A', strtotime($o['created_at'])) ?></td>
                                        <td>₹<?= number_format($o['total'], 2) ?></td>
                                        <td><?= getStatusBadge($o['status']) ?></td>
                                        <td><button class="view-btn"><i class="fas fa-eye"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-muted">No recent orders.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-container">
                    <div class="table-header"><h3>Top Selling Products</h3><a href="#" class="view-all" style="font-size:0.8rem;color:var(--accent);">View All</a></div>
                    <table class="data-table">
                        <thead><tr><th>Product</th><th>Sold</th></tr></thead>
                        <tbody>
                            <?php foreach ($topProducts as $p): ?>
                                <tr><td><?= htmlspecialchars($p['name']) ?></td><td><?= $p['total_sold'] ?> units</td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($topProducts)): ?>
                                <tr><td colspan="2" class="text-muted">No product data yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="quick-section">
                <div class="quick-actions">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:8px;">Quick Actions</h3>
                    <div class="actions-grid">
                        <button class="action-card" id="quickAddProduct"><i class="fas fa-plus-circle"></i><span>Add Product</span></button>
                        <button class="action-card" id="quickCreateOrder"><i class="fas fa-file-invoice"></i><span>Create Order</span></button>
                        <button class="action-card" id="quickNotify"><i class="fas fa-bullhorn"></i><span>Send Notification</span></button>
                        <button class="action-card" id="quickReport"><i class="fas fa-chart-bar"></i><span>Generate Report</span></button>
                    </div>
                </div>
                <div class="activity-feed">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:8px;">Recent Activity</h3>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $act): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= $act['icon'] ?>"><i class="fas <?= $act['iconClass'] ?>"></i></div>
                                <div class="activity-content">
                                    <p><?= $act['text'] ?></p>
                                    <span><?= $act['time'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <footer class="dashboard-footer">
                <div class="footer-content">
                    <div class="footer-info"><p>© 2026 KSR GURU Admin Dashboard</p></div>
                    <div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Help</a></div>
                </div>
            </footer>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: ORDERS (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-orders">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-shopping-cart" style="color:var(--accent);"></i> Order Management</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Manage and track all orders</p></div>
                <div style="display:flex;gap:8px;"><button class="btn btn-primary" id="ordersAddBtn"><i class="fas fa-plus"></i> New Order</button><button class="btn btn-outline"><i class="fas fa-download"></i> Export</button></div>
            </div>
            <div class="stats-grid" id="orderStats">
                <div class="stat-card"><div class="stat-icon orders" style="background:#dbeafe;color:#3b82f6;"><i class="fas fa-shopping-bag"></i></div><div class="stat-info"><h3><?= $totalOrders ?></h3><p>Total Orders</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?= $pendingOrders ?></h3><p>Pending</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-truck"></i></div><div class="stat-info"><h3><?= $pendingOrders ?></h3><p>Shipped</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;color:#059669;"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $totalOrders - $pendingOrders ?></h3><p>Delivered</p></div></div>
            </div>
            <div class="filter-bar"><div class="filter-group"><div class="filter-item"><label>Status</label><select id="orderStatusFilter"><option value="">All</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option></select></div><div class="filter-item"><label>Date</label><select id="orderDateFilter"><option value="today">Today</option><option value="week">This Week</option><option value="month">This Month</option></select></div></div><button class="btn btn-primary btn-sm" id="orderApplyFilter"><i class="fas fa-filter"></i> Apply</button></div>
            <div class="table-container"><table class="data-table"><thead><tr><th><input type="checkbox" id="orderSelectAll"></th><th>Order ID</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody id="ordersTableBody">
                <?php if (!empty($recentOrders)): ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><input type="checkbox" class="order-check"></td>
                            <td>#<?= htmlspecialchars($o['order_number']) ?></td>
                            <td><?= htmlspecialchars($o['customer'] ?? 'Guest') ?></td>
                            <td><?= date('d M, h:i A', strtotime($o['created_at'])) ?></td>
                            <td>₹<?= number_format($o['total'], 2) ?></td>
                            <td><?= getStatusBadge($o['status']) ?></td>
                            <td><button class="view-btn"><i class="fas fa-eye"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-muted">No orders found.</td></tr>
                <?php endif; ?>
            </tbody></table></div>
            <div class="pagination-bar"><div class="pagination-info">Showing 1-<?= count($recentOrders) ?> of <?= $totalOrders ?> orders</div><div class="pagination-controls"><button>‹</button><button class="active">1</button><button>2</button><button>3</button><button>…</button><button>125</button><button>›</button></div></div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: PRODUCTS (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-products">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-box" style="color:var(--accent);"></i> Product Management</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Manage your product catalog</p></div>
                <div style="display:flex;gap:8px;"><button class="btn btn-primary" id="productsAddBtn"><i class="fas fa-plus"></i> Add Product</button><button class="btn btn-outline"><i class="fas fa-download"></i> Export</button></div>
            </div>
            <div class="stats-grid" id="productStats">
                <div class="stat-card"><div class="stat-icon products" style="background:#f3e8ff;color:#a855f7;"><i class="fas fa-box-open"></i></div><div class="stat-info"><h3><?= $totalProducts ?></h3><p>Total Products</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;color:#10b981;"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $totalProducts - $lowStock ?></h3><p>In Stock</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h3><?= $lowStock ?></h3><p>Low Stock</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-times-circle"></i></div><div class="stat-info"><h3>0</h3><p>Out of Stock</p></div></div>
            </div>
            <div class="filter-bar"><div class="filter-group"><div class="filter-item"><label>Category</label><select id="productCategoryFilter"><option value="">All</option></select></div><div class="filter-item"><label>Status</label><select id="productStatusFilter"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div><button class="btn btn-primary btn-sm" id="productApplyFilter"><i class="fas fa-filter"></i> Apply</button></div>
            <div class="products-grid" id="productsGrid">
                <!-- Sample product cards -->
                <div class="product-card"><div class="product-image"><span class="product-badge new">NEW</span><span class="product-badge stock" style="left:auto;right:8px;background:#10b981;">In Stock</span></div><div class="product-info"><div class="product-title">Fresh Apples (1kg)</div><div class="product-category">Groceries / Fruits</div><div class="product-meta"><span class="product-price">₹199</span><span><i class="fas fa-box"></i> 156 units</span></div><div class="product-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i><span class="rating-count">(128)</span></div><div class="product-status"><span class="status-badge active-status">Active</span><span style="color:var(--text-secondary);font-size:0.7rem;">Added: 2024-01-15</span></div></div></div>
                <div class="product-card"><div class="product-image"><span class="product-badge stock" style="left:auto;right:8px;background:#10b981;">In Stock</span></div><div class="product-info"><div class="product-title">Whole Wheat Bread</div><div class="product-category">Groceries / Bakery</div><div class="product-meta"><span class="product-price">₹45</span><span><i class="fas fa-box"></i> 289 units</span></div><div class="product-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span class="rating-count">(215)</span></div><div class="product-status"><span class="status-badge active-status">Active</span><span style="color:var(--text-secondary);font-size:0.7rem;">Added: 2024-01-12</span></div></div></div>
                <div class="product-card"><div class="product-image"><span class="product-badge stock" style="left:auto;right:8px;background:#f59e0b;">Low Stock</span></div><div class="product-info"><div class="product-title">Olive Oil (1L)</div><div class="product-category">Groceries / Oils</div><div class="product-meta"><span class="product-price">₹599</span><span><i class="fas fa-box"></i> 78 units</span></div><div class="product-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span class="rating-count">(94)</span></div><div class="product-status"><span class="status-badge active-status">Active</span><span style="color:var(--text-secondary);font-size:0.7rem;">Added: 2024-01-10</span></div></div></div>
            </div>
            <div class="pagination-bar"><div class="pagination-info">Showing 1-3 of <?= $totalProducts ?> products</div><div class="pagination-controls"><button>‹</button><button class="active">1</button><button>2</button><button>3</button><button>…</button><button>66</button><button>›</button></div></div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: DELIVERY (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-delivery">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-truck" style="color:var(--accent);"></i> Delivery Management</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Manage and track deliveries in real-time</p></div>
                <button class="btn btn-primary" id="deliveryAddBtn"><i class="fas fa-plus"></i> Add Delivery</button>
            </div>
            <div class="stats-grid" id="deliveryStats">
                <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div><div class="stat-info"><h3 id="delPendingCount">3</h3><p>Pending</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-truck-moving"></i></div><div class="stat-info"><h3 id="delActiveCount">2</h3><p>Active</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;color:#059669;"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3 id="delDeliveredCount">3</h3><p>Delivered</p></div></div>
            </div>
            <div class="filter-bar"><div class="filter-group"><div class="filter-item"><label>Filter</label><select id="deliveryFilter"><option value="all">All</option><option value="pending">Pending</option><option value="active">Active</option><option value="delivered">Delivered</option></select></div></div><button class="btn btn-primary btn-sm" id="deliveryApplyFilter"><i class="fas fa-filter"></i> Apply</button></div>
            <div class="products-grid" id="deliveryGrid">
                <div class="delivery-card pending"><div class="card-header"><span class="order-id">#ORD-7842</span><span class="status-badge pending">Pending</span></div><div class="customer-name"><i class="fas fa-user"></i> Rajesh Kumar</div><div class="address"><i class="fas fa-map-marker-alt"></i> 12/3, Green Park, New Delhi - 110016</div><div class="eta"><i class="fas fa-hourglass-half"></i> 30-45 min</div><div class="card-actions"><button class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Start</button></div></div>
                <div class="delivery-card active-del"><div class="card-header"><span class="order-id">#ORD-7841</span><span class="status-badge shipped">Active</span></div><div class="customer-name"><i class="fas fa-user"></i> Priya Sharma</div><div class="address"><i class="fas fa-map-marker-alt"></i> 45, Lake View Apartments, Mumbai - 400054</div><div class="eta"><i class="fas fa-hourglass-half"></i> On the way • 15-20 min</div><div class="card-actions"><button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Mark Delivered</button></div></div>
                <div class="delivery-card delivered"><div class="card-header"><span class="order-id">#ORD-7839</span><span class="status-badge delivered">Delivered</span></div><div class="customer-name"><i class="fas fa-user"></i> Anjali Patel</div><div class="address"><i class="fas fa-map-marker-alt"></i> 23/A, Silver Oaks, Bangalore - 560001</div><div class="eta"><i class="fas fa-check-circle" style="color:var(--success);"></i> Delivered</div></div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: USERS (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-users">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-users" style="color:var(--accent);"></i> User Management</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Manage user accounts and permissions</p></div>
                <div style="display:flex;gap:8px;"><button class="btn btn-primary" id="usersAddBtn"><i class="fas fa-user-plus"></i> Add User</button><button class="btn btn-outline"><i class="fas fa-download"></i> Export</button></div>
            </div>
            <div class="stats-grid" id="userStats">
                <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;color:#3b82f6;"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $totalCustomers ?></h3><p>Total Users</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;color:#10b981;"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3><?= $totalCustomers ?></h3><p>Active Users</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fas fa-user-plus"></i></div><div class="stat-info"><h3>156</h3><p>New This Month</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#f3e8ff;color:#8b5cf6;"><i class="fas fa-crown"></i></div><div class="stat-info"><h3>324</h3><p>Premium Members</p></div></div>
            </div>
            <div class="filter-bar"><div class="filter-group"><div class="filter-item"><label>Role</label><select id="userRoleFilter"><option value="">All</option><option value="customer">Customer</option><option value="admin">Admin</option><option value="staff">Staff</option></select></div><div class="filter-item"><label>Status</label><select id="userStatusFilter"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div><button class="btn btn-primary btn-sm" id="userApplyFilter"><i class="fas fa-filter"></i> Apply</button></div>
            <div class="table-container"><table class="data-table"><thead><tr><th><input type="checkbox"></th><th>User</th><th>Role</th><th>Status</th><th>Orders</th><th>Joined</th><th>Action</th></tr></thead><tbody><tr><td><input type="checkbox"></td><td>Rajesh Kumar</td><td>Customer</td><td><span class="status-badge active-status">Active</span></td><td>42</td><td>Jan 15, 2023</td><td><button class="view-btn"><i class="fas fa-edit"></i></button></td></tr><tr><td><input type="checkbox"></td><td>Priya Sharma</td><td>Customer</td><td><span class="status-badge active-status">Active</span></td><td>38</td><td>Feb 2, 2023</td><td><button class="view-btn"><i class="fas fa-edit"></i></button></td></tr><tr><td><input type="checkbox"></td><td>Admin User</td><td>Admin</td><td><span class="status-badge active-status">Active</span></td><td>124</td><td>Jan 1, 2023</td><td><button class="view-btn"><i class="fas fa-edit"></i></button></td></tr></tbody></table></div>
            <div class="pagination-bar"><div class="pagination-info">Showing 1-3 of <?= $totalCustomers ?> users</div><div class="pagination-controls"><button>‹</button><button class="active">1</button><button>2</button><button>3</button><button>…</button><button>285</button><button>›</button></div></div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: REPORTS (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-reports">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-chart-line" style="color:var(--accent);"></i> Reports & Analytics</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Gain insights into business performance</p></div>
                <div style="display:flex;gap:8px;"><button class="btn btn-primary" id="reportExportBtn"><i class="fas fa-file-export"></i> Export CSV</button><button class="btn btn-danger" id="reportPdfBtn"><i class="fas fa-file-pdf"></i> Download PDF</button></div>
            </div>
            <div class="stats-grid" id="reportStats">
                <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;color:#3b82f6;"><i class="fas fa-rupee-sign"></i></div><div class="stat-info"><h3>₹<?= number_format($totalRevenue, 2) ?></h3><p>Total Revenue</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#dcfce7;color:#10b981;"><i class="fas fa-shopping-bag"></i></div><div class="stat-info"><h3><?= $totalOrders ?></h3><p>Total Orders</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fas fa-chart-bar"></i></div><div class="stat-info"><h3>₹<?= $totalOrders ? number_format($totalRevenue / $totalOrders, 2) : '0' ?></h3><p>Avg. Order Value</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background:#f3e8ff;color:#8b5cf6;"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $totalCustomers ?></h3><p>Total Customers</p></div></div>
            </div>
            <div class="charts-section">
                <div class="chart-container full-width"><div class="chart-header"><h3>Revenue Trend</h3><select class="chart-period" id="reportRevenuePeriod"><option value="7">Last 7 Days</option><option value="30">Last 30 Days</option></select></div><div class="chart-wrapper"><canvas id="reportRevenueChart"></canvas></div></div>
            </div>
            <div class="table-container"><div class="table-header"><h3>Sales Report</h3></div><table class="data-table"><thead><tr><th>Date</th><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead><tbody>
                <?php if (!empty($recentOrders)): ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr><td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td><td>#<?= htmlspecialchars($o['order_number']) ?></td><td><?= htmlspecialchars($o['customer'] ?? 'Guest') ?></td><td>₹<?= number_format($o['total'], 2) ?></td><td><?= getStatusBadge($o['status']) ?></td></tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-muted">No sales data yet.</td></tr>
                <?php endif; ?>
            </tbody></table></div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: SETTINGS (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-settings">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-cog" style="color:var(--accent);"></i> Shop Settings</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Configure your shop preferences</p></div>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Save All</button>
            </div>
            <div class="settings-nav" id="settingsNav">
                <button class="nav-item active" data-settings="general"><i class="fas fa-store"></i> General</button>
                <button class="nav-item" data-settings="business"><i class="fas fa-clock"></i> Hours</button>
                <button class="nav-item" data-settings="delivery"><i class="fas fa-truck"></i> Delivery</button>
                <button class="nav-item" data-settings="payment"><i class="fas fa-credit-card"></i> Payment</button>
                <button class="nav-item" data-settings="notifications"><i class="fas fa-bell"></i> Notifications</button>
                <button class="nav-item" data-settings="staff"><i class="fas fa-users"></i> Staff</button>
                <button class="nav-item" data-settings="security"><i class="fas fa-shield-alt"></i> Security</button>
                <button class="nav-item" data-settings="integration"><i class="fas fa-plug"></i> Integrations</button>
            </div>
            <!-- General -->
            <div class="settings-section active" id="settings-general">
                <div class="settings-card"><div class="card-title"><i class="fas fa-store"></i> General Information</div>
                    <div class="form-group"><label>Shop Name</label><input type="text" value="KSR GURU"></div>
                    <div class="form-group"><label>Tagline</label><input type="text" value="Everything Your Family Needs, Every Day"></div>
                    <div class="form-group"><label>Contact Email</label><input type="email" value="support@ksrguru.com"></div>
                    <div class="form-group"><label>Contact Phone</label><input type="tel" value="+91 9876543210"></div>
                    <div class="form-group"><label>Address</label><textarea rows="2">123, Main Street, Bangalore - 560001</textarea></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Business Hours -->
            <div class="settings-section" id="settings-business">
                <div class="settings-card"><div class="card-title"><i class="fas fa-clock"></i> Business Hours</div>
                    <div class="day-schedule"><div class="day-header"><h4>Monday - Friday</h4></div><div class="time-slot"><input type="time" value="09:00"><span>to</span><input type="time" value="21:00"></div></div>
                    <div class="day-schedule"><div class="day-header"><h4>Saturday</h4></div><div class="time-slot"><input type="time" value="10:00"><span>to</span><input type="time" value="22:00"></div></div>
                    <div class="day-schedule"><div class="day-header"><h4>Sunday</h4><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div><div class="time-slot"><input type="time" value="09:00" disabled><span>to</span><input type="time" value="21:00" disabled></div></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Delivery -->
            <div class="settings-section" id="settings-delivery">
                <div class="settings-card"><div class="card-title"><i class="fas fa-truck"></i> Delivery Settings</div>
                    <div class="form-group"><label>Delivery Radius (km)</label><input type="range" min="1" max="50" value="15" style="width:100%;"><span style="font-size:0.8rem;color:var(--text-secondary);">15 km</span></div>
                    <div class="form-group"><label>Minimum Order Amount (₹)</label><input type="number" value="200"></div>
                    <div class="form-group"><label>Standard Delivery Fee (₹)</label><input type="number" value="40"></div>
                    <div class="form-group"><label>Free Delivery Threshold (₹)</label><input type="number" value="500"></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Payment -->
            <div class="settings-section" id="settings-payment">
                <div class="settings-card"><div class="card-title"><i class="fas fa-credit-card"></i> Payment Methods</div>
                    <div class="payment-option"><div class="payment-info"><div class="payment-icon"><i class="fas fa-money-bill-wave"></i></div><div><h4>Cash on Delivery</h4><p>Accept cash upon delivery</p></div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="payment-option"><div class="payment-info"><div class="payment-icon"><i class="fas fa-credit-card"></i></div><div><h4>Credit/Debit Card</h4><p>Accept card payments online</p></div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="payment-option"><div class="payment-info"><div class="payment-icon"><i class="fas fa-mobile-alt"></i></div><div><h4>UPI Payment</h4><p>Accept UPI payments</p></div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Notifications -->
            <div class="settings-section" id="settings-notifications">
                <div class="settings-card"><div class="card-title"><i class="fas fa-bell"></i> Notification Settings</div>
                    <div class="notification-option"><div class="notification-info"><h4>New Order Alerts</h4><p>Receive email for new orders</p></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="notification-option"><div class="notification-info"><h4>Delivery Updates</h4><p>Receive email for delivery status</p></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="notification-option"><div class="notification-info"><h4>Daily Summary</h4><p>Receive daily sales summary</p></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Staff -->
            <div class="settings-section" id="settings-staff">
                <div class="settings-card"><div class="card-title"><i class="fas fa-users"></i> Staff Management</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;"><h4 style="font-size:0.95rem;">Staff Members</h4><button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button></div>
                    <div class="staff-card"><div class="staff-info"><div class="staff-avatar"><i class="fas fa-user"></i></div><div><h4>Rajesh Kumar</h4><p>Administrator</p></div></div><div class="staff-actions"><button class="view-btn"><i class="fas fa-edit"></i></button><button class="view-btn" style="color:var(--danger);"><i class="fas fa-trash"></i></button></div></div>
                    <div class="staff-card"><div class="staff-info"><div class="staff-avatar"><i class="fas fa-user"></i></div><div><h4>Priya Sharma</h4><p>Delivery Manager</p></div></div><div class="staff-actions"><button class="view-btn"><i class="fas fa-edit"></i></button><button class="view-btn" style="color:var(--danger);"><i class="fas fa-trash"></i></button></div></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Security -->
            <div class="settings-section" id="settings-security">
                <div class="settings-card"><div class="card-title"><i class="fas fa-shield-alt"></i> Security</div>
                    <div class="form-group"><label>Minimum Password Length</label><input type="number" value="8"></div>
                    <div class="form-group"><label>Session Timeout (minutes)</label><input type="number" value="30"></div>
                    <div class="form-group"><label class="checkbox-label" style="display:flex;align-items:center;gap:8px;"><input type="checkbox" checked><span>Force logout on password change</span></label></div>
                    <div class="form-group"><label class="checkbox-label" style="display:flex;align-items:center;gap:8px;"><input type="checkbox"><span>Enable Two-Factor Authentication</span></label></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
            <!-- Integrations -->
            <div class="settings-section" id="settings-integration">
                <div class="settings-card"><div class="card-title"><i class="fas fa-plug"></i> Integrations</div>
                    <div class="integration-card"><div class="integration-header"><div style="display:flex;align-items:center;gap:12px;"><div class="integration-icon"><i class="fas fa-shipping-fast"></i></div><div class="integration-info"><h4>Delivery Service</h4><p>Integrate with delivery partners</p></div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div><div class="integration-details"><p>Connect with Dunzo, Swiggy Genie, etc.</p><button class="btn btn-outline btn-sm">Configure</button></div></div>
                    <div class="integration-card"><div class="integration-header"><div style="display:flex;align-items:center;gap:12px;"><div class="integration-icon"><i class="fas fa-chart-line"></i></div><div class="integration-info"><h4>Analytics</h4><p>Google Analytics integration</p></div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="integration-details"><p>Track website traffic and behavior</p><button class="btn btn-outline btn-sm">Configure</button></div></div>
                    <div class="integration-card"><div class="integration-header"><div style="display:flex;align-items:center;gap:12px;"><div class="integration-icon"><i class="fas fa-credit-card"></i></div><div class="integration-info"><h4>Payment Gateway</h4><p>Process online payments</p></div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="integration-details"><p>Integrate with Razorpay, Stripe, etc.</p><button class="btn btn-outline btn-sm">Configure</button></div></div>
                    <div class="form-actions"><button class="btn btn-outline">Reset</button><button class="btn btn-primary">Save</button></div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: MAINTENANCE (placeholder) -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-maintenance">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div><h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-tools" style="color:var(--accent);"></i> Maintenance Mode</h2><p style="color:var(--text-secondary);font-size:0.85rem;">Control maintenance mode and settings</p></div>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>

            <div class="maintenance-toggle-card">
                <div class="toggle-status"><h2>Maintenance Mode</h2><span class="maintenance-badge disabled" id="maintenanceBadge">Disabled</span></div>
                <label class="toggle-switch"><input type="checkbox" id="maintenanceToggle"><span class="toggle-slider"></span></label>
            </div>

            <div class="settings-grid">
                <div class="settings-card"><div class="card-title"><i class="fas fa-envelope-open-text"></i> Maintenance Message</div>
                    <div class="form-group"><label>Title</label><input type="text" value="Under Maintenance" id="maintenanceTitle"></div>
                    <div class="form-group"><label>Message</label><textarea rows="2" id="maintenanceMessage">We are currently performing scheduled maintenance. Please check back soon!</textarea></div>
                    <div class="form-group"><label>Estimated Return Time</label><input type="text" value="Coming back shortly" id="maintenanceEta"></div>
                    <div class="form-group"><label>Contact Email</label><input type="email" placeholder="support@example.com" id="maintenanceEmail"></div>
                </div>
                <div class="settings-card"><div class="card-title"><i class="fas fa-calendar-alt"></i> Schedule</div>
                    <div class="form-group"><label>Start</label><input type="datetime-local"></div>
                    <div class="form-group"><label>End</label><input type="datetime-local"></div>
                    <button class="btn btn-primary">Schedule</button>
                    <div style="margin-top:12px;font-size:0.85rem;color:var(--text-secondary);">No scheduled maintenance</div>
                </div>
                <div class="settings-card"><div class="card-title"><i class="fas fa-shield-alt"></i> IP Whitelist</div>
                    <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:10px;">Users from these IPs can access during maintenance.</p>
                    <div class="ip-list"><div class="ip-tag">192.168.1.1 <button><i class="fas fa-times"></i></button></div><div class="ip-tag">10.0.0.0/24 <button><i class="fas fa-times"></i></button></div></div>
                    <div class="add-ip"><input type="text" placeholder="e.g., 192.168.1.1"><button class="btn btn-outline btn-sm">Add</button></div>
                </div>
            </div>

            <div class="settings-card"><div class="card-title"><i class="fas fa-eye"></i> Preview</div>
                <div class="maintenance-preview"><i class="fas fa-tools" style="font-size:3rem;color:var(--warning);margin-bottom:12px;display:block;"></i><h3 id="previewTitle">Under Maintenance</h3><p id="previewMessage">We are currently performing scheduled maintenance. Please check back soon!</p><p id="previewEta" style="font-size:0.85rem;color:var(--text-secondary);"><i class="fas fa-hourglass-half"></i> Coming back shortly</p></div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: DOCTOR MANAGEMENT -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-doctors">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div>
                    <h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-user-md" style="color:var(--accent);"></i> Doctor Practitioner Registry</h2>
                    <p style="color:var(--text-secondary);font-size:0.85rem;">Approve licensing, verify credentials, and manage patient directories</p>
                </div>
            </div>

            <?php if (!empty($doctorActionMsg)): ?>
                <div class="status-badge <?= $doctorActionSuccess ? 'active-status' : 'cancelled' ?>" style="margin-bottom:20px; font-weight: 600; padding:10px 20px !important; display:block !important; border-radius:10px !important; text-align:center;">
                    <?= htmlspecialchars($doctorActionMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Tabs buttons -->
            <div class="tab-buttons" style="margin-bottom: 20px;">
                <button class="tab-btn active" onclick="switchDocTab('practitioners', this)"><i class="fas fa-stethoscope"></i> Practitioner Verification</button>
                <button class="tab-btn" onclick="switchDocTab('patients', this)"><i class="fas fa-user-injured"></i> Patient Directory</button>
                <button class="tab-btn" onclick="switchDocTab('whatsapp', this)"><i class="fab fa-whatsapp"></i> WhatsApp Dispatch Logs</button>
            </div>

            <!-- Tab 1: Practitioners -->
            <div class="doctor-tab-content" id="doc-tab-practitioners">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Physician Details</th>
                                <th>Specialty & Authority</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($doctorsList)): ?>
                                <?php foreach ($doctorsList as $doc): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color:var(--text-primary);"><?= htmlspecialchars(stripos($doc['doctor_name'], 'Dr.') === 0 ? $doc['doctor_name'] : 'Dr. ' . $doc['doctor_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary);"><?= htmlspecialchars($doc['qualification']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($doc['specialty']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary);">Council: <?= htmlspecialchars($doc['registration_number']) ?> (<?= htmlspecialchars($doc['registration_authority']) ?>)</div>
                                        </td>
                                        <td><?= htmlspecialchars($doc['experience_years']) ?> Years</td>
                                        <td>
                                            <?php 
                                            $stClass = 'processing';
                                            if ($doc['verification_status'] === 'VERIFIED') $stClass = 'delivered';
                                            elseif ($doc['verification_status'] === 'PENDING') $stClass = 'pending';
                                            elseif ($doc['verification_status'] === 'REJECTED' || $doc['verification_status'] === 'SUSPENDED') $stClass = 'cancelled';
                                            ?>
                                            <span class="status-badge <?= $stClass ?>"><?= htmlspecialchars($doc['verification_status']) ?></span>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:4px;">
                                                <?php if ($doc['verification_status'] !== 'VERIFIED'): ?>
                                                    <a href="dashboard.php?verify_doctor_id=<?= $doc['id'] ?>&set_status=VERIFIED" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.7rem;"><i class="fas fa-check"></i> Verify</a>
                                                <?php endif; ?>
                                                <?php if ($doc['verification_status'] !== 'SUSPENDED' && $doc['verification_status'] !== 'REJECTED'): ?>
                                                    <a href="dashboard.php?verify_doctor_id=<?= $doc['id'] ?>&set_status=SUSPENDED" class="btn btn-danger btn-sm" style="padding: 4px 10px; font-size: 0.7rem;"><i class="fas fa-ban"></i> Suspend</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-muted" style="text-align:center;">No doctors registered in database repository.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Patients -->
            <div class="doctor-tab-content" id="doc-tab-patients" style="display:none;">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Birthdate / Gender</th>
                                <th>Contact & Registry Info</th>
                                <th>Emergency Contact</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($patientsList)): ?>
                                <?php foreach ($patientsList as $pat): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color:var(--text-primary);"><?= htmlspecialchars($pat['patient_name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary);">User email: <?= htmlspecialchars($pat['email'] ?? 'Unknown mapping') ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($pat['date_of_birth']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary);"><?= htmlspecialchars($pat['gender']) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($pat['mobile_number']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-secondary); text-overflow:ellipsis; overflow:hidden; max-width:200px; white-space:nowrap;"><?= htmlspecialchars($pat['address']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($pat['emergency_contact']) ?></td>
                                        <td>
                                            <button class="btn btn-outline btn-sm" style="padding:4px 8px;" onclick="openPatientModal(<?= htmlspecialchars(json_encode($pat)) ?>)"><i class="fas fa-clipboard-list"></i> History & Meds</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-muted" style="text-align:center;">No patient records registered in database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: WhatsApp Dispatch Logs -->
            <div class="doctor-tab-content" id="doc-tab-whatsapp" style="display:none;">
                <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:12px;"><i class="fas fa-info-circle"></i> Tracing immediate WhatsApp dispatch alerts delivered to patients matching critical verification status and flow updates.</p>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Event Type</th>
                                <th>Payload Parameter Details</th>
                                <th>Dispatch Status</th>
                                <th>Dispatch Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($notificationLogs)): ?>
                                <?php foreach ($notificationLogs as $nl): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:700;"><?= htmlspecialchars($nl['patient_user_name'] ?? 'User #'.$nl['user_id']) ?></div>
                                        </td>
                                        <td>
                                            <code style="background:rgba(15,123,58,0.08); padding:3px 6px; border-radius:4px; font-size:0.75rem; font-weight:700; color:var(--color-primary);">
                                                <?= htmlspecialchars($nl['template_name']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <div style="max-width:260px; overflow-x:auto; font-family:monospace; font-size:0.75rem; color:var(--text-secondary);">
                                                <?= htmlspecialchars($nl['payload_json']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge delivered"><?= htmlspecialchars($nl['dispatch_status']) ?></span>
                                        </td>
                                        <td><?= date('Y-m-d H:i:s', strtotime($nl['sent_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-muted" style="text-align:center;">No WhatsApp notification dispatch records.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- PAGE: CLINICAL POLICIES -->
        <!-- ============================================================ -->
        <div class="page-section" id="page-policies">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                <div>
                    <h2 style="font-size:1.3rem;font-weight:600;"><i class="fas fa-sliders-h" style="color:var(--accent);"></i> Clinical Compliance & Catalog Settings</h2>
                    <p style="color:var(--text-secondary);font-size:0.85rem;">Manage maximum age threshold variables for electronic scans & catalog drug SKUs</p>
                </div>
            </div>

            <div class="settings-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- left part: Compliance Settings -->
                <div class="card" style="padding: 24px;">
                    <div class="card-title" style="font-size: 1.1rem; font-weight:700; color:var(--color-primary); margin-bottom:14px; border-bottom:1px solid var(--border-light); padding-bottom:8px;"><i class="fas fa-clock"></i> Scan Recall Thresholds</div>
                    <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:16px;">Set the limit for oldest acceptable prescription uploads. Scans older than this value are flagged for audit verification.</p>
                    
                    <form method="POST" action="">
                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600; font-size:0.85rem;">Max Recall Threshold Days</label>
                            <input type="number" name="threshold_days" value="<?= $currentThreshold ?>" required style="margin-top:4px;">
                        </div>
                        <button type="submit" name="save_policy" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-save"></i> Save Parameter Recall Threshold</button>
                    </form>
                </div>

                <!-- right part: Medicine catalogue adder -->
                <div class="card" style="padding: 24px;">
                    <div class="card-title" style="font-size: 1.1rem; font-weight:700; color:var(--color-primary); margin-bottom:14px; border-bottom:1px solid var(--border-light); padding-bottom:8px;"><i class="fas fa-plus-circle"></i> Catalog Drug SKU Seeder</div>
                    <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:16px;">Insert new SKUs matching fuzzy engine lookup queries.</p>
                    
                    <form method="POST" action="">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div class="form-group">
                                <label style="font-weight:600; font-size:0.8rem;">Brand Name*</label>
                                <input type="text" name="brand_name" placeholder="Calpol" required style="width:100% !important;">
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600; font-size:0.8rem;">Generic Formulation*</label>
                                <input type="text" name="generic_name" placeholder="Paracetamol" required style="width:100% !important;">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div class="form-group">
                                <label style="font-weight:600; font-size:0.8rem;">Dosage Form*</label>
                                <select name="dosage_form" required style="width:100% !important;">
                                    <option value="Tablet">Tablet</option>
                                    <option value="Capsule">Capsule</option>
                                    <option value="Syrup">Syrup</option>
                                    <option value="Drops">Drops</option>
                                    <option value="Ointment">Ointment</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600; font-size:0.8rem;">Strength Value*</label>
                                <input type="text" name="strength" placeholder="650 mg" required style="width:100% !important;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600; font-size:0.8rem;">Manufacturer Maker</label>
                            <input type="text" name="manufacturer" placeholder="GSK Ltd." style="width:100% !important;">
                        </div>
                        <button type="submit" name="add_medicine" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-seedling"></i> Seed Drug Catalog SKU</button>
                    </form>
                </div>
            </div>

            <!-- Drug Catalog Inventories list -->
            <div class="card" style="margin-top:24px; padding:24px;">
                <div class="card-title" style="font-size: 1.1rem; font-weight:700; color:var(--color-primary); margin-bottom:14px; border-bottom:1px solid var(--border-light); padding-bottom:8px;"><i class="fas fa-prescription-bottle"></i> Drug Catalog Inventories</div>
                <div class="table-container" style="max-height: 250px; overflow-y: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Drug Brand</th>
                                <th>Generic Chemical Name</th>
                                <th>Strength / Form</th>
                                <th>Manufacturer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($medicinesList)): ?>
                                <?php foreach ($medicinesList as $med): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($med['brand_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($med['generic_name']) ?></td>
                                        <td><?= htmlspecialchars($med['strength']) ?> / <?= htmlspecialchars($med['dosage_form']) ?></td>
                                        <td><?= htmlspecialchars($med['manufacturer'] ?: 'Unknown') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-muted" style="text-align:center;">No cataloged medicines in inventory.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Audit Logs Section -->
            <div class="card" style="margin-top:24px; padding:24px;">
                <div class="card-title" style="font-size: 1.1rem; font-weight:700; color:var(--color-primary); margin-bottom:14px; border-bottom:1px solid var(--border-light); padding-bottom:8px;"><i class="fas fa-fingerprint"></i> System Cryptographic Audit Logs</div>
                <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:12px;">Chronological, immutable security trace logging for digital certifications, override permissions & settings edits.</p>
                <div class="table-container" style="max-height: 300px; overflow-y: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Operator</th>
                                <th>Action Details</th>
                                <th>Entity Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($auditLogs)): ?>
                                <?php foreach ($auditLogs as $al): ?>
                                    <tr>
                                        <td><small style="color:var(--text-muted); font-size:0.75rem;"><?= date('Y-m-d H:i:s', strtotime($al['created_at'])) ?></small></td>
                                        <td><strong><?= htmlspecialchars($al['operator_name'] ?? 'System/Guest') ?></strong></td>
                                        <td>
                                            <div style="font-weight:600; color:var(--color-primary);"><?= htmlspecialchars($al['action']) ?></div>
                                            <div style="font-size:0.8rem; color:var(--text-secondary);"><?= htmlspecialchars($al['details']) ?></div>
                                        </td>
                                        <td><code style="font-size:0.75rem; background:rgba(0,0,0,0.03); border:1px solid var(--border-light); padding:3px 6px; border-radius:4px;"><?= htmlspecialchars($al['target_table']) ?> #<?= $al['record_id'] ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-muted" style="text-align:center;">No cryptographic audit trail detected.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- ===== NOTIFICATION PANEL ===== -->
    <div class="notif-panel" id="notifPanel">
        <div class="modal-header"><h3>Notifications</h3><button class="modal-close" id="notifClose"><i class="fas fa-times"></i></button></div>
        <div class="notif-item"><div class="notif-icon"><i class="fas fa-shopping-cart"></i></div><div class="notif-content"><p>New order <strong>#ORD-7843</strong></p><span>Just now</span></div></div>
        <div class="notif-item"><div class="notif-icon"><i class="fas fa-star"></i></div><div class="notif-content"><p>New product review received</p><span>1 hour ago</span></div></div>
        <div class="notif-item"><div class="notif-icon"><i class="fas fa-truck"></i></div><div class="notif-content"><p>Delivery #DEL-001 is running late</p><span>2 hours ago</span></div></div>
    </div>

    <!-- ===== MODAL: Add Product ===== -->
    <div class="modal-overlay" id="modalProduct">
        <div class="modal-box">
            <div class="modal-header"><h3>Add New Product</h3><button class="modal-close" onclick="closeModal('modalProduct')"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Product Name</label><input type="text" placeholder="Enter product name"></div>
                <div class="form-group"><label>Category</label><select><option>Groceries</option><option>Food</option><option>Medicines</option></select></div>
                <div class="form-group"><label>Price (₹)</label><input type="number" placeholder="0.00"></div>
                <div class="form-group"><label>Stock</label><input type="number" placeholder="0"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline">Cancel</button><button class="btn btn-primary">Add Product</button></div>
        </div>
    </div>

    <!-- ===== MODAL: Add Delivery ===== -->
    <div class="modal-overlay" id="modalDelivery">
        <div class="modal-box">
            <div class="modal-header"><h3>Add New Delivery</h3><button class="modal-close" onclick="closeModal('modalDelivery')"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Order ID</label><input type="text" placeholder="#ORD-XXXX"></div>
                <div class="form-group"><label>Customer</label><input type="text" placeholder="Full name"></div>
                <div class="form-group"><label>Address</label><textarea rows="2" placeholder="Street, city, pincode"></textarea></div>
                <div class="form-group"><label>Estimated Time</label><input type="text" placeholder="e.g., 30-45 min"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline">Cancel</button><button class="btn btn-primary">Add Delivery</button></div>
        </div>
    </div>

    <!-- ===== MODAL: Add User ===== -->
    <div class="modal-overlay" id="modalUser">
        <div class="modal-box">
            <div class="modal-header"><h3>Add New User</h3><button class="modal-close" onclick="closeModal('modalUser')"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Full Name</label><input type="text" placeholder="John Doe"></div>
                <div class="form-group"><label>Email</label><input type="email" placeholder="john@example.com"></div>
                <div class="form-group"><label>Phone</label><input type="tel" placeholder="+91 9876543210"></div>
                <div class="form-group"><label>Role</label><select><option>Customer</option><option>Admin</option><option>Staff</option></select></div>
                <div class="form-group"><label>Password</label><input type="password" placeholder="******"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline">Cancel</button><button class="btn btn-primary">Add User</button></div>
        </div>
    </div>

    <!-- ===== MODAL: New Order ===== -->
    <div class="modal-overlay" id="modalOrder">
        <div class="modal-box">
            <div class="modal-header"><h3>Create New Order</h3><button class="modal-close" onclick="closeModal('modalOrder')"><i class="fas fa-times"></i></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Customer</label><input type="text" placeholder="Search or enter customer"></div>
                <div class="form-group"><label>Products</label><select><option>Fresh Apples - ₹199</option><option>Bread - ₹45</option><option>Olive Oil - ₹599</option></select></div>
                <div class="form-group"><label>Quantity</label><input type="number" value="1"></div>
                <div class="form-group"><label>Delivery Address</label><textarea rows="2" placeholder="Enter delivery address"></textarea></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline">Cancel</button><button class="btn btn-primary">Create Order</button></div>
        </div>
    </div>

    <!-- ===== MODAL: Patient Details ===== -->
    <div class="modal-overlay" id="modalPatient">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Patient Health Record Summary</h3>
                <button class="modal-close" onclick="closeModal('modalPatient')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="patientModalBody">
                <!-- Dynamically populated via JS -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('modalPatient')">Close</button>
            </div>
        </div>
    </div>

    <script>
        // ================================================================
        // GLOBALS
        // ================================================================
        let revenueChart, orderChart, reportRevenueChart;
        let maintenanceEnabled = false;

        // ================================================================
        // TOAST
        // ================================================================
        function toast(message, type = 'info') {
            const el = document.getElementById('toast');
            el.textContent = message;
            el.className = 'toast show ' + type;
            clearTimeout(el._timeout);
            el._timeout = setTimeout(() => { el.classList.remove('show'); }, 2500);
        }
        window.toast = toast;

        // ================================================================
        // MODAL
        // ================================================================
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        window.openModal = openModal;
        window.closeModal = closeModal;

        // ================================================================
        // SIDEBAR NAVIGATION
        // ================================================================
        const pageTitles = {
            dashboard: { title: 'Dashboard', icon: 'fa-tachometer-alt', breadcrumb: 'Dashboard' },
            analytics: { title: 'Analytics', icon: 'fa-chart-line', breadcrumb: 'Analytics' },
            orders: { title: 'Order Management', icon: 'fa-shopping-cart', breadcrumb: 'Orders' },
            products: { title: 'Product Management', icon: 'fa-box', breadcrumb: 'Products' },
            delivery: { title: 'Delivery Management', icon: 'fa-truck', breadcrumb: 'Delivery' },
            users: { title: 'User Management', icon: 'fa-users', breadcrumb: 'Users' },
            reports: { title: 'Reports & Analytics', icon: 'fa-chart-line', breadcrumb: 'Reports' },
            settings: { title: 'Shop Settings', icon: 'fa-cog', breadcrumb: 'Settings' },
            maintenance: { title: 'Maintenance Mode', icon: 'fa-tools', breadcrumb: 'Maintenance' },
            doctors: { title: 'Doctor Management', icon: 'fa-user-md', breadcrumb: 'Doctors' },
            policies: { title: 'Clinical Policies', icon: 'fa-sliders-h', breadcrumb: 'Policies' }
        };

        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.dataset.page;
                // Update URL query string
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=' + page;
                window.history.replaceState({path:newUrl}, '', newUrl);
                // Update nav
                document.querySelectorAll('.nav-item[data-page]').forEach(n => n.classList.remove('active'));
                this.classList.add('active');
                // Update pages
                document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
                const target = document.getElementById('page-' + page);
                if (target) target.classList.add('active');
                // Update header
                const info = pageTitles[page] || { title: page, icon: 'fa-tag', breadcrumb: page };
                document.getElementById('pageTitle').innerHTML = `<i class="fas ${info.icon}"></i> ${info.title}`;
                document.getElementById('breadcrumbActive').textContent = info.breadcrumb;
                // Close mobile sidebar
                document.getElementById('sidebar').classList.remove('mobile-open');
                // Refresh charts if needed
                if (page === 'dashboard' && revenueChart) { revenueChart.resize();
                    orderChart.resize(); }
                if (page === 'reports' && reportRevenueChart) { reportRevenueChart.resize(); }
            });
        });

        // Handle initial page load from URL query param
        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = urlParams.get('page');
        if (initialPage) {
            const navItem = document.querySelector(`.nav-item[data-page="${initialPage}"]`);
            if (navItem) {
                // Remove default active
                document.querySelectorAll('.nav-item[data-page]').forEach(n => n.classList.remove('active'));
                navItem.classList.add('active');
                document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
                const target = document.getElementById('page-' + initialPage);
                if (target) target.classList.add('active');
                const info = pageTitles[initialPage] || { title: initialPage, icon: 'fa-tag', breadcrumb: initialPage };
                document.getElementById('pageTitle').innerHTML = `<i class="fas ${info.icon}"></i> ${info.title}`;
                document.getElementById('breadcrumbActive').textContent = info.breadcrumb;
            }
        }

        // Switch doctor dashboard sub-tabs
        function switchDocTab(tabName, element) {
            document.querySelectorAll('.doctor-tab-content').forEach(el => {
                el.style.display = 'none';
            });
            document.getElementById('doc-tab-' + tabName).style.display = 'block';
            
            if (element) {
                const parent = element.parentElement;
                parent.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                element.classList.add('active');
            }
        }
        window.switchDocTab = switchDocTab;

        // Open patient modal dynamic renderer
        function openPatientModal(patData) {
            const body = document.getElementById('patientModalBody');
            body.innerHTML = `
                <div style="margin-bottom:14px;"><strong>Full Name:</strong> <span>${patData.patient_name}</span></div>
                <div style="grid-template-columns: 1fr 1fr; display: grid; gap: 14px; margin-bottom:14px;">
                    <div><strong>Date of Birth:</strong> <p>${patData.date_of_birth}</p></div>
                    <div><strong>Gender:</strong> <p>${patData.gender}</p></div>
                </div>
                <div style="grid-template-columns: 1fr 1fr; display: grid; gap: 14px; margin-bottom:14px;">
                    <div><strong>Mobile Number:</strong> <p>${patData.mobile_number}</p></div>
                    <div><strong>Emergency Contact:</strong> <p>${patData.emergency_contact}</p></div>
                </div>
                <div style="margin-bottom:14px;"><strong>Home Address:</strong> <p style="font-size:0.85rem; color:var(--text-secondary);">${patData.address}</p></div>
                <div style="border-top:1px solid var(--border-light); padding-top:14px; margin-bottom:10px;">
                    <div style="margin-bottom:8px;"><strong>Medical History:</strong>
                        <p style="background:var(--main-bg); padding:10px; border-radius:var(--radius-sm); font-size:0.8rem; color:var(--text-secondary);">${patData.medical_history || 'None declared'}</p>
                    </div>
                    <div style="margin-bottom:8px;"><strong>Allergies:</strong>
                        <p style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:var(--radius-sm); font-size:0.8rem;">${patData.allergies || 'No known allergies'}</p>
                    </div>
                    <div style="margin-bottom:8px;"><strong>Current Medications:</strong>
                        <p style="background:var(--main-bg); padding:10px; border-radius:var(--radius-sm); font-size:0.8rem; color:var(--text-secondary);">${patData.current_medications || 'None active'}</p>
                    </div>
                </div>
            `;
            openModal('modalPatient');
        }
        window.openPatientModal = openPatientModal;

        // ================================================================
        // SIDEBAR TOGGLE
        // ================================================================
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('mobile-open');
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
        }
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open')) {
                if (!sidebar.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // ================================================================
        // THEME TOGGLE
        // ================================================================
        document.getElementById('themeToggle').addEventListener('click', function() {
            document.body.classList.toggle('dark');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark')) icon.className = 'fas fa-sun';
            else icon.className = 'fas fa-moon';
            // Update chart colors
            const textColor = getComputedStyle(document.body).getPropertyValue('--text-primary');
            [revenueChart, orderChart, reportRevenueChart].forEach(chart => {
                if (chart) {
                    chart.options.plugins.legend.labels.color = textColor;
                    chart.update();
                }
            });
        });

        // ================================================================
        // FULLSCREEN
        // ================================================================
        document.getElementById('fullscreenBtn').addEventListener('click', function() {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen();
            else document.exitFullscreen();
        });

        // ================================================================
        // NOTIFICATION PANEL
        // ================================================================
        document.getElementById('notifBtn').addEventListener('click', function() {
            document.getElementById('notifPanel').classList.toggle('active');
        });
        document.getElementById('notifClose').addEventListener('click', function() {
            document.getElementById('notifPanel').classList.remove('active');
        });

        // ================================================================
        // LOGOUT
        // ================================================================
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                window.location.href = 'login.php?logout=1';
            });
        }

        // ================================================================
        // QUICK ACTION BUTTONS
        // ================================================================
        document.getElementById('quickAddProduct').addEventListener('click', () => openModal('modalProduct'));
        document.getElementById('quickCreateOrder').addEventListener('click', () => openModal('modalOrder'));
        document.getElementById('quickNotify').addEventListener('click', () => toast('Notification broadcast sent!', 'success'));
        document.getElementById('quickReport').addEventListener('click', () => toast('Generating sales report...', 'info'));

        // Page-specific add buttons
        document.getElementById('productsAddBtn').addEventListener('click', () => openModal('modalProduct'));
        document.getElementById('deliveryAddBtn').addEventListener('click', () => openModal('modalDelivery'));
        document.getElementById('usersAddBtn').addEventListener('click', () => openModal('modalUser'));
        document.getElementById('ordersAddBtn').addEventListener('click', () => openModal('modalOrder'));

        // ================================================================
        // SETTINGS NAVIGATION
        // ================================================================
        document.querySelectorAll('#settingsNav .nav-item').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#settingsNav .nav-item').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const target = this.dataset.settings;
                document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
                const section = document.getElementById('settings-' + target);
                if (section) section.classList.add('active');
            });
        });

        // ================================================================
        // MAINTENANCE TOGGLE
        // ================================================================
        document.getElementById('maintenanceToggle').addEventListener('change', function() {
            maintenanceEnabled = this.checked;
            const badge = document.getElementById('maintenanceBadge');
            if (maintenanceEnabled) {
                badge.textContent = 'Enabled';
                badge.className = 'maintenance-badge enabled';
            } else {
                badge.textContent = 'Disabled';
                badge.className = 'maintenance-badge disabled';
            }
            toast(maintenanceEnabled ? 'Maintenance mode enabled' : 'Maintenance mode disabled', 'info');
        });

        // Update maintenance preview in real-time
        ['maintenanceTitle', 'maintenanceMessage', 'maintenanceEta'].forEach(id => {
            document.getElementById(id).addEventListener('input', function() {
                const previewId = id.replace('maintenance', 'preview');
                const el = document.getElementById(previewId);
                if (el) el.textContent = this.value || (id === 'maintenanceTitle' ? 'Under Maintenance' : id ===
                    'maintenanceMessage' ? 'We are currently performing maintenance.' : 'Coming back shortly');
            });
        });

        // ================================================================
        // CHARTS
        // ================================================================
        function initCharts() {
            const ctx1 = document.getElementById('revenueChart').getContext('2d');
            const ctx2 = document.getElementById('orderChart').getContext('2d');
            const ctx3 = document.getElementById('reportRevenueChart').getContext('2d');

            const textColor = getComputedStyle(document.body).getPropertyValue('--text-primary');

            const revenueLabels = <?= json_encode($chartData['labels']) ?>;
            const revenueValues = <?= json_encode($chartData['values']) ?>;

            revenueChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueValues,
                        borderColor: '#3b82f6',
                        tension: 0.3,
                        fill: true,
                        backgroundColor: 'rgba(59,130,246,0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { labels: { color: textColor } } }
                }
            });

            orderChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
                    datasets: [{
                        label: 'Orders',
                        data: [<?= $pendingOrders ?>, 15, 20, <?= $totalOrders - $pendingOrders ?>, 5],
                        backgroundColor: '#f97316'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: textColor } } }
                }
            });

            reportRevenueChart = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueValues,
                        borderColor: '#3b82f6',
                        tension: 0.3,
                        fill: true,
                        backgroundColor: 'rgba(59,130,246,0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { labels: { color: textColor } } }
                }
            });
        }

        // ================================================================
        // CHART PERIOD UPDATES (demo simulation)
        // ================================================================
        document.getElementById('revenuePeriod').addEventListener('change', function() {
            if (!revenueChart) return;
            const val = this.value;
            if (val === '7') {
                revenueChart.data.labels = <?= json_encode($chartData['labels']) ?>;
                revenueChart.data.datasets[0].data = <?= json_encode($chartData['values']) ?>;
            } else {
                // Simulate 30-day data
                const labels = [];
                const values = [];
                for (let i = 29; i >= 0; i--) {
                    const d = new Date();
                    d.setDate(d.getDate() - i);
                    labels.push(d.toLocaleDateString('en', { weekday: 'short' }));
                    values.push(Math.floor(Math.random() * 50000) + 10000);
                }
                revenueChart.data.labels = labels;
                revenueChart.data.datasets[0].data = values;
            }
            revenueChart.update();
        });

        document.getElementById('orderPeriod').addEventListener('change', function() {
            if (!orderChart) return;
            const val = this.value;
            if (val === 'month') {
                orderChart.data.datasets[0].data = [<?= $pendingOrders ?>, 15, 20, <?= $totalOrders - $pendingOrders ?>, 5];
            } else {
                orderChart.data.datasets[0].data = [10, 25, 30, 40, 8];
            }
            orderChart.update();
        });

        document.getElementById('reportRevenuePeriod').addEventListener('change', function() {
            if (!reportRevenueChart) return;
            const val = this.value;
            if (val === '7') {
                reportRevenueChart.data.labels = <?= json_encode($chartData['labels']) ?>;
                reportRevenueChart.data.datasets[0].data = <?= json_encode($chartData['values']) ?>;
            } else {
                const labels = [];
                const values = [];
                for (let i = 29; i >= 0; i--) {
                    const d = new Date();
                    d.setDate(d.getDate() - i);
                    labels.push(d.toLocaleDateString('en', { weekday: 'short' }));
                    values.push(Math.floor(Math.random() * 50000) + 10000);
                }
                reportRevenueChart.data.labels = labels;
                reportRevenueChart.data.datasets[0].data = values;
            }
            reportRevenueChart.update();
        });

        // ================================================================
        // FILTER APPLY BUTTONS (demo)
        // ================================================================
        document.querySelectorAll('#orderApplyFilter, #productApplyFilter, #deliveryApplyFilter, #userApplyFilter')
        .forEach(btn => {
            btn.addEventListener('click', function() {
                toast('Filters applied', 'success');
            });
        });

        // ================================================================
        // SEARCH
        // ================================================================
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                toast('Searching for: ' + this.value, 'info');
            }
        });

        // ================================================================
        // REPORT EXPORT / PDF (demo)
        // ================================================================
        document.getElementById('reportExportBtn').addEventListener('click', function() {
            toast('Exporting CSV...', 'info');
        });
        document.getElementById('reportPdfBtn').addEventListener('click', function() {
            toast('Downloading PDF...', 'info');
        });

        // ================================================================
        // SELECT ALL checkboxes (demo)
        // ================================================================
        document.getElementById('orderSelectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.order-check').forEach(cb => cb.checked = this.checked);
        });

        // ================================================================
        // INIT
        // ================================================================
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            // Update delivery counts (hardcoded for demo)
            document.getElementById('delPendingCount').textContent = '3';
            document.getElementById('delActiveCount').textContent = '2';
            document.getElementById('delDeliveredCount').textContent = '3';
            // Set initial preview text
            document.getElementById('previewTitle').textContent = document.getElementById('maintenanceTitle').value ||
                'Under Maintenance';
            document.getElementById('previewMessage').textContent = document.getElementById('maintenanceMessage')
                .value || 'We are currently performing maintenance.';
            document.getElementById('previewEta').textContent = '⏱ ' + (document.getElementById('maintenanceEta')
                .value || 'Coming back shortly');
        });

        // ================================================================
        // RESIZE CHARTS ON WINDOW RESIZE
        // ================================================================
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                [revenueChart, orderChart, reportRevenueChart].forEach(chart => {
                    if (chart) chart.resize();
                });
            }, 200);
        });
    </script>

    <!-- Toast container (hidden by default) -->
    <div class="toast" id="toast"></div>

    <!-- Additional CSS for toast and modals -->
    <style>
        /* ===== TOAST ===== */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: 40px;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
            z-index: 5000;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            animation: toastIn 0.3s ease;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.info { background: var(--accent); }
        @keyframes toastIn {
            from { transform: translateX(-50%) translateY(20px); opacity: 0; }
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }

        /* ===== MODAL OVERLAY ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { transform: scale(0.95) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-light);
        }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .modal-close:hover { background: var(--main-bg); }
        .modal-body { padding: 20px 22px; }
        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-light); color: var(--text-primary); }
        .btn-outline:hover { background: var(--main-bg); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }

        /* ===== SETTINGS STYLES ===== */
        .settings-section { display: none; animation: fadeIn 0.3s ease; }
        .settings-section.active { display: block; }
        .settings-nav { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 20px; background: var(--card-bg); padding: 8px 12px; border-radius: var(--radius-lg); box-shadow: var(--shadow); }
        .settings-nav .nav-item {
            padding: 8px 16px;
            border-radius: var(--radius-md);
            border: none;
            background: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .settings-nav .nav-item:hover { background: var(--main-bg); color: var(--text-primary); }
        .settings-nav .nav-item.active { background: var(--accent); color: white; }
        .settings-card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow); margin-bottom: 16px; }
        .settings-card .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }

        /* ===== DAY SCHEDULE ===== */
        .day-schedule {
            background: var(--main-bg);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .day-schedule .day-header { display: flex; align-items: center; gap: 12px; }
        .day-schedule .time-slot { display: flex; align-items: center; gap: 8px; }
        .day-schedule .time-slot input[type="time"] { padding: 4px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-light); background: var(--card-bg); color: var(--text-primary); }

        /* ===== PAYMENT OPTIONS ===== */
        .payment-option { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
        .payment-option .payment-info { display: flex; align-items: center; gap: 12px; }
        .payment-option .payment-icon { width: 40px; height: 40px; border-radius: 50%; background: var(--main-bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--accent); }
        .payment-option .payment-info h4 { font-size: 0.9rem; font-weight: 500; }
        .payment-option .payment-info p { font-size: 0.75rem; color: var(--text-secondary); }

        /* ===== NOTIFICATION OPTIONS ===== */
        .notification-option { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-light); }
        .notification-option .notification-info h4 { font-size: 0.9rem; font-weight: 500; }
        .notification-option .notification-info p { font-size: 0.75rem; color: var(--text-secondary); }

        /* ===== STAFF CARD ===== */
        .staff-card { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--main-bg); border-radius: var(--radius-md); margin-bottom: 8px; }
        .staff-card .staff-info { display: flex; align-items: center; gap: 12px; }
        .staff-card .staff-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--card-bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--accent); }
        .staff-card .staff-info h4 { font-size: 0.9rem; font-weight: 500; }
        .staff-card .staff-info p { font-size: 0.75rem; color: var(--text-secondary); }
        .staff-card .staff-actions { display: flex; gap: 6px; }

        /* ===== INTEGRATION CARD ===== */
        .integration-card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 16px 18px; box-shadow: var(--shadow); margin-bottom: 14px; }
        .integration-card .integration-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .integration-card .integration-icon { width: 40px; height: 40px; border-radius: 50%; background: var(--main-bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--accent); }
        .integration-card .integration-info h4 { font-size: 0.95rem; font-weight: 500; }
        .integration-card .integration-info p { font-size: 0.75rem; color: var(--text-secondary); }
        .integration-card .integration-details { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .integration-card .integration-details p { font-size: 0.8rem; color: var(--text-secondary); }

        /* ===== TOGGLE SWITCH ===== */
        .toggle-switch { position: relative; display: inline-block; width: 52px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 30px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider { background-color: var(--accent); }
        input:checked + .toggle-slider:before { transform: translateX(24px); }

        /* ===== MAINTENANCE ===== */
        .maintenance-toggle-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow);
        }
        .maintenance-badge { padding: 6px 16px; border-radius: 40px; font-weight: 600; font-size: 0.8rem; }
        .maintenance-badge.enabled { background: #fee2e2; color: #b91c1c; }
        .maintenance-badge.disabled { background: #d1fae5; color: #065f46; }
        .maintenance-preview {
            background: var(--main-bg);
            border-radius: var(--radius-md);
            padding: 28px;
            text-align: center;
            border: 1px solid var(--border-light);
        }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .ip-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .ip-tag { background: var(--main-bg); padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; border: 1px solid var(--border-light); }
        .ip-tag button { background: none; border: none; cursor: pointer; color: var(--danger); }
        .add-ip { display: flex; gap: 8px; margin-top: 10px; }

        /* ===== PRODUCTS GRID ===== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .product-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: 0.2s;
        }
        .product-card:hover { transform: translateY(-4px); }
        .product-card .product-image {
            height: 160px;
            background: var(--main-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .product-card .product-image .product-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .product-badge.new { background: var(--accent); color: white; }
        .product-badge.stock { background: var(--success); color: white; }
        .product-card .product-info { padding: 14px 16px; }
        .product-card .product-title { font-weight: 600; font-size: 0.95rem; }
        .product-card .product-category { font-size: 0.75rem; color: var(--text-secondary); }
        .product-card .product-meta { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85rem; }
        .product-card .product-price { font-weight: 700; color: var(--accent); }
        .product-card .product-rating { margin-top: 6px; font-size: 0.75rem; color: var(--warning); }
        .product-card .product-rating .rating-count { color: var(--text-secondary); margin-left: 4px; }
        .product-card .product-status { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.7rem; }

        /* ===== DELIVERY CARD ===== */
        .delivery-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 16px;
            box-shadow: var(--shadow);
            border-left: 4px solid transparent;
            transition: 0.2s;
        }
        .delivery-card.pending { border-left-color: var(--warning); }
        .delivery-card.active-del { border-left-color: var(--accent); }
        .delivery-card.delivered { border-left-color: var(--success); opacity: 0.8; }
        .delivery-card .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .delivery-card .order-id { font-weight: 700; font-size: 1rem; color: var(--accent); }
        .delivery-card .customer-name { font-weight: 600; margin-bottom: 2px; }
        .delivery-card .address { font-size: 0.8rem; color: var(--text-secondary); display: flex; gap: 6px; align-items: flex-start; }
        .delivery-card .eta { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); margin-top: 8px; }
        .delivery-card .card-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }
        .filter-group { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .filter-item { display: flex; flex-direction: column; gap: 2px; }
        .filter-item label { font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); }
        .filter-item select, .filter-item input {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 0.8rem;
        }

        /* ===== PAGINATION ===== */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }
        .pagination-bar .pagination-info { font-size: 0.8rem; color: var(--text-secondary); }
        .pagination-bar .pagination-controls { display: flex; gap: 4px; align-items: center; }
        .pagination-bar .pagination-controls button {
            padding: 4px 10px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            border-radius: var(--radius-sm);
            cursor: pointer;
            color: var(--text-primary);
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .pagination-bar .pagination-controls button:hover { background: var(--main-bg); }
        .pagination-bar .pagination-controls button.active { background: var(--accent); color: white; border-color: var(--accent); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .settings-nav { overflow-x: auto; flex-wrap: nowrap; padding: 6px 10px; }
            .settings-nav .nav-item { white-space: nowrap; font-size: 0.7rem; padding: 6px 12px; }
            .settings-grid { grid-template-columns: 1fr; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-wrap: wrap; }
            .filter-item { flex: 1; min-width: 120px; }
            .charts-section { grid-template-columns: 1fr; }
            .tables-section { grid-template-columns: 1fr; }
            .quick-section { grid-template-columns: 1fr; }
        }
    </style>
</body>
</html>