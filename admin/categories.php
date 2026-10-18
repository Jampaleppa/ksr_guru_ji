<?php
// ============================================================
// admin/categories.php - Standardized Category Management
// Fully integrated with PDO and actual database schema
// ============================================================
$currentPage = 'categories';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// --- HELPER: Generate slug/ID ---
function createSlug(string $string): string
{
    $string = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '-', $string)));
    return preg_replace('/-+/', '-', $string);
}

// --- HANDLE ACTIONS ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

// Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $action_type = $_POST['action_type'] ?? 'add';
    $name = trim($_POST['name'] ?? '');
    $kn_name = trim($_POST['kn_name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-box');

    if (empty($name)) {
        $error = 'Category name is required.';
    }

    if (empty($error)) {
        if ($action_type === 'add') {
            $id = !empty($_POST['id']) ? createSlug($_POST['id']) : createSlug($name);

            // Check if ID already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "A category with ID/Slug '$id' already exists. Please choose a different slug.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (id, name, kn_name, icon) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $name, $kn_name, $icon]);
                    $message = 'Category added successfully.';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } else {
            // Edit
            $id = trim($_POST['id'] ?? '');
            if (empty($id)) {
                $error = 'Category ID is missing.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET name=?, kn_name=?, icon=? WHERE id=?");
                    $stmt->execute([$name, $kn_name, $icon, $id]);
                    $message = 'Category updated successfully.';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    // Redirect to prevent form resubmission
    header('Location: categories.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Delete Category
if ($action === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Check for products using this category
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $product_count = $stmt->fetchColumn();

    if ($product_count > 0) {
        $error = "Cannot delete: This category is linked to $product_count products. Please reassign or delete the products first.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Category deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: categories.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Bulk Delete
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $canDelete = true;
    $totalProductCount = 0;

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT category_id, COUNT(*) as count FROM products WHERE category_id IN ($placeholders) GROUP BY category_id");
        $stmt->execute($ids);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($ids as $id) {
            $prodCount = $counts[$id] ?? 0;
            if ($prodCount > 0) {
                $canDelete = false;
                $totalProductCount += $prodCount;
            }
        }
    }

    if (!$canDelete) {
        $error = "Cannot delete: One or more selected categories are linked to products ($totalProductCount in total).";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = count($ids) . ' categories deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: categories.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// --- FETCH DATA ---
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Stats calculations
$total = count($categories);
$usedCount = $pdo->query("SELECT COUNT(DISTINCT category_id) FROM products")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$translatedCount = count(array_filter($categories, fn($c) => !empty($c['kn_name'])));

if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Categories - KSR GURU Admin</title>
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

        .nav-item i {
            width: 22px;
            text-align: center;
        }

        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            border-left-color: var(--accent);
            color: white;
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

        .header-btn:hover {
            transform: scale(1.05);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }

        .stat-info h3 {
            font-size: 1.6rem;
            font-weight: 800;
        }

        .stat-info p {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.categories {
            background: #f3e8ff;
            color: #a855f7;
        }

        .stat-icon.active {
            background: #dcfce7;
            color: #10b981;
        }

        .stat-icon.inactive {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-icon.root {
            background: #dbeafe;
            color: #3b82f6;
        }

        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
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

        .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
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

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: var(--main-bg);
        }

        .btn-sm {
            padding: 4px 12px;
            font-size: 0.75rem;
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
            animation: modalIn 0.25s ease;
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

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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

        .toast-msg {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: var(--card-bg);
            border-radius: var(--radius-md);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            transition: opacity 0.3s;
        }

        .toast-msg.success {
            border-left: 4px solid var(--success);
            color: #065f46;
            background: #ecfdf5;
        }

        .toast-msg.error {
            border-left: 4px solid var(--danger);
            color: #991b1b;
            background: #fef2f2;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>

<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'categories';
    include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-tags"></i> Category Management</h1>
                <div class="breadcrumb"><span>Admin</span> <i class="fas fa-chevron-right"></i> <span class="active">Categories</span></div>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search categories..." id="searchInput">
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
                    <h3><?= $total ?></h3>
                    <p>Total Categories</p>
                </div>
                <div class="stat-icon categories"><i class="fas fa-tags"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $usedCount ?></h3>
                    <p>Used in Products</p>
                </div>
                <div class="stat-icon active"><i class="fas fa-link"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $translatedCount ?></h3>
                    <p>Kannada Translated</p>
                </div>
                <div class="stat-icon root"><i class="fas fa-language"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $totalProducts ?></h3>
                    <p>Total Products</p>
                </div>
                <div class="stat-icon inactive"><i class="fas fa-boxes"></i></div>
            </div>
        </section>

        <!-- Main Categories Card -->
        <div class="table-container">
            <div class="table-header">
                <h3>All Categories</h3>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-primary" id="addCategoryBtn"><i class="fas fa-plus"></i> Add Category</button>
                    <button class="btn btn-outline" id="exportBtn"><i class="fas fa-file-export"></i> Export CSV</button>
                    <button class="btn btn-outline" id="bulkDeleteBtn" style="color:var(--danger); border-color:rgba(239,68,68,0.2);"><i class="fas fa-trash"></i> Bulk Delete</button>
                </div>
            </div>
            <form method="POST" id="bulkForm">
                <table class="data-table" id="categoryTable">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll"></th>
                            <th>ID / Slug</th>
                            <th>English Name</th>
                            <th>Kannada Name</th>
                            <th>Icon</th>
                            <th>Date Created</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($cat['id']) ?>" class="row-checkbox"></td>
                                <td><code><?= htmlspecialchars($cat['id']) ?></code></td>
                                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['kn_name']) ?></td>
                                <td>
                                    <div class="icon-circle">
                                        <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
                                    </div>
                                    <span style="font-size:0.8rem; font-family:monospace; margin-left:6px;"><?= htmlspecialchars($cat['icon']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($cat['created_at'] ?? '2026-08-09') ?></td>
                                <td>
                                    <button type="button" class="view-btn edit-btn"
                                        data-id="<?= htmlspecialchars($cat['id']) ?>"
                                        data-name="<?= htmlspecialchars($cat['name']) ?>"
                                        data-kn_name="<?= htmlspecialchars($cat['kn_name']) ?>"
                                        data-icon="<?= htmlspecialchars($cat['icon']) ?>"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="view-btn delete-btn" data-id="<?= htmlspecialchars($cat['id']) ?>" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="7" class="text-center" style="padding:30px;color:var(--text-secondary); text-align:center;">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <input type="hidden" name="bulk_action" value="1" id="bulkActionField">
            </form>
        </div>
    </main>

    <!-- ===== MODAL: Add/Edit Category ===== -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Add Category</h3>
                <button class="modal-close" onclick="closeModal('categoryModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="categoryForm">
                <input type="hidden" name="action_type" id="actionType" value="add">
                <div class="modal-body">
                    <div class="form-group" id="idInputGroup">
                        <label>Category ID / Slug</label>
                        <input type="text" name="id" id="categoryId" placeholder="e.g. fresh-vegetables">
                        <small style="color:var(--text-secondary);font-size:0.7rem;">Unique identifier used in URLs. Leave blank to auto-generate from name.</small>
                    </div>
                    <div class="form-group" id="idDisplayGroup" style="display:none;">
                        <label>Category ID / Slug (Read-only)</label>
                        <input type="text" id="categoryIdDisplay" readonly style="background:#e2e8f0; cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Category English Name</label>
                        <input type="text" name="name" id="categoryName" required placeholder="e.g. Fresh Vegetables">
                    </div>
                    <div class="form-group">
                        <label>Category Kannada Name</label>
                        <input type="text" name="kn_name" id="categoryKnName" placeholder="e.g. ತಾಜಾ ತರಕಾರಿಗಳು">
                    </div>
                    <div class="form-group">
                        <label>FontAwesome Icon Class</label>
                        <input type="text" name="icon" id="categoryIcon" value="fa-box" placeholder="e.g. fa-carrot">
                        <small style="color:var(--text-secondary);font-size:0.7rem;">Enter FontAwesome icon class (e.g. <code>fa-carrot</code>, <code>fa-seedling</code>, <code>fa-bath</code>).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" name="save_category" class="btn btn-primary">Save Category</button>
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
                <p>Are you sure you want to delete this category?</p>
                <p style="color:var(--danger);font-size:0.85rem;margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone, and will fail if the category has linked products.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <script>
        // ===== MODAL HELPERS =====
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // ===== ADD CATEGORY =====
        document.getElementById('addCategoryBtn').addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('actionType').value = 'add';
            document.getElementById('categoryId').value = '';

            document.getElementById('idInputGroup').style.display = 'block';
            document.getElementById('idDisplayGroup').style.display = 'none';

            document.getElementById('categoryName').value = '';
            document.getElementById('categoryKnName').value = '';
            document.getElementById('categoryIcon').value = 'fa-box';
            openModal('categoryModal');
        });

        // ===== EDIT CATEGORY =====
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('modalTitle').textContent = 'Edit Category';
                document.getElementById('actionType').value = 'edit';

                const id = this.dataset.id;
                document.getElementById('idInputGroup').style.display = 'none';
                document.getElementById('idDisplayGroup').style.display = 'block';
                document.getElementById('categoryIdDisplay').value = id;

                // Set hidden field value
                let hiddenIdInput = document.getElementById('categoryForm').querySelector('input[name="id"]');
                if (!hiddenIdInput) {
                    hiddenIdInput = document.createElement('input');
                    hiddenIdInput.type = 'hidden';
                    hiddenIdInput.name = 'id';
                    document.getElementById('categoryForm').appendChild(hiddenIdInput);
                }
                hiddenIdInput.value = id;

                document.getElementById('categoryName').value = this.dataset.name;
                document.getElementById('categoryKnName').value = this.dataset.kn_name;
                document.getElementById('categoryIcon').value = this.dataset.icon;
                openModal('categoryModal');
            });
        });

        // ===== DELETE CATEGORY =====
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('confirmDeleteBtn').href = 'categories.php?action=delete&id=' + encodeURIComponent(id);
                openModal('deleteModal');
            });
        });

        // ===== SEARCH =====
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#categoryTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // ===== EXPORT CSV =====
        document.getElementById('exportBtn').addEventListener('click', function() {
            const rows = document.querySelectorAll('#categoryTable tbody tr');
            let csv = 'ID,Name,Kannada Name,Icon,Created At\n';
            rows.forEach(row => {
                if (row.style.display === 'none') return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;

                const id = cells[1].textContent.trim().replace(/"/g, '""');
                const name = cells[2].textContent.trim().replace(/"/g, '""');
                const kn = cells[3].textContent.trim().replace(/"/g, '""');
                const icon = cells[4].querySelector('span').textContent.trim().replace(/"/g, '""');
                const created = cells[5].textContent.trim().replace(/"/g, '""');

                csv += `"${id}","${name}","${kn}","${icon}","${created}"\n`;
            });
            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'categories.csv';
            a.click();
        });

        // ===== SELECT ALL / BULK DELETE =====
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('bulkDeleteBtn').addEventListener('click', function(e) {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) {
                alert('No categories selected.');
                return;
            }
            if (confirm('Delete selected categories? This will fail for categories linked to products.')) {
                document.getElementById('bulkActionField').value = '1';
                document.getElementById('bulkForm').submit();
            }
        });

        // ===== AUTO-GENERATE SLUG =====
        document.getElementById('categoryName').addEventListener('input', function() {
            if (document.getElementById('actionType').value === 'add') {
                const slugInput = document.getElementById('categoryId');
                slugInput.value = this.value.toLowerCase().replace(/[^a-zA-Z0-9]/g, '-').replace(/-+/g, '-');
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