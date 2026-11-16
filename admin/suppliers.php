<?php
// ============================================================
// admin/suppliers.php – Seller / Supplier Management
// ============================================================
$currentPage = 'suppliers';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

// Ensure suppliers table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        contact     TEXT,
        phone       TEXT,
        email       TEXT,
        address     TEXT,
        category    TEXT,
        notes       TEXT,
        status      TEXT NOT NULL DEFAULT 'active',
        created_at  TEXT DEFAULT (datetime('now', 'localtime')),
        updated_at  TEXT DEFAULT (datetime('now', 'localtime'))
    )");
} catch (Throwable $e) {}

$message = '';
$error   = '';

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $cat     = trim($_POST['category'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if (!$name) {
        $error = 'Supplier name is required.';
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE suppliers SET name=?,phone=?,email=?,address=?,category=?,notes=?,status=?,updated_at=datetime('now','localtime') WHERE id=?")
                    ->execute([$name,$phone,$email,$address,$cat,$notes,$status,$id]);
                $message = 'Supplier updated successfully.';
            } else {
                $pdo->prepare("INSERT INTO suppliers (name,phone,email,address,category,notes,status) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name,$phone,$email,$address,$cat,$notes,$status]);
                $message = 'Supplier added successfully.';
            }
        } catch (Throwable $e) {
            $error = 'DB error: ' . $e->getMessage();
        }
    }
}

// Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try { $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$delId]); $message = 'Supplier deleted.'; } catch (Throwable $e) {}
}

// Fetch all suppliers
$suppliers = [];
try {
    $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Edit mode
$editSupplier = null;
if (isset($_GET['edit'])) {
    $eId = (int)$_GET['edit'];
    foreach ($suppliers as $s) { if ((int)$s['id'] === $eId) { $editSupplier = $s; break; } }
}

$pageTitle  = 'Supplier / Seller Management';
require_once 'inc/header.php';
?>
    <div class="page-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="subtitle">Manage your vendors and suppliers for all product categories.</p>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3><?= $editSupplier ? 'Edit Supplier' : 'Add New Supplier' ?></h3></div>
        <div class="card-body">
            <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                <?php if ($editSupplier): ?><input type="hidden" name="id" value="<?= (int)$editSupplier['id'] ?>"><?php endif; ?>
                <div>
                    <label class="form-label">Name *</label>
                    <input class="form-control" name="name" required value="<?= htmlspecialchars($editSupplier['name'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" value="<?= htmlspecialchars($editSupplier['phone'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($editSupplier['email'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" placeholder="e.g. Vegetables, Spices" value="<?= htmlspecialchars($editSupplier['category'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label class="form-label">Address</label>
                    <input class="form-control" name="address" value="<?= htmlspecialchars($editSupplier['address'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($editSupplier['notes'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="active" <?= (!$editSupplier || $editSupplier['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editSupplier && $editSupplier['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end;gap:8px;">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-save"></i> <?= $editSupplier ? 'Update' : 'Add Supplier' ?>
                    </button>
                    <?php if ($editSupplier): ?>
                        <a href="suppliers.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- SUPPLIER LIST -->
    <div class="card">
        <div class="card-header"><h3>All Suppliers (<?= count($suppliers) ?>)</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($suppliers)): ?>
                <div style="text-align:center;padding:40px;color:#94a3b8;">
                    <i class="fas fa-truck-loading" style="font-size:2.5rem;margin-bottom:12px;"></i>
                    <p>No suppliers added yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($s['phone'] ?? '—', ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($s['email'] ?? '—', ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($s['category'] ?? '—', ENT_QUOTES) ?></td>
                                <td><span class="badge <?= $s['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
                                <td>
                                    <a href="suppliers.php?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="suppliers.php?delete=<?= (int)$s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this supplier?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php require_once 'inc/footer.php'; ?>
