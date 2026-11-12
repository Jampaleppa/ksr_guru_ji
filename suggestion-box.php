<?php
// ============================================================
// suggestion-box.php - Missing Product Suggestion Form
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$prefillProduct = trim((string)($_GET['product'] ?? $_POST['product_name'] ?? ''));
$isNotFoundRedirect = isset($_GET['not_found']) && $_GET['not_found'] === '1';

$success = false;
$error = '';
$requestId = '';
$productName = '';
$customerPhone = '';

// Load categories
$categories = [];
try {
    $pdo = getDBConnection();
    $categories = $pdo->query("SELECT id, name, kn_name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignore) {}

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_suggestion') {
    $productName = trim((string)($_POST['product_name'] ?? ''));
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $details = trim((string)($_POST['details'] ?? ''));
    $preferredPrice = !empty($_POST['preferred_price']) ? (float)$_POST['preferred_price'] : null;
    $customerName = trim((string)($_POST['customer_name'] ?? ''));
    $customerPhone = preg_replace('/[^0-9]/', '', (string)($_POST['customer_phone'] ?? ''));
    $customerEmail = trim((string)($_POST['customer_email'] ?? ''));

    if (empty($productName)) {
        $error = 'Please enter the name of the product you are looking for.';
    } elseif (strlen($customerPhone) < 10) {
        $error = 'Please enter a valid 10-digit mobile number so we can notify you.';
    } else {
        try {
            $pdo = getDBConnection();

            // Resolve category name
            $categoryName = null;
            if ($categoryId) {
                $st = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                $st->execute([$categoryId]);
                $categoryName = $st->fetchColumn() ?: null;
            }

            // Generate Unique Request ID: KSR-REQ-YEAR-XXXXX
            $year = date('Y');
            $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
            $requestId = "KSR-SUGG-{$year}-{$randomSuffix}";
            $userId = $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO product_suggestions (
                    request_id, user_id, product_name, category_id, category_name,
                    details, preferred_price, customer_name, customer_phone, customer_email,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $requestId,
                $userId,
                $productName,
                $categoryId,
                $categoryName,
                $details,
                $preferredPrice,
                $customerName,
                $customerPhone,
                $customerEmail
            ]);

            $success = true;
        } catch (Throwable $e) {
            error_log("Failed to save product suggestion: " . $e->getMessage());
            $error = 'Could not save your suggestion at this moment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest a Missing Product · KSR GURU JI</title>
    <link rel="icon" href="/company_logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Kannada:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
            max-width: 900px;
            margin: 0 auto;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        .main-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
            width: 100%;
            flex: 1;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #0F7B3A, #0A5E2C);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .card-header h1 { font-size: 1.6rem; margin-bottom: 8px; font-weight: 800; }
        .card-header p { opacity: 0.9; font-size: 0.95rem; }
        .card-body { padding: 32px; }
        .notice-banner {
            background: #FEF3C7;
            border: 1px solid #FCD34D;
            color: #92400E;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .form-label .req { color: #dc2626; }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text-dark);
            background: #fff;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15,123,58,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .btn-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover { background: var(--primary-dark); }
        .alert-error {
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .success-box {
            text-align: center;
            padding: 40px 20px;
        }
        .success-icon {
            width: 70px;
            height: 70px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        .ref-badge {
            background: #F1F5F9;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 800;
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--primary-dark);
            margin: 15px 0 25px;
        }
        .workflow-steps {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin: 25px 0;
            font-size: 0.9rem;
        }
        .workflow-steps ol { padding-left: 20px; margin-top: 8px; }
        .workflow-steps li { margin-bottom: 6px; }
        .btn-home {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
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
            <a href="/" style="text-decoration:none; color:var(--text-muted); font-weight:600; font-size:0.9rem;">
                <i class="fas fa-arrow-left"></i> Back to Store
            </a>
        </div>
    </header>

    <main class="main-container">
        <div class="card">
            <div class="card-header">
                <h1>Suggest a Missing Product</h1>
                <p>ಉತ್ಪನ್ನ ಸಲಹೆ ಪೆಟ್ಟಿಗೆ · Tell us what you need and our sourcing team will acquire it for you!</p>
            </div>

            <div class="card-body">
                <?php if ($success): ?>
                    <div class="success-box">
                        <div class="success-icon"><i class="fas fa-check"></i></div>
                        <h2>Thank You! Your Request Has Been Submitted</h2>
                        <p style="color:var(--text-muted); margin-top:6px;">We have logged your product sourcing request in our system.</p>
                        <div class="ref-badge"><?php echo htmlspecialchars($requestId); ?></div>

                        <?php
                            $waText = urlencode("Hello KSR GURU JI! I have submitted a product sourcing request.\n\n*Request ID:* {$requestId}\n*Product:* {$productName}\n*Phone:* {$customerPhone}\n\nPlease check availability.");
                            $waLink = "https://wa.me/918762474406?text={$waText}";
                        ?>
                        <div style="background:#e8f5e9; border:1px solid #c8e6c9; border-radius:12px; padding:18px; margin:20px 0; text-align:center;">
                            <p style="color:#1b5e20; font-weight:700; margin-bottom:10px;">
                                <i class="fab fa-whatsapp" style="font-size:1.3rem; color:#25d366; vertical-align:middle; margin-right:6px;"></i>
                                Want faster assistance or instant confirmation?
                            </p>
                            <a href="<?php echo $waLink; ?>" target="_blank" style="display:inline-flex; align-items:center; gap:8px; background:#25d366; color:#fff; padding:10px 20px; border-radius:30px; text-decoration:none; font-weight:700; font-size:0.95rem; box-shadow:0 3px 10px rgba(37,211,102,0.3);">
                                <i class="fab fa-whatsapp"></i> Chat directly on WhatsApp (8762474406)
                            </a>
                        </div>

                        <div class="workflow-steps">
                            <strong>What happens next?</strong>
                            <ol>
                                <li><strong>Admin Review:</strong> Our procurement team verifies product availability, brand specs & wholesale sourcing pricing.</li>
                                <li><strong>Delivery Partner Fulfillment:</strong> Once approved, the assigned delivery partner coordinates local pickup or catalog stocking.</li>
                                <li><strong>SMS / WhatsApp Update:</strong> We will contact you at your phone number when the product is ready to order!</li>
                            </ol>
                        </div>

                        <a href="/" class="btn-home"><i class="fas fa-store"></i> Continue Shopping</a>
                    </div>
                <?php else: ?>

                    <?php if ($isNotFoundRedirect): ?>
                        <div class="notice-banner">
                            <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
                            <div>
                                <strong>We couldn't find "<?php echo htmlspecialchars($prefillProduct); ?>" in our catalog!</strong><br>
                                Fill in the details below, or <a href="https://wa.me/918762474406?text=<?php echo urlencode("Hello KSR GURU JI! I am looking for product: {$prefillProduct}. Please check availability."); ?>" target="_blank" style="color:#0f7b3a; font-weight:700; text-decoration:underline;"><i class="fab fa-whatsapp"></i> WhatsApp us at 8762474406</a> directly.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="/suggestion-box.php" method="POST">
                        <input type="hidden" name="action" value="submit_suggestion">

                        <div class="form-group">
                            <label class="form-label">Product Name / ವಿವರ <span class="req">*</span></label>
                            <input type="text" name="product_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($prefillProduct); ?>" 
                                   placeholder="e.g. Almond Oil 500ml, Organic Ragi Flour, Mysore Sandal Soap..." required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Category / ವರ್ಗ</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-- Select Category (Optional) --</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?> <?php echo !empty($c['kn_name']) ? '(' . htmlspecialchars($c['kn_name']) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Target / Expected Price (₹)</label>
                                <input type="number" step="0.01" name="preferred_price" class="form-input" placeholder="e.g. 150.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Additional Details / Brand / Quantity</label>
                            <textarea name="details" class="form-textarea" rows="3" placeholder="Specify preferred brand (e.g. Tata, Aashirvaad), package size, urgent timeline, or special requirements..."></textarea>
                        </div>

                        <hr style="border:none; border-top:1px solid var(--border); margin:24px 0;">

                        <h3 style="font-size:1.05rem; margin-bottom:16px; color:var(--text-dark);">Your Contact Details</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Your Name</label>
                                <input type="text" name="customer_name" class="form-input" placeholder="Your full name"
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Mobile Phone Number <span class="req">*</span></label>
                                <input type="tel" name="customer_phone" class="form-input" placeholder="10-digit mobile number" 
                                       pattern="[0-9]{10}" maxlength="10" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address (Optional)</label>
                            <input type="email" name="customer_email" class="form-input" placeholder="name@example.com">
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Product Suggestion
                        </button>

                        <div style="text-align:center; margin-top:16px;">
                            <span style="color:var(--text-muted); font-size:0.9rem;">— OR —</span>
                            <div style="margin-top:10px;">
                                <a href="https://wa.me/918762474406?text=<?php echo urlencode("Hello KSR GURU JI! I need this product: " . ($prefillProduct ?: '')); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:8px; background:#25d366; color:#fff; padding:10px 20px; border-radius:30px; text-decoration:none; font-weight:700; font-size:0.9rem; box-shadow:0 3px 10px rgba(37,211,102,0.25);">
                                    <i class="fab fa-whatsapp" style="font-size:1.1rem;"></i> Quick Order via WhatsApp (8762474406)
                                </a>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.85rem;">
        &copy; <?php echo date('Y'); ?> KSR GURU JI · Dedicated to bringing all your essentials to your doorstep.
    </footer>
</body>
</html>

