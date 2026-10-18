<?php
// ============================================================
// admin/notification.php - Notification Management
// ============================================================
$currentPage = 'notification';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// --- HANDLE ACTIONS ---
$message = '';
$error = '';

// Send Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $type = $_POST['type'] ?? 'general';
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $link = trim($_POST['link'] ?? '');
    
    if (empty($subject) || empty($message_text)) {
        $error = 'Subject and message are required.';
    } else {
        try {
            if ($user_id > 0) {
                // Send to specific user
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, subject, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$user_id, $type, $subject, $message_text, $link]);
                $message = 'Notification sent to user #' . $user_id;
            } else {
                // Send to all users (broadcast)
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, subject, message, link, is_read) 
                    SELECT id, ?, ?, ?, ?, 0 FROM users WHERE role = 'customer'
                ");
                $stmt->execute([$type, $subject, $message_text, $link]);
                $count = $stmt->rowCount();
                $message = 'Notification broadcasted to ' . $count . ' customers.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    header('Location: notification.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

// Mark as Read
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?")->execute([$id]);
    header('Location: notification.php');
    exit;
}

// Mark All as Read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0")->execute();
    header('Location: notification.php');
    exit;
}

// Delete Notification
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
    header('Location: notification.php');
    exit;
}

// Clear All Notifications
if (isset($_GET['action']) && $_GET['action'] === 'clear_all') {
    $pdo->prepare("DELETE FROM notifications")->execute();
    header('Location: notification.php');
    exit;
}

// --- FETCH DATA ---
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT n.*, u.fullname as user_name, u.email as user_email 
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id";
if ($filter === 'unread') {
    $sql .= " WHERE n.is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " WHERE n.is_read = 1";
}
$sql .= " ORDER BY n.created_at DESC";
$notifications = $pdo->query($sql)->fetchAll();

// Stats
$total = count($notifications);
$unread = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$read = $total - $unread;

// Fetch users for dropdown
$users = $pdo->query("SELECT id, fullname AS name, email FROM users WHERE role = 'customer' ORDER BY fullname ASC")->fetchAll();

// Handle messages
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== SAME SIDEBAR STYLES ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root { --sidebar-bg: #0f172a; --accent: #3b82f6; --main-bg: #f1f5f9; --card-bg: #ffffff; --text-primary: #0f172a; --text-secondary: #475569; --border-light: #e2e8f0; --shadow: 0 10px 25px -5px rgba(0,0,0,0.05); --radius-lg: 1rem; --radius-md: 0.75rem; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; }
        body { font-family: 'Inter', sans-serif; background: var(--main-bg); color: var(--text-primary); }
        .sidebar { width: 280px; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; bottom: 0; z-index: 1000; transition: all 0.3s ease; display: flex; flex-direction: column; overflow-y: auto; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .admin-logo { display: flex; align-items: center; gap: 12px; }
        .admin-logo i { font-size: 28px; color: var(--accent); }
        .admin-logo h2 { font-size: 1.3rem; font-weight: 700; color: white; }
        .admin-logo p { font-size: 0.65rem; color: #94a3b8; }
        .admin-profile { padding: 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size: 42px; color: #94a3b8; }
        .profile-info h3 { font-size: 0.95rem; font-weight: 600; color: white; }
        .profile-info p { font-size: 0.7rem; color: #94a3b8; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-section h4 { padding: 0 20px; font-size: 0.65rem; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 9px 20px; color: #e2e8f0; transition: 0.2s; font-size: 0.9rem; border-left: 3px solid transparent; text-decoration: none; }
        .nav-item i { width: 22px; text-align: center; }
        .nav-item.active { background: rgba(59,130,246,0.15); border-left-color: var(--accent); color: white; }
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .sidebar-footer { padding: 16px 18px; border-top: 1px solid rgba(255,255,255,0.08); }
        .logout-btn { width: 100%; background: rgba(239,68,68,0.15); border: none; padding: 10px; border-radius: 12px; color: #f87171; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s; cursor: pointer; }
        .logout-btn:hover { background: rgba(239,68,68,0.3); }
        .main-content { margin-left: 280px; padding: 20px 28px; min-height: 100vh; }
        .top-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .header-left h1 { font-size: 1.6rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-left h1 i { color: var(--accent); }
        .breadcrumb { color: var(--text-secondary); font-size: 0.8rem; }
        .header-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .user-menu { display: flex; align-items: center; gap: 10px; background: var(--card-bg); padding: 4px 14px 4px 10px; border-radius: 50px; cursor: pointer; box-shadow: var(--shadow); border: 1px solid var(--border-light); }
        .user-avatar i { font-size: 28px; color: var(--accent); }
        .user-info .user-name { font-weight: 600; font-size: 0.85rem; }
        .user-info .user-role { font-size: 0.65rem; color: var(--text-secondary); display: block; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 16px 18px; box-shadow: var(--shadow); border: 1px solid var(--border-light); }
        .stat-card .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); font-weight: 600; }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 800; margin: 4px 0; }
        .stat-card .stat-icon { float: right; font-size: 1.4rem; opacity: 0.6; }
        .btn { padding: 8px 20px; border-radius: 40px; border: none; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline { background: transparent; border: 1px solid var(--border-light); color: var(--text-primary); }
        .btn-outline:hover { background: var(--main-bg); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        .filter-tabs { display: flex; gap: 4px; margin-bottom: 20px; background: var(--card-bg); border-radius: var(--radius-lg); padding: 6px; box-shadow: var(--shadow); border: 1px solid var(--border-light); }
        .filter-tabs .tab { padding: 8px 20px; border-radius: var(--radius-md); cursor: pointer; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; border: none; background: transparent; color: var(--text-secondary); text-decoration: none; }
        .filter-tabs .tab i { margin-right: 8px; }
        .filter-tabs .tab:hover { background: var(--main-bg); }
        .filter-tabs .tab.active { background: var(--accent); color: white; }
        .notification-item { display: flex; align-items: flex-start; gap: 16px; padding: 14px 16px; border-bottom: 1px solid var(--border-light); transition: background 0.2s; }
        .notification-item:hover { background: var(--main-bg); }
        .notification-item.unread { border-left: 3px solid var(--accent); background: #f0f7ff; }
        .notification-item .icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .notification-item .icon.general { background: #dbeafe; color: #1e40af; }
        .notification-item .icon.order { background: #fef3c7; color: #b45309; }
        .notification-item .icon.delivery { background: #d1fae5; color: #065f46; }
        .notification-item .icon.payment { background: #e0e7ff; color: #4f46e5; }
        .notification-item .content { flex: 1; }
        .notification-item .content .subject { font-weight: 600; }
        .notification-item .content .message { font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px; }
        .notification-item .content .meta { font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px; }
        .notification-item .actions { display: flex; gap: 6px; flex-shrink: 0; }
        .notification-item .actions .action-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: 0.2s; }
        .notification-item .actions .action-btn:hover { background: var(--main-bg); color: var(--accent); }
        .notification-item .actions .action-btn.danger:hover { color: var(--danger); }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--card-bg); border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.25s ease; }
        @keyframes modalIn { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid var(--border-light); }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.3rem; color: var(--text-secondary); cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: 0.2s; }
        .modal-close:hover { background: var(--main-bg); }
        .modal-body { padding: 20px 22px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 10px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-light); background: var(--main-bg); color: var(--text-primary); font-size: 0.9rem; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 12px 24px; border-radius: 40px; color: white; font-weight: 500; font-size: 0.9rem; z-index: 5000; box-shadow: 0 8px 30px rgba(0,0,0,0.2); animation: toastIn 0.3s ease; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.info { background: var(--accent); }
        @keyframes toastIn { from { transform: translateX(-50%) translateY(20px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); }
        .empty-state i { font-size: 4rem; opacity: 0.3; margin-bottom: 16px; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.mobile-open { transform: translateX(0); } .main-content { margin-left: 0; padding: 16px; } .notification-item { flex-wrap: wrap; } .notification-item .actions { margin-left: auto; } }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'notification'; include 'inc/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left"><h1><i class="fas fa-bell"></i> Notifications</h1><div class="breadcrumb">Admin / Notifications</div></div>
            <div class="header-right">
                <button class="btn btn-primary" id="sendNotificationBtn"><i class="fas fa-plus"></i> Send Notification</button>
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Admin</span></div>
                </div>
            </div>
        </header>

        <?php if ($message): ?><div class="toast show success" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="toast show error" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value"><?= $total ?></div><div class="stat-icon"><i class="fas fa-bell" style="color:var(--accent);"></i></div></div>
            <div class="stat-card"><div class="stat-label">Unread</div><div class="stat-value" style="color:var(--warning);"><?= $unread ?></div><div class="stat-icon"><i class="fas fa-circle" style="color:var(--warning);"></i></div></div>
            <div class="stat-card"><div class="stat-label">Read</div><div class="stat-value" style="color:var(--success);"><?= $read ?></div><div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success);"></i></div></div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="notification.php?filter=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>"><i class="fas fa-list"></i> All</a>
            <a href="notification.php?filter=unread" class="tab <?= $filter === 'unread' ? 'active' : '' ?>"><i class="fas fa-circle" style="color:var(--warning);"></i> Unread</a>
            <a href="notification.php?filter=read" class="tab <?= $filter === 'read' ? 'active' : '' ?>"><i class="fas fa-check-circle" style="color:var(--success);"></i> Read</a>
            <div style="flex:1;"></div>
            <a href="notification.php?action=mark_all_read" class="btn btn-sm btn-outline"><i class="fas fa-check-double"></i> Mark All Read</a>
            <a href="notification.php?action=clear_all" class="btn btn-sm btn-danger" onclick="return confirm('Delete all notifications?')"><i class="fas fa-trash"></i> Clear All</a>
        </div>

        <!-- Notifications List -->
        <div class="table-container" style="background:var(--card-bg);border-radius:var(--radius-lg);box-shadow:var(--shadow);border:1px solid var(--border-light);overflow:hidden;">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications found</h3>
                    <p>When you send notifications, they will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="icon <?= $n['type'] ?>">
                            <i class="fas fa-<?= $n['type'] === 'order' ? 'shopping-cart' : ($n['type'] === 'delivery' ? 'truck' : ($n['type'] === 'payment' ? 'credit-card' : 'bell')) ?>"></i>
                        </div>
                        <div class="content">
                            <div class="subject"><?= htmlspecialchars($n['subject']) ?></div>
                            <div class="message"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="meta">
                                <?php if ($n['user_name']): ?>
                                    To: <?= htmlspecialchars($n['user_name']) ?> (<?= htmlspecialchars($n['user_email']) ?>) ·
                                <?php else: ?>
                                    To: All Customers ·
                                <?php endif; ?>
                                <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                                <?php if ($n['is_read']): ?>
                                    · <span style="color:var(--success);">✓ Read</span>
                                <?php else: ?>
                                    · <span style="color:var(--warning);">Unread</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="actions">
                            <?php if (!$n['is_read']): ?>
                                <a href="notification.php?action=mark_read&id=<?= $n['id'] ?>" class="action-btn" title="Mark as read"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="notification.php?action=delete&id=<?= $n['id'] ?>" class="action-btn danger" title="Delete" onclick="return confirm('Delete this notification?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===== MODAL: Send Notification ===== -->
    <div class="modal-overlay" id="notificationModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-bell"></i> Send Notification</h3>
                <button class="modal-close" onclick="closeModal('notificationModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Recipient</label>
                        <select name="user_id" id="notifyUser">
                            <option value="0">All Customers (Broadcast)</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name'] ?? '') ?> (<?= htmlspecialchars($u['email'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="general">General</option>
                            <option value="order">Order</option>
                            <option value="delivery">Delivery</option>
                            <option value="payment">Payment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" placeholder="Notification subject" required>
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="4" placeholder="Type your message here..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Link (optional)</label>
                        <input type="url" name="link" placeholder="https://example.com/order/123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('notificationModal')">Cancel</button>
                    <button type="submit" name="send_notification" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        document.getElementById('sendNotificationBtn').addEventListener('click', function() {
            openModal('notificationModal');
        });

        // Close modal on backdrop click
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal('notificationModal');
        });

        // Auto-hide toast
        setTimeout(() => {
            document.querySelectorAll('.toast.show').forEach(toast => {
                setTimeout(() => toast.style.opacity = '0', 4000);
            });
        }, 1000);
    </script>
</body>
</html>