<?php
// ============================================================
// final-suggest.php - Product & Medical Suggestion Box
// ============================================================
require_once 'config.php';
require_once 'functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Create tables if they don't exist (on first run)
try {
    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS suggestions_products (
        id VARCHAR(36) PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        unit VARCHAR(50) NOT NULL,
        qty INT NOT NULL DEFAULT 1,
        note TEXT,
        payment VARCHAR(100),
        payment_status VARCHAR(50) DEFAULT 'Paid',
        upi_id VARCHAR(100),
        image_base64 MEDIUMTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS suggestions_medical (
        id VARCHAR(36) PRIMARY KEY,
        user_id INT NULL,
        patient_name VARCHAR(255) NOT NULL,
        age INT NOT NULL,
        gender VARCHAR(20) NOT NULL,
        shop_name VARCHAR(255) NOT NULL,
        symptoms TEXT,
        suggested_meds TEXT,
        diagnosis VARCHAR(255),
        date DATE,
        doctor_name VARCHAR(255),
        suggestion TEXT NOT NULL,
        payment VARCHAR(100),
        payment_status VARCHAR(50) DEFAULT 'Paid',
        upi_id VARCHAR(100),
        prescription_image MEDIUMTEXT,
        resolved BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';

    try {
        $pdo = getDBConnection();

        // ─── PRODUCTS ─────────────────────────────────────────────
        if ($action === 'products') {
            $method = $_SERVER['REQUEST_METHOD'];
            if ($method === 'GET') {
                // List products
                $stmt = $pdo->query("SELECT * FROM suggestions_products ORDER BY created_at DESC");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($products);
                exit;
            }
            if ($method === 'POST') {
                // Add or update product
                $id = $_POST['id'] ?? null;
                $name = trim($_POST['name'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $unit = $_POST['unit'] ?? 'Packet';
                $qty = (int) ($_POST['qty'] ?? 1);
                $note = trim($_POST['note'] ?? '');
                $payment = $_POST['payment'] ?? 'Cash on Delivery';
                $payment_status = $_POST['payment_status'] ?? 'Paid';
                $upi_id = trim($_POST['upi_id'] ?? '');
                $image_base64 = $_POST['image_base64'] ?? '';

                if (empty($name) || $price <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid name or price']);
                    exit;
                }

                if ($id) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE suggestions_products SET name=?, price=?, unit=?, qty=?, note=?, payment=?, payment_status=?, upi_id=?, image_base64=? WHERE id=?");
                    $stmt->execute([$name, $price, $unit, $qty, $note, $payment, $payment_status, $upi_id, $image_base64, $id]);
                    echo json_encode(['success' => true, 'id' => $id]);
                } else {
                    // Insert
                    $id = uniqid();
                    $stmt = $pdo->prepare("INSERT INTO suggestions_products (id, user_id, name, price, unit, qty, note, payment, payment_status, upi_id, image_base64) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $userId, $name, $price, $unit, $qty, $note, $payment, $payment_status, $upi_id, $image_base64]);
                    echo json_encode(['success' => true, 'id' => $id]);
                }
                exit;
            }
            if ($method === 'DELETE') {
                // Delete product
                parse_str(file_get_contents('php://input'), $data);
                $id = $data['id'] ?? null;
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM suggestions_products WHERE id=?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Missing ID']);
                }
                exit;
            }
        }

        // ─── MEDICAL ──────────────────────────────────────────────
        if ($action === 'medical') {
            $method = $_SERVER['REQUEST_METHOD'];
            if ($method === 'GET') {
                $stmt = $pdo->query("SELECT * FROM suggestions_medical ORDER BY created_at DESC");
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($records);
                exit;
            }
            if ($method === 'POST') {
                $id = $_POST['id'] ?? null;
                $patient_name = trim($_POST['patient_name'] ?? '');
                $age = (int) ($_POST['age'] ?? 0);
                $gender = $_POST['gender'] ?? '';
                $shop_name = trim($_POST['shop_name'] ?? '');
                $symptoms = trim($_POST['symptoms'] ?? '');
                $suggested_meds = trim($_POST['suggested_meds'] ?? '');
                $diagnosis = trim($_POST['diagnosis'] ?? '');
                $date = $_POST['date'] ?? date('Y-m-d');
                $doctor_name = trim($_POST['doctor_name'] ?? '');
                $suggestion = trim($_POST['suggestion'] ?? '');
                $payment = $_POST['payment'] ?? 'Cash on Delivery';
                $payment_status = $_POST['payment_status'] ?? 'Paid';
                $upi_id = trim($_POST['upi_id'] ?? '');
                $prescription_image = $_POST['prescription_image'] ?? '';
                $resolved = isset($_POST['resolved']) ? (int) $_POST['resolved'] : 0;

                if (empty($patient_name) || $age < 1 || empty($gender) || empty($shop_name) || empty($suggestion)) {
                    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                    exit;
                }

                if ($id) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE suggestions_medical SET patient_name=?, age=?, gender=?, shop_name=?, symptoms=?, suggested_meds=?, diagnosis=?, date=?, doctor_name=?, suggestion=?, payment=?, payment_status=?, upi_id=?, prescription_image=?, resolved=? WHERE id=?");
                    $stmt->execute([$patient_name, $age, $gender, $shop_name, $symptoms, $suggested_meds, $diagnosis, $date, $doctor_name, $suggestion, $payment, $payment_status, $upi_id, $prescription_image, $resolved, $id]);
                    echo json_encode(['success' => true, 'id' => $id]);
                } else {
                    $id = uniqid();
                    $stmt = $pdo->prepare("INSERT INTO suggestions_medical (id, user_id, patient_name, age, gender, shop_name, symptoms, suggested_meds, diagnosis, date, doctor_name, suggestion, payment, payment_status, upi_id, prescription_image, resolved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $userId, $patient_name, $age, $gender, $shop_name, $symptoms, $suggested_meds, $diagnosis, $date, $doctor_name, $suggestion, $payment, $payment_status, $upi_id, $prescription_image, $resolved]);
                    echo json_encode(['success' => true, 'id' => $id]);
                }
                exit;
            }
            if ($method === 'DELETE') {
                parse_str(file_get_contents('php://input'), $data);
                $id = $data['id'] ?? null;
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM suggestions_medical WHERE id=?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Missing ID']);
                }
                exit;
            }
        }

        // ─── SERVE IMAGE ──────────────────────────────────────────
        if ($action === 'serve_image') {
            $id = $_GET['id'] ?? null;
            $type = $_GET['type'] ?? ''; // 'product' or 'medical'
            if (!$id || !$type) {
                http_response_code(400);
                echo "Missing parameters";
                exit;
            }
            $table = ($type === 'product') ? 'suggestions_products' : 'suggestions_medical';
            $col = ($type === 'product') ? 'image_base64' : 'prescription_image';
            $stmt = $pdo->prepare("SELECT $col FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row[$col])) {
                $data = $row[$col];
                // Detect image type from base64 header
                if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
                    $ext = $matches[1];
                    $data = substr($data, strpos($data, ',') + 1);
                    $decoded = base64_decode($data);
                    header("Content-Type: image/$ext", true);
                    echo $decoded;
                } else {
                    // Assume JPEG
                    header("Content-Type: image/jpeg", true);
                    echo base64_decode($data);
                }
            } else {
                http_response_code(404);
                echo "Image not found";
            }
            exit;
        }

        echo json_encode(['error' => 'Invalid action']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get cart count for badge
$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>KSR GURU JI · Unified Suggestion Box</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="suggestion-box-style.css?v=1.2" />
</head>

<body>

    <!-- ─── IMAGE VIEWER ─── -->
    <div class="image-viewer" id="imageViewer" onclick="closeImageViewer()">
        <button class="close-viewer" onclick="closeImageViewer()">&times;</button>
        <img id="viewerImg" src="" alt="Enlarged view" />
    </div>

    <!-- ─── GLASS CONTAINER ─── -->
    <div class="glass-container">

        <!-- HEADER -->
        <header class="main-header" style="border-radius: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin: 10px 10px 20px 10px; padding: 20px 24px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(15, 123, 58, 0.04);">
            <!-- Left: Brand Logo Zone (Clickable to Home) -->
            <div class="logo-container" onclick="window.location.href='index.php'" style="cursor: pointer; display: flex; align-items: center; gap: 14px;">
                <div class="logo-image" style="width: 50px; height: 50px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 2px solid rgba(255, 255, 255, 0.8);">
                    <img src="company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';">
                </div>
                <div class="logo-text" style="display: flex; flex-direction: column;">
                    <span class="logo-brand" style="font-family: 'Poppins', sans-serif; font-size: 1.45rem; font-weight: 800; background: linear-gradient(135deg, #0f7b3a, #1ea85a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -0.5px; display: block; line-height: 1.1;">KSR GURU JI</span>
                    <span class="logo-tagline" style="font-family: 'Inter', sans-serif; font-size: 0.72rem; color: #5e6f63; font-weight: 600; display: block; line-height: 1.2;">Everything Your Family Needs, Every Day</span>
                </div>
            </div>

            <!-- Center: Title Zone -->
            <div class="branding" style="flex: 1; min-width: 250px; text-align: left; padding: 0 10px; border-left: 2px solid rgba(15, 123, 58, 0.15); margin-left: 10px;">
                <h1 style="font-family: 'Poppins', 'Inter', sans-serif; font-size: 1.5rem; font-weight: 800; color: #122116; letter-spacing: -0.01em; margin: 0; line-height: 1.2;">
                    <span style="background: linear-gradient(135deg, #0f7b3a, #19a557); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Suggestion Box</span>
                </h1>
                <div class="kannada-tag" style="font-family: 'Inter', sans-serif; font-size: 0.85rem; color: #e85d2c; font-weight: 600; margin-top: 3px; display: block; line-height: 1.2;">Product &amp; Medical · All in one</div>
            </div>

            <!-- Right: Actions -->
            <div class="header-actions" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" style="background: rgba(255,255,255,0.75); border: 1px solid rgba(15,123,58,0.15); width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="fas fa-moon" style="color:#0f7b3a;"></i></button>
                <a href="checkout/checkout.php" class="checkout-link" target="_blank" style="background: #e85d2c; color: white; padding: 10px 20px; border-radius: 50px; font-weight: 700; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(232,93,44,0.2); transition: all 0.2s ease; text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-credit-card"></i> Checkout
                </a>
                <a href="https://wa.me/918088584421?text=Hello%20KSR%20GURU%20JI" target="_blank" class="whatsapp-link" style="background: #25d366; color: white; padding: 10px 20px; border-radius: 50px; font-weight: 700; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37,211,102,0.2); transition: all 0.2s ease; text-decoration: none; font-size: 0.9rem;">
                    <i class="fab fa-whatsapp"></i> 8088584421
                </a>
                <div class="emergency-badge" id="emergencyTrigger" style="background: #dc2626; color: white; padding: 10px 20px; border-radius: 50px; font-weight: 700; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(220,38,38,0.2); cursor: pointer; transition: all 0.2s ease; font-size: 0.9rem;">
                    <i class="fas fa-triangle-exclamation"></i> Emergency · Doctor
                </div>

            </div>
        </header>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="product"><i class="fas fa-box"></i> Products</button>
            <button class="tab-btn" data-tab="medical"><i class="fas fa-notes-medical"></i> Medical</button>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: PRODUCTS -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div id="tab-product" class="tab-content active">
            <div class="dashboard">
                <!-- Form -->
                <div class="form-card">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Product Name <span class="req">*</span></label>
                        <input type="text" id="prodName" placeholder="e.g. Aashirvaad Atta" />
                    </div>
                    <div class="row-2col">
                        <div class="form-group"><label>Price (₹) <span class="req">*</span></label><input type="number"
                                id="prodPrice" placeholder="0.00" step="any" min="0.01" /></div>
                        <div class="form-group"><label>Unit</label><select id="prodUnit">
                                <option>Packet</option>
                                <option>KG</option>
                                <option>Gram</option>
                                <option>Liter</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label>Quantity</label><input type="number" id="prodQty" value="1"
                            min="1" /></div>
                    <div class="form-group"><label>Note</label><textarea id="prodNote" rows="2"
                            placeholder="Door Delivery, Express Shipping..."></textarea></div>

                    <input type="hidden" id="prodPayment" value="Cash on Delivery" />


                    <!-- Image -->
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="upload-zone">
                            <button type="button" class="upload-btn" id="prodUploadBtn"><i
                                    class="fas fa-cloud-upload-alt"></i> Choose image</button>
                            <input type="file" id="prodImage" accept="image/png,image/jpeg,image/webp"
                                style="display:none;" />
                            <img id="prodPreview" class="preview-img" alt="Product preview" />
                            <span id="prodUploadStatus" class="upload-status missing"><i
                                    class="fas fa-exclamation-circle"></i> No image</span>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn-primary" id="prodAddBtn"><i class="fas fa-plus-circle"></i> Add
                            Product &amp; WhatsApp</button>
                        <button type="button" class="btn-outline" id="prodClearBtn"><i class="fas fa-trash-alt"></i>
                            Clear All</button>
                        <button type="button" class="btn-cancel-edit" id="prodCancelBtn" style="display:none;"><i
                                class="fas fa-times-circle"></i> Cancel Edit</button>
                    </div>
                </div>

                <!-- List -->
                <div class="list-card">
                    <div
                        style="display:flex;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:8px;">
                        <h3><i class="fas fa-list-ul"></i> Inventory</h3>
                        <span style="font-size:0.7rem;background:#eef2ff;padding:4px 14px;border-radius:40px;"><i
                                class="fas fa-save"></i> auto-saved</span>
                    </div>
                    <div class="search-box">
                        <input type="search" id="prodSearch" placeholder="🔍 Search products..."
                            aria-label="Search products" />
                        <div class="sort-options">
                            <select id="prodSort">
                                <option value="name">Name A-Z</option>
                                <option value="name-desc">Name Z-A</option>
                                <option value="price">Price Low-High</option>
                                <option value="price-desc">Price High-Low</option>
                                <option value="newest">Newest First</option>
                            </select>
                        </div>
                        <div class="export-btns">
                            <button type="button" id="prodExportCSV"><i class="fas fa-file-csv"></i> CSV</button>
                            <button type="button" id="prodPrint"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                    <div id="prodGrid" class="record-grid">
                        <div class="empty-state"><i class="fas fa-box-open"></i>
                            <p>No products yet.</p>
                        </div>
                    </div>
                    <div class="pagination-wrap" id="prodPaginationWrap">
                        <div class="pagination-info" id="prodPaginationInfo">Showing 0-0 of 0</div>
                        <div class="pagination-controls" id="prodPaginationControls"></div>
                    </div>
                    <div class="total-summary">
                        <span class="stat"><i class="fas fa-cubes"></i> Total Items <span
                                id="prodTotalItems">0</span></span>
                        <span class="stat"><i class="fas fa-coins"></i> Grand Total ₹<span
                                id="prodTotalAmount">0</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: MEDICAL -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div id="tab-medical" class="tab-content">
            <div class="dashboard">
                <!-- Form -->
                <div class="form-card">
                    <!-- Doctor Assist Banner -->

                    <form id="medForm" novalidate>
                        <div class="form-group"><label>Patient Name <span class="req">*</span></label><input type="text"
                                id="medPatient" placeholder="e.g. Ravi Kumar" required /></div>
                        <div class="row-2col">
                            <div class="form-group"><label>Age <span class="req">*</span></label><input type="number"
                                    id="medAge" placeholder="45" min="1" max="120" required /></div>
                            <div class="form-group"><label>Gender <span class="req">*</span></label><select
                                    id="medGender" required>
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Other</option>
                                </select></div>
                        </div>
                        <div class="form-group"><label>Medical Shop <span class="req">*</span></label><input type="text"
                                id="medShop" placeholder="e.g. Apollo Pharmacy" required /></div>
                        <div class="row-2col">
                            <div class="form-group"><label>Symptoms</label><textarea id="medSymptoms" rows="2"
                                    placeholder="fever, cough..."></textarea></div>
                            <div class="form-group"><label>Tablets Name</label><textarea id="medMeds" rows="2"
                                    placeholder="Paracetamol 650mg..."></textarea></div>
                        </div>
                        <div class="row-2col">
                            <div class="form-group"><label>Diagnosis</label><input type="text" id="medDiagnosis"
                                    placeholder="Viral Fever" /></div>
                            <div class="form-group"><label>Date</label><input type="date" id="medDate" /></div>
                        </div>
                        <div class="form-group"><label>Doctor Name</label><input type="text" id="medDoctor"
                                placeholder="Dr. S. Sharma" /></div>
                        <div class="form-group"><label>Medical Suggestion <span class="req">*</span></label><textarea
                                id="medSuggestion" rows="2" placeholder="Describe health concern…" required></textarea>
                        </div>

                        <input type="hidden" id="medPayment" value="Cash on Delivery" />


                        <!-- Prescription -->
                        <div class="form-group">
                            <label>Upload Prescription <span class="req">*</span></label>
                            <div class="upload-zone">
                                <button type="button" class="upload-btn mandatory" id="medUploadBtn"><i
                                        class="fas fa-cloud-upload-alt"></i> Choose Prescription</button>
                                <input type="file" id="medPrescription" accept="image/png,image/jpeg,image/webp"
                                    style="display:none;" />
                                <img id="medPreview" class="preview-img" alt="Prescription preview" />
                                <span id="medUploadStatus" class="upload-status missing"><i
                                        class="fas fa-exclamation-circle"></i> Required</span>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-primary" id="medSubmitBtn"><i
                                    class="fas fa-paper-plane"></i> Submit &amp; WhatsApp</button>
                            <button type="button" class="btn-outline" id="medClearFormBtn"><i class="fas fa-eraser"></i>
                                Clear</button>
                            <button type="button" class="btn-cancel-edit" id="medCancelBtn" style="display:none;"><i
                                    class="fas fa-times-circle"></i> Cancel Edit</button>
                        </div>
                        <div style="margin-top:12px;">
                            <a href="WhatsApp/telehealth/index.php?triage=1" class="btn-emergency" style="text-decoration:none; display:inline-flex; width:100%;"><i
                                    class="fas fa-ambulance"></i> 🚨 Emergency · Request Doctor</a>
                        </div>

                    </form>
                </div>

                <!-- List -->
                <div class="list-card">
                    <div
                        style="display:flex;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:8px;">
                        <h3><i class="fas fa-clock-rotate-left"></i> Medical History</h3>
                        <span class="badge-count" id="medRecordCount">0 records</span>
                    </div>
                    <div class="search-box">
                        <input type="search" id="medSearch" placeholder="🔍 Search medical records..."
                            aria-label="Search medical records" />
                        <div class="sort-options">
                            <select id="medSort">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="patient">Patient A-Z</option>
                            </select>
                        </div>
                        <div class="export-btns">
                            <button type="button" id="medExportCSV"><i class="fas fa-file-csv"></i> CSV</button>
                            <button type="button" id="medPrint"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                    <div id="medGrid" class="record-grid">
                        <div class="empty-state"><i class="fas fa-file-prescription"></i>
                            <p>No medical suggestions yet.</p>
                        </div>
                    </div>
                    <div class="pagination-wrap" id="medPaginationWrap">
                        <div class="pagination-info" id="medPaginationInfo">Showing 0-0 of 0</div>
                        <div class="pagination-controls" id="medPaginationControls"></div>
                    </div>
                    <div class="total-summary">
                        <span class="stat"><i class="fas fa-prescription"></i> Total <span id="medTotal">0</span></span>
                        <span class="stat"><i class="fas fa-user-md"></i> Pending <span id="medPending">0</span></span>
                        <span class="stat"><i class="fas fa-phone"></i> WhatsApp Ready</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  HELPERS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function showToast(msg, isError = false) {
            document.querySelectorAll('.toast-msg').forEach(t => t.remove());
            const t = document.createElement('div');
            t.className = 'toast-msg';
            t.textContent = msg;
            t.style.backgroundColor = isError ? '#b91c1cdd' : '#132b3cdd';
            document.body.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                setTimeout(() => t.remove(), 400);
            }, 2400);
        }

        function formatINR(amount) {
            return new Intl.NumberFormat('en-IN', {
                style: 'currency', currency: 'INR', minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        function generateId() {
            return (crypto.randomUUID && crypto.randomUUID()) || Date.now().toString(36) + Math.random().toString(36).slice(2);
        }

        function getWhatsAppNumber() {
            return '918088584421'; // default, can be overridden
        }

        function openImageViewer(src) {
            const viewer = document.getElementById('imageViewer');
            const img = document.getElementById('viewerImg');
            img.src = src;
            viewer.classList.add('active');
        }

        function closeImageViewer() {
            document.getElementById('imageViewer').classList.remove('active');
        }

        function mapPaymentMethod(val) {
            const map = {
                'Cash on Delivery': 'cod',
                'QR Scan & Pay': 'qr',
                'UPI ID': 'upi',
                'Google Pay': 'gpay',
                'PhonePe': 'phonepe',
                'Paytm': 'paytm',
                'BHIM UPI': 'bhim',
                'Amazon Pay': 'amazonpay'
            };
            return map[val] || 'qr';
        }

        function setCheckoutPaymentMethod(methodVal) {
            const mapped = mapPaymentMethod(methodVal);
            localStorage.setItem('selectedPaymentMethod', mapped);
        }

        function addProductToSuggestionCart(product) {
            const cartKey = 'suggestionCart';
            let cart = [];
            try {
                cart = JSON.parse(localStorage.getItem(cartKey)) || [];
                if (!Array.isArray(cart)) cart = [];
            } catch (e) {
                cart = [];
            }
            
            const cartItem = {
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                qty: parseInt(product.qty) || 1,
                image: (product.image_base64 && product.image_base64.length > 100) ? product.image_base64 : '',
                variant: product.unit || 'Packet',
                description: product.note || '',
                storeName: 'Suggestion'
            };
            
            const existingIdx = cart.findIndex(item => String(item.id) === String(product.id));
            if (existingIdx > -1) {
                cart[existingIdx] = cartItem;
            } else {
                cart.push(cartItem);
            }
            
            localStorage.setItem(cartKey, JSON.stringify(cart));
        }

        function addMedicalToSuggestionCart(rec) {
            const cartKey = 'suggestionCart';
            let cart = [];
            try {
                cart = JSON.parse(localStorage.getItem(cartKey)) || [];
                if (!Array.isArray(cart)) cart = [];
            } catch (e) {
                cart = [];
            }
            
            const cartItem = {
                id: rec.id,
                name: `Medical: ${rec.patient_name} (${rec.shop_name})`,
                price: 1.00,
                qty: 1,
                image: rec.prescription_image || '',
                variant: `${rec.gender}, ${rec.age}y`,
                description: `Doctor: ${rec.doctor_name || 'N/A'}. Suggestion: ${rec.suggestion}`,
                storeName: 'Suggestion'
            };
            
            const existingIdx = cart.findIndex(item => String(item.id) === String(rec.id));
            if (existingIdx > -1) {
                cart[existingIdx] = cartItem;
            } else {
                cart.push(cartItem);
            }
            localStorage.setItem(cartKey, JSON.stringify(cart));
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  TABS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                const tab = this.dataset.tab;
                if (tab === 'product') { if (window.triggerProductSearch) window.triggerProductSearch(); }
                if (tab === 'medical') { if (window.triggerMedicalSearch) window.triggerMedicalSearch(); }
            });
        });

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  THEME TOGGLE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        document.getElementById('themeToggle').addEventListener('click', function () {
            document.body.classList.toggle('dark');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark')) {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
            localStorage.setItem('ksr_theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        });

        if (localStorage.getItem('ksr_theme') === 'dark') {
            document.body.classList.add('dark');
            document.getElementById('themeToggle').querySelector('i').className = 'fas fa-sun';
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  PAYMENT & CHECKOUT SYNC
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function mapPaymentMethod(val) {
            switch (val) {
                case 'Cash on Delivery': return 'cod';
                case 'QR Scan & Pay': return 'qr';
                case 'UPI ID': return 'upi';
                case 'Google Pay': return 'gpay';
                case 'PhonePe': return 'phonepe';
                case 'Paytm': return 'paytm';
                case 'BHIM UPI': return 'bhim';
                case 'Amazon Pay': return 'amazonpay';
                default: return 'qr';
            }
        }

        function updateCheckoutUrl() {
            // Checkout page payment selection handles the selection directly
        }

        // Add listeners
        document.addEventListener('DOMContentLoaded', () => {
            // No payment selection sync required to checkout
        });

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  PRODUCT MODULE (AJAX version)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        (function () {
            let products = [];
            let editingId = null;
            let currentImage = '';
            let filteredProducts = [];
            let prodPage = 1,
                prodPerPage = 8;

            const DEFAULT_IMG = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23F2F6FC'/%3E%3Ctext x='50' y='55' font-size='12' text-anchor='middle' fill='%237F9FBF'%3E📦%3C/text%3E%3C/svg%3E";

            const nameInp = document.getElementById('prodName');
            const priceInp = document.getElementById('prodPrice');
            const unitSel = document.getElementById('prodUnit');
            const qtyInp = document.getElementById('prodQty');
            const noteInp = document.getElementById('prodNote');
            const paymentSel = document.getElementById('prodPayment');
            const paymentStatusSel = document.getElementById('prodPaymentStatus');
            const upiIdInp = document.getElementById('prodUpiId');
            const imageInput = document.getElementById('prodImage');
            const previewImg = document.getElementById('prodPreview');
            const uploadStatus = document.getElementById('prodUploadStatus');
            const grid = document.getElementById('prodGrid');
            const totalItems = document.getElementById('prodTotalItems');
            const totalAmount = document.getElementById('prodTotalAmount');
            const addBtn = document.getElementById('prodAddBtn');
            const clearBtn = document.getElementById('prodClearBtn');
            const cancelBtn = document.getElementById('prodCancelBtn');
            const searchInput = document.getElementById('prodSearch');
            const sortSelect = document.getElementById('prodSort');
            const pagInfo = document.getElementById('prodPaginationInfo');
            const pagControls = document.getElementById('prodPaginationControls');

            // ─── Image handling ───
            document.getElementById('prodUploadBtn').addEventListener('click', () => imageInput.click());

            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    showToast('⚠️ Only image files allowed.', true);
                    this.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('⚠️ Image must be less than 5 MB.', true);
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    currentImage = ev.target.result;
                    previewImg.src = currentImage;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                };
                reader.readAsDataURL(file);
            });

            function resetImage() {
                currentImage = '';
                previewImg.src = '';
                previewImg.classList.remove('show');
                imageInput.value = '';
                uploadStatus.className = 'upload-status missing';
                uploadStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> No image';
            }

            function resetForm() {
                nameInp.value = '';
                priceInp.value = '';
                unitSel.value = 'Packet';
                qtyInp.value = '1';
                noteInp.value = '';
                paymentSel.value = 'Cash on Delivery';
                if (paymentStatusSel) paymentStatusSel.value = 'Paid';
                if (upiIdInp) upiIdInp.value = '';
                resetImage();
            }

            // ─── Load from server ───
            function loadProducts() {
                fetch('final-suggest.php?ajax=products')
                    .then(res => res.json())
                    .then(data => {
                        products = data;
                        filteredProducts = [...products];
                        render();
                    })
                    .catch(err => {
                        showToast('⚠️ Error loading products', true);
                        console.error(err);
                    });
            }

            // ─── Save to server (add/update) ───
            function saveProduct(productData, isUpdate = false) {
                const formData = new FormData();
                formData.append('name', productData.name);
                formData.append('price', productData.price);
                formData.append('unit', productData.unit);
                formData.append('qty', productData.qty);
                formData.append('note', productData.note || '');
                formData.append('payment', productData.payment || 'Cash on Delivery');
                formData.append('payment_status', productData.paymentStatus || 'Paid');
                formData.append('upi_id', productData.upiId || '');
                formData.append('image_base64', productData.imgBase64 || '');
                if (isUpdate && productData.id) {
                    formData.append('id', productData.id);
                }
                return fetch('final-suggest.php?ajax=products', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            loadProducts(); // refresh list
                            return data;
                        } else {
                            showToast('⚠️ Error saving: ' + (data.error || 'unknown'), true);
                            return data;
                        }
                    });
            }

            function deleteProduct(id) {
                return fetch('final-suggest.php?ajax=products', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            loadProducts();
                            showToast('🗑️ Removed', false);
                        } else {
                            showToast('⚠️ Delete failed', true);
                        }
                    });
            }

            // ─── Sort ───
            function sortProducts(list, sortKey) {
                const copy = [...list];
                switch (sortKey) {
                    case 'name': return copy.sort((a, b) => a.name.localeCompare(b.name));
                    case 'name-desc': return copy.sort((a, b) => b.name.localeCompare(a.name));
                    case 'price': return copy.sort((a, b) => a.price - b.price);
                    case 'price-desc': return copy.sort((a, b) => b.price - a.price);
                    case 'newest': return copy.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    default: return copy;
                }
            }

            // ─── Render ───
            function render() {
                const sortKey = sortSelect.value;
                const sorted = sortProducts(filteredProducts, sortKey);
                const total = sorted.length;
                const totalPages = Math.ceil(total / prodPerPage) || 1;
                if (prodPage > totalPages) prodPage = totalPages;
                const start = (prodPage - 1) * prodPerPage;
                const page = sorted.slice(start, start + prodPerPage);

                if (total === 0) {
                    grid.innerHTML = `<div class="empty-state"><i class="fas fa-box-open"></i><p>No products found.</p></div>`;
                    totalItems.textContent = '0';
                    totalAmount.textContent = '0';
                    pagInfo.textContent = 'Showing 0-0 of 0';
                    pagControls.innerHTML = '';
                    return;
                }

                let units = 0,
                    totalAmt = 0;
                let html = '';
                page.forEach(p => {
                    const itemTotal = p.price * p.qty;
                    units += p.qty;
                    totalAmt += itemTotal;
                    const statusEmoji = p.payment_status === 'Paid' ? '✅' :
                        p.payment_status === 'Unpaid' ? '❌' :
                            p.payment_status === 'Pending' ? '🟡' : '🟠';
                    const imgSrc = (p.image_base64 && p.image_base64.length > 100) ? p.image_base64 : DEFAULT_IMG;
                    html += `
                        <div class="record-card">
                            <img class="card-img" loading="lazy" decoding="async" src="${imgSrc}" alt="${escapeHtml(p.name)}" onerror="this.src='${DEFAULT_IMG}'" onclick="openImageViewer('${imgSrc}')" style="cursor:pointer;" />
                            <div class="rec-name">${escapeHtml(p.name)}</div>
                            <div class="rec-meta"><i class="fas fa-tag"></i> ${formatINR(p.price)} · ${p.unit} · Qty: ${p.qty}</div>
                            ${p.note ? `<div class="rec-tag"><i class="fas fa-comment"></i> ${escapeHtml(p.note.substring(0, 40))}</div>` : ''}
                            <div class="rec-actions">
                                <button class="rec-checkout" data-id="${p.id}" style="background:#10b981; color:#fff;"><i class="fas fa-credit-card"></i> Checkout</button>
                                <button class="rec-edit" data-id="${p.id}"><i class="fas fa-edit"></i> Edit</button>
                                <button class="rec-delete" data-id="${p.id}"><i class="fas fa-trash-can"></i></button>
                            </div>
                        </div>
                    `;
                });
                grid.innerHTML = html;
                totalItems.textContent = units;
                totalAmount.textContent = formatINR(totalAmt);

                pagInfo.textContent = `Showing ${start + 1}-${Math.min(start + prodPerPage, total)} of ${total}`;
                let phtml = '';
                phtml += `<button type="button" class="${prodPage === 1 ? '' : 'active'}" onclick="window.prodGoPage(${prodPage - 1})" ${prodPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
                const maxShow = 5;
                let startP = Math.max(1, prodPage - 2);
                let endP = Math.min(totalPages, startP + maxShow - 1);
                if (endP - startP < maxShow - 1) startP = Math.max(1, endP - maxShow + 1);
                for (let i = startP; i <= endP; i++) {
                    phtml += `<button type="button" class="${i === prodPage ? 'active' : ''}" onclick="window.prodGoPage(${i})">${i}</button>`;
                }
                phtml += `<button type="button" class="${prodPage === totalPages ? '' : 'active'}" onclick="window.prodGoPage(${prodPage + 1})" ${prodPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
                pagControls.innerHTML = phtml;

                // Attach event listeners
                grid.querySelectorAll('.rec-checkout').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const found = products.find(p => p.id === id);
                        if (found) {
                            addProductToSuggestionCart(found);
                            showToast('🛒 Added to Cart! Redirecting to checkout...', false);
                            setTimeout(() => {
                                window.location.href = 'checkout/checkout.php';
                            }, 1000);
                        }
                    });
                });
                grid.querySelectorAll('.rec-edit').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const found = products.find(p => p.id === id);
                        if (found) editProduct(found);
                    });
                });
                grid.querySelectorAll('.rec-delete').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        if (confirm('Delete this product?')) {
                            deleteProduct(id);
                        }
                    });
                });
            }

            window.prodGoPage = function (page) {
                const total = filteredProducts.length;
                const totalPages = Math.ceil(total / prodPerPage) || 1;
                if (page < 1 || page > totalPages) return;
                prodPage = page;
                render();
            };

            // ─── Build WhatsApp message ───
            function buildProductWhatsAppMessage(product) {
                const hasImage = product.image_base64 && product.image_base64.length > 100;
                let lines = [
                    '🛒 KSR GURU JI',
                    '',
                    `📦 Product\n${product.name}`,
                    `💰 Price\n${formatINR(product.price)}`,
                    `📦 Quantity\n${product.qty}`,
                    `💳 Payment Method\n${product.payment}`,
                ];
                if (product.note) lines.push(`📝 Note\n${product.note}`);
                lines.push('');
                if (hasImage && product.id) {
                    const url = window.location.href.split('?')[0] + '?ajax=serve_image&id=' + product.id + '&type=product&ext=.jpg';
                    lines.push(`📷 Product Image\n${url}`);
                } else {
                    lines.push('📷 Product Image\n❌ No image');
                }
                lines.push('');
                lines.push('Thank You');
                return lines.join('\n');
            }

            function sendProductWhatsApp(product) {
                const msg = encodeURIComponent(buildProductWhatsAppMessage(product));
                window.open(`https://wa.me/${getWhatsAppNumber()}?text=${msg}`, '_blank');
                showToast(`📱 WhatsApp sent for "${product.name}"`, false);
            }

            // ─── Add/Update ───
            function addOrUpdate(e) {
                if (e) e.preventDefault();
                const name = nameInp.value.trim();
                const price = parseFloat(priceInp.value);
                const unit = unitSel.value;
                let qty = parseInt(qtyInp.value) || 1;
                const note = noteInp.value.trim();
                const payment = paymentSel.value;
                const paymentStatus = paymentStatusSel ? paymentStatusSel.value : 'Paid';
                const upiId = upiIdInp ? upiIdInp.value.trim() : '';

                if (!name) {
                    showToast('⚠️ Product name required', true);
                    nameInp.focus(); return;
                }
                if (isNaN(price) || price <= 0 || price > 1000000) {
                    showToast('⚠️ Valid price required (1-1,000,000)',
                        true);
                    priceInp.focus(); return;
                }
                if (qty < 1) qty = 1;
                const img = currentImage || '';

                const productData = {
                    id: editingId || null,
                    name,
                    price,
                    unit,
                    qty,
                    note,
                    payment,
                    paymentStatus,
                    upiId,
                    imgBase64: img,
                };

                const isUpdate = !!editingId;
                saveProduct(productData, isUpdate).then(result => {
                    if (result && result.success) {
                        const finalProd = { ...productData, id: result.id, image_base64: img };
                        addProductToSuggestionCart(finalProd);
                        setCheckoutPaymentMethod(payment);

                        // reset form
                        resetForm();
                        editingId = null;
                        addBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Add Product &amp; WhatsApp';
                        cancelBtn.style.display = 'none';
                        showToast(isUpdate ? '✏️ Updated' : '✅ Added', false);
                        // send WhatsApp
                        sendProductWhatsApp(finalProd);

                        // Redirect to checkout
                        setTimeout(() => {
                            window.location.href = 'checkout/checkout.php';
                        }, 1800);
                    }
                });
            }

            function editProduct(product) {
                if (editingId !== null) cancelEdit();
                editingId = product.id;
                nameInp.value = product.name;
                priceInp.value = product.price;
                unitSel.value = product.unit || 'Packet';
                qtyInp.value = product.qty || 1;
                noteInp.value = product.note || '';
                paymentSel.value = product.payment || 'Cash on Delivery';
                if (paymentStatusSel) paymentStatusSel.value = product.payment_status || 'Paid';
                if (upiIdInp) upiIdInp.value = product.upi_id || '';
                if (product.image_base64 && product.image_base64.length > 100) {
                    currentImage = product.image_base64;
                    previewImg.src = currentImage;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                } else resetImage();
                addBtn.innerHTML = '<i class="fas fa-pen"></i> Update Product &amp; WhatsApp';
                cancelBtn.style.display = 'inline-flex';
                showToast(`✏️ Editing "${product.name}"`, false);
            }

            function cancelEdit() {
                editingId = null;
                resetForm();
                addBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Add Product &amp; WhatsApp';
                cancelBtn.style.display = 'none';
                resetImage();
                showToast('Edit cancelled', false);
            }

            function clearAll() {
                if (products.length === 0) { showToast('✨ Already empty', false); return; }
                if (!confirm('⚠️ Delete ALL products?')) return;
                // We don't have a bulk delete, so we'll delete one by one – or we can use a loop.
                // For simplicity, we'll just clear locally and reload.
                // Actually, we should delete from server. We'll iterate and delete each.
                const allIds = products.map(p => p.id);
                Promise.all(allIds.map(id => deleteProduct(id))).then(() => {
                    loadProducts();
                    showToast('🗑️ All products cleared', false);
                });
            }

            function triggerProductSearch() {
                const keyword = searchInput.value.toLowerCase().trim();
                if (!keyword) {
                    filteredProducts = [...products];
                } else {
                    filteredProducts = products.filter(p =>
                        p.name.toLowerCase().includes(keyword) ||
                        (p.note && p.note.toLowerCase().includes(keyword))
                    );
                }
                prodPage = 1;
                render();
            }

            function exportProductCSV() {
                if (products.length === 0) { showToast('No data to export', true); return; }
                const headers = ['Name', 'Price', 'Unit', 'Quantity', 'Note', 'Payment', 'PaymentStatus', 'UPI_ID'];
                const rows = products.map(p => [p.name, p.price, p.unit, p.qty, p.note || '', p.payment || '',
                p.payment_status || '', p.upi_id || ''
                ]);
                let csv = headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'products.csv';
                a.click();
                URL.revokeObjectURL(url);
                showToast('📥 CSV exported', false);
            }

            function printProducts() {
                if (products.length === 0) { showToast('No data to print', true); return; }
                let content = '<h2>Product Inventory</h2><ul>';
                products.forEach(p => {
                    content +=
                        `<li><strong>${escapeHtml(p.name)}</strong> – ${formatINR(p.price)} (${p.unit}), Qty: ${p.qty}, Payment: ${p.payment || 'N/A'}, Status: ${p.payment_status || 'N/A'}${p.note ? ', Note: ' + escapeHtml(p.note) : ''}</li>`;
                });
                content += '</ul>';
                const win = window.open('', '_blank');
                win.document.write(`<html><head><title>Products</title></head><body>${content}</body></html>`);
                win.document.close();
                win.print();
            }

            // ─── Event listeners ───
            addBtn.addEventListener('click', addOrUpdate);
            clearBtn.addEventListener('click', clearAll);
            cancelBtn.addEventListener('click', cancelEdit);
            searchInput.addEventListener('input', triggerProductSearch);
            sortSelect.addEventListener('change', render);
            document.getElementById('prodExportCSV').addEventListener('click', exportProductCSV);
            document.getElementById('prodPrint').addEventListener('click', printProducts);

            window.triggerProductSearch = triggerProductSearch;

            // ─── Init ───
            loadProducts();
        })();


        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  MEDICAL MODULE (AJAX version)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        (function () {
            let records = [];
            let editingId = null;
            let currentImage = '';
            let filteredRecords = [];
            let medPage = 1,
                medPerPage = 8;

            const patient = document.getElementById('medPatient');
            const age = document.getElementById('medAge');
            const gender = document.getElementById('medGender');
            const shop = document.getElementById('medShop');
            const symptoms = document.getElementById('medSymptoms');
            const meds = document.getElementById('medMeds');
            const diagnosis = document.getElementById('medDiagnosis');
            const date = document.getElementById('medDate');
            const doctor = document.getElementById('medDoctor');
            const suggestion = document.getElementById('medSuggestion');
            const paymentSel = document.getElementById('medPayment');
            const paymentStatusSel = document.getElementById('medPaymentStatus');
            const upiIdInp = document.getElementById('medUpiId');
            const imageInput = document.getElementById('medPrescription');
            const previewImg = document.getElementById('medPreview');
            const uploadStatus = document.getElementById('medUploadStatus');
            const grid = document.getElementById('medGrid');
            const recordCount = document.getElementById('medRecordCount');
            const totalSpan = document.getElementById('medTotal');
            const pendingSpan = document.getElementById('medPending');
            const submitBtn = document.getElementById('medSubmitBtn');
            const cancelBtn = document.getElementById('medCancelBtn');
            const clearFormBtn = document.getElementById('medClearFormBtn');
            const emergencyBtn = document.getElementById('medEmergencyBtn');
            const form = document.getElementById('medForm');
            const searchInput = document.getElementById('medSearch');
            const sortSelect = document.getElementById('medSort');
            const pagInfo = document.getElementById('medPaginationInfo');
            const pagControls = document.getElementById('medPaginationControls');

            function getWhatsAppNumber() { return '918088584421'; }

            // ─── Image handling ───
            document.getElementById('medUploadBtn').addEventListener('click', () => imageInput.click());

            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    showToast('⚠️ Please upload an image (JPG/PNG).', true);
                    this.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('⚠️ Image must be less than 5 MB.', true);
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    currentImage = ev.target.result;
                    previewImg.src = currentImage;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                };
                reader.readAsDataURL(file);
            });

            function resetImage() {
                currentImage = '';
                previewImg.src = '';
                previewImg.classList.remove('show');
                imageInput.value = '';
                uploadStatus.className = 'upload-status missing';
                uploadStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Required';
            }

            function resetForm() {
                patient.value = '';
                age.value = '';
                gender.value = '';
                shop.value = '';
                symptoms.value = '';
                meds.value = '';
                diagnosis.value = '';
                doctor.value = '';
                suggestion.value = '';
                paymentSel.value = 'Cash on Delivery';
                if (paymentStatusSel) paymentStatusSel.value = 'Paid';
                if (upiIdInp) upiIdInp.value = '';
                if (!date.value) {
                    const today = new Date().toISOString().split('T')[0];
                    date.value = today;
                }
                resetImage();
            }

            // ─── Load from server ───
            function loadMedical() {
                fetch('final-suggest.php?ajax=medical')
                    .then(res => res.json())
                    .then(data => {
                        records = data;
                        filteredRecords = [...records];
                        render();
                    })
                    .catch(err => {
                        showToast('⚠️ Error loading medical records', true);
                        console.error(err);
                    });
            }

            // ─── Save (add/update) ───
            function saveMedical(data, isUpdate = false) {
                const formData = new FormData();
                Object.keys(data).forEach(key => formData.append(key, data[key]));
                if (isUpdate && data.id) formData.append('id', data.id);
                return fetch('final-suggest.php?ajax=medical', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            loadMedical();
                            return result;
                        } else {
                            showToast('⚠️ Error saving: ' + (result.error || 'unknown'), true);
                            return result;
                        }
                    });
            }

            function deleteMedical(id) {
                return fetch('final-suggest.php?ajax=medical', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            loadMedical();
                            showToast('🗑️ Removed', false);
                        } else {
                            showToast('⚠️ Delete failed', true);
                        }
                    });
            }

            // ─── Sort ───
            function sortMedical(list, sortKey) {
                const copy = [...list];
                switch (sortKey) {
                    case 'newest': return copy.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    case 'oldest': return copy.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    case 'patient': return copy.sort((a, b) => a.patient_name.localeCompare(b.patient_name));
                    default: return copy;
                }
            }

            // ─── Render ───
            function render() {
                const sortKey = sortSelect.value;
                const sorted = sortMedical(filteredRecords, sortKey);
                const total = sorted.length;
                const totalPages = Math.ceil(total / medPerPage) || 1;
                if (medPage > totalPages) medPage = totalPages;
                const start = (medPage - 1) * medPerPage;
                const page = sorted.slice(start, start + medPerPage);

                if (total === 0) {
                    grid.innerHTML =
                        `<div class="empty-state"><i class="fas fa-file-prescription"></i><p>No medical suggestions found.</p></div>`;
                    recordCount.textContent = '0 records';
                    totalSpan.textContent = '0';
                    pendingSpan.textContent = '0';
                    pagInfo.textContent = 'Showing 0-0 of 0';
                    pagControls.innerHTML = '';
                    return;
                }

                let pending = records.filter(r => !r.resolved).length;
                let html = '';
                page.forEach(r => {
                    const img = r.prescription_image || '';
                    const hasImg = img && img.length > 100;
                    const dateObj = new Date(r.date);
                    const dateStr = !isNaN(dateObj) ? dateObj.toLocaleDateString('en-IN', {
                        day: 'numeric',
                        month: 'short', year: 'numeric'
                    }) : '';
                    const timeStr = r.created_at ? new Date(r.created_at).toLocaleTimeString('en-IN', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    const statusEmoji = r.payment_status === 'Paid' ? '✅' :
                        r.payment_status === 'Unpaid' ? '❌' :
                            r.payment_status === 'Pending' ? '🟡' : '🟠';
                    const imgSrc = hasImg ? img :
                        "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23F2F6FC'/%3E%3Ctext x='50' y='55' font-size='14' text-anchor='middle' fill='%237F9FBF'%3E📄%3C/text%3E%3C/svg%3E";
                    html += `
                        <div class="record-card">
                            <img class="card-img" loading="lazy" decoding="async" src="${imgSrc}" alt="prescription" onclick="openImageViewer('${imgSrc}')" style="cursor:pointer;" />
                            <div class="rec-name">${escapeHtml(r.patient_name)} <span style="font-weight:400;font-size:0.7rem;color:var(--text-secondary);">${r.age}y · ${r.gender}</span></div>
                            <div class="rec-meta"><i class="fas fa-store"></i> ${escapeHtml(r.shop_name)}</div>
                            <div class="rec-meta"><i class="fas fa-calendar-day"></i> ${dateStr} ${timeStr}</div>
                            ${r.diagnosis ? `<span class="rec-tag"><i class="fas fa-diagnoses"></i> ${escapeHtml(r.diagnosis)}</span>` : ''}
                            ${r.suggestion ? `<div class="rec-tag"><i class="fas fa-quote-left"></i> ${escapeHtml(r.suggestion.substring(0, 50))}${r.suggestion.length > 50 ? '…' : ''}</div>` : ''}
                            <div class="rec-actions">
                                <button class="rec-checkout" data-id="${r.id}" style="background:#10b981; color:#fff;"><i class="fas fa-credit-card"></i> Checkout</button>
                                <button class="rec-whatsapp" data-id="${r.id}"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                                <button class="rec-edit" data-id="${r.id}"><i class="fas fa-edit"></i> Edit</button>
                                <button class="rec-delete" data-id="${r.id}"><i class="fas fa-trash-can"></i></button>
                            </div>
                        </div>
                    `;
                });
                grid.innerHTML = html;
                recordCount.textContent = `${total} record${total > 1 ? 's' : ''}`;
                totalSpan.textContent = total;
                pendingSpan.textContent = pending;

                pagInfo.textContent = `Showing ${start + 1}-${Math.min(start + medPerPage, total)} of ${total}`;
                let phtml = '';
                phtml += `<button type="button" class="${medPage === 1 ? '' : 'active'}" onclick="window.medGoPage(${medPage - 1})" ${medPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
                const maxShow = 5;
                let startP = Math.max(1, medPage - 2);
                let endP = Math.min(totalPages, startP + maxShow - 1);
                if (endP - startP < maxShow - 1) startP = Math.max(1, endP - maxShow + 1);
                for (let i = startP; i <= endP; i++) {
                    phtml += `<button type="button" class="${i === medPage ? 'active' : ''}" onclick="window.medGoPage(${i})">${i}</button>`;
                }
                phtml += `<button type="button" class="${medPage === totalPages ? '' : 'active'}" onclick="window.medGoPage(${medPage + 1})" ${medPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
                pagControls.innerHTML = phtml;

                // Attach events
                grid.querySelectorAll('.rec-checkout').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const rec = records.find(r => r.id === id);
                        if (rec) {
                            addMedicalToSuggestionCart(rec);
                            showToast('🩺 Added to Cart! Redirecting to checkout...', false);
                            setTimeout(() => {
                                window.location.href = 'checkout/checkout.php';
                            }, 1000);
                        }
                    });
                });
                grid.querySelectorAll('.rec-whatsapp').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const rec = records.find(r => r.id === id);
                        if (rec) sendWhatsAppRecord(rec);
                    });
                });
                grid.querySelectorAll('.rec-edit').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const rec = records.find(r => r.id === id);
                        if (rec) editRecord(rec);
                    });
                });
                grid.querySelectorAll('.rec-delete').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        if (confirm('Delete this record?')) {
                            deleteMedical(id);
                        }
                    });
                });
            }

            window.medGoPage = function (page) {
                const total = filteredRecords.length;
                const totalPages = Math.ceil(total / medPerPage) || 1;
                if (page < 1 || page > totalPages) return;
                medPage = page;
                render();
            };

            // ─── WhatsApp ───
            function buildMedicalWhatsAppMessage(data) {
                let lines = [
                    '🏥 Medical Suggestion',
                    '',
                    `👤 Patient\n${data.patient_name}`,
                    `🏪 Medical Shop\n${data.shop_name}`,
                    `💳 Payment Method\n${data.payment}`,
                ];
                lines.push(`🩺 Suggestion\n${data.suggestion}`);
                if (data.suggested_meds && data.suggested_meds !== '(none)') lines.push(`💊 Tablets Name\n${data.suggested_meds}`);
                if (data.diagnosis && data.diagnosis !== '(none)') lines.push(`🔬 Diagnosis\n${data.diagnosis}`);
                if (data.doctor_name && data.doctor_name !== '(none)') lines.push(`👨‍⚕ Doctor\n${data.doctor_name}`);
                lines.push(`📅 Date\n${data.date}`);
                lines.push('');
                if (data.hasImage && data.id) {
                    const url = window.location.href.split('?')[0] + '?ajax=serve_image&id=' + data.id + '&type=medical&ext=.jpg';
                    lines.push(`📷 Prescription\n${url}`);
                } else {
                    lines.push('📷 Prescription\n❌ No image');
                }
                lines.push('');
                lines.push('— Sent via KSR GURU JI');
                return lines.join('\n');
            }

            function sendWhatsAppRecord(record) {
                const data = {
                    id: record.id,
                    patient_name: record.patient_name,
                    shop_name: record.shop_name,
                    payment: record.payment || 'Cash on Delivery',
                    payment_status: record.payment_status || 'Paid',
                    upi_id: record.upi_id || '',
                    suggestion: record.suggestion || '(not specified)',
                    suggested_meds: record.suggested_meds || '(none)',
                    diagnosis: record.diagnosis || '(none)',
                    doctor_name: record.doctor_name || '(none)',
                    date: record.date || 'Today',
                    hasImage: record.prescription_image && record.prescription_image.length > 100
                };
                const msg = encodeURIComponent(buildMedicalWhatsAppMessage(data));
                window.open(`https://wa.me/${getWhatsAppNumber()}?text=${msg}`, '_blank');
                showToast(`📱 WhatsApp sent for ${record.patient_name}`, false);
            }

            function sendMedicalWhatsAppFromForm(data) {
                const msg = encodeURIComponent(buildMedicalWhatsAppMessage(data));
                window.open(`https://wa.me/${getWhatsAppNumber()}?text=${msg}`, '_blank');
                showToast(`📱 WhatsApp sent for "${data.patient_name}"`, false);
            }

            // ─── Add/Update ───
            function addOrUpdate(e) {
                if (e) e.preventDefault();
                const name = patient.value.trim();
                const ageVal = parseInt(age.value);
                const gen = gender.value;
                const shopVal = shop.value.trim();
                const sugg = suggestion.value.trim();
                const payment = paymentSel.value;
                const paymentStatus = paymentStatusSel ? paymentStatusSel.value : 'Paid';
                const upiId = upiIdInp ? upiIdInp.value.trim() : '';
                const medsVal = meds.value.trim() || '(none)';
                const diag = diagnosis.value.trim() || '(none)';
                const doc = doctor.value.trim() || '(none)';
                const dateVal = date.value || 'Today';

                if (!name) {
                    showToast('⚠️ Patient name required', true);
                    patient.focus(); return;
                }
                if (!ageVal || ageVal < 1 || ageVal > 120) {
                    showToast('⚠️ Enter valid age (1-120)', true);
                    age.focus(); return;
                }
                if (!gen) {
                    showToast('⚠️ Select gender', true);
                    gender.focus(); return;
                }
                if (!shopVal) {
                    showToast('⚠️ Medical shop name required', true);
                    shop.focus(); return;
                }
                if (!sugg) {
                    showToast('⚠️ Suggestion/request required', true);
                    suggestion.focus(); return;
                }
                if (!currentImage) {
                    showToast('⚠️ Prescription image is mandatory!', true);
                    document.querySelector('#medUploadBtn').style.borderColor = '#dc2626';
                    setTimeout(() => { document.querySelector('#medUploadBtn').style.borderColor = ''; }, 600);
                    return;
                }

                const payload = {
                    patient_name: name,
                    age: ageVal,
                    gender: gen,
                    shop_name: shopVal,
                    symptoms: symptoms.value.trim(),
                    suggested_meds: meds.value.trim(),
                    diagnosis: diag,
                    date: dateVal,
                    doctor_name: doc,
                    suggestion: sugg,
                    payment: payment,
                    payment_status: paymentStatus,
                    upi_id: upiId,
                    prescription_image: currentImage,
                    resolved: 0,
                };
                if (editingId) payload.id = editingId;

                const isUpdate = !!editingId;
                saveMedical(payload, isUpdate).then(result => {
                    if (result && result.success) {
                        const savedId = result.id || payload.id;
                        const finalRec = { ...payload, id: savedId };
                        addMedicalToSuggestionCart(finalRec);
                        setCheckoutPaymentMethod(payment);

                        resetForm();
                        editingId = null;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit &amp; WhatsApp';
                        cancelBtn.style.display = 'none';
                        showToast(isUpdate ? '✏️ Updated' : '✅ Submitted', false);
                        // send WhatsApp
                        const msgData = { ...payload, hasImage: true, id: savedId };
                        sendMedicalWhatsAppFromForm(msgData);

                        // Redirect to checkout
                        setTimeout(() => {
                            window.location.href = 'checkout/checkout.php';
                        }, 1800);
                    }
                });
            }

            function editRecord(record) {
                if (editingId !== null) cancelEdit();
                editingId = record.id;
                patient.value = record.patient_name || '';
                age.value = record.age || '';
                gender.value = record.gender || '';
                shop.value = record.shop_name || '';
                symptoms.value = record.symptoms || '';
                meds.value = record.suggested_meds || '';
                diagnosis.value = record.diagnosis || '';
                date.value = record.date || '';
                doctor.value = record.doctor_name || '';
                suggestion.value = record.suggestion || '';
                paymentSel.value = record.payment || 'Cash on Delivery';
                if (paymentStatusSel) paymentStatusSel.value = record.payment_status || 'Paid';
                if (upiIdInp) upiIdInp.value = record.upi_id || '';
                if (record.prescription_image && record.prescription_image.length > 100) {
                    currentImage = record.prescription_image;
                    previewImg.src = currentImage;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                } else resetImage();
                submitBtn.innerHTML = '<i class="fas fa-pen"></i> Update';
                cancelBtn.style.display = 'inline-flex';
                showToast(`✏️ Editing "${record.patient_name}"`, false);
            }

            function cancelEdit() {
                editingId = null;
                resetForm();
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit &amp; WhatsApp';
                cancelBtn.style.display = 'none';
                resetImage();
                showToast('Edit cancelled', false);
            }

            function clearForm() {
                if (confirm('Clear form fields? (history remains)')) {
                    resetForm();
                    showToast('🧹 Form cleared', false);
                }
            }

            // ─── Emergency functions ───
            function triggerEmergency() {
                const name = patient.value.trim() || 'Unknown Patient';
                const ageVal = age.value.trim() || '?';
                const gen = gender.value || 'Not specified';
                const shopVal = shop.value.trim() || 'not available';
                const sugg = suggestion.value.trim() || 'Emergency – no additional details';
                const medsVal = meds.value.trim() || 'Not specified';
                const doc = doctor.value.trim() || 'Not available';
                const dateVal = date.value || new Date().toISOString().split('T')[0];

                const now = new Date();
                const d = now.getDate();
                const m = now.getMonth() + 1;
                const y = now.getFullYear();
                let hours = now.getHours();
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                const ampm = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12;
                hours = hours ? hours : 12;
                const timeFormatted = `${d}/${m}/${y} names, ${hours}:${minutes}:${seconds} ${ampm}`;

                let lines = [
                    '🚨 *🚑 MEDICAL EMERGENCY — DOCTOR REQUESTED* 🚨',
                    '',
                    `👤 Patient : ${name}`,
                    `📅 Age : ${ageVal}  ·  ⚥ Gender : ${gen}`,
                    '',
                    '💬 Emergency Description :',
                    sugg,
                    '',
                    `💊 Medicines mentioned : ${medsVal}`,
                    ` 🏥Clinic name:${shopVal}`,
                    `👨‍⚕ Doctor name : ${doc}`,
                    `📞Doctor number:not available`,
                    `📅 Date : ${dateVal}`,
                    '',
                    `🕒 Emergency triggered : ${timeFormatted}`,
                    `⚠️ PRIORITY : HIGH — Immediate doctor attention required`,
                    '',
                    '— Sent via KSR GURU JI Emergency Button'
                ];

                const msg = encodeURIComponent(lines.join('\n'));
                window.open(`https://wa.me/${getWhatsAppNumber()}?text=${msg}`, '_blank');
                showToast('🚨 Emergency alert sent to doctor via WhatsApp!', false);

                const btn = document.getElementById('medEmergencyBtn');
                btn.textContent = '⚠️ Emergency Sent!';
                btn.style.background = '#7f1d1d';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-ambulance"></i> 🚨 Emergency · Request Doctor';
                    btn.style.background = '';
                }, 3000);
            }

            function triggerMedicalSearch() {
                const keyword = searchInput.value.toLowerCase().trim();
                if (!keyword) {
                    filteredRecords = [...records];
                } else {
                    filteredRecords = records.filter(r =>
                        r.patient_name.toLowerCase().includes(keyword) ||
                        r.shop_name.toLowerCase().includes(keyword) ||
                        (r.suggestion && r.suggestion.toLowerCase().includes(keyword)) ||
                        (r.diagnosis && r.diagnosis.toLowerCase().includes(keyword))
                    );
                }
                medPage = 1;
                render();
            }

            function exportMedicalCSV() {
                if (records.length === 0) { showToast('No data to export', true); return; }
                const headers = ['Patient', 'Age', 'Gender', 'Shop', 'Symptoms', 'Meds', 'Diagnosis', 'Doctor', 'Date',
                    'Suggestion', 'Payment', 'PaymentStatus', 'UPI_ID'
                ];
                const rows = records.map(r => [
                    r.patient_name, r.age, r.gender, r.shop_name,
                    r.symptoms || '', r.suggested_meds || '', r.diagnosis || '',
                    r.doctor_name || '', r.date || '', r.suggestion || '',
                    r.payment || 'Cash on Delivery', r.payment_status || '', r.upi_id || ''
                ]);
                let csv = headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'medical_records.csv';
                a.click();
                URL.revokeObjectURL(url);
                showToast('📥 CSV exported', false);
            }

            function printMedical() {
                if (records.length === 0) { showToast('No data to print', true); return; }
                let content = '<h2>Medical Suggestion Records</h2><ul>';
                records.forEach(r => {
                    content +=
                        `<li><strong>${escapeHtml(r.patient_name)}</strong> (${r.age}y, ${r.gender}) – ${escapeHtml(r.shop_name)} – ${escapeHtml(r.suggestion || '')} – Payment: ${r.payment || 'Cash on Delivery'} – Status: ${r.payment_status || 'N/A'}</li>`;
                });
                content += '</ul>';
                const win = window.open('', '_blank');
                win.document.write(`<html><head><title>Medical Records</title></head><body>${content}</body></html>`);
                win.document.close();
                win.print();
            }

            // ─── Event listeners ───
            form.addEventListener('submit', addOrUpdate);
            cancelBtn.addEventListener('click', cancelEdit);
            clearFormBtn.addEventListener('click', clearForm);
            emergencyBtn.addEventListener('click', triggerEmergency);
            searchInput.addEventListener('input', triggerMedicalSearch);
            sortSelect.addEventListener('change', render);
            document.getElementById('medExportCSV').addEventListener('click', exportMedicalCSV);
            document.getElementById('medPrint').addEventListener('click', printMedical);

            window.triggerMedicalSearch = triggerMedicalSearch;

            // ─── Emergency badge trigger (Connected to Doctor Assist) ───
            document.getElementById('emergencyTrigger').addEventListener('click', function () {
                window.location.href = 'WhatsApp/telehealth/index.php?triage=1';
            });



            // ─── Keyboard shortcut ───
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    if (document.getElementById('tab-medical').classList.contains('active')) {
                        e.preventDefault();
                        addOrUpdate(e);
                    }
                }
            });

            // ─── Init ───
            if (!date.value) {
                const today = new Date().toISOString().split('T')[0];
                date.value = today;
            }
            loadMedical();
        })();

        // ─── Close image viewer on escape ───
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeImageViewer();
        });
    </script>
</body>

</html>