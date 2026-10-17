<?php
// ============================================================
// admin/settings.php - Complete Settings Management (Merged)
// ============================================================
$currentPage = 'settings';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';
require_once 'inc/settings_logic.php';
require_once __DIR__ . '/inc/whatsapp/WhatsAppFactory.php';

$pdo = getDBConnection();

// --- (Default settings live in inc/settings_logic.php) ---

// --- HANDLE ACTIONS ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $category = $_POST['category'] ?? 'general';
    $settings = $_POST['settings'] ?? [];

    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        foreach ($settings as $key => $value) {
            if ($key === 'whatsapp_access_token') {
                continue;
            }
            $value = settings_encode_value($value);
            $stmt->execute([$key, $value, $category, $value]);
        }
        $message = 'Settings saved successfully.';
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_whatsapp_config'])) {
    $status = WhatsAppFactory::configurationStatus($pdo);
    $businessAccountId = trim((string)getenv('WHATSAPP_BUSINESS_ACCOUNT_ID'));

    if ($status['provider'] !== 'cloud_api' || !$status['configured']) {
        $missing = array_merge($status['missing'], $status['invalid']);
        $error = 'Cloud API is not ready. Check required server values: ' . implode(', ', $missing ?: ['WHATSAPP_PROVIDER=cloud_api']) . '.';
    } elseif ($businessAccountId === '') {
        $error = 'Template approval check requires WHATSAPP_BUSINESS_ACCOUNT_ID in the server environment.';
    } else {
        $provider = WhatsAppFactory::make($pdo);
        if (!$provider instanceof CloudApiWhatsAppProvider) {
            $error = 'Cloud API provider is unavailable.';
        } else {
            $templateStatus = $provider->verifyOtpTemplate($businessAccountId);
            if ($templateStatus['status'] === 'approved') {
                $message = 'Meta confirms the configured authentication template is approved for the selected language.';
            } elseif ($templateStatus['status'] === 'not_approved') {
                $error = 'The configured template is not approved as an Authentication template for the selected language.';
            } elseif ($templateStatus['status'] === 'not_found') {
                $error = 'Meta did not find the configured template name and language in this WhatsApp Business Account.';
            } else {
                $error = 'Meta configuration check failed. Safe error code: ' . $templateStatus['error_code'] . '.';
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    $tab = $_GET['tab'] ?? 'general';
    $defaults = getDefaultSettings($tab);
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        foreach ($defaults as $key => $value) {
            $value = settings_encode_value($value);
            $stmt->execute([$key, $value, $tab, $value]);
        }
        $message = 'Settings reset to defaults.';
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- FETCH SETTINGS ---
$activeTab = $_GET['tab'] ?? 'general';

// Load all settings from DB
$allSettings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value, category FROM settings");
while ($row = $stmt->fetch()) {
    $allSettings[$row['category']][$row['setting_key']] = $row['setting_value'];
}
unset($allSettings['integrations']['whatsapp_access_token']);

// Merge with defaults for active tab
$defaults = getDefaultSettings($activeTab);
$currentSettings = settings_merge_with_defaults($defaults, $allSettings[$activeTab] ?? []);
$whatsappStatus = WhatsAppFactory::configurationStatus($pdo);

if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --sidebar-bg: #0f172a;
            --accent: #3b82f6;
            --main-bg: #f1f5f9;
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
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
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
        }

        .sidebar-header {
            padding: 20px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .admin-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-logo i {
            font-size: 28px;
            color: var(--accent);
        }

        .admin-logo h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
        }

        .admin-logo p {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .admin-profile {
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .profile-image i {
            font-size: 42px;
            color: #94a3b8;
        }

        .profile-info h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
        }

        .profile-info p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }

        .nav-section {
            h4 {
                padding: 0 20px;
                font-size: 0.65rem;
                letter-spacing: 1px;
                color: #64748b;
                margin-bottom: 8px;
                text-transform: uppercase;
            }
        }

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

        .nav-item i {
            width: 22px;
            text-align: center;
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            border-left-color: var(--accent);
            color: white;
        }

        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar-footer {
            padding: 16px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .logout-btn {
            width: 100%;
            background: rgba(239, 68, 68, 0.15);
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

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.3);
        }

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

        .header-left h1 {
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-left h1 i {
            color: var(--accent);
        }

        .breadcrumb {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
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

        .user-avatar i {
            font-size: 28px;
            color: var(--accent);
        }

        .user-info .user-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .user-info .user-role {
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: block;
        }

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
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: var(--main-bg);
        }

        .settings-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 24px;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }

        .settings-nav .nav-item {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
        }

        .settings-nav .nav-item:hover {
            background: var(--main-bg);
            color: var(--text-primary);
        }

        .settings-nav .nav-item.active {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .settings-panel {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }

        .section-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .form-group .form-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            justify-content: flex-end;
        }

        .input-with-symbol {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-with-symbol .input-symbol {
            background: var(--gray-200);
            padding: 10px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            color: var(--text-secondary);
        }

        .input-with-symbol input {
            flex: 1;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: .4s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: .4s;
            border-radius: 50%;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: var(--accent);
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(22px);
        }

        .day-schedule {
            background: var(--main-bg);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }

        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .day-header h4 {
            font-weight: 600;
            text-transform: capitalize;
        }

        .time-slots {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .time-slots input[type="time"] {
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: white;
        }

        .time-slots .disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .payment-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--main-bg);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }

        .payment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--accent);
        }

        .notification-option,
        .two-factor-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: var(--main-bg);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }

        .notification-category {
            margin-bottom: 20px;
        }

        .notification-category h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-secondary);
        }

        .staff-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--main-bg);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .staff-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 4px 8px;
            cursor: pointer;
            color: var(--text-secondary);
            border-radius: 6px;
            transition: 0.2s;
        }

        .action-btn:hover {
            background: var(--card-bg);
            color: var(--accent);
        }

        .action-btn.delete-staff:hover {
            color: var(--danger);
        }

        .permissions-table {
            overflow-x: auto;
            margin-top: 12px;
            width: 100%;
        }

        .permissions-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .permissions-table th,
        .permissions-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-light);
            text-align: center;
        }

        .permissions-table th {
            background: var(--main-bg);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .permissions-table td:first-child {
            text-align: left;
        }

        .integration-card {
            background: var(--main-bg);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 12px;
        }

        .integration-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .integration-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--accent);
        }

        .integration-info {
            flex: 1;
        }

        .integration-info h4 {
            font-weight: 600;
        }

        .integration-info p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .integration-details {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .api-key-display {
            background: var(--main-bg);
            padding: 16px;
            border-radius: var(--radius-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            width: 100%;
        }

        .api-key-value code {
            background: var(--card-bg);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            word-break: break-all;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-weight: 700;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
        }

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
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
            pointer-events: auto;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .settings-nav {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>

<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'settings';
    include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-cog"></i> Shop Settings</h1>
                <div class="breadcrumb">Admin / Settings</div>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Admin</span></div>
                </div>
            </div>
        </header>

        <?php if ($message): ?><div class="toast show success" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="toast show error" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Navigation Tabs -->
        <nav class="settings-nav" id="settingsNav">
            <a href="settings.php?tab=general" class="nav-item <?= $activeTab === 'general' ? 'active' : '' ?>"><i class="fas fa-store"></i> General</a>
            <a href="settings.php?tab=business_hours" class="nav-item <?= $activeTab === 'business_hours' ? 'active' : '' ?>"><i class="fas fa-clock"></i> Business Hours</a>
            <a href="settings.php?tab=delivery" class="nav-item <?= $activeTab === 'delivery' ? 'active' : '' ?>"><i class="fas fa-truck"></i> Delivery</a>
            <a href="settings.php?tab=payment" class="nav-item <?= $activeTab === 'payment' ? 'active' : '' ?>"><i class="fas fa-credit-card"></i> Payment</a>
            <a href="settings.php?tab=notifications" class="nav-item <?= $activeTab === 'notifications' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
            <a href="settings.php?tab=staff" class="nav-item <?= $activeTab === 'staff' ? 'active' : '' ?>"><i class="fas fa-users"></i> Staff</a>
            <a href="settings.php?tab=security" class="nav-item <?= $activeTab === 'security' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Security</a>
            <a href="settings.php?tab=integrations" class="nav-item <?= $activeTab === 'integrations' ? 'active' : '' ?>"><i class="fas fa-plug"></i> Integrations</a>
        </nav>

        <!-- ========================================================== -->
        <!-- TAB: General -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'general'): ?>
            <section class="settings-section" id="general">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-store"></i> General Shop Settings</h2>
                        <p>Basic information about your shop</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="general">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="store_name">Shop Name *</label>
                                <input type="text" id="store_name" name="settings[store_name]" value="<?= htmlspecialchars($currentSettings['store_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="store_tagline">Shop Tagline</label>
                                <input type="text" id="store_tagline" name="settings[store_tagline]" value="<?= htmlspecialchars($currentSettings['store_tagline'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="store_email">Contact Email *</label>
                                <input type="email" id="store_email" name="settings[store_email]" value="<?= htmlspecialchars($currentSettings['store_email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="store_phone">Contact Phone *</label>
                                <input type="tel" id="store_phone" name="settings[store_phone]" value="<?= htmlspecialchars($currentSettings['store_phone'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="store_address">Shop Address *</label>
                            <textarea id="store_address" name="settings[store_address]" rows="3" required><?= htmlspecialchars($currentSettings['store_address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=general" class="btn btn-secondary" onclick="return confirm('Reset general settings to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Business Hours -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'business_hours'): ?>
            <section class="settings-section" id="business">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-clock"></i> Business Hours</h2>
                        <p>Set your shop opening and closing times</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="business_hours">
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        $hours = $currentSettings;
                        foreach ($days as $day):
                            $enabled = isset($hours[$day]['enabled']) ? $hours[$day]['enabled'] : true;
                            $open = $hours[$day]['open'] ?? '09:00';
                            $close = $hours[$day]['close'] ?? '21:00';
                        ?>
                            <div class="day-schedule">
                                <div class="day-header">
                                    <h4><?= htmlspecialchars($day) ?></h4>
                                    <label class="toggle-switch">
                                        <input type="hidden" name="settings[<?= $day ?>][enabled]" value="0">
                                        <input type="checkbox" name="settings[<?= $day ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="time-slots">
                                    <div class="time-slot <?= $enabled ? '' : 'disabled' ?>">
                                        <input type="time" name="settings[<?= $day ?>][open]" value="<?= $open ?>">
                                        <span>to</span>
                                        <input type="time" name="settings[<?= $day ?>][close]" value="<?= $close ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=business_hours" class="btn btn-secondary" onclick="return confirm('Reset business hours to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Hours</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Delivery -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'delivery'): ?>
            <section class="settings-section" id="delivery">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-truck"></i> Delivery Settings</h2>
                        <p>Configure delivery zones, fees, and timing</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="delivery">
                        <div class="form-group">
                            <label for="delivery_radius">Delivery Radius (km) *</label>
                            <div class="input-with-symbol">
                                <input type="number" id="delivery_radius" name="settings[delivery_radius]" min="1" max="50" value="<?= htmlspecialchars($currentSettings['delivery_radius'] ?? 15) ?>">
                                <span class="input-symbol">km</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="min_order_amount">Minimum Order Amount (₹) *</label>
                                <input type="number" id="min_order_amount" name="settings[min_order_amount]" min="0" step="10" value="<?= htmlspecialchars($currentSettings['min_order_amount'] ?? 200) ?>">
                            </div>
                            <div class="form-group">
                                <label for="delivery_fee">Standard Delivery Fee (₹)</label>
                                <input type="number" id="delivery_fee" name="settings[delivery_fee]" min="0" step="5" value="<?= htmlspecialchars($currentSettings['delivery_fee'] ?? 40) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="free_delivery_threshold">Free Delivery Threshold (₹)</label>
                                <input type="number" id="free_delivery_threshold" name="settings[free_delivery_threshold]" min="0" step="50" value="<?= htmlspecialchars($currentSettings['free_delivery_threshold'] ?? 500) ?>">
                            </div>
                            <div class="form-group">
                                <label for="delivery_time">Estimated Delivery Time (minutes)</label>
                                <input type="number" id="delivery_time" name="settings[delivery_time]" min="15" max="180" value="<?= htmlspecialchars($currentSettings['delivery_time'] ?? 45) ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=delivery" class="btn btn-secondary" onclick="return confirm('Reset delivery settings to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Delivery Settings</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Payment -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'payment'): ?>
            <section class="settings-section" id="payment">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-credit-card"></i> Payment Methods</h2>
                        <p>Configure accepted payment options</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="payment">
                        <div class="payment-option">
                            <div class="payment-info">
                                <div class="payment-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div>
                                    <h4>Cash on Delivery</h4>
                                    <p>Accept cash payments upon delivery</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="hidden" name="settings[cash_on_delivery]" value="0">
                                <input type="checkbox" name="settings[cash_on_delivery]" value="1" <?= ($currentSettings['cash_on_delivery'] ?? true) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="payment-option">
                            <div class="payment-info">
                                <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                                <div>
                                    <h4>Credit/Debit Card</h4>
                                    <p>Accept card payments online</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="hidden" name="settings[card_payment]" value="0">
                                <input type="checkbox" name="settings[card_payment]" value="1" <?= ($currentSettings['card_payment'] ?? true) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="payment-option">
                            <div class="payment-info">
                                <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                                <div>
                                    <h4>UPI Payment</h4>
                                    <p>Accept payments via UPI apps</p>
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="hidden" name="settings[upi_payment]" value="0">
                                <input type="checkbox" name="settings[upi_payment]" value="1" <?= ($currentSettings['upi_payment'] ?? true) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div style="margin: 20px 0; border-top: 1px dashed var(--border-light); padding-top: 20px;">
                            <h3 style="font-size: 1.05rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; color: var(--accent);"><i class="fas fa-wallet"></i> Razorpay Payment Integration</h3>

                            <div class="payment-option">
                                <div class="payment-info">
                                    <div class="payment-icon" style="color: #0f7b3a; background: rgba(15, 123, 58, 0.1);"><i class="fas fa-shield-alt"></i></div>
                                    <div>
                                        <h4>Enable Razorpay Checkout</h4>
                                        <p>Enable direct card/UPI payments through the Razorpay modal</p>
                                    </div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[razorpay_enabled]" value="0">
                                    <input type="checkbox" name="settings[razorpay_enabled]" value="1" <?= ($currentSettings['razorpay_enabled'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="razorpay_key_id">Razorpay Key ID</label>
                                    <input type="text" id="razorpay_key_id" name="settings[razorpay_key_id]" value="<?= htmlspecialchars($currentSettings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_test_xxxx">
                                </div>
                                <div class="form-group">
                                    <label for="razorpay_key_secret">Razorpay Key Secret</label>
                                    <input type="password" id="razorpay_key_secret" name="settings[razorpay_key_secret]" value="<?= htmlspecialchars($currentSettings['razorpay_key_secret'] ?? '') ?>" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="razorpay_webhook_secret">Razorpay Webhook Secret</label>
                                <input type="text" id="razorpay_webhook_secret" name="settings[razorpay_webhook_secret]" value="<?= htmlspecialchars($currentSettings['razorpay_webhook_secret'] ?? '') ?>" placeholder="Your Webhook Secret">
                                <div class="form-hint">Used to verify signature verification payloads sent from Razorpay webhooks securely. Your webhook URL: <code>http://[your-domain]/api/razorpay_webhook.php</code></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="payment_instructions">Payment Instructions</label>
                            <textarea id="payment_instructions" name="settings[payment_instructions]" rows="3"><?= htmlspecialchars($currentSettings['payment_instructions'] ?? '') ?></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=payment" class="btn btn-secondary" onclick="return confirm('Reset payment settings to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment Settings</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Notifications -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'notifications'): ?>
            <section class="settings-section" id="notifications">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-bell"></i> Notification Settings</h2>
                        <p>Configure notification channels and alerts</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="notifications">

                        <div class="notification-category" style="margin-bottom:30px;">
                            <h3 style="border-bottom: 2px solid var(--border-light); padding-bottom:8px; margin-bottom:16px; color:var(--accent); display:flex; align-items:center; gap:8px;"><i class="fas fa-user-shield"></i> Admin Alerts</h3>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>New Order Alerts</h4>
                                    <p>Receive email when a new order is placed</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[email_new_order]" value="0">
                                    <input type="checkbox" name="settings[email_new_order]" value="1" <?= ($currentSettings['email_new_order'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>Daily Summary</h4>
                                    <p>Receive daily sales summary email</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[email_daily_summary]" value="0">
                                    <input type="checkbox" name="settings[email_daily_summary]" value="1" <?= ($currentSettings['email_daily_summary'] ?? false) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>Low Stock Alerts</h4>
                                    <p>Receive push notifications representing low-stock items</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[push_low_stock]" value="0">
                                    <input type="checkbox" name="settings[push_low_stock]" value="1" <?= ($currentSettings['push_low_stock'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="notification-category">
                            <h3 style="border-bottom: 2px solid var(--border-light); padding-bottom:8px; margin-bottom:16px; color:var(--success); display:flex; align-items:center; gap:8px;"><i class="fas fa-motorcycle"></i> Delivery boy &amp; Customer updates</h3>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>Driver Assignment Push</h4>
                                    <p>Send push alerts to delivery partners upon order assignment</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[push_new_order]" value="0">
                                    <input type="checkbox" name="settings[push_new_order]" value="1" <?= ($currentSettings['push_new_order'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>Order Dispatched Email</h4>
                                    <p>Send notification emails when orders are marked as out-for-delivery</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[email_delivery]" value="0">
                                    <input type="checkbox" name="settings[email_delivery]" value="1" <?= ($currentSettings['email_delivery'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>Delivery Stage SMS</h4>
                                    <p>Send SMS updates to customers during delivery tracking phases</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[sms_delivery_update]" value="0">
                                    <input type="checkbox" name="settings[sms_delivery_update]" value="1" <?= ($currentSettings['sms_delivery_update'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="notification-option">
                                <div class="notification-info">
                                    <h4>SMS Order Confirmation</h4>
                                    <p>Send SMS confirmation to customers immediately when their order is placed</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="hidden" name="settings[sms_order_confirm]" value="0">
                                    <input type="checkbox" name="settings[sms_order_confirm]" value="1" <?= ($currentSettings['sms_order_confirm'] ?? true) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=notifications" class="btn btn-secondary" onclick="return confirm('Reset notification settings to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Notification Settings</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Staff -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'staff'): ?>
            <section class="settings-section" id="staff">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-users"></i> Staff Management</h2>
                        <p>Manage staff members</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="staff">
                        <div class="staff-management">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                                <h3>Staff List</h3>
                            </div>
                            <div class="staff-list">
                                <?php
                                $staffList = $currentSettings['staff_list'] ?? [];
                                foreach ($staffList as $staff): ?>
                                    <div class="staff-card">
                                        <div class="staff-info">
                                            <div class="staff-avatar"><i class="fas fa-user"></i></div>
                                            <div>
                                                <h4><?= htmlspecialchars($staff['name'] ?? '') ?></h4>
                                                <p style="font-size:0.85rem;color:var(--text-secondary);"><?= htmlspecialchars($staff['role'] ?? '') ?></p>
                                                <span style="font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($staff['email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Security -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'security'): ?>
            <section class="settings-section" id="security">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                        <p>Password policies and account security</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="security">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password_min_length">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="settings[password_min_length]" min="6" max="20" value="<?= htmlspecialchars($currentSettings['password_min_length'] ?? 8) ?>">
                            </div>
                            <div class="form-group">
                                <label for="session_timeout">Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="settings[session_timeout]" min="5" max="480" value="<?= htmlspecialchars($currentSettings['session_timeout'] ?? 30) ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=security" class="btn btn-secondary" onclick="return confirm('Reset security settings to default?')"><i class="fas fa-undo"></i> Reset</a>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Security Settings</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB: Integrations -->
        <!-- ========================================================== -->
        <?php if ($activeTab === 'integrations'): ?>
            <section class="settings-section" id="integrations">
                <div class="settings-panel">
                    <div class="section-header">
                        <h2><i class="fas fa-plug"></i> Integrations</h2>
                        <p>Connect APIs and third-party tools</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="category" value="integrations">

                        <div class="notification-category" style="margin-bottom:24px;">
                            <h3 style="border-bottom:2px solid var(--border-light); padding-bottom:8px; margin-bottom:16px; color:var(--accent); display:flex; align-items:center; gap:8px;">
                                <i class="fab fa-whatsapp"></i> WhatsApp OTP Provider
                            </h3>
                            <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:14px;">
                                Patient and Super Admin OTPs use an approved Meta Authentication template with a Copy Code button and a 5-minute expiration. Enter its exact name and language below. The disabled provider does not deliver messages.
                            </p>
                            <div role="status" style="padding:12px 14px;margin-bottom:16px;border:1px solid <?= $whatsappStatus['configured'] ? '#86efac' : '#fcd34d' ?>;background:<?= $whatsappStatus['configured'] ? '#f0fdf4' : '#fffbeb' ?>;color:#334155;border-radius:8px;font-size:0.9rem;">
                                <strong>Provider status:</strong> <?= htmlspecialchars($whatsappStatus['provider'], ENT_QUOTES, 'UTF-8') ?>.
                                <?php if ($whatsappStatus['configured']): ?>
                                    Required send configuration is present. Template approval: <?= $whatsappStatus['business_account_id_present'] ? 'not checked' : 'not checked (set WHATSAPP_BUSINESS_ACCOUNT_ID to enable the check)' ?>.
                                <?php elseif ($whatsappStatus['provider'] !== 'cloud_api'): ?>
                                    Select Meta WhatsApp Cloud API and configure the server-side credentials to enable OTP delivery.
                                    <?php if ($whatsappStatus['missing']): ?>Missing values: <code><?= htmlspecialchars(implode(', ', $whatsappStatus['missing']), ENT_QUOTES, 'UTF-8') ?></code><?php endif; ?>
                                <?php else: ?>
                                    <?php if ($whatsappStatus['missing']): ?>Missing values: <code><?= htmlspecialchars(implode(', ', $whatsappStatus['missing']), ENT_QUOTES, 'UTF-8') ?></code><?php endif; ?>
                                    <?php if ($whatsappStatus['invalid']): ?>Invalid values: <code><?= htmlspecialchars(implode(', ', $whatsappStatus['invalid']), ENT_QUOTES, 'UTF-8') ?></code><?php endif; ?>
                                <?php endif; ?>
                                <br><small>The access token is read only from the server environment and is never displayed or saved from this form.</small>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Provider</label>
                                    <select name="settings[whatsapp_provider]" class="form-control">
                                        <option value="log" <?= $whatsappStatus['provider'] === 'log' ? 'selected' : '' ?>>Disabled (no delivery)</option>
                                        <option value="cloud_api" <?= $whatsappStatus['provider'] === 'cloud_api' ? 'selected' : '' ?>>Meta WhatsApp Cloud API</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>API Version</label>
                                    <input type="text" name="settings[whatsapp_api_version]" class="form-control"
                                        value="<?= htmlspecialchars($currentSettings['whatsapp_api_version'] ?? 'v20.0') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Phone Number ID</label>
                                    <input type="text" name="settings[whatsapp_phone_number_id]" class="form-control"
                                        value="<?= htmlspecialchars($currentSettings['whatsapp_phone_number_id'] ?? '') ?>"
                                        placeholder="e.g. 123456789012345">
                                </div>
                                <div class="form-group">
                                    <label>Access Token</label>
                                    <div class="form-control" aria-readonly="true">
                                        <?= getenv('WHATSAPP_ACCESS_TOKEN') ? 'Configured in server environment' : 'Not configured in server environment' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>OTP Authentication Template Name</label>
                                    <input type="text" name="settings[whatsapp_otp_template_name]" class="form-control"
                                        value="<?= htmlspecialchars($currentSettings['whatsapp_otp_template_name'] ?? '') ?>"
                                        placeholder="e.g. verification_code">
                                </div>
                                <div class="form-group">
                                    <label>OTP Template Language</label>
                                    <input type="text" name="settings[whatsapp_otp_template_language]" class="form-control"
                                        value="<?= htmlspecialchars($currentSettings['whatsapp_otp_template_language'] ?? 'en_US') ?>"
                                        placeholder="e.g. en_US">
                                </div>
                            </div>
                        </div>

                        <div class="api-key-display">
                            <div>
                                <h4>Your API Key</h4>
                                <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:8px;">Use this key to authenticate API requests</p>
                                <div class="api-key-value">
                                    <code><?= htmlspecialchars($currentSettings['api_key'] ?? 'ksrg_apikey_7f3b2d8a5c1e9f6a4b7d2c8e5f9a3b6d') ?></code>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="settings[api_key]" value="<?= htmlspecialchars($currentSettings['api_key'] ?? 'ksrg_apikey_7f3b2d8a5c1e9f6a4b7d2c8e5f9a3b6d') ?>">
                        <div class="form-actions">
                            <a href="settings.php?action=reset&tab=integrations" class="btn btn-secondary" onclick="return confirm('Reset integrations key to default?')"><i class="fas fa-undo"></i> Reset</a>
                                    <button type="submit" name="test_whatsapp_config" value="1" class="btn btn-secondary"><i class="fas fa-plug"></i> Check credentials and template</button>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> Save Integrations</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <script>
        // Auto-hide toast
        setTimeout(() => {
            document.querySelectorAll('.toast.show').forEach(toast => {
                setTimeout(() => toast.style.opacity = '0', 4000);
            });
        }, 1000);
    </script>
</body>

</html>