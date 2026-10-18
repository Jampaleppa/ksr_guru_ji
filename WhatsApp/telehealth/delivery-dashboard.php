<?php
require_once 'helper.php';

$pdo = getAssistDB();
$userId = $_SESSION['user_id'] ?? 5; 

$driverName = $_SESSION['user_name'] ?? 'Courier Details';

// Stats queries
try { $assignedOrders = (int)$pdo->query("SELECT COUNT(*) FROM order_delivery WHERE delivery_user_id = " . (int)$userId . " AND delivery_time IS NULL")->fetchColumn(); } catch (Throwable $e) { $assignedOrders = 0; }
try { $completedOrders = (int)$pdo->query("SELECT COUNT(*) FROM order_delivery WHERE delivery_user_id = " . (int)$userId . " AND delivery_time IS NOT NULL")->fetchColumn(); } catch (Throwable $e) { $completedOrders = 0; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Dashboard | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; }
        .dash-layout { max-width: 1000px; margin: 40px auto; padding: 0 16px; margin-bottom: 100px; }
        .welcome-card { background: linear-gradient(135deg, #1D4ED8, #3B82F6); color: white; border-radius: var(--radius-lg); padding: 40px; margin-bottom: 32px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
        .welcome-card::after { content: '\f48b'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: rgba(255,255,255,0.1); }
        .w-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 8px; }
        .w-sub { font-size: 1.1rem; opacity: 0.9; margin-bottom: 24px; }
        
        .dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .d-card { background: white; border: 1px solid var(--clr-border); border-radius: var(--radius-lg); padding: 24px; text-decoration: none; color: var(--clr-text-primary); transition: 0.3s; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; }
        .d-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: #3B82F6; }
        .d-icon { width: 60px; height: 60px; border-radius: 16px; background: #DBEAFE; color: #1D4ED8; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; transition: 0.3s; }
        .d-card:hover .d-icon { background: #3B82F6; color: white; }
        .d-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; }
        .d-desc { font-size: 0.95rem; color: var(--clr-text-secondary); flex-grow: 1; margin-bottom: 20px; line-height: 1.5; }
        .d-action { color: #1D4ED8; font-weight: 600; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; }
        
        /* Badges */
        .badge-count { background: #DBEAFE; color: #1D4ED8; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; margin-left: auto; }
        
        .recent-section { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 24px; box-shadow: var(--shadow-sm); }
        .section-title { font-size: 1.25rem; font-weight: 700; color: #1D4ED8; margin-bottom: 20px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: var(--clr-text-secondary); }
        .empty-state i { font-size: 3rem; opacity: 0.5; margin-bottom: 16px; }

        .navbar-links { display: flex; gap: 24px; align-items: center; }
        .navbar-links a { color: var(--clr-text-secondary); text-decoration: none; font-weight: 600; transition: 0.2s; }
        .navbar-links a:hover, .navbar-links a.active { color: #3B82F6; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<?php renderRoleSwitcher(); ?>

<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
            <div class="navbar-links">
                <a href="index.php">Home</a>
                <a href="delivery-dashboard.php" class="active">Logistics Fleet</a>
                <a href="driver-dashboard.php">Driver Manifest</a>
            </div>
        </div>
        <div class="navbar-links">
            <a href="#" ><i class="fas fa-bell"></i></a>
            <a href="profile.php" style="background:#DBEAFE; color:#1D4ED8; padding:8px 16px; border-radius:20px;"><i class="fas fa-motorcycle"></i> Courier Profile</a>
        </div>
    </div>
</nav>

<div class="dash-layout">
    
    <div class="welcome-card">
        <div class="w-title">Courier Outpost, <?php echo htmlspecialchars($driverName); ?>.</div>
        <div class="w-sub">Update package tracking states and verify customer completion signatures safely.</div>
    </div>

    <div class="dash-grid">
        <a href="driver-dashboard.php" class="d-card">
            <div style="display:flex; align-items:flex-start;">
                <div class="d-icon"><i class="fas fa-box-open"></i></div>
                <?php if($assignedOrders > 0): ?><div class="badge-count"><?php echo $assignedOrders; ?> Active Trips</div><?php endif; ?>
            </div>
            <div class="d-title">Active Manifest</div>
            <div class="d-desc">View currently assigned logistics requests from verified dispensaries. Addresses and Maps are provided.</div>
            <div class="d-action">Open Fleet Manifest <i class="fas fa-arrow-right"></i></div>
        </a>

        <a href="pharmacy-orders.php" class="d-card">
            <div style="display:flex; align-items:flex-start;">
                <div class="d-icon" style="color: #10B981; background: #D1FAE5;"><i class="fas fa-clipboard-check"></i></div>
                <?php if($completedOrders > 0): ?><div class="badge-count" style="background:#D1FAE5; color:#065F46;"><?php echo $completedOrders; ?> Deliveries</div><?php endif; ?>
            </div>
            <div class="d-title">Delivery Logs</div>
            <div class="d-desc">Audit your historical fulfilled drop-offs and digital customer handshake signatures.</div>
            <div class="d-action" style="color: #10B981;">View Delivery Logs <i class="fas fa-arrow-right"></i></div>
        </a>

    </div>

    <!-- Quick Insights -->
    <div class="recent-section">
        <h3 class="section-title"><i class="fas fa-map-marked-alt"></i> Active Route GPS Map</h3>
        <?php if ($assignedOrders > 0): ?>
            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; padding: 16px; border-radius: var(--radius-sm); margin-bottom: 16px; color: #1D4ED8; font-weight: 600;">
                <i class="fas fa-motorcycle"></i> Dispatch Assigned
                <p style="font-weight: 500; font-size: 0.95rem; margin-top: 8px; color: var(--clr-text-primary);">
                    You have <strong><?php echo $assignedOrders; ?></strong> active packages loaded. Please proceed to the drop-off locations mapped in your system.
                </p>
                <div style="margin-top: 16px;">
                    <a href="delivery-dashboard.php" class="btn-action" style="border-color:#1D4ED8; color:#1D4ED8; margin-right:8px;"><i class="fas fa-map-marker-alt"></i> View Navigation Data</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-road"></i>
                <h4>Fleet is Dormant</h4>
                <p>You have no current dispatches. Wait for pharmacy assignments.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
