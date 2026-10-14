<?php
// ============================================================
// admin/delivery.php - Delivery Operations (Full Integration)
// ============================================================
$currentPage = 'delivery';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';
$pdo = getDBConnection();

// Generate OTP
function generateOTP() {
    return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

// --- HANDLE OTP VERIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $order_id = (int)$_POST['order_id'];
    $otp = trim($_POST['otp_code']);
    
    // In production, fetch OTP from a separate table (e.g., delivery_otps)
    // For demo, OTP is '1234'
    if ($otp === '1234') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $stmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'delivered', 'OTP verified', ?)");
            $stmt->execute([$order_id, $_SESSION['admin_id'] ?? null]);
            
            $pdo->commit();
            $message = '✅ Order #' . $order_id . ' marked as delivered!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = '❌ Invalid OTP. Please try again.';
    }
    header('Location: delivery.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// --- ASSIGN DRIVER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver'])) {
    $order_id = (int)$_POST['order_id'];
    $driver_id = (int)$_POST['driver_id'];
    
    if ($driver_id > 0) {
        try {
            $pdo->beginTransaction();
            
            $otp = generateOTP();
            $stmt = $pdo->prepare("UPDATE orders SET driver_id = ?, status = 'out_for_delivery', assigned_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$driver_id, $order_id]);
            
            $stmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'out_for_delivery', CONCAT('Assigned to driver ID: ', ?), ?)");
            $stmt->execute([$order_id, $driver_id, $_SESSION['admin_id'] ?? null]);
            
            // Log notification to customer
            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, subject, message, link, is_read) VALUES (?, 'delivery', 'Order Out for Delivery', CONCAT('Your order #', ?, ' is out for delivery. OTP: ', ?), CONCAT('orders.php?view=', ?), 0)");
                $stmt->execute([$userId, $order_id, $otp, $order_id]);
            }
            
            $pdo->commit();
            $message = '✅ Driver assigned. OTP for customer: ' . $otp;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select a delivery driver.';
    }
    header('Location: delivery.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}


// --- FETCH DATA ---
$today = date('Y-m-d');

// Stats
$todayOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn();
$acceptedOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE driver_id IS NOT NULL AND status IN ('confirmed', 'picked', 'out_for_delivery')")->fetchColumn();
$deliveredOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = '$today'")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE driver_id IS NULL AND status = 'pending'")->fetchColumn();

$collectedCash = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_method = 'cod' AND status = 'delivered' AND DATE(delivered_at) = '$today'")->fetchColumn();
$onlinePayments = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_method != 'cod' AND status = 'delivered' AND DATE(delivered_at) = '$today'")->fetchColumn();

$dailyEarnings = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = '$today'")->fetchColumn();
$weeklyEarnings = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'delivered' AND delivered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$monthlyEarnings = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'delivered' AND MONTH(delivered_at) = MONTH(CURDATE()) AND YEAR(delivered_at) = YEAR(CURDATE())")->fetchColumn();

// Active Deliveries (with customer & driver info)
$activeDeliveries = $pdo->query("
    SELECT o.*, 
           o.name as customer_name, o.phone as customer_phone,
           d.fullname as driver_name, d.phone as driver_phone,
           o.address as address_line1, o.city, o.state, o.pincode as zip_code
    FROM orders o
    LEFT JOIN users d ON o.driver_id = d.id
    WHERE o.status IN ('pending', 'confirmed', 'picked', 'out_for_delivery')
    ORDER BY o.created_at DESC
    LIMIT 50
")->fetchAll();

// Delivery Boys for dropdown
$deliveryBoys = $pdo->query("
    SELECT id, fullname AS name, phone, 'Motorcycle' AS vehicle_type, 1 AS is_online 
    FROM users 
    WHERE role = 'delivery'
    ORDER BY fullname ASC
")->fetchAll();

// Notifications (latest 5)
$notifications = $pdo->query("
    SELECT * FROM notifications 
    WHERE type IN ('delivery', 'order')
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Handle messages
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Delivery Management | KSR GURU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & VARIABLES ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
        }
        body { font-family: 'Inter', sans-serif; background: var(--gray-100); color: var(--gray-800); }

        /* ===== SIDEBAR (same as other admin pages) ===== */
        .sidebar {
            width: 280px;
            background: #0f172a;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .admin-logo { display: flex; align-items: center; gap: 12px; }
        .admin-logo i { font-size: 28px; color: var(--primary); }
        .admin-logo h2 { font-size: 1.3rem; font-weight: 700; color: white; }
        .admin-logo p { font-size: 0.65rem; color: #94a3b8; }
        .admin-profile { padding: 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size: 42px; color: #94a3b8; }
        .profile-info h3 { font-size: 0.95rem; font-weight: 600; color: white; }
        .profile-info p { font-size: 0.7rem; color: #94a3b8; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-section h4 { padding: 0 20px; font-size: 0.65rem; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 9px 20px; color: #e2e8f0; transition: 0.2s; font-size: 0.9rem; border-left: 3px solid transparent; text-decoration: none; }
        .nav-item i { width: 22px; text-align: center; }
        .nav-item.active { background: rgba(59,130,246,0.15); border-left-color: var(--primary); color: white; }
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .sidebar-footer { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.08); }
        .logout-btn { width: 100%; background: rgba(239,68,68,0.15); border: none; padding: 10px; border-radius: 12px; color: #f87171; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s; cursor: pointer; }
        .logout-btn:hover { background: rgba(239,68,68,0.3); }

        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: 280px; padding: 20px 28px; min-height: 100vh; }
        .top-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .header-left h1 { font-size: 1.6rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-left h1 i { color: var(--primary); }
        .breadcrumb { color: var(--gray-600); font-size: 0.8rem; }
        .header-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .user-menu { display: flex; align-items: center; gap: 10px; background: white; padding: 4px 14px 4px 10px; border-radius: 50px; cursor: pointer; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); }
        .user-avatar i { font-size: 28px; color: var(--primary); }
        .user-info .user-name { font-weight: 600; font-size: 0.85rem; }
        .user-info .user-role { font-size: 0.65rem; color: var(--gray-600); display: block; }

        /* ===== STATS CARDS ===== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-md);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-icon.pending { background: #fef3c7; color: #d97706; }
        .stat-icon.active { background: #dbeafe; color: #2563eb; }
        .stat-icon.delivered { background: #d1fae5; color: #059669; }
        .stat-icon.earnings { background: #e0e7ff; color: #4f46e5; }
        .stat-info h3 { font-size: 1.75rem; font-weight: 800; line-height: 1.2; }
        .stat-info p { color: var(--gray-600); font-size: 0.875rem; }

        /* ===== FILTER & SEARCH ===== */
        .filter-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .search-box {
            flex: 1;
            min-width: 240px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--gray-50);
            padding: 0.625rem 1rem;
            border-radius: 2rem;
            border: 1px solid var(--gray-200);
        }
        .search-box i { color: var(--gray-600); }
        .search-box input { border: none; background: transparent; outline: none; flex: 1; font-size: 0.9rem; }
        .filter-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            border: 1px solid var(--gray-200);
            background: white;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .filter-btn:hover:not(.active) { background: var(--gray-50); }

        /* ===== DELIVERY CARDS ===== */
        .delivery-list h2 {
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge { background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 2rem; font-size: 0.875rem; }
        .deliveries-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.5rem;
        }
        .delivery-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--gray-200);
            transition: all 0.2s;
        }
        .delivery-card.status-pending { border-left-color: var(--warning); }
        .delivery-card.status-confirmed { border-left-color: var(--primary); }
        .delivery-card.status-picked { border-left-color: #8b5cf6; }
        .delivery-card.status-out_for_delivery { border-left-color: #06b6d4; }
        .delivery-card.status-delivered { border-left-color: var(--success); opacity: 0.85; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .order-id { font-weight: 700; font-size: 1.1rem; color: var(--primary); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; }
        .status-badge.pending { background: #fef3c7; color: #b45309; }
        .status-badge.confirmed { background: #dbeafe; color: #1e40af; }
        .status-badge.picked { background: #ede9fe; color: #6d28d9; }
        .status-badge.out_for_delivery { background: #cffafe; color: #0e7490; }
        .status-badge.delivered { background: #d1fae5; color: #065f46; }
        .customer-info { margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--gray-200); }
        .customer-name { font-weight: 600; margin-bottom: 0.25rem; }
        .address { font-size: 0.875rem; color: var(--gray-600); display: flex; gap: 0.5rem; align-items: flex-start; }
        .address i { margin-top: 0.2rem; }
        .eta { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: var(--gray-600); margin-top: 0.75rem; }
        .card-actions { display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; justify-content: flex-end; }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); }
        .btn-outline:hover { background: var(--gray-100); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.75rem; }

        /* ===== MODALS ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { transform: scale(0.95) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-600); }
        .modal-close:hover { color: var(--gray-900); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.9rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }

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
            box-shadow: var(--shadow-lg);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.info { background: var(--primary); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-container { grid-template-columns: 1fr; }
            .deliveries-container { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'delivery'; include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-truck"></i> Delivery Management</h1>
                <div class="breadcrumb">Admin / Delivery</div>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                        <span class="user-role">Admin</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Toast Messages -->
        <?php if ($message): ?>
            <div class="toast show success" style="position:relative;transform:none;left:auto;margin-bottom:16px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="toast show error" style="position:relative;transform:none;left:auto;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($pendingOrders) ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active"><i class="fas fa-truck-moving"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($acceptedOrders) ?></h3>
                    <p>Active Deliveries</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon delivered"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?= number_format($deliveredOrders) ?></h3>
                    <p>Delivered Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon earnings"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-info">
                    <h3>₹<?= number_format($dailyEarnings, 2) ?></h3>
                    <p>Today's Earnings</p>
                </div>
            </div>
        </div>

        <!-- Additional stats (cash/online) -->
        <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
            <div style="background:white;border-radius:var(--radius-lg);padding:0.75rem 1.25rem;box-shadow:var(--shadow-sm);">
                <span style="color:var(--gray-600);">💵 Collected Cash: <strong>₹<?= number_format($collectedCash, 2) ?></strong></span>
            </div>
            <div style="background:white;border-radius:var(--radius-lg);padding:0.75rem 1.25rem;box-shadow:var(--shadow-sm);">
                <span style="color:var(--gray-600);">💳 Online Payments: <strong>₹<?= number_format($onlinePayments, 2) ?></strong></span>
            </div>
            <div style="background:white;border-radius:var(--radius-lg);padding:0.75rem 1.25rem;box-shadow:var(--shadow-sm);">
                <span style="color:var(--gray-600);">📊 Weekly Earnings: <strong>₹<?= number_format($weeklyEarnings, 2) ?></strong></span>
            </div>
            <div style="background:white;border-radius:var(--radius-lg);padding:0.75rem 1.25rem;box-shadow:var(--shadow-sm);">
                <span style="color:var(--gray-600);">📆 Monthly Earnings: <strong>₹<?= number_format($monthlyEarnings, 2) ?></strong></span>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by order ID, customer, or address...">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="pending">Pending</button>
                <button class="filter-btn" data-filter="confirmed">Confirmed</button>
                <button class="filter-btn" data-filter="out_for_delivery">Out for Delivery</button>
                <button class="filter-btn" data-filter="delivered">Delivered</button>
            </div>
        </div>

        <!-- Delivery List -->
        <section class="delivery-list">
            <h2>Active Deliveries <span class="badge" id="deliveryCount"><?= count($activeDeliveries) ?></span></h2>
            <div class="deliveries-container" id="deliveriesContainer">
                <?php if (empty($activeDeliveries)): ?>
                    <div class="empty-state" style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--gray-500);">
                        <i class="fas fa-box-open" style="font-size:4rem;margin-bottom:1rem;opacity:0.5;"></i>
                        <p>No deliveries found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeDeliveries as $del): ?>
                        <div class="delivery-card status-<?= $del['status'] ?>" 
                             data-status="<?= $del['status'] ?>"
                             data-search="<?= strtolower($del['order_number'] . ' ' . $del['customer_name'] . ' ' . ($del['address_line1'] ?? '')) ?>">
                            <div class="card-header">
                                <span class="order-id">#<?= htmlspecialchars($del['order_number'] ?? $del['id']) ?></span>
                                <span class="status-badge <?= $del['status'] ?>"><?= ucfirst(str_replace('_', ' ', $del['status'])) ?></span>
                            </div>
                            <div class="customer-info">
                                <div class="customer-name"><i class="fas fa-user"></i> <?= htmlspecialchars($del['customer_name'] ?? 'Guest') ?></div>
                                <div class="address"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(($del['address_line1'] ?? '') . ', ' . ($del['city'] ?? '') . ' ' . ($del['state'] ?? '') . ' - ' . ($del['zip_code'] ?? '')) ?></div>
                                <?php if (!empty($del['customer_phone'])): ?>
                                    <div style="font-size:0.85rem;color:var(--gray-600);margin-top:0.25rem;">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($del['customer_phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($del['driver_name'])): ?>
                                <div style="font-size:0.85rem;color:var(--gray-600);margin-bottom:0.5rem;">
                                    <i class="fas fa-user-tie"></i> Driver: <?= htmlspecialchars($del['driver_name']) ?>
                                    <?php if (!empty($del['driver_phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($del['driver_phone']) ?>" style="color:var(--primary);margin-left:0.5rem;">
                                            <i class="fas fa-phone-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="eta">
                                <i class="fas fa-clock"></i>
                                <span>
                                    <?php if ($del['status'] === 'delivered'): ?>
                                        Delivered on <?= date('d M, h:i A', strtotime($del['delivered_at'] ?? $del['updated_at'] ?? 'now')) ?>
                                    <?php elseif (!empty($del['assigned_at'])): ?>
                                        Assigned: <?= date('h:i A', strtotime($del['assigned_at'])) ?>
                                    <?php else: ?>
                                        Created: <?= date('d M, h:i A', strtotime($del['created_at'] ?? 'now')) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="card-actions">
                                <?php if ($del['status'] === 'pending' || $del['status'] === 'confirmed'): ?>
                                    <button class="btn btn-primary btn-sm assign-driver-btn" data-id="<?= $del['id'] ?>">
                                        <i class="fas fa-user-plus"></i> Assign Driver
                                    </button>
                                <?php endif; ?>
                                <?php if ($del['status'] === 'out_for_delivery'): ?>
                                    <button class="btn btn-success btn-sm verify-otp-btn" data-id="<?= $del['id'] ?>">
                                        <i class="fas fa-shield-alt"></i> Verify OTP
                                    </button>
                                <?php endif; ?>
                                <a href="orders.php?view=<?= $del['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ============================================================ -->
    <!-- MODAL: Assign Driver -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="assignDriverModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Assign Delivery Driver</h3>
                <button class="modal-close" onclick="closeModal('assignDriverModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="delivery.php">
                <input type="hidden" name="order_id" id="assignOrderId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Driver</label>
                        <select name="driver_id" id="driverSelect" required>
                            <option value="">— Select a driver —</option>
                            <?php foreach ($deliveryBoys as $db): ?>
                                <option value="<?= $db['id'] ?>">
                                    <?= htmlspecialchars($db['name'] ?? '') ?> (<?= htmlspecialchars($db['vehicle_type'] ?? 'N/A') ?>)
                                    <?= !empty($db['is_online']) ? '🟢 Online' : '🔴 Offline' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p style="font-size:0.85rem;color:var(--gray-600);">
                        <i class="fas fa-info-circle"></i> An OTP will be generated and sent to the customer.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('assignDriverModal')">Cancel</button>
                    <button type="submit" name="assign_driver" class="btn btn-primary">Assign Driver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MODAL: Verify OTP -->
    <!-- ============================================================ -->
    <div class="modal-overlay" id="otpModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>OTP Verification</h3>
                <button class="modal-close" onclick="closeModal('otpModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="delivery.php">
                <input type="hidden" name="order_id" id="otpOrderId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Enter OTP (4-digit)</label>
                        <input type="text" name="otp_code" id="otpCode" maxlength="4" placeholder="1234" required autocomplete="off" style="text-align:center;font-size:1.5rem;letter-spacing:8px;">
                        <small style="color:var(--gray-600);display:block;margin-top:0.25rem;">Demo OTP: <strong>1234</strong></small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('otpModal')">Cancel</button>
                    <button type="submit" name="verify_otp" class="btn btn-success"><i class="fas fa-check"></i> Verify & Complete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================================ -->
    <script>
        // ===== MODAL HELPERS =====
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        // ===== ASSIGN DRIVER =====
        document.querySelectorAll('.assign-driver-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('assignOrderId').value = this.dataset.id;
                openModal('assignDriverModal');
            });
        });

        // ===== VERIFY OTP =====
        document.querySelectorAll('.verify-otp-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('otpOrderId').value = this.dataset.id;
                document.getElementById('otpCode').value = '';
                openModal('otpModal');
            });
        });

        // Auto-submit OTP on 4 digits
        document.getElementById('otpCode').addEventListener('input', function(e) {
            if (this.value.length === 4) {
                this.form.submit();
            }
        });

        // ===== SEARCH & FILTER =====
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        let currentFilter = 'all';

        function filterDeliveries() {
            const searchTerm = searchInput.value.toLowerCase();
            const cards = document.querySelectorAll('.delivery-card');
            cards.forEach(card => {
                const status = card.dataset.status;
                const searchData = card.dataset.search || '';
                let show = true;
                if (currentFilter !== 'all' && status !== currentFilter) show = false;
                if (searchTerm && !searchData.includes(searchTerm)) show = false;
                card.style.display = show ? '' : 'none';
            });
            // Update count
            const visible = document.querySelectorAll('.delivery-card[style*="display: none"]');
            const total = cards.length;
            const shown = total - visible.length;
            document.getElementById('deliveryCount').textContent = shown;
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                filterDeliveries();
            });
        });

        searchInput.addEventListener('input', filterDeliveries);

        // ===== CLOSE MODALS ON BACKDROP CLICK =====
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        // ===== AUTO-HIDE TOAST =====
        setTimeout(() => {
            document.querySelectorAll('.toast.show').forEach(toast => {
                setTimeout(() => toast.style.opacity = '0', 4000);
            });
        }, 1000);
    </script>
</body>
</html>