<?php
require_once 'helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';

$pdo = getAssistDB();
$medicines = [];

try {
    // Attempt to load from the newly created 'medicines' catalog
    $stmt = $pdo->query("SELECT * FROM medicines LIMIT 50");
    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbProducts as $prod) {
        $productName = trim((string)($prod['brand_name'] ?? ($prod['name'] ?? ($prod['generic_name'] ?? 'Medicine'))));
        $genericName = trim((string)($prod['generic_name'] ?? ''));
        $strength = trim((string)($prod['strength'] ?? ''));
        $dosage = trim((string)($prod['dosage_form'] ?? ''));
        $mfg = trim((string)($prod['manufacturer'] ?? ''));
        
        $desc = trim((string)($prod['description'] ?? ''));
        if (empty($desc)) {
            $desc = trim("$genericName $strength - $mfg");
        }
        
        $isRx = !empty($prod['prescription_required']) || (stripos($productName, 'mg') !== false || stripos($desc, 'prescription') !== false);

        $img = $prod['image_path'] ?? ($prod['image_url'] ?? ($prod['image'] ?? ''));
        if (empty($img) || strpos($img, 'images/medicines/') !== false) {
            $img = 'https://ui-avatars.com/api/?name=' . urlencode($productName) . '&background=E0F2FE&color=0284C7&size=300&font-size=0.25&bold=true';
        }

        $medicines[] = [
            'id' => $prod['id'],
            'name' => htmlspecialchars($productName),
            'pack' => htmlspecialchars($prod['dosage_form'] ?? ($prod['unit'] ?? 'Standard Pack')),
            'price' => (float)($prod['current_price'] ?? ($prod['price'] ?? 0)) > 0 ? (float)($prod['current_price'] ?? $prod['price']) : 75.00,
            'description' => htmlspecialchars($desc),
            'rx' => $isRx,
            'image' => $img,
            'category' => htmlspecialchars($prod['medicine_type'] ?? 'General Medicine')
        ];
    }
} catch (Exception $e) {
    // DB or table might not be seeded yet
}

// Fallback if no real products found in the medicines table (user hasn't executed SQL yet)
if (empty($medicines)) {
    // Load from old products table as fallback
    try { $stmt = $pdo ? $pdo->query("SELECT * FROM products WHERE category_id = 12 OR category_id = 17 LIMIT 20") : null; } catch (Throwable $e) { $stmt = null; }
    $dbProducts = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($dbProducts as $prod) {
        $isRx = (stripos($prod['name'], 'mg') !== false);
        $images = !empty($prod['images']) ? json_decode($prod['images'], true) : [];
        $imagePath = (is_array($images) && count($images) > 0) ? '../../uploads/' . $images[0] : 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=300&q=80';

        $medicines[] = [
            'id' => $prod['id'],
            'name' => htmlspecialchars($prod['name']),
            'pack' => htmlspecialchars($prod['unit'] ?? 'Standard Pack'),
            'price' => (float)$prod['price'],
            'description' => htmlspecialchars($prod['description'] ?? ''),
            'rx' => $isRx,
            'image' => $imagePath,
            'category' => 'General Pharmacy'
        ];
    }
}
if (empty($medicines)) {
    try {
        $catDb = getCatalogueDB();
        $catStmt = $catDb->query("SELECT id, medicine_name AS name, strength, dosage_form, composition, selling_price FROM medicine_catalogue WHERE status = 1 LIMIT 20");
        foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $prod) {
            $productName = $prod['name'];
            $medicines[] = [
                'id' => $prod['id'],
                'name' => htmlspecialchars($productName),
                'pack' => htmlspecialchars($prod['dosage_form'] ?: 'Standard Pack'),
                'price' => (float)($prod['selling_price'] ?: 50.00),
                'description' => htmlspecialchars($prod['composition'] ?: $prod['strength']),
                'rx' => true,
                'image' => 'https://ui-avatars.com/api/?name=' . urlencode($productName) . '&background=E0F2FE&color=0284C7&size=300&font-size=0.25&bold=true',
                'category' => 'General Pharmacy'
            ];
        }
    } catch (Throwable $e) {}
}

// Check if we arrived from prescription bridge
$hasRx = isset($_GET['rx']) && $_GET['rx'] == 'true';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
            --radius-xl: 16px;
        }

        body {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .hero-banner {
            background: var(--gradient-primary);
            color: white;
            padding: 60px 0;
            text-align: center;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            margin-bottom: -40px;
        }

        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .hero-sub {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.15rem;
            font-weight: 500;
        }

        .layout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 32px;
            margin-bottom: 80px;
            position: relative;
            z-index: 10;
        }

        /* Left Side: Upload & Core Functions */
        .rx-upload-box {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-xl);
            padding: 32px;
            box-shadow: var(--shadow-glass);
            text-align: center;
            border: 2px dashed rgba(37, 99, 235, 0.3);
            transition: 0.3s;
            margin-bottom: 24px;
            position: relative;
        }

        .rx-upload-box:hover {
            border-color: var(--clr-primary);
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
        }

        .rx-icon {
            font-size: 3rem;
            color: var(--clr-primary);
            margin-bottom: 16px;
        }

        .rx-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--clr-text-primary);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .rx-desc {
            color: var(--clr-text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .uploaded-state {
            background: rgba(240, 253, 244, 0.7);
            border: 1px solid rgba(187, 247, 208, 0.7);
            padding: 16px;
            border-radius: var(--radius-sm);
            text-align: left;
        }

        .uploaded-file {
            font-weight: 600;
            color: #166534;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .uploaded-status {
            font-size: 0.85rem;
            color: #EA580C;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .auto-rx-match {
            background: rgba(238, 242, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(199, 210, 254, 0.8);
            padding: 24px;
            border-radius: var(--radius-xl);
            margin-bottom: 24px;
            box-shadow: var(--shadow-glass);
        }

        .arm-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--clr-primary-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .arm-list {
            margin-bottom: 16px;
        }

        .arm-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: var(--clr-text-secondary);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .arm-item i {
            color: #16A34A;
        }

        /* Right Side: Search & Products */
        .search-area {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: var(--shadow-glass);
            border: 1px solid var(--glass-border);
            margin-bottom: 32px;
        }

        .search-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-wrap i {
            position: absolute;
            left: 20px;
            color: var(--clr-text-secondary);
            font-size: 1.2rem;
        }

        .search-wrap input {
            width: 100%;
            padding: 18px 20px 18px 56px;
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-size: 1.1rem;
            font-family: inherit;
            transition: 0.3s;
            background: rgba(255, 255, 255, 0.5);
        }

        .search-wrap input:focus {
            background: white;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            outline: none;
        }

        .popular-tags {
            margin-top: 16px;
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 0.9rem;
            color: var(--clr-text-secondary);
            flex-wrap: wrap;
        }

        .tag {
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            color: var(--clr-text-primary);
            cursor: pointer;
            transition: 0.2s;
            font-weight: 600;
        }

        .tag:hover {
            background: white;
            color: var(--clr-primary);
            box-shadow: var(--shadow-glass);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .cat-box {
            background: var(--glass-bg);
            backdrop-filter: blur(8px);
            padding: 20px 12px;
            border-radius: var(--radius-xl);
            border: 1px solid var(--glass-border);
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: var(--shadow-glass);
        }

        .cat-box:hover {
            border-color: var(--clr-primary);
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.9);
        }

        .cat-emoji {
            font-size: 2.2rem;
            margin-bottom: 8px;
        }

        .cat-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--clr-text-secondary);
        }

        .prod-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 0 8px;
        }

        .prod-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--clr-text-primary);
            text-transform: uppercase;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
        }

        .prod-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-glass);
        }

        .prod-card:hover {
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.1);
            transform: translateY(-6px);
            background: white;
            border-color: var(--clr-primary-light);
        }

        .prod-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .prod-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .prod-name {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--clr-text-primary);
            margin-bottom: 6px;
        }

        .prod-pack {
            font-size: 0.85rem;
            color: var(--clr-text-secondary);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .prod-price {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--clr-primary-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prod-avail {
            font-size: 0.85rem;
            font-weight: 700;
            color: #16A34A;
            margin-bottom: 16px;
            background: #F0FDF4;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-table;
            width: max-content;
            border: 1px solid #BBF7D0;
        }

        .rx-req {
            font-size: 0.85rem;
            font-weight: 700;
            color: #DC2626;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #FEF2F2;
            padding: 4px 10px;
            border-radius: 12px;
            width: max-content;
            border: 1px solid #FECACA;
        }

        .btn-add {
            background: rgba(37, 99, 235, 0.08);
            color: var(--clr-primary);
            border: 1px solid rgba(37, 99, 235, 0.2);
            padding: 12px;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            margin-top: auto;
            font-size: 0.95rem;
        }

        .btn-add:hover {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        /* Product Details Modal Options */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 16px;
            backdrop-filter: blur(8px);
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(24px);
            width: 100%;
            max-width: 650px;
            border-radius: var(--radius-xl);
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2), inset 0 0 0 1px rgba(255, 255, 255, 0.6);
            overflow: hidden;
            transform: translateY(30px) scale(0.95);
            transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .modal-overlay.active .modal-card {
            transform: translateY(0) scale(1);
        }

        .modal-scroll {
            overflow-y: auto;
            padding: 28px;
        }

        .mdet-header {
            display: flex;
            gap: 28px;
            margin-bottom: 28px;
            padding-bottom: 28px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .mdet-img {
            width: 140px;
            height: 140px;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(0, 0, 0, 0.05);
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .mdet-info {
            flex: 1;
        }

        .c-sect {
            margin-bottom: 20px;
        }

        .c-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--clr-text-secondary);
            text-transform: uppercase;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 6px;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .c-content {
            font-size: 1rem;
            color: var(--clr-text-primary);
            line-height: 1.6;
            font-weight: 500;
        }

        /* Cart Drawer */
        .cart-toggle {
            position: relative;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: 0.3s;
        }

        .cart-toggle:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .cart-drawer {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(24px);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.1);
            z-index: 2000;
            transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            border-left: 1px solid rgba(255, 255, 255, 0.5);
        }

        .cart-drawer.active {
            right: 0;
        }

        .cart-header {
            padding: 28px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(248, 250, 252, 0.5);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .c-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .c-item:hover {
            transform: translateX(-4px);
            border-color: var(--clr-primary-light);
        }

        .c-qty {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(241, 245, 249, 0.8);
            border-radius: 50px;
            padding: 6px 16px;
        }

        .c-qty button {
            border: none;
            background: transparent;
            font-weight: 800;
            cursor: pointer;
            color: var(--clr-primary);
            font-size: 1.1rem;
            transition: 0.2s;
        }

        .c-qty button:hover {
            color: var(--clr-primary-dark);
            transform: scale(1.2);
        }

        .cart-footer {
            padding: 28px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            background: rgba(248, 250, 252, 0.8);
        }

        .cf-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 1.05rem;
        }

        @media (max-width: 900px) {
            .layout-container {
                grid-template-columns: 1fr;
                transform: none;
                margin-top: 24px;
            }

            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cart-drawer {
                width: 100%;
                right: -100%;
            }
        }
    </style>
</head>

<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = 'medicines'; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






    <nav class="navbar">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
            <div style="display:flex; align-items:center; gap:32px;">
                <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
                <div class="navbar-links" style="display: flex; gap: 24px;">
                    <a href="index.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Home</a>
                    <a href="find-doctor.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Find Doctor</a>
                    <a href="my-prescriptions.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Prescriptions</a>
                    <a href="pharmacy.php" style="color:var(--clr-primary); text-decoration:none; font-weight:600;">Pharmacy</a>
                    <a href="medicine/tablet.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Medicines</a>
                </div>
            </div>
            <div style="display: flex; gap: 16px;">
                <div class="cart-toggle" onclick="toggleCart()">
                    <i class="fas fa-shopping-cart"></i> Cart (2)
                </div>
                <a href="#" style="background:var(--clr-primary-light); color:var(--clr-primary); padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600;"><i class="fas fa-user-circle"></i></a>
            </div>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="hero-title">Online Pharmacy</div>
        <div class="hero-sub">Safe • Convenient • Prescription-Aware</div>
    </div>

    <div class="layout-container">

        <!-- LEFT SIDE -->
        <div>
            <!-- 3. Prescription Upload Block -->
            <?php if (!$hasRx): ?>
                <div class="rx-upload-box" id="upload-stage">
                    <div class="rx-icon"><i class="fas fa-file-prescription"></i></div>
                    <div class="rx-title">HAVE A PRESCRIPTION?</div>
                    <div class="rx-desc">Upload your prescription and let our pharmacy team verify the medicines before fulfillment.</div>
                    <button class="btn-action" style="width: 100%; border-radius: var(--radius-full); background: var(--clr-primary); color: white; border: none; padding: 14px; font-weight: 700; font-size: 1.05rem; cursor: pointer;" onclick="mockUpload()">📤 Upload Prescription</button>
                    <div style="margin-top: 16px; font-size: 0.85rem; color: #166534; font-weight: 600;"><i class="fas fa-lock"></i> Securely handled</div>
                </div>

                <div class="rx-upload-box" id="success-stage" style="display:none; border-color:#BBF7D0; background:#F0FDF4;">
                    <div class="rx-title" style="color:#166534; margin-bottom:16px;">Prescription uploaded ✓</div>
                    <div class="uploaded-state" style="background:white;">
                        <div class="uploaded-file"><i class="fas fa-file-pdf"></i> my_prescription.pdf</div>
                        <div class="uploaded-status">🟠 Pharmacist Verification Pending</div>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:16px;">
                        <button style="flex:1; padding:8px; border:1px solid var(--clr-border); background:white; border-radius:var(--radius-sm); cursor:pointer;">View</button>
                        <button style="flex:1; padding:8px; border:1px solid var(--clr-border); background:white; border-radius:var(--radius-sm); cursor:pointer;" onclick="location.reload()">Replace</button>
                    </div>
                </div>

            <?php else: ?>
                <!-- 8. Prescription Bridge Found -->
                <div class="auto-rx-match">
                    <div class="arm-title"><i class="fas fa-check-circle" style="color:#16A34A;"></i> YOUR PRESCRIPTION</div>
                    <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-bottom:12px;">Doctor: <?php echo htmlspecialchars($docName); ?><br>ID: RX-2026-X84B9A2</div>

                    <div class="arm-list">
                        <div class="arm-item"><i class="fas fa-check"></i> Pregabalin (75mg)</div>
                        <div class="arm-item"><i class="fas fa-check"></i> Baclofen (20mg)</div>
                    </div>

                    <button style="width: 100%; border-radius: var(--radius-full); background: var(--clr-primary); color: white; border: none; padding: 14px; font-weight: 700; font-size: 1.05rem; cursor: pointer;">Add Medicines to Cart</button>
                </div>
            <?php endif; ?>

            <!-- 11. Check Delivery -->
            <div style="background: white; border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm);">
                <div style="font-weight: 700; margin-bottom: 12px; font-size: 1.1rem; text-transform: uppercase; color: var(--clr-text-secondary);">Check Delivery</div>
                <div style="display: flex; gap: 8px;">
                    <input type="text" placeholder="Enter PIN Code" style="flex:1; padding: 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-family: inherit;">
                    <button style="padding: 12px 16px; background: var(--clr-primary-light); color: var(--clr-primary); border: 1px solid var(--clr-primary); border-radius: var(--radius-sm); font-weight: 700; cursor:pointer;" onclick="this.innerHTML='✓ Check'; document.getElementById('dl-msg').style.display='block';">Check</button>
                </div>
                <div id="dl-msg" style="display:none; margin-top:16px;">
                    <div style="color: #16A34A; font-weight: 700; font-size: 0.95rem; margin-bottom: 4px;"><i class="fas fa-map-marker-alt"></i> Delivery Available</div>
                    <div style="font-size: 0.85rem; color: var(--clr-text-secondary);">Estimated delivery: Today by 9:00 PM</div>
                </div>
            </div>

        </div>

        <!-- RIGHT SIDE -->
        <div>
            <!-- 2. Search -->
            <div class="search-area">
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 16px;">Find Your Medicines</div>
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search medicines, brands, health products...">
                </div>
                <div class="popular-tags">
                    <strong style="color:var(--clr-text-primary);">Popular:</strong>
                    <span class="tag">Paracetamol</span>
                    <span class="tag">Vitamins</span>
                    <span class="tag">Diabetes</span>
                    <span class="tag">Skin Care</span>
                </div>
            </div>

            <!-- 4. Categories -->
            <div class="category-grid">
                <div class="cat-box">
                    <div class="cat-emoji">💊</div>
                    <div class="cat-name">General</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">🫀</div>
                    <div class="cat-name">Heart</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">🩸</div>
                    <div class="cat-name">Diabetes</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">🧴</div>
                    <div class="cat-name">Skin</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">👶</div>
                    <div class="cat-name">Baby Care</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">🦷</div>
                    <div class="cat-name">Dental</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">💪</div>
                    <div class="cat-name">Wellness</div>
                </div>
                <div class="cat-box">
                    <div class="cat-emoji">🩹</div>
                    <div class="cat-name">First Aid</div>
                </div>
            </div>

            <!-- 5. Medicine Results Grid -->
            <div class="prod-header">
                <div class="prod-title">All Medicines</div>
                <select style="padding:8px 12px; border:1px solid var(--clr-border); border-radius:var(--radius-sm); font-family:inherit;">
                    <option>Relevance</option>
                    <option>Price: Low to High</option>
                </select>
            </div>

            <div class="product-grid">
                <?php foreach ($medicines as $m): ?>
                    <div class="prod-card" onclick="openMed(<?php echo $m['id']; ?>)" style="cursor:pointer;">
                        <img src="<?php echo $m['image']; ?>" class="prod-img">
                        <div class="prod-body">
                            <div class="prod-name"><?php echo $m['name']; ?></div>
                            <div class="prod-pack"><?php echo $m['pack']; ?></div>
                            <div class="prod-desc" style="font-size:0.8rem; color:var(--clr-text-secondary); margin-bottom:12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; height: 35px;"><?php echo $m['description']; ?></div>

                            <div class="prod-price">₹<?php echo number_format($m['price'], 2); ?></div>

                            <?php if ($m['rx']): ?>
                                <div class="rx-req"><i class="fas fa-prescription"></i> Prescription Required</div>
                            <?php else: ?>
                                <div class="prod-avail">🟢 Available</div>
                            <?php endif; ?>

                            <button class="btn-add" onclick="event.stopPropagation(); addToCart(<?php echo $m['id']; ?>, '<?php echo addslashes($m['name']); ?>', <?php echo $m['price']; ?>, <?php echo $m['rx'] ? 'true' : 'false'; ?>)">+ Add to Cart</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 6. & 7. MEDICINE DETAIL MODAL -->
    <div class="modal-overlay" id="med-modal">
        <div class="modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px; border-bottom:1px solid var(--clr-border);">
                <div style="font-weight:700;">MEDICINE DETAILS</div>
                <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="document.getElementById('med-modal').classList.remove('active')">&times;</button>
            </div>

            <div class="modal-scroll">
                <div class="mdet-header">
                    <img src="https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=300&q=80" class="mdet-img">
                    <div class="mdet-info">
                        <h2 style="font-size:1.5rem; font-weight:800; color:var(--clr-text-primary); margin-bottom:4px;">Paracetamol 500mg</h2>
                        <div style="color:var(--clr-text-secondary); margin-bottom:16px;">Brand: Generic</div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:0.9rem; margin-bottom:16px;">
                            <div><strong style="color:var(--clr-text-secondary);">Strength:</strong> 500mg</div>
                            <div><strong style="color:var(--clr-text-secondary);">Pack Size:</strong> 10 Tablets</div>
                            <div style="grid-column: 1/-1;"><strong style="color:var(--clr-text-secondary);">Manufacturer:</strong> Pharma Corp Ltd.</div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:1.5rem; font-weight:800; color:var(--clr-primary-dark);">₹25.00</div>
                                <div style="font-size:0.85rem; color:#16A34A; font-weight:600;">🟢 Available</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px; background:#F1F5F9; border-radius:var(--radius-full); padding:4px 8px;">
                                <button style="border:none; text-align:center; width:30px; height:30px; border-radius:50%; cursor:pointer;">-</button>
                                <span style="font-weight:700;">1</span>
                                <button style="border:none; text-align:center; width:30px; height:30px; border-radius:50%; background:white; cursor:pointer;">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. Medicine Info Blocks -->
                <div class="c-sect">
                    <div class="c-title">Composition</div>
                    <div class="c-content">Paracetamol (500mg)</div>
                </div>
                <div class="c-sect">
                    <div class="c-title">Uses</div>
                    <div class="c-content">Used for temporary relief of fever, minor aches, and pains.</div>
                </div>
                <div class="c-sect">
                    <div class="c-title">Storage</div>
                    <div class="c-content">Store in a cool and dry place away from direct sunlight.</div>
                </div>
                <div class="c-sect" style="background:#FEF2F2; border:1px solid #FECACA; padding:12px; border-radius:var(--radius-sm);">
                    <div class="c-title" style="color:#DC2626; border-color:#FECACA;">Prescription Warning</div>
                    <div class="c-content" style="color:#B91C1C; font-size:0.9rem;">
                        For Rx-flagged medicines in this interface, you will be required to upload a valid prescription during checkout. Verification is handled by registered pharmacists.
                    </div>
                </div>

                <button style="width:100%; border:none; background:var(--clr-primary); color:white; padding:16px; border-radius:var(--radius-md); font-weight:700; font-size:1.1rem; margin-top:24px; cursor:pointer;" onclick="alert('Added to Cart')"><i class="fas fa-shopping-cart"></i> Add to Cart</button>

            </div>
        </div>
    </div>

    <!-- 10. CART DRAWER -->
    <div class="cart-drawer" id="cart-drawer">
        <div class="cart-header">
            <div style="font-weight:800; font-size:1.2rem; color:var(--clr-primary-dark);" id="cart-count">MY CART (0)</div>
            <button style="background:transparent; border:none; font-size:1.5rem; cursor:pointer; color:var(--clr-text-secondary);" onclick="toggleCart()">&times;</button>
        </div>

        <div class="cart-items" id="cart-items">
            <!-- Renders Dynamically via LocalStorage -->
        </div>

        <div class="cart-footer">
            <div class="cf-row"><span>Subtotal</span> <span id="cart-subtotal">₹0.00</span></div>
            <div class="cf-row"><span>Delivery Fee</span> <span>₹15.00</span></div>
            <div class="cf-row" style="font-weight:800; font-size:1.25rem; color:var(--clr-primary-dark); margin-top:8px; border-top:1px dashed var(--clr-border); padding-top:12px;">
                <span>Total</span> <span id="cart-total">₹15.00</span>
            </div>

            <button style="width:100%; border:none; background:var(--clr-primary); color:white; padding:16px; border-radius:var(--radius-md); font-weight:700; font-size:1.1rem; margin-top:24px; cursor:pointer;" onclick="location.href='checkout.php'">Proceed to Checkout &rarr;</button>
        </div>
    </div>

    <script>
        // --- UI Logic ---
        function mockUpload() {
            document.getElementById('upload-stage').style.display = 'none';
            document.getElementById('success-stage').style.display = 'block';
        }

        function openMed(id) {
            document.getElementById('med-modal').classList.add('active');
        }

        function toggleCart() {
            const c = document.getElementById('cart-drawer');
            c.classList.toggle('active');
        }

        // --- State Persistence & Cart Logic ---
        let cartState = JSON.parse(localStorage.getItem('da_pharmacy_cart')) || {};

        function saveCart() {
            localStorage.setItem('da_pharmacy_cart', JSON.stringify(cartState));
            renderCart();
        }

        function addToCart(id, name, price, rx, qty = 1) {
            if (cartState[id]) {
                cartState[id].qty += qty;
            } else {
                cartState[id] = {
                    id,
                    name,
                    price,
                    rx,
                    qty
                };
            }
            alert('Added ' + name + ' to cart!');
            saveCart();
        }

        function updateQty(id, delta) {
            if (!cartState[id]) return;
            cartState[id].qty += delta;
            if (cartState[id].qty <= 0) {
                delete cartState[id];
            }
            saveCart();
        }

        function removeItem(id) {
            delete cartState[id];
            saveCart();
        }

        function renderCart() {
            const itemContainer = document.getElementById('cart-items');
            const countEl = document.getElementById('cart-count');
            const subtotalEl = document.getElementById('cart-subtotal');
            const totalEl = document.getElementById('cart-total');

            itemContainer.innerHTML = '';
            let count = 0;
            let subtotal = 0;

            for (const key in cartState) {
                const item = cartState[key];
                count += item.qty;
                subtotal += item.price * item.qty;

                const rxBadge = item.rx ? `<div style="font-size:0.8rem; color:#16A34A; margin-top:4px;"><i class="fas fa-check"></i> Rx Verified</div>` : '';

                const itemHTML = `
            <div class="c-item">
                <div style="flex:1;">
                    <div style="font-weight:700; color:var(--clr-text-primary);">${item.name}</div>
                    <div style="color:var(--clr-primary); font-weight:700; margin-top:4px;">₹${item.price}</div>
                    ${rxBadge}
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                    <i class="fas fa-trash-alt" style="color:var(--clr-text-secondary); cursor:pointer;" onclick="removeItem(${item.id})"></i>
                    <div class="c-qty">
                        <button onclick="updateQty(${item.id}, -1)">-</button> 
                        ${item.qty} 
                        <button onclick="updateQty(${item.id}, 1)">+</button>
                    </div>
                </div>
            </div>`;
                itemContainer.insertAdjacentHTML('beforeend', itemHTML);
            }

            const exactTotal = count > 0 ? (subtotal + 15) : 0;

            countEl.textContent = `MY CART (${count})`;
            subtotalEl.textContent = `₹${subtotal.toFixed(2)}`;
            totalEl.textContent = `₹${exactTotal.toFixed(2)}`;
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', renderCart);
    </script>

</body>

</html>