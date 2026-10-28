<?php
require_once 'helper.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pdo = getAssistDB();
$pharmacistName = $_SESSION['user_name'] ?? 'Pharmacist';

// Fetch Orders
$stmt = $pdo->query("
    SELECT o.*, 
           COALESCE(u.name, o.name) as patient_name, 
           COALESCE(u.email, o.email) as patient_email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE LOWER(o.status) IN ('pending', 'approved', 'pharmacy_accepted') 
    ORDER BY o.placed_at DESC, o.id DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Orders | Doctor Assist</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        body { 
            background: #F1F5F9; 
            margin: 0; 
            padding-bottom: 40px; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(22, 163, 74, 0.05), transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(37, 99, 235, 0.05), transparent 30%);
        }
        
        /* Pharmacy Navbar Theme */
        .pha-nav { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border); 
            padding: 12px 0; 
            box-shadow: var(--shadow-glass);
            position: sticky; top: 0; z-index: 100;
        }
        .dash-layout { max-width: 1200px; margin: 40px auto; padding: 0 16px; margin-bottom: 100px; flex: 1; }
        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .ph-title { font-size: 1.8rem; font-weight: 900; color: #166534; }
        .ph-sub { font-size: 1rem; color: var(--clr-text-secondary); margin-top: 4px; font-weight: 600; }
        
        .order-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }
        .order-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            border: 2px solid white;
            padding: 24px;
            box-shadow: var(--shadow-glass);
            transition: 0.3s;
        }
        .order-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
        .oc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; }
        .oc-id { font-weight: 900; font-size: 1.1rem; color: #166534; font-family: monospace; }
        .oc-date { font-size: 0.8rem; color: var(--clr-text-secondary); font-weight: 700; margin-top: 2px; }
        
        .oc-status {
            display: inline-flex; align-items: center; gap: 6px;
            background: #FEF3C7; color: #D97706; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 900; box-shadow: var(--shadow-sm); text-transform: uppercase;
        }
        .oc-pat { margin: 12px 0; font-size: 1rem; font-weight: 800; color: var(--clr-primary-dark); }
        .oc-items { background: rgba(255,255,255,0.6); padding: 16px; border: 1px solid white; border-radius: var(--radius-md); font-size: 0.85rem; margin-bottom: 20px; min-height: 60px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        
        .btn-pack {
            width: 100%;
            background: #166534;
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-md);
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .btn-pack:hover { background: #14532D; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(22, 101, 52, 0.3); }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: rgba(255,255,255,0.7);
            border-radius: var(--radius-lg);
            border: 2px dashed #CBD5E1;
            backdrop-filter: blur(12px);
        }
        .empty-state i { font-size: 4rem; color: #94A3B8; margin-bottom: 16px; }
        .empty-state h2 { color: var(--clr-primary-dark); margin: 0 0 8px 0; }
        .navbar-links a { color: #64748B; text-decoration: none; transition: 0.3s; padding: 8px 12px; font-weight: 600; border-radius: 8px; }
        .navbar-links a.active, .navbar-links a:hover { color: #166534; background: rgba(22, 163, 74, 0.1); }
        @media (max-width: 768px) {
            .dash-layout { margin: 20px auto 60px; padding: 0 12px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .order-grid { grid-template-columns: 1fr; gap: 16px; }
            .navbar-links { display: none !important; }
        }

        @media (max-width: 480px) {
            .ph-title { font-size: 1.4rem; }
            .ph-sub { font-size: 0.9rem; }
            .order-card { padding: 16px; }
            .btn-pack { padding: 10px; font-size: 0.9rem; }
        }

        @media (max-width: 360px) {
            .ph-title { font-size: 1.25rem; }
            .oc-id { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar pha-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="pharmacist-dashboard.php" class="navbar-brand" style="color:var(--clr-primary-dark); text-decoration:none; font-weight:800; font-size:1.2rem;"><i class="fas fa-prescription-bottle-alt"></i> PHARMACY PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 16px;">
                <a href="pharmacist-dashboard.php" style="font-weight:600;"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="pharmacy-verification.php" style="font-weight:600;"><i class="fas fa-tasks"></i> Verification Queue</a>
                <a href="pharmacy-orders.php" class="active" style="font-weight:600;"><i class="fas fa-box"></i> Orders & Packing</a>
                <a href="pharmacy-inventory.php" style="font-weight:600;"><i class="fas fa-cubes"></i> Inventory</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="profile.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600; background:#F1F5F9; color:var(--clr-text-primary);"><i class="fas fa-user-shield text-primary"></i> PHA-0021</a>
        </div>
    </div>
</nav>

<div class="dash-layout">
    <div class="page-header">
        <div>
            <div class="ph-title">Order Fulfillment</div>
            <div class="ph-sub">Manage active prescriptions, package medicines, and dispatch to courier.</div>
        </div>
        <div>
            <div style="background: white; border: 1px solid var(--clr-border); padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem;">
                <i class="fas fa-clock text-primary"></i> <?php echo count($orders); ?> Orders Pending
            </div>
        </div>
    </div>

    <div class="order-grid">
        <?php if (count($orders) > 0): ?>
            <?php foreach($orders as $idx => $order): ?>
                <div class="order-card">
                    <div class="oc-header">
                        <div>
                            <div class="oc-id">ORD-<?php echo 10200 + $order['id']; ?></div>
                            <div class="oc-date"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="oc-status"><i class="fas fa-box-open"></i> PACKING</div>
                    </div>
                    
                    <div class="oc-pat">
                        <i class="fas fa-user-injured text-primary"></i> <?php echo htmlspecialchars($order['patient_name']); ?>
                    </div>
                    
                    <div class="oc-items">
                        <div style="font-weight:700; margin-bottom:8px;">Prescription Items:</div>
                        • Pregabalin 75mg (14 Days)<br>
                        • Baclofen (20mg)<br>
                        <i class="fas fa-check-circle text-success" style="margin-top:8px;"></i> Verified by Pharmacist
                    </div>
                    
                    <button class="btn-pack"><i class="fas fa-box"></i> Mark as Packed & Ready</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h2>No Orders Pending</h2>
                <p style="color:var(--clr-text-secondary);">There are currently no medication orders waiting for fulfillment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
