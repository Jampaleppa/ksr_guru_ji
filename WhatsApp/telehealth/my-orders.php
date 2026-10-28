<?php
require_once 'helper.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$patientName = 'Jampaleppa Rathod';

$pdo = getAssistDB();

// Fetch dynamic orders
try { $stmt = $pdo ? $pdo->prepare("SELECT * FROM orders WHERE name = ? ORDER BY id DESC LIMIT 10") : null; } catch (Throwable $e) { $stmt = null; }
try { if ($stmt) { $stmt->execute([$patientName]); } } catch (Throwable $e) { $stmt = null; }
$orders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | KSR GURU JI</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; }
        .dash-layout { max-width: 1000px; margin: 40px auto; padding: 0 16px; margin-bottom: 100px; }

        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 2rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .page-sub { color: var(--clr-text-secondary); font-size: 1.05rem; }

        /* Tabs */
        .dash-tabs { display: flex; border-bottom: 2px solid var(--clr-border); margin-bottom: 32px; overflow-x: auto; scrollbar-width: none; }
        .tab { padding: 12px 24px; font-weight: 600; color: var(--clr-text-secondary); cursor: pointer; transition: 0.3s; margin-bottom: -2px; border-bottom: 2px solid transparent; white-space: nowrap; }
        .tab:hover { color: var(--clr-text-primary); }
        .tab.active { color: var(--clr-primary); border-bottom-color: var(--clr-primary); }

        /* Order Cards */
        .order-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 24px; position: relative; overflow: hidden; }
        
        .oc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid var(--clr-border); padding-bottom: 20px; }
        .oc-status { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; border-radius: var(--radius-full); font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .st-active { background: #EEF2FF; color: var(--clr-primary); }
        .st-completed { background: #F0FDF4; color: #16A34A; }
        .st-cancelled { background: #FEF2F2; color: #DC2626; }
        
        .oc-id { font-family: monospace; font-size: 1.15rem; font-weight: 800; color: var(--clr-text-primary); }
        .oc-date { font-size: 0.9rem; color: var(--clr-text-secondary); margin-top: 4px; }

        .oc-body { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
        
        .oc-items { font-size: 0.95rem; color: var(--clr-text-primary); }
        .oc-items-title { font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .oc-items ul { list-style: none; padding: 0; margin: 0 0 16px 0; }
        .oc-items li { margin-bottom: 8px; padding-left: 20px; position: relative; }
        .oc-items li::before { content: '💊'; position: absolute; left: 0; top: 0; font-size: 0.85rem; }
        .oc-total { font-weight: 800; font-size: 1.25rem; color: var(--clr-primary-dark); border-top: 1px dashed var(--clr-border); padding-top: 12px; }

        /* Track Bar Embedded */
        .track-box { background: #F8FAFC; border: 1px solid var(--clr-border); border-radius: var(--radius-md); padding: 20px; }
        .tb-line { display: flex; align-items: flex-start; margin-bottom: 16px; position: relative; }
        .tb-line:last-child { margin-bottom: 0; }
        .tb-line::before { content: ''; position: absolute; left: 11px; top: 24px; bottom: -16px; width: 2px; background: var(--clr-border); z-index: 1; }
        .tb-line:last-child::before { display: none; }
        .tb-icon { width: 24px; height: 24px; border-radius: 50%; background: white; border: 2px solid var(--clr-border); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; position: relative; z-index: 2; margin-right: 12px; }
        .tb-line.done .tb-icon { background: #16A34A; border-color: #16A34A; color: white; }
        .tb-line.done::before { background: #16A34A; }
        .tb-line.current .tb-icon { border-color: var(--clr-primary); border-width: 4px; }
        .tb-text { font-weight: 600; font-size: 0.95rem; color: var(--clr-text-secondary); line-height: 24px; }
        .tb-line.done .tb-text, .tb-line.current .tb-text { color: var(--clr-text-primary); }

        .oc-footer { display: flex; gap: 12px; margin-top: 24px; border-top: 1px solid var(--clr-border); padding-top: 24px; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; transition: 0.3s; flex: 1; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); text-decoration: none; font-size: 0.95rem; }
        .btn-action:hover { background: #F8FAFC; }
        .btn-primary { background: var(--clr-primary); color: white; border: none; }
        .btn-primary:hover { background: var(--clr-primary-dark); color: white; }

        @media (max-width: 900px) {
            .oc-body { grid-template-columns: 1fr; gap: 16px; }
        }
        @media (max-width: 768px) {
            .dash-layout { margin: 20px auto 60px; padding: 0 12px; }
            .order-card { padding: 18px 14px; }
            .oc-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .oc-footer { flex-direction: column; gap: 10px; }
            .btn-action { width: 100%; }
            .navbar-links { display: none !important; }
        }

        @media (max-width: 480px) {
            .page-title { font-size: 1.5rem; }
            .page-sub { font-size: 0.9rem; }
            .dash-tabs { margin-bottom: 20px; }
            .tab { padding: 10px 16px; font-size: 0.85rem; }
            .oc-id { font-size: 1rem; }
            .oc-total { font-size: 1.1rem; }
            .track-box { padding: 14px; }
        }

        @media (max-width: 360px) {
            .page-title { font-size: 1.3rem; }
            .tab { padding: 8px 12px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="index.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Home</a>
                <a href="find-doctor.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Find Doctor</a>
                <a href="my-appointments.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Appointments</a>
                <a href="pharmacy.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Pharmacy</a>
                <a href="my-prescriptions.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Prescriptions</a>
                <a href="my-orders.php" style="color:var(--clr-primary); text-decoration:none; font-weight:600;">My Orders</a>
            </div>
        </div>
        <a href="profile.php" style="background:var(--clr-primary-light); color:var(--clr-primary); padding:10px 16px; border-radius:20px; text-decoration:none;"><i class="fas fa-user-circle"></i> Profile</a>
    </div>
</nav>

<div class="dash-layout">
    <div class="page-header">
        <h1 class="page-title">My Orders</h1>
        <p class="page-sub">Track and manage your e-commerce and medical orders securely.</p>
    </div>

    <!-- Tabs -->
    <div class="dash-tabs">
        <div class="tab active">All Orders</div>
        <div class="tab">Active</div>
        <div class="tab">Delivered</div>
        <div class="tab">Cancelled</div>
    </div>

    <?php if(empty($orders)): ?>
        <div style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
            <i class="fas fa-box-open" style="font-size:3rem; color:var(--clr-border); margin-bottom:16px;"></i>
            <h3 style="color:var(--clr-text-primary);">No Orders Found</h3>
            <p style="color:var(--clr-text-secondary);">You haven't placed any orders yet.</p>
        </div>
    <?php else: ?>
        <?php foreach($orders as $order): ?>
            <div class="order-card">
                <div class="oc-header">
                    <div>
                        <?php if($order['status'] === 'delivered'): ?>
                            <div class="oc-status st-completed">✓ DELIVERED</div>
                        <?php elseif($order['status'] === 'cancelled'): ?>
                            <div class="oc-status st-cancelled">🔴 CANCELLED</div>
                        <?php else: ?>
                            <div class="oc-status st-active">🟢 IN PROGRESS</div>
                        <?php endif; ?>
                        <div class="oc-date">Placed on: <?php echo date('d M Y, h:i A', strtotime($order['placed_at'])); ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="oc-id"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    </div>
                </div>
                
                <div class="oc-body">
                    <div class="oc-items">
                        <?php 
                        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                        $itemsStmt->execute([$order['id']]);
                        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div class="oc-items-title"><i class="fas fa-box" style="color:var(--clr-primary);"></i> <?php echo count($items); ?> Items</div>
                        <ul>
                            <?php foreach($items as $item): ?>
                                <li><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="oc-total">Total: ₹<?php echo number_format($order['total'], 2); ?></div>
                    </div>

                    <!-- Pipeline Tracker -->
                    <div class="track-box">
                        <div class="tb-line done"><div class="tb-icon"><i class="fas fa-check"></i></div><div class="tb-text">Order Placed</div></div>
                        <div class="tb-line <?php echo ($order['status'] != 'pending') ? 'done' : 'current'; ?>"><div class="tb-icon"><?php if($order['status'] != 'pending') echo '<i class="fas fa-check"></i>'; ?></div><div class="tb-text">Processing</div></div>
                        <div class="tb-line <?php echo ($order['status'] == 'shipped' || $order['status'] == 'delivered') ? 'done' : ''; ?> <?php echo ($order['status'] == 'shipped') ? 'current' : ''; ?>"><div class="tb-icon"><?php if($order['status'] == 'shipped' || $order['status'] == 'delivered') echo '<i class="fas fa-check"></i>'; ?></div><div class="tb-text">Out for Delivery</div></div>
                        <div class="tb-line <?php echo ($order['status'] == 'delivered') ? 'done current' : ''; ?>"><div class="tb-icon"><?php if($order['status'] == 'delivered') echo '<i class="fas fa-check"></i>'; ?></div><div class="tb-text">Delivered</div></div>
                    </div>
                </div>

                <div class="oc-footer">
                    <button class="btn-action btn-primary"><i class="fas fa-map-marked-alt"></i> Track Logistics</button>
                    <button class="btn-action"><i class="fas fa-file-invoice"></i> Download Invoice</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>
