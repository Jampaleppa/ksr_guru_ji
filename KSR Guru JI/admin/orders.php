<?php
// ============================================================
// admin/orders.php - Complete Order Management System
// ============================================================
$currentPage = 'orders';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';

// 1. Update Order Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $allowed = ['pending', 'pending_verification', 'confirmed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];
    
    if (in_array($status, $allowed)) {
        try {
            $pdo->beginTransaction();
            
            // Update order
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            // Log to timeline
            $stmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $status, $notes, $_SESSION['admin_id']]);
            
            // If delivered, update delivered_at
            if ($status === 'delivered') {
                $pdo->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ?")->execute([$id]);
                
                // Deduct from inventory (if using inventory table)
                $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $items->execute([$id]);
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
                foreach ($items->fetchAll() as $item) {
                    $updateStmt->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            $pdo->commit();
            $message = "Order #{$id} status updated to " . ucfirst(str_replace('_', ' ', $status));
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid status.';
    }
    header('Location: orders.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// 2. Assign Delivery Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_delivery'])) {
    $order_id = (int)$_POST['order_id'];
    $driver_id = (int)$_POST['driver_id'];
    $tracking_number = $_POST['tracking_number'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET driver_id = ?, tracking_number = ?, status = 'confirmed', assigned_at = NOW() WHERE id = ?");
        $stmt->execute([$driver_id, $tracking_number, $order_id]);
        
        // Log to timeline
        $stmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'confirmed', ?, ?)");
        $stmt->execute([$order_id, "Assigned to driver ID: {$driver_id}", $_SESSION['admin_id']]);
        
        $message = "Delivery assigned successfully!";
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
    header('Location: orders.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// 3. Delete Order
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM order_timeline WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $message = 'Order deleted.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Cannot delete: ' . $e->getMessage();
    }
    header('Location: orders.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// 4. Bulk Action
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $action = $_POST['bulk_action_type'] ?? '';
    
    if ($action === 'delete') {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)")->execute($ids);
        $message = count($ids) . ' orders deleted.';
    } elseif (in_array($action, ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$action], $ids);
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)")->execute($params);
        $message = count($ids) . ' orders updated to ' . ucfirst($action);
    }
    header('Location: orders.php?msg=' . urlencode($message));
    exit;
}

// ============================================================
// FETCH DATA
// ============================================================

// Build filter query
$where = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$search, $search, $search]);
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = "o.status = ?";
    $params[] = $_GET['status'];
}
if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
    $where[] = "o.payment_status = ?";
    $params[] = $_GET['payment_status'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

$sql = "SELECT o.*, 
               o.name as customer_name, o.email as customer_email, o.phone as customer_phone,
               d.fullname as driver_name, d.phone as driver_phone
        FROM orders o
        LEFT JOIN users d ON o.driver_id = d.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats
$total = count($orders);
$pending = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$confirmed = count(array_filter($orders, fn($o) => $o['status'] === 'confirmed'));
$processing = count(array_filter($orders, fn($o) => $o['status'] === 'processing'));
$shipped = count(array_filter($orders, fn($o) => $o['status'] === 'shipped'));
$out_for_delivery = count(array_filter($orders, fn($o) => $o['status'] === 'out_for_delivery'));
$delivered = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$cancelled = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));

$totalRevenue = array_sum(array_column($orders, 'total'));
$codRevenue = array_sum(array_column(array_filter($orders, fn($o) => $o['payment_method'] === 'cod'), 'total'));
$onlineRevenue = $totalRevenue - $codRevenue;

// Fetch drivers for assignment dropdown
$drivers = $pdo->query("SELECT id, fullname AS name, phone FROM users WHERE role = 'delivery' ORDER BY fullname")->fetchAll();

// Handle messages
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Orders - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== SAME STYLES AS PREVIOUS FILES ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --sidebar-bg: #0f172a;
            --accent: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
        }
        body { font-family: 'Inter', sans-serif; background: var(--main-bg); color: var(--text-primary); }
        .sidebar { width:280px; background:var(--sidebar-bg); position:fixed; left:0; top:0; bottom:0; z-index:1000; transition:all .3s ease; display:flex; flex-direction:column; overflow-y:auto; }
        .sidebar-header { padding:20px 18px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .admin-logo { display:flex; align-items:center; gap:12px; }
        .admin-logo i { font-size:28px; color:var(--accent); }
        .admin-logo h2 { font-size:1.3rem; font-weight:700; color:white; }
        .admin-logo p { font-size:0.65rem; color:#94a3b8; }
        .admin-profile { padding:18px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size:42px; color:#94a3b8; }
        .profile-info h3 { font-size:.95rem; font-weight:600; color:white; }
        .profile-info p { font-size:.7rem; color:#94a3b8; }
        .sidebar-nav { flex:1; padding:12px 0; }
        .nav-section h4 { padding:0 20px; font-size:.65rem; letter-spacing:1px; color:#64748b; margin-bottom:8px; text-transform:uppercase; }
        .nav-item { display:flex; align-items:center; gap:14px; padding:9px 20px; color:#e2e8f0; transition:.2s; font-size:.9rem; border-left:3px solid transparent; text-decoration:none; }
        .nav-item i { width:22px; text-align:center; }
        .nav-item.active { background:rgba(59,130,246,0.15); border-left-color:var(--accent); color:white; }
        .nav-item:hover:not(.active) { background:rgba(255,255,255,0.05); }
        .sidebar-footer { padding:16px 18px; border-top:1px solid rgba(255,255,255,0.08); }
        .logout-btn { width:100%; background:rgba(239,68,68,0.15); border:none; padding:10px; border-radius:12px; color:#f87171; font-weight:500; display:flex; align-items:center; justify-content:center; gap:10px; transition:.2s; cursor:pointer; }
        .logout-btn:hover { background:rgba(239,68,68,0.3); }
        .main-content { margin-left:280px; padding:20px 28px; min-height:100vh; }
        .top-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:24px; }
        .header-left h1 { font-size:1.6rem; font-weight:700; display:flex; align-items:center; gap:10px; }
        .header-left h1 i { color:var(--accent); }
        .breadcrumb { color:var(--text-secondary); font-size:.8rem; }
        .header-right { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
        .search-box { background:var(--card-bg); border-radius:40px; padding:7px 16px; display:flex; align-items:center; gap:8px; box-shadow:var(--shadow); border:1px solid var(--border-light); }
        .search-box input { background:transparent; border:none; outline:none; color:var(--text-primary); font-size:.85rem; min-width:180px; }
        .search-box i { color:var(--text-secondary); }
        .header-btn { background:var(--card-bg); border:none; width:38px; height:38px; border-radius:50%; cursor:pointer; color:var(--text-primary); box-shadow:var(--shadow); display:flex; align-items:center; justify-content:center; font-size:.95rem; transition:.2s; position:relative; }
        .header-btn:hover { transform:scale(1.05); }
        .user-menu { display:flex; align-items:center; gap:10px; background:var(--card-bg); padding:4px 14px 4px 10px; border-radius:50px; cursor:pointer; box-shadow:var(--shadow); border:1px solid var(--border-light); }
        .user-avatar i { font-size:28px; color:var(--accent); }
        .user-info .user-name { font-weight:600; font-size:.85rem; }
        .user-info .user-role { font-size:.65rem; color:var(--text-secondary); display:block; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px,1fr)); gap:12px; margin-bottom:20px; }
        .stat-card { background:var(--card-bg); border-radius:var(--radius-lg); padding:14px 16px; box-shadow:var(--shadow); text-align:center; border:1px solid var(--border-light); }
        .stat-card .stat-value { font-size:1.5rem; font-weight:800; }
        .stat-card .stat-label { font-size:.7rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px; }
        .filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; background:var(--card-bg); padding:12px 16px; border-radius:var(--radius-lg); box-shadow:var(--shadow); }
        .filter-bar select, .filter-bar input { padding:6px 12px; border-radius:var(--radius-md); border:1px solid var(--border-light); background:var(--main-bg); color:var(--text-primary); font-size:.8rem; }
        .table-container { background:var(--card-bg); border-radius:var(--radius-lg); padding:16px 18px; box-shadow:var(--shadow); overflow-x:auto; }
        .table-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px; }
        .table-header h3 { font-size:1rem; font-weight:600; }
        .data-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .data-table th, .data-table td { padding:10px 8px; text-align:left; border-bottom:1px solid var(--border-light); }
        .data-table th { font-weight:600; color:var(--text-secondary); font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; }
        .status-badge { padding:3px 12px; border-radius:20px; font-size:.7rem; font-weight:600; display:inline-block; }
        .status-badge.pending { background:#fef3c7; color:#92400e; }
        .status-badge.pending_verification { background:#f3e8ff; color:#6b21a8; }
        .status-badge.confirmed { background:#dbeafe; color:#1e40af; }
        .status-badge.processing { background:#ede9fe; color:#6d28d9; }
        .status-badge.shipped { background:#d1d5db; color:#374151; }
        .status-badge.out_for_delivery { background:#ece0fd; color:#5b21b6; }
        .status-badge.delivered { background:#dcfce7; color:#15803d; }
        .status-badge.cancelled { background:#fee2e2; color:#b91c1c; }
        .payment-badge { padding:2px 10px; border-radius:20px; font-size:.65rem; font-weight:600; display:inline-block; }
        .payment-badge.paid { background:#dcfce7; color:#15803d; }
        .payment-badge.pending { background:#fef3c7; color:#92400e; }
        .payment-badge.pending_verification { background:#f3e8ff; color:#6b21a8; }
        .payment-badge.failed { background:#fee2e2; color:#b91c1c; }
        .action-btn { background:transparent; border:none; padding:4px 8px; border-radius:8px; cursor:pointer; transition:.2s; color:var(--text-secondary); }
        .action-btn:hover { background:var(--main-bg); }
        .action-btn.primary { color:var(--accent); }
        .action-btn.danger { color:var(--danger); }
        .action-btn.success { color:var(--success); }
        .btn { padding:6px 16px; border-radius:40px; border:none; font-weight:500; font-size:.8rem; transition:.2s; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
        .btn-primary { background:var(--accent); color:white; }
        .btn-primary:hover { background:#2563eb; }
        .btn-success { background:var(--success); color:white; }
        .btn-success:hover { background:#059669; }
        .btn-danger { background:var(--danger); color:white; }
        .btn-danger:hover { background:#dc2626; }
        .btn-outline { background:transparent; border:1px solid var(--border-light); color:var(--text-primary); }
        .btn-outline:hover { background:var(--main-bg); }
        .btn-sm { padding:4px 12px; font-size:.7rem; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:var(--card-bg); border-radius:var(--radius-lg); max-width:560px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:modalIn .25s ease; }
        @keyframes modalIn { from { transform:scale(.95) translateY(20px); opacity:0; } to { transform:scale(1) translateY(0); opacity:1; } }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border-light); }
        .modal-header h3 { font-size:1.1rem; font-weight:600; }
        .modal-close { background:none; border:none; font-size:1.3rem; color:var(--text-secondary); cursor:pointer; padding:4px 8px; border-radius:8px; transition:.2s; }
        .modal-close:hover { background:var(--main-bg); }
        .modal-body { padding:16px 20px; }
        .modal-footer { padding:14px 20px; border-top:1px solid var(--border-light); display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
        .form-group { margin-bottom:14px; }
        .form-group label { display:block; font-weight:500; font-size:.85rem; margin-bottom:4px; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:8px 12px; border-radius:var(--radius-md); border:1px solid var(--border-light); background:var(--main-bg); color:var(--text-primary); font-size:.9rem; transition:.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(59,130,246,0.15); }
        .toast { position:fixed; bottom:30px; left:50%; transform:translateX(-50%); padding:12px 24px; border-radius:40px; color:white; font-weight:500; font-size:.9rem; z-index:5000; box-shadow:0 8px 30px rgba(0,0,0,0.2); animation:toastIn .3s ease; opacity:0; pointer-events:none; transition:opacity .3s; }
        .toast.show { opacity:1; pointer-events:auto; }
        .toast.success { background:var(--success); }
        .toast.error { background:var(--danger); }
        .toast.info { background:var(--accent); }
        @keyframes toastIn { from { transform:translateX(-50%) translateY(20px); opacity:0; } to { transform:translateX(-50%) translateY(0); opacity:1; } }
        .bulk-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:10px 0; border-top:1px solid var(--border-light); margin-top:10px; }
        .bulk-bar select { padding:4px 10px; border-radius:var(--radius-md); border:1px solid var(--border-light); background:var(--main-bg); font-size:.8rem; }
        @media (max-width:768px) { .sidebar { transform:translateX(-100%); } .sidebar.mobile-open { transform:translateX(0); } .main-content { margin-left:0; padding:16px; } .stats-grid { grid-template-columns:1fr 1fr; } }
        .revenue-row { background:var(--main-bg); border-radius:var(--radius-md); padding:8px 12px; margin-top:8px; display:flex; justify-content:space-between; font-size:.8rem; }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'orders'; include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-shopping-cart"></i> Orders</h1>
                <div class="breadcrumb">Admin / Orders</div>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search orders..." id="searchInput" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Admin</span></div>
                </div>
            </div>
        </header>

        <!-- ===== STATS ===== -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--warning);"><?= $pending ?></div><div class="stat-label">Pending</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--info);"><?= $confirmed ?></div><div class="stat-label">Confirmed</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--purple);"><?= $processing ?></div><div class="stat-label">Processing</div></div>
            <div class="stat-card"><div class="stat-value" style="color:#6b7280;"><?= $shipped ?></div><div class="stat-label">Shipped</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--danger);"><?= $out_for_delivery ?></div><div class="stat-label">Out for Delivery</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--success);"><?= $delivered ?></div><div class="stat-label">Delivered</div></div>
            <div class="stat-card"><div class="stat-value" style="color:var(--danger);"><?= $cancelled ?></div><div class="stat-label">Cancelled</div></div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="revenue-row">
            <span><strong>Total Revenue:</strong> ₹<?= number_format($totalRevenue, 2) ?></span>
            <span><span style="color:var(--success);">COD: ₹<?= number_format($codRevenue, 2) ?></span> | <span style="color:var(--accent);">Online: ₹<?= number_format($onlineRevenue, 2) ?></span></span>
        </div>

        <?php if ($message): ?>
            <div class="toast show success" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="toast show error" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== FILTER BAR ===== -->
        <form method="GET" class="filter-bar" id="filterForm">
            <select name="status">
                <option value="">All Status</option>
                <option value="pending" <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="pending_verification" <?= isset($_GET['status']) && $_GET['status'] === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
                <option value="confirmed" <?= isset($_GET['status']) && $_GET['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="processing" <?= isset($_GET['status']) && $_GET['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="shipped" <?= isset($_GET['status']) && $_GET['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="out_for_delivery" <?= isset($_GET['status']) && $_GET['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                <option value="delivered" <?= isset($_GET['status']) && $_GET['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <select name="payment_status">
                <option value="">All Payment</option>
                <option value="paid" <?= isset($_GET['payment_status']) && $_GET['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending_verification" <?= isset($_GET['payment_status']) && $_GET['payment_status'] === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
                <option value="pending" <?= isset($_GET['payment_status']) && $_GET['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="failed" <?= isset($_GET['payment_status']) && $_GET['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
            <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" placeholder="To">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="orders.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="button" class="btn btn-outline btn-sm" id="exportBtn"><i class="fas fa-download"></i> Export</button>
        </form>

        <!-- ===== ORDER TABLE ===== -->
        <div class="table-container">
            <div class="table-header">
                <h3><?= count($orders) ?> Orders</h3>
                <div>
                    <span style="font-size:.7rem;color:var(--text-secondary);">Last updated: <?= date('d M Y, h:i A') ?></span>
                </div>
            </div>

            <form method="POST" id="bulkForm">
                <table class="data-table" id="orderTable">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll"></th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Driver</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr data-id="<?= $o['id'] ?>">
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $o['id'] ?>" class="row-checkbox"></td>
                                <td><strong>#<?= htmlspecialchars($o['order_number'] ?? $o['id']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?>
                                    <br><small style="color:var(--text-secondary);font-size:.7rem;"><?= htmlspecialchars($o['customer_phone'] ?? '') ?></small>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></td>
                                <td>₹<?= number_format($o['total'], 2) ?></td>
                                <td><span class="payment-badge <?= $o['payment_status'] ?? 'pending' ?>"><?= ucfirst($o['payment_status'] ?? 'Pending') ?></span></td>
                                <td><span class="status-badge <?= $o['status'] ?>"><?= ucfirst(str_replace('_', ' ', $o['status'])) ?></span></td>
                                <td><?= htmlspecialchars($o['driver_name'] ?? '—') ?></td>
                                <td>
                                    <button class="action-btn primary view-order" data-id="<?= $o['id'] ?>" title="View Details"><i class="fas fa-eye"></i></button>
                                    <button class="action-btn primary status-btn" data-id="<?= $o['id'] ?>" data-status="<?= $o['status'] ?>" title="Update Status"><i class="fas fa-edit"></i></button>
                                    <button class="action-btn success assign-btn" data-id="<?= $o['id'] ?>" title="Assign Delivery"><i class="fas fa-truck"></i></button>
                                    <button class="action-btn danger delete-btn" data-id="<?= $o['id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="9" class="text-center" style="padding:30px;color:var(--text-secondary);">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Bulk Actions -->
                <div class="bulk-bar">
                    <span id="selectedCount">0 selected</span>
                    <select name="bulk_action_type" id="bulkAction">
                        <option value="">Bulk Action</option>
                        <option value="pending">Set Pending</option>
                        <option value="confirmed">Set Confirmed</option>
                        <option value="processing">Set Processing</option>
                        <option value="shipped">Set Shipped</option>
                        <option value="delivered">Set Delivered</option>
                        <option value="cancelled">Set Cancelled</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" name="bulk_action" value="1" class="btn btn-primary btn-sm">Apply</button>
                </div>
            </form>
        </div>
    </main>

    <!-- ============================================================ -->
    <!-- MODALS -->
    <!-- ============================================================ -->

    <!-- ===== MODAL: Update Status ===== -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Update Order Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="statusOrderId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="statusSelect">
                            <option value="pending">Pending</option>
                            <option value="pending_verification">Pending Verification</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <textarea name="notes" rows="2" placeholder="Add notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL: Assign Delivery ===== -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Assign Delivery Partner</h3>
                <button class="modal-close" onclick="closeModal('assignModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="assignOrderId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Delivery Partner</label>
                        <select name="driver_id" id="driverSelect" required>
                            <option value="">Select Driver</option>
                            <?php foreach ($drivers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'] ?? '') ?> (<?= htmlspecialchars($d['phone'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tracking Number</label>
                        <input type="text" name="tracking_number" placeholder="Enter tracking number (optional)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('assignModal')">Cancel</button>
                    <button type="submit" name="assign_delivery" class="btn btn-success"><i class="fas fa-truck"></i> Assign</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAL: Delete Confirmation ===== -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this order? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Order Details (View) ===== -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-box" style="max-width:700px;">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div style="text-align:center;padding:20px;color:var(--text-secondary);">Loading...</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('viewModal')">Close</button>
                <button class="btn btn-primary" id="viewPrintBtn"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================================ -->
    <script>
        // ===== MODAL HELPERS =====
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
        });

        // ===== UPDATE STATUS =====
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('statusOrderId').value = this.dataset.id;
                document.getElementById('statusSelect').value = this.dataset.status;
                openModal('statusModal');
            });
        });

        // ===== ASSIGN DELIVERY =====
        document.querySelectorAll('.assign-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('assignOrderId').value = this.dataset.id;
                openModal('assignModal');
            });
        });

        // ===== DELETE =====
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('confirmDeleteBtn').href = 'orders.php?action=delete&id=' + id;
                openModal('deleteModal');
            });
        });

        // ===== VIEW ORDER DETAILS (AJAX) =====
        document.querySelectorAll('.view-order').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                const body = document.getElementById('viewModalBody');
                body.innerHTML = 'Loading...';
                openModal('viewModal');
                
                try {
                    const resp = await fetch(`ajax/order-details.php?id=${id}`);
                    const html = await resp.text();
                    body.innerHTML = html;
                } catch (e) {
                    body.innerHTML = '<div style="color:var(--danger);">Failed to load order details.</div>';
                }
            });
        });

        // ===== PRINT ORDER =====
        document.getElementById('viewPrintBtn').addEventListener('click', function() {
            const printContent = document.getElementById('viewModalBody').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Print Order</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">');
            printWindow.document.write('<style>body{font-family:sans-serif;padding:20px;color:#334155;} table{width:100%;border-collapse:collapse;margin-top:10px;} th,td{border-bottom:1px solid #e2e8f0;padding:8px;text-align:left;} th{background:#f8fafc;} .status-badge,.payment-badge{padding:3px 12px;border-radius:20px;font-size:.7rem;font-weight:600;display:inline-block;background:#e2e8f0;} [style*="display:flex"]{display:none !important;} h4:last-of-type, h4:last-of-type + div { display:none !important; }</style>');
            printWindow.document.write('    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">');
            printWindow.document.write('    <script src="js/admin-theme.js?v=1.6" defer><\/script>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        });

        // ===== SEARCH =====
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const params = new URLSearchParams(window.location.search);
                params.set('search', this.value);
                window.location.search = params.toString();
            }
        });

        // ===== SELECT ALL =====
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
            updateCount();
        });
        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.addEventListener('change', updateCount);
        });

        function updateCount() {
            const count = document.querySelectorAll('.row-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = count + ' selected';
        }

        // ===== EXPORT CSV =====
        document.getElementById('exportBtn').addEventListener('click', function() {
            const rows = document.querySelectorAll('#orderTable tbody tr');
            let csv = 'Order ID,Customer,Phone,Date,Amount,Payment,Status\n';
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;
                csv += cells[0].textContent.trim() + ',' +
                       cells[1].textContent.trim() + ',' +
                       cells[2].textContent.trim() + ',' +
                       cells[3].textContent.trim() + ',' +
                       cells[4].textContent.replace('₹','').trim() + ',' +
                       cells[5].textContent.trim() + ',' +
                       cells[6].textContent.trim() + '\n';
            });
            const blob = new Blob([csv], {type: 'text/csv'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'orders_<?= date('Y-m-d') ?>.csv';
            a.click();
        });

        // ===== AUTO-HIDE TOAST =====
        setTimeout(() => {
            document.querySelectorAll('.toast').forEach(t => t.style.opacity = '0');
        }, 4000);
    </script>

</body>
</html>