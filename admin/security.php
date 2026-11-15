<?php
// ============================================================
// admin/security.php — Security & Audit Logs
// ============================================================
$currentPage = 'security';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Action', 'IP Address', 'User Agent', 'Created At']);
    try {
        $rows = $pdo->query("SELECT id, user_name, action, ip_address, user_agent, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['user_name'] ?? '', $r['action'] ?? '', $r['ip_address'] ?? '', $r['user_agent'] ?? '', $r['created_at'] ?? '']);
        }
    } catch (PDOException $e) {
        fputcsv($out, ['Error', $e->getMessage()]);
    }
    fclose($out);
    exit;
}

$filterType = $_GET['filter'] ?? 'all';
$logs       = [];
$summaryLogins  = 0;
$summaryFailed  = 0;
$summarySessions = 0;

try {
    // Summary cards
    $summaryLogins  = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $summaryFailed  = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%fail%' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $summarySessions = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();

    // Build filter
    $where = '';
    if ($filterType === 'login') {
        $where = " WHERE action LIKE '%login%'";
    } elseif ($filterType === 'admin') {
        $where = " WHERE action LIKE '%admin%' OR action LIKE '%update%' OR action LIKE '%delete%'";
    } elseif ($filterType === 'failed') {
        $where = " WHERE action LIKE '%fail%' OR action LIKE '%block%' OR action LIKE '%wrong%'";
    }
    $logs = $pdo->query("SELECT * FROM audit_logs{$where} ORDER BY created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist — graceful fallback
    $logs = [];
}

require_once 'inc/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fas fa-shield-alt text-danger"></i> Security & Audit Logs</h2>
        <a href="?export=csv" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-primary"><?= $summaryLogins ?></div>
                    <div class="text-muted">Total Actions Today</div>
                    <i class="fas fa-sign-in-alt text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-danger"><?= $summaryFailed ?></div>
                    <div class="text-muted">Failed Logins (24h)</div>
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-success"><?= $summarySessions ?></div>
                    <div class="text-muted">Active Sessions (1h)</div>
                    <i class="fas fa-users text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php
        $filters = ['all' => 'All Logs', 'login' => 'Login Activity', 'admin' => 'Admin Actions', 'failed' => 'Failed/Blocked'];
        foreach ($filters as $k => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filterType === $k ? 'active' : '' ?>" href="?filter=<?= $k ?>">
                <?= $label ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-shield-alt fa-3x mb-3 d-block"></i>
                    <p>No audit logs found. Logs will appear here as admin actions are performed.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Browser</th>
                                <th>Date &amp; Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $i => $log):
                            $isFailure = str_contains(strtolower($log['action'] ?? ''), 'fail') ||
                                         str_contains(strtolower($log['action'] ?? ''), 'wrong');
                        ?>
                            <tr class="<?= $isFailure ? 'table-danger' : '' ?>">
                                <td class="text-muted small"><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($log['user_name'] ?? $log['user_id'] ?? '—') ?></strong></td>
                                <td>
                                    <?php if ($isFailure): ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($log['action'] ?? '—') ?></span>
                                    <?php elseif (str_contains(strtolower($log['action'] ?? ''), 'login')): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($log['action'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($log['action'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code></td>
                                <td>
                                    <small class="text-muted" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'] ?? '—', 0, 60)) ?>
                                    </small>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($log['created_at'] ?? '—') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    Showing <?= count($logs) ?> record(s). <a href="?filter=<?= $filterType ?>&export=csv">Download full CSV</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'inc/footer.php'; ?>
