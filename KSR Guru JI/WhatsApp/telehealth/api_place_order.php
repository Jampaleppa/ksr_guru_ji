<?php
header('Content-Type: application/json');
require_once 'helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getAssistDB();
    
    // Check if orders table exists, if not, create it
    $sqlSchema = "
    CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) NOT NULL UNIQUE,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NULL,
        state VARCHAR(100) NULL,
        pincode VARCHAR(10) NULL,
        total DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100) NULL,
        payment_status VARCHAR(50) DEFAULT 'pending',
        notes TEXT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        driver_id INT NULL,
        delivery_boy_id INT NULL,
        placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sqlSchema);
    
    // Generate secure order ID
    $order_num = 'MED-' . date('ymd') . '-' . rand(1000, 9999);
    
    $payMethod = trim($_POST['payment_method'] ?? 'UPI');
    $txnId = trim($_POST['transaction_id'] ?? '');
    if (!$txnId) {
        $txnId = (stripos($payMethod, 'Cash') !== false) ? ('COD-' . rand(100000, 999999)) : ('UPI-' . rand(1000000000, 9999999999));
    }
    $payStatus = (stripos($payMethod, 'Cash') !== false || stripos($payMethod, 'COD') !== false) ? 'pending' : 'completed';
    $total = isset($_POST['total']) ? (float)$_POST['total'] : 315.00;
    
    $stmt = $pdo->prepare("INSERT INTO orders (
        order_number, name, email, phone, address, total, payment_method, transaction_id, payment_status, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $order_num,
        'Jampaleppa Rathod', 
        'patient@example.com', 
        '8088584421', 
        '#42 Healthcare Layout, Main Road, Gajendragad', 
        $total,
        $payMethod, 
        $txnId,
        $payStatus, 
        'pending' // Dispatch status is pending
    ]);
    
    $orderId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true, 
        'order_number' => $order_num, 
        'id' => $orderId,
        'payment_method' => $payMethod,
        'payment_status' => $payStatus,
        'transaction_id' => $txnId
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
