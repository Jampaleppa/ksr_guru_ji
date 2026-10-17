<?php
// ============================================================
// admin/management.php - Unified Management (Banners + Coupons + Inventory)
// ============================================================
$currentPage = 'management';
require_once 'inc/auth_check.php';
require_once '../config.php'; // added config for DB connection
require_once 'inc/functions.php';

// Initialize database connection
$pdo = getDBConnection();

// --- GENERATE COUPON CODE ---
function generateCouponCode(int $length = 8): string
{
    return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length));
}

// --- HELPER: Get table name ---
function getTableName(string $type): string
{
    return ($type === 'slider') ? 'sliders' : 'banners';
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'banners';

// ============================================================
// BANNER / SLIDER ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $button_text = trim($_POST['button_text'] ?? 'Shop Now');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $type = $_POST['banner_type'] ?? 'banner';

    $image_url = '';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = '../uploads/banners/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/banners/' . $filename;
        } else {
            $error = 'Failed to upload image.';
        }
    }

    if (empty($error)) {
        try {
            $table = getTableName($type);
            if ($id > 0) {
                $sql = "UPDATE $table SET title=?, subtitle=?, link=?, button_text=?, sort_order=?, status=?";
                $params = [$title, $subtitle, $link, $button_text, $sort_order, $status];
                if ($image_url) {
                    $sql .= ", image_url=?";
                    $params[] = $image_url;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = ucfirst($type) . ' updated successfully.';
            } else {
                if (empty($image_url)) {
                    $error = 'Image is required.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO $table (title, subtitle, image_url, link, button_text, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $subtitle, $image_url, $link, $button_text, $sort_order, $status]);
                    $message = ucfirst($type) . ' added successfully.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: management.php?tab=' . $type . '&msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Delete Banner/Slider
if (isset($_GET['action']) && $_GET['action'] === 'delete_banner' && isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    $table = getTableName($type);
    try {
        $stmt = $pdo->prepare("SELECT image_url FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && $row['image_url']) {
            $filePath = '../' . $row['image_url'];
            if (file_exists($filePath)) unlink($filePath);
        }
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $message = ucfirst($type) . ' deleted.';
    } catch (PDOException $e) {
        $error = 'Cannot delete: ' . $e->getMessage();
    }
    header('Location: management.php?tab=' . $type . '&msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Toggle Banner/Slider Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_banner' && isset($_GET['id']) && isset($_GET['type'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    $table = getTableName($type);
    $pdo->prepare("UPDATE $table SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    header('Location: management.php?tab=' . $type);
    exit;
}

// ============================================================
// COUPON ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $id = (int)($_POST['id'] ?? 0);
    $code = !empty($_POST['code']) ? strtoupper(trim($_POST['code'])) : generateCouponCode();
    $description = trim($_POST['description'] ?? '');
    $discount_type = $_POST['discount_type'] ?? 'percent';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_order_amount = (float)($_POST['min_order_amount'] ?? 0);
    $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
    $usage_limit = (int)($_POST['usage_limit'] ?? 1);
    $user_limit = (int)($_POST['user_limit'] ?? 1);
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'active';

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coupons SET code=?, description=?, discount_type=?, discount_value=?, min_order_amount=?, max_discount=?, usage_limit=?, user_limit=?, start_date=?, end_date=?, status=? WHERE id=?");
            $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_amount, $max_discount, $usage_limit, $user_limit, $start_date, $end_date, $status, $id]);
            $message = 'Coupon updated successfully.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_discount, usage_limit, user_limit, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_amount, $max_discount, $usage_limit, $user_limit, $start_date, $end_date, $status]);
            $message = 'Coupon created successfully. Code: ' . $code;
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
    header('Location: management.php?tab=coupons&msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Delete Coupon
if (isset($_GET['action']) && $_GET['action'] === 'delete_coupon' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Coupon deleted.';
    } catch (PDOException $e) {
        $error = 'Cannot delete: ' . $e->getMessage();
    }
    header('Location: management.php?tab=coupons&msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Toggle Coupon Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_coupon' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE coupons SET status = IF(status='active','inactive','active') WHERE id = ?")->execute([$id]);
    header('Location: management.php?tab=coupons');
    exit;
}

// Generate Coupon Code (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'generate_code') {
    header('Content-Type: application/json');
    echo json_encode(['code' => generateCouponCode()]);
    exit;
}

// ============================================================
// INVENTORY ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $id = (int)$_POST['id'];
    $quantity = (int)$_POST['quantity'];
    $change_type = $_POST['change_type'] ?? 'adjustment';
    $note = trim($_POST['note'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $oldQty = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $id]);

        $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, old_quantity, new_quantity, change_type, note, created_by) SELECT product_id, ?, ?, ?, ?, ? FROM inventory WHERE id = ?");
        $stmt->execute([$oldQty, $quantity, $change_type, $note, $_SESSION['admin_id'] ?? null, $id]);

        $pdo->commit();
        $message = 'Stock updated successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
    header('Location: management.php?tab=inventory&msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// ============================================================
// FETCH DATA
// ============================================================

// Banners & Sliders
$bannerType = $_GET['subtab'] ?? 'banner';
$bannerTable = getTableName($bannerType);
$banners = $pdo->query("SELECT * FROM $bannerTable ORDER BY sort_order ASC, id DESC")->fetchAll();

// Coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
$totalCoupons = count($coupons);
$activeCoupons = count(array_filter($coupons, fn($c) => $c['status'] === 'active'));
$expiredCoupons = count(array_filter($coupons, fn($c) => $c['end_date'] && strtotime($c['end_date']) < time()));

// Inventory
$inventory = $pdo->query("SELECT i.*, p.name, p.sku, p.price, p.status as product_status, p.id as product_id FROM inventory i JOIN products p ON i.product_id = p.id ORDER BY i.quantity ASC, p.name ASC")->fetchAll();
$totalItems = count($inventory);
$lowStock = count(array_filter($inventory, fn($i) => $i['quantity'] > 0 && $i['quantity'] < 5));
$outOfStock = count(array_filter($inventory, fn($i) => $i['quantity'] == 0));
$totalStock = array_sum(array_column($inventory, 'quantity'));

// Preserve messages from redirects
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Unified styling – same as other admin pages */
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
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: all .3s ease;
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

        .nav-section h4 {
            padding: 0 20px;
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
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

        .nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            border-left-color: var(--accent);
            color: white;
        }

        .nav-item:hover:not(.active) {
            background: rgba(55, 65, 81, 0.5);
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

        .main-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 6px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
            overflow-x: auto;
        }

        .main-tabs .tab {
            padding: 10px 24px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all .2s;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .main-tabs .tab.active {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .sub-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .sub-tabs .sub-tab {
            padding: 6px 16px;
            border-radius: 40px;
            border: 1px solid var(--border-light);
            background: var(--card-bg);
            cursor: pointer;
            font-weight: 500;
            font-size: 0.8rem;
            transition: 0.2s;
            text-decoration: none;
            color: var(--text-primary);
        }

        .sub-tabs .sub-tab.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }

        .stat-card .stat-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 2px 0;
        }

        .stat-card .stat-icon {
            float: right;
            font-size: 1.3rem;
            opacity: 0.6;
        }

        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table th,
        .data-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table th {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            text-decoration: none;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #15803d;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-badge.expired {
            background: #fef3c7;
            color: #92400e;
        }

        .code-badge {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .banner-preview {
            width: 80px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }

        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .stock-indicator.green {
            background: var(--success);
        }

        .stock-indicator.yellow {
            background: var(--warning);
        }

        .stock-indicator.red {
            background: var(--danger);
        }

        .btn {
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all .2s;
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: var(--main-bg);
        }

        .view-btn {
            background: transparent;
            border: none;
            color: var(--accent);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: 0.2s;
        }

        .view-btn:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 560px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalIn .25s ease;
        }

        @keyframes modalIn {
            from {
                transform: scale(0.95) translateY(20px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            transition: 0.2s;
        }

        .modal-close:hover {
            background: var(--main-bg);
        }

        .modal-body {
            padding: 20px 22px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 4px;
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
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .generate-btn {
            cursor: pointer;
            background: var(--main-bg);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 8px 12px;
            transition: 0.2s;
        }

        .generate-btn:hover {
            background: var(--accent);
            color: white;
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
            animation: toastIn .3s ease;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s;
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

        .toast.info {
            background: var(--accent);
        }

        @keyframes toastIn {
            from {
                transform: translateX(-50%) translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        .toast show toast-inline {
            position: static;
            margin-bottom: 20px;
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
        }

        .filter-bar select,
        .filter-bar input {
            padding: 6px 12px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            font-size: 0.85rem;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            border-radius: 40px;
            padding: 6px 16px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow);
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 0.85rem;
            min-width: 180px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        @media(max-width:768px) {
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

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .main-tabs {
                flex-wrap: nowrap;
            }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>

<body>
    <!-- Sidebar -->
    <?php $currentPage = 'management';
    include 'inc/sidebar.php'; ?>
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-layer-group"></i> Management</h1>
                <div class="breadcrumb">Admin / Management</div>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Admin</span></div>
                </div>
            </div>
        </header>
        <?php if ($message): ?><div class="toast show toast-inline success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="toast show toast-inline error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="main-tabs">
            <button class="tab <?= $activeTab === 'banners' ? 'active' : '' ?>" data-tab="banners"><i class="fas fa-image"></i> Banners & Sliders</button>
            <button class="tab <?= $activeTab === 'coupons' ? 'active' : '' ?>" data-tab="coupons"><i class="fas fa-gift"></i> Coupons</button>
            <button class="tab <?= $activeTab === 'inventory' ? 'active' : '' ?>" data-tab="inventory"><i class="fas fa-warehouse"></i> Inventory</button>
        </div>
        <!-- BANNERS TAB -->
        <div class="tab-content <?= $activeTab === 'banners' ? 'active' : '' ?>" id="tab-banners">
            <div class="sub-tabs">
                <a href="management.php?tab=banners&subtab=banner" class="sub-tab <?= $bannerType === 'banner' ? 'active' : '' ?>"><i class="fas fa-image"></i> Banners</a>
                <a href="management.php?tab=banners&subtab=slider" class="sub-tab <?= $bannerType === 'slider' ? 'active' : '' ?>"><i class="fas fa-sliders-h"></i> Sliders</a>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total <?= ucfirst($bannerType) ?>s</div>
                    <div class="stat-value"><?= count($banners) ?></div>
                    <div class="stat-icon"><i class="fas fa-image" style="color:var(--accent);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active</div>
                    <div class="stat-value" style="color:var(--success);"><?= count(array_filter($banners, fn($b) => $b['status'] === 'active')) ?></div>
                    <div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Inactive</div>
                    <div class="stat-value" style="color:var(--danger);"><?= count(array_filter($banners, fn($b) => $b['status'] === 'inactive')) ?></div>
                    <div class="stat-icon"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
                </div>
            </div>
            <div class="table-container">
                <div class="table-header">
                    <h3><?= ucfirst($bannerType) ?>s</h3><button class="btn btn-primary" id="addBannerBtn"><i class="fas fa-plus"></i> Add <?= ucfirst($bannerType) ?></button>
                </div>
                <table class="data-table" id="bannerTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Link</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $item): ?>
                            <tr>
                                <td><img src="../<?= htmlspecialchars($item['image_url'] ?: $item['image']) ?>" class="banner-preview" alt="Banner"></td>
                                <td><strong><?= htmlspecialchars($item['title']) ?></strong><br><small><?= htmlspecialchars($item['subtitle']) ?></small></td>
                                <td><a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['link']) ?></a></td>
                                <td><?= $item['sort_order'] ?></td>
                                <td><a href="management.php?action=toggle_banner&id=<?= $item['id'] ?>&type=<?= $bannerType ?>&tab=banners" class="status-badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></a></td>
                                <td>
                                    <button class="view-btn edit-banner" data-id="<?= $item['id'] ?>" data-title="<?= htmlspecialchars($item['title']) ?>" data-subtitle="<?= htmlspecialchars($item['subtitle']) ?>" data-link="<?= htmlspecialchars($item['link']) ?>" data-button="<?= htmlspecialchars($item['button_text']) ?>" data-order="<?= $item['sort_order'] ?>" data-status="<?= $item['status'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="view-btn delete-banner" data-id="<?= $item['id'] ?>" data-type="<?= $bannerType ?>" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($banners)): ?><tr>
                                <td colspan="6" class="text-center" style="padding:30px;color:var(--text-secondary);">No <?= $bannerType ?>s found.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- COUPONS TAB -->
        <div class="tab-content <?= $activeTab === 'coupons' ? 'active' : '' ?>" id="tab-coupons">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Coupons</div>
                    <div class="stat-value"><?= $totalCoupons ?></div>
                    <div class="stat-icon"><i class="fas fa-ticket-alt" style="color:var(--accent);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active</div>
                    <div class="stat-value" style="color:var(--success);"><?= $activeCoupons ?></div>
                    <div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Expired</div>
                    <div class="stat-value" style="color:var(--danger);"><?= $expiredCoupons ?></div>
                    <div class="stat-icon"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
                </div>
            </div>
            <div class="table-container">
                <div class="table-header">
                    <h3>All Coupons</h3><button class="btn btn-primary" id="addCouponBtn"><i class="fas fa-plus"></i> Add Coupon</button>
                </div>
                <table class="data-table" id="couponTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Order</th>
                            <th>Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $c): $isExpired = $c['end_date'] && strtotime($c['end_date']) < time();
                            $statusClass = $isExpired ? 'expired' : $c['status']; ?>
                            <tr>
                                <td><span class="code-badge"><?= htmlspecialchars($c['code']) ?></span></td>
                                <td><?= htmlspecialchars($c['description']) ?></td>
                                <td><?= $c['discount_type'] === 'percent' ? '%' : 'Fixed' ?></td>
                                <td><?= $c['discount_type'] === 'percent' ? $c['discount_value'] . '%' : '₹' . number_format($c['discount_value'], 2) ?></td>
                                <td><?= $c['min_order_amount'] ? '₹' . number_format($c['min_order_amount'], 2) : '—' ?></td>
                                <td><?= $c['used_count'] ?>/<?= $c['usage_limit'] ?></td>
                                <td><a href="management.php?action=toggle_coupon&id=<?= $c['id'] ?>&tab=coupons" class="status-badge <?= $statusClass ?>"><?= ucfirst($statusClass) ?></a></td>
                                <td>
                                    <button class="view-btn edit-coupon" data-id="<?= $c['id'] ?>" data-code="<?= htmlspecialchars($c['code']) ?>" data-desc="<?= htmlspecialchars($c['description']) ?>" data-type="<?= $c['discount_type'] ?>" data-value="<?= $c['discount_value'] ?>" data-min="<?= $c['min_order_amount'] ?>" data-max="<?= $c['max_discount'] ?>" data-usage="<?= $c['usage_limit'] ?>" data-user="<?= $c['user_limit'] ?>" data-start="<?= $c['start_date'] ?>" data-end="<?= $c['end_date'] ?>" data-status="<?= $c['status'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="view-btn delete-coupon" data-id="<?= $c['id'] ?>" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($coupons)): ?><tr>
                                <td colspan="8" class="text-center" style="padding:30px;color:var(--text-secondary);">No coupons found.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- INVENTORY TAB -->
        <div class="tab-content <?= $activeTab === 'inventory' ? 'active' : '' ?>" id="tab-inventory">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?= $totalItems ?></div>
                    <div class="stat-icon"><i class="fas fa-boxes" style="color:var(--accent);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Stock</div>
                    <div class="stat-value"><?= number_format($totalStock) ?></div>
                    <div class="stat-icon"><i class="fas fa-cubes" style="color:var(--accent);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value" style="color:var(--warning);"><?= $lowStock ?></div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color:var(--warning);"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value" style="color:var(--danger);"><?= $outOfStock ?></div>
                    <div class="stat-icon"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
                </div>
            </div>
            <div class="filter-bar">
                <select id="stockFilter">
                    <option value="">All Stock Status</option>
                    <option value="in-stock">In Stock (&gt;10)</option>
                    <option value="low-stock">Low Stock (1-10)</option>
                    <option value="out-of-stock">Out of Stock (0)</option>
                </select>
                <button class="btn btn-sm btn-primary" id="applyStockFilter"><i class="fas fa-filter"></i> Filter</button>
                <button class="btn btn-sm btn-outline" id="clearStockFilter">Clear</button>
                <div class="search-box" style="margin-left:auto;"><i class="fas fa-search"></i><input type="text" placeholder="Search products..." id="stockSearch"></div>
                <button class="btn btn-sm btn-success" id="exportStockBtn"><i class="fas fa-download"></i> Export</button>
            </div>
            <div class="table-container">
                <div class="table-header">
                    <h3>Stock Management</h3>
                </div>
                <table class="data-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $i): $stockStatus = $i['quantity'] <= 0 ? 'out-of-stock' : ($i['quantity'] < 5 ? 'low-stock' : 'in-stock'); ?>
                            <tr data-stock="<?= $stockStatus ?>">
                                <td><strong><?= htmlspecialchars($i['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($i['sku']) ?></code></td>
                                <td>₹<?= number_format($i['price'], 2) ?></td>
                                <td><span class="stock-indicator <?= $stockStatus === 'in-stock' ? 'green' : ($stockStatus === 'low-stock' ? 'yellow' : 'red') ?>"></span><?= $i['quantity'] ?></td>
                                <td><span class="status-badge <?= $stockStatus ?>"><?= ucfirst(str_replace('-', ' ', $stockStatus)) ?></span></td>
                                <td><button class="view-btn edit-stock" data-id="<?= $i['id'] ?>" data-product="<?= htmlspecialchars($i['name']) ?>" data-qty="<?= $i['quantity'] ?>"><i class="fas fa-edit"></i> Update</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($inventory)): ?><tr>
                                <td colspan="6" class="text-center" style="padding:30px;color:var(--text-secondary);">No inventory records found.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <!-- Modals -->
    <div class="modal-overlay" id="bannerModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="bannerModalTitle">Add <?= ucfirst($bannerType) ?></h3><button class="modal-close" onclick="closeModal('bannerModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="management.php"><input type="hidden" name="id" id="bannerId"><input type="hidden" name="banner_type" value="<?= $bannerType ?>">
                <div class="modal-body">
                    <div class="form-group"><label>Title *</label><input type="text" name="title" id="bannerTitle" required></div>
                    <div class="form-group"><label>Subtitle</label><input type="text" name="subtitle" id="bannerSubtitle"></div>
                    <div class="form-group"><label>Link URL</label><input type="url" name="link" id="bannerLink" placeholder="https://example.com"></div>
                    <div class="form-group"><label>Button Text</label><input type="text" name="button_text" id="bannerButton" value="Shop Now"></div>
                    <div class="form-row">
                        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="bannerOrder" value="0"></div>
                        <div class="form-group"><label>Status</label><select name="status" id="bannerStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label>Image <?= $bannerType === 'banner' ? '(Recommended: 1200x400px)' : '(Recommended: 1920x600px)' ?></label><input type="file" name="image" accept="image/*" id="bannerImage"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('bannerModal')">Cancel</button><button type="submit" name="save_banner" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
    <div class="modal-overlay" id="couponModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="couponModalTitle">Add Coupon</h3><button class="modal-close" onclick="closeModal('couponModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="management.php"><input type="hidden" name="id" id="couponId">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group"><label>Coupon Code</label>
                            <div style="display:flex;gap:8px;"><input type="text" name="code" id="couponCode" style="flex:1;text-transform:uppercase;font-family:monospace;letter-spacing:1px;"><button type="button" class="generate-btn" id="generateCode"><i class="fas fa-random"></i></button></div>
                        </div>
                        <div class="form-group"><label>Status</label><select name="status" id="couponStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label>Description</label><input type="text" name="description" id="couponDesc" placeholder="e.g., 20% off on all orders"></div>
                    <div class="form-row">
                        <div class="form-group"><label>Discount Type</label><select name="discount_type" id="couponType">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select></div>
                        <div class="form-group"><label>Discount Value *</label><input type="number" step="0.01" name="discount_value" id="couponValue" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Min Order Amount</label><input type="number" step="0.01" name="min_order_amount" id="couponMin" placeholder="0"></div>
                        <div class="form-group"><label>Max Discount</label><input type="number" step="0.01" name="max_discount" id="couponMax" placeholder="Leave empty for no limit"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Usage Limit (Total)</label><input type="number" name="usage_limit" id="couponUsage" value="1"></div>
                        <div class="form-group"><label>Usage Limit (Per User)</label><input type="number" name="user_limit" id="couponUser" value="1"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Start Date</label><input type="datetime-local" name="start_date" id="couponStart"></div>
                        <div class="form-group"><label>End Date</label><input type="datetime-local" name="end_date" id="couponEnd"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('couponModal')">Cancel</button><button type="submit" name="save_coupon" class="btn btn-primary">Save Coupon</button></div>
            </form>
        </div>
    </div>
    <div class="modal-overlay" id="stockModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Update Stock</h3><button class="modal-close" onclick="closeModal('stockModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="management.php"><input type="hidden" name="id" id="stockId">
                <div class="modal-body">
                    <div class="form-group"><label>Product</label><input type="text" id="productName" disabled style="background:#e2e8f0;cursor:not-allowed;"></div>
                    <div class="form-group"><label>New Quantity *</label><input type="number" name="quantity" id="stockQty" required min="0"></div>
                    <div class="form-group"><label>Change Type</label><select name="change_type" id="stockChangeType">
                            <option value="adjustment">Adjustment</option>
                            <option value="purchase">Purchase</option>
                            <option value="sale">Sale</option>
                            <option value="return">Return</option>
                            <option value="restock">Restock</option>
                        </select></div>
                    <div class="form-group"><label>Note (Optional)</label><input type="text" name="note" placeholder="Why is this stock being updated?"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('stockModal')">Cancel</button><button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button></div>
            </form>
        </div>
    </div>
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this item?</p>
            </div>
            <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button><a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a></div>
        </div>
    </div>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function setDeleteLink(url) {
            document.getElementById('confirmDeleteBtn').href = url;
        }
        document.querySelectorAll('.main-tabs .tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.pushState({}, '', url);
                document.querySelectorAll('.main-tabs .tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById('tab-' + tabName).classList.add('active');
            });
        });
        // Banner
        document.getElementById('addBannerBtn').addEventListener('click', () => {
            document.getElementById('bannerModalTitle').textContent = 'Add <?= ucfirst($bannerType) ?>';
            document.getElementById('bannerId').value = '';
            document.getElementById('bannerTitle').value = '';
            document.getElementById('bannerSubtitle').value = '';
            document.getElementById('bannerLink').value = '';
            document.getElementById('bannerButton').value = 'Shop Now';
            document.getElementById('bannerOrder').value = '0';
            document.getElementById('bannerStatus').value = 'active';
            document.getElementById('bannerImage').value = '';
            openModal('bannerModal');
        });
        document.querySelectorAll('.edit-banner').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('bannerModalTitle').textContent = 'Edit <?= ucfirst($bannerType) ?>';
                document.getElementById('bannerId').value = this.dataset.id;
                document.getElementById('bannerTitle').value = this.dataset.title;
                document.getElementById('bannerSubtitle').value = this.dataset.subtitle;
                document.getElementById('bannerLink').value = this.dataset.link;
                document.getElementById('bannerButton').value = this.dataset.button || 'Shop Now';
                document.getElementById('bannerOrder').value = this.dataset.order || 0;
                document.getElementById('bannerStatus').value = this.dataset.status;
                document.getElementById('bannerImage').value = '';
                openModal('bannerModal');
            });
        });
        document.querySelectorAll('.delete-banner').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = 'management.php?action=delete_banner&id=' + this.dataset.id + '&type=' + this.dataset.type + '&tab=banners';
                document.getElementById('deleteMessage').textContent = 'Delete this ' + this.dataset.type + '?';
                setDeleteLink(url);
                openModal('deleteModal');
            });
        });
        // Coupon
        document.getElementById('addCouponBtn').addEventListener('click', () => {
            document.getElementById('couponModalTitle').textContent = 'Add Coupon';
            document.getElementById('couponId').value = '';
            document.getElementById('couponCode').value = '';
            document.getElementById('couponDesc').value = '';
            document.getElementById('couponType').value = 'percent';
            document.getElementById('couponValue').value = '';
            document.getElementById('couponMin').value = '';
            document.getElementById('couponMax').value = '';
            document.getElementById('couponUsage').value = '1';
            document.getElementById('couponUser').value = '1';
            document.getElementById('couponStart').value = '';
            document.getElementById('couponEnd').value = '';
            document.getElementById('couponStatus').value = 'active';
            fetch('management.php?action=generate_code').then(r => r.json()).then(d => document.getElementById('couponCode').value = d.code);
            openModal('couponModal');
        });
        document.getElementById('generateCode').addEventListener('click', () => {
            fetch('management.php?action=generate_code').then(r => r.json()).then(d => document.getElementById('couponCode').value = d.code);
        });
        document.querySelectorAll('.edit-coupon').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
                document.getElementById('couponId').value = this.dataset.id;
                document.getElementById('couponCode').value = this.dataset.code;
                document.getElementById('couponDesc').value = this.dataset.desc;
                document.getElementById('couponType').value = this.dataset.type;
                document.getElementById('couponValue').value = this.dataset.value;
                document.getElementById('couponMin').value = this.dataset.min;
                document.getElementById('couponMax').value = this.dataset.max || '';
                document.getElementById('couponUsage').value = this.dataset.usage || 1;
                document.getElementById('couponUser').value = this.dataset.user || 1;
                document.getElementById('couponStart').value = this.dataset.start || '';
                document.getElementById('couponEnd').value = this.dataset.end || '';
                document.getElementById('couponStatus').value = this.dataset.status;
                openModal('couponModal');
            });
        });
        document.querySelectorAll('.delete-coupon').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = 'management.php?action=delete_coupon&id=' + this.dataset.id + '&tab=coupons';
                document.getElementById('deleteMessage').textContent = 'Delete this coupon?';
                setDeleteLink(url);
                openModal('deleteModal');
            });
        });
        // Inventory
        document.querySelectorAll('.edit-stock').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('stockId').value = this.dataset.id;
                document.getElementById('productName').value = this.dataset.product;
                document.getElementById('stockQty').value = this.dataset.qty;
                openModal('stockModal');
            });
        });
        document.getElementById('stockSearch').addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
            });
        });
        document.getElementById('applyStockFilter').addEventListener('click', function() {
            const filter = document.getElementById('stockFilter').value;
            document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
                if (!filter) {
                    row.style.display = '';
                    return;
                }
                row.style.display = row.dataset.stock === filter ? '' : 'none';
            });
        });
        document.getElementById('clearStockFilter').addEventListener('click', function() {
            document.getElementById('stockFilter').value = '';
            document.querySelectorAll('#inventoryTable tbody tr').forEach(row => row.style.display = '');
        });
        document.getElementById('exportStockBtn').addEventListener('click', function() {
            let csv = 'Product,SKU,Price,Stock,Status\n';
            document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
                if (row.style.display === 'none') return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 5) return;
                csv += cells[0].textContent + ',' + cells[1].textContent + ',' + cells[2].textContent.replace('₹', '') + ',' + cells[3].textContent.replace(/[^0-9]/g, '') + ',' + cells[4].textContent.trim() + '\n';
            });
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'inventory.csv';
            a.click();
        });
        setTimeout(() => {
            document.querySelectorAll('.toast-inline').forEach(toast => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>

</html>