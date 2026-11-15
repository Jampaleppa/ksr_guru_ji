<?php
// ============================================================
// admin/daily_prices.php - Daily Market Price & Selling Price Manager
// KSR GURU JI - Vegetables & Fruits Daily Rates
// ============================================================
$currentPage = 'daily_prices';
require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/../includes/market_prices.php';

$pdo = getDBConnection();
ensureMarketPriceTables($pdo);

// Handle AJAX History Request
if (isset($_GET['ajax_history'])) {
    header('Content-Type: application/json; charset=utf-8');
    $productId = (int)($_GET['ajax_history'] ?? 0);
    $history = getMarketPriceHistory($productId, 30);
    echo json_encode(['success' => true, 'history' => $history], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = '';
$error = '';
$adminUser = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Single Price Update
    if ($action === 'update_single') {
        $id = (int)($_POST['id'] ?? 0);
        $marketPrice = (float)($_POST['market_price'] ?? 0);
        $unitType = trim($_POST['unit_type'] ?? 'kg');
        $margin = isset($_POST['profit_margin']) ? (float)$_POST['profit_margin'] : KSR_DEFAULT_PRICE_MARGIN;
        $notes = trim($_POST['notes'] ?? 'Daily mandi update');

        if ($id > 0 && $marketPrice >= 0) {
            if (updateDailyMarketPrice($id, $marketPrice, $unitType, $margin, $notes, $adminUser)) {
                $message = 'Price updated successfully. Selling price calculated automatically (Market + ₹' . number_format($margin, 2) . ').';
            } else {
                $error = 'Failed to update price for the item.';
            }
        } else {
            $error = 'Invalid product ID or market price.';
        }
    }

    // 2. Bulk Price Update
    elseif ($action === 'bulk_update') {
        $prices = $_POST['market_prices'] ?? [];
        $units = $_POST['unit_types'] ?? [];
        $margins = $_POST['margins'] ?? [];
        $updatedCount = 0;

        if (is_array($prices) && !empty($prices)) {
            foreach ($prices as $id => $priceVal) {
                $id = (int)$id;
                $marketPrice = (float)$priceVal;
                $unit = $units[$id] ?? null;
                $margin = isset($margins[$id]) ? (float)$margins[$id] : KSR_DEFAULT_PRICE_MARGIN;

                if ($id > 0 && $marketPrice >= 0) {
                    if (updateDailyMarketPrice($id, $marketPrice, $unit, $margin, 'Bulk daily update', $adminUser)) {
                        $updatedCount++;
                    }
                }
            }
            $message = "Successfully updated {$updatedCount} products with latest market rates and selling prices (+₹3).";
        } else {
            $error = 'No price updates were submitted.';
        }
    }

    // 3. Quick Mandi Rates Import (CSV or text paste)
    elseif ($action === 'import_text') {
        $rawText = trim($_POST['import_data'] ?? '');
        if (!empty($rawText)) {
            $lines = preg_split('/[\r\n]+/', $rawText);
            $importedCount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;

                // Support: Name, MarketPrice OR Name, MarketPrice, Unit
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) >= 2) {
                    $name = $parts[0];
                    $price = (float)$parts[1];
                    $unit = $parts[2] ?? null;

                    // Match product by name
                    $stmt = $pdo->prepare("SELECT id FROM daily_market_prices WHERE name LIKE ? OR name_kn LIKE ? LIMIT 1");
                    $stmt->execute(["%{$name}%", "%{$name}%"]);
                    $foundId = $stmt->fetchColumn();

                    if ($foundId && $price >= 0) {
                        if (updateDailyMarketPrice((int)$foundId, $price, $unit, KSR_DEFAULT_PRICE_MARGIN, 'Mandi import', $adminUser)) {
                            $importedCount++;
                        }
                    }
                }
            }
            $message = "Import completed: {$importedCount} market rates updated successfully.";
        } else {
            $error = 'Please paste market rate lines in format: Product Name, Market Price';
        }
    }

    // 4. Add New Product to Daily Price Board
    elseif ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $nameKn = trim($_POST['name_kn'] ?? '');
        $category = trim($_POST['category'] ?? 'indianMain');
        $desc = trim($_POST['description'] ?? 'Fresh farm produce');
        $unitType = trim($_POST['unit_type'] ?? 'kg');
        $packetSizes = trim($_POST['packet_sizes'] ?? '100g,250g,500g,1kg');
        $marketPrice = (float)($_POST['market_price'] ?? 0);
        $margin = KSR_DEFAULT_PRICE_MARGIN;
        $sellingPrice = round($marketPrice + $margin, 2);
        $today = date('Y-m-d');

        if (!empty($name)) {
            $code = 'VEG_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
            $stmt = $pdo->prepare("INSERT INTO daily_market_prices 
                (product_code, name, name_kn, category, description, unit_type, packet_sizes, market_price, profit_margin, selling_price, price_date, is_available)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$code, $name, $nameKn, $category, $desc, $unitType, $packetSizes, $marketPrice, $margin, $sellingPrice, $today]);
            $newId = (int)$pdo->lastInsertId();

            // Record initial history
            $stmtHist = $pdo->prepare("INSERT INTO market_price_history 
                (product_id, product_name, unit_type, market_price, profit_margin, selling_price, effective_date, updated_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Initial listing')");
            $stmtHist->execute([$newId, $name, $unitType, $marketPrice, $margin, $sellingPrice, $today, $adminUser]);

            $message = "Product '{$name}' added with market price ₹{$marketPrice} and selling price ₹{$sellingPrice} (+₹3).";
        } else {
            $error = 'Product name is required.';
        }
    }
}

// Fetch all market items
$selectedCat = $_GET['cat'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');
$items = getDailyMarketPrices($selectedCat, $searchQuery);

// Compute statistics
$today = date('Y-m-d');
$totalItems = count($items);
$updatedTodayCount = 0;
foreach ($items as $it) {
    if ($it['price_date'] === $today) {
        $updatedTodayCount++;
    }
}
$pendingUpdateCount = $totalItems - $updatedTodayCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Daily Market & Selling Prices - KSR GURU JI Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin_unified.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #d1fae5;
            --accent: #3b82f6;
            --dark: #0f172a;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }
        .main-content {
            margin-left: 280px;
            padding: 24px;
            background: #f8fafc;
            min-height: 100vh;
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        .top-banner {
            background: linear-gradient(135deg, #065f46 0%, #047857 50%, #10b981 100%);
            color: white;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .top-banner h1 {
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }
        .top-banner p {
            color: #a7f3d0;
            font-size: 0.95rem;
            margin: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 18px 20px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            line-height: 1.2;
        }
        .stat-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
            font-weight: 500;
        }
        .action-bar {
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .cat-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cat-pill {
            padding: 7px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-muted);
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .cat-pill.active, .cat-pill:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 6px 16px;
        }
        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .price-table-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .price-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }
        .price-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .price-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .price-table tr:hover {
            background: #fafafa;
        }
        .product-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .product-thumb {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            background: #f1f5f9;
        }
        .product-name {
            font-weight: 700;
            color: var(--text-main);
        }
        .product-kn {
            font-size: 0.8rem;
            color: #059669;
            font-weight: 600;
        }
        .unit-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e0f2fe;
            color: #0369a1;
            text-transform: uppercase;
        }
        .unit-badge.kg { background: #dcfce7; color: #15803d; }
        .unit-badge.piece { background: #fef3c7; color: #b45309; }
        .unit-badge.packet { background: #f3e8ff; color: #7e22ce; }
        .unit-badge.bunch { background: #ecfdf5; color: #047857; }
        .unit-badge.dozen { background: #fee2e2; color: #b91c1c; }
        
        .market-input {
            width: 90px;
            padding: 6px 10px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            outline: none;
            transition: border-color 0.2s;
        }
        .market-input:focus {
            border-color: var(--primary);
            background: #f0fdf4;
        }
        .formula-tag {
            font-size: 0.75rem;
            color: var(--text-muted);
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        .selling-display {
            font-size: 1.05rem;
            font-weight: 800;
            color: #059669;
        }
        .updated-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .updated-badge.today { background: #d1fae5; color: #065f46; }
        .updated-badge.old { background: #fee2e2; color: #991b1b; }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-sm { padding: 4px 10px; font-size: 0.78rem; }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active { display: flex; }
        .modal-card {
            background: white;
            border-radius: 16px;
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 24px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <div class="main-content">
        <!-- Top Banner -->
        <div class="top-banner">
            <div>
                <h1><i class="fas fa-carrot"></i> Vegetables & Fruits Daily Rates</h1>
                <p>ದೈನಂದಿನ ಮಾರುಕಟ್ಟೆ ಬೆಲೆ ನವೀಕರಣ & ಸ್ವಯಂಚಾಲಿತ ಮಾರಾಟ ಬೆಲೆ (+₹3.00)</p>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-secondary" onclick="openImportModal()"><i class="fas fa-file-import"></i> Quick Import Mandi Rates</button>
                <button class="btn btn-secondary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Item</button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div style="background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#ecfdf5; color:#059669;"><i class="fas fa-boxes-stacked"></i></div>
                <div class="stat-info">
                    <h3><?= $totalItems ?></h3>
                    <p>Total Veg & Fruits</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#d1fae5; color:#047857;"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?= $updatedTodayCount ?></h3>
                    <p>Updated Today (<?= date('d M') ?>)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2; color:#b91c1c;"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?= $pendingUpdateCount ?></h3>
                    <p>Pending Today's Update</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f2fe; color:#0284c7;"><i class="fas fa-calculator"></i></div>
                <div class="stat-info">
                    <h3>+₹3.00</h3>
                    <p>Auto Selling Margin</p>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="action-bar">
            <div class="cat-pills">
                <a href="daily_prices.php?cat=all" class="cat-pill <?= $selectedCat === 'all' ? 'active' : '' ?>">All Produce</a>
                <a href="daily_prices.php?cat=indianMain" class="cat-pill <?= $selectedCat === 'indianMain' ? 'active' : '' ?>">Everyday Veg</a>
                <a href="daily_prices.php?cat=indianLeafy" class="cat-pill <?= $selectedCat === 'indianLeafy' ? 'active' : '' ?>">Leafy Greens (Bunches)</a>
                <a href="daily_prices.php?cat=dailyHerbs" class="cat-pill <?= $selectedCat === 'dailyHerbs' ? 'active' : '' ?>">Herbs & Aromatics</a>
                <a href="daily_prices.php?cat=indianFruits" class="cat-pill <?= $selectedCat === 'indianFruits' ? 'active' : '' ?>">Indian Fruits</a>
                <a href="daily_prices.php?cat=globalFruits" class="cat-pill <?= $selectedCat === 'globalFruits' ? 'active' : '' ?>">Global Fruits</a>
                <a href="daily_prices.php?cat=global" class="cat-pill <?= $selectedCat === 'global' ? 'active' : '' ?>">Exotic & Packaged</a>
            </div>
            <form method="GET" action="daily_prices.php" class="search-box">
                <input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCat) ?>">
                <i class="fas fa-search" style="color:#94a3b8;"></i>
                <input type="text" name="search" placeholder="Search product..." value="<?= htmlspecialchars($searchQuery) ?>">
            </form>
        </div>

        <!-- Main Daily Prices Form (supports Bulk Update) -->
        <form method="POST" action="daily_prices.php" id="bulkForm">
            <input type="hidden" name="action" value="bulk_update">
            
            <div class="price-table-card">
                <div style="padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:700; color:#334155;">
                        <i class="fas fa-layer-group"></i> Daily Rate Board &mdash; <?= date('l, d F Y') ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Changed Rates</button>
                </div>

                <div style="overflow-x:auto;">
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Unit Type</th>
                                <th>Market Price (₹)</th>
                                <th>Margin</th>
                                <th>Selling Price (₹)</th>
                                <th>Date Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:36px; color:#94a3b8;">
                                        No items found. Click "Add Item" to add produce.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $idx => $item): 
                                    $isToday = ($item['price_date'] === $today);
                                    $imgUrl = $item['image'] ?: 'images/Potato.jpg';
                                    if (!str_starts_with($imgUrl, 'http') && !str_starts_with($imgUrl, '../')) {
                                        $imgUrl = '../' . ltrim($imgUrl, '/');
                                    }
                                ?>
                                    <tr data-row-id="<?= $item['id'] ?>">
                                        <td style="color:#94a3b8; font-weight:600;"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="product-meta">
                                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="product-thumb" onerror="this.src='https://placehold.co/80x80?text=Veg'">
                                                <div>
                                                    <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                                                    <div class="product-kn"><?= htmlspecialchars($item['name_kn'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($item['category']) ?></span></td>
                                        <td>
                                            <select name="unit_types[<?= $item['id'] ?>]" class="form-select" style="padding:4px 8px; border-radius:6px; font-size:0.8rem; border:1px solid #cbd5e1;">
                                                <option value="kg" <?= $item['unit_type'] === 'kg' ? 'selected' : '' ?>>kg (Weight)</option>
                                                <option value="piece" <?= $item['unit_type'] === 'piece' ? 'selected' : '' ?>>piece (ತುಂಡು)</option>
                                                <option value="bunch" <?= $item['unit_type'] === 'bunch' ? 'selected' : '' ?>>bunch (ಕಟ್ಟು)</option>
                                                <option value="dozen" <?= $item['unit_type'] === 'dozen' ? 'selected' : '' ?>>dozen (ಡಜನ್)</option>
                                                <option value="packet" <?= $item['unit_type'] === 'packet' ? 'selected' : '' ?>>packet (ಪ್ಯಾಕೆಟ್)</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                <span>₹</span>
                                                <input type="number" step="0.50" min="0" 
                                                    name="market_prices[<?= $item['id'] ?>]" 
                                                    id="market_<?= $item['id'] ?>"
                                                    class="market-input" 
                                                    value="<?= number_format((float)$item['market_price'], 2, '.', '') ?>"
                                                    oninput="recalcRow(<?= $item['id'] ?>)">
                                            </div>
                                        </td>
                                        <td>
                                            <span class="formula-tag">+₹<?= number_format((float)$item['profit_margin'], 2) ?></span>
                                            <input type="hidden" id="margin_<?= $item['id'] ?>" name="margins[<?= $item['id'] ?>]" value="<?= (float)$item['profit_margin'] ?>">
                                        </td>
                                        <td>
                                            <span class="selling-display" id="selling_<?= $item['id'] ?>">
                                                ₹<?= number_format((float)$item['selling_price'], 2) ?>
                                            </span>
                                            <span style="font-size:0.75rem; color:#64748b;">/ <?= htmlspecialchars($item['unit_type']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($isToday): ?>
                                                <span class="updated-badge today"><i class="fas fa-check"></i> Today</span>
                                            <?php else: ?>
                                                <span class="updated-badge old"><i class="fas fa-history"></i> <?= htmlspecialchars($item['price_date']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="saveSingle(<?= $item['id'] ?>)" title="Quick Save Item">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewHistory(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')" title="Price History">
                                                <i class="fas fa-chart-line"></i> History
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:16px 20px; background:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:0.85rem; color:#64748b;">
                        Showing <?= count($items) ?> vegetables & fruits. Selling Price is automatically maintained as Market Price + ₹3.00.
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Changed Rates</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Hidden Form for Single Save -->
    <form id="singleForm" method="POST" action="daily_prices.php" style="display:none;">
        <input type="hidden" name="action" value="update_single">
        <input type="hidden" name="id" id="single_id">
        <input type="hidden" name="market_price" id="single_market_price">
        <input type="hidden" name="unit_type" id="single_unit_type">
        <input type="hidden" name="profit_margin" id="single_profit_margin">
        <input type="hidden" name="notes" id="single_notes" value="Quick inline save">
    </form>

    <!-- Modal: Quick Import Mandi Rates -->
    <div class="modal" id="importModal">
        <div class="modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:1.2rem; font-weight:700;"><i class="fas fa-file-import"></i> Quick Mandi Rates Import</h3>
                <button type="button" style="border:none; background:none; font-size:1.3rem; cursor:pointer;" onclick="closeModal('importModal')">&times;</button>
            </div>
            <p style="color:#64748b; font-size:0.88rem; margin-bottom:12px;">
                Paste current market prices from Mandi / APMC list. Format: <code>Product Name, Market Price</code> (one per line).<br>
                <em>Example:</em><br>
                <code>Tomato, 32</code><br>
                <code>Potato, 22</code><br>
                <code>Onion, 27</code><br>
                <code>Banana, 47, dozen</code>
            </p>
            <form method="POST" action="daily_prices.php">
                <input type="hidden" name="action" value="import_text">
                <textarea name="import_data" rows="10" style="width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:12px; font-family:monospace; font-size:0.9rem;" placeholder="Tomato, 32&#10;Potato, 22&#10;Onion, 27"></textarea>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import & Apply Rates</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add New Produce Item -->
    <div class="modal" id="addModal">
        <div class="modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:1.2rem; font-weight:700;"><i class="fas fa-plus-circle"></i> Add Produce Item</h3>
                <button type="button" style="border:none; background:none; font-size:1.3rem; cursor:pointer;" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="daily_prices.php">
                <input type="hidden" name="action" value="add_product">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">English Name *</label>
                        <input type="text" name="name" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;" placeholder="e.g. Ridge Gourd">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">Kannada Name (ಕನ್ನಡ ಹೆಸರು)</label>
                        <input type="text" name="name_kn" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;" placeholder="e.g. ಹೀರೇಕಾಯಿ">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">Category</label>
                        <select name="category" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                            <option value="indianMain">Everyday Indian Veg</option>
                            <option value="indianLeafy">Indian Leafy Greens</option>
                            <option value="dailyHerbs">Daily Aromatics & Herbs</option>
                            <option value="indianFruits">Indian Fruits</option>
                            <option value="globalFruits">Global Fruits</option>
                            <option value="global">Global Veg / Exotic</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">Unit Type *</label>
                        <select name="unit_type" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                            <option value="kg">kg (Weight by gram/kg)</option>
                            <option value="piece">piece (ಪ್ರತಿ ಕಾಯಿ / ತುಂಡು)</option>
                            <option value="bunch">bunch (ಕಟ್ಟು)</option>
                            <option value="dozen">dozen (ಡಜನ್)</option>
                            <option value="packet">packet (ಪ್ಯಾಕೆಟ್)</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">Today's Market Price (₹) *</label>
                        <input type="number" step="0.50" min="0" name="market_price" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;" placeholder="e.g. 40.00">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#475569;">Selling Price Calculation</label>
                        <div style="padding:9px 12px; background:#f1f5f9; border-radius:8px; font-weight:700; color:#059669; font-size:0.9rem;">
                            Auto: Market Rate + ₹3.00
                        </div>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Packet Sizes (for packet units)</label>
                    <input type="text" name="packet_sizes" value="100g,250g,500g,1kg" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Price History -->
    <div class="modal" id="historyModal">
        <div class="modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:1.2rem; font-weight:700;" id="histTitle"><i class="fas fa-chart-line"></i> Price History</h3>
                <button type="button" style="border:none; background:none; font-size:1.3rem; cursor:pointer;" onclick="closeModal('historyModal')">&times;</button>
            </div>
            <div id="histContent" style="max-height:400px; overflow-y:auto;">
                <p style="color:#94a3b8; text-align:center;">Loading history...</p>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('historyModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Real-time automatic calculation of selling price (Market + ₹3)
        function recalcRow(id) {
            const mInput = document.getElementById('market_' + id);
            const marginInput = document.getElementById('margin_' + id);
            const sSpan = document.getElementById('selling_' + id);
            if (!mInput || !sSpan) return;

            const mVal = parseFloat(mInput.value) || 0;
            const margin = parseFloat(marginInput ? marginInput.value : 3.00) || 3.00;
            const selling = (mVal + margin).toFixed(2);
            sSpan.innerText = '₹' + selling;
        }

        function saveSingle(id) {
            const row = document.querySelector(`tr[data-row-id="${id}"]`);
            if (!row) return;

            const mInput = document.getElementById('market_' + id);
            const uSelect = row.querySelector(`select[name="unit_types[${id}]"]`);
            const marginInput = document.getElementById('margin_' + id);

            document.getElementById('single_id').value = id;
            document.getElementById('single_market_price').value = mInput ? mInput.value : '0';
            document.getElementById('single_unit_type').value = uSelect ? uSelect.value : 'kg';
            document.getElementById('single_profit_margin').value = marginInput ? marginInput.value : '3.00';

            document.getElementById('singleForm').submit();
        }

        function viewHistory(id, name) {
            document.getElementById('histTitle').innerHTML = `<i class="fas fa-chart-line"></i> Price History: ${name}`;
            const content = document.getElementById('histContent');
            content.innerHTML = '<p style="text-align:center; padding:20px; color:#94a3b8;">Loading history records...</p>';
            document.getElementById('historyModal').classList.add('active');

            fetch('daily_prices.php?ajax_history=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.history && data.history.length > 0) {
                        let html = '<table style="width:100%; border-collapse:collapse; font-size:0.88rem;">';
                        html += '<thead><tr style="background:#f1f5f9; text-align:left;"><th style="padding:8px 12px;">Date</th><th style="padding:8px 12px;">Market (₹)</th><th style="padding:8px 12px;">Margin</th><th style="padding:8px 12px;">Selling (₹)</th><th style="padding:8px 12px;">Updated By</th><th style="padding:8px 12px;">Notes</th></tr></thead><tbody>';
                        data.history.forEach(h => {
                            html += `<tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:8px 12px; font-weight:600;">${h.effective_date}</td>
                                <td style="padding:8px 12px;">₹${parseFloat(h.market_price).toFixed(2)}</td>
                                <td style="padding:8px 12px; color:#059669;">+₹${parseFloat(h.profit_margin).toFixed(2)}</td>
                                <td style="padding:8px 12px; font-weight:700; color:#059669;">₹${parseFloat(h.selling_price).toFixed(2)}</td>
                                <td style="padding:8px 12px; color:#64748b;">${h.updated_by || 'Admin'}</td>
                                <td style="padding:8px 12px; font-size:0.8rem; color:#64748b;">${h.notes || '-'}</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p style="text-align:center; padding:20px; color:#94a3b8;">No previous price changes recorded yet. Today is the initial baseline rate.</p>';
                    }
                })
                .catch(err => {
                    content.innerHTML = '<p style="color:#ef4444; text-align:center;">Failed to load history.</p>';
                });
        }

        function openImportModal() { document.getElementById('importModal').classList.add('active'); }
        function openAddModal() { document.getElementById('addModal').classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        };
    </script>
</body>
</html>
