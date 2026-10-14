<?php
// ============================================================
// admin/payments.php - Payment Monitoring & Audit Dashboard
// ============================================================
$currentPage = 'payments';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';
require_once '../api/payment_state.php';
require_once '../api/razorpay_helper.php';

$pdo = getDBConnection();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminUserId = (int)($_SESSION['user_id'] ?? 0);

// --- HANDLE REFUND (full or partial) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_payment'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: payments.php?err=' . urlencode('CSRF token validation failed.'));
        exit;
    }

    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $amount     = (float)($_POST['refund_amount'] ?? 0);
    $reason     = trim($_POST['refund_reason'] ?? '');

    try {
        if ($payment_id <= 0 || $amount <= 0) {
            throw new Exception('A valid payment and refund amount are required.');
        }

        $stmt = $pdo->prepare("
            SELECT p.*, o.order_number, o.id AS order_id
            FROM payments p
            LEFT JOIN orders o ON p.order_id = o.id
            WHERE p.id = ? LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $payInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payInfo) {
            throw new Exception('Payment record not found.');
        }

        $currentStatus = ksrNormalizePaymentStatus($payInfo['status']);

        // Refunds are only valid from a settled payment.
        if (!in_array($currentStatus, ['success', 'cod_collected'], true)) {
            throw new Exception('Only a successful payment can be refunded. Current status: ' . $currentStatus . '.');
        }

        // Never refund more than was collected, minus what already went back.
        $alreadyRefunded = (float)$pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM refunds WHERE payment_id = {$payment_id} AND status = 'processed'"
        )->fetchColumn();

        $refundable = (float)$payInfo['amount'] - $alreadyRefunded;

        if ($amount > $refundable + 0.01) {
            throw new Exception('Refund amount exceeds the refundable balance of ₹' . number_format($refundable, 2) . '.');
        }

        $isPartial = ($amount < (float)$payInfo['amount'] - 0.01);

        $refundTxnId = 'REF' . date('YmdHis') . rand(100, 999);

        // --- Gateway refund for online payments -----------------------
        if ($payInfo['method'] !== 'cod') {
            $gatewayPaymentId = $payInfo['gateway_payment_id'] ?: $payInfo['transaction_id'];

            if (empty($gatewayPaymentId)) {
                throw new Exception('Cannot refund: gateway payment id is missing.');
            }

            $refundRes = RazorpayHelper::refundPayment(
                $gatewayPaymentId,
                $amount,
                'Refund for Order #' . $payInfo['order_number'] . ': ' . $reason
            );

            if (empty($refundRes['success'])) {
                throw new Exception('Razorpay refund API failed: ' . ($refundRes['error'] ?? 'Unknown error'));
            }

            $refundTxnId = $refundRes['refund_id'] ?? $refundRes['id'] ?? $refundTxnId;
        }

        $pdo->beginTransaction();

        // Move the payment into REFUND_PENDING via the state machine.
        applyPaymentStatus($pdo, $payment_id, 'refund_pending', [
            'event_type'             => 'REFUND_REQUESTED',
            'refund_transaction_id'  => $refundTxnId,
            'source'                 => 'admin',
            'payload'                => ['amount' => $amount, 'reason' => $reason, 'partial' => $isPartial],
        ]);

        // Record the refund ledger row.
        $stmt = $pdo->prepare("
            INSERT INTO refunds (order_id, payment_id, refund_id, amount, status, reason, gateway_response, created_at, processed_at)
            VALUES (?, ?, ?, ?, 'processed', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $payInfo['order_id'],
            $payment_id,
            $refundTxnId,
            $amount,
            $reason,
            json_encode(['initiated_by' => $adminUserId, 'at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_SLASHES),
        ]);

        // Fully refunded -> REFUNDED; partial leaves the rest settled.
        if (!$isPartial) {
            applyPaymentStatus($pdo, $payment_id, 'refunded', [
                'event_type'            => 'PAYMENT_REFUNDED',
                'refund_transaction_id' => $refundTxnId,
                'source'                => 'admin',
            ]);
        }

        $timelineNotes = ($isPartial ? 'Partial refund' : 'Refund')
            . ' of ₹' . number_format($amount, 2)
            . '. Gateway refund ID: ' . $refundTxnId
            . ($reason !== '' ? '. Reason: ' . $reason : '');

        $stmt = $pdo->prepare("
            INSERT INTO order_timeline (order_id, status, notes, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$payInfo['order_id'], $payInfo['order_number'] ? 'refunded' : 'refunded', $timelineNotes, $adminUserId]);

        $pdo->commit();

        $message = $isPartial
            ? '✅ Partial refund of ₹' . number_format($amount, 2) . ' processed successfully.'
            : '✅ Payment refunded successfully in local DB and via Razorpay gateway.';
    } catch (Exception $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Refund failed: ' . $e->getMessage();
    }

    header('Location: payments.php?msg=' . urlencode($message ?? '') . '&err=' . urlencode($error ?? ''));
    exit;
}

// --- COD: mark cash collected ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_cod'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: payments.php?err=' . urlencode('CSRF token validation failed.'));
        exit;
    }

    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $reference  = trim($_POST['collection_reference'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $pay = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pay) {
            throw new Exception('Payment record not found.');
        }

        $pdo->beginTransaction();

        applyPaymentStatus($pdo, $payment_id, 'cod_collected', [
            'event_type'           => 'COD_COLLECTED',
            'collected_amount'     => (float)$pay['amount'],
            'collected_by'         => (string)($_SESSION['admin_name'] ?? ('Admin #' . $adminUserId)),
            'collection_reference' => $reference ?: ('COD' . date('YmdHis')),
            'source'               => 'admin',
        ]);

        applyPaymentStatus($pdo, $payment_id, 'success', [
            'event_type' => 'COD_SETTLED',
            'source'     => 'admin',
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO order_timeline (order_id, status, notes, created_by)
            VALUES (?, (SELECT status FROM orders WHERE id = ?), ?, ?)
        ");
        $stmt->execute([
            $pay['order_id'],
            $pay['order_id'],
            'COD cash collected by admin: ₹' . number_format((float)$pay['amount'], 2),
            $adminUserId,
        ]);

        $pdo->commit();
        $message = '✅ COD marked as collected.';
    } catch (Exception $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'COD collection failed: ' . $e->getMessage();
    }

    header('Location: payments.php?msg=' . urlencode($message ?? '') . '&err=' . urlencode($error ?? ''));
    exit;
}

// --- DELETE PAYMENT ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        header('Location: payments.php?err=' . urlencode('CSRF token validation failed.'));
        exit;
    }
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM payment_events WHERE payment_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Payment record deleted.';
    } catch (PDOException $e) {
        $error = 'Cannot delete: ' . $e->getMessage();
    }
    header('Location: payments.php?msg=' . urlencode($message ?? '') . '&err=' . urlencode($error ?? ''));
    exit;
}

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------
$fDate   = trim((string)($_GET['f_date'] ?? ''));
$fStatus = trim((string)($_GET['f_status'] ?? ''));
$fMethod = trim((string)($_GET['f_method'] ?? ''));
$fGateway= trim((string)($_GET['f_gateway'] ?? ''));
$fOrder  = trim((string)($_GET['f_order'] ?? ''));
$fTxn    = trim((string)($_GET['f_txn'] ?? ''));
$fCustomer = trim((string)($_GET['f_customer'] ?? ''));

$where  = [];
$params = [];

if ($fDate !== '') {
    $where[] = 'DATE(p.created_at) = ?';
    $params[] = $fDate;
}
if ($fStatus !== '') {
    $where[] = 'p.status = ?';
    $params[] = $fStatus;
}
if ($fMethod !== '') {
    $where[] = 'p.method = ?';
    $params[] = $fMethod;
}
if ($fGateway !== '') {
    $where[] = 'p.gateway = ?';
    $params[] = $fGateway;
}
if ($fOrder !== '') {
    $where[] = 'o.order_number LIKE ?';
    $params[] = '%' . $fOrder . '%';
}
if ($fTxn !== '') {
    $where[] = '(p.transaction_id LIKE ? OR p.gateway_payment_id LIKE ? OR p.gateway_order_id LIKE ?)';
    $t = '%' . $fTxn . '%';
    array_push($params, $t, $t, $t);
}
if ($fCustomer !== '') {
    $where[] = '(u.name LIKE ? OR o.name LIKE ? OR o.phone LIKE ?)';
    $c = '%' . $fCustomer . '%';
    array_push($params, $c, $c, $c);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- FETCH PAYMENTS ---
$sql = "
    SELECT p.*,
           o.order_number,
           o.name AS order_name,
           o.phone AS order_phone,
           u.name AS customer_name,
           u.email AS customer_email
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    {$whereSql}
    ORDER BY p.created_at DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- TODAY'S SUMMARY (independent of filters) ---
$today = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN status IN ('success','cod_collected') THEN amount END), 0) AS success_amount,
        COUNT(CASE WHEN status IN ('success','cod_collected') THEN 1 END) AS success_count,

        COALESCE(SUM(CASE WHEN status IN ('pending','initiated','processing','cod_pending') THEN amount END), 0) AS pending_amount,
        COUNT(CASE WHEN status IN ('pending','initiated','processing','cod_pending') THEN 1 END) AS pending_count,

        COALESCE(SUM(CASE WHEN status IN ('failed','cancelled','expired') THEN amount END), 0) AS failed_amount,
        COUNT(CASE WHEN status IN ('failed','cancelled','expired') THEN 1 END) AS failed_count,

        COALESCE(SUM(CASE WHEN status IN ('refunded','refund_pending') THEN amount END), 0) AS refund_amount,
        COUNT(CASE WHEN status IN ('refunded','refund_pending') THEN 1 END) AS refund_count
    FROM payments
    WHERE DATE(created_at) = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

// Stats over the fetched/filtered set.
$totalPayments = count($payments);
$totalAmount   = array_sum(array_column($payments, 'amount'));
$successful    = count(array_filter($payments, fn($p) => in_array(ksrNormalizePaymentStatus($p['status']), ['success', 'cod_collected'], true)));
$failed        = count(array_filter($payments, fn($p) => in_array(ksrNormalizePaymentStatus($p['status']), ['failed', 'cancelled', 'expired'], true)));
$refunded      = count(array_filter($payments, fn($p) => in_array(ksrNormalizePaymentStatus($p['status']), ['refunded', 'refund_pending'], true)));

// --- PAYMENT TIMELINE for a selected payment ---
$timelinePaymentId = (int)($_GET['timeline'] ?? 0);
$timelineEvents = [];
$timelinePayment = null;

if ($timelinePaymentId > 0) {
    $tStmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
    $tStmt->execute([$timelinePaymentId]);
    $timelinePayment = $tStmt->fetch(PDO::FETCH_ASSOC);

    if ($timelinePayment) {
        $eStmt = $pdo->prepare("
            SELECT * FROM payment_events
            WHERE payment_id = ? OR order_id = ?
            ORDER BY id ASC LIMIT 200
        ");
        $eStmt->execute([$timelinePaymentId, $timelinePayment['order_id']]);
        $timelineEvents = $eStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle messages
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

// Status badge styling helper.
function ksrStatusBadgeClass(string $status): string {
    $status = ksrNormalizePaymentStatus($status);
    return match ($status) {
        'success', 'cod_collected' => 'success',
        'failed'                   => 'failed',
        'refunded', 'refund_pending' => 'refunded',
        'cancelled', 'expired'     => 'failed',
        'cod_pending'              => 'pending_verification',
        'initiated'                => 'initiated',
        default                    => 'pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same styles as products.php */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --sidebar-bg: #0f172a;
            --accent: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            --radius-lg: 1rem;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
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
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .status-badge.success { background: #dcfce7; color: #15803d; }
        .status-badge.failed { background: #fee2e2; color: #b91c1c; }
        .status-badge.refunded { background: #fee2e2; color: #b91c1c; } /* fallback red or amber */
        .status-badge.refunded { background: #fffbeb; color: #d97706; }
        .status-badge.initiated { background: #dbeafe; color: #1e40af; }
        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.pending_verification { background: #f3e8ff; color: #6b21a8; }
        .table-container { background: var(--card-bg); border-radius: var(--radius-lg); padding: 16px 18px; box-shadow: var(--shadow); overflow-x: auto; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .data-table th, .data-table td { padding: 10px 8px; text-align: left; border-bottom: 1px solid var(--border-light); }
        .data-table th { font-weight: 600; color: var(--text-secondary); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn { padding: 8px 20px; border-radius: 40px; border: none; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-light); color: var(--text-primary); }
        .btn-outline:hover { background: var(--main-bg); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--card-bg); border-radius: var(--radius-lg); max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalIn 0.25s ease; }
        @keyframes modalIn { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid var(--border-light); }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.3rem; color: var(--text-secondary); cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: 0.2s; }
        .modal-close:hover { background: var(--main-bg); }
        .modal-body { padding: 20px 22px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 10px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border-radius: 0.75rem; border: 1px solid var(--border-light); background: var(--main-bg); color: var(--text-primary); font-size: 0.9rem; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 12px 24px; border-radius: 40px; color: white; font-weight: 500; font-size: 0.9rem; z-index: 5000; box-shadow: 0 8px 30px rgba(0,0,0,0.2); animation: toastIn 0.3s ease; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        @keyframes toastIn { from { transform: translateX(-50%) translateY(20px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.mobile-open { transform: translateX(0); } .main-content { margin-left: 0; padding: 16px; } .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>
    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'payments'; include 'inc/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h1><i class="fas fa-credit-card"></i> Payments</h1>
                <div class="breadcrumb">Admin / Payments</div>
            </div>
            <div class="header-right">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Search transactions..." id="searchInput"></div>
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info"><span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span><span class="user-role">Admin</span></div>
                </div>
            </div>
        </header>

        <!-- Today's summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Today · Successful</div>
                <div class="stat-value" style="color:var(--success);">₹<?= number_format((float)$today['success_amount'], 0) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary);"><?= (int)$today['success_count'] ?> payments</div>
                <div class="stat-icon"><i class="fas fa-check-circle" style="color:var(--success);"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today · Pending</div>
                <div class="stat-value" style="color:var(--warning);">₹<?= number_format((float)$today['pending_amount'], 0) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary);"><?= (int)$today['pending_count'] ?> payments</div>
                <div class="stat-icon"><i class="fas fa-clock" style="color:var(--warning);"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today · Failed</div>
                <div class="stat-value" style="color:var(--danger);">₹<?= number_format((float)$today['failed_amount'], 0) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary);"><?= (int)$today['failed_count'] ?> payments</div>
                <div class="stat-icon"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today · Refunded</div>
                <div class="stat-value" style="color:var(--accent);">₹<?= number_format((float)$today['refund_amount'], 0) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary);"><?= (int)$today['refund_count'] ?> refunds</div>
                <div class="stat-icon"><i class="fas fa-undo-alt" style="color:var(--accent);"></i></div>
            </div>
        </div>

        <!-- Filtered-set overview -->
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
            <div class="stat-card"><div class="stat-label">Records Shown</div><div class="stat-value" style="font-size:1.25rem;"><?= number_format($totalPayments) ?></div></div>
            <div class="stat-card"><div class="stat-label">Successful</div><div class="stat-value" style="font-size:1.25rem;color:var(--success);"><?= number_format($successful) ?></div></div>
            <div class="stat-card"><div class="stat-label">Failed / Expired</div><div class="stat-value" style="font-size:1.25rem;color:var(--danger);"><?= number_format($failed) ?></div></div>
            <div class="stat-card"><div class="stat-label">Refunds</div><div class="stat-value" style="font-size:1.25rem;color:var(--warning);"><?= number_format($refunded) ?></div></div>
            <div class="stat-card"><div class="stat-label">Amount</div><div class="stat-value" style="font-size:1.25rem;">₹<?= number_format($totalAmount, 2) ?></div></div>
        </div>

        <?php if ($message): ?><div class="toast show success" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="toast show error" style="position:relative;opacity:1;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Filters -->
        <div class="table-container" style="margin-bottom:18px;">
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label>Date</label>
                    <input type="date" name="f_date" value="<?= htmlspecialchars($fDate) ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Status</label>
                    <select name="f_status">
                        <option value="">All statuses</option>
                        <?php foreach (ksrPaymentStates() as $st): ?>
                            <option value="<?= htmlspecialchars($st) ?>" <?= $fStatus === $st ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $st))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Method</label>
                    <select name="f_method">
                        <option value="">All methods</option>
                        <?php foreach (['cod', 'upi', 'card', 'bank_transfer'] as $m): ?>
                            <option value="<?= $m ?>" <?= $fMethod === $m ? 'selected' : '' ?>><?= strtoupper($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Gateway</label>
                    <select name="f_gateway">
                        <option value="">All</option>
                        <option value="razorpay" <?= $fGateway === 'razorpay' ? 'selected' : '' ?>>Razorpay</option>
                        <option value="cod" <?= $fGateway === 'cod' ? 'selected' : '' ?>>COD</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Order No.</label>
                    <input type="text" name="f_order" value="<?= htmlspecialchars($fOrder) ?>" placeholder="KSR-2026-0001">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Transaction ID</label>
                    <input type="text" name="f_txn" value="<?= htmlspecialchars($fTxn) ?>" placeholder="pay_... / rzp_order_...">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Customer</label>
                    <input type="text" name="f_customer" value="<?= htmlspecialchars($fCustomer) ?>" placeholder="Name or phone">
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                    <a href="payments.php" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>Transactions</h3>
                <button class="btn btn-sm btn-outline" id="exportBtn"><i class="fas fa-download"></i> Export</button>
            </div>
            <table class="data-table" id="paymentTable">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Gateway Ref</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p):
                        $pStatus = ksrNormalizePaymentStatus($p['status']);
                        $badgeClass = ksrStatusBadgeClass($p['status']);
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars((string)($p['order_number'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($p['customer_name'] ?? $p['order_name'] ?? 'Guest') ?></td>
                        <td>₹<?= number_format((float)$p['amount'], 2) ?></td>
                        <td><span class="status-badge" style="background:#dbeafe;color:#1e40af;"><?= strtoupper(htmlspecialchars((string)$p['method'])) ?></span></td>
                        <td><code style="font-size:0.72rem;"><?= htmlspecialchars((string)($p['gateway_payment_id'] ?: ($p['gateway_order_id'] ?: ($p['transaction_id'] ?? '—')))) ?></code></td>
                        <td><span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $pStatus))) ?></span></td>
                        <td><?= date('d M Y, h:i A', strtotime((string)$p['created_at'])) ?></td>
                        <td style="white-space:nowrap;">
                            <a class="view-btn" href="?timeline=<?= (int)$p['id'] ?>" title="Timeline" style="color:var(--accent);text-decoration:none;"><i class="fas fa-stream"></i></a>
                            <?php if (in_array($pStatus, ['success', 'cod_collected'], true)): ?>
                                <button class="view-btn refund-btn" data-id="<?= (int)$p['id'] ?>" data-amount="<?= (float)$p['amount'] ?>" title="Refund" style="color:var(--warning);border:none;background:none;cursor:pointer;"><i class="fas fa-undo-alt"></i></button>
                            <?php endif; ?>
                            <?php if ($pStatus === 'cod_pending'): ?>
                                <button class="view-btn collect-btn" data-id="<?= (int)$p['id'] ?>" data-amount="<?= (float)$p['amount'] ?>" title="Collect Cash" style="color:var(--success);border:none;background:none;cursor:pointer;"><i class="fas fa-hand-holding-usd"></i></button>
                            <?php endif; ?>
                            <button class="view-btn delete-btn" data-id="<?= (int)$p['id'] ?>" title="Delete" style="color:var(--danger);border:none;background:none;cursor:pointer;"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center" style="padding:30px;color:var(--text-secondary);">No payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Refund Modal (full or partial) -->
    <div class="modal-overlay" id="refundModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Process Refund</h3>
                <button class="modal-close" onclick="closeModal('refundModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="payment_id" id="refundPaymentId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Original Amount</label>
                        <input type="text" id="refundOriginal" readonly style="background:#f1f5f9;font-weight:700;">
                    </div>
                    <div class="form-group">
                        <label>Refund Amount</label>
                        <input type="number" step="0.01" min="0.01" name="refund_amount" id="refundAmount" required>
                        <small style="color:var(--text-secondary);font-size:0.72rem;">Enter a smaller amount for a partial refund.</small>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="refund_reason" rows="3" placeholder="Why is this refund being processed?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('refundModal')">Cancel</button>
                    <button type="submit" name="refund_payment" class="btn btn-danger">Process Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COD Collection Modal -->
    <div class="modal-overlay" id="codModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Collect Cash on Delivery</h3>
                <button class="modal-close" onclick="closeModal('codModal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="payment_id" id="codPaymentId">
                <div class="modal-body">
                    <div style="text-align:center;margin-bottom:14px;">
                        <div style="font-size:0.72rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;">Amount to Collect</div>
                        <div id="codAmount" style="font-size:2rem;font-weight:800;">₹0.00</div>
                        <div style="font-size:0.8rem;color:var(--text-secondary);">Payment: Cash on Delivery</div>
                    </div>
                    <div class="form-group">
                        <label>Collection Reference (optional)</label>
                        <input type="text" name="collection_reference" placeholder="Receipt / slip number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('codModal')">Cancel</button>
                    <button type="submit" name="collect_cod" class="btn btn-success"><i class="fas fa-hand-holding-usd"></i> Cash Received</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Timeline Drawer -->
    <?php if ($timelinePayment): ?>
    <div class="modal-overlay active" id="timelineModal" onclick="if(event.target===this){window.location='payments.php';}">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-stream" style="color:var(--accent);"></i> Payment Timeline</h3>
                <button class="modal-close" onclick="window.location='payments.php'"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <?php
                $tpStatus = ksrNormalizePaymentStatus($timelinePayment['status']);
                ?>
                <div style="margin-bottom:16px;">
                    <span class="status-badge <?= ksrStatusBadgeClass($timelinePayment['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $tpStatus))) ?></span>
                    <span style="float:right;font-weight:700;">₹<?= number_format((float)$timelinePayment['amount'], 2) ?></span>
                </div>
                <?php if (!empty($timelineEvents)): ?>
                    <?php foreach ($timelineEvents as $ev): ?>
                        <div style="display:flex;gap:12px;padding-bottom:14px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0;"></div>
                            <div style="flex:1;">
                                <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string)$ev['event_type'])))) ?></div>
                                <div style="font-size:0.72rem;color:var(--text-secondary);">
                                    <?= date('h:i:s A', strtotime((string)$ev['created_at'])) ?>
                                    <?php if (!empty($ev['old_status']) || !empty($ev['new_status'])): ?>
                                        &nbsp;·&nbsp; <?= htmlspecialchars((string)$ev['old_status']) ?> → <?= htmlspecialchars((string)$ev['new_status']) ?>
                                    <?php endif; ?>
                                    &nbsp;·&nbsp; <?= htmlspecialchars((string)$ev['source']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-secondary);">No events recorded for this payment yet.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline" href="payments.php">Close</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this payment record?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        document.querySelectorAll('.refund-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const amount = parseFloat(this.dataset.amount) || 0;
                document.getElementById('refundPaymentId').value = this.dataset.id;
                document.getElementById('refundAmount').value = amount.toFixed(2);
                document.getElementById('refundOriginal').value = '₹' + amount.toFixed(2);
                openModal('refundModal');
            });
        });

        document.querySelectorAll('.collect-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const amount = parseFloat(this.dataset.amount) || 0;
                document.getElementById('codPaymentId').value = this.dataset.id;
                document.getElementById('codAmount').textContent = '₹' + amount.toFixed(2);
                openModal('codModal');
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
                document.getElementById('confirmDeleteBtn').href = 'payments.php?action=delete&id=' + this.dataset.id + '&csrf_token=' + csrfToken;
                openModal('deleteModal');
            });
        });

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('#paymentTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
            });
        });

        document.getElementById('exportBtn').addEventListener('click', function() {
            let csv = 'Order,Customer,Amount,Method,Gateway Ref,Status,Date\n';
            document.querySelectorAll('#paymentTable tbody tr').forEach(row => {
                if (row.style.display === 'none') return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 7) return;
                const vals = [];
                for (let i = 0; i < 7; i++) {
                    vals.push('"' + cells[i].textContent.trim().replace('₹', '').replace(/"/g, '""') + '"');
                }
                csv += vals.join(',') + '\n';
            });
            const blob = new Blob([csv], {type: 'text/csv'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'payments.csv';
            a.click();
        });

        setTimeout(() => { const toast = document.querySelector('.toast'); if (toast) toast.style.opacity = '0'; }, 4000);
    </script>
</body>
</html>