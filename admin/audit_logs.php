<?php
// ============================================================
// admin/audit_logs.php
// ------------------------------------------------------------
// Admin Audit Log viewer.
//   - Super Admin: sees every entry
//   - Admin with audit.view: read-only view
//   - supports filtering by admin, module, action and date
//   - CSV export (audit.export permission)
// ============================================================

$currentPage        = 'audit';
$requiredPermission = 'audit.view';

require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/inc/functions.php';

$pdo = getDBConnection();

// --- Filters ---
$fAdminId = (int) ($_GET['admin_id'] ?? 0);
$fModule  = trim((string) ($_GET['module'] ?? ''));
$fAction  = trim((string) ($_GET['action'] ?? ''));
$fFrom    = trim((string) ($_GET['from'] ?? ''));
$fTo      = trim((string) ($_GET['to'] ?? ''));
$page     = max(1, (int) ($_GET['p'] ?? 1));
$perPage  = 40;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($fAdminId > 0) {
    $where[] = 'admin_id = :admin_id';
    $params['admin_id'] = $fAdminId;
}
if ($fModule !== '') {
    $where[] = 'module = :module';
    $params['module'] = $fModule;
}
if ($fAction !== '') {
    $where[] = 'action LIKE :action';
    $params['action'] = '%' . $fAction . '%';
}
if ($fFrom !== '') {
    $where[] = 'created_at >= :from';
    $params['from'] = $fFrom . ' 00:00:00';
}
if ($fTo !== '') {
    $where[] = 'created_at <= :to';
    $params['to']   = $fTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ------------------------------------------------------------
// CSV EXPORT
// ------------------------------------------------------------
if (isset($_GET['export']) && $auth->can('audit.export')) {
    $stmt = $pdo->prepare(
        "SELECT id, admin_name, action, module, target_table, record_id, details, ip_address, created_at
         FROM admin_audit_logs $whereSql
         ORDER BY id DESC LIMIT 5000"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $auth->audit('audit.export', 'audit', 'admin_audit_logs', null, ['rows' => count($rows)]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_audit_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Admin', 'Action', 'Module', 'Table', 'Record', 'Details', 'IP', 'When']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['admin_name'],
            $r['action'],
            $r['module'],
            $r['target_table'],
            $r['record_id'],
            $r['details'],
            $r['ip_address'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ------------------------------------------------------------
// DATA
// ------------------------------------------------------------
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listStmt = $pdo->prepare(
    "SELECT * FROM admin_audit_logs $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$logs = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

$adminOptions = $pdo->query(
    "SELECT DISTINCT admin_id, admin_name FROM admin_audit_logs
     WHERE admin_id IS NOT NULL ORDER BY admin_name"
)->fetchAll();

$moduleOptions = $pdo->query(
    "SELECT DISTINCT module FROM admin_audit_logs
     WHERE module IS NOT NULL AND module <> '' ORDER BY module"
)->fetchAll(PDO::FETCH_COLUMN);

$csrf = $auth->csrfToken();

/** color by action verb */
function actionTone(string $action): string
{
    $a = strtolower($action);
    if (str_contains($a, 'delete')) return 'tone-red';
    if (str_contains($a, 'suspend') || str_contains($a, 'reject')) return 'tone-amber';
    if (str_contains($a, 'create') || str_contains($a, 'login')) return 'tone-green';
    if (str_contains($a, 'update') || str_contains($a, 'edit')) return 'tone-blue';
    return 'tone-grey';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - KSR GURU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <style>
        body {
            background: #f6f8fa;
            font-family: 'Poppins', sans-serif;
            color: #172033;
        }

        .main-content {
            padding: 24px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #fff;
            border: 1px solid #e3e8ef;
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 20px;
        }

        .topbar h1 {
            font-size: 1.35rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar h1 i {
            color: #0f7b3a;
        }

        .topbar .sub {
            color: #667085;
            font-size: .82rem;
        }

        .filter-bar {
            background: #fff;
            border: 1px solid #e3e8ef;
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .filter-bar label {
            display: block;
            font-size: .76rem;
            font-weight: 500;
            color: #475467;
            margin-bottom: 5px;
        }

        .filter-bar input,
        .filter-bar select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #e3e8ef;
            border-radius: 9px;
            font-family: inherit;
            font-size: .86rem;
            outline: none;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: #0f7b3a;
        }

        .btn-sm {
            padding: 9px 16px;
            border-radius: 9px;
            border: none;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 500;
            font-family: inherit;
            text-decoration: none;
            display: inline-block;
        }

        .btn-green {
            background: #0f7b3a;
            color: #fff;
        }

        .btn-grey {
            background: #eef2f6;
            color: #172033;
        }

        .btn-sm:hover {
            opacity: .9;
        }

        .panel {
            background: #fff;
            border: 1px solid #e3e8ef;
            border-radius: 16px;
            padding: 20px 22px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            padding: 11px 13px;
            text-align: left;
            font-size: .85rem;
            border-bottom: 1px solid #eef2f6;
            vertical-align: top;
        }

        table.data th {
            color: #667085;
            font-weight: 600;
            font-size: .74rem;
            text-transform: uppercase;
        }

        table.data tr:hover td {
            background: #fafbfc;
        }

        .tone {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
        }

        .tone-green {
            background: #dcfce7;
            color: #166534;
        }

        .tone-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .tone-amber {
            background: #fef3c7;
            color: #92400e;
        }

        .tone-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .tone-grey {
            background: #eef2f6;
            color: #475467;
        }

        .details-cell {
            max-width: 340px;
            word-break: break-word;
            color: #475467;
            font-size: .8rem;
        }

        .muted {
            color: #98a2b3;
        }

        .pager {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .pager a,
        .pager span {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #e3e8ef;
            text-decoration: none;
            color: #172033;
            font-size: .82rem;
        }

        .pager .current {
            background: #0f7b3a;
            color: #fff;
            border-color: #0f7b3a;
        }
    </style>
</head>

<body>
    <?php include 'inc/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar">
            <div>
                <h1><i class="fas fa-shield-halved"></i> Audit Logs</h1>
                <div class="sub"><?= number_format($total) ?> recorded administrator action(s)</div>
            </div>
            <?php if ($auth->can('audit.export')): ?>
                <a class="btn-sm btn-green"
                    href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            <?php endif; ?>
        </div>

        <form class="filter-bar" method="GET">
            <div>
                <label>Admin</label>
                <select name="admin_id">
                    <option value="0">All admins</option>
                    <?php foreach ($adminOptions as $a): ?>
                        <option value="<?= (int) $a['admin_id'] ?>" <?= $fAdminId === (int) $a['admin_id'] ? 'selected' : '' ?>>
                            <?= e($a['admin_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Module</label>
                <select name="module">
                    <option value="">All modules</option>
                    <?php foreach ($moduleOptions as $m): ?>
                        <option value="<?= e($m) ?>" <?= $fModule === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Action contains</label>
                <input type="text" name="action" value="<?= e($fAction) ?>" placeholder="e.g. delete">
            </div>
            <div>
                <label>From</label>
                <input type="date" name="from" value="<?= e($fFrom) ?>">
            </div>
            <div>
                <label>To</label>
                <input type="date" name="to" value="<?= e($fTo) ?>">
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-sm btn-green"><i class="fas fa-filter"></i> Filter</button>
                <a href="audit_logs.php" class="btn-sm btn-grey"><i class="fas fa-rotate"></i> Reset</a>
            </div>
        </form>

        <div class="panel">
            <div style="overflow-x:auto;">
                <table class="data">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>When</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Target</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$logs): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:#98a2b3; padding:30px;">No audit entries match your filters.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="muted"><?= (int) $log['id'] ?></td>
                                <td><?= e(date('d M Y, H:i:s', strtotime($log['created_at']))) ?></td>
                                <td><?= $log['admin_name'] ? e($log['admin_name']) : '<span class="muted">system</span>' ?></td>
                                <td><span class="tone <?= actionTone($log['action']) ?>"><?= e($log['action']) ?></span></td>
                                <td><?= $log['module'] ? e($log['module']) : '<span class="muted">-</span>' ?></td>
                                <td>
                                    <?= $log['target_table'] ? e($log['target_table']) : '<span class="muted">-</span>' ?>
                                    <?= $log['record_id'] ? '#<strong>' . (int) $log['record_id'] . '</strong>' : '' ?>
                                </td>
                                <td class="details-cell"><?= $log['details'] ? e($log['details']) : '<span class="muted">-</span>' ?></td>
                                <td class="muted"><?= e($log['ip_address'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pager">
                    <?php
                    $qBase = $_GET;
                    unset($qBase['p']);
                    $mk = fn($p) => '?' . http_build_query(array_merge($qBase, ['p' => $p]));
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= $mk($page - 1) ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $mk($i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $mk($page + 1) ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>