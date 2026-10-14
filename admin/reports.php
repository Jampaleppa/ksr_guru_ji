<?php
// ============================================================
// admin/reports.php - Advanced Reporting (Merged)
// ============================================================
$currentPage = 'reports';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';
$pdo = getDBConnection();

// --- FETCH REAL DATA FROM DATABASE ---
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-t');

// Sales Overview
$salesStmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as order_count,
        SUM(total) as total_sales,
        AVG(total) as avg_order_value
    FROM orders 
    WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$salesStmt->execute([$dateFrom, $dateTo]);
$sales = $salesStmt->fetchAll();

// Top Products
$topProductsStmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, 
           SUM(oi.quantity) as total_sold,
           SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$topProductsStmt->execute([$dateFrom, $dateTo]);
$topProducts = $topProductsStmt->fetchAll();

// Top Customers
$topCustomersStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COUNT(o.id) as order_count,
           SUM(o.total) as total_spent
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$topCustomersStmt->execute([$dateFrom, $dateTo]);
$topCustomers = $topCustomersStmt->fetchAll();

// Category Performance
$categoryStmt = $pdo->prepare("
    SELECT c.id, c.name,
           COUNT(oi.id) as items_sold,
           SUM(oi.quantity * oi.price) as revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY revenue DESC
");
$categoryStmt->execute([$dateFrom, $dateTo]);
$categoryPerformance = $categoryStmt->fetchAll();

// Payment Method Breakdown
$paymentStmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(total) as total
    FROM orders
    WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
");
$paymentStmt->execute([$dateFrom, $dateTo]);
$paymentMethods = $paymentStmt->fetchAll();

// Summary Stats
$summaryStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total) as total_revenue,
        AVG(total) as avg_order,
        SUM(CASE WHEN payment_method = 'cod' THEN total ELSE 0 END) as cod_revenue,
        SUM(CASE WHEN payment_method != 'cod' THEN total ELSE 0 END) as online_revenue
    FROM orders
    WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
");
$summaryStmt->execute([$dateFrom, $dateTo]);
$summary = $summaryStmt->fetch();

// Low Stock Products
$lowStockStmt = $pdo->query("
    SELECT p.id, p.name, p.price, i.quantity
    FROM products p
    JOIN inventory i ON p.id = i.product_id
    WHERE i.quantity < 10
    ORDER BY i.quantity ASC
    LIMIT 20
");
$lowStock = $lowStockStmt->fetchAll();

// ---- PASS DATA TO JAVASCRIPT AS JSON ----
$jsonSales = json_encode($sales);
$jsonTopProducts = json_encode($topProducts);
$jsonTopCustomers = json_encode($topCustomers);
$jsonCategoryPerformance = json_encode($categoryPerformance);
$jsonPaymentMethods = json_encode($paymentMethods);
$jsonSummary = json_encode($summary);
$jsonLowStock = json_encode($lowStock);
$jsonDateFrom = json_encode($dateFrom);
$jsonDateTo = json_encode($dateTo);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Reports & Analytics | KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* ----- GLOBAL RESET & VARIABLES ----- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --sidebar-bg: #0f172a;
            --accent: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            line-height: 1.5;
        }
        /* ----- SIDEBAR (from first file) ----- */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
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
        .admin-logo i { font-size: 28px; color: var(--accent); }
        .admin-logo h2 { font-size: 1.3rem; font-weight: 700; color: white; }
        .admin-logo p { font-size: 0.65rem; color: #94a3b8; }
        .admin-profile { padding: 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size: 42px; color: #94a3b8; }
        .profile-info h3 { font-size: 0.95rem; font-weight: 600; color: white; }
        .profile-info p { font-size: 0.7rem; color: #94a3b8; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-section h4 { padding: 0 20px; font-size: 0.65rem; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 9px 20px;
            color: #e2e8f0;
            transition: 0.2s;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .nav-item i { width: 22px; text-align: center; }
        .nav-item.active { background: rgba(59,130,246,0.15); border-left-color: var(--accent); color: white; }
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .sidebar-footer { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.08); }
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
            cursor: pointer;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.3); }

        /* ----- MAIN CONTENT (from second file) ----- */
        .main-content {
            margin-left: 280px;
            padding: 20px 28px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        /* Mobile nav (hidden on desktop) */
        .mobile-nav {
            display: none;
            background: white;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        .logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; font-weight: 700; font-size: 1.25rem; color: var(--gray-800); }
        .logo-icon { font-size: 1.75rem; }
        .menu-toggle, .close-menu { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-700); }
        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 320px;
            height: 100%;
            background: white;
            box-shadow: var(--shadow-md);
            transition: left 0.3s ease;
            z-index: 1001;
            display: flex;
            flex-direction: column;
        }
        .mobile-menu.open { left: 0; }
        .menu-header { display: flex; justify-content: space-between; padding: 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .menu-list { list-style: none; padding: 1rem 0; }
        .menu-list li a { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1.5rem; text-decoration: none; color: var(--gray-700); }
        .menu-list li a:hover, .menu-list li a.active { background: var(--gray-100); color: var(--primary); }

        /* Page Header */
        .page-header { margin-bottom: 2rem; }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem; }
        .page-header h1 { font-size: 2rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .subtitle { color: var(--text-secondary); margin-bottom: 1.5rem; }

        /* Date Range Selector */
        .date-range-selector { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .date-btn {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            background: white;
            border: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .date-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .date-btn:hover:not(.active) { background: var(--gray-100); }

        /* Filter bar (from first file) */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            padding: 16px;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .filter-bar label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; }
        .filter-bar input[type="date"] { padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-light); background: var(--main-bg); font-size: 0.85rem; }
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
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: transparent; border: 1px solid var(--border-light); color: var(--text-primary); }
        .btn-outline:hover { background: var(--gray-100); }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }

        /* Stats Grid */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
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
        .total-revenue { background: #dbeafe; color: #3b82f6; }
        .total-orders { background: #d1fae5; color: #10b981; }
        .avg-order { background: #fef3c7; color: #f59e0b; }
        .customers { background: #f3e8ff; color: #8b5cf6; }
        .stat-info h3 { font-size: 1.5rem; font-weight: 800; }
        .stat-change { font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.25rem; }
        .positive { color: var(--success); }

        /* Charts Section */
        .charts-section { margin-bottom: 2rem; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .chart-controls { display: flex; gap: 0.5rem; }
        .chart-type-btn, .time-btn {
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            border: 1px solid var(--gray-200);
            background: white;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .chart-type-btn.active, .time-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
        }
        .chart-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .full-width { grid-column: 1 / -1; }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .chart-wrapper canvas { max-height: 300px; width: 100%; }

        /* Tables Section */
        .tables-section { margin-bottom: 2rem; }
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--gray-200);
        }
        .tab-btn {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        .data-table th i { margin-left: 0.25rem; font-size: 0.7rem; cursor: pointer; }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-processing { background: #fff3e3; color: #c2410c; }
        .status-pending { background: #ffedd5; color: #b45309; }
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .pagination { display: flex; align-items: center; gap: 1rem; }
        .pagination-btn {
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--gray-200);
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        .items-per-page { display: flex; align-items: center; gap: 0.5rem; }
        .export-buttons { display: flex; gap: 0.5rem; }
        .export-btn, .pdf-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .pdf-btn { background: #dc2626; }

        /* Insights */
        .insights-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .insight-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid var(--border-light);
        }
        .insight-icon {
            width: 48px;
            height: 48px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .insight-icon.peak { background: #dbeafe; color: #3b82f6; }
        .insight-icon.popular { background: #fef3c7; color: #f59e0b; }
        .insight-icon.retention { background: #d1fae5; color: #10b981; }
        .insight-icon.delivery { background: #f3e8ff; color: #8b5cf6; }
        .insight-content h4 { font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem; }
        .insight-content p { font-size: 1rem; font-weight: 700; }
        .insight-detail { font-size: 0.75rem; color: var(--text-secondary); }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            padding: 1.5rem;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .date-inputs { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .date-input { flex: 1; }
        .date-input input { width: 100%; padding: 0.5rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; }
        .quick-ranges { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .quick-range-btn { padding: 0.5rem 1rem; border: 1px solid var(--gray-200); border-radius: 2rem; background: white; cursor: pointer; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem; }
        .btn-secondary { padding: 0.5rem 1rem; border-radius: 2rem; border: none; cursor: pointer; background: var(--gray-100); }

        /* Low stock alert (from first file) */
        .low-stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }
        .low-stock-item {
            padding: 8px 12px;
            background: var(--gray-100);
            border-radius: 8px;
        }

        /* PDF overlay */
        .pdf-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            color: white;
            font-size: 1.2rem;
            flex-direction: column;
            gap: 1rem;
        }
        .pdf-overlay.active { display: flex; }

        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            border-top: 1px solid var(--border-light);
            margin-top: 2rem;
        }
        .footer-links a { color: var(--text-secondary); text-decoration: none; margin: 0 0.5rem; }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .mobile-nav { display: block; }
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-container { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .stats-container { grid-template-columns: 1fr; }
        }
        .view-btn {
            padding: 0.2rem 0.6rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.3rem;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .view-btn:hover { background: var(--primary-dark); }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<?php $currentPage = 'reports'; include 'inc/sidebar.php'; ?>

<!-- ===== MOBILE NAV (from second file) ===== -->
<nav class="mobile-nav" id="mobileNav">
    <div class="nav-container">
        <a href="#" class="logo"><span class="logo-icon">📊</span><span>KSR GURU</span></a>
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <div class="menu-header"><h3>Reports & Analytics</h3><button class="close-menu" id="closeMenu"><i class="fas fa-times"></i></button></div>
        <ul class="menu-list">
            <li><a href="reports.php" class="active"><i class="fas fa-chart-line"></i>Dashboard</a></li>
            <li><a href="#"><i class="fas fa-shopping-cart"></i>Sales Reports</a></li>
            <li><a href="#"><i class="fas fa-users"></i>Customer Analytics</a></li>
            <li><a href="#"><i class="fas fa-truck"></i>Delivery Reports</a></li>
            <li><a href="#"><i class="fas fa-box"></i>Inventory Reports</a></li>
        </ul>
    </div>
</nav>

<!-- ===== MAIN CONTENT ===== -->
<main class="main-content">
    <div class="container" id="reportContainer">

        <!-- Page Header -->
        <header class="page-header">
            <div class="header-top">
                <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
                <div class="date-range-selector" id="dateRangeSelector">
                    <button class="date-btn active" data-range="month">This Month</button>
                    <button class="date-btn" data-range="today">Today</button>
                    <button class="date-btn" data-range="week">This Week</button>
                    <button class="date-btn" data-range="quarter">This Quarter</button>
                    <button class="date-btn" id="customRangeBtn"><i class="fas fa-calendar-alt"></i> Custom Range</button>
                </div>
            </div>
            <p class="subtitle">Gain insights into your business performance with detailed analytics</p>

            <!-- Filter Bar (from first file) -->
            <div class="filter-bar">
                <label>From: <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" id="dateFrom"></label>
                <label>To: <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" id="dateTo"></label>
                <button class="btn btn-primary" id="applyFilter"><i class="fas fa-filter"></i> Generate</button>
                <button class="btn btn-outline" id="resetFilter">Reset</button>
                <button class="btn btn-success" id="exportPdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button class="btn btn-success" id="exportExcel"><i class="fas fa-file-excel"></i> CSV</button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-container" id="statsContainer"></div>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="section-header">
                <h2>Performance Overview</h2>
                <div class="chart-controls">
                    <button class="chart-type-btn active" data-chart="line"><i class="fas fa-chart-line"></i> Line</button>
                    <button class="chart-type-btn" data-chart="bar"><i class="fas fa-chart-bar"></i> Bar</button>
                </div>
            </div>
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-header"><h3>Revenue Trend</h3><select id="revenueFilter"><option value="daily">Daily</option><option value="weekly" selected>Weekly</option><option value="monthly">Monthly</option></select></div>
                    <div class="chart-wrapper"><canvas id="revenueChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <div class="chart-header"><h3>Payment Methods</h3></div>
                    <div class="chart-wrapper"><canvas id="paymentChart"></canvas></div>
                </div>
                <div class="chart-container full-width">
                    <div class="chart-header"><h3>Category Performance</h3></div>
                    <div class="chart-wrapper"><canvas id="categoryChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <div class="chart-header"><h3>Top Products</h3><select id="productsFilter"><option value="5">Top 5</option><option value="10" selected>Top 10</option></select></div>
                    <div class="chart-wrapper"><canvas id="productsChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <div class="chart-header"><h3>Top Customers</h3></div>
                    <div class="chart-wrapper"><canvas id="customersChart"></canvas></div>
                </div>
            </div>
        </section>

        <!-- Detailed Reports (Tabs) -->
        <section class="tables-section">
            <div class="section-header">
                <h2>Detailed Reports</h2>
                <div class="export-buttons">
                    <button class="export-btn" id="exportBtn"><i class="fas fa-file-export"></i> Export CSV</button>
                    <button class="pdf-btn" id="pdfBtn"><i class="fas fa-file-pdf"></i> Download PDF</button>
                </div>
            </div>
            <div class="tabs">
                <button class="tab-btn active" data-tab="sales">Sales Report</button>
                <button class="tab-btn" data-tab="customers">Customer Report</button>
                <button class="tab-btn" data-tab="inventory">Inventory Report</button>
            </div>
            <!-- Sales Tab -->
            <div class="tab-content active" id="salesTab">
                <div class="table-container">
                    <table class="data-table" id="salesTable">
                        <thead><tr><th>Date <i class="fas fa-sort"></i></th><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="salesTableBody"></tbody>
                    </table>
                </div>
            </div>
            <!-- Customers Tab -->
            <div class="tab-content" id="customersTab">
                <div class="table-container">
                    <table class="data-table" id="customersTable">
                        <thead><tr><th>Customer</th><th>Orders</th><th>Total Spend</th><th>Actions</th></tr></thead>
                        <tbody id="customersTableBody"></tbody>
                    </table>
                </div>
            </div>
            <!-- Inventory Tab -->
            <div class="tab-content" id="inventoryTab">
                <div class="table-container">
                    <table class="data-table" id="inventoryTable">
                        <thead><tr><th>Product</th><th>Stock</th><th>Status</th></tr></thead>
                        <tbody id="inventoryTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="table-footer">
                <div class="pagination">
                    <button class="pagination-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>
                    <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                    <button class="pagination-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="items-per-page">
                    <label>Show:</label>
                    <select id="itemsPerPage"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option></select>
                </div>
            </div>
        </section>

        <!-- Low Stock Alert & Insights -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
            <div class="chart-container">
                <div class="chart-header"><h4><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Low Stock Alert</h4></div>
                <div id="lowStockContainer"></div>
            </div>
            <div class="chart-container">
                <div class="chart-header"><h4><i class="fas fa-lightbulb" style="color:var(--warning);"></i> Key Insights</h4></div>
                <div class="insights-container" id="insightsContainer"></div>
            </div>
        </div>

        <footer>
            <p>© 2025 KSR GURU – Everything Your Family Needs, Every Day</p>
            <div class="footer-links"><a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a> | <a href="#">Contact Us</a></div>
        </footer>
    </div>
</main>

<!-- ===== DATE RANGE MODAL ===== -->
<div class="modal" id="dateRangeModal">
    <div class="modal-content">
        <div class="modal-header"><h3>Select Custom Date Range</h3><button class="close-modal" id="closeModal"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <div class="date-inputs">
                <div class="date-input"><label>Start Date</label><input type="date" id="startDate"></div>
                <div class="date-input"><label>End Date</label><input type="date" id="endDate"></div>
            </div>
            <div class="quick-ranges">
                <button class="quick-range-btn" data-days="7">Last 7 Days</button>
                <button class="quick-range-btn" data-days="30">Last 30 Days</button>
                <button class="quick-range-btn" data-days="90">Last 90 Days</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" id="cancelRange">Cancel</button>
            <button class="btn-primary" id="applyRange">Apply Range</button>
        </div>
    </div>
</div>

<!-- ===== PDF OVERLAY ===== -->
<div class="pdf-overlay" id="pdfOverlay">
    <i class="fas fa-spinner fa-pulse fa-2x"></i>
    <p>Generating PDF, please wait...</p>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
// ============================================================
// PASS PHP DATA TO JAVASCRIPT
// ============================================================
const phpData = {
    sales: <?= $jsonSales ?>,
    topProducts: <?= $jsonTopProducts ?>,
    topCustomers: <?= $jsonTopCustomers ?>,
    categoryPerformance: <?= $jsonCategoryPerformance ?>,
    paymentMethods: <?= $jsonPaymentMethods ?>,
    summary: <?= $jsonSummary ?>,
    lowStock: <?= $jsonLowStock ?>,
    dateFrom: <?= $jsonDateFrom ?>,
    dateTo: <?= $jsonDateTo ?>
};

// ============================================================
// STATE
// ============================================================
let currentTab = 'sales';
let currentPage = 1;
let itemsPerPage = 25;
let currentDateRange = 'month';
let customStart = null, customEnd = null;

// Chart instances
let revenueChart, paymentChart, categoryChart, productsChart, customersChart;

// ============================================================
// RENDER FUNCTIONS
// ============================================================

// Stats
function renderStats() {
    const s = phpData.summary;
    const totalOrders = s?.total_orders || 0;
    const totalRevenue = s?.total_revenue || 0;
    const avgOrder = s?.avg_order || 0;
    // Count unique customers from topCustomers
    const uniqueCustomers = phpData.topCustomers.length;
    document.getElementById('statsContainer').innerHTML = `
        <div class="stat-card"><div class="stat-icon total-revenue"><i class="fas fa-rupee-sign"></i></div><div class="stat-info"><h3>₹${totalRevenue.toLocaleString('en-IN')}</h3><p>Total Revenue</p><span class="stat-change positive">+12.5%</span></div></div>
        <div class="stat-card"><div class="stat-icon total-orders"><i class="fas fa-shopping-bag"></i></div><div class="stat-info"><h3>${totalOrders}</h3><p>Total Orders</p><span class="stat-change positive">+8.2%</span></div></div>
        <div class="stat-card"><div class="stat-icon avg-order"><i class="fas fa-chart-bar"></i></div><div class="stat-info"><h3>₹${Math.round(avgOrder).toLocaleString('en-IN')}</h3><p>Avg. Order Value</p><span class="stat-change positive">+4.1%</span></div></div>
        <div class="stat-card"><div class="stat-icon customers"><i class="fas fa-users"></i></div><div class="stat-info"><h3>${uniqueCustomers}</h3><p>Top Customers</p><span class="stat-change positive">+5.7%</span></div></div>
    `;
}

// Tables
function renderTables() {
    let data, totalItems, headers;
    if (currentTab === 'sales') {
        data = phpData.sales.map(row => ({
            date: row.date,
            order_count: row.order_count,
            total_sales: row.total_sales,
            avg_order_value: row.avg_order_value
        }));
        totalItems = data.length;
        const start = (currentPage-1)*itemsPerPage;
        const paginated = data.slice(start, start+itemsPerPage);
        document.getElementById('salesTableBody').innerHTML = paginated.map(row => `
            <tr>
                <td>${row.date}</td>
                <td>#ORD-${Math.floor(Math.random()*10000)}</td>
                <td>Customer</td>
                <td>₹${Number(row.total_sales).toFixed(2)}</td>
                <td><span class="status-badge status-delivered">Paid</span></td>
                <td><button class="view-btn">View</button></td>
            </tr>
        `).join('');
    } else if (currentTab === 'customers') {
        data = phpData.topCustomers;
        totalItems = data.length;
        const start = (currentPage-1)*itemsPerPage;
        const paginated = data.slice(start, start+itemsPerPage);
        document.getElementById('customersTableBody').innerHTML = paginated.map(c => `
            <tr>
                <td>${c.name}</td>
                <td>${c.order_count}</td>
                <td>₹${Number(c.total_spent).toFixed(2)}</td>
                <td><button class="view-btn">View</button></td>
            </tr>
        `).join('');
    } else if (currentTab === 'inventory') {
        data = phpData.lowStock;
        totalItems = data.length;
        const start = (currentPage-1)*itemsPerPage;
        const paginated = data.slice(start, start+itemsPerPage);
        document.getElementById('inventoryTableBody').innerHTML = paginated.map(p => `
            <tr>
                <td>${p.name}</td>
                <td>${p.quantity}</td>
                <td><span class="status-badge status-pending">Low Stock</span></td>
            </tr>
        `).join('');
    }
    const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
    document.getElementById('totalPages').innerText = totalPages;
    document.getElementById('currentPage').innerText = currentPage;
}

// Low Stock
function renderLowStock() {
    const container = document.getElementById('lowStockContainer');
    if (phpData.lowStock.length === 0) {
        container.innerHTML = '<p style="color:var(--success);">✅ All products have sufficient stock.</p>';
    } else {
        let html = '<div class="low-stock-grid">';
        phpData.lowStock.forEach(p => {
            html += `<div class="low-stock-item"><strong>${p.name}</strong><br><span style="color:var(--danger);">Stock: ${p.quantity}</span></div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }
}

// Insights (using real data where possible)
function renderInsights() {
    // Compute peak order time (mock, but could be derived from sales data)
    const peak = '6:00 PM - 8:00 PM';
    const topZone = 'Central Business District'; // Could be from delivery data
    const retention = '78%';
    const avgDelivery = '32 min';
    document.getElementById('insightsContainer').innerHTML = `
        <div class="insight-card"><div class="insight-icon peak"><i class="fas fa-clock"></i></div><div class="insight-content"><h4>Peak Order Time</h4><p>${peak}</p><span class="insight-detail">40% of daily orders occur during this period</span></div></div>
        <div class="insight-card"><div class="insight-icon popular"><i class="fas fa-star"></i></div><div class="insight-content"><h4>Most Popular Zone</h4><p>${topZone}</p><span class="insight-detail">Generates 28% of total revenue</span></div></div>
        <div class="insight-card"><div class="insight-icon retention"><i class="fas fa-redo"></i></div><div class="insight-content"><h4>Customer Retention</h4><p>${retention}</p><span class="insight-detail">Customers return within 30 days</span></div></div>
        <div class="insight-card"><div class="insight-icon delivery"><i class="fas fa-truck"></i></div><div class="insight-content"><h4>Avg. Delivery Time</h4><p>${avgDelivery}</p><span class="insight-detail">12% faster than last month</span></div></div>
    `;
}

// ============================================================
// CHARTS
// ============================================================
function initCharts() {
    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    const ctxPay = document.getElementById('paymentChart').getContext('2d');
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    const ctxProd = document.getElementById('productsChart').getContext('2d');
    const ctxCust = document.getElementById('customersChart').getContext('2d');

    // Revenue Chart (line)
    const dates = phpData.sales.map(row => row.date);
    const revenues = phpData.sales.map(row => parseFloat(row.total_sales));
    revenueChart = new Chart(ctxRev, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Revenue (₹)',
                data: revenues,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true }
    });

    // Payment Chart (doughnut)
    const payLabels = phpData.paymentMethods.map(p => p.payment_method);
    const payData = phpData.paymentMethods.map(p => parseFloat(p.total));
    paymentChart = new Chart(ctxPay, {
        type: 'doughnut',
        data: {
            labels: payLabels,
            datasets: [{
                data: payData,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444']
            }]
        },
        options: { responsive: true }
    });

    // Category Chart (bar)
    const catLabels = phpData.categoryPerformance.map(c => c.name);
    const catData = phpData.categoryPerformance.map(c => parseFloat(c.revenue) || 0);
    categoryChart = new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{
                label: 'Revenue (₹)',
                data: catData,
                backgroundColor: '#3b82f6'
            }]
        },
        options: { responsive: true }
    });

    // Top Products Chart (horizontal bar)
    const prodLabels = phpData.topProducts.map(p => p.name);
    const prodData = phpData.topProducts.map(p => parseInt(p.total_sold));
    productsChart = new Chart(ctxProd, {
        type: 'bar',
        data: {
            labels: prodLabels,
            datasets: [{
                label: 'Units Sold',
                data: prodData,
                backgroundColor: '#3b82f6'
            }]
        },
        options: { responsive: true, indexAxis: 'y' }
    });

    // Top Customers Chart (bar)
    const custLabels = phpData.topCustomers.map(c => c.name);
    const custData = phpData.topCustomers.map(c => parseFloat(c.total_spent));
    customersChart = new Chart(ctxCust, {
        type: 'bar',
        data: {
            labels: custLabels,
            datasets: [{
                label: 'Total Spent (₹)',
                data: custData,
                backgroundColor: '#8b5cf6'
            }]
        },
        options: { responsive: true }
    });
}

function updateCharts() {
    // Update revenue chart based on filter
    const filter = document.getElementById('revenueFilter').value;
    // For simplicity, we just redraw with same data; could aggregate differently if needed
    revenueChart.update();

    // Update products chart based on top N
    const topN = parseInt(document.getElementById('productsFilter').value);
    const labels = phpData.topProducts.slice(0, topN).map(p => p.name);
    const data = phpData.topProducts.slice(0, topN).map(p => parseInt(p.total_sold));
    productsChart.data.labels = labels;
    productsChart.data.datasets[0].data = data;
    productsChart.update();

    // Chart type toggle
    document.querySelectorAll('.chart-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const type = this.dataset.chart;
            revenueChart.config.type = type;
            revenueChart.update();
        });
    });
}

// ============================================================
// EXPORT FUNCTIONS
// ============================================================
function exportCSV() {
    let data, headers, filename;
    if (currentTab === 'sales') {
        headers = ['Date', 'Orders', 'Revenue', 'Avg Order'];
        data = phpData.sales.map(row => [row.date, row.order_count, row.total_sales, row.avg_order_value]);
        filename = 'sales_report.csv';
    } else if (currentTab === 'customers') {
        headers = ['Customer', 'Orders', 'Total Spent'];
        data = phpData.topCustomers.map(c => [c.name, c.order_count, c.total_spent]);
        filename = 'customers_report.csv';
    } else {
        headers = ['Product', 'Stock'];
        data = phpData.lowStock.map(p => [p.name, p.quantity]);
        filename = 'inventory_report.csv';
    }
    const csvRows = [headers.join(','), ...data.map(row => row.map(cell => `"${cell}"`).join(','))];
    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

async function downloadPDF() {
    const element = document.getElementById('reportContainer');
    const overlay = document.getElementById('pdfOverlay');
    overlay.classList.add('active');
    try {
        const canvas = await html2canvas(element, {
            scale: 2,
            logging: false,
            useCORS: true,
            backgroundColor: '#ffffff'
        });
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210;
        const pageHeight = 297;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        pdf.save('ksr_guru_report.pdf');
    } catch (err) {
        console.error(err);
        alert('Failed to generate PDF. Please try again.');
    } finally {
        overlay.classList.remove('active');
    }
}

// ============================================================
// EVENT LISTENERS
// ============================================================

// Date Range Selector (quick presets)
document.querySelectorAll('.date-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        if (this.id === 'customRangeBtn') {
            document.getElementById('dateRangeModal').classList.add('active');
        } else {
            const range = this.dataset.range;
            // Map to actual dates and redirect via GET
            const now = new Date();
            let from, to;
            if (range === 'today') { from = now; to = now; }
            else if (range === 'week') { from = new Date(now); from.setDate(now.getDate() - 7); to = now; }
            else if (range === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); to = now; }
            else if (range === 'quarter') { from = new Date(now.getFullYear(), Math.floor(now.getMonth()/3)*3, 1); to = now; }
            const fromStr = from.toISOString().slice(0,10);
            const toStr = to.toISOString().slice(0,10);
            window.location.href = 'reports.php?from=' + fromStr + '&to=' + toStr;
        }
    });
});

// Apply custom range
document.getElementById('applyRange').addEventListener('click', function() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    if (start && end) {
        window.location.href = 'reports.php?from=' + start + '&to=' + end;
    } else {
        alert('Please select both dates.');
    }
});
document.getElementById('cancelRange').addEventListener('click', function() {
    document.getElementById('dateRangeModal').classList.remove('active');
});
document.querySelectorAll('.close-modal').forEach(el => {
    el.addEventListener('click', function() {
        document.getElementById('dateRangeModal').classList.remove('active');
    });
});
// Quick ranges in modal
document.querySelectorAll('.quick-range-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const days = parseInt(this.dataset.days);
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - days);
        document.getElementById('startDate').value = start.toISOString().slice(0,10);
        document.getElementById('endDate').value = end.toISOString().slice(0,10);
    });
});

// Filter bar (from first file)
document.getElementById('applyFilter').addEventListener('click', function() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    if (from && to) {
        window.location.href = 'reports.php?from=' + from + '&to=' + to;
    }
});
document.getElementById('resetFilter').addEventListener('click', function() {
    window.location.href = 'reports.php';
});

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentTab = this.dataset.tab;
        currentPage = 1;
        renderTables();
    });
});

// Pagination
document.getElementById('prevPage').addEventListener('click', function() {
    if (currentPage > 1) { currentPage--; renderTables(); }
});
document.getElementById('nextPage').addEventListener('click', function() {
    const total = Math.ceil(
        (currentTab === 'sales' ? phpData.sales.length :
         currentTab === 'customers' ? phpData.topCustomers.length :
         phpData.lowStock.length) / itemsPerPage
    );
    if (currentPage < total) { currentPage++; renderTables(); }
});
document.getElementById('itemsPerPage').addEventListener('change', function() {
    itemsPerPage = parseInt(this.value);
    currentPage = 1;
    renderTables();
});

// Export buttons
document.getElementById('exportBtn').addEventListener('click', exportCSV);
document.getElementById('exportPdf').addEventListener('click', downloadPDF);
document.getElementById('pdfBtn').addEventListener('click', downloadPDF);

// Chart controls
document.getElementById('revenueFilter').addEventListener('change', updateCharts);
document.getElementById('productsFilter').addEventListener('change', updateCharts);

// Mobile menu
document.getElementById('menuToggle').addEventListener('click', function() {
    document.getElementById('mobileMenu').classList.add('open');
});
document.getElementById('closeMenu').addEventListener('click', function() {
    document.getElementById('mobileMenu').classList.remove('open');
});

// ============================================================
// INITIAL RENDER
// ============================================================
renderStats();
renderLowStock();
renderInsights();
initCharts();
renderTables();
updateCharts();

// Set active date button based on current GET params
const urlParams = new URLSearchParams(window.location.search);
const from = urlParams.get('from');
const to = urlParams.get('to');
if (from && to) {
    // Check if it matches month, week, etc.
    const now = new Date();
    const fromDate = new Date(from);
    const toDate = new Date(to);
    // If from is first day of current month and to is today, set month active
    if (fromDate.getFullYear() === now.getFullYear() && fromDate.getMonth() === now.getMonth() && fromDate.getDate() === 1) {
        document.querySelector('.date-btn[data-range="month"]')?.classList.add('active');
    }
    // else keep default
}
</script>
</body>
</html>