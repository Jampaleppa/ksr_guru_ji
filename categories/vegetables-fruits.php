<?php
// ============================================================
// categories/vegetables-fruits.php - Dynamic Vegetables & Fruits page
// KSR GURU JI - Daily Market Rate & Automatic Selling Price (+₹3)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/market_prices.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
// Get cart count & total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => ($i['price'] ?? 0) * ($i['qty'] ?? 1), $_SESSION['cart']));

// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : 'Guest';

// ============================================================
// FETCH PRODUCTS - Load from daily_market_prices with automatic +₹3 pricing
// ============================================================
$productsData = [];
$todayDate = date('d M Y');

try {
    $marketRows = getDailyMarketPrices();
    if (!empty($marketRows)) {
        foreach ($marketRows as $row) {
            $sellingPrice = (float)$row['selling_price'];
            $marketPrice = (float)$row['market_price'];
            $margin = (float)($row['profit_margin'] ?? 3.00);
            $unitType = $row['unit_type'] ?? 'kg';
            $packetSizes = $row['packet_sizes'] ?? '100g,250g,500g,1kg';

            // Generate flexible measurement options for this product
            $options = getPurchaseOptionsForUnit($unitType, $sellingPrice, $packetSizes);

            $productsData[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'kannada' => $row['name_kn'] ?: $row['name'],
                'desc' => $row['description'] ?: 'Fresh farm produce',
                'marketPrice' => $marketPrice,
                'profitMargin' => $margin,
                'sellingPrice' => $sellingPrice,
                'unitType' => $unitType,
                'packetSizes' => $packetSizes,
                'category' => $row['category'] ?: 'indianMain',
                'imageUrl' => $row['image'] ?: '',
                'priceDate' => $row['price_date'] ?? date('Y-m-d'),
                'options' => $options
            ];
        }
    }
} catch (Throwable $e) {
    error_log("Failed to load daily market prices: " . $e->getMessage());
}

// Fallback if database had no rows
if (empty($productsData)) {
    $fallbackCatalog = getDefaultMarketCatalog();
    foreach ($fallbackCatalog as $idx => $item) {
        $market = (float)$item['market_price'];
        $selling = round($market + 3.00, 2);
        $unitType = $item['unit_type'] ?? 'kg';
        $packetSizes = $item['packet_sizes'] ?? '100g,250g,500g,1kg';
        $options = getPurchaseOptionsForUnit($unitType, $selling, $packetSizes);

        $productsData[] = [
            'id' => $idx + 1,
            'name' => $item['name'],
            'kannada' => $item['name_kn'],
            'desc' => $item['desc'],
            'marketPrice' => $market,
            'profitMargin' => 3.00,
            'sellingPrice' => $selling,
            'unitType' => $unitType,
            'packetSizes' => $packetSizes,
            'category' => $item['category'],
            'imageUrl' => $item['image'],
            'priceDate' => date('Y-m-d'),
            'options' => $options
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>KSR GURU JI – ತಾಜಾ ತರಕಾರಿ & ಹಣ್ಣುಗಳು | Daily Market Rates</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        :root {
            --bg-body: #f5fbf9;
            --bg-surface: #ffffff;
            --text-primary: #1b3b3a;
            --text-secondary: #2d4f4c;
            --text-muted: #54706c;
            --border-light: #d0e6e1;
            --card-shadow: 0 12px 24px -12px rgba(20, 80, 70, 0.2);
            --hover-shadow: 0 28px 36px -14px #1b6b61;
            --accent-teal: #1b7f74;
            --accent-soft: #bef0e8;
            --header-gradient: linear-gradient(145deg, #10635b, #23877c);
            --footer-bg: #0e544b;
            --tagline-color: #dcfff8;
            --cart-bg: #ffffff;
            --empty-cart-icon: #b6d9d2;
            --weight-bg: #f0f9f7;
        }

        body.dark-mode {
            --bg-body: #10211f;
            --bg-surface: #1b2f2c;
            --text-primary: #e0f0ed;
            --text-secondary: #c0dad5;
            --text-muted: #9bbbb5;
            --border-light: #38635c;
            --card-shadow: 0 10px 18px -6px #040a09;
            --hover-shadow: 0 24px 32px -10px black;
            --accent-teal: #44c2b2;
            --accent-soft: #25554d;
            --header-gradient: linear-gradient(145deg, #0d4941, #166b60);
            --footer-bg: #0a3f38;
            --tagline-color: #bdeae2;
            --cart-bg: #1d332f;
            --empty-cart-icon: #42736b;
            --weight-bg: #2a4943;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            transition: background-color 0.25s, color 0.2s;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 18px;
            width: 100%;
        }

        header {
            background: var(--header-gradient);
            padding: 1.2rem 1.8rem;
            border-radius: 0 0 38px 38px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 12px 22px rgba(0, 45, 35, 0.25);
            margin-bottom: 1.8rem;
            color: white;
            gap: 16px;
        }

        .logo-section {
            display: flex;
            flex-direction: column;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo i {
            font-size: 2.2rem;
            filter: drop-shadow(2px 4px 6px #02221e);
            color: #a7f3d0;
        }

        .tagline {
            font-size: 0.92rem;
            opacity: 0.95;
            color: var(--tagline-color);
            margin-top: 4px;
        }

        .daily-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #ffedd5;
            margin-top: 6px;
        }

        .user-controls {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }

        .cart-link,
        .theme-toggle {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(6px);
            border-radius: 40px;
            padding: 10px 18px;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }

        .cart-link:hover,
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: scale(1.02);
        }

        .cart-count {
            background: #ff9b71;
            color: #142f2b;
            font-weight: bold;
            border-radius: 40px;
            padding: 2px 12px;
            font-size: 0.95rem;
        }

        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 1.5rem 0 1.2rem;
            justify-content: center;
        }

        .tab {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            padding: 10px 22px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--text-secondary);
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: 0.2s;
        }

        .tab.active {
            background: var(--accent-teal);
            color: white;
            border-color: var(--accent-teal);
            box-shadow: 0 10px 20px -8px var(--accent-teal);
        }

        .tab:hover {
            transform: translateY(-2px);
        }

        .search-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 1.5rem 0 1.8rem;
        }

        .search-box {
            flex: 2 1 300px;
            display: flex;
            align-items: center;
            background: var(--bg-surface);
            border-radius: 56px;
            padding: 8px 22px;
            border: 1px solid var(--border-light);
            box-shadow: var(--card-shadow);
        }

        .search-box i {
            color: var(--accent-teal);
            font-size: 1.2rem;
            margin-right: 12px;
        }

        .search-box input {
            border: none;
            background: transparent;
            width: 100%;
            font-size: 1.05rem;
            padding: 8px 0;
            color: var(--text-primary);
            outline: none;
        }

        .filter-controls select {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            padding: 12px 36px 12px 20px;
            border-radius: 50px;
            font-size: 0.95rem;
            color: var(--text-primary);
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%231b7f74" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>');
            background-repeat: no-repeat;
            background-position: right 16px center;
        }

        .category-section {
            display: none;
            margin-bottom: 3rem;
        }

        .category-section.active {
            display: block;
        }

        .category-section > h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 1rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 3px solid var(--accent-teal);
            padding-bottom: 0.5rem;
        }

        .subcategory {
            margin: 2rem 0 1.2rem;
        }

        .subcategory h3 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--accent-teal);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.6rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: var(--bg-surface);
            border-radius: 28px;
            padding: 1.1rem 1rem 1.3rem;
            box-shadow: var(--card-shadow);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid var(--border-light);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--hover-shadow);
        }

        .unit-pill {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            background: #e0f2fe;
            color: #0369a1;
        }
        .unit-pill.kg { background: #dcfce7; color: #166534; }
        .unit-pill.piece { background: #fef3c7; color: #92400e; }
        .unit-pill.bunch { background: #ecfdf5; color: #065f46; }
        .unit-pill.dozen { background: #fee2e2; color: #991b1b; }
        .unit-pill.packet { background: #f3e8ff; color: #6b21a8; }

        .product-image {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-soft);
            margin-bottom: 0.7rem;
            background: #eef3f1;
        }

        .product-card h4 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.15rem;
        }

        .product-kannada {
            font-size: 0.85rem;
            color: var(--accent-teal);
            font-weight: 600;
            background: var(--accent-soft);
            padding: 0.15rem 0.7rem;
            border-radius: 30px;
            display: inline-block;
            margin: 0.2rem 0 0.3rem;
        }

        .product-desc {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            padding: 0 4px;
            line-height: 1.3;
        }

        .base-price-hint {
            font-size: 0.72rem;
            background: var(--weight-bg);
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 16px;
            margin: 4px 0;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .base-price-hint .market-sub {
            font-size: 0.65rem;
            color: #059669;
            display: block;
        }

        .unit-selector {
            width: 90%;
            margin: 8px 0 6px;
            padding: 7px 10px;
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            color: var(--text-primary);
        }

        .dynamic-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent-teal);
            margin: 6px 0;
        }

        .add-to-cart {
            background: var(--accent-teal);
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin-top: 6px;
            cursor: pointer;
            transition: 0.2s;
            width: 90%;
        }

        .add-to-cart:hover {
            background: #0f5f56;
            transform: scale(1.02);
        }

        footer {
            background: var(--footer-bg);
            color: white;
            padding: 2rem 1.8rem;
            border-radius: 44px 44px 0 0;
            margin-top: 3rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            font-size: 0.95rem;
        }

        @media (max-width: 600px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.9rem;
            }
            .product-image {
                width: 80px;
                height: 80px;
            }
            .dynamic-price {
                font-size: 1.25rem;
            }
            .add-to-cart, .unit-selector {
                width: 100%;
            }
            .product-card {
                padding: 0.8rem 0.6rem 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="../index.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <header>
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-seedling"></i>
                    <img src="../company_logo.jpg" alt="KSR GURU JI" class="ksr-logo-img" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.8);margin-right:8px;" onerror="this.style.display='none'">
                    <h1>KSR GURU JI Vegetables and Fruits</h1>
                </div>
                <p class="tagline">ಕೆ ಎಸ್ ಆರ್ ಗುರು ತಾಜಾ ತರಕಾರಿ & ಹಣ್ಣುಗಳು | Daily Market Rates + ₹3.00 Fair Price</p>
                <div class="daily-badge">
                    <i class="fas fa-check-circle"></i> Today's Fresh Rates (<?= $todayDate ?>): Market Price + ₹3
                </div>
            </div>
            <div class="user-controls">
                <a href="../cart/cart.php" class="cart-link" id="cartLink">
                    <i class="fas fa-basket-shopping"></i>
                    <span class="cart-count" id="cartCount"><?= $cartCount ?></span>
                </a>
                <button class="theme-toggle" id="themeToggle" title="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>

        <!-- Category Tabs -->
        <nav class="category-tabs">
            <button class="tab active" data-category="indian">🇮🇳 Indian Veg</button>
            <button class="tab" data-category="leafy">🥬 Leafy Greens (Bunches)</button>
            <button class="tab" data-category="fruits">🍎 Fresh Fruits</button>
            <button class="tab" data-category="daily">🧄 Herbs & Aromatics</button>
            <button class="tab" data-category="global">🌍 Global Veg & Exotic</button>
        </nav>

        <!-- Search & Filter Controls -->
        <div class="search-filter">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="ಹುಡುಕಿ (Tomato, Banana, ಸೇಬು, ಪಾಲಕ್...)" autocomplete="off">
            </div>
            <div class="filter-controls">
                <select id="sortSelect">
                    <option value="name">Sort by Name (A-Z)</option>
                    <option value="price-low">Price (Low to High)</option>
                    <option value="price-high">Price (High to Low)</option>
                </select>
            </div>
        </div>

        <!-- Main Product Sections -->
        <main>
            <!-- 1. Indian Veg -->
            <div class="category-section active" id="indian">
                <h2><i class="fas fa-pepper-hot"></i> Everyday Indian Vegetables (ದೈನಂದಿನ ತರಕಾರಿಗಳು)</h2>
                <div class="products-grid" id="indianMainGrid"></div>
            </div>

            <!-- 2. Leafy Greens -->
            <div class="category-section" id="leafy">
                <h2><i class="fas fa-leaf"></i> Indian Leafy Greens & Bunches (ಸೊಪ್ಪುಗಳು - ಕಟ್ಟುಗಳಲ್ಲಿ)</h2>
                <div class="products-grid" id="indianLeafyGrid"></div>
            </div>

            <!-- 3. Fresh Fruits -->
            <div class="category-section" id="fruits">
                <h2><i class="fas fa-apple-whole"></i> Fresh Fruits (ತಾಜಾ ಹಣ್ಣುಗಳು)</h2>
                <div class="subcategory">
                    <h3><i class="fas fa-crown"></i> Popular Indian Fruits</h3>
                    <div class="products-grid" id="indianFruitsGrid"></div>
                </div>
                <div class="subcategory">
                    <h3><i class="fas fa-globe"></i> Global & Health Fruits</h3>
                    <div class="products-grid" id="globalFruitsGrid"></div>
                </div>
            </div>

            <!-- 4. Daily Herbs & Aromatics -->
            <div class="category-section" id="daily">
                <h2><i class="fas fa-mortar-pestle"></i> Daily Herbs, Aromatics & Spices (ಮಸಾಲೆ & ಗಿಡಮೂಲಿಕೆಗಳು)</h2>
                <div class="products-grid" id="dailyHerbsGrid"></div>
            </div>

            <!-- 5. Global Veg & Exotic -->
            <div class="category-section" id="global">
                <h2><i class="fas fa-globe"></i> Global Veggies & Packaged Products (ವಿಶೇಷ ತರಕಾರಿಗಳು & ಪ್ಯಾಕೆಟ್ಗಳು)</h2>
                <div class="products-grid" id="globalGrid"></div>
            </div>
        </main>
    </div>

    <!-- Client-Side Product Data & Interactive Logic -->
    <script>
        (function() {
            // Master Product Dataset (includes daily market rates, units, and unit-specific options)
            const productsData = <?= json_encode($productsData, JSON_UNESCAPED_UNICODE) ?>;

            // Normalize product image paths
            productsData.forEach(p => {
                let imgUrl = p.imageUrl || '';
                if (imgUrl && !imgUrl.startsWith('http') && !imgUrl.startsWith('data:')) {
                    const idx = imgUrl.toLowerCase().indexOf('images');
                    if (idx !== -1) {
                        imgUrl = '../' + imgUrl.substring(idx).replace(/\\/g, '/');
                    } else if (!imgUrl.startsWith('../') && !imgUrl.startsWith('images/')) {
                        const cleanUrl = imgUrl.startsWith('/') ? imgUrl.substring(1) : imgUrl;
                        imgUrl = '../' + cleanUrl;
                    }
                }
                p.imageUrl = imgUrl;
            });

            // Cart State
            let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
            let currentSort = 'name';
            let currentSearch = '';

            // DOM References
            const containers = {
                indianMain: document.getElementById('indianMainGrid'),
                indianLeafy: document.getElementById('indianLeafyGrid'),
                indianFruits: document.getElementById('indianFruitsGrid'),
                globalFruits: document.getElementById('globalFruitsGrid'),
                dailyHerbs: document.getElementById('dailyHerbsGrid'),
                global: document.getElementById('globalGrid')
            };

            const cartCountSpan = document.getElementById('cartCount');
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const tabs = document.querySelectorAll('.tab');

            function updateCartBadge() {
                const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
                if (cartCountSpan) cartCountSpan.innerText = totalQty;
                const allBadges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
                allBadges.forEach(b => b.innerText = totalQty || "");
                localStorage.setItem('glowCartNew', JSON.stringify(cart));
            }

            // Add Product to Cart via AJAX with exact calculated unit price and variant
            function addToCart(productId, variantLabel, unitPrice, productName, description, imageUrl, kannadaName) {
                const formData = new FormData();
                formData.append('id', productId);
                formData.append('qty', 1);
                formData.append('variant', variantLabel);
                formData.append('price', unitPrice);
                formData.append('custom_price', unitPrice);
                formData.append('name', productName);
                formData.append('kn', kannadaName || '');
                formData.append('image', imageUrl || '');

                fetch('../cart/add_cart.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        cart = data.cart || cart;
                        updateCartBadge();
                        if (typeof openCart === "function") {
                            openCart();
                        }
                    } else {
                        alert(data.error || 'Failed to add product to cart');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error adding item to cart.");
                });
            }

            // Filter & Sort
            function getFilteredByCategory(categoryKey) {
                let filtered = [];

                if (categoryKey === 'indianMain') {
                    filtered = productsData.filter(p => p.category === 'indianMain');
                } else if (categoryKey === 'indianLeafy') {
                    filtered = productsData.filter(p => p.category === 'indianLeafy');
                } else if (categoryKey === 'indianFruits') {
                    filtered = productsData.filter(p => p.category === 'indianFruits');
                } else if (categoryKey === 'globalFruits') {
                    filtered = productsData.filter(p => p.category === 'globalFruits' || p.category === 'dailyFruits');
                } else if (categoryKey === 'dailyHerbs') {
                    filtered = productsData.filter(p => p.category === 'dailyHerbs');
                } else if (categoryKey === 'global') {
                    filtered = productsData.filter(p => p.category === 'global' || p.category === 'exotic');
                }

                if (currentSearch) {
                    const term = currentSearch.toLowerCase();
                    filtered = filtered.filter(p =>
                        p.name.toLowerCase().includes(term) ||
                        p.kannada.toLowerCase().includes(term) ||
                        p.desc.toLowerCase().includes(term)
                    );
                }

                if (currentSort === 'name') {
                    filtered.sort((a, b) => a.name.localeCompare(b.name));
                } else if (currentSort === 'price-low') {
                    filtered.sort((a, b) => a.sellingPrice - b.sellingPrice);
                } else if (currentSort === 'price-high') {
                    filtered.sort((a, b) => b.sellingPrice - a.sellingPrice);
                }

                return filtered;
            }

            // Render Product Grid
            function renderGrid(container, categoryKey) {
                if (!container) return;
                const itemsList = getFilteredByCategory(categoryKey);

                if (itemsList.length === 0) {
                    container.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem; color:var(--text-muted);">✨ ಯಾವುದೇ ತರಕಾರಿ ಅಥವಾ ಹಣ್ಣು ಸಿಗಲಿಲ್ಲ ✨</div>`;
                    return;
                }

                let html = '';
                itemsList.forEach(p => {
                    const defaultOption = p.options.find(o => o.is_default) || p.options[0];
                    const defaultPrice = defaultOption ? defaultOption.price : p.sellingPrice;
                    const unitPillClass = p.unitType || 'kg';
                    const unitLabel = p.unitType === 'piece' ? 'pc' : p.unitType;

                    html += `
                    <div class="product-card" data-pid="${p.id}">
                        <span class="unit-pill ${unitPillClass}">${unitLabel}</span>
                        <img src="${p.imageUrl}" alt="${p.name}" class="product-image" onerror="this.src='https://placehold.co/180x180?text=${encodeURIComponent(p.name)}'">
                        <h4>${p.name}</h4>
                        <span class="product-kannada">${p.kannada}</span>
                        <p class="product-desc">${p.desc}</p>

                        <div class="base-price-hint">
                            <span>₹${p.sellingPrice.toFixed(2)} / ${unitLabel}</span>
                            <span class="market-sub">Mkt: ₹${p.marketPrice.toFixed(2)} + ₹3.00</span>
                        </div>

                        <select class="unit-selector" data-pid="${p.id}">
                            ${p.options.map(opt => `
                                <option value="${opt.value}" data-label="${opt.label}" data-price="${opt.price}" ${opt === defaultOption ? 'selected' : ''}>
                                    ${opt.label} &mdash; ₹${opt.price.toFixed(2)}
                                </option>
                            `).join('')}
                        </select>

                        <div class="dynamic-price" id="price-${p.id}">₹${defaultPrice.toFixed(2)}</div>

                        <button class="add-to-cart" 
                            data-id="${p.id}" 
                            data-name="${p.name.replace(/['"]/g, '&quot;')}" 
                            data-kn="${p.kannada.replace(/['"]/g, '&quot;')}" 
                            data-desc="${p.desc.replace(/['"]/g, '&quot;')}" 
                            data-img="${p.imageUrl}">
                            <i class="fas fa-cart-plus"></i> Add
                        </button>
                    </div>`;
                });

                container.innerHTML = html;

                // Bind selector changes to update card price instantly
                container.querySelectorAll('.unit-selector').forEach(sel => {
                    const pid = parseInt(sel.dataset.pid);
                    const priceSpan = document.getElementById(`price-${pid}`);
                    sel.addEventListener('change', function() {
                        const selectedOpt = this.options[this.selectedIndex];
                        const price = parseFloat(selectedOpt.dataset.price) || 0;
                        if (priceSpan) {
                            priceSpan.innerText = '₹' + price.toFixed(2);
                        }
                    });
                });

                // Bind Add to Cart button clicks
                container.querySelectorAll('.add-to-cart').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const pid = parseInt(this.dataset.id);
                        const product = productsData.find(item => item.id === pid);
                        if (!product) return;

                        const card = this.closest('.product-card');
                        const selector = card.querySelector('.unit-selector');
                        const selectedOpt = selector.options[selector.selectedIndex];
                        const variantLabel = selectedOpt.dataset.label;
                        const unitPrice = parseFloat(selectedOpt.dataset.price);

                        addToCart(
                            pid,
                            variantLabel,
                            unitPrice,
                            product.name,
                            product.desc,
                            this.dataset.img,
                            product.kannada
                        );
                    });
                });
            }

            function renderAll() {
                renderGrid(containers.indianMain, 'indianMain');
                renderGrid(containers.indianLeafy, 'indianLeafy');
                renderGrid(containers.indianFruits, 'indianFruits');
                renderGrid(containers.globalFruits, 'globalFruits');
                renderGrid(containers.dailyHerbs, 'dailyHerbs');
                renderGrid(containers.global, 'global');
            }

            // Search & Sort Event Listeners
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    currentSearch = e.target.value.trim();
                    renderAll();
                });
            }

            if (sortSelect) {
                sortSelect.addEventListener('change', function(e) {
                    currentSort = e.target.value;
                    renderAll();
                });
            }

            // Tab Navigation
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    const targetCategory = this.dataset.category;

                    document.querySelectorAll('.category-section').forEach(sec => sec.classList.remove('active'));
                    const targetSec = document.getElementById(targetCategory);
                    if (targetSec) targetSec.classList.add('active');
                });
            });

            // Theme toggle
            const themeBtn = document.getElementById('themeToggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    document.body.classList.toggle('dark-mode');
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-moon');
                        icon.classList.toggle('fa-sun');
                    }
                });
            }

            // Cart Sidebar Drawer Functions
            function openCart() {
                const sidebar = document.getElementById('cartSidebar');
                const overlay = document.getElementById('overlay');
                if (sidebar) sidebar.classList.add('open');
                if (overlay) overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                if (typeof renderCartItems === "function") 
                    {
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
                            cart = data.cart;
                            updateCartBadge();
                            renderCartItems();
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
                            cart = data.cart;
                            updateCartBadge();
                            renderCartItems();
                        }
                    })
                    .catch(console.error);
            }

            function renderCartItems() {
                const container = document.getElementById('cartItems');
                const totalSpan = document.querySelector('.total-price');
                if (!container) return;

                if (!cart.length) {
                    container.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
                    if (totalSpan) totalSpan.innerText = '₹0';
                    return;
                }

                let total = 0;
                let html = '';
                cart.forEach(item => {
                    const price = parseFloat(item.price) || 0;
                    const qty = parseInt(item.qty) || 1;
                    const itemTotal = price * qty;
                    total += itemTotal;
                    const itemImg = item.image ? (item.image.startsWith('http') || item.image.startsWith('../') ? item.image : '../' + item.image) : '';

                    html += `
                    <div class="cart-item" data-id="${item.id}">
                        <div class="cart-item-image">
                            ${itemImg ? `<img src="${itemImg}" alt="${item.name}" onerror="this.outerHTML='<span class=\'emoji-fallback\'>🥣</span>'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />` : `<span class="emoji-fallback">🥣</span>`}
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">₹${price.toFixed(2)} × ${qty} = ₹${itemTotal.toFixed(2)}</div>
                            <div class="cart-item-actions">
                                <button onclick="window.changeQty('${item.id}', ${qty - 1})">-</button>
                                <span>${qty}</span>
                                <button onclick="window.changeQty('${item.id}', ${qty + 1})">+</button>
                                <button onclick="window.removeItem('${item.id}')"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    </div>`;
                });

                container.innerHTML = html;
                if (totalSpan) totalSpan.innerText = `₹${total.toFixed(2)}`;
            }

            function setupCartSidebarEvents() {
                const closeBtn = document.getElementById('closeCart');
                const overlay = document.getElementById('overlay');
                const continueBtn = document.getElementById('continueShopping');
                const checkoutBtn = document.getElementById('checkoutBtn');

                if (closeBtn) closeBtn.addEventListener('click', closeCart);
                if (overlay) overlay.addEventListener('click', closeCart);
                if (continueBtn) continueBtn.addEventListener('click', closeCart);
                if (checkoutBtn) {
                    checkoutBtn.addEventListener('click', function() {
                        if (cart.length) {
                            window.location.href = '../checkout/checkout.php';
                        } else {
                            alert('Your cart is empty! Add items to proceed.');
                        }
                    });
                }

                const headerCartBtns = document.querySelectorAll('.cart-link, #cartLink');
                headerCartBtns.forEach(btn => {
                    const clone = btn.cloneNode(true);
                    btn.parentNode.replaceChild(clone, btn);
                    clone.addEventListener('click', function(e) {
                        e.preventDefault();
                        openCart();
                    });
                });

                window.changeQty = updateCartQty;
                window.removeItem = removeFromCart;

                // Sync server session cart on load
                fetch('../index.php?ajax=cart')
                    .then(res => res.json())
                    .then(data => {
                        if (data && Array.isArray(data)) {
                            cart = data;
                            updateCartBadge();
                        }
                    })
                    .catch(console.error);
            }

            // Init
            renderAll();
            updateCartBadge();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupCartSidebarEvents);
            } else {
                setupCartSidebarEvents();
            }

            console.log('🌿 KSR GURU JI: Daily Market Rate Vegetables & Fruits loaded (' + productsData.length + ' produce items)');
        })();
    </script>

    <!-- Cart Sidebar Markup -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Your Cart (ಬುಟ್ಟಿ)</h3>
            <button class="close-cart" id="closeCart"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-footer">
            <div class="cart-total"><span>Total (ಒಟ್ಟು ಮೊತ್ತ)</span><span class="total-price">₹0</span></div>
            <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
            <button class="continue-shopping" id="continueShopping">Continue Shopping</button>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>

    <?php include __DIR__ . '/../includes/user-footer.php'; ?>
    <script src="sorting.js"></script>
</body>

</html>