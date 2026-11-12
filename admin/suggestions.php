<?php
// ============================================================
// admin/suggestions.php - Missing Product Suggestion Management
// ============================================================
$currentPage = 'suggestions';
require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/functions.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle Actions (Approve, Reject, Sourced, Assign Delivery, Add to Catalog)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $suggId = (int)($_POST['id'] ?? 0);

    if ($suggId > 0) {
        if ($action === 'update_status') {
            $newStatus = trim((string)($_POST['status'] ?? 'pending'));
            $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
            $supplierInfo = trim((string)($_POST['supplier_info'] ?? ''));
            $deliveryId = !empty($_POST['assigned_delivery_id']) ? (int)$_POST['assigned_delivery_id'] : null;

            $allowedStatuses = ['pending', 'under_review', 'approved', 'sourced', 'added_to_catalog', 'rejected', 'cancelled'];
            if (in_array($newStatus, $allowedStatuses, true)) {
                $stmt = $pdo->prepare("
                    UPDATE product_suggestions
                    SET status = ?, admin_notes = ?, supplier_info = ?,
                        assigned_delivery_id = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $adminId = $_SESSION['admin_auth_id'] ?? $_SESSION['admin_user_id'] ?? null;
                $stmt->execute([$newStatus, $adminNotes, $supplierInfo, $deliveryId, $adminId, $suggId]);
                $message = "Suggestion updated successfully to status: " . strtoupper($newStatus);
            }
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM product_suggestions WHERE id = ?");
            $stmt->execute([$suggId]);
            $message = "Suggestion removed.";
        }
    }
}

// Fetch Delivery Agents for assignment
$deliveryAgents = [];
try {
    $deliveryAgents = $pdo->query("SELECT id, name, phone FROM delivery_boys ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Filter by status
$statusFilter = trim((string)($_GET['status'] ?? ''));
$sql = "SELECT s.*, db.name AS delivery_agent_name, db.phone AS delivery_agent_phone
        FROM product_suggestions s
        LEFT JOIN delivery_boys db ON s.assigned_delivery_id = db.id";
$params = [];
if ($statusFilter !== '') {
    $sql .= " WHERE s.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'all' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0,
    'sourced' => 0, 'added_to_catalog' => 0, 'rejected' => 0
];
try {
    $st = $pdo->query("SELECT status, COUNT(*) as c FROM product_suggestions GROUP BY status");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']] = (int)$r['c'];
        }
        $counts['all'] += (int)$r['c'];
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Suggestions · KSR GURU JI Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-under_review { background: #e0f2fe; color: #0369a1; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-sourced { background: #f3e8ff; color: #7e22ce; }
        .badge-added_to_catalog { background: #bbf7d0; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab {
            padding: 8px 16px; border-radius: 20px; background: #fff; text-decoration: none;
            color: #475569; font-size: 0.88rem; font-weight: 600; border: 1px solid #cbd5e1;
        }
        .filter-tab.active { background: #0f7b3a; color: #fff; border-color: #0f7b3a; }
        .action-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 16px; }
    </style>
</head>
<body>
    <?php include 'inc/header.php'; ?>
    <div class="admin-container">
        <?php include 'inc/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h1><i class="fas fa-lightbulb"></i> Customer Product Suggestions</h1>
                    <p style="color:#64748b;">Review missing product requests from customers across all categories</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success" style="padding:12px; background:#dcfce7; color:#15803d; border-radius:8px; margin-bottom:20px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="suggestions.php" class="filter-tab <?php echo $statusFilter === '' ? 'active' : ''; ?>">
                    All (<?php echo $counts['all']; ?>)
                </a>
                <a href="suggestions.php?status=pending" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $counts['pending']; ?>)
                </a>
                <a href="suggestions.php?status=approved" class="filter-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $counts['approved']; ?>)
                </a>
                <a href="suggestions.php?status=sourced" class="filter-tab <?php echo $statusFilter === 'sourced' ? 'active' : ''; ?>">
                    Sourced (<?php echo $counts['sourced']; ?>)
                </a>
                <a href="suggestions.php?status=added_to_catalog" class="filter-tab <?php echo $statusFilter === 'added_to_catalog' ? 'active' : ''; ?>">
                    In Catalog (<?php echo $counts['added_to_catalog']; ?>)
                </a>
                <a href="suggestions.php?status=rejected" class="filter-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $counts['rejected']; ?>)
                </a>
            </div>

            <div class="table-card" style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
                <table class="table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left; font-size:0.85rem; color:#475569;">
                            <th style="padding:14px;">Req ID</th>
                            <th style="padding:14px;">Product & Category</th>
                            <th style="padding:14px;">Customer Info</th>
                            <th style="padding:14px;">Target Price</th>
                            <th style="padding:14px;">Status</th>
                            <th style="padding:14px;">Assigned Delivery</th>
                            <th style="padding:14px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suggestions)): ?>
                            <tr>
                                <td colspan="7" style="padding:40px; text-align:center; color:#94a3b8;">
                                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                    No suggestions found for this status.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($suggestions as $s): ?>
                            <tr style="border-bottom:1px solid #f1f5f9; font-size:0.9rem;">
                                <td style="padding:14px; font-family:monospace; font-weight:700;">
                                    <?php echo htmlspecialchars($s['request_id']); ?><br>
                                    <small style="color:#94a3b8;"><?php echo date('d M Y, H:i', strtotime($s['created_at'])); ?></small>
                                </td>
                                <td style="padding:14px;">
                                    <strong><?php echo htmlspecialchars($s['product_name']); ?></strong><br>
                                    <span style="font-size:0.8rem; color:#0f7b3a; font-weight:600;">
                                        <?php echo htmlspecialchars($s['category_name'] ?: 'General'); ?>
                                    </span>
                                    <?php if (!empty($s['details'])): ?>
                                        <p style="font-size:0.82rem; color:#64748b; margin-top:4px;">
                                            <em><?php echo htmlspecialchars($s['details']); ?></em>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px;">
                                    <?php echo htmlspecialchars($s['customer_name'] ?: 'Guest'); ?><br>
                                    <a href="tel:<?php echo htmlspecialchars($s['customer_phone']); ?>" style="color:#0f7b3a; text-decoration:none; font-weight:600;">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($s['customer_phone']); ?>
                                    </a>
                                </td>
                                <td style="padding:14px; font-weight:700; color:#1e293b;">
                                    <?php echo $s['preferred_price'] ? '₹' . number_format((float)$s['preferred_price'], 2) : '—'; ?>
                                </td>
                                <td style="padding:14px;">
                                    <span class="badge badge-<?php echo htmlspecialchars($s['status']); ?>">
                                        <?php echo str_replace('_', ' ', htmlspecialchars($s['status'])); ?>
                                    </span>
                                </td>
                                <td style="padding:14px;">
                                    <?php if (!empty($s['delivery_agent_name'])): ?>
                                        <i class="fas fa-truck" style="color:#0f7b3a;"></i> 
                                        <strong><?php echo htmlspecialchars($s['delivery_agent_name']); ?></strong><br>
                                        <small style="color:#64748b;"><?php echo htmlspecialchars($s['delivery_agent_phone']); ?></small>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:0.85rem;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px; text-align:right;">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($s)); ?>)" style="background:#0f7b3a; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Manage Modal -->
            <div id="manageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; width:90%; max-width:550px; border-radius:12px; padding:24px; max-height:90vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h2 style="font-size:1.2rem;">Manage Suggestion: <span id="modalReqId"></span></h2>
                        <button type="button" onclick="closeModal()" style="border:none; background:transparent; font-size:1.2rem; cursor:pointer;">&times;</button>
                    </div>

                    <form action="suggestions.php" method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" id="modalId">

                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; font-size:0.88rem;">Workflow Status</label>
                            <select name="status" id="modalStatus" style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1;">
                                <option value="pending">Pending Review</option>
                                <option value="under_review">Under Review</option>
                                <option value="approved">Approved for Sourcing</option>
                                <option value="sourced">Product Sourced & Ready</option>
                                <option value="added_to_catalog">Added to Online Catalog</option>
                                <option value="rejected">Rejected / Unavailable</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; font-size:0.88rem;">Assign Delivery Agent (for sourcing/pickup)</label>
                            <select name="assigned_delivery_id" id="modalDelivery" style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1;">
                                <option value="">-- No Delivery Agent Assigned --</option>
                                <?php foreach ($deliveryAgents as $agent): ?>
                                    <option value="<?php echo (int)$agent['id']; ?>">
                                        <?php echo htmlspecialchars($agent['name']); ?> (<?php echo htmlspecialchars($agent['phone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; font-size:0.88rem;">Supplier & Cost Information</label>
                            <input type="text" name="supplier_info" id="modalSupplier" placeholder="e.g. Metro Cash & Carry, Local APMC wholesale, Unit cost ₹120..." style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1;">
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; font-weight:700; margin-bottom:6px; font-size:0.88rem;">Admin Notes (Internal)</label>
                            <textarea name="admin_notes" id="modalNotes" rows="3" placeholder="Notes on procurement, stock arrival, customer notification..." style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1;"></textarea>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button type="button" onclick="closeModal()" style="padding:8px 16px; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer;">Cancel</button>
                            <button type="submit" style="padding:8px 16px; border-radius:6px; border:none; background:#0f7b3a; color:#fff; font-weight:700; cursor:pointer;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openUpdateModal(data) {
            document.getElementById('modalId').value = data.id;
            document.getElementById('modalReqId').textContent = data.request_id;
            document.getElementById('modalStatus').value = data.status;
            document.getElementById('modalDelivery').value = data.assigned_delivery_id || '';
            document.getElementById('modalSupplier').value = data.supplier_info || '';
            document.getElementById('modalNotes').value = data.admin_notes || '';
            document.getElementById('manageModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('manageModal').style.display = 'none';
        }
    </script>
</body>
</html>

