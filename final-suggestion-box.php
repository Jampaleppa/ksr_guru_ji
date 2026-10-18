<?php
    // ============================================================
    // UNIFIED: Product Suggestions + Medical Suggestions + Emergency Orders
    // Self-contained single file (no external dependencies)
    // ============================================================
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
    // ---------- WHATSAPP API CONFIG (replace with your own) ----------
    define('WHATSAPP_ACCESS_TOKEN', 'EAA_YOUR_LONG_META_ACCESS_TOKEN');
    define('WHATSAPP_PHONE_NUMBER_ID', '109876543212345');
    define('WHATSAPP_TEMPLATE_NAME', 'emergency_order');      // must be approved in Meta Business
    define('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US');
    define('WHATSAPP_RECIPIENTS', ['918762474406', '918088584421']);
    // ---------- DATABASE CONFIG ----------
    try {
        $pdo = getDBConnection();
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    // Start session (for user info – optional)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $isLoggedIn = isset($_SESSION['user_id']);
    $userId = $isLoggedIn ? $_SESSION['user_id'] : null;
    // ---------- CREATE TABLES (if not exist) ----------
    try {
        // --- Product submission tables (ADD workflow) ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        different_product VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_submission_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(30) NOT NULL,
        current_price DECIMAL(10,2) NOT NULL,
        suggested_shop VARCHAR(255) DEFAULT NULL,
        brand VARCHAR(100) DEFAULT NULL,
        suggestion_price DECIMAL(10,2) DEFAULT NULL,
        note VARCHAR(255) DEFAULT NULL,
        product_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES product_submissions(id) ON DELETE CASCADE
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
        // --- Emergency order tables ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS emergency_customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        mobile VARCHAR(15) NOT NULL,
        address TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS emergency_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        prescription VARCHAR(255) NOT NULL,
        remarks TEXT,
        status ENUM('Pending','Approved','Rejected','Delivered') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES emergency_customers(id) ON DELETE CASCADE
    )");
    } catch (PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
    // ---------- WHATSAPP API HELPERS ----------
    function sendEmergencyOrderWhatsApp(string $recipientPhone, int $orderId, string $customerName, string $imageUrl): int
    {
        $accessToken = WHATSAPP_ACCESS_TOKEN;
        $phoneNumberId = WHATSAPP_PHONE_NUMBER_ID;
        $templateName = WHATSAPP_TEMPLATE_NAME;
        $url = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";
        $payload = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $recipientPhone,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => WHATSAPP_TEMPLATE_LANGUAGE],
                "components" => [
                    [
                        "type" => "header",
                        "parameters" => [
                            [
                                "type" => "image",
                                "image" => ["link" => $imageUrl]
                            ]
                        ]
                    ],
                    [
                        "type" => "body",
                        "parameters" => [
                            ["type" => "text", "text" => $customerName],
                            ["type" => "text", "text" => "#" . $orderId]
                        ]
                    ]
                ]
            ]
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically
        file_put_contents('whatsapp_log.txt', date('Y-m-d H:i:s') . " - Sent to $recipientPhone - HTTP $httpCode - " . $response . "\n", FILE_APPEND);
        return $httpCode;
    }
    // ---------- HANDLE AJAX REQUESTS (Products, Medical, Image Serving) ----------
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        $action = $_GET['ajax'] ?? '';
        try {
            // ─── PRODUCT SUBMISSION (via POST with products_json) ───
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products_json'])) {
                $products = json_decode($_POST['products_json'], true);
                $different_product = trim($_POST['different_product'] ?? '');
                $product_message = '';
                if (!is_array($products) || count($products) === 0) {
                    $product_message = "Please add at least one product.";
                } else {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("INSERT INTO product_submissions (different_product) VALUES (?)");
                        $stmt->execute([$different_product]);
                        $submission_id = $pdo->lastInsertId();
                        $itemStmt = $pdo->prepare("INSERT INTO product_submission_items
                        (submission_id, product_name, category, quantity, unit, current_price, suggested_shop, brand, note, product_image)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        foreach ($products as $product) {
                            $product_name = trim($product['product_name']);
                            $category = trim($product['category'] ?? '');
                            $quantity = floatval($product['quantity'] ?? 0);
                            $unit = strval($product['unit'] ?? '');
                            $current_price = floatval($product['current_price'] ?? 0);
                            $suggested_shop = trim($product['suggested_shop'] ?? '');
                            $brand = trim($product['brand'] ?? '');
                            $note = trim($product['note'] ?? '');
                            $imageName = '';
                            if (!empty($product['image'])) {
                                $imageData = $product['image'];
                                if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                                    $imageData = substr($imageData, strpos($imageData, ',') + 1);
                                    $imageData = base64_decode($imageData);
                                    $extension = strtolower($type[1]);
                                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                                        $imageName = time() . '_' . uniqid() . '.' . $extension;
                                        file_put_contents($uploadDir . $imageName, $imageData);
                                    }
                                }
                            }
                            $itemStmt->execute([$submission_id, $product_name, $category, $quantity, $unit, $current_price, $suggested_shop, $brand, $note, $imageName]);
                        }
                        $pdo->commit();
                        $product_message = "All " . count($products) . " products submitted successfully!";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $product_message = "Submission failed: " . $e->getMessage();
                    }
                }
                echo json_encode(['success' => true, 'message' => $product_message]);
                exit;
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
                    $age = intval($_POST['age'] ?? 0);
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
                    $resolved = intval($_POST['resolved'] ?? 0);
                    if (empty($patient_name) || $age < 1 || empty($gender) || empty($shop_name) || empty($suggestion)) {
                        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                        exit;
                    }
                    if ($id) {
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
                    if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
                        $ext = $matches[1];
                        $data = substr($data, strpos($data, ',') + 1);
                        $decoded = base64_decode($data);
                        header("Content-Type: image/$ext");
                        echo $decoded;
                    } else {
                        header("Content-Type: image/jpeg");
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
    // ---------- HANDLE EMERGENCY ORDER FORM SUBMISSION ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
        $name    = htmlspecialchars(trim($_POST['name']));
        $mobile  = htmlspecialchars(trim($_POST['mobile']));
        $address = htmlspecialchars(trim($_POST['address']));
        $remarks = htmlspecialchars(trim($_POST['remarks'] ?? ''));
        if (empty($name) || empty($mobile) || empty($address)) {
            $error = "All required fields must be filled.";
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $error = "Mobile number must be 10 digits.";
        } else {
            $targetDir = 'uploads/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $imageFile = $_FILES['prescription'];
            $fileName = time() . '_' . basename($imageFile['name']);
            $targetPath = $targetDir . $fileName;
            $check = getimagesize($imageFile['tmp_name']);
            if ($check === false) {
                $error = "File is not a valid image.";
            } elseif (!move_uploaded_file($imageFile['tmp_name'], $targetPath)) {
                $error = "Failed to upload image.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO emergency_customers (name, mobile, address) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $mobile, $address]);
                    $customerId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("INSERT INTO emergency_orders (customer_id, prescription, remarks) VALUES (?, ?, ?)");
                    $stmt->execute([$customerId, $targetPath, $remarks]);
                    $orderId = $pdo->lastInsertId();
                    $pdo->commit();
                    $imageUrl = "http://{$_SERVER['HTTP_HOST']}/$targetPath";
                    $recipients = WHATSAPP_RECIPIENTS;
                    foreach ($recipients as $phone) {
                        sendEmergencyOrderWhatsApp($phone, $orderId, $name, $imageUrl);
                    }
                    $success = "Order #$orderId placed successfully! Delivery within 45 minutes.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    // ---------- HANDLE ADMIN ACTIONS (Emergency Orders) ----------
    $admin_password = 'admin123'; // change this!
    $is_admin = (isset($_GET['admin']) && $_GET['admin'] === $admin_password);
    if ($is_admin && isset($_GET['action'], $_GET['id'])) {
        $id = intval($_GET['id']);
        $action = $_GET['action'];
        $status = match ($action) {
            'approve' => 'Approved',
            'reject'  => 'Rejected',
            'deliver' => 'Delivered',
            default   => null
        };
        if ($status) {
            $stmt = $pdo->prepare("UPDATE emergency_orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            header("Location: ?admin=$admin_password#tab-emergency");
            exit;
        }
    }
    // ---------- FETCH ORDERS FOR ADMIN (Emergency) ----------
    $emergency_orders = [];
    if ($is_admin) {
        $stmt = $pdo->query("SELECT o.*, c.name, c.mobile, c.address
                         FROM emergency_orders o
                         JOIN emergency_customers c ON o.customer_id = c.id 
                         ORDER BY o.created_at DESC");
        $emergency_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>KSR GURU JI · Unified System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ─── RESET & VARIABLES ─── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #d4e4f5;
            --glass: rgba(255, 255, 255, 0.93);
            --text: #0b1f2e;
            --text-secondary: #1f4057;
            --border: #d7e4ef;
            --accent: #2563eb;
            --accent-hover: #1d4fbb;
            --success: #0b8a4b;
            --danger: #dc2626;
            --warning: #f59e0b;
            --radius: 3rem;
            --shadow: 0 40px 70px -20px rgba(0, 20, 40, 0.30);
            --transition: 0.25s ease;
        }

        body.dark {
            --bg: #0b1a2a;
            --glass: rgba(20, 35, 55, 0.94);
            --text: #e8f0f8;
            --text-secondary: #aac3db;
            --border: #2a4058;
            --shadow: 0 40px 70px -20px rgba(0, 0, 0, 0.6);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            padding: 1.8rem 1.2rem;
            transition: background var(--transition), color var(--transition);
            color: var(--text);
        }

        .glass-container {
            max-width: 1440px;
            margin: 0 auto;
            background: var(--glass);
            backdrop-filter: blur(16px);
            border-radius: var(--radius);
            box-shadow: var(--shadow), 0 0 0 1px rgba(255, 255, 255, 0.6);
            overflow: hidden;
            transition: background var(--transition), box-shadow var(--transition);
        }

        /* ─── HEADER ─── */
        .main-header {
            background: linear-gradient(115deg, #0b1f2e, #1b3a4f);
            padding: 1.2rem 2.2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand h1 {
            font-size: 1.9rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #acd5ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .brand h1 i {
            color: #56b0ff;
        }

        .brand .tagline {
            font-size: 0.72rem;
            color: #8bb9e6;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .whatsapp-link,
        .checkout-link {
            color: #fff;
            padding: 8px 22px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .whatsapp-link {
            background: #25d366;
        }

        .whatsapp-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(37, 211, 102, 0.4);
        }

        .checkout-link {
            background: var(--warning);
        }

        .checkout-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(245, 158, 11, 0.4);
        }

        .emergency-badge {
            background: var(--danger);
            color: #fff;
            padding: 8px 22px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
            animation: pulse-badge 2s infinite;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: var(--transition);
        }

        .emergency-badge:hover {
            transform: scale(1.02);
        }

        @keyframes pulse-badge {

            0%,
            100% {
                box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
            }

            50% {
                box-shadow: 0 6px 30px rgba(220, 38, 38, 0.6);
            }
        }

        .theme-toggle {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ─── TABS ─── */
        .tabs {
            display: flex;
            background: #eef4fa;
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            gap: 0.5rem;
            transition: background var(--transition), border var(--transition);
            overflow-x: auto;
        }

        .tab-btn {
            padding: 0.9rem 1.8rem;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            background: transparent;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .tab-btn.active {
            border-bottom-color: var(--accent);
            color: var(--text);
            background: rgba(255, 255, 255, 0.6);
        }

        .tab-btn i {
            font-size: 1rem;
        }

        /* ─── CONTENT ─── */
        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        /* ─── DASHBOARD LAYOUT ─── */
        .dashboard {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .form-card {
            flex: 1.2;
            min-width: 300px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(4px);
            border-radius: 2rem;
            padding: 1.8rem 1.6rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: background var(--transition), border var(--transition);
        }

        .list-card {
            flex: 2;
            min-width: 340px;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(4px);
            border-radius: 2rem;
            padding: 1.6rem 1.6rem 1.8rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: background var(--transition), border var(--transition);
        }

        /* ─── FORM ELEMENTS ─── */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 0.4rem;
        }

        .form-group label .req {
            color: var(--danger);
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            border: 1px solid var(--border);
            border-radius: 40px;
            background: #ffffff;
            transition: var(--transition);
            outline: none;
            color: var(--text);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(43, 123, 224, 0.13);
        }

        .form-group textarea {
            border-radius: 24px;
            resize: vertical;
            min-height: 60px;
        }

        .row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .row-3col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .row-4col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 1rem;
        }

        /* ─── UPLOAD ZONE ─── */
        .upload-zone {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            padding: 6px 0;
        }

        .upload-btn {
            background: #eef4fc;
            border: 1.5px dashed #90b2d3;
            border-radius: 48px;
            padding: 10px 28px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .upload-btn:hover {
            background: #e3edf9;
            border-color: var(--accent);
        }

        .upload-btn.mandatory {
            border-color: var(--danger);
            background: #fff0ee;
        }

        .preview-img {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            object-fit: cover;
            background: #f2f6fc;
            border: 2px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            display: none;
            cursor: pointer;
        }

        .preview-img.show {
            display: block;
        }

        .upload-status {
            font-size: 0.7rem;
            color: #1f6b3a;
            background: #e4f5ea;
            padding: 4px 16px;
            border-radius: 40px;
            display: inline-block;
        }

        .upload-status.missing {
            color: #a12b2b;
            background: #fee9e7;
        }

        /* ─── BUTTONS ─── */
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 1.6rem;
        }

        button {
            border: none;
            padding: 11px 28px;
            border-radius: 60px;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(95deg, #1d5fb0, #133b77);
            color: #fff;
            box-shadow: 0 6px 18px rgba(29, 95, 176, 0.28);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            filter: brightness(1.04);
        }

        .btn-success {
            background: var(--success);
            color: #fff;
            box-shadow: 0 6px 16px rgba(11, 138, 75, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #c5d8e8;
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: #f0f7fe;
        }

        .btn-danger-outline {
            background: transparent;
            border: 1.5px solid var(--danger);
            color: #b91c1c;
        }

        .btn-danger-outline:hover {
            background: #fee9e7;
        }

        .btn-cancel-edit {
            background: #94a3b8;
            color: #fff;
        }

        .btn-cancel-edit:hover {
            background: #6c7d9c;
        }

        .btn-emergency {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
            animation: pulse-badge 2.4s infinite;
            width: 100%;
            justify-content: center;
        }

        .btn-emergency:hover {
            transform: scale(1.02);
        }

        .btn-sm {
            padding: 6px 16px;
            font-size: 0.7rem;
        }

        /* ─── RECORD GRID ─── */
        .record-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.2rem;
            max-height: 480px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .record-grid::-webkit-scrollbar {
            width: 5px;
        }

        .record-grid::-webkit-scrollbar-track {
            background: #e8eff6;
            border-radius: 10px;
        }

        .record-grid::-webkit-scrollbar-thumb {
            background: #b2c9de;
            border-radius: 10px;
        }

        .record-card {
            background: #ffffff;
            border-radius: 1.6rem;
            padding: 1rem 1.1rem;
            border: 1px solid #e9f0f7;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .record-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px -8px rgba(0, 20, 40, 0.10);
            border-color: #c6d9ed;
        }

        .record-card .card-img {
            width: 100%;
            height: 110px;
            border-radius: 14px;
            object-fit: cover;
            background: #f2f6fc;
            margin-bottom: 10px;
            border: 1px solid #edf3fa;
        }

        .record-card .card-img-placeholder {
            width: 100%;
            height: 110px;
            border-radius: 14px;
            background: #f2f6fc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9bb3cb;
            font-size: 2rem;
            border: 1px solid #edf3fa;
            margin-bottom: 10px;
        }

        .record-card .rec-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text);
        }

        .record-card .rec-meta {
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            flex-wrap: wrap;
            gap: 6px 12px;
            margin: 4px 0;
        }

        .record-card .rec-meta i {
            width: 14px;
        }

        .record-card .rec-tag {
            font-size: 0.7rem;
            background: #f0f6fd;
            padding: 3px 12px;
            border-radius: 30px;
            display: inline-block;
            margin: 4px 0;
            color: var(--text-secondary);
        }

        .rec-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .rec-actions button {
            padding: 5px 14px;
            font-size: 0.65rem;
            border-radius: 30px;
            font-weight: 600;
            background: #f0f4fb;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .rec-actions .rec-whatsapp {
            background: #25d366;
            color: #fff;
        }

        .rec-actions .rec-whatsapp:hover {
            background: #1da855;
        }

        .rec-actions .rec-edit {
            background: #e9eff8;
            color: var(--accent);
        }

        .rec-actions .rec-edit:hover {
            background: #d5e3f5;
        }

        .rec-actions .rec-delete {
            background: #fee9e7;
            color: #b91c1c;
        }

        .rec-actions .rec-delete:hover {
            background: #fcd8d4;
        }

        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 2.4rem 0.5rem;
            color: #6f92b0;
        }

        .empty-state i {
            font-size: 2.8rem;
            opacity: 0.5;
            margin-bottom: 8px;
        }

        /* ─── SUMMARY ─── */
        .total-summary {
            background: linear-gradient(115deg, #e7f0fa, #d9e8f5);
            border-radius: 1.6rem;
            padding: 0.9rem 1.4rem;
            margin-top: 1.4rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            transition: background var(--transition);
        }

        .total-summary .stat {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .total-summary .stat span {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
            margin-left: 6px;
        }

        /* ─── SEARCH & EXPORT ─── */
        .search-box {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .search-box input {
            flex: 1;
            min-width: 160px;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            background: #fff;
            outline: none;
            transition: var(--transition);
            color: var(--text);
        }

        .search-box input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(43, 123, 224, 0.13);
        }

        .search-box .export-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .search-box .export-btns button {
            padding: 8px 16px;
            font-size: 0.7rem;
            border-radius: 40px;
            background: #f0f4fa;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .search-box .export-btns button:hover {
            background: #e3edf9;
        }

        .sort-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 0.8rem;
        }

        .sort-options select {
            padding: 6px 14px;
            border: 1px solid var(--border);
            border-radius: 40px;
            background: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            color: var(--text-secondary);
            outline: none;
            cursor: pointer;
        }

        .sort-options select:focus {
            border-color: var(--accent);
        }

        /* ─── PAGINATION ─── */
        .pagination-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 1rem;
        }

        .pagination-wrap .pagination-controls {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .pagination-wrap .pagination-controls button {
            padding: 4px 14px;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: var(--transition);
            color: var(--text-secondary);
        }

        .pagination-wrap .pagination-controls button.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .pagination-wrap .pagination-controls button:hover:not(.active) {
            background: #f0f4fa;
        }

        .pagination-wrap .pagination-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* ─── TOAST ─── */
        .toast-msg {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #132b3cd9;
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 14px 32px;
            border-radius: 60px;
            font-weight: 500;
            z-index: 999;
            font-size: 0.85rem;
            pointer-events: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: opacity 0.3s;
        }

        /* ─── IMAGE VIEWER ─── */
        .image-viewer {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .image-viewer.active {
            display: flex;
        }

        .image-viewer img {
            max-width: 92%;
            max-height: 92%;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            object-fit: contain;
        }

        .image-viewer .close-viewer {
            position: absolute;
            top: 24px;
            right: 32px;
            font-size: 2.2rem;
            color: #fff;
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            transition: var(--transition);
        }

        .image-viewer .close-viewer:hover {
            opacity: 1;
        }

        /* ─── EMERGENCY ORDER TABLE ─── */
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .order-table th,
        .order-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .order-table th {
            background: #e9eef3;
            font-weight: 600;
        }

        .order-table td a {
            margin-right: 8px;
            color: #2a7de1;
            text-decoration: none;
        }

        .order-table td a:hover {
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-Pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-Approved {
            background: #d4edda;
            color: #155724;
        }

        .status-Rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-Delivered {
            background: #cce5ff;
            color: #004085;
        }

        /* ─── PRODUCT LIST (ADD workflow) ─── */
        .product-list-section {
            margin: 25px 0;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }

        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .list-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .list-header .badge {
            background: var(--accent);
            color: #fff;
            border-radius: 30px;
            padding: 2px 16px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .product-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
        }

        .product-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fafafa;
            transition: var(--transition);
        }

        .product-card:hover {
            background: #f5f9ff;
        }

        .product-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #333;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .product-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .product-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .product-details strong {
            font-size: 16px;
        }

        .product-details span {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .product-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .product-actions button {
            padding: 4px 12px;
            font-size: 0.7rem;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .product-actions .edit-btn {
            background: #e9eff8;
            color: var(--accent);
        }

        .product-actions .edit-btn:hover {
            background: #d5e3f5;
        }

        .product-actions .delete-btn {
            background: #fee9e7;
            color: #b91c1c;
        }

        .product-actions .delete-btn:hover {
            background: #fcd8d4;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width:820px) {
            body {
                padding: 0.8rem;
            }

            .glass-container {
                border-radius: 1.8rem;
            }

            .dashboard {
                flex-direction: column;
            }

            .row-2col,
            .row-3col,
            .row-4col {
                grid-template-columns: 1fr !important;
            }

            .main-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                padding: 1rem 1.2rem;
            }

            .header-actions {
                justify-content: center;
            }

            .tabs {
                padding: 0 0.8rem;
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .tab-btn {
                padding: 0.7rem 1rem;
                font-size: 0.75rem;
                white-space: nowrap;
            }

            .tab-content {
                padding: 1rem;
            }

            .form-card,
            .list-card {
                padding: 1rem;
                border-radius: 1.4rem;
                min-width: 0;
            }

            .search-box {
                flex-direction: column;
                align-items: stretch;
            }

            .record-grid {
                grid-template-columns: 1fr;
                max-height: 400px;
            }

            .total-summary {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }

        @media (max-width:480px) {
            body {
                padding: 0.4rem;
            }

            .brand h1 {
                font-size: 1.4rem;
            }

            .header-actions .whatsapp-link,
            .header-actions .checkout-link {
                font-size: 0.7rem;
                padding: 6px 14px;
            }

            .emergency-badge {
                font-size: 0.7rem;
                padding: 6px 14px;
            }

            .btn-group button {
                flex: 1 1 auto;
                justify-content: center;
                padding: 10px 16px;
                font-size: 0.75rem;
            }

            .product-card {
                padding: 10px;
                gap: 8px;
            }

            .product-thumb {
                width: 50px;
                height: 50px;
            }

            .product-details strong {
                font-size: 14px;
            }

            .product-details span {
                font-size: 12px;
            }
        }
    </style>
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
        <header class="main-header">
            <div class="brand">
                <h1><i class="fas fa-cubes"></i> KSR GURU JI</h1>
                <div class="tagline"><i class="fas fa-sync-alt"></i> Products · Medical · Emergency</div>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
                <a href="checkout/checkout.php" class="checkout-link" target="_blank"><i class="fas fa-credit-card"></i> Checkout</a>
                <a href="https://wa.me/918088584421?text=Hello%20KSR%20GURU%20JI" target="_blank" class="whatsapp-link"><i class="fab fa-whatsapp"></i> 8088584421</a>
                <a href="#" class="emergency-badge" id="emergencyTrigger" style="text-decoration:none;"><i class="fas fa-triangle-exclamation"></i> Emergency · Doctor</a>
            </div>
        </header>
        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="product"><i class="fas fa-box"></i> Products</button>
            <button class="tab-btn" data-tab="medical"><i class="fas fa-notes-medical"></i> Medical</button>
            <button class="tab-btn" data-tab="emergency"><i class="fas fa-ambulance"></i> Emergency Orders</button>
        </div>
        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: PRODUCTS (UPDATED DESIGN) -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div id="tab-product" class="tab-content active">
            <div class="dashboard">
                <!-- Product Form -->
                <div class="form-card">
                    <form id="productForm" method="POST">
                        <div class="form-group">
                            <label>Product Name <span class="req">*</span></label>
                            <input type="text" id="product_name" placeholder="Search or enter product name" required>
                        </div>
                        <!-- Row: Category, Quantity, Unit -->
                        <div class="row-3col" style="margin-bottom:1.2rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Category <span class="req">*</span></label>
                                <select id="category" required>
                                    <option value="">Select</option>
                                    <option>Groceries</option>
                                    <option>Dairy</option>
                                    <option>Snacks</option>
                                    <option>Beverages</option>
                                    <option>Personal Care</option>
                                    <option>Cleaning</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Quantity <span class="req">*</span></label>
                                <input type="number" id="quantity" min="0.01" step="0.01" value="1" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Unit <span class="req">*</span></label>
                                <select id="unit" required>
                                    <option>Kg</option>
                                    <option>Gram</option>
                                    <option>Packet</option>
                                    <option>Liter</option>
                                    <option>Piece</option>
                                    <option>Box</option>
                                </select>
                            </div>
                        </div>
                        <!-- Row: Price, Suggested Shop, Brand -->
                        <div class="row-3col" style="margin-bottom:1.2rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Price (₹) <span class="req">*</span></label>
                                <input type="number" id="current_price" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Suggested Shop</label>
                                <input type="text" id="suggested_shop" placeholder="Supplier / Shop name">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Brand</label>
                                <input type="text" id="brand" placeholder="Brand name">
                            </div>
                        </div>
                        <!-- Note -->
                        <div class="form-group">
                            <label>Note</label>
                            <input type="text" id="note" placeholder="Door Delivery, Express Shipping...">
                        </div>
                        <!-- Image Upload -->
                        <div class="form-group">
                            <label>Product Image</label>
                            <div class="upload-zone">
                                <button type="button" class="upload-btn" id="productUploadBtn"><i class="fas fa-image"></i> Choose image</button>
                                <input type="file" id="product_image" accept="image/*" style="display:none;">
                                <img id="preview" class="preview-img" alt="Product preview">
                                <span id="prodUploadStatus" class="upload-status missing" style="display:none;"><i class="fas fa-exclamation-circle"></i> No image</span>
                            </div>
                        </div>
                        <!-- Action Buttons: Add Product & WhatsApp + Clear Current -->
                        <div class="btn-group">
                            <button type="button" class="btn-primary" id="addProductBtn"><i class="fab fa-whatsapp"></i> Add Product &amp; WhatsApp</button>
                            <button type="button" class="btn-outline" id="clearCurrentBtn"><i class="fas fa-eraser"></i> Clear</button>
                        </div>
                        <!-- Product List -->
                        <div id="productListSection" class="product-list-section" style="display:none;">
                            <div class="list-header">
                                <h2>Added Products</h2>
                                <span class="badge" id="productCount">0</span>
                            </div>
                            <div id="productList" class="product-list"></div>
                            <!-- Summary -->
                            <div class="total-summary" id="productSummary" style="display:none;">
                                <span class="stat"><i class="fas fa-cubes"></i> Total Items <span id="totalItems">0</span></span>
                                <span class="stat"><i class="fas fa-rupee-sign"></i> Total Value <span id="totalValue">₹0.00</span></span>
                            </div>
                        </div>
                        <!-- Different Product -->
                        <div class="form-group" style="margin-top:15px;">
                            <label>Any Different Product?</label>
                            <input type="text" name="different_product" id="different_product" placeholder="Enter different product name">
                        </div>
                        <!-- Hidden JSON -->
                        <input type="hidden" name="products_json" id="products_json">
                        <!-- FINAL SUBMIT & CLEAR ALL -->
                        <div class="btn-group" style="margin-top:20px;">
                            <button type="submit" class="btn-success" id="finalSubmitBtn"><i class="fas fa-check-double"></i> FINAL SUBMIT</button>
                            <button type="button" class="btn-danger-outline" id="clearAllBtn"><i class="fas fa-eraser"></i> CLEAR ALL</button>
                        </div>
                        <div id="productMessage" style="margin-top:15px; display:none; padding:10px; border-radius:8px;"></div>
                    </form>
                </div>
                <!-- Sidebar Info -->
                <div class="list-card" style="background:rgba(255,255,255,0.4);">
                    <h3><i class="fas fa-info-circle"></i> How it works</h3>
                    <ol style="padding-left:20px; font-size:0.9rem; line-height:1.8;">
                        <li>Fill product details and click <strong>ADD PRODUCT</strong>.</li>
                        <li>Products appear in the list below the form.</li>
                        <li>Add as many as you want – you can edit or delete any.</li>
                        <li>Click <strong>FINAL SUBMIT</strong> to save all products as one submission.</li>
                        <li>Use <strong>CLEAR ALL</strong> to reset the list.</li>
                    </ol>
                    <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:15px;">
                        <p><i class="fas fa-database"></i> All products are stored in a single submission for easy tracking.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: MEDICAL (unchanged) -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div id="tab-medical" class="tab-content">
            <div class="dashboard">
                <!-- Form -->
                <div class="form-card">
                    <form id="medForm" novalidate>
                        <div class="form-group"><label>Patient Name <span class="req">*</span></label><input type="text" id="medPatient" placeholder="e.g. Ravi Kumar" required /></div>
                        <div class="row-2col">
                            <div class="form-group"><label>Age <span class="req">*</span></label><input type="number" id="medAge" placeholder="45" min="1" max="120" required /></div>
                            <div class="form-group"><label>Gender <span class="req">*</span></label><select id="medGender" required>
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Other</option>
                                </select></div>
                        </div>
                        <div class="form-group"><label>Medical Shop <span class="req">*</span></label><input type="text" id="medShop" placeholder="e.g. Apollo Pharmacy" required /></div>
                        <div class="row-2col">
                            <div class="form-group"><label>Symptoms</label><textarea id="medSymptoms" rows="2" placeholder="fever, cough..."></textarea></div>
                            <div class="form-group"><label>Tablets Name</label><textarea id="medMeds" rows="2" placeholder="Paracetamol 650mg..."></textarea></div>
                        </div>
                        <div class="row-2col">
                            <div class="form-group"><label>Diagnosis</label><input type="text" id="medDiagnosis" placeholder="Viral Fever" /></div>
                            <div class="form-group"><label>Date</label><input type="date" id="medDate" /></div>
                        </div>
                        <div class="form-group"><label>Doctor Name</label><input type="text" id="medDoctor" placeholder="Dr. S. Sharma" /></div>
                        <div class="form-group"><label>Medical Suggestion <span class="req">*</span></label><textarea id="medSuggestion" rows="2" placeholder="Describe health concern…" required></textarea></div>
                        <div class="form-group"><label><i class="fas fa-credit-card"></i> Payment Method</label>
                            <select id="medPayment">
                                <option value="Cash on Delivery">Cash on Delivery</option>
                                <option value="QR Scan &amp; Pay">QR Scan &amp; Pay</option>
                                <option value="UPI ID">UPI ID</option>
                                <option value="Google Pay">Google Pay</option>
                                <option value="PhonePe">PhonePe</option>
                                <option value="Paytm">Paytm</option>
                                <option value="BHIM UPI">BHIM UPI</option>
                                <option value="Amazon Pay">Amazon Pay</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Upload Prescription <span class="req">*</span></label>
                            <div class="upload-zone">
                                <button type="button" class="upload-btn mandatory" id="medUploadBtn"><i class="fas fa-cloud-upload-alt"></i> Choose Prescription</button>
                                <input type="file" id="medPrescription" accept="image/*" style="display:none;" />
                                <img id="medPreview" class="preview-img" alt="Prescription preview" />
                                <span id="medUploadStatus" class="upload-status missing"><i class="fas fa-exclamation-circle"></i> Required</span>
                            </div>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn-primary" id="medSubmitBtn"><i class="fas fa-paper-plane"></i> Submit &amp; WhatsApp</button>
                            <button type="button" class="btn-outline" id="medClearFormBtn"><i class="fas fa-eraser"></i> Clear</button>
                            <button type="button" class="btn-cancel-edit" id="medCancelBtn" style="display:none;"><i class="fas fa-times-circle"></i> Cancel Edit</button>
                        </div>
                        <div style="margin-top:16px;">
                            <button type="button" class="btn-emergency" id="medEmergencyBtn"><i class="fas fa-ambulance"></i> Emergency · Request Doctor</button>
                        </div>
                    </form>
                </div>
                <!-- List -->
                <div class="list-card">
                    <div style="display:flex;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:8px;">
                        <h3><i class="fas fa-clock-rotate-left"></i> Medical History</h3>
                        <span class="badge-count" id="medRecordCount">0 records</span>
                    </div>
                    <div class="search-box">
                        <input type="search" id="medSearch" placeholder="Search patient / shop" aria-label="Search medical records" />
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
        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- TAB 3: EMERGENCY ORDERS (unchanged) -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div id="tab-emergency" class="tab-content">
            <div class="dashboard" style="flex-direction:column; gap:1rem;">
                <?php if ($is_admin): ?>
                    <div class="form-card" style="flex:1;">
                        <h2>📋 Admin – Emergency Orders</h2>
                        <p><a href="?">← Back to Customer Form</a></p>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Mobile</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($emergency_orders)): foreach ($emergency_orders as $o): ?>
                                        <tr>
                                            <td>#<?= $o['id'] ?></td>
                                            <td><?= htmlspecialchars($o['name']) ?></td>
                                            <td><?= htmlspecialchars($o['mobile']) ?></td>
                                            <td><?= htmlspecialchars(substr($o['address'], 0, 30)) . (strlen($o['address']) > 30 ? '…' : '') ?></td>
                                            <td><span class="status-badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                                            <td>
                                                <div style="margin-bottom: 6px;">
                                                    <a href="?admin=<?= $admin_password ?>&action=approve&id=<?= $o['id'] ?>#tab-emergency" style="font-weight:600;">Approve</a> |
                                                    <a href="?admin=<?= $admin_password ?>&action=reject&id=<?= $o['id'] ?>#tab-emergency" style="color:var(--danger); font-weight:600;">Reject</a> |
                                                    <a href="?admin=<?= $admin_password ?>&action=deliver&id=<?= $o['id'] ?>#tab-emergency" style="color:var(--success); font-weight:600;">Deliver</a> |
                                                    <a href="<?= htmlspecialchars($o['prescription']) ?>" target="_blank" style="font-weight:600;">View Image</a>
                                                </div>
                                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                                    <?php
                                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                                                    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                                                    $scriptDir = rtrim($scriptDir, '/\\');
                                                    $imgUrlForWA = "{$protocol}://{$_SERVER['HTTP_HOST']}" . $scriptDir . '/' . $o['prescription'];
                                                    $waText = "🆕 NEW EMERGENCY MEDICINE ORDER\n\n" .
                                                        "Order ID: #" . $o['id'] . "\n" .
                                                        "Customer: " . $o['name'] . "\n" .
                                                        "Phone: " . $o['mobile'] . "\n" .
                                                        "Address: " . $o['address'] . "\n" .
                                                        "Remarks: " . ($o['remarks'] ?: 'None') . "\n" .
                                                        "Status: " . $o['status'] . "\n\n" .
                                                        "📄 Prescription Image:\n" . $imgUrlForWA . "\n\n" .
                                                        "— KSR GURU JI";
                                                    $waTextEncoded = urlencode($waText);
                                                    ?>
                                                    <a href="https://wa.me/918762474406?text=<?= $waTextEncoded ?>" target="_blank" style="background:#20ba5a; color:white; padding: 3px 8px; border-radius: 12px; text-decoration:none; font-weight:600; font-size:10px; display:inline-flex; align-items:center; gap:3px;"><i class="fab fa-whatsapp"></i> Primary Driver</a>
                                                    <a href="https://wa.me/918088584421?text=<?= $waTextEncoded ?>" target="_blank" style="background:#20ba5a; color:white; padding: 3px 8px; border-radius: 12px; text-decoration:none; font-weight:600; font-size:10px; display:inline-flex; align-items:center; gap:3px;"><i class="fab fa-whatsapp"></i> Secondary Driver</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="6">No orders yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top:20px;">
                            <p><strong>Order Details:</strong> Click "View Image" to see the prescription.</p>
                            <p><small>Tip: Replace the default password <code>admin123</code> in the code.</small></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-card" style="flex:1;">
                        <h2>Emergency Medicine Order</h2>
                        <?php if (isset($error)): ?>
                            <div style="color:#d32f2f; background:#fde8e8; padding:12px; border-radius:6px; margin-bottom:15px;"><?= $error ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div style="color:#0d6e2b; background:#e3f5e8; padding:12px; border-radius:6px; margin-bottom:15px;"><?= $success ?></div>
                            <?php
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                            $scriptDir = rtrim($scriptDir, '/\\');
                            $targetPathVal = $targetPath ?? '';
                            $orderIdVal = $orderId ?? '';
                            $nameVal = $name ?? '';
                            $mobileVal = $mobile ?? '';
                            $addressVal = $address ?? '';
                            $remarksVal = $remarks ?? '';
                            $imgUrlForWA = "{$protocol}://{$_SERVER['HTTP_HOST']}" . $scriptDir . '/' . $targetPathVal;
                            $waText = "🆕 NEW EMERGENCY MEDICINE ORDER\n\n" .
                                "Order ID: #" . $orderIdVal . "\n" .
                                "Customer: " . $nameVal . "\n" .
                                "Phone: " . $mobileVal . "\n" .
                                "Address: " . $addressVal . "\n" .
                                "Remarks: " . ($remarksVal ?: 'None') . "\n\n" .
                                "📄 Prescription Image:\n" . $imgUrlForWA . "\n\n" .
                                "— KSR GURU JI";
                            $waTextEncoded = urlencode($waText);
                            ?>
                            <div style="margin: 20px 0; border: 1px dashed #20ba5a; padding: 15px; border-radius: 12px; background: #f0fdf4; text-align: center;">
                                <div style="font-weight: 700; font-size: 0.9rem; color: #166534; margin-bottom: 12px;">
                                    <i class="fab fa-whatsapp" style="font-size: 1.2rem; color: #20ba5a; vertical-align: middle;"></i> Share Order/Prescription with Delivery Personnel
                                </div>
                                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                    <a href="https://wa.me/918762474406?text=<?= $waTextEncoded ?>" target="_blank" style="background:#20ba5a; color:white; padding: 8px 16px; border-radius: 20px; text-decoration:none; font-weight:600; font-size:0.8rem; display:inline-flex; align-items:center; gap:6px;">
                                        <i class="fab fa-whatsapp"></i> Notify Primary Driver
                                    </a>
                                    <a href="https://wa.me/918088584421?text=<?= $waTextEncoded ?>" target="_blank" style="background:#20ba5a; color:white; padding: 8px 16px; border-radius: 20px; text-decoration:none; font-weight:600; font-size:0.8rem; display:inline-flex; align-items:center; gap:6px;">
                                        <i class="fab fa-whatsapp"></i> Notify Secondary Driver
                                    </a>
                                </div>
                            </div>
                            <p><a href="?">← Place another order</a></p>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" name="name" placeholder="Enter your name" required>
                                </div>
                                <div class="form-group">
                                    <label>Mobile Number *</label>
                                    <input type="tel" name="mobile" placeholder="10-digit mobile" required pattern="[0-9]{10}">
                                </div>
                                <div class="form-group">
                                    <label>Delivery Address *</label>
                                    <textarea name="address" placeholder="Enter full address" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Upload Prescription (image) *</label>
                                    <input type="file" name="prescription" accept="image/*" required id="emergencyPrescriptionInput">
                                    <div id="emergencyPreview"></div>
                                </div>
                                <div class="form-group">
                                    <label>Remarks (optional)</label>
                                    <textarea name="remarks" placeholder="Any special instructions"></textarea>
                                </div>
                                <button type="submit" name="submit_order" class="btn-primary" style="width:100%; justify-content:center;">Submit Order</button>
                            </form>
                            <p style="margin-top:20px;"><a href="?admin=admin123#tab-emergency">Admin Panel</a> (password: admin123)</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ─── JAVASCRIPT ─── -->
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [m];
            });
        }

        function getWhatsAppNumbers() {
            return ['918762474406', '918088584421'];
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
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  TABS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const tab = this.dataset.tab;
                document.getElementById('tab-' + tab).classList.add('active');
                if (tab === 'medical' && window.triggerMedicalSearch) window.triggerMedicalSearch();
            });
        });
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  THEME
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        document.getElementById('themeToggle').addEventListener('click', function() {
            document.body.classList.toggle('dark');
            const icon = this.querySelector('i');
            icon.className = document.body.classList.contains('dark') ? 'fas fa-sun' : 'fas fa-moon';
            localStorage.setItem('ksr_theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        });
        if (localStorage.getItem('ksr_theme') === 'dark') {
            document.body.classList.add('dark');
            document.getElementById('themeToggle').querySelector('i').className = 'fas fa-sun';
        }
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  PRODUCT MODULE (ADD workflow)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        (function() {
            let products = [];
            let editingIndex = null;

            // DOM refs
            const nameInp = document.getElementById('product_name');
            const categorySel = document.getElementById('category');
            const qtyInp = document.getElementById('quantity');
            const unitSel = document.getElementById('unit');
            const priceInp = document.getElementById('current_price');
            const shopInp = document.getElementById('suggested_shop');
            const brandInp = document.getElementById('brand');
            const noteInp = document.getElementById('note');
            const imageInput = document.getElementById('product_image');
            const previewImg = document.getElementById('preview');
            const uploadStatus = document.getElementById('prodUploadStatus');
            const listSection = document.getElementById('productListSection');
            const listContainer = document.getElementById('productList');
            const countSpan = document.getElementById('productCount');
            const totalItemsSpan = document.getElementById('totalItems');
            const totalValueSpan = document.getElementById('totalValue');
            const summaryDiv = document.getElementById('productSummary');
            const hiddenJson = document.getElementById('products_json');
            const msgDiv = document.getElementById('productMessage');

            // Upload button
            document.getElementById('productUploadBtn').addEventListener('click', () => imageInput.click());
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                    uploadStatus.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });

            function clearCurrentProduct() {
                nameInp.value = '';
                categorySel.value = '';
                qtyInp.value = '1';
                unitSel.value = 'Kg';
                priceInp.value = '';
                shopInp.value = '';
                brandInp.value = '';
                noteInp.value = '';
                imageInput.value = '';
                previewImg.src = '';
                previewImg.classList.remove('show');
                uploadStatus.className = 'upload-status missing';
                uploadStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> No image';
                uploadStatus.style.display = 'none';
                nameInp.focus();
                editingIndex = null;
                document.getElementById('addProductBtn').innerHTML = '<i class="fab fa-whatsapp"></i> Add Product &amp; WhatsApp';
            }

            function renderProducts() {
                const count = products.length;
                countSpan.textContent = count;
                if (count === 0) {
                    listSection.style.display = 'none';
                    summaryDiv.style.display = 'none';
                    listContainer.innerHTML = '';
                    return;
                }
                listSection.style.display = 'block';
                summaryDiv.style.display = 'flex';
                listContainer.innerHTML = '';
                let totalValue = 0;
                products.forEach((p, idx) => {
                    totalValue += p.current_price * p.quantity;
                    const card = document.createElement('div');
                    card.className = 'product-card';
                    let imgHtml = p.image ? `<img src="${p.image}" class="product-thumb" alt="Product image">` : '';
                    const catBadge = p.category ? `<span style="background:var(--accent); color:#fff; padding:2px 6px; border-radius:4px; font-size:11px;">${escapeHtml(p.category)}</span>` : '';
                    card.innerHTML = `
                <div class="product-number">${idx+1}</div>
                ${imgHtml}
                <div class="product-details">
                    <strong>${escapeHtml(p.product_name)} ${catBadge}</strong>
                    <span>Qty: ${p.quantity} ${escapeHtml(p.unit)} | ₹${p.current_price.toFixed(2)}</span>
                    ${p.suggested_shop ? `<span>Shop: ${escapeHtml(p.suggested_shop)}</span>` : ''}
                    ${p.brand ? `<span>Brand: ${escapeHtml(p.brand)}</span>` : ''}
                    ${p.note ? `<span>Note: ${escapeHtml(p.note)}</span>` : ''}
                </div>
                <div class="product-actions">
                    <button class="edit-btn" data-index="${idx}"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn" data-index="${idx}"><i class="fas fa-trash"></i> Delete</button>
                </div>
            `;
                    listContainer.appendChild(card);
                });
                totalItemsSpan.textContent = count;
                totalValueSpan.textContent = '₹' + totalValue.toFixed(2);

                // Attach events
                listContainer.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const idx = parseInt(this.dataset.index);
                        editProduct(idx);
                    });
                });
                listContainer.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const idx = parseInt(this.dataset.index);
                        if (confirm('Remove this product?')) {
                            products.splice(idx, 1);
                            renderProducts();
                            showToast('Product removed', false);
                        }
                    });
                });
            }

            function addProduct() {
                const name = nameInp.value.trim();
                const category = categorySel.value;
                const qty = parseFloat(qtyInp.value);
                const unit = unitSel.value;
                const price = parseFloat(priceInp.value);
                const shop = shopInp.value.trim();
                const brand = brandInp.value.trim();
                const note = noteInp.value.trim();
                const image = previewImg.src && previewImg.classList.contains('show') ? previewImg.src : '';

                if (!name) {
                    showToast('Product name required', true);
                    nameInp.focus();
                    return;
                }
                if (!category) {
                    showToast('Select category', true);
                    categorySel.focus();
                    return;
                }
                if (!qty || qty <= 0) {
                    showToast('Valid quantity required', true);
                    qtyInp.focus();
                    return;
                }
                if (isNaN(price) || price < 0) {
                    showToast('Valid price required', true);
                    priceInp.focus();
                    return;
                }

                const product = {
                    product_name: name,
                    category,
                    quantity: qty,
                    unit,
                    current_price: price,
                    suggested_shop: shop,
                    brand,
                    note,
                    image
                };

                if (editingIndex !== null) {
                    products[editingIndex] = product;
                    editingIndex = null;
                    document.getElementById('addProductBtn').innerHTML = '<i class="fab fa-whatsapp"></i> Add Product &amp; WhatsApp';
                    showToast('Product updated', false);
                } else {
                    products.push(product);
                    showToast('Product added', false);
                }

                renderProducts();
                clearCurrentProduct();
                // Send WhatsApp (queue mode)
                sendQueueWhatsApp(product);
            }

            function editProduct(index) {
                const p = products[index];
                nameInp.value = p.product_name;
                categorySel.value = p.category || '';
                qtyInp.value = p.quantity;
                unitSel.value = p.unit || 'Kg';
                priceInp.value = p.current_price;
                shopInp.value = p.suggested_shop || '';
                brandInp.value = p.brand || '';
                noteInp.value = p.note || '';
                if (p.image) {
                    previewImg.src = p.image;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                    uploadStatus.style.display = 'block';
                } else {
                    previewImg.classList.remove('show');
                    uploadStatus.className = 'upload-status missing';
                    uploadStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> No image';
                    uploadStatus.style.display = 'none';
                }
                editingIndex = index;
                document.getElementById('addProductBtn').innerHTML = '<i class="fas fa-pen"></i> Update Product';
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function sendQueueWhatsApp(product) {
                let lines = [
                    'KSR GURU JI - Product Suggestion (Queue Mode)',
                    '',
                    '- Product: ' + product.product_name,
                    '- Price: ₹' + product.current_price.toFixed(2),
                    '- Quantity: ' + product.quantity + ' ' + product.unit,
                ];
                if (product.suggested_shop) lines.push('- Shop: ' + product.suggested_shop);
                if (product.brand) lines.push('- Brand: ' + product.brand);
                if (product.note) lines.push('- Note: ' + product.note);
                lines.push('');
                lines.push('Thank You');
                const msg = encodeURIComponent(lines.join('\n'));
                getWhatsAppNumbers().forEach(num => {
                    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
                });
                showToast(`WhatsApp sent for ${product.product_name}`, false);
            }

            function clearAll() {
                if (products.length === 0) return;
                if (!confirm('Remove all products?')) return;
                products = [];
                renderProducts();
                clearCurrentProduct();
                document.getElementById('different_product').value = '';
                showToast('All products cleared', false);
            }

            // Event listeners
            document.getElementById('addProductBtn').addEventListener('click', addProduct);
            document.getElementById('clearCurrentBtn').addEventListener('click', clearCurrentProduct);
            document.getElementById('clearAllBtn').addEventListener('click', clearAll);

            // Final submit via AJAX
            document.getElementById('finalSubmitBtn').addEventListener('click', function(e) {
                e.preventDefault();
                if (products.length === 0) {
                    showToast('Please add at least one product.', true);
                    return;
                }
                const diff = document.getElementById('different_product').value.trim();
                hiddenJson.value = JSON.stringify(products);
                const form = document.getElementById('productForm');
                const formData = new FormData(form);
                formData.append('ajax', 'product'); // to trigger our AJAX handler

                fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            msgDiv.style.display = 'block';
                            msgDiv.style.background = '#d4edda';
                            msgDiv.style.color = '#155724';
                            msgDiv.innerHTML = data.message;
                            products = [];
                            renderProducts();
                            hiddenJson.value = '';
                            document.getElementById('different_product').value = '';
                            showToast('Submitted successfully!', false);
                        } else {
                            msgDiv.style.display = 'block';
                            msgDiv.style.background = '#f8d7da';
                            msgDiv.style.color = '#721c24';
                            msgDiv.innerHTML = 'Submission failed: ' + (data.message || 'Unknown error');
                            showToast('Submission failed', true);
                        }
                    })
                    .catch(err => {
                        showToast('Error submitting: ' + err.message, true);
                    });
            });

            // Initial render
            renderProducts();
        })();

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  MEDICAL MODULE (AJAX version) – identical to previous
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        (function() {
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

            document.getElementById('medUploadBtn').addEventListener('click', () => imageInput.click());
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    showToast('Please upload an image (JPG/PNG).', true);
                    this.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('Image must be less than 5 MB.', true);
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
                if (!date.value) {
                    const today = new Date().toISOString().split('T')[0];
                    date.value = today;
                }
                resetImage();
            }

            function loadMedical() {
                fetch('?ajax=medical')
                    .then(res => res.json())
                    .then(data => {
                        records = data;
                        filteredRecords = [...records];
                        render();
                    })
                    .catch(err => {
                        showToast('Error loading medical records', true);
                        console.error(err);
                    });
            }

            function saveMedical(data, isUpdate = false) {
                const formData = new FormData();
                Object.keys(data).forEach(key => formData.append(key, data[key]));
                if (isUpdate && data.id) formData.append('id', data.id);
                return fetch('?ajax=medical', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            loadMedical();
                            return result;
                        } else {
                            showToast('Error saving: ' + (result.error || 'unknown'), true);
                            return result;
                        }
                    });
            }

            function deleteMedical(id) {
                return fetch('?ajax=medical', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + encodeURIComponent(id)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            loadMedical();
                            showToast('Removed', false);
                        } else {
                            showToast('Delete failed', true);
                        }
                    });
            }

            function sortMedical(list, sortKey) {
                const copy = [...list];
                switch (sortKey) {
                    case 'newest':
                        return copy.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    case 'oldest':
                        return copy.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    case 'patient':
                        return copy.sort((a, b) => a.patient_name.localeCompare(b.patient_name));
                    default:
                        return copy;
                }
            }

            function render() {
                const sortKey = sortSelect.value;
                const sorted = sortMedical(filteredRecords, sortKey);
                const total = sorted.length;
                const totalPages = Math.ceil(total / medPerPage) || 1;
                if (medPage > totalPages) medPage = totalPages;
                const start = (medPage - 1) * medPerPage;
                const page = sorted.slice(start, start + medPerPage);
                if (total === 0) {
                    grid.innerHTML = '<div class="empty-state"><i class="fas fa-file-prescription"></i><p>No medical suggestions found.</p></div>';
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
                        month: 'short',
                        year: 'numeric'
                    }) : '';
                    const timeStr = r.created_at ? new Date(r.created_at).toLocaleTimeString('en-IN', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    const statusEmoji = r.payment_status === 'Paid' ? '✅' :
                        r.payment_status === 'Unpaid' ? '❌' :
                        r.payment_status === 'Pending' ? '🟡' : '🟠';
                    const imgSrc = hasImg ? img : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23F2F6FC'/%3E%3Ctext x='50' y='55' font-size='14' text-anchor='middle' fill='%237F9FBF'%3E📄%3C/text%3E%3C/svg%3E";
                    html += `
                <div class="record-card">
                    <img class="card-img" loading="lazy" decoding="async" src="${imgSrc}" alt="prescription" onclick="openImageViewer('${imgSrc}')" style="cursor:pointer;" />
                    <div class="rec-name">${escapeHtml(r.patient_name)} <span style="font-weight:400;font-size:0.7rem;color:var(--text-secondary);">${r.age}y · ${r.gender}</span></div>
                    <div class="rec-meta"><i class="fas fa-store"></i> ${escapeHtml(r.shop_name)}</div>
                    <div class="rec-meta"><i class="fas fa-calendar-day"></i> ${dateStr} ${timeStr}</div>
                    ${r.diagnosis ? `<span class="rec-tag"><i class="fas fa-diagnoses"></i> ${escapeHtml(r.diagnosis)}</span>` : ''}
                    ${r.payment ? `<span class="rec-tag"><i class="fas fa-credit-card"></i> ${escapeHtml(r.payment)}</span>` : ''}
                    ${r.payment_status ? `<span class="rec-tag">${statusEmoji} ${escapeHtml(r.payment_status)}</span>` : ''}
                    ${r.upi_id ? `<span class="rec-tag"><i class="fas fa-qrcode"></i> ${escapeHtml(r.upi_id)}</span>` : ''}
                    ${r.suggestion ? `<div class="rec-tag"><i class="fas fa-quote-left"></i> ${escapeHtml(r.suggestion.substring(0,50))}${r.suggestion.length > 50 ? '…' : ''}</div>` : ''}
                    <div class="rec-actions">
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
                grid.querySelectorAll('.rec-whatsapp').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const rec = records.find(r => r.id === id);
                        if (rec) sendWhatsAppRecord(rec);
                    });
                });
                grid.querySelectorAll('.rec-edit').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const rec = records.find(r => r.id === id);
                        if (rec) editRecord(rec);
                    });
                });
                grid.querySelectorAll('.rec-delete').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        if (confirm('Delete this record?')) deleteMedical(id);
                    });
                });
            }

            window.medGoPage = function(page) {
                const total = filteredRecords.length;
                const totalPages = Math.ceil(total / medPerPage) || 1;
                if (page < 1 || page > totalPages) return;
                medPage = page;
                render();
            };

            function buildMedicalWhatsAppMessage(data) {
                let lines = [
                    'Medical Suggestion',
                    '',
                    '- Patient: ' + data.patient_name,
                    '- Medical Shop: ' + data.shop_name,
                    '- Payment: ' + data.payment,
                ];
                lines.push('- Suggestion: ' + data.suggestion);
                if (data.suggested_meds && data.suggested_meds !== '(none)') lines.push('- Tablets Name: ' + data.suggested_meds);
                if (data.diagnosis && data.diagnosis !== '(none)') lines.push('- Diagnosis: ' + data.diagnosis);
                if (data.doctor_name && data.doctor_name !== '(none)') lines.push('- Doctor: ' + data.doctor_name);
                lines.push('- Date: ' + data.date);
                if (data.hasImage && data.id) {
                    const url = window.location.href.split('?')[0] + '?ajax=serve_image&id=' + data.id + '&type=medical&ext=.jpg';
                    lines.push('- Prescription: ' + url);
                } else {
                    lines.push('- Prescription: No image');
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
                    suggestion: record.suggestion || '(not specified)',
                    suggested_meds: record.suggested_meds || '(none)',
                    diagnosis: record.diagnosis || '(none)',
                    doctor_name: record.doctor_name || '(none)',
                    date: record.date || 'Today',
                    hasImage: record.prescription_image && record.prescription_image.length > 100
                };
                const msg = encodeURIComponent(buildMedicalWhatsAppMessage(data));
                getWhatsAppNumbers().forEach(num => {
                    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
                });
                showToast(`WhatsApp sent for ${record.patient_name}`, false);
            }

            function sendMedicalWhatsAppFromForm(data) {
                const msg = encodeURIComponent(buildMedicalWhatsAppMessage(data));
                getWhatsAppNumbers().forEach(num => {
                    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
                });
                showToast(`WhatsApp sent for "${data.patient_name}"`, false);
            }

            function addOrUpdate(e) {
                if (e) e.preventDefault();
                const name = patient.value.trim();
                const ageVal = parseInt(age.value);
                const gen = gender.value;
                const shopVal = shop.value.trim();
                const sugg = suggestion.value.trim();
                const payment = paymentSel.value;
                const medsVal = meds.value.trim() || '(none)';
                const diag = diagnosis.value.trim() || '(none)';
                const doc = doctor.value.trim() || '(none)';
                const dateVal = date.value || 'Today';
                if (!name) {
                    showToast('Patient name required', true);
                    patient.focus();
                    return;
                }
                if (!ageVal || ageVal < 1 || ageVal > 120) {
                    showToast('Enter valid age (1-120)', true);
                    age.focus();
                    return;
                }
                if (!gen) {
                    showToast('Select gender', true);
                    gender.focus();
                    return;
                }
                if (!shopVal) {
                    showToast('Medical shop name required', true);
                    shop.focus();
                    return;
                }
                if (!sugg) {
                    showToast('Suggestion/request required', true);
                    suggestion.focus();
                    return;
                }
                if (!currentImage) {
                    showToast('Prescription image is mandatory!', true);
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
                    payment_status: 'Paid',
                    upi_id: '',
                    prescription_image: currentImage,
                    resolved: 0,
                };
                if (editingId) payload.id = editingId;
                const isUpdate = !!editingId;
                saveMedical(payload, isUpdate).then(result => {
                    if (result && result.success) {
                        resetForm();
                        editingId = null;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit &amp; WhatsApp';
                        cancelBtn.style.display = 'none';
                        showToast(isUpdate ? 'Updated' : 'Submitted', false);
                        const msgData = {
                            ...payload,
                            hasImage: true,
                            id: result.id || payload.id
                        };
                        sendMedicalWhatsAppFromForm(msgData);
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
                if (record.prescription_image && record.prescription_image.length > 100) {
                    currentImage = record.prescription_image;
                    previewImg.src = currentImage;
                    previewImg.classList.add('show');
                    uploadStatus.className = 'upload-status';
                    uploadStatus.innerHTML = '<i class="fas fa-check-circle"></i> Uploaded';
                } else resetImage();
                submitBtn.innerHTML = '<i class="fas fa-pen"></i> Update';
                cancelBtn.style.display = 'inline-flex';
                showToast(`Editing "${record.patient_name}"`, false);
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
                    showToast('Form cleared', false);
                }
            }

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
                    `👨⚕ Doctor name : ${doc}`,
                    `📞Doctor number:not available`,
                    `📅 Date : ${dateVal}`,
                    '',
                    `🕒 Emergency triggered : ${timeFormatted}`,
                    `⚠️ PRIORITY : HIGH — Immediate doctor attention required`,
                    '',
                    '— Sent via KSR GURU JI Emergency Button'
                ];
                const msg = encodeURIComponent(lines.join('\n'));
                getWhatsAppNumbers().forEach(num => {
                    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
                });
                showToast('Emergency alert sent to doctor via WhatsApp!', false);
                const btn = document.getElementById('medEmergencyBtn');
                btn.textContent = 'Emergency Sent!';
                btn.style.background = '#7f1d1d';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-ambulance"></i> Emergency · Request Doctor';
                    btn.style.background = '';
                }, 3000);
            }

            function triggerMedicalSearch() {
                const keyword = searchInput.value.toLowerCase().trim();
                if (!keyword) filteredRecords = [...records];
                else filteredRecords = records.filter(r =>
                    r.patient_name.toLowerCase().includes(keyword) ||
                    r.shop_name.toLowerCase().includes(keyword) ||
                    (r.suggestion && r.suggestion.toLowerCase().includes(keyword)) ||
                    (r.diagnosis && r.diagnosis.toLowerCase().includes(keyword))
                );
                medPage = 1;
                render();
            }

            function exportMedicalCSV() {
                if (records.length === 0) {
                    showToast('No data to export', true);
                    return;
                }
                const headers = ['Patient', 'Age', 'Gender', 'Shop', 'Symptoms', 'Meds', 'Diagnosis', 'Doctor', 'Date', 'Suggestion', 'Payment', 'PaymentStatus', 'UPI_ID'];
                const rows = records.map(r => [
                    r.patient_name, r.age, r.gender, r.shop_name,
                    r.symptoms || '', r.suggested_meds || '', r.diagnosis || '',
                    r.doctor_name || '', r.date || '', r.suggestion || '',
                    r.payment || 'Cash on Delivery', r.payment_status || '', r.upi_id || ''
                ]);
                let csv = headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
                const blob = new Blob([csv], {
                    type: 'text/csv'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'medical_records.csv';
                a.click();
                URL.revokeObjectURL(url);
                showToast('CSV exported', false);
            }

            function printMedical() {
                if (records.length === 0) {
                    showToast('No data to print', true);
                    return;
                }
                let content = '<h2>Medical Suggestion Records</h2><ul>';
                records.forEach(r => {
                    content += `<li><strong>${escapeHtml(r.patient_name)}</strong> (${r.age}y, ${r.gender}) – ${escapeHtml(r.shop_name)} – ${escapeHtml(r.suggestion || '')} – Payment: ${r.payment || 'Cash on Delivery'} – Status: ${r.payment_status || 'N/A'}</li>`;
                });
                content += '</ul>';
                const win = window.open('', '_blank');
                win.document.write(`<html><head><title>Medical Records</title><style>body{font-family:Inter,sans-serif;padding:20px;}</style></head><body>${content}</body></html>`);
                win.document.close();
                win.print();
            }

            // Events
            form.addEventListener('submit', addOrUpdate);
            cancelBtn.addEventListener('click', cancelEdit);
            clearFormBtn.addEventListener('click', clearForm);
            emergencyBtn.addEventListener('click', triggerEmergency);
            searchInput.addEventListener('input', triggerMedicalSearch);
            sortSelect.addEventListener('change', render);
            document.getElementById('medExportCSV').addEventListener('click', exportMedicalCSV);
            document.getElementById('medPrint').addEventListener('click', printMedical);
            window.triggerMedicalSearch = triggerMedicalSearch;

            document.getElementById('emergencyTrigger').addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('.tab-btn[data-tab="medical"]').click();
                setTimeout(() => {
                    document.getElementById('medEmergencyBtn').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    document.getElementById('medEmergencyBtn').style.transform = 'scale(1.04)';
                    setTimeout(() => {
                        document.getElementById('medEmergencyBtn').style.transform = '';
                    }, 500);
                    showToast('Tap the red Emergency button to call a doctor.', false);
                }, 300);
            });

            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    if (document.getElementById('tab-medical').classList.contains('active')) {
                        e.preventDefault();
                        addOrUpdate(e);
                    }
                }
            });

            if (!date.value) {
                const today = new Date().toISOString().split('T')[0];
                date.value = today;
            }
            loadMedical();
        })();

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  EMERGENCY ORDERS (image preview)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('emergencyPrescriptionInput');
            const preview = document.getElementById('emergencyPreview');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.innerHTML = `<img src="${e.target.result}" alt="Prescription preview" style="max-width:200px;max-height:200px;border:1px solid #ddd;border-radius:8px;padding:5px;">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.innerHTML = '';
                    }
                });
            }
        });

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  CLOSE IMAGE VIEWER ON ESCAPE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeImageViewer();
        });
    </script>
</body>

</html>