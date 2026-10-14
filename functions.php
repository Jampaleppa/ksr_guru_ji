<?php
// ============================================================
// functions.php - Database helper functions
// ============================================================

require_once 'config.php';

/**
 * Get all categories, ordered by name.
 * @return array
 */
function getCategories(): array
{
    global $conn;
    $sql = "SELECT id, name, kn_name, icon FROM categories ORDER BY name ASC";
    $result = $conn->query($sql);
    if (!$result)
        return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Get a single category by its ID.
 * @param string $id
 * @return array|null
 */
function getCategoryById(string $id): ?array
{
    global $conn;
    $stmt = $conn->prepare("SELECT id, name, kn_name, icon FROM categories WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get all main categories (groupings), ordered by name.
 * @return array
 */
function getMainCategories(): array
{
    global $conn;
    $sql = "SELECT id, name, kn, emoji, sub_ids FROM main_categories ORDER BY name ASC";
    $result = $conn->query($sql);
    if (!$result)
        return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Get products, optionally filtered by category and/or search term.
 * @param string $category  Category ID filter (empty = all)
 * @param string $search    Search keyword (empty = no filter)
 * @return array
 */
function getProducts(string $category = '', string $search = ''): array
{
    global $conn;

    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1";
    $types = '';
    $params = [];

    if ($category !== '') {
        $sql .= " AND p.category_id = ?";
        $types .= 's';
        $params[] = $category;
    }

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ?)";
        $types .= 'ss';
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Get a single product by its ID.
 * @param int $id
 * @return array|null
 */
function getProductById(int $id): ?array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get the first featured product (is_featured = 1).
 * @return array|null
 */
function getFeaturedProduct(): ?array
{
    global $conn;
    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_featured = 1
            LIMIT 1";
    $result = $conn->query($sql);
    if (!$result)
        return null;
    $row = $result->fetch_assoc();
    return $row ?: null;
}

/**
 * Get random product recommendations, excluding a specific product ID.
 * Optimized to avoid random database sorting which causes full table scanning.
 * 
 * @param int $excludeId  Product ID to exclude
 * @param int $limit      Number of recommendations
 * @return array
 */
function getRecommendations(int $excludeId = 0, int $limit = 4): array
{
    global $conn;

    // 1. Fetch available product IDs
    $sql = "SELECT id FROM products";
    if ($excludeId > 0) {
        $sql .= " WHERE id != ?";
    }

    $stmt = $conn->prepare($sql);
    if ($excludeId > 0) {
        $stmt->bind_param("i", $excludeId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt->close();

    if (empty($ids)) {
        return [];
    }

    // 2. Select a random subset of IDs in PHP (O(1) database complexity)
    if (count($ids) <= $limit) {
        $selectedIds = $ids;
    } else {
        $randomKeys = array_rand($ids, $limit);
        $selectedIds = is_array($randomKeys) ? array_map(fn($key) => $ids[$key], $randomKeys) : [$ids[$randomKeys]];
    }

    // 3. Retrieve detail logs for the selected random IDs
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sqlLoad = "SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id IN ($placeholders)";

    $stmtLoad = $conn->prepare($sqlLoad);
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($selectedIds));
    $stmtLoad->bind_param($types, ...$selectedIds);
    $stmtLoad->execute();
    $resultLoad = $stmtLoad->get_result();
    
    $rows = [];
    while ($row = $resultLoad->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmtLoad->close();
    
    // Shuffle rows to ensure random display order
    shuffle($rows);
    return $rows;
}

/**
 * Get a category by its slug.
 * @param string $slug
 * @return array|null
 */
function getCategoryBySlug(string $slug): ?array
{
    global $conn;
    $stmt = $conn->prepare("SELECT id, name, kn_name, icon FROM categories WHERE id = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get products by a category slug (alias of getProducts).
 * @param string $category_id
 * @return array
 */
function getProductsByCategory(string $category_id): array
{
    return getProducts($category_id);
}

if (!function_exists('esc')) {
    /**
     * Escape value for safe HTML output.
     * 
     * @param mixed $value
     * @return string
     */
    function esc($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('getCart')) {
    /**
     * Get cart items from session
     * @return array
     */
    function getCart(): array
    {
        return $_SESSION['cart'] ?? [];
    }
}

if (!function_exists('getCartCount')) {
    /**
     * Get cart count (total quantity)
     * @return int
     */
    function getCartCount(): int
    {
        $cart = getCart();
        return array_sum(array_column($cart, 'qty'));
    }
}

if (!function_exists('getCartTotal')) {
    /**
     * Get cart total price
     * @return float|int
     */
    function getCartTotal()
    {
        $cart = getCart();
        return array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $cart));
    }
}

if (!function_exists('getProductSchemaInfo')) {
    /**
     * Get products schema info with static caching to prevent redundant SHOW COLUMNS queries.
     * @return array
     */
    function getProductSchemaInfo(): array
    {
        static $schemaInfo = null;
        if ($schemaInfo === null) {
            $schemaInfo = [
                'desc_col' => 'p.name',
                'img_col' => '""',
                'has_desc' => false,
                'has_img' => false,
                'cat_slug_col' => 'c.id',
                'has_cat_slug' => false,
                'has_weight' => false
            ];
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->query("SHOW COLUMNS FROM products");
                $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('description_en', $cols)) {
                    $schemaInfo['desc_col'] = 'p.description_en';
                    $schemaInfo['has_desc'] = true;
                } elseif (in_array('description', $cols)) {
                    $schemaInfo['desc_col'] = 'p.description';
                    $schemaInfo['has_desc'] = true;
                }
                
                if (in_array('image_url', $cols)) {
                    $schemaInfo['img_col'] = 'p.image_url';
                    $schemaInfo['has_img'] = true;
                } elseif (in_array('images', $cols)) {
                    $schemaInfo['img_col'] = 'p.images';
                    $schemaInfo['has_img'] = true;
                }
                
                if (in_array('weight', $cols)) {
                    $schemaInfo['has_weight'] = true;
                }
                
                $stmt2 = $pdo->query("SHOW COLUMNS FROM categories");
                $catCols = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('slug', $catCols)) {
                    $schemaInfo['cat_slug_col'] = 'c.slug';
                    $schemaInfo['has_cat_slug'] = true;
                }
            } catch (Throwable $e) {
                // Keep default fallback values
            }
        }
        return $schemaInfo;
    }
}

if (!function_exists('normalizeImagePath')) {
    function normalizeImagePath(?string $rawPath): string {
        if (empty($rawPath)) return '';
        $clean = str_replace('\\', '/', $rawPath);
        
        // Dynamically detect subdirectory depth to prepend correct relative path
        $prefix = '';
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptPath, '/categories/') !== false || 
            strpos($scriptPath, '/cart/') !== false || 
            strpos($scriptPath, '/checkout/') !== false ||
            strpos($scriptPath, '/admin/') !== false || 
            strpos($scriptPath, '/delivery/') !== false) {
            $prefix = '../';
        }
        
        if (preg_match('#(?:.*)(images/.*)$#i', $clean, $matches)) {
            $imagePath = preg_replace('#/+#', '/', $matches[1]);
            $localPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
            if (!is_file($localPath)) {
                $flatPath = 'images/' . basename($imagePath);
                $flatLocalPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $flatPath);
                if (is_file($flatLocalPath)) {
                    $imagePath = $flatPath;
                }
            }
            return $prefix . $imagePath;
        }
        return $prefix . 'images/' . basename($clean);
    }
}

register_shutdown_function(function() {
    // Avoid injecting HTML into JSON/AJAX API requests
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax || isset($_GET['ajax'])) return;
    
    $isJson = false;
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type: application/json') !== false) {
            $isJson = true;
            break;
        }
    }
    if ($isJson) return;
    ?>
    <!-- Styled Cart Add Toast Popup -->
    <style>
        #ksr-cart-alert-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(15, 123, 58, 0.15), 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e8f5e9;
            padding: 20px;
            width: 350px;
            z-index: 100000;
            display: none;
            font-family: 'Inter', -apple-system, sans-serif;
            box-sizing: border-box;
            animation: ksrFadeInUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes ksrFadeInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .ksr-toast-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            border-bottom: 1px solid #f0f7f2;
            padding-bottom: 8px;
        }
        .ksr-toast-title {
            color: #0f7b3a;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }
        .ksr-toast-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: #999;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }
        .ksr-toast-close:hover {
            color: #333;
        }
        .ksr-toast-body {
            font-size: 0.92rem;
            color: #2e3e35;
            margin-bottom: 16px;
            line-height: 1.5;
            font-family: 'Inter', sans-serif;
        }
        .ksr-toast-body strong {
            color: #111;
        }
        .ksr-toast-count {
            display: inline-block;
            margin-top: 6px;
            background: #e8f5e9;
            color: #0f7b3a;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .ksr-toast-actions {
            display: flex;
            gap: 12px;
        }
        .ksr-toast-btn {
            flex: 1;
            padding: 10px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        .ksr-toast-btn-continue {
            background: #f3f5f4;
            color: #4b6354;
            border: 1px solid #e1e7e4;
        }
        .ksr-toast-btn-continue:hover {
            background: #e8ebe9;
        }
        .ksr-toast-btn-cart {
            background: linear-gradient(135deg, #0f7b3a, #149c4a);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 10px rgba(15, 123, 58, 0.2);
        }
        .ksr-toast-btn-cart:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(15, 123, 58, 0.3);
        }
    </style>
    <div id="ksr-cart-alert-toast">
        <div class="ksr-toast-header">
            <span class="ksr-toast-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #10b981; vertical-align: middle;"><polyline points="20 6 9 17 4 12"/></svg> Added to Cart!</span>
            <button class="ksr-toast-close" onclick="closeKsrToast()">&times;</button>
        </div>
        <div class="ksr-toast-body">
            You added <strong id="ksr-toast-item-name">Product</strong><br>
            Quantity: <span id="ksr-toast-item-qty">1</span><br>
            <span class="ksr-toast-count" id="ksr-toast-item-total">Total items in cart: 0</span>
        </div>
        <div class="ksr-toast-actions">
            <button class="ksr-toast-btn ksr-toast-btn-continue" onclick="closeKsrToast()">Continue</button>
            <a href="" id="ksr-toast-item-link" class="ksr-toast-btn ksr-toast-btn-cart">View Cart</a>
        </div>
    </div>
    <script>
        function normalizeImagePathJS(rawPath) {
            if (!rawPath) return '';
            let clean = rawPath.replace(/\\/g, '/');
            const idx = clean.toLowerCase().indexOf('images/');
            if (idx !== -1) {
                clean = clean.substring(idx);
            } else {
                const parts = clean.split('/');
                clean = 'images/' + parts[parts.length - 1];
            }
            const path = window.location.pathname;
            if (path.includes('/categories/') || path.includes('/cart/') || path.includes('/checkout/') || path.includes('/delivery/') || path.includes('/admin/')) {
                return '../' + clean;
            }
            return clean;
        }

        function closeKsrToast() {
            document.getElementById('ksr-cart-alert-toast').style.display = 'none';
        }
        function showAddedToCartPopup(name, qty, totalItems) {
            document.getElementById('ksr-toast-item-name').innerText = name;
            document.getElementById('ksr-toast-item-qty').innerText = qty;
            document.getElementById('ksr-toast-item-total').innerText = "Total items in cart: " + totalItems;
            
            var targetLink = document.getElementById('ksr-toast-item-link');
            if (window.location.pathname.indexOf('/categories/') !== -1) {
                targetLink.href = '../cart/cart.php';
            } else if (window.location.pathname.indexOf('/cart/') !== -1 || window.location.pathname.indexOf('/checkout/') !== -1) {
                targetLink.href = 'cart.php';
            } else {
                targetLink.href = 'cart/cart.php';
            }
            
            var toast = document.getElementById('ksr-cart-alert-toast');
            toast.style.display = 'block';
            
            if (window._ksrToastTimeout) clearTimeout(window._ksrToastTimeout);
            window._ksrToastTimeout = setTimeout(function() {
                toast.style.display = 'none';
            }, 5000);
            
            // Dynamically update standard badges
            var selectors = ['#cartCount', '#cartBadge', '.cart-count', '.cart-badge', '#cartCountSpan', '.cart-item-count'];
            selectors.forEach(function(sel) {
                document.querySelectorAll(sel).forEach(function(el) {
                    if (el.tagName === 'INPUT') {
                        el.value = totalItems;
                    } else {
                        el.innerText = totalItems;
                    }
                });
            });
        }
    </script>
    <?php
});
