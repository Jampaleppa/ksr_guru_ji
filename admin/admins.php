<?php
// ============================================================
// admin/admins.php
// ------------------------------------------------------------
// Admin Management (Super Admin only)
//   - create / edit admin accounts
//   - activate / suspend / delete
//   - assign role + per-role permission matrix
//   - reset passwords
//
// Enforces:
//   * only Super Admin may access this page
//   * cannot create a Super Admin from here (role limited)
//   * cannot suspend/modify the last active Super Admin
//   * every mutation is audited
// ============================================================

$currentPage = 'admins';

require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/inc/functions.php';

// --- HARD GATE: Super Admin only ---
// Admins.php can create/grant privileges, so it stays Super Admin
// only even if a role were (mis)configured to hold admins.view.
if (!$auth->isSuperAdmin()) {
    admin_auth()->audit(
        'access_denied',
        'admins',
        'admin_users',
        null,
        ['role' => $_SESSION['user_role'] ?? 'admin']
    );
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();

/** Fetch non-super roles (Admin may only be created with these). */
$manageableRoles = $pdo->query(
    "SELECT id, role_key, role_name, description
     FROM admin_roles
     WHERE role_key <> 'SUPER_ADMIN'
     ORDER BY role_name"
)->fetchAll();

/** Admin list with role + permission counts. */
function fetchAdmins(PDO $pdo): array
{
    return $pdo->query(
        "SELECT au.*, ar.role_key, ar.role_name,
                (SELECT COUNT(*) FROM admin_role_permissions rp WHERE rp.role_id = au.role_id) AS perm_count
         FROM admin_users au
         JOIN admin_roles ar ON ar.id = au.role_id
         ORDER BY au.is_super_admin DESC, au.created_at DESC"
    )->fetchAll();
}

/** All permissions grouped by module. */
function fetchPermissionsGrouped(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT id, permission_key, module, action, label
         FROM admin_permissions
         ORDER BY module, action"
    )->fetchAll();

    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['module']][] = $r;
    }
    return $grouped;
}

/** Role -> permission id map. */
function fetchRolePermissionMap(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT role_id, permission_id FROM admin_role_permissions"
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['role_id']][] = (int) $r['permission_id'];
    }
    return $map;
}

$flash = ['type' => '', 'msg' => ''];
$activeTab = $_GET['tab'] ?? 'admins';

// ============================================================
// HANDLE ACTIONS (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    try {
        // ----------------------------------------------------
        // CREATE ADMIN
        // ----------------------------------------------------
        if ($action === 'create_admin') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email    = trim((string) ($_POST['email'] ?? ''));
            $phone    = trim((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $roleId   = (int) ($_POST['role_id'] ?? 0);

            $errors = [];
            if ($fullName === '')                          $errors[] = 'Full name is required.';
            if (!preg_match('/^[A-Za-z0-9_.]{3,80}$/', $username)) $errors[] = 'Username must be 3-80 chars (letters, numbers, _ .).';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
            if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';

            $role = $pdo->prepare("SELECT id, role_key FROM admin_roles WHERE id = ?");
            $role->execute([$roleId]);
            $roleRow = $role->fetch();
            if (!$roleRow) {
                $errors[] = 'Please choose a valid role.';
            } elseif ($roleRow['role_key'] === 'SUPER_ADMIN') {
                $errors[] = 'Super Admin accounts cannot be created from this screen.';
            }

            // uniqueness
            $dup = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
            $dup->execute([$username, $email]);
            if ($dup->fetch()) {
                $errors[] = 'That username or email is already in use.';
            }

            if ($errors) {
                $flash = ['type' => 'error', 'msg' => implode(' ', $errors)];
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO admin_users
                       (username, email, password_hash, full_name, phone, role_id, status, is_super_admin, must_change_password, created_by)
                     VALUES (:u, :e, :p, :n, :ph, :r, 'active', 0, 1, :cb)"
                );
                $stmt->execute([
                    'u'  => $username,
                    'e'  => $email,
                    'p'  => $auth->hashPassword($password),
                    'n'  => $fullName,
                    'ph' => $phone ?: null,
                    'r'  => $roleId,
                    'cb' => $auth->id(),
                ]);
                $newId = (int) $pdo->lastInsertId();
                $auth->audit('admin.create', 'admins', 'admin_users', $newId, [
                    'username' => $username,
                    'role_id' => $roleId,
                ]);
                $flash = ['type' => 'success', 'msg' => "Admin \"{$fullName}\" created successfully."];
            }
        }

        // ----------------------------------------------------
        // UPDATE ADMIN (details + role)
        // ----------------------------------------------------
        elseif ($action === 'update_admin') {
            $id       = (int) ($_POST['id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email    = trim((string) ($_POST['email'] ?? ''));
            $phone    = trim((string) ($_POST['phone'] ?? ''));
            $roleId   = (int) ($_POST['role_id'] ?? 0);

            $target = $auth->findAdminById($id);
            if (!$target) {
                $flash = ['type' => 'error', 'msg' => 'Admin not found.'];
            } elseif ((int) $target['is_super_admin'] === 1 && $id !== $auth->id()) {
                $flash = ['type' => 'error', 'msg' => 'Super Admin accounts cannot be modified here.'];
            } else {
                // Never allow changing to Super Admin role.
                $role = $pdo->prepare("SELECT role_key FROM admin_roles WHERE id = ?");
                $role->execute([$roleId]);
                $roleKey = $role->fetchColumn();
                if ($roleKey === 'SUPER_ADMIN') {
                    $roleId = $target['role_id']; // silently keep existing
                }

                $stmt = $pdo->prepare(
                    "UPDATE admin_users
                     SET full_name = :n, email = :e, phone = :ph, role_id = :r
                     WHERE id = :id"
                );
                $stmt->execute([
                    'n' => $fullName,
                    'e' => $email,
                    'ph' => $phone ?: null,
                    'r' => $roleId,
                    'id' => $id,
                ]);
                $auth->audit('admin.update', 'admins', 'admin_users', $id);
                $flash = ['type' => 'success', 'msg' => 'Admin updated successfully.'];
            }
        }

        // ----------------------------------------------------
        // STATUS: activate / suspend
        // ----------------------------------------------------
        elseif ($action === 'set_status') {
            $id     = (int) ($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['active', 'suspended'], true)) {
                $flash = ['type' => 'error', 'msg' => 'Invalid status.'];
            } else {
                $target = $auth->findAdminById($id);
                if (!$target) {
                    $flash = ['type' => 'error', 'msg' => 'Admin not found.'];
                } elseif (($target['role_key'] === 'SUPER_ADMIN') && $status === 'suspended') {
                    $flash = ['type' => 'error', 'msg' => 'Super Admin accounts cannot be suspended.'];
                } elseif ($id === $auth->id() && $status === 'suspended') {
                    $flash = ['type' => 'error', 'msg' => 'You cannot suspend your own account.'];
                } else {
                    $pdo->prepare("UPDATE admin_users SET status = ? WHERE id = ?")
                        ->execute([$status, $id]);
                    $auth->audit('admin.' . $status, 'admins', 'admin_users', $id);
                    $flash = ['type' => 'success', 'msg' => "Admin {$status} successfully."];
                }
            }
        }

        // ----------------------------------------------------
        // DELETE ADMIN
        // ----------------------------------------------------
        elseif ($action === 'delete_admin') {
            $id = (int) ($_POST['id'] ?? 0);
            $target = $auth->findAdminById($id);
            if (!$target) {
                $flash = ['type' => 'error', 'msg' => 'Admin not found.'];
            } elseif ((int) $target['is_super_admin'] === 1 || $target['role_key'] === 'SUPER_ADMIN') {
                $flash = ['type' => 'error', 'msg' => 'Super Admin accounts cannot be deleted.'];
            } elseif ($id === $auth->id()) {
                $flash = ['type' => 'error', 'msg' => 'You cannot delete your own account.'];
            } else {
                $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
                $auth->audit('admin.delete', 'admins', 'admin_users', $id, [
                    'username' => $target['username'],
                ]);
                $flash = ['type' => 'success', 'msg' => 'Admin deleted.'];
            }
        }

        // ----------------------------------------------------
        // RESET PASSWORD
        // ----------------------------------------------------
        elseif ($action === 'reset_password') {
            $id  = (int) ($_POST['id'] ?? 0);
            $new = (string) ($_POST['new_password'] ?? '');
            $target = $auth->findAdminById($id);
            if (!$target) {
                $flash = ['type' => 'error', 'msg' => 'Admin not found.'];
            } elseif (strlen($new) < 8) {
                $flash = ['type' => 'error', 'msg' => 'Password must be at least 8 characters.'];
            } elseif ((int) $target['is_super_admin'] === 1 && $id !== $auth->id()) {
                $flash = ['type' => 'error', 'msg' => 'Super Admin passwords cannot be reset here.'];
            } else {
                $pdo->prepare("UPDATE admin_users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?")
                    ->execute([$auth->hashPassword($new), $id]);
                $auth->audit('admin.reset_password', 'admins', 'admin_users', $id);
                $flash = ['type' => 'success', 'msg' => 'Password reset successfully.'];
            }
        }

        // ----------------------------------------------------
        // UPDATE ROLE PERMISSIONS
        // ----------------------------------------------------
        elseif ($action === 'save_role_permissions') {
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $perms  = $_POST['permissions'] ?? [];

            $role = $pdo->prepare("SELECT role_key FROM admin_roles WHERE id = ?");
            $role->execute([$roleId]);
            $roleKey = $role->fetchColumn();

            if (!$roleKey) {
                $flash = ['type' => 'error', 'msg' => 'Role not found.'];
            } elseif ($roleKey === 'SUPER_ADMIN') {
                $flash = ['type' => 'error', 'msg' => 'Super Admin always has full access and cannot be edited.'];
            } else {
                $permIds = array_values(array_unique(array_map('intval', (array) $perms)));

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM admin_role_permissions WHERE role_id = ?")
                        ->execute([$roleId]);
                    if ($permIds) {
                        $ins = $pdo->prepare(
                            "INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)"
                        );
                        foreach ($permIds as $pid) {
                            $ins->execute([$roleId, $pid]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

                $auth->audit('role.permissions.update', 'admins', 'admin_roles', $roleId, [
                    'permission_count' => count($permIds),
                ]);
                $flash = ['type' => 'success', 'msg' => 'Role permissions updated.'];
            }
            $activeTab = 'roles';
        }
    } catch (Throwable $e) {
        $flash = ['type' => 'error', 'msg' => 'Operation failed: ' . $e->getMessage()];
    }
}

$admins          = fetchAdmins($pdo);
$permissionsByModule = fetchPermissionsGrouped($pdo);
$rolePermMap     = fetchRolePermissionMap($pdo);
$csrf            = $auth->csrfToken();

$stats = [
    'total'     => count($admins),
    'active'    => count(array_filter($admins, fn($a) => $a['status'] === 'active')),
    'suspended' => count(array_filter($admins, fn($a) => $a['status'] === 'suspended')),
    'supers'    => count(array_filter($admins, fn($a) => (int) $a['is_super_admin'] === 1)),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - KSR GURU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <style>
        :root {
            --bg: #f6f8fa;
            --card-bg: #ffffff;
            --border: #e3e8ef;
            --text: #172033;
            --muted: #667085;
            --primary: #0f7b3a;
            --primary-2: #1ea85a;
            --danger: #dc3545;
            --warn: #f59e0b;
        }

        body {
            background: var(--bg);
            font-family: 'Poppins', sans-serif;
            color: var(--text);
        }

        .main-content {
            padding: 24px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            border: 1px solid var(--border);
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
            color: var(--primary);
        }

        .topbar .sub {
            color: var(--muted);
            font-size: .82rem;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat .ic {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
        }

        .stat .num {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat .lbl {
            color: var(--muted);
            font-size: .8rem;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .tab-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            cursor: pointer;
            font-size: .9rem;
            font-weight: 500;
            text-decoration: none;
            color: var(--text);
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .panel {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .panel h2 {
            font-size: 1.05rem;
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel h2 i {
            color: var(--primary);
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            padding: 12px 14px;
            text-align: left;
            font-size: .88rem;
            border-bottom: 1px solid var(--border);
        }

        table.data th {
            color: var(--muted);
            font-weight: 600;
            font-size: .78rem;
            text-transform: uppercase;
        }

        table.data tr:hover td {
            background: #fafbfc;
        }

        .badge-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
        }

        .pill-active {
            background: #dcfce7;
            color: #166534;
        }

        .pill-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .pill-super {
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            color: #fff;
        }

        .pill-admin {
            background: #eef2ff;
            color: #3730a3;
        }

        .btn-sm {
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 500;
            font-family: inherit;
        }

        .btn-green {
            background: var(--primary);
            color: #fff;
        }

        .btn-red {
            background: var(--danger);
            color: #fff;
        }

        .btn-amber {
            background: var(--warn);
            color: #fff;
        }

        .btn-grey {
            background: #eef2f6;
            color: var(--text);
        }

        .btn-sm:hover {
            opacity: .9;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: .82rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: #344054;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: .9rem;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15, 123, 58, .12);
        }

        .flash {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: .9rem;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .flash.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .flash.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .perm-module {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        .perm-module h4 {
            margin: 0 0 10px;
            font-size: .9rem;
            text-transform: capitalize;
            color: var(--primary);
        }

        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 8px;
        }

        .perm-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
        }

        .perm-check input {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(16, 24, 40, .55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 26px;
            width: min(560px, 100%);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-box h3 {
            margin: 0 0 6px;
        }

        .modal-box .close {
            float: right;
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--muted);
        }

        @media (max-width: 900px) {
            .stat-row {
                grid-template-columns: 1fr 1fr;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'inc/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar">
            <div>
                <h1><i class="fas fa-user-shield"></i> Admin Management</h1>
                <div class="sub">Create and control administrator accounts, roles and permissions</div>
            </div>
            <button class="btn-sm btn-green" onclick="openModal('createModal')">
                <i class="fas fa-plus"></i> New Admin
            </button>
        </div>

        <?php if ($flash['msg']): ?>
            <div class="flash <?= e($flash['type']) ?>">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <span><?= e($flash['msg']) ?></span>
            </div>
        <?php endif; ?>

        <div class="stat-row">
            <div class="stat">
                <div class="ic"><i class="fas fa-users"></i></div>
                <div>
                    <div class="num"><?= $stats['total'] ?></div>
                    <div class="lbl">Total Admins</div>
                </div>
            </div>
            <div class="stat">
                <div class="ic" style="background:linear-gradient(135deg,#0f7b3a,#34d399)"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="num"><?= $stats['active'] ?></div>
                    <div class="lbl">Active</div>
                </div>
            </div>
            <div class="stat">
                <div class="ic" style="background:linear-gradient(135deg,#dc3545,#f87171)"><i class="fas fa-user-slash"></i></div>
                <div>
                    <div class="num"><?= $stats['suspended'] ?></div>
                    <div class="lbl">Suspended</div>
                </div>
            </div>
            <div class="stat">
                <div class="ic" style="background:linear-gradient(135deg,#0369a1,#38bdf8)"><i class="fas fa-crown"></i></div>
                <div>
                    <div class="num"><?= $stats['supers'] ?></div>
                    <div class="lbl">Super Admins</div>
                </div>
            </div>
        </div>

        <div class="tabs">
            <a href="?tab=admins" class="tab-btn <?= $activeTab === 'admins' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i> Admins
            </a>
            <a href="?tab=roles" class="tab-btn <?= $activeTab === 'roles' ? 'active' : '' ?>">
                <i class="fas fa-shield-halved"></i> Roles &amp; Permissions
            </a>
        </div>

        <?php if ($activeTab === 'admins'): ?>
            <div class="panel">
                <h2><i class="fas fa-list"></i> Administrator Accounts</h2>
                <div style="overflow-x:auto;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username / Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $a): ?>
                                <?php $isSuper = (int) $a['is_super_admin'] === 1 || $a['role_key'] === 'SUPER_ADMIN'; ?>
                                <tr>
                                    <td>
                                        <strong><?= e($a['full_name']) ?></strong>
                                        <?php if ($isSuper): ?>
                                            <span class="badge-pill pill-super" style="margin-left:6px;">SUPER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e($a['username']) ?><br>
                                        <small style="color:var(--muted)"><?= e($a['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-pill <?= $isSuper ? 'pill-super' : 'pill-admin' ?>">
                                            <?= e($a['role_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-pill <?= $a['status'] === 'active' ? 'pill-active' : 'pill-suspended' ?>">
                                            <?= ucfirst(e($a['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $a['last_login_at'] ? e(date('d M Y, H:i', strtotime($a['last_login_at']))) : '<span style="color:var(--muted)">Never</span>' ?>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <?php if (!$isSuper): ?>
                                            <button class="btn-sm btn-grey"
                                                onclick='editAdmin(<?= htmlspecialchars(json_encode([
                                                                        "id" => $a["id"],
                                                                        "full_name" => $a["full_name"],
                                                                        "email" => $a["email"],
                                                                        "phone" => $a["phone"],
                                                                        "role_id" => $a["role_id"],
                                                                    ]), ENT_QUOTES) ?>)'>
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <?php if ($a['status'] === 'active'): ?>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Suspend this admin?')">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="action" value="set_status">
                                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                    <input type="hidden" name="status" value="suspended">
                                                    <button class="btn-sm btn-amber" title="Suspend"><i class="fas fa-ban"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="action" value="set_status">
                                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button class="btn-sm btn-green" title="Activate"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn-sm btn-grey" title="Reset Password"
                                                onclick="resetPassword(<?= (int) $a['id'] ?>, '<?= e($a['full_name']) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this admin permanently?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button class="btn-sm btn-red" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:var(--muted); font-size:.8rem;">Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- ROLES & PERMISSIONS -->
            <?php foreach ($manageableRoles as $role): ?>
                <?php
                $rp = $rolePermMap[(int) $role['id']] ?? [];
                $isSuperRole = $role['role_key'] === 'SUPER_ADMIN';
                ?>
                <div class="panel">
                    <h2>
                        <i class="fas fa-user-tag"></i> <?= e($role['role_name']) ?>
                        <span class="badge-pill pill-admin"><?= count($rp) ?> permissions</span>
                    </h2>
                    <?php if ($isSuperRole): ?>
                        <p style="color:var(--muted)">Super Admin always has full system access and cannot be restricted.</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="save_role_permissions">
                            <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">

                            <?php foreach ($permissionsByModule as $module => $perms): ?>
                                <div class="perm-module">
                                    <h4><?= e(str_replace('_', ' ', $module)) ?></h4>
                                    <div class="perm-grid">
                                        <?php foreach ($perms as $p): ?>
                                            <label class="perm-check">
                                                <input type="checkbox" name="permissions[]"
                                                    value="<?= (int) $p['id'] ?>"
                                                    <?= in_array((int) $p['id'], $rp, true) ? 'checked' : '' ?>>
                                                <span><?= e($p['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button type="submit" class="btn-sm btn-green">
                                <i class="fas fa-save"></i> Save Permissions
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- ============ CREATE MODAL ============ -->
    <div class="modal-backdrop" id="createModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('createModal')">&times;</button>
            <h3><i class="fas fa-user-plus" style="color:var(--primary)"></i> Create Admin</h3>
            <p style="color:var(--muted); font-size:.85rem; margin-bottom:18px;">
                New admins receive permission-based access. You cannot create a Super Admin here.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="create_admin">

                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label>Username</label>
                        <input type="text" name="username" required pattern="[A-Za-z0-9_.]{3,80}">
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label>Phone (WhatsApp)</label>
                        <input type="text" name="phone" placeholder="+91...">
                    </div>
                    <div class="field">
                        <label>Role</label>
                        <select name="role_id" required>
                            <?php foreach ($manageableRoles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>"><?= e($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Temporary Password (min 8 chars)</label>
                    <input type="text" name="password" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-check"></i> Create Admin
                </button>
            </form>
        </div>
    </div>

    <!-- ============ EDIT MODAL ============ -->
    <div class="modal-backdrop" id="editModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('editModal')">&times;</button>
            <h3><i class="fas fa-pen" style="color:var(--primary)"></i> Edit Admin</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="update_admin">
                <input type="hidden" name="id" id="edit_id">

                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit_phone">
                    </div>
                    <div class="field">
                        <label>Role</label>
                        <select name="role_id" id="edit_role_id">
                            <?php foreach ($manageableRoles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>"><?= e($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- ============ RESET PASSWORD MODAL ============ -->
    <div class="modal-backdrop" id="resetModal">
        <div class="modal-box">
            <button class="close" onclick="closeModal('resetModal')">&times;</button>
            <h3><i class="fas fa-key" style="color:var(--primary)"></i> Reset Password</h3>
            <p id="reset_for" style="color:var(--muted); font-size:.85rem;"></p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_id">
                <div class="field">
                    <label>New Password (min 8 chars)</label>
                    <input type="text" name="new_password" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-check"></i> Reset Password
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function editAdmin(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_full_name').value = data.full_name || '';
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('edit_role_id').value = data.role_id;
            openModal('editModal');
        }

        function resetPassword(id, name) {
            document.getElementById('reset_id').value = id;
            document.getElementById('reset_for').textContent = 'Set a new password for: ' + name;
            openModal('resetModal');
        }

        // close on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(function(el) {
            el.addEventListener('click', function(ev) {
                if (ev.target === el) el.classList.remove('open');
            });
        });
    </script>
</body>

</html>