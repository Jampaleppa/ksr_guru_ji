<?php
// ============================================================
// admin/products.php - Standardized Product Management
// Fully integrated with PDO and actual database schema
// ============================================================
$currentPage = 'products';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// --- HANDLE ACTIONS ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

// Add / Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $action_type = $_POST['action_type'] ?? 'add';
    $name = trim($_POST['name'] ?? '');
    $kn_name = trim($_POST['kn_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0.00);
    $category_id = trim($_POST['category_id'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-box');
    $emoji = trim($_POST['emoji'] ?? '📦');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $initial_stock = intval($_POST['stock'] ?? 10);

    if (empty($name)) {
        $error = 'Product name is required.';
    }

    if (empty($error)) {
        try {
            if ($action_type === 'add') {
                // Insert Product
                $stmt = $pdo->prepare("INSERT INTO products (name, kn_name, price, category_id, icon, emoji, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $kn_name, $price, $category_id, $icon, $emoji, $is_featured]);
                $newProductId = $pdo->lastInsertId();

                // Add corresponding row in inventory table
                $stmtInventory = $pdo->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?)");
                $stmtInventory->execute([$newProductId, $initial_stock]);

                $message = 'Product added successfully.';
            } else {
                // Edit
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = 'Product ID is missing or invalid.';
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET name=?, kn_name=?, price=?, category_id=?, icon=?, emoji=?, is_featured=? WHERE id=?");
                    $stmt->execute([$name, $kn_name, $price, $category_id, $icon, $emoji, $is_featured, $id]);

                    // Update corresponding row in inventory table
                    $stmtInventory = $pdo->prepare("UPDATE inventory SET quantity=? WHERE product_id=?");
                    $stmtInventory->execute([$initial_stock, $id]);

                    $message = 'Product updated successfully.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: products.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Delete Product
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Check if any order items are referencing this product
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $stmt->execute([$id]);
    $order_count = $stmt->fetchColumn();
    
    if ($order_count > 0) {
        $error = "Cannot delete: This product is linked to $order_count orders. Consider setting its stock to 0 instead.";
    } else {
        try {
            // Delete from inventory table first
            $pdo->prepare("DELETE FROM inventory WHERE product_id = ?")->execute([$id]);
            
            // Delete from products table
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = 'Product deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: products.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Bulk Delete Products
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $canDelete = true;
    $totalOrderCount = 0;
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT product_id, COUNT(*) as count FROM order_items WHERE product_id IN ($placeholders) GROUP BY product_id");
        $stmt->execute($ids);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($ids as $id) {
            $order_count = $counts[$id] ?? 0;
            if ($order_count > 0) {
                $canDelete = false;
                $totalOrderCount += $order_count;
            }
        }
    }
    
    if (!$canDelete) {
        $error = "Cannot delete: One or more selected products are linked to customer orders ($totalOrderCount total purchases).";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Delete from inventory table
            $pdo->prepare("DELETE FROM inventory WHERE product_id IN ($placeholders)")->execute($ids);
            
            // Delete from products table
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $message = count($ids) . ' products deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: products.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// --- FETCH DATA ---
$products = $pdo->query("
    SELECT p.*, c.name as category_name, COALESCE(i.quantity, 0) as stock 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN inventory i ON p.id = i.product_id 
    ORDER BY p.name ASC
")->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Stats calculations
$totalProducts = count($products);
$featuredCount = count(array_filter($products, fn($p) => $p['is_featured'] == 1));
$lowStockCount = count(array_filter($products, fn($p) => $p['stock'] < 5));
$totalCategories = count($categories);

if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Products - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --radius-lg: 1rem;
            --radius-md: 0.75rem;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { font-family: 'Inter', sans-serif; background: var(--main-bg); color: var(--text-primary); }
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
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .nav-item.active { background: rgba(59,130,246,0.15); border-left-color: var(--accent); color: white; }
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
        .main-content { margin-left: 280px; padding: 20px 28px; min-height: 100vh; }
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
        .stat-icon.products { background: #dbeafe; color: #3b82f6; }
        .stat-icon.featured { background: #fef3c7; color: #d97706; }
        .stat-icon.lowstock { background: #fee2e2; color: #ef4444; }
        .stat-icon.categories { background: #f3e8ff; color: #a855f7; }
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
        .emoji-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .status-badge.featured { background: #fef3c7; color: #d97706; }
        .status-badge.standard { background: #f1f5f9; color: #475569; }
        .stock-badge { padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .stock-badge.good { background: #dcfce7; color: #15803d; }
        .stock-badge.low { background: #fee2e2; color: #b91c1c; }
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
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .form-group-checkbox { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
        .form-group-checkbox input { width: auto; cursor: pointer; }
        .form-group-checkbox label { font-weight: 500; font-size: 0.85rem; cursor: pointer; margin-bottom: 0; }
        .toast-msg {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: var(--card-bg);
            border-radius: var(--radius-md);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 9999;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            transition: opacity 0.3s;
        }
        .toast-msg.success { border-left: 4px solid var(--success); color: #065f46; background: #ecfdf5; }
        .toast-msg.error { border-left: 4px solid var(--danger); color: #991b1b; background: #fef2f2; }
        @keyframes slideIn {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'products'; include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-box"></i> Product Management</h1>
                <div class="breadcrumb"><span>Admin</span> <i class="fas fa-chevron-right"></i> <span class="active">Products</span></div>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search products..." id="searchInput">
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Toast Messages -->
        <?php if (!empty($message)): ?>
            <div class="toast-msg success" id="toastMsg"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="toast-msg error" id="toastMsg"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalProducts ?></h3>
                    <p>Total Products</p>
                </div>
                <div class="stat-icon products"><i class="fas fa-box"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $featuredCount ?></h3>
                    <p>Featured Items</p>
                </div>
                <div class="stat-icon featured"><i class="fas fa-star"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $lowStockCount ?></h3>
                    <p>Low Stock (< 5)</p>
                </div>
                <div class="stat-icon lowstock"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalCategories ?></h3>
                    <p>Product Categories</p>
                </div>
                <div class="stat-icon categories"><i class="fas fa-tags"></i></div>
            </div>
        </section>

        <!-- Main Products Card -->
        <div class="table-container">
            <div class="table-header">
                <h3>All Products</h3>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-primary" id="addProductBtn"><i class="fas fa-plus"></i> Add Product</button>
                    <button class="btn btn-outline" id="exportBtn"><i class="fas fa-file-export"></i> Export CSV</button>
                    <button class="btn btn-outline" id="bulkDeleteBtn" style="color:var(--danger); border-color:rgba(239,68,68,0.2);"><i class="fas fa-trash"></i> Bulk Delete</button>
                </div>
            </div>
            <form method="POST" id="bulkForm">
                <table class="data-table" id="productTable">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Icon</th>
                            <th>Product Name</th>
                            <th>Kannada Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Featured</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($p['id']) ?>" class="row-checkbox"></td>
                                <td><code>#<?= htmlspecialchars($p['id']) ?></code></td>
                                <td>
                                    <div class="emoji-avatar">
                                        <?= htmlspecialchars($p['emoji'] ?: '📦') ?>
                                    </div>
                                </td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><?= htmlspecialchars($p['kn_name']) ?></td>
                                <td><span class="status-badge standard"><?= htmlspecialchars($p['category_name'] ?: 'None') ?></span></td>
                                <td><strong>₹<?= number_format($p['price'], 2) ?></strong></td>
                                <td>
                                    <span class="stock-badge <?= ($p['stock'] < 5) ? 'low' : 'good' ?>">
                                        <?= htmlspecialchars($p['stock']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['is_featured'] == 1): ?>
                                        <span class="status-badge featured">Yes</span>
                                    <?php else: ?>
                                        <span class="status-badge standard">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="view-btn edit-btn" 
                                            data-id="<?= htmlspecialchars($p['id']) ?>" 
                                            data-name="<?= htmlspecialchars($p['name']) ?>" 
                                            data-kn_name="<?= htmlspecialchars($p['kn_name']) ?>" 
                                            data-price="<?= htmlspecialchars($p['price']) ?>" 
                                            data-category_id="<?= htmlspecialchars($p['category_id']) ?>" 
                                            data-icon="<?= htmlspecialchars($p['icon']) ?>" 
                                            data-emoji="<?= htmlspecialchars($p['emoji']) ?>" 
                                            data-is_featured="<?= htmlspecialchars($p['is_featured']) ?>"
                                            data-stock="<?= htmlspecialchars($p['stock']) ?>"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="view-btn delete-btn" data-id="<?= htmlspecialchars($p['id']) ?>" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="10" style="padding:30px;color:var(--text-secondary); text-align:center;">No products found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <input type="hidden" name="bulk_action" value="1" id="bulkActionField">
            </form>
        </div>
    </main>

    <!-- ===== MODAL: Add/Edit Product ===== -->
    <div class="modal-overlay" id="productModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Add Product</h3>
                <button class="modal-close" onclick="closeModal('productModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="productForm">
                <input type="hidden" name="action_type" id="actionType" value="add">
                <input type="hidden" name="id" id="productId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name (English)</label>
                        <input type="text" name="name" id="productName" required placeholder="e.g. Marie Gold Biscuit">
                    </div>
                    <div class="form-group">
                        <label>Product Name (Kannada)</label>
                        <input type="text" name="kn_name" id="productKnName" placeholder="e.g. ಮಾರಿ ಗೋಲ್ಡ್ ಬಿಸ್ಕತ್ತು">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="productCategoryId" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="price" id="productPrice" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock" id="productStock" required placeholder="10" value="10">
                    </div>
                    <div class="form-group" style="display:none;">
                        <label>FontAwesome Icon Class</label>
                        <input type="text" name="icon" id="productIcon" value="fa-box" placeholder="e.g. fa-box">
                    </div>
                    <div class="form-group">
                        <label>Emoji Icon</label>
                        <input type="text" name="emoji" id="productEmoji" value="📦" placeholder="e.g. 🍞">
                    </div>
                    <div class="form-group-checkbox">
                        <input type="checkbox" name="is_featured" id="productFeatured" value="1">
                        <label for="productFeatured">Featured Product (Shows on home page / special sections)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('productModal')">Cancel</button>
                    <button type="submit" name="save_product" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== DELETE CONFIRMATION MODAL ===== -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product?</p>
                <p style="color:var(--danger);font-size:0.85rem;margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone, and will fail if the product has associated order receipts.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <script>
        // ===== MODAL HELPERS =====
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        // ===== ADD PRODUCT =====
        document.getElementById('addProductBtn').addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('actionType').value = 'add';
            document.getElementById('productId').value = '';
            document.getElementById('productName').value = '';
            document.getElementById('productKnName').value = '';
            document.getElementById('productCategoryId').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productStock').value = '10';
            document.getElementById('productIcon').value = 'fa-box';
            document.getElementById('productEmoji').value = '📦';
            document.getElementById('productFeatured').checked = false;
            openModal('productModal');
        });

        // ===== EDIT PRODUCT =====
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('modalTitle').textContent = 'Edit Product';
                document.getElementById('actionType').value = 'edit';
                document.getElementById('productId').value = this.dataset.id;
                document.getElementById('productName').value = this.dataset.name;
                document.getElementById('productKnName').value = this.dataset.kn_name;
                document.getElementById('productCategoryId').value = this.dataset.category_id;
                document.getElementById('productPrice').value = this.dataset.price;
                document.getElementById('productStock').value = this.dataset.stock;
                document.getElementById('productIcon').value = this.dataset.icon;
                document.getElementById('productEmoji').value = this.dataset.emoji;
                document.getElementById('productFeatured').checked = (this.dataset.is_featured == 1);
                openModal('productModal');
            });
        });

        // ===== DELETE PRODUCT =====
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('confirmDeleteBtn').href = 'products.php?action=delete&id=' + encodeURIComponent(id);
                openModal('deleteModal');
            });
        });

        // ===== SEARCH =====
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // ===== EXPORT CSV =====
        document.getElementById('exportBtn').addEventListener('click', function() {
            const rows = document.querySelectorAll('#productTable tbody tr');
            let csv = 'ID,Name,Kannada Name,Category,Price,Stock,Featured\n';
            rows.forEach(row => {
                if (row.style.display === 'none') return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 10) return;
                
                const id = cells[1].textContent.trim().replace(/"/g, '""');
                const name = cells[3].textContent.trim().replace(/"/g, '""');
                const kn = cells[4].textContent.trim().replace(/"/g, '""');
                const category = cells[5].textContent.trim().replace(/"/g, '""');
                const price = cells[6].textContent.trim().replace(/"/g, '""').replace('₹', '');
                const stock = cells[7].textContent.trim().replace(/"/g, '""');
                const featured = cells[8].textContent.trim().replace(/"/g, '""');
                
                csv += `"${id}","${name}","${kn}","${category}","${price}","${stock}","${featured}"\n`;
            });
            const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'products.csv';
            a.click();
        });

        // ===== SELECT ALL / BULK DELETE =====
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('bulkDeleteBtn').addEventListener('click', function(e) {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) {
                alert('No products selected.');
                return;
            }
            if (confirm('Delete selected products? This will fail for products linked to order items.')) {
                document.getElementById('bulkActionField').value = '1';
                document.getElementById('bulkForm').submit();
            }
        });

        // ===== AUTO-HIDE TOAST =====
        setTimeout(() => {
            const toast = document.getElementById('toastMsg');
            if (toast) toast.style.opacity = '0';
        }, 4000);
    </script>
</body>
</html>