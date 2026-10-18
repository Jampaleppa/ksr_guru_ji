<?php
// ============================================================
// track-order.php - Customer Order Tracking Search Page
// ============================================================
require_once 'config.php';
require_once 'functions.php';

session_start();

$error = '';
$order_ref = trim($_GET['order_ref'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_ref = trim($_POST['order_ref'] ?? '');
    if (empty($order_ref)) {
        $error = 'Please enter a valid Order Number or ID.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = ? OR order_number = ?");
            $stmt->execute([$order_ref, $order_ref]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Redirect to delivery/tracking page
                header("Location: delivery/tracking.php?order=" . urlencode($order['order_number']));
                exit;
            } else {
                $error = 'No order found with that Number or ID. Please check and try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error code: ' . $e->getCode();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order - KSR GURU JI</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --primary: #0F7B3A;
            --primary-dark: #0A5E2C;
            --primary-light: #E8F5E9;
            --secondary: #E85D2C;
            --text-dark: #1A1F2E;
            --text-muted: #5A6A7E;
            --bg-body: #F0F4F8;
            --white: #FFFFFF;
            --border-radius: 16px;
            --transition: 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        body {
            background: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .tracking-card {
            background: var(--white);
            max-width: 480px;
            width: 100%;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(15, 123, 58, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
            text-align: center;
        }

        .logo-wrap {
            margin-bottom: 24px;
        }

        .logo-wrap img {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
            box-shadow: 0 4px 10px rgba(15, 123, 58, 0.15);
        }

        h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.1rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border-radius: 12px;
            border: 2px solid #E2E8F0;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 123, 58, 0.12);
        }

        .btn-track {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, #1EA85A 100%);
            color: var(--white);
            border: none;
            padding: 14px 20px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 123, 58, 0.2);
            transition: var(--transition);
        }

        .btn-track:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(15, 123, 58, 0.3);
        }

        .btn-track:active {
            transform: translateY(0);
        }

        .error-alert {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }

        .back-home {
            display: inline-block;
            margin-top: 24px;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .back-home:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="tracking-card">
        <div class="logo-wrap" onclick="window.location.href='index.php'" style="cursor: pointer; display: flex; justify-content: center; margin-bottom: 24px;">
            <div class="logo-image" style="width: 72px; height: 72px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px rgba(15, 123, 58, 0.15); border: 2px solid rgba(255, 255, 255, 0.8);">
                <img src="company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';" />
            </div>
        </div>
        <h2>Track Order</h2>
        <p class="subtitle">Enter your Order ID/Number to view live status</p>
        
        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="track-order.php">
            <div class="form-group">
                <label for="order_ref">Order ID or Number</label>
                <div class="input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="order_ref" name="order_ref" placeholder="e.g. KSR-2026-0001" value="<?= htmlspecialchars($order_ref) ?>" required>
                </div>
            </div>
            
            <button type="submit" class="btn-track">Track Now</button>
        </form>
        
        <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
    </div>
</body>
</html>
