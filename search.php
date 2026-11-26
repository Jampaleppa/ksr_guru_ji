<?php
// ============================================================
// search.php - Global Product Search Across All Categories
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$query          = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$minPrice       = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? max(0.0, (float)$_GET['min_price']) : null;
$maxPrice       = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? max(0.0, (float)$_GET['max_price']) : null;
$inStockOnly    = !empty($_GET['in_stock']);
$sortParam      = trim((string)($_GET['sort'] ?? 'newest'));
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 24;
$hasAnyFilter   = ($categoryFilter !== '' || $minPrice !== null || $maxPrice !== null || $inStockOnly);

$orderSql = match ($sortParam) {
    'price_asc'  => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
    'name_asc'   => 'p.name ASC',
    default      => 'p.id DESC',
};

// Handle AJAX search autocomplete / live results
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    if ($query === '' && !$hasAnyFilter) {
        echo json_encode(['results' => [], 'total' => 0, 'query' => '']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $whereParts = ['(p.status = 1 OR p.status IS NULL)'];
        $params = [];
        if ($query !== '') {
            $whereParts[] = '(p.name LIKE ? OR p.kn_name LIKE ? OR c.name LIKE ?)';
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        if ($categoryFilter !== '') {
            $whereParts[] = 'p.category_id = ?';
            $params[] = $categoryFilter;
        }
        if ($minPrice !== null) {
            $whereParts[] = 'p.price >= ?';
            $params[] = $minPrice;
        }
        if ($maxPrice !== null) {
            $whereParts[] = 'p.price <= ?';
            $params[] = $maxPrice;
        }
        if ($inStockOnly) {
            $whereParts[] = 'p.stock > 0';
        }
        $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE " . implode(' AND ', $whereParts) . "
                ORDER BY {$orderSql}
                LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $r) {
            $img = $r['images'] ?? $r['image'] ?? $r['image_url'] ?? '';
            $results[] = [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
                'kn_name' => (string)($r['kn_name'] ?? ''),
                'price' => (float)($r['price'] ?? 0),
                'stock' => (int)($r['stock'] ?? 10),
                'category_id' => (int)($r['category_id'] ?? 0),
                'images' => $img,
                'image_url' => $img,
                'category_name' => (string)($r['category_name'] ?? 'General')
            ];
        }

        // Fallback search if DB yields 0
        if (empty($results) && function_exists('getStoreFallbackProducts')) {
            $fb = getStoreFallbackProducts($categoryFilter, $query);
            foreach ($fb as $f) {
                $pVal = (float)($f['price'] ?? 0);
                $stk  = (int)($f['stock'] ?? 10);
                if ($minPrice !== null && $pVal < $minPrice) continue;
                if ($maxPrice !== null && $pVal > $maxPrice) continue;
                if ($inStockOnly && $stk <= 0) continue;
                $img = $f['images'] ?? $f['image'] ?? $f['image_url'] ?? '';
                $results[] = [
                    'id' => (int)($f['id'] ?? 0),
                    'name' => (string)($f['name'] ?? ''),
                    'kn_name' => (string)($f['kn_name'] ?? ''),
                    'price' => $pVal,
                    'stock' => $stk,
                    'category_id' => (int)($f['category_id'] ?? 0),
                    'images' => $img,
                    'image_url' => $img,
                    'category_name' => (string)($f['category_name'] ?? 'General')
                ];
            }
        }

        echo json_encode([
            'results' => $results,
            'total' => count($results),
            'query' => $query,
            'suggestion_url' => '/suggestion-box.php?product=' . urlencode($query)
        ]);
    } catch (Throwable $e) {
        error_log("Search AJAX error: " . $e->getMessage());
        echo json_encode(['results' => [], 'total' => 0, 'error' => 'Database error']);
    }
    exit;
}

// ------------------------------------------------------------
// Full Page Search Execution (Filters + Sorting + Pagination)
// ------------------------------------------------------------
$products = [];
$totalResults = 0;
$totalPages = 1;

if ($query !== '' || $hasAnyFilter) {
    try {
        $pdo = getDBConnection();
        $whereParts = ['(p.status = 1 OR p.status IS NULL)'];
        $params = [];
        if ($query !== '') {
            $whereParts[] = '(p.name LIKE ? OR p.kn_name LIKE ? OR c.name LIKE ?)';
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        if ($categoryFilter !== '') {
            $whereParts[] = 'p.category_id = ?';
            $params[] = $categoryFilter;
        }
        if ($minPrice !== null) {
            $whereParts[] = 'p.price >= ?';
            $params[] = $minPrice;
        }
        if ($maxPrice !== null) {
            $whereParts[] = 'p.price <= ?';
            $params[] = $maxPrice;
        }
        if ($inStockOnly) {
            $whereParts[] = 'p.stock > 0';
        }
        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalResults = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalResults / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*, c.name AS category_name, c.kn_name AS category_kn_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE {$whereSql}
                ORDER BY {$orderSql}
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Search error: " . $e->getMessage());
    }

    if (empty($products) && $totalResults === 0 && function_exists('getStoreFallbackProducts')) {
        $fbAll = getStoreFallbackProducts($categoryFilter, $query);
        $fbFiltered = array_values(array_filter($fbAll, function (array $f) use ($minPrice, $maxPrice, $inStockOnly): bool {
            $pVal = (float)($f['price'] ?? 0);
            $stk  = (int)($f['stock'] ?? 10);
            if ($minPrice !== null && $pVal < $minPrice) return false;
            if ($maxPrice !== null && $pVal > $maxPrice) return false;
            if ($inStockOnly && $stk <= 0) return false;
            return true;
        }));
        usort($fbFiltered, function (array $a, array $b) use ($sortParam): int {
            return match ($sortParam) {
                'price_asc'  => ((float)($a['price'] ?? 0)) <=> ((float)($b['price'] ?? 0)),
                'price_desc' => ((float)($b['price'] ?? 0)) <=> ((float)($a['price'] ?? 0)),
                'name_asc'   => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')),
                default      => 0,
            };
        });
        $totalResults = count($fbFiltered);
        $totalPages = max(1, (int)ceil($totalResults / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $products = array_slice($fbFiltered, ($page - 1) * $perPage, $perPage);
    }

    // Redirect to Suggestion Box when search query returns nothing and no narrowing filters were active
    if (empty($products) && $query !== '' && !$hasAnyFilter) {
        header('Location: /suggestion-box.php?product=' . urlencode($query) . '&not_found=1');
        exit;
    }
}

// Fetch categories for filtering dropdown
$categories = [];
try {
    $pdo = getDBConnection();
    $categories = $pdo->query("SELECT id, name, kn_name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignore) {}

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search: <?php echo htmlspecialchars($query); ?> · KSR GURU JI</title>
    <link rel="icon" href="/company_logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Kannada:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/cart-checkout.css">
    <style>
        :root {
            --primary: #0F7B3A;
            --primary-dark: #0A5E2C;
            --primary-light: #E8F5E9;
            --secondary: #E85D2C;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-light: #F8FAFC;
            --border: #E2E8F0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Noto Sans Kannada', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .logo-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 800;
            font-size: 1.3rem;
        }
        .logo-img { height: 42px; border-radius: 8px; }
        .search-form-wrap {
            flex: 1;
            max-width: 700px;
            position: relative;
        }
        .search-bar {
            display: flex;
            align-items: center;
            background: #F1F5F9;
            border-radius: 50px;
            padding: 4px 6px 4px 20px;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .search-bar:focus-within {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15,123,58,0.1);
        }
        .search-bar input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            outline: none;
            padding: 8px 0;
            color: var(--text-dark);
        }
        .search-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-btn:hover { background: var(--primary-dark); }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }
        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            background: #F1F5F9;
            transition: all 0.2s;
        }
        .nav-btn:hover { background: #E2E8F0; }
        .nav-btn.primary { background: var(--primary-light); color: var(--primary); }
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 20px;
            flex: 1;
            width: 100%;
        }
        .search-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .results-title { font-size: 1.4rem; font-weight: 700; }
        .results-count { color: var(--text-muted); font-size: 0.95rem; margin-top: 4px; }
        .suggest-banner {
            background: #FEF3C7;
            border: 1px solid #FCD34D;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        .suggest-banner p { color: #92400E; font-size: 0.95rem; font-weight: 500; }
        .suggest-link {
            background: var(--secondary);
            color: #fff;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: opacity 0.2s;
        }
        .suggest-link:hover { opacity: 0.9; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }
        .card-img-wrap {
            height: 180px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 12px;
        }
        .card-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        .card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .card-cat {
            font-size: 0.75rem;
            color: var(--primary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .card-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .card-title-kn {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .card-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px dashed var(--border);
        }
        .card-price {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary-dark);
        }
        .add-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }
        .add-btn:hover { background: var(--primary-dark); }
        .stock-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 20px;
            background: #dcfce7;
            color: #15803d;
            font-weight: 600;
        }
        .stock-badge.out { background: #fee2e2; color: #b91c1c; }
        footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 40px;
        }
    </style>
    <link rel="stylesheet" href="/mobile-app.css?v=3.0" />
</head>
<body>
    <header class="header">
        <div class="header-wrap">
            <a href="/" class="logo-link">
                <img src="/company_logo.jpg" alt="Logo" class="logo-img" onerror="this.style.display='none'">
                <span>KSR GURU JI</span>
            </a>

            <div class="search-form-wrap">
                <form class="search-bar" action="/search.php" method="GET">
                    <input
                        type="text"
                        placeholder="Search products across all categories..."
                        id="globalSearchInput"
                        name="q"
                        value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                        autocomplete="off"
                        required
                    />
                    <button type="submit" class="search-btn" title="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <div class="nav-actions">
                <a href="/suggestion-box.php?product=<?php echo urlencode($query); ?>" class="nav-btn primary" title="Suggest product">
                    <i class="fas fa-lightbulb"></i> Suggest Product
                </a>
                <a href="/checkout.php" class="nav-btn" title="View Cart">
                    <i class="fas fa-shopping-cart"></i> Cart (<?php echo $cartCount; ?>)
                </a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="search-header">
            <div>
                <h1 class="results-title">
                    <?php if ($query !== ''): ?>
                        Search Results for "<?php echo htmlspecialchars($query); ?>"
                    <?php else: ?>
                        Browse All Products
                    <?php endif; ?>
                </h1>
                <p class="results-count"><?php echo (int)$totalResults; ?> item(s) found<?php echo $totalPages > 1 ? " · Page {$page} of {$totalPages}" : ''; ?></p>
            </div>
            <div>
                <a href="/suggestion-box.php?product=<?php echo urlencode($query); ?>" class="suggest-link">
                    <i class="fas fa-plus-circle"></i> Can't find what you need? Suggest it here
                </a>
            </div>
        </div>

        <form method="GET" action="/search.php" style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" />
            <div style="flex:1;min-width:150px;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#475569;margin-bottom:4px;">Category</label>
                <select name="category" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.88rem;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars((string)$c['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string)$categoryFilter === (string)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="width:110px;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#475569;margin-bottom:4px;">Min ₹</label>
                <input type="number" name="min_price" min="0" step="1" value="<?php echo $minPrice !== null ? htmlspecialchars((string)$minPrice) : ''; ?>" placeholder="0" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.88rem;" />
            </div>
            <div style="width:110px;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#475569;margin-bottom:4px;">Max ₹</label>
                <input type="number" name="max_price" min="0" step="1" value="<?php echo $maxPrice !== null ? htmlspecialchars((string)$maxPrice) : ''; ?>" placeholder="Any" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.88rem;" />
            </div>
            <div style="min-width:150px;">
                <label style="display:block;font-size:0.75rem;font-weight:700;color:#475569;margin-bottom:4px;">Sort By</label>
                <select name="sort" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.88rem;">
                    <option value="newest" <?php echo $sortParam === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="price_asc" <?php echo $sortParam === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sortParam === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name_asc" <?php echo $sortParam === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                </select>
            </div>
            <label style="display:flex;align-items:center;gap:6px;font-size:0.86rem;font-weight:600;color:#334155;padding-bottom:8px;cursor:pointer;">
                <input type="checkbox" name="in_stock" value="1" <?php echo $inStockOnly ? 'checked' : ''; ?> /> In Stock Only
            </label>
            <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;">Apply Filters</button>
            <?php if ($hasAnyFilter): ?>
                <a href="/search.php?q=<?php echo urlencode($query); ?>" style="padding:9px 12px;color:#dc2626;font-size:0.85rem;font-weight:700;text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>

        <div class="suggest-banner">
            <div>
                <p><strong>Looking for an item not listed?</strong> We can source it for you!</p>
                <small style="color:#B45309;">Our sourcing & delivery team will fulfill custom requests quickly.</small>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a href="/suggestion-box.php?product=<?php echo urlencode($query); ?>" class="suggest-link">
                    Suggest Missing Product &rarr;
                </a>
                <a href="https://wa.me/918762474406?text=<?php echo urlencode("Hello KSR GURU JI! I am searching for: {$query}. Can you source this for me?"); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:#25d366; color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:700; font-size:0.88rem;">
                    <i class="fab fa-whatsapp"></i> WhatsApp 8762474406
                </a>
            </div>
        </div>

        <div class="product-grid">
            <?php if (empty($products)): ?>
                <div style="grid-column:1/-1;background:#fff;border:1px solid var(--border);border-radius:14px;padding:32px;text-align:center;color:var(--text-muted);">
                    <p style="font-size:1rem;margin-bottom:10px;">No products match your search or active filters.</p>
                    <a href="/search.php?q=<?php echo urlencode($query); ?>" style="color:var(--primary);font-weight:700;">Reset filters</a>
                </div>
            <?php endif; ?>
            <?php foreach ($products as $p): 
                $img = $p['images'] ?? $p['image_url'] ?? '';
                $name = $p['name'] ?? 'Product';
                $knName = $p['kn_name'] ?? '';
                $price = number_format((float)($p['price'] ?? 0), 2);
                $cat = $p['category_name'] ?? 'General';
                $stock = (int)($p['stock'] ?? 0);
            ?>
                <div class="product-card">
                    <div class="card-img-wrap">
                        <?php if ($img): ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>" class="card-img" onerror="this.src='/company_logo.jpg'">
                        <?php else: ?>
                            <i class="fas fa-box" style="font-size:3rem; color:#cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <span class="card-cat"><?php echo htmlspecialchars($cat); ?></span>
                        <h3 class="card-title"><?php echo htmlspecialchars($name); ?></h3>
                        <?php if ($knName): ?>
                            <div class="card-title-kn"><?php echo htmlspecialchars($knName); ?></div>
                        <?php endif; ?>

                        <div class="card-footer">
                            <div>
                                <div class="card-price">₹<?php echo $price; ?></div>
                                <span class="stock-badge <?php echo $stock <= 0 ? 'out' : ''; ?>">
                                    <?php echo $stock > 0 ? "In Stock ({$stock})" : 'Out of Stock'; ?>
                                </span>
                            </div>
                            <form action="/add_cart.php" method="POST" style="margin:0;">
                                <input type="hidden" name="product_id" value="<?php echo (int)($p['id'] ?? 0); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="add-btn" <?php echo $stock <= 0 ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : ''; ?>>
                                    <i class="fas fa-cart-plus"></i> Add
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:26px;flex-wrap:wrap;">
                <?php for ($pg = 1; $pg <= $totalPages; $pg++):
                    $pgQuery = array_filter([
                        'q' => $query,
                        'category' => $categoryFilter,
                        'min_price' => $minPrice !== null ? (string)$minPrice : '',
                        'max_price' => $maxPrice !== null ? (string)$maxPrice : '',
                        'in_stock' => $inStockOnly ? '1' : '',
                        'sort' => $sortParam,
                        'page' => (string)$pg,
                    ], fn($v) => $v !== '');
                ?>
                    <a href="/search.php?<?php echo http_build_query($pgQuery); ?>"
                       style="padding:8px 14px;border-radius:8px;text-decoration:none;font-weight:700;font-size:0.88rem;border:1px solid var(--border);<?php echo $pg === $page ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:#fff;color:var(--text-dark);'; ?>">
                        <?php echo $pg; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> KSR GURU JI · Direct farm & daily essentials sourcing</p>
    </footer>

    <!-- Mobile Bottom Navigation -->
    <?php if (file_exists(__DIR__ . '/mobile-bottom-nav.php')) require_once __DIR__ . '/mobile-bottom-nav.php'; ?>
    <script src="/assets/js/mobile-app.js?v=3.0" defer></script>
</body>
</html>

