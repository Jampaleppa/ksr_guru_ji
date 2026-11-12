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

$query = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));

// Handle AJAX search autocomplete / live results
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    if ($query === '') {
        echo json_encode(['results' => [], 'total' => 0, 'query' => '']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.stock, p.category_id,
                       p.images, p.image_url, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE (p.name LIKE ? OR p.kn_name LIKE ? OR c.name LIKE ?)";
        $params = ["%{$query}%", "%{$query}%", "%{$query}%"];
        if ($categoryFilter !== '') {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryFilter;
        }
        $sql .= " AND (p.status = 1 OR p.status IS NULL) LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback search if DB yields 0 or in fallback mode
        if (empty($results) && function_exists('getStoreFallbackProducts')) {
            $fb = getStoreFallbackProducts($categoryFilter, $query);
            foreach ($fb as $f) {
                $results[] = [
                    'id' => $f['id'] ?? 0,
                    'name' => $f['name'] ?? '',
                    'kn_name' => $f['kn_name'] ?? '',
                    'price' => $f['price'] ?? 0,
                    'stock' => $f['stock'] ?? 10,
                    'category_id' => $f['category_id'] ?? 0,
                    'images' => $f['images'] ?? ($f['image_url'] ?? ''),
                    'image_url' => $f['images'] ?? ($f['image_url'] ?? ''),
                    'category_name' => $f['category_name'] ?? 'General'
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
// Full Page Search Execution
// ------------------------------------------------------------
$products = [];
if ($query !== '') {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT p.*, c.name AS category_name, c.kn_name AS category_kn_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE (p.name LIKE ? OR p.kn_name LIKE ? OR c.name LIKE ?)";
        $params = ["%{$query}%", "%{$query}%", "%{$query}%"];
        if ($categoryFilter !== '') {
            $sql .= " AND p.category_id = ?";
            $params[] = $categoryFilter;
        }
        $sql .= " AND (p.status = 1 OR p.status IS NULL) ORDER BY p.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Search error: " . $e->getMessage());
    }

    if (empty($products) && function_exists('getStoreFallbackProducts')) {
        $products = getStoreFallbackProducts($categoryFilter, $query);
    }

    // REQUIREMENT: If product not found, redirect to Suggestion Box with search term auto-filled!
    if (empty($products)) {
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
                <p class="results-count"><?php echo count($products); ?> item(s) found across all categories</p>
            </div>
            <div>
                <a href="/suggestion-box.php?product=<?php echo urlencode($query); ?>" class="suggest-link">
                    <i class="fas fa-plus-circle"></i> Can't find what you need? Suggest it here
                </a>
            </div>
        </div>

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
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> KSR GURU JI · Direct farm & daily essentials sourcing</p>
    </footer>
</body>
</html>

