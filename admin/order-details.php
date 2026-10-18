<?php
// ============================================================
// admin/ajax/order-details.php - AJAX Detail View with WhatsApp Channels
// ============================================================
// admin/ajax/order-details.php
// Access: admin RBAC, requires the `orders.view` permission.
$currentPage = 'orders';
require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div style="color:red;padding:12px;">Invalid Order ID.</div>';
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT o.*, 
               o.phone as customer_phone, o.name as customer_name, o.email as customer_email,
               d.fullname as driver_name, d.phone as driver_phone
        FROM orders o
        LEFT JOIN users d ON o.driver_id = d.id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo '<div style="color:red;padding:12px;">Order not found.</div>';
        exit;
    }

    // Fetch items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '<div style="color:red;padding:12px;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Build invoice URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME']; // e.g. /admin/ajax/order-details.php
$baseDir = str_replace('/admin/ajax/order-details.php', '', $scriptName);
$pdfFilename = 'Invoice_' . preg_replace('/[^A-Za-z0-9\-]/', '', $order['order_number']) . '.pdf';
$pdfUrl = $protocol . "://" . $host . $baseDir . "/invoices/" . $pdfFilename;

// Prepare WhatsApp numbers
$customerPhone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? $order['phone']);
if (strlen($customerPhone) === 10) {
    $customerPhone = '91' . $customerPhone;
}

// Message Templates
// 1. Order Confirmed + Invoice
$placedText = "Hello *" . $order['customer_name'] . "*, thank you for shopping with *KSR GURU JI*! 🛍️\n\n" .
              "Your order *" . $order['order_number'] . "* has been confirmed.\n" .
              "Total Amount: *₹" . number_format($order['total'], 2) . "*\n" .
              "Payment Method: *" . strtoupper($order['payment_method']) . "*\n\n" .
              "📄 *View/Download PDF Invoice:* \n" . $pdfUrl . "\n\n" .
              "We are preparing your order and will deliver it super fast!";
$waPlacedUrl = "https://wa.me/" . $customerPhone . "?text=" . urlencode($placedText);

// 2. Out for Delivery
$driverInfo = $order['driver_name'] ? "\nDelivery Boy: *" . $order['driver_name'] . "* (" . $order['driver_phone'] . ")" : "";
$deliveryText = "Hello *" . $order['customer_name'] . "*!\n\n" .
                "Your KSR GURU JI order *" . $order['order_number'] . "* is now *OUT FOR DELIVERY*! 🚀" .
                $driverInfo . "\n" .
                "Our team will arrive shortly within 45 minutes.\n\n" .
                "Total Payable: *₹" . number_format($order['total'], 2) . "*\n" .
                "Payment Method: *" . strtoupper($order['payment_method']) . "*\n\n" .
                "Need help? Contact: 8088584421.";
$waDeliveryUrl = "https://wa.me/" . $customerPhone . "?text=" . urlencode($deliveryText);

// 3. Delivered & Payment Verified
$completedText = "Dear *" . $order['customer_name'] . "*,\n\n" .
                 "Your KSR GURU JI order *" . $order['order_number'] . "* has been successfully *DELIVERED*! ✅\n" .
                 "Payment of *₹" . number_format($order['total'], 2) . "* is confirmed.\n\n" .
                 "Thank you for shopping with KSR GURU JI! Come back soon! 🙏";
$waCompletedUrl = "https://wa.me/" . $customerPhone . "?text=" . urlencode($completedText);
?>

<div style="font-family:inherit;">
    <!-- Two Column Grid -->
    <div style="display:grid;grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
        <!-- Left Side: Order Info -->
        <div>
            <h4 style="margin-bottom:10px;color:#1e293b;border-bottom:1px solid #e2e8f0;padding-bottom:6px;">Order Information</h4>
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                <tr><td style="padding:4px 0;color:#64748b;width:120px;">Order Number</td><td style="font-weight:600;">#<?= htmlspecialchars($order['order_number']) ?></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Created At</td><td><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Method</td><td><span style="background:#e2e8f0;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:600;"><?= strtoupper($order['payment_method']) ?></span></td></tr>
                <?php if (!empty($order['transaction_id'])): ?>
                <tr><td style="padding:4px 0;color:#64748b;">Transaction/UTR ID</td><td style="font-weight:700;color:#2563eb;font-family:monospace;font-size:0.9rem;"><?= htmlspecialchars($order['transaction_id']) ?></td></tr>
                <?php endif; ?>
                <tr><td style="padding:4px 0;color:#64748b;">Payment Status</td><td><span class="payment-badge <?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Order Status</td><td><span class="status-badge <?= $order['status'] ?>"><?= ucfirst(str_replace('_', ' ', $order['status'])) ?></span></td></tr>
            </table>
        </div>
        
        <!-- Right Side: customer Info -->
        <div>
            <h4 style="margin-bottom:10px;color:#1e293b;border-bottom:1px solid #e2e8f0;padding-bottom:6px;">Customer Details</h4>
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                <tr><td style="padding:4px 0;color:#64748b;width:100px;">Name</td><td style="font-weight:600;"><?= htmlspecialchars($order['customer_name']) ?></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Phone</td><td><?= htmlspecialchars($order['customer_phone']) ?></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Email</td><td><?= htmlspecialchars($order['customer_email'] ?: '—') ?></td></tr>
                <tr><td style="padding:4px 0;color:#64748b;">Address</td><td><?= htmlspecialchars($order['address']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['pincode']) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Order Notes -->
    <?php if (!empty($order['notes'])): ?>
        <div style="background:#fff8e6; border-left:4px solid #f5a623; padding:10px 14px; border-radius:8px; margin-bottom:20px; font-size:0.85rem;">
            <strong>Order Notes:</strong> <?= htmlspecialchars($order['notes']) ?>
        </div>
    <?php endif; ?>

    <!-- Items List -->
    <h4 style="margin-bottom:10px;color:#1e293b;border-bottom:1px solid #e2e8f0;padding-bottom:6px;">Order Items</h4>
    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-bottom:20px;">
        <thead>
            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                <th style="padding:8px;text-align:left;">Product</th>
                <th style="padding:8px;text-align:center;width:80px;">Quantity</th>
                <th style="padding:8px;text-align:right;width:100px;">Price</th>
                <th style="padding:8px;text-align:right;width:100px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:8px;"><?= htmlspecialchars($item['product_name']) ?></td>
                    <td style="padding:8px;text-align:center;"><?= $item['quantity'] ?></td>
                    <td style="padding:8px;text-align:right;">₹<?= number_format($item['price'], 2) ?></td>
                    <td style="padding:8px;text-align:right;">₹<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2" style="border:none;"></td>
                <td style="padding:8px;text-align:right;font-weight:600;border-top:2px solid #e2e8f0;">Grand Total:</td>
                <td style="padding:8px;text-align:right;font-weight:700;color:#28a745;border-top:2px solid #e2e8f0;font-size:1rem;">₹<?= number_format($order['total'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Driver Actions -->
    <?php if ($order['driver_name']): ?>
        <div style="background:#f0faf3; border:1px solid #bbf7d0; padding:12px; border-radius:8px; display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; font-size:0.85rem;">
            <div>
                <strong>Assigned Hand:</strong> <?= htmlspecialchars($order['driver_name']) ?> (<?= htmlspecialchars($order['driver_phone']) ?>)
            </div>
            <?php if (!empty($order['tracking_number'])): ?>
                <div>
                    <strong>Tracking:</strong> <?= htmlspecialchars($order['tracking_number']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- WhatsApp Share Panel -->
    <h4 style="margin-bottom:10px;color:#1e293b;border-bottom:1px solid #e2e8f0;padding-bottom:6px;"><i class="fab fa-whatsapp" style="color:#25d366;"></i> WhatsApp Notifications Center</h4>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <a href="<?= $waPlacedUrl ?>" target="_blank" style="background:#25d366;color:white;text-decoration:none;padding:8px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:0.2s;">
            <i class="fab fa-whatsapp"></i> Confirm &amp; Send Invoice PDF
        </a>
        <a href="<?= $waDeliveryUrl ?>" target="_blank" style="background:#3b82f6;color:white;text-decoration:none;padding:8px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:0.2s;">
            <i class="fas fa-shipping-fast"></i> Notify Out for Delivery
        </a>
        <a href="<?= $waCompletedUrl ?>" target="_blank" style="background:#10b981;color:white;text-decoration:none;padding:8px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:0.2s;">
            <i class="fas fa-check-double"></i> Confirm Payment &amp; Delivery
        </a>
        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" style="background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1;text-decoration:none;padding:8px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:0.2s;">
            <i class="fas fa-file-pdf" style="color:#ef4444;"></i> View Server Invoice PDF
        </a>
    </div>
</div>
