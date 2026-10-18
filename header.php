<?php
// ============================================================
// includes/header.php - Admin Panel Header
// ============================================================
// This file expects $pageTitle and $activePage to be defined before inclusion.
// $pageTitle: string - page title
// $activePage: string - identifier for active menu item (e.g., 'dashboard', 'products', etc.)
// If not set, defaults will be used.
$pageTitle = $pageTitle ?? 'Admin Panel';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($pageTitle); ?> - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & VARIABLES ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --accent: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --radius-sm: 0.5rem;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--main-bg); color: var(--text-primary); }
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
        }
        .nav-item i { width: 22px; text-align: center; }
        .nav-item.active { background: rgba(59,130,246,0.15); border-left-color: var(--accent); color: white; }
        .nav-item.active i { color: var(--accent); }
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
        .sidebar-toggle {
            display: none;
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

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            padding: 20px 28px;
            min-height: 100vh;
        }
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .header-left h1 { font-size: 1.6rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-left h1 i { color: var(--accent); }
        .breadcrumb { color: var(--text-secondary); font-size: 0.8rem; }
        .header-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
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
        .search-box input { background: transparent; border: none; outline: none; color: var(--text-primary); font-size: 0.85rem; min-width: 180px; }
        .search-box i { color: var(--text-secondary); }
        .header-btn {
            background: var(--card-bg);
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-primary);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .header-btn:hover { transform: scale(1.05); }
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

        /* ===== STATS & TABLES (common) ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }
        .stat-info h3 { font-size: 1.6rem; font-weight: 800; }
        .stat-info p { font-size: 0.8rem; color: var(--text-secondary); }
        .stat-icon { width: 50px; height: 50px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .table-header h3 { font-size: 1rem; font-weight: 600; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .data-table th, .data-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid var(--border-light); }
        .data-table th { font-weight: 600; color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .status-badge.active { background: #dcfce7; color: #15803d; }
        .status-badge.inactive { background: #fee2e2; color: #b91c1c; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.processing { background: #dbeafe; color: #1e40af; }
        .status-badge.shipped { background: #d1d5db; color: #374151; }
        .status-badge.delivered { background: #dcfce7; color: #15803d; }
        .status-badge.cancelled { background: #fee2e2; color: #b91c1c; }
        .view-btn { background: transparent; border: none; color: var(--accent); cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: 0.2s; }
        .view-btn:hover { background: rgba(59,130,246,0.1); }
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
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: var(--success); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-light); color: var(--text-primary); }
        .btn-outline:hover { background: var(--main-bg); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }

        /* ===== MODAL ===== */
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
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid var(--border-light); }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.3rem; color: var(--text-secondary); cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: 0.2s; }
        .modal-close:hover { background: var(--main-bg); }
        .modal-body { padding: 20px 22px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 10px; }
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

        /* ===== FILTER BAR ===== */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
        }
        .filter-bar select, .filter-bar input {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 0.8rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar-toggle { display: block; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-right .search-box { width: 100%; }
            .header-right .search-box input { width: 100%; min-width: unset; }
            .top-header { flex-direction: column; align-items: stretch; }
            .header-right { flex-wrap: wrap; justify-content: stretch; }
            .user-menu { width: 100%; justify-content: center; }
            .header-actions { justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="admin-logo" style="display: flex; align-items: center; gap: 10px;">
            <?php $logoPath = file_exists('KSR_GURU_JI.png') ? 'KSR_GURU_JI.png' : (file_exists('../KSR_GURU_JI.png') ? '../KSR_GURU_JI.png' : '../../KSR_GURU_JI.png'); ?>
            <div class="logo-image" style="width: 32px; height: 32px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 1px solid rgba(255, 255, 255, 0.8);">
                <img src="<?php echo $logoPath; ?>" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛡️';">
            </div>
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin: 0; line-height: 1.1;">KSR GURU JI</h2>
                <p style="font-size: 0.65rem; color: #94a3b8; margin: 2px 0 0; line-height: 1;">Admin Panel</p>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    </div>
    <div class="admin-profile">
        <div class="profile-image"><i class="fas fa-user-circle"></i></div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></h3>
            <p>Administrator</p>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section"><h4>MAIN</h4>
            <a href="dashboard.php" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="products.php" class="nav-item <?php echo $activePage === 'products' ? 'active' : ''; ?>"><i class="fas fa-box"></i> Products</a>
            <a href="categories.php" class="nav-item <?php echo $activePage === 'categories' ? 'active' : ''; ?>"><i class="fas fa-tags"></i> Categories</a>
        </div>
        <div class="nav-section"><h4>MANAGEMENT</h4>
            <a href="orders.php" class="nav-item <?php echo $activePage === 'orders' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="customers.php" class="nav-item <?php echo $activePage === 'customers' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Customers</a>
            <a href="<?php echo $base_url; ?>WhatsApp/telehealth/index.php" class="nav-item <?php echo $activePage === 'doctor_assist' ? 'active' : ''; ?>"><i class="fas fa-user-md"></i> Doctor Assist</a>
        </div>
        <div class="nav-section"><h4>SYSTEM</h4>
            <a href="settings.php" class="nav-item <?php echo $activePage === 'settings' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <button class="logout-btn" onclick="window.location.href='login.php?logout=1'"><i class="fas fa-sign-out-alt"></i> Logout</button>
    </div>
</aside>

<!-- ===== MAIN CONTENT ===== -->
<main class="main-content">

    <!-- Top Header -->
    <header class="top-header">
        <div class="header-left">
            <h1><i class="fas <?php echo $activePageIcon ?? 'fa-tachometer-alt'; ?>"></i> <?php echo htmlspecialchars($pageTitle); ?></h1>
            <div class="breadcrumb">Admin / <?php echo htmlspecialchars($pageTitle); ?></div>
        </div>
        <div class="header-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search..." id="globalSearch">
            </div>
            <div class="header-actions">
                <button class="header-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
                <button class="header-btn" id="fullscreenBtn"><i class="fas fa-expand"></i></button>
            </div>
            <div class="user-menu">
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    <span class="user-role">Super Admin</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </header>