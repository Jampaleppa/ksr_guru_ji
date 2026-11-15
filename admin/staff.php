<?php
// ============================================================
// admin/staff.php — Staff & Role Management
// ============================================================
$currentPage = 'staff';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo       = getDBConnection();
$message   = '';
$error     = '';
$selfId    = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add staff member
    if ($action === 'add_staff') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'staff';

        $allowedRoles = ['admin', 'staff', 'delivery_manager'];
        if (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role selected.';
        } elseif (!$name || !$email || !$password) {
            $error = 'Name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                        ->execute([$name, $email, $hash, $role]);
                    $message = "Staff member '{$name}' added successfully.";
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // Toggle active/inactive
    elseif ($action === 'toggle' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        if ($id === $selfId) {
            $error = 'You cannot deactivate your own account.';
        } else {
            try {
                $pdo->prepare("UPDATE users SET status = 1 - status WHERE id = ? AND id != ?")->execute([$id, $selfId]);
                $message = 'Staff status updated.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }

    // Delete staff
    elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        if ($id === $selfId) {
            $error = 'You cannot delete your own account.';
        } else {
            try {
                $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?")->execute([$id, $selfId]);
                $message = 'Staff member removed.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

try {
    $staffList = $pdo->query(
        "SELECT id, name, email, role, status, created_at, last_login_at
         FROM users
         WHERE role IN ('admin','staff','delivery_manager')
         ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staffList = [];
    if (!$error) $error = 'Could not load staff list.';
}

$roleBadgeColor = ['admin' => 'danger', 'staff' => 'primary', 'delivery_manager' => 'warning'];

require_once 'inc/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fas fa-users-cog text-primary"></i> Staff & Role Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-user-plus"></i> Add Staff
        </button>
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

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Name</th><th>Email</th><th>Role</th>
                            <th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No staff members found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($staffList as $i => $s):
                            $isSelf  = ((int)$s['id'] === $selfId);
                            $badgeColor = $roleBadgeColor[$s['role']] ?? 'secondary';
                        ?>
                        <tr>
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($s['name']) ?></strong>
                                <?php if ($isSelf): ?> <span class="badge bg-dark ms-1">You</span><?php endif; ?>
                            </td>
                            <td><small><?= htmlspecialchars($s['email']) ?></small></td>
                            <td><span class="badge bg-<?= $badgeColor ?>"><?= htmlspecialchars($s['role']) ?></span></td>
                            <td>
                                <?php if ($s['status']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($s['last_login_at'] ?? '—') ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars(substr($s['created_at'] ?? '—', 0, 10)) ?></small></td>
                            <td>
                                <?php if (!$isSelf): ?>
                                <div class="d-flex gap-1">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $s['status'] ? 'warning' : 'success' ?>" title="<?= $s['status'] ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas fa-<?= $s['status'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($s['name'])) ?>?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Staff Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_staff">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Ravi Kumar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="ravi@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="delivery_manager">Delivery Manager</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'inc/footer.php'; ?>
