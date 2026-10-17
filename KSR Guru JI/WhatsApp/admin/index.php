<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Protect Admin Route
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    // For local simulation, we'll bypass strict role guards if requested, but normally:
    header("Location: ../public/login.php"); exit;
}

try {
    $db = \Config\Database::getInstance()->getConnection();
    
    // Total Registered Users
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    // Active WebSocket Connections (approx. derived from online presence)
    $onlineCount = $db->query("SELECT COUNT(*) FROM user_profiles WHERE is_online = 1")->fetchColumn();
    // System Health Status 
    $dbHealth = "Optimal";
} catch (Exception $e) {
    $userCount = 0; $onlineCount = 0; 
    $dbHealth = "MySQL Offline";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSR GURU JI - Ecosystem Command Center</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00a884; --dark: #111b21; --bg: #f0f2f5; --card: #ffffff; --text: #3b4a54; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; padding: 0; display: flex; height: 100vh; }
        
        /* Sidebar Admin Navi */
        .sidebar { width: 280px; background: var(--dark); color: white; display: flex; flex-direction: column; }
        .brand { font-size: 1.4rem; font-weight: 700; padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); border-left: 4px solid var(--primary); }
        .nav-link { padding: 18px 25px; color: #a6b0b5; text-decoration: none; font-size: 1rem; display: flex; align-items: center; gap: 15px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.05); color: white; border-left: 4px solid var(--primary); }
        
        /* Main Dashboard Window */
        .main-content { flex: 1; overflow-y: auto; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .header h1 { color: var(--dark); margin: 0; font-size: 1.8rem; }
        .admin-profile { display: flex; align-items: center; gap: 15px; color: var(--text); font-weight: 600; }
        
        /* Metric Cards Map */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .metric-card { background: var(--card); border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); }
        .card-title { color: var(--text); font-size: 0.95rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; display:flex; justify-content:space-between; }
        .card-value { font-size: 2.2rem; font-weight: 700; color: var(--dark); display: flex; align-items: end; gap: 10px; }
        .card-delta { font-size: 0.9rem; color: #25d366; font-weight: 500; font-family: monospace; }
        
        .sys-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; background: #dcfce7; color: #166534; font-weight: 700; }
        .bg-red { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand"><i class="fab fa-whatsapp"></i> KSR GURU JI</div>
        <div style="padding: 20px 0;">
            <!-- Unified WhatsApp Core Nodes -->
            <div style="color:#667781; font-size:0.7rem; font-weight:700; padding:10px 25px; letter-spacing:1px; text-transform:uppercase;">Super-App Core</div>
            <a href="#" class="nav-link active"><i class="fas fa-chart-pie"></i> Core Telemetry</a>
            <a href="#" class="nav-link"><i class="fas fa-users"></i> Platform User Matrix</a>
            <a href="#" class="nav-link"><i class="fas fa-server"></i> v2.0 Engine Stats</a>
            
            <!-- Legacy E-Commerce Administration Hooks -->
            <div style="color:#667781; font-size:0.7rem; font-weight:700; padding:15px 25px 10px 25px; letter-spacing:1px; text-transform:uppercase;">E-Commerce Ops</div>
            <a href="../../admin/dashboard.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Store Dashboard</a>
            <a href="../../admin/orders.php" class="nav-link"><i class="fas fa-box-open"></i> Order Pipeline</a>
            <a href="../../admin/products.php" class="nav-link"><i class="fas fa-tags"></i> Product Catalog</a>
            <a href="../../admin/delivery.php" class="nav-link"><i class="fas fa-truck"></i> Delivery Routes</a>
            
            <div style="color:#667781; font-size:0.7rem; font-weight:700; padding:15px 25px 10px 25px; letter-spacing:1px; text-transform:uppercase;">Platform Health</div>
            <a href="../../admin/settings.php" class="nav-link"><i class="fas fa-cogs"></i> Global Settings</a>
        </div>
        <div style="margin-top:auto; padding:20px;">
            <a href="../public/index.php" style="color:var(--primary); text-decoration:none;"><i class="fas fa-sign-out-alt"></i> Return to Frontend</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="header">
            <h1>Platform Command Center</h1>
            <div class="admin-profile">
                <span>System Root Administrator</span>
                <i class="fas fa-user-shield" style="font-size: 1.5rem; color: var(--primary);"></i>
            </div>
        </div>

        <div class="card-grid">
            <div class="metric-card">
                <div class="card-title">Database Nodes <i class="fas fa-database"></i></div>
                <div class="card-value"><?= number_format($userCount) ?> <span class="card-delta">+1 New</span></div>
                <div style="font-size:0.8rem; color:var(--text); margin-top:10px;">Unique Registered KSR IDs</div>
            </div>
            
            <div class="metric-card">
                <div class="card-title">v2.0 WebSockets <i class="fas fa-network-wired"></i></div>
                <div class="card-value"><?= number_format($onlineCount) ?> <span class="card-delta" style="color:#667781;">Active Nodes</span></div>
                <div style="font-size:0.8rem; color:var(--text); margin-top:10px;">Live Real-Time Connections</div>
            </div>
            
            <div class="metric-card">
                <div class="card-title">SQL Core Integrity <i class="fas fa-heartbeat"></i></div>
                <div class="card-value" style="font-size:1.5rem; margin-top:15px; color:var(--primary);">
                    <span class="sys-badge <?= $dbHealth==='Optimal'?'':'bg-red' ?>"><?= $dbHealth ?></span>
                </div>
                <div style="font-size:0.8rem; color:var(--text); margin-top:10px;">PDO Singleton Wrapper</div>
            </div>
        </div>
        
    </main>

</body>
</html>
