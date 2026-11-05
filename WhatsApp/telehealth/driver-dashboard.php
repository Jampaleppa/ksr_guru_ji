<?php
/**
 * driver-dashboard.php — KSR GURU JI  •  Live Driver Operations Portal
 * Pulls real-time data from:
 *   - pharmacy_orders  (via getAssistDB / telehealth DB)
 *   - parcel_requests  (via same MySQL DB — same DB_* constants)
 * PHP 7.4+ / 8.x  •  MySQL (production) / SQLite (local fallback)
 */
require_once 'helper.php';

/* ── POST: mark pharmacy order delivered ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getAssistDB();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'deliver_pharm') {
        $code = trim((string)($_POST['order_code'] ?? ''));
        if ($code !== '') {
            try {
                $pdo->prepare("UPDATE pharmacy_orders SET status='delivered', verified_at=NOW() WHERE order_code=?")
                    ->execute([$code]);
            } catch (Throwable $e) {
                error_log('[driver-dashboard] deliver_pharm: ' . $e->getMessage());
            }
        }
        header('Location: driver-dashboard.php');
        exit;
    }

    if ($action === 'deliver_parcel') {
        $id = (int)($_POST['parcel_id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("UPDATE parcel_requests SET delivery_status='DELIVERED', payment_status=CASE WHEN payment_status='PENDING' THEN 'CONFIRMED' ELSE payment_status END, updated_at=NOW() WHERE id=?")
                    ->execute([$id]);
            } catch (Throwable $e) {
                error_log('[driver-dashboard] deliver_parcel: ' . $e->getMessage());
            }
        }
        header('Location: driver-dashboard.php');
        exit;
    }
}

/* ── Live DB queries ─────────────────────────────────────────────── */
$pdo = getAssistDB();
$today = date('Y-m-d');
$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

/* -- Pharmacy orders: today's delivered count ----------------------- */
$pharmDelivered = 0;
try {
    $q = $isSqlite
        ? "SELECT COUNT(*) FROM pharmacy_orders WHERE status='delivered' AND DATE(verified_at)=DATE('now')"
        : "SELECT COUNT(*) FROM pharmacy_orders WHERE status='delivered' AND DATE(verified_at)=CURDATE()";
    $pharmDelivered = (int)$pdo->query($q)->fetchColumn();
} catch (Throwable $e) {}

/* -- Pharmacy orders: active (dispatched) --------------------------- */
$activePharmOrders = [];
try {
    $activePharmOrders = $pdo->query(
        "SELECT * FROM pharmacy_orders WHERE status='dispatched' ORDER BY id DESC LIMIT 10"
    )->fetchAll();
} catch (Throwable $e) {}

/* -- Pharmacy orders: pending pickup (approved, ready) -------------- */
$pendingPharmOrders = [];
try {
    $pendingPharmOrders = $pdo->query(
        "SELECT * FROM pharmacy_orders WHERE status IN ('approved','processing') ORDER BY id DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {}

/* -- Parcel requests: active (not yet delivered/cancelled) ---------- */
$activeParcels = [];
try {
    $activeParcels = $pdo->query(
        "SELECT * FROM parcel_requests WHERE delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED') ORDER BY id DESC LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {}

/* -- Parcel: today's delivered count -------------------------------- */
$parcelDelivered = 0;
try {
    $q = $isSqlite
        ? "SELECT COUNT(*) FROM parcel_requests WHERE delivery_status='DELIVERED' AND DATE(updated_at)=DATE('now')"
        : "SELECT COUNT(*) FROM parcel_requests WHERE delivery_status='DELIVERED' AND DATE(updated_at)=CURDATE()";
    $parcelDelivered = (int)$pdo->query($q)->fetchColumn();
} catch (Throwable $e) {}

/* -- COD cash to collect today (pending parcels, COD only) ---------- */
$codCash = 0.0;
try {
    $q = $isSqlite
        ? "SELECT COALESCE(SUM(amount),0) FROM parcel_requests WHERE delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED') AND payment_method='CASH_ON_DELIVERY'"
        : "SELECT COALESCE(SUM(amount),0) FROM parcel_requests WHERE delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED') AND payment_method='CASH_ON_DELIVERY'";
    $codCash = (float)$pdo->query($q)->fetchColumn();
} catch (Throwable $e) {}

$totalDelivered = $pharmDelivered + $parcelDelivered;
$totalActive    = count($activePharmOrders) + count($activeParcels);
$totalPending   = count($pendingPharmOrders);

/* helpers */
function dh(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mapsLink(?string $coords, ?string $fallbackAddr = null): string {
    if (!empty($coords)) {
        $parts = explode(',', $coords);
        if (count($parts) >= 2) {
            $lat = trim($parts[0]); $lng = trim($parts[1]);
            return "https://www.google.com/maps/dir/?api=1&destination=" . urlencode("$lat,$lng");
        }
    }
    if (!empty($fallbackAddr)) {
        return "https://www.google.com/maps/search/?api=1&query=" . urlencode($fallbackAddr);
    }
    return '#';
}
function waCall(string $phone): string {
    $p = preg_replace('/\D/', '', $phone);
    if (strlen($p) === 10) $p = '91' . $p;
    return "tel:+$p";
}
function statusBadge(string $status): string {
    $map = [
        'pending'    => ['#F59E0B','#FEF3C7','Pending'],
        'approved'   => ['#3B82F6','#DBEAFE','Approved'],
        'processing' => ['#8B5CF6','#EDE9FE','Processing'],
        'dispatched' => ['#F97316','#FFF7ED','Dispatched'],
        'delivered'  => ['#16A34A','#DCFCE7','Delivered'],
        'REQUESTED'  => ['#64748B','#F1F5F9','Requested'],
        'ASSIGNED'   => ['#3B82F6','#DBEAFE','Assigned'],
        'PICKED_UP'  => ['#8B5CF6','#EDE9FE','Picked Up'],
        'IN_TRANSIT' => ['#F97316','#FFF7ED','In Transit'],
        'DELIVERED'  => ['#16A34A','#DCFCE7','Delivered'],
    ];
    $s = trim($status);
    [$color,$bg,$label] = $map[$s] ?? ['#64748B','#F1F5F9',$s];
    return "<span style=\"background:$bg;color:$color;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:800;\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Operations | KSR GURU JI</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; padding-bottom: 90px; }

        /* Driver Navbar */
        .drv-nav { background: #0F172A; }
        .drv-nav .navbar-brand { color: white; }
        .drv-nav .navbar-links a { color: #94A3B8; }
        .drv-nav .navbar-links a:hover, .drv-nav .navbar-links a.active { color: white; }
        .drv-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .container-main { max-width: 800px; margin: 24px auto; padding: 0 16px; }

        /* Mobile-First Adjustments for Driver */
        .dr-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .dr-title { font-size: 1.25rem; font-weight: 800; color: var(--clr-primary-dark); }

        /* Availability Toggles */
        .status-toggle { background: white; border-radius: var(--radius-lg); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .st-info { display: flex; align-items: center; gap: 12px; }
        .st-indicator { width: 12px; height: 12px; border-radius: 50%; background: #16A34A; box-shadow: 0 0 0 4px #DCFCE7; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); } 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); } }
        .st-text { font-weight: 800; font-size: 1.1rem; color: #16A34A; }

        /* Switch */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #CBD5E1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #16A34A; }
        input:checked + .slider:before { transform: translateX(22px); }

        /* Metrics */
        .metrics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
        .m-card { background: white; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); text-align: center; }
        .m-val { font-size: 1.5rem; font-weight: 900; color: var(--clr-text-primary); margin-bottom: 4px; }
        .m-label { font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; }

        /* Active Job Card */
        .job-card { background: white; border-radius: var(--radius-lg); border: 2px solid var(--clr-primary); box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1); overflow: hidden; margin-bottom: 20px; position:relative; }
        .jc-head { background: var(--clr-primary); color: white; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; }
        .jc-head.parcel-head { background: #7C3AED; }
        .jc-type { font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .jc-id { font-family: monospace; font-size: 1rem; font-weight: bold; }
        .jc-body { padding: 18px 20px; }

        .route-path { position: relative; padding-left: 24px; margin-bottom: 20px; }
        .route-path::before { content: ''; position: absolute; left: 6px; top: 8px; bottom: 8px; width: 2px; border-left: 2px dashed #94A3B8; }
        .rp-point { position: relative; margin-bottom: 18px; }
        .rp-point:last-child { margin-bottom: 0; }
        .rp-point::before { content: ''; position: absolute; left: -24px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: white; border: 3px solid var(--clr-primary); }
        .rp-point.done::before { background: var(--clr-primary); }
        .rp-point.dest::before { border-color: #F59E0B; background: white; }

        .rp-label { font-size: 0.8rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 2px; }
        .rp-val { font-size: 1rem; font-weight: 800; color: var(--clr-text-primary); }
        .rp-meta { font-size: 0.85rem; color: var(--clr-text-secondary); margin-top: 2px; }

        .jc-actions { display: flex; gap: 12px; padding: 14px 20px; background: #F8FAFC; border-top: 1px solid var(--clr-border); flex-wrap: wrap; }
        .btn-full { flex: 1; min-width: 100px; padding: 12px 14px; border-radius: 8px; font-weight: 800; font-size: 0.9rem; border: none; cursor: pointer; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-primary { background: var(--clr-primary); color: white; }
        .btn-green { background: #16A34A; color: white; }
        .btn-purple { background: #7C3AED; color: white; }
        .btn-orange { background: #EA580C; color: white; }
        .btn-outline { background: white; border: 2px solid var(--clr-border); color: var(--clr-text-primary); }
        .btn-call { background: #0EA5E9; color: white; }

        /* COD badge */
        .cod-badge { display:inline-flex; align-items:center; gap:5px; background:#FEF3C7; color:#92400E; padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:800; }
        .upi-badge { display:inline-flex; align-items:center; gap:5px; background:#D1FAE5; color:#065F46; padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:800; }

        /* General List Items */
        .list-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); padding: 14px 16px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .list-card-info { flex: 1; min-width: 0; }
        .list-card-id { font-weight: 800; color: var(--clr-text-primary); font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .list-card-meta { font-size: 0.82rem; color: var(--clr-text-secondary); margin-top: 2px; }

        /* Section header */
        .sec-title { font-weight: 800; font-size: 1.1rem; color: var(--clr-primary-dark); margin-bottom: 14px; margin-top: 28px; display:flex; align-items:center; gap:8px; }
        .sec-badge { background: var(--clr-primary); color: white; border-radius: 20px; padding: 2px 10px; font-size: 0.8rem; font-weight: 800; }
        .sec-badge.purple { background: #7C3AED; }

        /* Empty state */
        .empty-card { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); padding: 28px 16px; text-align: center; color: var(--clr-text-secondary); margin-bottom: 12px; }
        .empty-card i { font-size: 2.2rem; opacity: 0.4; display:block; margin-bottom: 8px; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); display: flex; align-items: flex-end; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 0; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 600px; border-radius: 24px 24px 0 0; box-shadow: 0 -10px 40px rgba(0,0,0,0.3); transform: translateY(100%); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1); padding: 24px; padding-bottom: max(24px, env(safe-area-inset-bottom)); }
        .modal-overlay.active .modal-card { transform: translateY(0); }

        .otp-inputs { display: flex; gap: 12px; justify-content: center; margin: 20px 0; }
        .otp-inputs input { width: 50px; height: 60px; font-size: 2rem; text-align: center; font-weight: 900; border: 2px solid var(--clr-border); border-radius: 8px; font-family: monospace; }
        .otp-inputs input:focus { border-color: var(--clr-primary); outline: none; }

        /* Auto-refresh badge */
        .refresh-note { font-size: 0.75rem; color: var(--clr-text-secondary); font-weight: 600; }

        @media (max-width: 480px) {
            html, body { overflow-x: hidden; }
            .container-main { padding: 0 10px; margin-top: 14px; }
            .jc-actions { flex-direction: column; gap: 8px; }
            .btn-full { width: 100%; min-width: unset; }
            .otp-inputs { gap: 8px; }
            .otp-inputs input { width: 44px; height: 52px; font-size: 1.5rem; }
            .modal-card { padding: 18px 16px; }
        }

        @media (max-width: 360px) {
            .dr-title { font-size: 1.1rem; }
            .m-val { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>

    <nav class="navbar drv-nav">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
            <div style="display:flex; align-items:center; gap:32px;">
                <a href="driver-dashboard.php" class="navbar-brand"><i class="fas fa-motorcycle"></i> DRIVER PORTAL</a>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <span class="refresh-note"><i class="fas fa-sync-alt"></i> Live</span>
                <a href="delivery-dashboard.php" class="profile-badge" style="padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:700;"><i class="fas fa-th-large"></i></a>
            </div>
        </div>
    </nav>

    <div class="container-main">

        <!-- Header: live date -->
        <div class="dr-header">
            <div class="dr-title">Today's Shift</div>
            <div style="font-weight:700; color:var(--clr-text-secondary);"><?= date('d M Y') ?></div>
        </div>

        <!-- Toggle Online/Offline (UI state only) -->
        <div class="status-toggle">
            <div class="st-info">
                <div class="st-indicator"></div>
                <div>
                    <div class="st-text">ONLINE</div>
                    <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:2px;">Receiving assignments</div>
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" checked>
                <span class="slider"></span>
            </label>
        </div>

        <!-- Live Metrics -->
        <div class="metrics-grid">
            <div class="m-card">
                <div class="m-val"><?= $totalDelivered ?></div>
                <div class="m-label">Delivered Today</div>
            </div>
            <div class="m-card">
                <div class="m-val"><?= $totalActive ?></div>
                <div class="m-label">Active Jobs</div>
            </div>
            <div class="m-card">
                <div class="m-val" style="color:#F59E0B;"><?= $totalPending ?></div>
                <div class="m-label">Pending Pickup</div>
            </div>
            <div class="m-card">
                <div class="m-val" style="color:#16A34A;">₹<?= number_format($codCash, 0) ?></div>
                <div class="m-label">COD to Collect</div>
            </div>
        </div>

        <!-- ═══ ACTIVE PHARMACY DELIVERIES ═══════════════════════════ -->
        <div class="sec-title">
            <i class="fas fa-pills"></i> Active Pharmacy Deliveries
            <span class="sec-badge"><?= count($activePharmOrders) ?></span>
        </div>

        <?php if (empty($activePharmOrders)): ?>
        <div class="empty-card">
            <i class="fas fa-box-open"></i>
            No pharmacy orders dispatched right now.
        </div>
        <?php else: foreach ($activePharmOrders as $ord): ?>
        <div class="job-card">
            <div class="jc-head">
                <div class="jc-type"><i class="fas fa-pills"></i> PHARMACY ORDER</div>
                <div class="jc-id"><?= dh($ord['order_code']) ?></div>
            </div>
            <div class="jc-body">

                <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                    <?= statusBadge((string)($ord['status'] ?? '')) ?>
                    <?php if (!empty($ord['needs_rx']) && $ord['needs_rx']): ?>
                        <span style="background:#FEE2E2;color:#991B1B;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:800;"><i class="fas fa-prescription"></i> Rx Required</span>
                    <?php endif; ?>
                    <?php $pm = strtolower((string)($ord['payment_method'] ?? 'cod'));
                    if (str_contains($pm, 'upi') || str_contains($pm, 'online')): ?>
                        <span class="upi-badge"><i class="fas fa-mobile-alt"></i> PREPAID UPI</span>
                    <?php else: ?>
                        <span class="cod-badge"><i class="fas fa-money-bill"></i> COD — ₹<?= number_format((float)($ord['total'] ?? 0), 0) ?></span>
                    <?php endif; ?>
                </div>

                <div class="route-path">
                    <div class="rp-point done">
                        <div class="rp-label">Pickup • <span style="color:#16A34A;">Pharmacy</span></div>
                        <div class="rp-val">KSR Pharmacy / Dispensary</div>
                        <div class="rp-meta">Order: <?= dh($ord['items_text'] ?? 'See order details') ?></div>
                    </div>
                    <div class="rp-point dest">
                        <div class="rp-label">Delivery Destination</div>
                        <div class="rp-val"><?= dh($ord['address_text'] ?? 'Address not provided') ?></div>
                        <?php if (!empty($ord['customer_note'])): ?>
                        <div class="rp-meta">Note: "<?= dh($ord['customer_note']) ?>"</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Patient info -->
                <div style="background:#F1F5F9; border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <div>
                        <div style="font-weight:800; font-size:0.9rem;"><?= dh($ord['customer_name'] ?? '—') ?></div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">📞 <?= dh($ord['customer_phone'] ?? '—') ?></div>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:2px;">
                            Placed: <?= date('d M, h:i A', strtotime((string)($ord['created_at'] ?? 'now'))) ?>
                        </div>
                    </div>
                    <a href="<?= waCall((string)($ord['customer_phone'] ?? '')) ?>" class="btn-full btn-call" style="width:auto; padding:10px 14px;">
                        <i class="fas fa-phone-alt"></i>
                    </a>
                </div>
            </div>
            <div class="jc-actions">
                <?php $navLink = mapsLink((string)($ord['latitude'] ?? '') . ',' . (string)($ord['longitude'] ?? ''), (string)($ord['address_text'] ?? '')); ?>
                <a href="<?= $navLink ?>" target="_blank" class="btn-full btn-outline"><i class="fas fa-location-arrow"></i> Navigate</a>
                <button class="btn-full btn-green" onclick="openOtpModal('<?= dh($ord['order_code']) ?>', 'pharm')">
                    <i class="fas fa-shield-alt"></i> Confirm Delivery
                </button>
            </div>
        </div>
        <?php endforeach; endif; ?>


        <!-- ═══ ACTIVE PARCEL DELIVERIES ═══════════════════════════ -->
        <div class="sec-title">
            <i class="fas fa-box"></i> Active Parcel Deliveries
            <span class="sec-badge purple"><?= count($activeParcels) ?></span>
        </div>

        <?php if (empty($activeParcels)): ?>
        <div class="empty-card">
            <i class="fas fa-truck"></i>
            No parcel jobs active right now.
        </div>
        <?php else: foreach ($activeParcels as $p): ?>
        <div class="job-card">
            <div class="jc-head parcel-head">
                <div class="jc-type"><i class="fas fa-box"></i> PARCEL JOB</div>
                <div class="jc-id"><?= dh($p['order_code']) ?></div>
            </div>
            <div class="jc-body">

                <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                    <?= statusBadge((string)($p['delivery_status'] ?? '')) ?>
                    <?php $pm = (string)($p['payment_method'] ?? 'CASH_ON_DELIVERY');
                    if (str_contains($pm, 'UPI') || str_contains($pm, 'ONLINE')): ?>
                        <span class="upi-badge"><i class="fas fa-mobile-alt"></i> PREPAID UPI</span>
                    <?php else: ?>
                        <span class="cod-badge"><i class="fas fa-money-bill"></i> COD — ₹<?= number_format((float)($p['amount'] ?? 50), 0) ?></span>
                    <?php endif; ?>
                    <span style="background:#F3F4F6;color:#374151;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:800;"><?= dh($p['parcel_type'] ?? 'Parcel') ?></span>
                </div>

                <div class="route-path">
                    <div class="rp-point done">
                        <div class="rp-label">Pickup From</div>
                        <div class="rp-val"><?= dh($p['pickup_name'] ?? '—') ?></div>
                        <div class="rp-meta"><?= dh($p['pickup_address'] ?? '') ?>
                            <?php if (!empty($p['pickup_landmark'])): ?> · <?= dh($p['pickup_landmark']) ?><?php endif; ?>
                        </div>
                        <?php $pcLink = mapsLink((string)($p['pickup_coords'] ?? ''), (string)($p['pickup_address'] ?? '')); ?>
                        <div class="rp-meta"><a href="<?= $pcLink ?>" target="_blank" style="color:var(--clr-primary); font-weight:700;"><i class="fas fa-map-marker-alt"></i> Maps</a></div>
                    </div>
                    <div class="rp-point dest">
                        <div class="rp-label">Deliver To</div>
                        <div class="rp-val"><?= dh($p['receiver_name'] ?? '—') ?></div>
                        <div class="rp-meta"><?= dh($p['delivery_address'] ?? '') ?>
                            <?php if (!empty($p['delivery_landmark'])): ?> · <?= dh($p['delivery_landmark']) ?><?php endif; ?>
                        </div>
                        <?php $dcLink = mapsLink((string)($p['delivery_coords'] ?? ''), (string)($p['delivery_address'] ?? '')); ?>
                        <div class="rp-meta"><a href="<?= $dcLink ?>" target="_blank" style="color:#7C3AED; font-weight:700;"><i class="fas fa-map-marker-alt"></i> Maps</a></div>
                    </div>
                </div>

                <!-- Receiver info -->
                <div style="background:#F5F3FF; border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <div>
                        <div style="font-weight:800; font-size:0.9rem;"><?= dh($p['receiver_name'] ?? '—') ?></div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">📞 <?= dh($p['receiver_mobile'] ?? '—') ?></div>
                        <?php if (!empty($p['description'])): ?>
                        <div style="font-size:0.8rem; color:var(--clr-text-secondary); margin-top:2px;">Note: <?= dh($p['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="<?= waCall((string)($p['receiver_mobile'] ?? '')) ?>" class="btn-full btn-purple" style="width:auto; padding:10px 14px;">
                        <i class="fas fa-phone-alt"></i>
                    </a>
                </div>
            </div>
            <div class="jc-actions">
                <a href="<?= $dcLink ?>" target="_blank" class="btn-full btn-outline"><i class="fas fa-location-arrow"></i> Navigate</a>
                <?php $codPharmFalse = !(str_contains(strtoupper((string)($p['payment_method'] ?? '')), 'UPI') || str_contains(strtoupper((string)($p['payment_method'] ?? '')), 'ONLINE')); ?>
                <?php if ($codPharmFalse): ?>
                    <button class="btn-full btn-orange" onclick="openOtpModal('', 'parcel', <?= (int)$p['id'] ?>, '<?= dh($p['order_code']) ?>')">
                        <i class="fas fa-money-bill"></i> Cash Received ✓
                    </button>
                <?php else: ?>
                    <button class="btn-full btn-green" onclick="openOtpModal('', 'parcel', <?= (int)$p['id'] ?>, '<?= dh($p['order_code']) ?>')">
                        <i class="fas fa-check-circle"></i> Mark Delivered
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>


        <!-- ═══ PENDING PHARMACY PICKUPS ════════════════════════════ -->
        <?php if (!empty($pendingPharmOrders)): ?>
        <div class="sec-title">
            <i class="fas fa-clock"></i> Pending Pharmacy Pickups
            <span class="sec-badge" style="background:#F59E0B;"><?= count($pendingPharmOrders) ?></span>
        </div>
        <?php foreach ($pendingPharmOrders as $ord): ?>
        <div class="list-card">
            <div class="list-card-info">
                <div class="list-card-id"><?= dh($ord['order_code']) ?></div>
                <div class="list-card-meta">
                    <?= dh($ord['customer_name'] ?? '—') ?> · <?= dh($ord['customer_phone'] ?? '') ?>
                </div>
                <div class="list-card-meta" style="margin-top:4px;">
                    <?= statusBadge((string)($ord['status'] ?? '')) ?>
                    &nbsp;₹<?= number_format((float)($ord['total'] ?? 0), 0) ?>
                </div>
            </div>
            <a href="<?= mapsLink(null, (string)($ord['address_text'] ?? '')) ?>" target="_blank" class="btn-full btn-primary" style="width:auto; padding:10px 14px; font-size:0.85rem;">
                <i class="fas fa-map-pin"></i> View
            </a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /.container-main -->


    <!-- OTP / Confirm Delivery Modal -->
    <div class="modal-overlay" id="otp-modal">
        <div class="modal-card">
            <div style="text-align:center; margin-bottom:16px;">
                <div style="width:60px; height:60px; background:#F0FDF4; color:#16A34A; font-size:2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <i class="fas fa-lock"></i>
                </div>
                <div style="font-size:1.3rem; font-weight:900; color:var(--clr-primary-dark);" id="otp-title">Delivery Verification</div>
                <div style="font-size:0.95rem; color:var(--clr-text-secondary); margin-top:8px;" id="otp-desc">Confirm delivery by entering the customer's 4-digit OTP.</div>
            </div>

            <div class="otp-inputs" id="otp-inputs-wrap">
                <input type="text" maxlength="1" oninput="otpNext(this)" inputmode="numeric">
                <input type="text" maxlength="1" oninput="otpNext(this)" inputmode="numeric">
                <input type="text" maxlength="1" oninput="otpNext(this)" inputmode="numeric">
                <input type="text" maxlength="1" oninput="otpNext(this)" inputmode="numeric">
            </div>

            <!-- Hidden form for POST submission -->
            <form id="confirm-form" method="post" action="driver-dashboard.php">
                <input type="hidden" name="action" id="form-action" value="">
                <input type="hidden" name="order_code" id="form-order-code" value="">
                <input type="hidden" name="parcel_id" id="form-parcel-id" value="">
            </form>

            <div style="display:flex; gap:16px; margin-top:8px;">
                <button class="btn-full btn-outline" style="flex:0.4;" onclick="closeModal('otp-modal')">Cancel</button>
                <button class="btn-full btn-green" onclick="submitConfirm()">Confirm Delivery ✓</button>
            </div>
        </div>
    </div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    function openOtpModal(orderCode, type, parcelId, parcelCode) {
        const title = document.getElementById('otp-title');
        const desc  = document.getElementById('otp-desc');

        if (type === 'pharm') {
            document.getElementById('form-action').value = 'deliver_pharm';
            document.getElementById('form-order-code').value = orderCode;
            document.getElementById('form-parcel-id').value = '';
            title.textContent = 'Pharmacy Delivery';
            desc.textContent  = 'Confirm delivery of order ' + orderCode + '. Get OTP from patient.';
        } else {
            document.getElementById('form-action').value = 'deliver_parcel';
            document.getElementById('form-order-code').value = parcelCode || '';
            document.getElementById('form-parcel-id').value = parcelId || '';
            title.textContent = 'Parcel Delivery';
            desc.textContent  = 'Confirm delivery of parcel ' + (parcelCode || ('#' + parcelId)) + '.';
        }

        // Clear OTP inputs
        document.querySelectorAll('#otp-inputs-wrap input').forEach(inp => inp.value = '');
        openModal('otp-modal');
        setTimeout(() => document.querySelector('#otp-inputs-wrap input').focus(), 300);
    }

    function otpNext(el) {
        el.value = el.value.replace(/\D/g, '');
        if (el.value.length === 1 && el.nextElementSibling) {
            el.nextElementSibling.focus();
        }
    }

    function submitConfirm() {
        // For now: submit the form (OTP verification is a UI/UX flow — real OTP matching can be added later)
        document.getElementById('confirm-form').submit();
    }

    // Auto-refresh every 60 seconds to keep dashboard live
    setTimeout(() => location.reload(), 60000);
</script>

</body>
</html>
