<?php
// ============================================================
// admin/customers.php - Customer Management (Unified)
// ============================================================
$currentPage = 'customers';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

// Initialize database connection
$pdo = getDBConnection();

// ------------------------------------------------------------
// Handle actions (delete, toggle status, bulk actions)
// ------------------------------------------------------------
$action  = $_GET['action'] ?? '';
$message = '';
$error   = '';

// Delete a single customer
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Customer deleted successfully.";
}

// Toggle active/inactive status
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE customers SET status = IF(status='active','inactive','active') WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Customer status toggled.";
}

// Bulk actions (e.g., bulk delete)
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $bulkAction = $_POST['bulk_action'];
    if ($bulkAction === 'delete') {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $message = count($ids) . " customers deleted.";
    }
    // Additional bulk actions (activate, deactivate) can be added here.
}

// Fetch customers for display
$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();

// Preserve messages from redirects
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error   = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Customers - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Basic styling – mirrors other admin pages for a premium look */
        * {margin:0;padding:0;box-sizing:border-box;}
        :root {
            --sidebar-bg:#0f172a;--sidebar-text:#e2e8f0;--accent:#3b82f6;--main-bg:#f1f5f9;
            --card-bg:#ffffff;--text-primary:#0f172a;--text-secondary:#475569;--border-light:#e2e8f0;
            --shadow:0 10px 25px -5px rgba(0,0,0,0.05);--radius-lg:1rem;--radius-md:0.75rem;
            --success:#10b981;--danger:#ef4444;--warning:#f59e0b;
        }
        body {font-family:'Inter',sans-serif;background:var(--main-bg);color:var(--text-primary);}
        .sidebar {width:280px;background:var(--sidebar-bg);position:fixed;top:0;bottom:0;left:0;z-index:1000;display:flex;flex-direction:column;overflow-y:auto;transition:all .3s ease;}
        .sidebar-header,.admin-profile{padding:20px 18px;border-bottom:1px solid rgba(255,255,255,0.08);}
        .admin-logo{display:flex;align-items:center;gap:12px;}
        .admin-logo i{font-size:28px;color:var(--accent);} .admin-logo h2{font-size:1.3rem;font-weight:700;color:white;}
        .nav-section h4{padding:0 20px;font-size:0.65rem;letter-spacing:1px;color:#64748b;margin-bottom:8px;text-transform:uppercase;}
        .nav-item{display:flex;align-items:center;gap:14px;padding:9px 20px;color:#e2e8f0;font-size:0.9rem;border-left:3px solid transparent;text-decoration:none;transition:0.2s;}
        .nav-item.active,.nav-item:hover{background:rgba(255,255,255,0.05);border-left-color:var(--accent);}
        .main-content{margin-left:280px;padding:20px;}
        .alert{padding:10px 15px;border-radius:var(--radius-md);margin-bottom:15px;}
        .alert-success{background:var(--success);color:#fff;}
        .alert-error{background:var(--danger);color:#fff;}
        table{width:100%;border-collapse:collapse;margin-top:20px;}
        th,td{padding:12px;text-align:left;border-bottom:1px solid var(--border-light);}
        th{background:var(--sidebar-bg);color:#fff;}
        .btn{padding:8px 12px;border:none;border-radius:var(--radius-md);cursor:pointer;}
        .btn-primary{background:var(--accent);color:#fff;}
        .btn-danger{background:var(--danger);color:#fff;}
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>
    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'customers'; include 'inc/sidebar.php'; ?>
    <main class="main-content">
        <h1>Customer Management</h1>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" id="bulkForm">
            <select name="bulk_action" class="btn">
                <option value="">Bulk Actions</option>
                <option value="delete">Delete Selected</option>
                <option value="activate">Activate Selected</option>
                <option value="deactivate">Deactivate Selected</option>
            </select>
            <button type="submit" class="btn btn-primary">Apply</button>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><input type="checkbox" name="selected_ids[]" value="<?php echo $c['id']; ?>"></td>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['email']); ?></td>
                        <td><?php echo $c['status']; ?></td>
                        <td><?php echo $c['created_at']; ?></td>
                        <td>
                            <a href="?action=toggle&id=<?php echo $c['id']; ?>" class="btn btn-primary">Toggle</a>
                            <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this customer?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </main>
    <script>
        // Simple "select all" checkbox logic
        document.getElementById('selectAll')?.addEventListener('change', function(){
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>