<?php
// ============================================================
// cold-drinks.php - Dynamic Cold Drinks page (Standardized Design)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests for this page (before outputting HTML)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
        case 'products':
            $search = $_GET['search'] ?? '';
            echo json_encode(getColdDrinksProducts($search));
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        // The standard cart handler inside index.php handles add/update/remove
        default:
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
}

// ============================================================
// FETCH COLD DRINKS FROM DATABASE (PDO)
// ============================================================
function getColdDrinksProducts(string $search = ''): array {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.id = 'cold-drinks' OR c.name LIKE '%Cold%' OR c.name LIKE '%Drink%'";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ?)";
            $searchWild = '%' . $search . '%';
            $params[] = $searchWild;
            $params[] = $searchWild;
        }
        $sql .= " ORDER BY p.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch fallback data to map real images since DB has 'fa-box' placeholders
        $path = __DIR__ . '/cold-drinks-fallback.json';
        $jsonStr = file_exists($path) ? file_get_contents($path) : '[]';
        $fallbackData = json_decode($jsonStr, true) ?: [];
        $imageMap = [];
        foreach ($fallbackData as $item) {
            if (!empty($item['imageUrl'])) {
                $imageMap[strtolower(trim($item['name']))] = $item['imageUrl'];
            }
        }

        // Formulate output format for the frontend page
        $products = [];
        foreach ($rows as $row) {
            $nameLow = strtolower(trim($row['name']));
            
            // Resolve Image URL
            $finalImage = '';
            $dbIcon = $row['icon'] ?? '';
            // If DB icon is a font-awesome class placeholder or empty, override it!
            if (empty($dbIcon) || str_starts_with($dbIcon, 'fa-') || $dbIcon === 'fa-box') {
                if (isset($imageMap[$nameLow])) {
                    $finalImage = $imageMap[$nameLow];
                } else {
                    foreach ($imageMap as $baseName => $url) {
                        if (str_starts_with($nameLow, $baseName) || strpos($nameLow, $baseName) !== false) {
                            $finalImage = $url;
                            break;
                        }
                    }
                }
                
                if (!empty($finalImage) && !str_starts_with($finalImage, 'http') && !str_starts_with($finalImage, '../')) {
                    $finalImage = '../images/' . ltrim(str_replace('images/', '', $finalImage), '/');
                }
            } else {
                $finalImage = (function($img) {
                    if (str_starts_with($img, 'http') || str_starts_with($img, 'data:')) return $img;
                    $img = str_replace(['D:\\KSR Guru\\', 'D:\\KSR Guru JI\\'], '', $img);
                    $img = str_replace('\\', '/', $img);
                    if (str_starts_with($img, 'images/')) return '../' . $img;
                    if (!str_starts_with($img, '../')) return '../images/' . ltrim($img, '/');
                    return $img;
                })($dbIcon);
            }

            $products[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'kn_name' => $row['kn_name'] ?? $row['name'],
                'price' => (float)$row['price'],
                'mrp' => (float)$row['price'], // Add mrp if missing
                'category' => $row['category_name'] ?? 'Other',
                'emoji' => $row['emoji'] !== '📦' ? $row['emoji'] : '🍹',
                'description_en' => 'Refreshing cold drink',
                'variants' => [['label' => '1 unit', 'price' => (float)$row['price']]],
                'imageUrl' => $finalImage,
                'stock' => 20,
                'source' => 'Local Mart',
                'is_featured' => $row['is_featured'] ?? 0
            ];
        }
        return $products;
    } catch (PDOException $e) {
        error_log("Database error in getColdDrinksProducts: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// FALLBACK: Load static products from fallback JSON
// ============================================================
function getFallbackColdDrinksProducts(): array {
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $path = __DIR__ . '/cold-drinks-fallback.json';
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $result = [];
    $idCounter = 2000;
    $seenNames = [];
    
    foreach ($data as $item) {
        $uniqueKey = $item['name'] . '|' . ($item['size'] ?? '');
        if (isset($seenNames[$uniqueKey])) {
            continue; // skip duplicate combos
        }
        $seenNames[$uniqueKey] = true;

        $name = $item['name'];
        $size = $item['size'] ?? '1 unit';
        if ($size && strpos($name, $size) === false) {
            $name .= ' (' . $size . ')';
        }
        $knName = $item['localTag'] ?? $item['variant'] ?? $item['name'];
        
        $imagePath = $item['imageUrl'] ?? '';
        if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
            $imagePath = str_replace('D:\\KSR Guru\\', '', $imagePath);
            $imagePath = str_replace('D:\\KSR Guru JI\\', '', $imagePath);
            $imagePath = str_replace('\\', '/', $imagePath);
            if (!str_starts_with($imagePath, 'images/') && !str_starts_with($imagePath, '../')) {
                $imagePath = '../images/' . ltrim($imagePath, '/');
            } elseif (!str_starts_with($imagePath, '../')) {
                $imagePath = '../' . ltrim($imagePath, '/');
            }
        }

        $result[] = [
            'id' => $idCounter++,
            'name' => $name,
            'kn_name' => $knName,
            'price' => (float)$item['price'],
            'mrp' => (float)($item['price'] + 10),
            'category' => $item['type'] ?? 'Other',
            'emoji' => '🍹',
            'description_en' => ($item['variant'] ?? '') . ' · ' . $size,
            'variants' => [['label' => $size, 'price' => (float)$item['price']]],
            'imageUrl' => $imagePath,
            'stock' => 15,
            'source' => 'KSR GURU',
            'is_featured' => 0
        ];
    }
    $fallback = $result;
    return $fallback;
}

if (!function_exists('getFallbackProducts')) {
    function getFallbackProducts(): array {
        return getFallbackColdDrinksProducts();
    }
}

// Load products
$products = getColdDrinksProducts();
if (empty($products)) {
    $products = getFallbackColdDrinksProducts();
}

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>KSR Guru JI · Cold Drinks</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    /* ===== MODERN FRESH DESIGN ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { display: block; }
    .app-wrapper { max-width: 1400px; width: 100%; margin: 0 auto; display: block; }
    
    .search-panel { margin-bottom: 32px; }
    .search-bar { background: white; border-radius: 70px; padding: 6px 24px; display: flex; align-items: center; border: 1px solid #d1fae5; gap: 12px; margin-bottom: 24px; box-shadow: 0 6px 12px rgba(0,0,0,0.02); }
    .search-bar i { font-size: 1.2rem; color: #0284c7; }
    .search-bar input { flex: 1; border: none; padding: 15px 0; background: transparent; font-size: 1rem; outline: none; font-weight: 500; }
    
    .filter-row { overflow-x: auto; padding-bottom: 8px; scrollbar-width: thin; }
    .pill-group { display: flex; gap: 12px; }
    .pill { background: #ffffff; padding: 8px 24px; border-radius: 60px; font-weight: 600; font-size: 0.85rem; color: #0369a1; cursor: pointer; white-space: nowrap; border: 1px solid #bae6fd; transition: all 0.15s; }
    .pill.active { background: #0284c7; color: white; border-color: #0284c7; box-shadow: 0 6px 12px rgba(2,132,199,0.2); }
    
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin: 32px 0 48px; }
    .product-card { background: #ffffff; border-radius: 24px; padding: 22px 18px 24px; transition: all 0.25s ease; border: 1px solid #e5e7eb; box-shadow: 0 8px 16px rgba(0,0,0,0.02); display: flex; flex-direction: column; position: relative; }
    .product-card:hover { transform: translateY(-6px); border-color: #7dd3fc; box-shadow: 0 24px 36px -12px rgba(2,132,199, 0.12); }
    
    .product-img { width: 100%; height: 160px; background: transparent; display: flex; align-items: center; justify-content: center; font-size: 2.8rem; overflow: hidden; margin: 0 auto 16px; }
    .product-img img { width: 100%; height: 100%; object-fit: contain; }
    
    .product-title { font-weight: 800; font-size: 1.15rem; color: #0c4a6e; line-height: 1.3; }
    .product-title-kn { font-size: 0.75rem; color: #0284c7; margin: 5px 0 12px; font-weight: 500; }
    .product-description { font-size: 0.8rem; background: #f0f9ff; padding: 8px 12px; border-radius: 12px; color: #0369a1; margin: 8px 0 14px; line-height: 1.4; border-left: 3px solid #0284c7; }
    
    .price-row { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-top: auto; }
    .price { font-weight: 800; font-size: 1.5rem; color: #0284c7; letter-spacing: -0.02em; }
    .price small { font-size: 0.7rem; font-weight: 500; color: #38bdf8; }
    
    .descriptive-cart-btn { background: linear-gradient(100deg, #0284c7, #38bdf8); border: none; border-radius: 60px; padding: 12px 8px; font-weight: 700; font-size: 0.75rem; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; transition: 0.2s; box-shadow: 0 4px 12px rgba(2,132,199,0.25); }
    .descriptive-cart-btn i { font-size: 0.8rem; }
    .descriptive-cart-btn:hover { transform: scale(0.96); background: #0369a1; }
    
    .product-badge { position: absolute; top: 12px; left: 12px; background: #ef4444; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; z-index: 2; box-shadow: 0 4px 8px rgba(239,68,68,0.25); }
    .product-source-row { font-size: 0.72rem; color: #4b5563; margin: 0.5rem 0 0; display: flex; justify-content: space-between; width: 100%; border-top: 1px solid #f3f4f6; padding-top: 8px; font-weight: 500; gap: 4px; flex-wrap: wrap; }
    .product-source-row .stock-tag { font-weight: 600; color: #374151; }
    .product-source-row .ad-tag { border-radius: 4px; padding: 1px 4px; font-size: 0.65rem; font-weight: 700; }
    .product-source-row .ad-tag.sponsored { background: #fee2e2; color: #dc2626; }
    .product-source-row .ad-tag.trusted { background: #e0f2fe; color: #0369a1; }
    
    .design-credits { margin-top: 20px; text-align: center; font-size: 0.7rem; color: #38bdf8; }
    @media (max-width: 640px) { .product-grid { gap: 18px; } .product-img { aspect-ratio: 1/1; } .price { font-size: 1.3rem; } }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>
<body>
<div class="app-wrapper">
  <?php
  $base_url = '../';
  include __DIR__ . '/../includes/global-header.php';
  ?>
  
  <div class="search-panel">
    <div class="search-bar">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search cold drinks / ಹುಡುಕಿ" autocomplete="off">
    </div>
    <div class="sort-container" style="margin-bottom: 20px; text-align: right;">
        <select id="sortSelect" style="padding: 8px 16px; border-radius: 60px; border: 1px solid var(--border-color, #eee); font-family: inherit; font-size: 0.9rem; font-weight: 600; color: var(--text-dark, #333); outline: none;">
            <option value="default">Sort By - Default</option>
            <option value="price_low">Price: Low to High</option>
            <option value="price_high">Price: High to Low</option>
        </select>
    </div>
    <div class="filter-row">
      <div class="pill-group" id="filterPills">
        <div class="pill active" data-category="all">All Drinks / ಎಲ್ಲ</div>
        <div class="pill" data-category="cola">Cola / ಕೋಲಾ</div>
        <div class="pill" data-category="lemon-lime">Lemon-Lime / ನಿಂಬೆ</div>
        <div class="pill" data-category="orange">Orange / ಕಿತ್ತಳೆ</div>
        <div class="pill" data-category="mango">Mango / ಮಾವು</div>
        <div class="pill" data-category="energy">Energy / ಶಕ್ತಿ</div>
        <div class="pill" data-category="soda/jeera">Soda/Jeera / ಜೀರಿಗೆ</div>
        <div class="pill" data-category="local special">Desi Special / ಡಿಸಿ </div>
      </div>
    </div>
  </div>

  <div class="product-grid" id="productGrid"></div>
  
  <div class="design-credits">
    <i class="fa-regular fa-compass"></i> refreshing chilled collection ✦ glassmorphic modernization
  </div>
</div>

<script>
// ============================================================
// JAVASCRIPT - Modern Glassmorphic Category Grid
// ============================================================
(function() {
  const productsData = <?php
    $jsProducts = [];
    foreach ($products as $p) {
        $cat = strtolower($p['category']);
        
        $img = $p['imageUrl'] ?? '';
        $jsProducts[] = [
            'id' => $p['id'],
            'nameEn' => $p['name'],
            'kn' => $p['kn_name'] ?? $p['name'],
            'category' => $cat,
            'price' => (float)$p['price'],
            'mrp' => (float)($p['mrp'] ?? $p['price']),
            'image' => $img,
            'description' => $p['description_en'] ?? '',
            'buttonText' => '➕ Quick Chill',
            'source' => $p['source'] ?? 'KSR GURU',
            'stock' => (int)($p['stock'] ?? 10),
            'isFeatured' => (int)($p['is_featured'] ?? 0),
            'emoji' => $p['emoji'] ?? '🍹'
        ];
    }
    echo json_encode($jsProducts);
  ?>;

  let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
  let activeCategory = 'all';
  let searchTerm = '';
  let activeSort = 'default';
  
  const productGridEl = document.getElementById('productGrid');
  const cartCountSpan = document.getElementById('cartCount');
  const searchInput = document.getElementById('searchInput');
  const sortSelect = document.getElementById('sortSelect');
  const filterPills = document.querySelectorAll('.pill');

  function updateCartBadge() {
    const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
    if(cartCountSpan) cartCountSpan.innerText = totalQty;
    localStorage.setItem('glowCartNew', JSON.stringify(cart));
  }

  function addToCart(productId) {
    let product = productsData.find(p => p.id == productId);
    if (!product) return;

    const formData = new FormData();
    formData.append('id', productId);
    formData.append('qty', 1);
    formData.append('name', product.nameEn);
    formData.append('kn', product.kn);
    formData.append('price', product.price);
    formData.append('image', product.image);
    
    fetch('../cart/add_cart.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof window.cart !== "undefined") {
                window.cart = data.cart;
            }
            cart = data.cart;
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
                b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
            if (typeof openCart === "function") {
                openCart();
            }
        } else {
            alert(data.error || 'Failed to add product to cart');
        }
    })
    .catch(console.error);
  }

  function renderProducts() {
    let filtered = productsData.filter(p => {
      // Handle the complex category filtering logic
      if (activeCategory !== 'all') {
          const type = p.category;
          const map = {
              'cola': ['cola'],
              'lemon-lime': ['lemon', 'lime', 'sprite', '7up'],
              'orange': ['orange', 'fanta', 'mirinda'],
              'mango': ['mango', 'frooti', 'maaza', 'slice'],
              'apple': ['apple', 'appy'],
              'energy': ['energy', 'red bull', 'sting', 'monster'],
              'soda/jeera': ['soda', 'jeera', 'bindu'],
              'local special': ['local', 'sarbath', 'rose']
          };
          
          let matches = false;
          if (type.includes(activeCategory)) matches = true;
          
          if(!matches && map[activeCategory]) {
              map[activeCategory].forEach(kw => {
                  if (type.includes(kw) || p.nameEn.toLowerCase().includes(kw)) matches = true;
              });
          }
          if(!matches) return false;
      }
      const term = searchTerm.trim().toLowerCase();
      if (term) {
        return p.nameEn.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term);
      }
      return true;
    });

    if (activeSort === 'price_low') {
        filtered.sort((a,b) => a.price - b.price);
    } else if (activeSort === 'price_high') {
        filtered.sort((a,b) => b.price - a.price);
    }

    if (filtered.length === 0) {
      productGridEl.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#0284c7;">✨ No cold drinks found / ಯಾವುದೇ ಪಾನೀಯ ಇಲ್ಲ</div>`;
      return;
    }
    
    productGridEl.innerHTML = filtered.map(p => {
      const hasOffer = p.isFeatured == 1;
      const sponsoredClass = hasOffer ? 'sponsored' : 'trusted';
      const sponsoredLabel = hasOffer ? 'Sponsored' : 'Trusted';
      
      let imgHtml = '';
      if (p.image && p.image.trim() !== '') {
        imgHtml = `<img src="${p.image}" alt="" onerror="this.parentElement.textContent='${p.emoji}'">`;
      } else {
        imgHtml = `<span style="font-size:3rem;">${p.emoji}</span>`;
      }
      
      return `
        <div class="product-card" data-id="${p.id}">
          ${hasOffer ? `<div class="product-badge">OFFER</div>` : ''}
          <div class="product-img">${imgHtml}</div>
          <div class="product-title">${p.nameEn}</div>
          <div class="product-title-kn">${p.kn}</div>
          <div class="product-description"><i class="fa-solid fa-glass-water"></i> ${p.description}</div>
          <div class="price-row">
            <span class="price">₹${p.price.toFixed(2)} <small>inr</small></span>
            ${p.mrp > p.price ? `<span style="font-size:0.85rem;color:#7dd3fc;text-decoration:line-through;margin-left:6px;">₹${p.mrp.toFixed(2)}</span>` : ''}
          </div>
          <div class="product-source-row">
            <span>${p.source}</span>
            <span class="ad-tag ${sponsoredClass}">${sponsoredLabel}</span>
            <span>Stock: <span class="stock-tag">${p.stock}</span></span>
          </div>
          <button class="descriptive-cart-btn" data-id="${p.id}">
            <i class="fa-solid fa-snowflake"></i>
            <span>${p.buttonText}</span>
          </button>
        </div>
      `;
    }).join('');

    document.querySelectorAll('.descriptive-cart-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        addToCart(this.dataset.id);
        const orig = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check"></i> Chilled!';
        setTimeout(() => this.innerHTML = orig, 800);
      });
    });
  }

  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      filterPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeCategory = pill.dataset.category;
      renderProducts();
    });
  });

  searchInput.addEventListener('input', e => { searchTerm = e.target.value; renderProducts(); });
  sortSelect.addEventListener('change', e => { activeSort = e.target.value; renderProducts(); });

  renderProducts();

  // Setup Sidebar global handlers to override duplication issues
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachSidebarHandlers);
  } else {
    attachSidebarHandlers();
  }

  function attachSidebarHandlers() {
    const legacyOverlay = document.getElementById('cartOverlay');
    if (legacyOverlay) {
        legacyOverlay.style.display = 'none';
        legacyOverlay.style.pointerEvents = 'none';
    }
  }

})();
</script>


<script>
/* Injected Cart Drawer JavaScript Handlers */
  function openCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (typeof renderCartItems === "function") {
      renderCartItems();
    }
  }
  function closeCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  function updateCartQty(productId, qty) {
    const formData = new FormData();
    formData.append('id', productId);
    formData.append('qty', qty);
    fetch('../index.php?ajax=update_cart', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (typeof cart !== "undefined") {
              cart = data.cart;
          }
          const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
          badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
          });
          if (typeof renderCartItems === "function") {
              renderCartItems();
          }
        }
      })
      .catch(console.error);
  }
  function removeFromCart(productId) {
    const formData = new FormData();
    formData.append('id', productId);
    fetch('../index.php?ajax=remove_cart', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (typeof cart !== "undefined") {
              cart = data.cart;
          }
          const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
          badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
          });
          if (typeof renderCartItems === "function") {
              renderCartItems();
          }
        }
      })
      .catch(console.error);
  }
  function renderCartItems() {
    const container = document.getElementById('cartItems');
    const totalSpan = document.querySelector('.total-price');
    if (!container) return;
    const currentCart = (typeof cart !== "undefined") ? cart : [];
    if (!currentCart.length) {
      container.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
      if (totalSpan) totalSpan.innerText = '₹0';
      return;
    }
    let total = 0;
    let html = '';
    currentCart.forEach(item => {
      const itemTotal = item.price * item.qty;
      total += itemTotal;
      const itemImgSrc = item.image || '';
      const imgHtml = itemImgSrc ? 
        `<img src="${itemImgSrc}" alt="${item.name}" onerror="this.outerHTML='<span class=\'emoji-fallback\'>🥣</span>'" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />` : 
        `<span class="emoji-fallback">🥣</span>`;
      html += `
        <div class="cart-item" data-id="${item.id}">
          <div class="cart-item-image">${imgHtml}</div>
          <div class="cart-item-details">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₹${item.price} × ${item.qty} = ₹${itemTotal.toFixed(2)}</div>
            <div class="cart-item-actions">
              <button onclick="window.changeQty('${item.id}', ${item.qty - 1})">-</button>
              <span>${item.qty}</span>
              <button onclick="window.changeQty('${item.id}', ${item.qty + 1})">+</button>
              <button onclick="window.removeItem('${item.id}')"><i class="fas fa-trash-alt"></i></button>
            </div>
          </div>
        </div>
      `;
    });
    container.innerHTML = html;
    if (totalSpan) totalSpan.innerText = `₹${total.toFixed(2)}`;
  }
  function setupCartSidebarEvents() {
    const closeBtn = document.getElementById('closeCart');
    const overlay = document.getElementById('overlay');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const continueBtn = document.getElementById('continueShopping');
    if (closeBtn) closeBtn.addEventListener('click', closeCart);
    if (overlay) overlay.addEventListener('click', closeCart);
    if (continueBtn) continueBtn.addEventListener('click', closeCart);
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            const currentCart = (typeof cart !== "undefined") ? cart : [];
            if (currentCart.length) {
                window.location.href = '../checkout/checkout.php';
            } else {
                window.showFastCartToast ? window.showFastCartToast('Your cart is empty! Add items to proceed.') : alert('Cart is empty');
            }
        });
    }
    const headerCartBtns = document.querySelectorAll('.cart-link, #cartLink, #cartInfoIcon');
    headerCartBtns.forEach(btn => {
        const clone = btn.cloneNode(true);
        btn.parentNode.replaceChild(clone, btn);
        clone.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openCart();
        });
    });
    const legacyOverlay = document.getElementById('cartOverlay');
    if (legacyOverlay) {
        legacyOverlay.style.display = 'none';
        legacyOverlay.style.pointerEvents = 'none';
    }
    window.changeQty = updateCartQty;
    window.removeItem = removeFromCart;
    fetch('../index.php?ajax=cart')
      .then(res => res.json())
      .then(data => {
        if (data && typeof cart !== "undefined") {
            cart = data;
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
                b.innerText = cart ? cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
        }
      }).catch(console.error);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCartSidebarEvents);
  } else {
    setupCartSidebarEvents();
  }
</script>
<!-- Injected Cart Sidebar Markup -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
      <button class="close-cart" id="closeCart"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-footer">
      <div class="cart-total"><span>Total</span><span class="total-price">₹0</span></div>
      <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
      <button class="continue-shopping" id="continueShopping">Continue Shopping</button>
    </div>
</div>
<div class="overlay" id="overlay"></div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
</body>
</html>