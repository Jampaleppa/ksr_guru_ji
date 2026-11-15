<?php
// ============================================================
// admin/notifications.php — Notifications Management
// ============================================================
$currentPage = 'notifications';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo     = getDBConnection();
$message = '';
$error   = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'mark_all_read') {
            $pdo->exec("UPDATE notifications SET is_read = 1");
            $message = 'All notifications marked as read.';
        } elseif ($action === 'delete_old') {
            $deleted = $pdo->exec("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $message = "Deleted {$deleted} old notification(s) (>30 days).";
        } elseif ($action === 'mark_read' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->execute([(int) $_POST['id']]);
            $message = 'Notification marked as read.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$filterType = $_GET['filter'] ?? 'all';
$notifications = [];
$unreadCount   = 0;
try {
    // Check table exists
    $pdo->query("SELECT 1 FROM notifications LIMIT 1");
    // Build query
    $where = '';
    if ($filterType === 'unread') {
        $where = " WHERE is_read = 0";
    } elseif ($filterType === 'read') {
        $where = " WHERE is_read = 1";
    } elseif ($filterType === 'order') {
        $where = " WHERE type LIKE '%order%'";
    } elseif ($filterType === 'system') {
        $where = " WHERE type LIKE '%system%' OR type LIKE '%admin%'";
    }
    $notifications = $pdo->query("SELECT * FROM notifications{$where} ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    $unreadCount   = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
} catch (PDOException $e) {
    // Table may not exist yet — graceful fallback
    $notifications = [];
}

require_once 'inc/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">
            <i class="fas fa-bell text-warning"></i> Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?= $unreadCount ?> unread</span>
            <?php endif; ?>
        </h2>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
            </form>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Delete all notifications older than 30 days?')">
                <input type="hidden" name="action" value="delete_old">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-trash-alt"></i> Delete Old (&gt;30 days)
                </button>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php
        $filters = ['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'order' => 'Order', 'system' => 'System'];
        foreach ($filters as $k => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filterType === $k ? 'active' : '' ?>"
               href="?filter=<?= $k ?>">
                <?= $label ?>
                <?php if ($k === 'unread' && $unreadCount > 0): ?>
                    <span class="badge bg-danger"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
                    <p>No notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Type</th>
                                <th>Message</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($notifications as $n): ?>
                            <tr class="<?= $n['is_read'] ? '' : 'table-warning' ?>">
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($n['type'] ?? 'general') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(substr($n['message'] ?? '—', 0, 120)) ?></td>
                                <td><small><?= htmlspecialchars($n['user_name'] ?? $n['user_id'] ?? '—') ?></small></td>
                                <td>
                                    <?php if ($n['is_read']): ?>
                                        <span class="badge bg-secondary">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unread</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($n['created_at'] ?? '—') ?></small></td>
                                <td>
                                    <?php if (!$n['is_read']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                        <button class="btn btn-xs btn-outline-success" type="submit" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'inc/footer.php'; ?>
