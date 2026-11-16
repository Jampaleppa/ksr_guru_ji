<?php
// ============================================================
// admin/inc/header.php
// Standardized Admin Layout Header
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_check.php';

$pageTitle = $pageTitle ?? 'Admin - KSR GURU JI';
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --accent: #0f7b3a;
            --accent-light: #1ea85a;
            --main-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }
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
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08);
        }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .admin-logo { display: flex; align-items: center; gap: 12px; }
        .admin-logo i { font-size: 28px; color: var(--accent); }
        .admin-logo h2 { font-size: 1.25rem; font-weight: 700; color: white; margin: 0; }
        .admin-logo p { font-size: 0.65rem; color: #94a3b8; margin: 0; }
        .admin-profile { padding: 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size: 40px; color: #94a3b8; }
        .profile-info h3 { font-size: 0.95rem; font-weight: 600; color: white; margin: 0; }
        .profile-info p { font-size: 0.7rem; color: #94a3b8; margin: 0; }
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
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); color: white; }
        .nav-item.active { background: rgba(15,123,58,0.2); border-left-color: var(--accent); color: white; font-weight: 600; }
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
        .logout-btn:hover { background: rgba(239,68,68,0.3); color: white; }
        .main-content {
            margin-left: 280px;
            padding: 24px 32px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .page-header {
            margin-bottom: 24px;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .page-header .subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-light);
            padding: 16px 20px;
        }
        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        .card-body {
            padding: 20px;
        }
        .system-status {
            padding: 10px 0;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .status-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            margin-right: 6px;
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<main class="main-content" id="mainContent">
