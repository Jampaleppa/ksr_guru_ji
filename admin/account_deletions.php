<?php
// ============================================================
// admin/account_deletions.php - Customer Account Deletion Queue
// ============================================================
$currentPage        = 'account_deletions';
$requiredPermission = 'customers.view';
require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/functions.php';

$pdo      = getDBConnection();
$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

// Ensure table and review metadata columns exist on both SQLite and MySQL
try {
    if ($isSqlite) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            email        TEXT NULL,
            reason       TEXT NULL,
            status       TEXT NOT NULL DEFAULT 'pending',
            requested_at TEXT NOT NULL DEFAULT (datetime('now')),
            reviewed_by  INTEGER NULL,
            reviewed_at  TEXT NULL,
            admin_note   TEXT NULL
        )");
        $adrCols = $pdo->query("PRAGMA table_info(account_deletion_requests)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['reviewed_by INTEGER NULL', 'reviewed_at TEXT NULL', 'admin_note TEXT NULL'] as $def) {
            $col = explode(' ', $def)[0];
            if (!in_array($col, $adrCols, true)) {
                try {
                    $pdo->exec("ALTER TABLE account_deletion_requests ADD COLUMN {$def}");
                } catch (Throwable $ignore) {
                }
            }
        }
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            email        VARCHAR(150) NULL,
            reason       TEXT NULL,
            status       VARCHAR(30) NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by  INT NULL,
            reviewed_at  DATETIME NULL,
            admin_note   TEXT NULL,
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $adrCols = $pdo->query("SHOW COLUMNS FROM account_deletion_requests")->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach (
            [
                'reviewed_by INT NULL',
                'reviewed_at DATETIME NULL',
                'admin_note TEXT NULL',
            ] as $def
        ) {
            $col = explode(' ', $def)[0];
            if (!in_array($col, $adrCols, true)) {
                try {
                    $pdo->exec("ALTER TABLE account_deletion_requests ADD COLUMN {$def}");
                } catch (Throwable $ignore) {
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('[account_deletions] schema ensure error: ' . $e->getMessage());
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['admin_csrf_token'];

$message = (string)($_GET['msg'] ?? '');
$error   = (string)($_GET['err'] ?? '');
$filter  = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($filter, ['all', 'pending', 'approved', 'denied'], true)) {
    $filter = 'all';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Please reload and try again.');
    }

    require_admin_permission('customers.edit');

    $action    = (string)($_POST['action'] ?? '');
    $requestId = (int)($_POST['request_id'] ?? 0);
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));
    $adminId   = (int)($_SESSION['admin_auth_id'] ?? 0);
    $nowStr    = date('Y-m-d H:i:s');

    if ($requestId <= 0 || !in_array($action, ['approve', 'deny'], true)) {
        $error = 'Invalid account deletion action or request ID.';
    } else {
        try {
            $pdo->beginTransaction();

            $reqStmt = $pdo->prepare("SELECT * FROM account_deletion_requests WHERE id = ? LIMIT 1");
            $reqStmt->execute([$requestId]);
            $req = $reqStmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $pdo->rollBack();
                $error = 'Account deletion request not found.';
            } elseif (($req['status'] ?? '') !== 'pending') {
                $pdo->rollBack();
                $error = 'This request has already been processed (' . $req['status'] . ').';
            } elseif ($action === 'approve') {
                $targetUserId = (int)$req['user_id'];
                $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $uStmt->execute([$targetUserId]);
                $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

                if ($targetUser && in_array(strtolower((string)($targetUser['role'] ?? '')), ['admin', 'super_admin'], true)) {
                    $pdo->rollBack();
                    $error = 'Admin accounts cannot be deleted through the customer deletion queue.';
                } else {
                    if ($targetUser) {
                        $uCols = $isSqlite
                            ? $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1)
                            : $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);

                        $updates = [
                            'name = ?',
                            'email = ?',
                            'password = ?',
                        ];
                        $params = [
                            'Deleted User #' . $targetUserId,
                            'deleted_' . $targetUserId . '_' . time() . '@deleted.local',
                            password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                        ];

                        if (in_array('fullname', $uCols, true)) {
                            $updates[] = 'fullname = ?';
                            $params[]  = 'Deleted User #' . $targetUserId;
                        }
                        if (in_array('account_status', $uCols, true)) {
                            $deletedStatus = 'DELETED';
                            if (!$isSqlite) {
                                try {
                                    $colInfo = $pdo->query("SHOW COLUMNS FROM users LIKE 'account_status'")->fetch(PDO::FETCH_ASSOC);
                                    $colType = strtoupper((string)($colInfo['Type'] ?? ''));
                                    if (str_starts_with($colType, 'ENUM(') && strpos($colType, "'DELETED'") === false) {
                                        $deletedStatus = 'SUSPENDED';
                                    }
                                } catch (Throwable $ignore) {
                                }
                            }
                            $updates[] = 'account_status = ?';
                            $params[]  = $deletedStatus;
                        }
                        foreach (['phone', 'mobile', 'address'] as $clearCol) {
                            if (in_array($clearCol, $uCols, true)) {
                                $updates[] = "{$clearCol} = NULL";
                            }
                        }

                        $params[] = $targetUserId;
                        $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')
                            ->execute($params);

                        try {
                            $pdo->prepare("DELETE FROM customer_addresses WHERE user_id = ?")
                                ->execute([$targetUserId]);
                        } catch (Throwable $ignore) {
                        }
                    }

                    $pdo->prepare(
                        "UPDATE account_deletion_requests
                            SET status = 'approved', reviewed_by = ?, reviewed_at = ?, admin_note = ?
                          WHERE id = ? AND status = 'pending'"
                    )->execute([$adminId ?: null, $nowStr, $adminNote !== '' ? $adminNote : null, $requestId]);

                    $pdo->commit();

                    admin_audit(
                        'approve_account_deletion',
                        'customers',
                        'account_deletion_requests',
                        $requestId,
                        ['user_id' => $targetUserId, 'email' => $req['email'] ?? '', 'note' => $adminNote]
                    );
                    $message = "Request #{$requestId} approved and account anonymized/deactivated.";
                }
            } else {
                // Deny request
                $pdo->prepare(
                    "UPDATE account_deletion_requests
                        SET status = 'denied', reviewed_by = ?, reviewed_at = ?, admin_note = ?
                      WHERE id = ? AND status = 'pending'"
                )->execute([$adminId ?: null, $nowStr, $adminNote !== '' ? $adminNote : null, $requestId]);

                $targetUserId = (int)$req['user_id'];
                if ($targetUserId > 0) {
                    try {
                        $msgText = 'Your account deletion request was declined by our support team.'
                            . ($adminNote !== '' ? ' Note: ' . $adminNote : '');
                        $pdo->prepare(
                            "INSERT INTO notifications (user_id, title, subject, message, type) VALUES (?, ?, ?, ?, 'system')"
                        )->execute([$targetUserId, 'Account Deletion Request Update', 'Account Deletion Request Update', $msgText]);
                    } catch (Throwable $ignore) {
                    }
                }

                $pdo->commit();

                admin_audit(
                    'deny_account_deletion',
                    'customers',
                    'account_deletion_requests',
                    $requestId,
                    ['user_id' => $targetUserId, 'note' => $adminNote]
                );
                $message = "Request #{$requestId} denied.";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to process request: ' . $e->getMessage();
        }
    }

    $redirectUrl = 'account_deletions.php?status=' . urlencode($filter);
    if ($message !== '') {
        $redirectUrl .= '&msg=' . urlencode($message);
    }
    if ($error !== '') {
        $redirectUrl .= '&err=' . urlencode($error);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Fetch summary counts + list
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'denied' => 0];
$requests = [];
try {
    foreach ($pdo->query("SELECT status, COUNT(*) AS cnt FROM account_deletion_requests GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $cRow) {
        $stKey = strtolower((string)($cRow['status'] ?? 'pending'));
        $cnt   = (int)$cRow['cnt'];
        $counts['all'] += $cnt;
        if (isset($counts[$stKey])) {
            $counts[$stKey] = $cnt;
        }
    }

    $sql = "SELECT r.*, COALESCE(NULLIF(u.name, ''), u.email) AS current_user_name, u.role AS user_role
              FROM account_deletion_requests r
              LEFT JOIN users u ON u.id = r.user_id";
    $params = [];
    if ($filter !== 'all') {
        $sql .= " WHERE r.status = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY CASE r.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'denied' THEN 3 ELSE 4 END, r.id DESC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error ?: ('Failed to load requests: ' . $e->getMessage());
}

$canEdit   = current_admin_can('customers.edit');
$pageTitle = 'Account Deletion Requests';
require_once __DIR__ . '/inc/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-user-times text-danger me-2"></i><?= e($pageTitle) ?></h1>
        <p class="subtitle">Review and approve or deny customer account deletion requests while preserving financial order records.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied'] as $k => $lbl): ?>
            <a href="?status=<?= e($k) ?>"
                class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= e($lbl) ?> (<?= (int)$counts[$k] ?>)
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> <?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3>Deletion Queue (<?= count($requests) ?>)</h3>
        <?php if (!$canEdit): ?>
            <span class="badge bg-secondary">Read-only access</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div style="text-align:center;padding:42px;color:#64748b;">
                <i class="fas fa-user-check" style="font-size:2.4rem;color:#10b981;margin-bottom:10px;"></i>
                <p class="mb-0">No account deletion requests matching the selected filter.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Reason</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Review / Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $row):
                            $st = strtolower((string)($row['status'] ?? 'pending'));
                            $badgeClass = match ($st) {
                                'approved' => 'bg-success',
                                'denied'   => 'bg-secondary',
                                default    => 'bg-warning text-dark',
                            };
                        ?>
                            <tr>
                                <td><strong>#<?= (int)$row['id'] ?></strong></td>
                                <td>
                                    <?= e($row['current_user_name'] ?: ('User #' . (int)$row['user_id'])) ?>
                                    <div class="text-muted" style="font-size:0.75rem;">ID: <?= (int)$row['user_id'] ?></div>
                                </td>
                                <td><?= e($row['email'] ?: '—') ?></td>
                                <td style="max-width:260px;"><?= e($row['reason'] ?: 'No reason provided') ?></td>
                                <td style="font-size:0.82rem;"><?= $row['requested_at'] ? e(date('d M Y, H:i', strtotime((string)$row['requested_at']))) : '—' ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= e(ucfirst($st)) ?></span></td>
                                <td style="font-size:0.82rem;">
                                    <?php if ($st !== 'pending'): ?>
                                        <div><?= e($row['admin_note'] ?: '—') ?></div>
                                        <?php if (!empty($row['reviewed_at'])): ?>
                                            <div class="text-muted" style="font-size:0.72rem;"><?= e(date('d M Y, H:i', strtotime((string)$row['reviewed_at']))) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Awaiting review</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st === 'pending' && $canEdit): ?>
                                        <form method="POST" style="display:flex;flex-direction:column;gap:6px;min-width:210px;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
                                            <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Optional review note...">
                                            <div class="d-flex gap-2">
                                                <button type="submit" name="action" value="approve"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Approve deletion and permanently deactivate/anonymize this customer account?');">
                                                    <i class="fas fa-user-slash"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="deny"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    onclick="return confirm('Decline this account deletion request?');">
                                                    <i class="fas fa-times"></i> Deny
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
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
<?php require_once __DIR__ . '/inc/footer.php'; ?>