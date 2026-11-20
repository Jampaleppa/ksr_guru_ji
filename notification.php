<?php
// ============================================================
// api/notification.php - Notification API Endpoint
// ============================================================
require_once '../config.php';
require_once '../functions.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response header
header('Content-Type: application/json');

// --- Helper: Check if user is admin ---
function isAdmin() {
    return isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
}

// --- Helper: Get current user ID (if logged in) ---
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// --- Get action from request ---
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- Handle actions ---
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $pdo = getDBConnection();

    // --- ACTION: SEND NOTIFICATION ---
    if ($action === 'send') {
        // Only admin can send
        if (!isAdmin()) {
            throw new Exception('Unauthorized: Admin access required.');
        }

        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            // Fallback to $_POST
            $data = $_POST;
        }

        $title = trim($data['title'] ?? '');
        $message = trim($data['message'] ?? '');
        $type = $data['type'] ?? 'info';
        $user_id = isset($data['user_id']) ? (int)$data['user_id'] : null; // null = broadcast
        $delivery_boy_id = isset($data['delivery_boy_id']) ? (int)$data['delivery_boy_id'] : null;

        if (empty($title) || empty($message)) {
            throw new Exception('Title and message are required.');
        }

        // Determine recipient
        if ($user_id !== null) {
            // Send to specific user
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $message, $type]);
            $recipient = "User ID: $user_id";
        } elseif ($delivery_boy_id !== null) {
            // Send to delivery boy (also stored in users table, role = 'delivery')
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$delivery_boy_id, $title, $message, $type]);
            $recipient = "Delivery Boy ID: $delivery_boy_id";
        } else {
            // Broadcast to all users (customer + delivery)
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (NULL, ?, ?, ?)");
            $stmt->execute([$title, $message, $type]);
            $recipient = "All Users (Broadcast)";
        }

        $response['success'] = true;
        $response['message'] = "Notification sent to $recipient";
        $response['notification_id'] = $pdo->lastInsertId();
    }

    // --- ACTION: LIST NOTIFICATIONS ---
    elseif ($action === 'list') {
        $user_id = getUserId();
        $isAdmin = isAdmin();

        if (!$user_id && !$isAdmin) {
            throw new Exception('Unauthorized: Please log in.');
        }

        // If admin, they can see all notifications (with optional user filter)
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

        if ($isAdmin && $filter_user_id !== null) {
            // Admin viewing specific user's notifications
            $sql = "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$filter_user_id, $limit, $offset]);
        } elseif ($isAdmin) {
            // Admin viewing all (including broadcasts)
            $sql = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
        } else {
            // Regular user: their own notifications + broadcasts
            $sql = "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $limit, $offset]);
        }

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unread count
        if ($isAdmin && $filter_user_id !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
            $stmt->execute([$filter_user_id]);
            $unreadCount = $stmt->fetchColumn();
        } elseif ($isAdmin) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
            $unreadCount = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
            $stmt->execute([$user_id]);
            $unreadCount = $stmt->fetchColumn();
        }

        $response['success'] = true;
        $response['notifications'] = $notifications;
        $response['unread_count'] = (int)$unreadCount;
    }

    // --- ACTION: MARK AS READ ---
    elseif ($action === 'mark_read') {
        $user_id = getUserId();
        $isAdmin = isAdmin();

        if (!$user_id && !$isAdmin) {
            throw new Exception('Unauthorized: Please log in.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }

        $notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : null;

        if (!$notification_id) {
            throw new Exception('Notification ID required.');
        }

        // Check ownership: user can mark only their own, admin can mark any
        if ($isAdmin) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([$notification_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
            $stmt->execute([$notification_id, $user_id]);
        }

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Notification marked as read.';
        } else {
            $response['message'] = 'Notification not found or not owned.';
        }
    }

    // --- ACTION: MARK ALL READ ---
    elseif ($action === 'mark_all_read') {
        $user_id = getUserId();
        $isAdmin = isAdmin();

        if (!$user_id && !$isAdmin) {
            throw new Exception('Unauthorized: Please log in.');
        }

        if ($isAdmin) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
            $stmt->execute([$user_id]);
        }

        $response['success'] = true;
        $response['message'] = 'All notifications marked as read.';
        $response['affected_rows'] = $stmt->rowCount();
    }

    // --- ACTION: DELETE NOTIFICATION (admin only) ---
    elseif ($action === 'delete') {
        if (!isAdmin()) {
            throw new Exception('Unauthorized: Admin access required.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }

        $notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : null;

        if (!$notification_id) {
            throw new Exception('Notification ID required.');
        }

        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$notification_id]);

        $response['success'] = true;
        $response['message'] = 'Notification deleted.';
    }

    else {
        $response['message'] = 'Unknown action. Available: send, list, mark_read, mark_all_read, delete';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;