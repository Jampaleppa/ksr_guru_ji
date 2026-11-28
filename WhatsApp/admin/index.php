<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Admin-side dashboard for KSR WhatsApp Admin
// Real KPI data pulled from DB if available
require_once dirname(__DIR__, 2) . '/includes/db.php';

$db = getDBConnection();

$totalOrders   = 0;
$pendingOrders = 0;
$todaySales    = 0;
$totalUsers    = 0;

try {
    $totalOrders   = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')")->fetchColumn();
    $todaySales    = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)=DATE('now')")->fetchColumn();
    $totalUsers    = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    // Fallback to zeroes — DB may not have all tables yet
}

function fmt_inr($v) {
    return '₹' . number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>WhatsApp Admin | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --bg: #f0f2f5;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
            --sidebar-w: 260px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--text-main); min-height: 100vh; }

        /* ===== MOBILE TOP BAR ===== */
        .mobile-topbar {
            display: none;
            background: var(--primary);
            color: #fff;
            padding: 12px 16px;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        @media (max-width: 768px) { .mobile-topbar { display: flex; } }
        .hamburger-btn {
            background: rgba(255,255,255,0.14);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hamburger-btn:active { background: rgba(255,255,255,0.28); }
        .topbar-title { font-size: 1.1rem; font-weight: 700; flex: 1; }

        /* ===== LAYOUT ===== */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR / DRAWER ===== */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--bg-screen);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: calc(-1 * var(--sidebar-w));
                height: 100vh;
                z-index: 300;
                box-shadow: 2px 0 20px rgba(0,0,0,0.14);
                transition: left 0.26s ease;
                padding-top: env(safe-area-inset-top, 0px);
            }
            .sidebar.open { left: 0; }
        }
        .sidebar-brand {
            padding: 20px 18px 14px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-brand .brand-name { font-size: 1.1rem; font-weight: 800; color: var(--primary-dark); }
        .sidebar-brand .brand-sub  { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }

        .nav-label {
            padding: 14px 18px 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            border-radius: 10px;
            margin: 1px 10px;
            transition: background 0.15s;
        }
        .nav-link:hover, .nav-link:focus { background: var(--bg); }
        .nav-link.active { background: #e7fce3; color: var(--primary-dark); }
        .nav-link i { width: 20px; text-align: center; color: var(--text-muted); }
        .nav-link.active i { color: var(--primary-dark); }

        /* Drawer overlay */
        .drawer-overlay { display: none; position: fixed; inset: 0; background: rgba(17,27,33,0.42); z-index: 250; }
        .drawer-overlay.open { display: block; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            padding: 24px 18px;
            overflow-y: auto;
            max-width: 980px;
        }
        @media (max-width: 768px) { .main-content { padding: 16px; } }

        .page-title { font-size: 1.35rem; font-weight: 800; margin-bottom: 4px; }
        .page-sub   { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px; }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 22px;
        }
        @media (min-width: 580px) { .kpi-grid { grid-template-columns: repeat(4, 1fr); } }
        .kpi-card {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .kpi-icon { font-size: 1.6rem; margin-bottom: 8px; }
        .kpi-value { font-size: 1.4rem; font-weight: 800; }
        .kpi-label { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }

        /* Quick Links */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        @media (min-width: 480px) { .quick-grid { grid-template-columns: repeat(3, 1fr); } }
        .quick-card {
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: #fff;
            border-radius: 14px;
            padding: 14px 12px;
            text-decoration: none;
            color: var(--text-main);
            border: 1px solid var(--border);
            font-size: 0.88rem;
            font-weight: 600;
        }
        .quick-card:active { background: var(--bg); }
        .quick-card i { font-size: 1.3rem; }
        .quick-card span { font-size: 0.75rem; color: var(--text-muted); font-weight: 400; }

        .section-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: var(--text-main); }
    </style>
</head>
<body>

<!-- Mobile Top Bar -->
<div class="mobile-topbar">
    <button type="button" class="hamburger-btn" onclick="openDrawer()" aria-label="Open navigation menu" aria-expanded="false" id="hamburgerBtn">
        <i class="fas fa-bars"></i>
    </button>
    <span class="topbar-title">Admin Console</span>
    <a href="../../index.php" style="color:#fff;font-size:0.85rem;text-decoration:none;">Store</a>
</div>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>

<div class="layout">
    <!-- Sidebar / Drawer -->
    <nav class="sidebar" id="sidebar" aria-label="Admin navigation">
        <div class="sidebar-brand">
            <div class="brand-name">🛡️ KSR Admin</div>
            <div class="brand-sub">WhatsApp Management Console</div>
        </div>

        <div class="nav-label">Store Management</div>
        <a class="nav-link active" href="../../admin/dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="nav-link" href="../../admin/orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a class="nav-link" href="../../admin/products.php"><i class="fas fa-box-open"></i> Products</a>
        <a class="nav-link" href="../../admin/payments/pending.php"><i class="fas fa-clock"></i> Pending Payments</a>
        <a class="nav-link" href="../../admin/payments/cod.php"><i class="fas fa-money-bill"></i> COD Collection</a>
        <a class="nav-link" href="../../admin/delivery.php"><i class="fas fa-truck"></i> Delivery</a>

        <div class="nav-label">WhatsApp</div>
        <a class="nav-link" href="../public/chats.php"><i class="fas fa-comments"></i> Chats</a>
        <a class="nav-link" href="../public/calls.php"><i class="fas fa-phone"></i> Calls</a>
        <a class="nav-link" href="../public/updates.php"><i class="fas fa-circle-nodes"></i> Updates</a>

        <div class="nav-label">Telehealth</div>
        <a class="nav-link" href="../telehealth/admin-dashboard.php"><i class="fas fa-stethoscope"></i> Telehealth Admin</a>
        <a class="nav-link" href="../../telehealth/doctors.php"><i class="fas fa-user-doctor"></i> Doctors</a>

        <div class="nav-label">Settings</div>
        <a class="nav-link" href="../../admin/settings.php"><i class="fas fa-gear"></i> Site Settings</a>
        <a class="nav-link" href="../../index.php"><i class="fas fa-house"></i> Main Store</a>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-title">Admin Dashboard</div>
        <div class="page-sub">KSR GURU JI · WhatsApp Management Console · <?php echo date('D, d M Y'); ?></div>

        <!-- KPI Row -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon">📦</div>
                <div class="kpi-value"><?php echo $totalOrders; ?></div>
                <div class="kpi-label">Total Orders</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">⏳</div>
                <div class="kpi-value"><?php echo $pendingOrders; ?></div>
                <div class="kpi-label">Pending Orders</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">💰</div>
                <div class="kpi-value"><?php echo fmt_inr($todaySales); ?></div>
                <div class="kpi-label">Today's Revenue</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">👤</div>
                <div class="kpi-value"><?php echo $totalUsers; ?></div>
                <div class="kpi-label">Registered Users</div>
            </div>
        </div>

        <!-- Quick Action Links -->
        <div class="section-title">Quick Actions</div>
        <div class="quick-grid">
            <a class="quick-card" href="../../admin/orders.php">
                <i class="fas fa-shopping-cart" style="color:#0ea5e9;"></i>
                Manage Orders
                <span>View &amp; process</span>
            </a>
            <a class="quick-card" href="../../admin/payments/pending.php">
                <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i>
                Pending Payments
                <span>UPI &amp; Bank transfers</span>
            </a>
            <a class="quick-card" href="../../admin/payments/cod.php">
                <i class="fas fa-money-bill-wave" style="color:#22c55e;"></i>
                COD Collection
                <span>Confirm delivery OTP</span>
            </a>
            <a class="quick-card" href="../../admin/products.php">
                <i class="fas fa-box-open" style="color:#8b5cf6;"></i>
                Products
                <span>Add / edit items</span>
            </a>
            <a class="quick-card" href="../telehealth/admin-dashboard.php">
                <i class="fas fa-stethoscope" style="color:#00a884;"></i>
                Telehealth Admin
                <span>Doctors &amp; appointments</span>
            </a>
            <a class="quick-card" href="../public/chats.php">
                <i class="fas fa-comments" style="color:#06b6d4;"></i>
                Open Chats
                <span>Customer messages</span>
            </a>
        </div>
    </main>
</div>

<script>
function openDrawer() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('drawerOverlay').classList.add('open');
    const btn = document.getElementById('hamburgerBtn');
    if (btn) btn.setAttribute('aria-expanded', 'true');
}
function closeDrawer() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('drawerOverlay').classList.remove('open');
    const btn = document.getElementById('hamburgerBtn');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawer();
});
</script>
</body>
</html>
