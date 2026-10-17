<?php
// ============================================================
// API/login.php - Delivery Boy Login API (JSON)
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include database configuration
require_once '../config.php';

// Connect to Database
try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// --- Rate Limiting (5 attempts per 15 minutes) ---
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE");
$stmt->execute([$ip]);
$attempts = $stmt->fetchColumn();

if ($attempts >= 5) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many failed attempts. Please wait 15 minutes.'
    ]);
    exit;
}

// --- Only POST requests ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// --- Get input ---
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback to form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
} else {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
}

// --- Validate input ---
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// --- Query database ---
try {
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, delivery_zone FROM users WHERE email = ? AND role = 'delivery'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Log failed attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
        $stmt->execute([$ip]);

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    // --- Login successful ---
    // Clear failed attempts
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);

    // Start session and set user data
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['delivery_boy_id'] = $user['id'];
    $_SESSION['delivery_boy_name'] = $user['name'];
    $_SESSION['delivery_boy_email'] = $user['email'];
    $_SESSION['delivery_boy_zone'] = $user['delivery_zone'] ?? '';

    // Generate a simple token (session ID)
    $token = session_id();

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'delivery_zone' => $user['delivery_zone']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}