<?php
require_once 'helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pharmacistName = $_SESSION['user_name'] ?? 'Bheemappa Kali-Appa Hanamannavar';

$pdo = getAssistDB();

// Mock data
$newRxCount = 24;
$todaysOrdersCount = 48;
$readyDeliveryCount = 17;
$lowStockCount = 12;

// Fetch live prescriptions
$livePrescriptions = $pdo->query("
    SELECT p.id, p.verification_status, u1.name as patient_name, u2.name as doctor_name
    FROM prescriptions p
    LEFT JOIN users u1 ON p.patient_id = u1.id
    LEFT JOIN users u2 ON p.doctor_id = u2.id
    ORDER BY p.id DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch live orders
$liveOrders = $pdo->query("
    SELECT id, name as patient_name, total as amount, payment_method, status
    FROM orders
    ORDER BY created_at DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch live database records for integrated modules. These tables only
// exist on deployments that ran the pharmacy schema — degrade gracefully
// instead of throwing "no such table: pharmacy_stock_movement".
try {
    $stockMovements = telehealthTableExists($pdo, 'pharmacy_stock_movement')
        ? $pdo->query("SELECT * FROM pharmacy_stock_movement ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC)
        : [];
} catch (Throwable $e) {
    $stockMovements = [];
}
try {
    $deliveries = telehealthTableExists($pdo, 'pharmacy_deliveries')
        ? $pdo->query("SELECT * FROM pharmacy_deliveries ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC)
        : [];
} catch (Throwable $e) {
    $deliveries = [];
}

// Map live products catalog into the Pharmacy Inventory format
$inventoryQuery = $pdo->query("SELECT name, stock, price FROM products LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$inventory = [];
foreach ($inventoryQuery as $idx => $prod) {
    $stock = (int)$prod['stock'];
    $status = 'Available';
    $statusClass = 'inv-available';
    if ($stock <= 0) {
        $status = 'Out of Stock';
        $statusClass = 'inv-outofstock';
    } elseif ($stock < 20) {
        $status = 'Low Stock';
        $statusClass = 'inv-lowstock';
    }

    $inventory[] = [
        'name' => htmlspecialchars($prod['name']),
        'category' => 'Pharmacy',
        'batch' => 'B-' . (1000 + $idx),
        'stock' => $stock,
        'expiry' => date('d M Y', strtotime('+' . rand(3, 24) . ' months')),
        'price' => $prod['price'],
        'status' => $status,
        'status_class' => $statusClass
    ];
}

// Live Billing & Revenue Calculation
$billingSummary = $pdo->query("SELECT 
    SUM(CASE WHEN payment_status = 'COMPLETED' OR payment_status = 'PAID' OR payment_method = 'ONLINE' THEN total ELSE 0 END) as completed_revenue,
    SUM(CASE WHEN payment_status != 'COMPLETED' AND payment_status != 'PAID' AND payment_method != 'ONLINE' THEN total ELSE 0 END) as pending_revenue
    FROM orders WHERE DATE(created_at) = '" . date('Y-m-d') . "'")->fetch(PDO::FETCH_ASSOC);

$completedRevenue = $billingSummary['completed_revenue'] ?? 0;
$pendingRevenue = $billingSummary['pending_revenue'] ?? 0;
$todaysTotalRevenue = $completedRevenue + $pendingRevenue;

$recentTransactions = $pdo->query("SELECT id as order_id, transaction_id, payment_method, total as amount, payment_status FROM orders ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Pharmacy Color Palette */
            --clr-primary: #00796B;
            /* Deep Teal */
            --clr-primary-dark: #004D40;
            /* Dark Teal */
            --clr-secondary: #1976D2;
            /* Medical Blue */
            --clr-success: #2E7D32;
            /* Green */
            --clr-warning: #F9A825;
            /* Amber */
            --clr-danger: #D32F2F;
            /* Red */
            --clr-dispensing: #8E24AA;
            /* Purple */
            --clr-bg: #F5F7FA;
            /* Very Light Grey */
            --clr-card: #FFFFFF;
            /* White */
            --clr-text-main: #172033;
            /* Dark Navy */
            --clr-text-muted: #667085;
            /* Secondary Text / Grey */
            --clr-border: #E4E7EC;
            /* Light Grey */

            --sidebar-width: 260px;
            --header-height: 70px;

            /* Glassmorphism & UI Tokens */
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.02);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.08);

            --font-family: 'Inter', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: var(--clr-bg);
            color: var(--clr-text-main);
            overflow-x: hidden;
        }

        /* SCROLLBAR */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 77, 64, 0.2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 77, 64, 0.4);
        }

        /* BUTTONS */
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
            outline: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: var(--clr-primary);
            color: #FFF;
        }

        .btn-primary:hover {
            background: var(--clr-primary-dark);
        }

        .btn-secondary {
            background: var(--clr-secondary);
            color: #FFF;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--clr-border);
            color: var(--clr-text-main);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--clr-bg);
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        .btn-success {
            background: var(--clr-success);
            color: #FFF;
            border: none;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--clr-primary-dark);
            color: #FFF;
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .sb-brand {
            padding: 24px;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: #FFF;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sb-brand i {
            color: #FFF;
            font-size: 1.5rem;
            background: var(--clr-primary);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 121, 107, 0.5);
        }

        .sb-menu {
            padding: 16px 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sb-item {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .sb-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #FFF;
        }

        .sb-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: #FFF;
            border-left: 3px solid var(--clr-warning);
            font-weight: 700;
        }

        .sb-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sb-item.active i {
            color: var(--clr-warning);
        }

        .sb-sub-menu {
            padding-left: 50px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 2px;
            margin-bottom: 8px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .sb-item.expanded+.sb-sub-menu {
            max-height: 200px;
        }

        .sb-sub-item {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 6px 0;
            transition: 0.2s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-right: 24px;
            cursor: pointer;
        }

        .sb-sub-item:before {
            content: '';
            position: absolute;
            left: -16px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: 0.2s;
        }

        .sb-sub-item:hover {
            color: #FFF;
        }

        .sb-sub-item.active {
            color: var(--clr-warning);
            font-weight: 700;
        }

        .sb-sub-item.active:before {
            background: var(--clr-warning);
            width: 6px;
            height: 6px;
            left: -17px;
        }

        /* HEADER */
        .header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--clr-border);
            z-index: 900;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: var(--shadow-sm);
            transition: 0.3s;
        }

        .h-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--clr-text-main);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .h-actions {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .h-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 0.8rem;
            background: rgba(46, 125, 50, 0.1);
            color: var(--clr-success);
            padding: 6px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }

        .h-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--clr-success);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
            animation: pulseGreen 2s infinite;
        }

        @keyframes pulseGreen {
            0% {
                box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.4);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(46, 125, 50, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(46, 125, 50, 0);
            }
        }

        .h-icon {
            position: relative;
            color: var(--clr-text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .h-icon:hover {
            color: var(--clr-text-main);
            transform: scale(1.1);
        }

        .h-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--clr-danger);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 5px;
            border-radius: 10px;
            border: 2px solid var(--clr-card);
        }

        .h-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 24px;
            border-left: 1px solid var(--clr-border);
            cursor: pointer;
        }

        .hp-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--clr-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            border: 2px solid #FFF;
            box-shadow: var(--shadow-sm);
        }

        .hp-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .hp-name {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--clr-text-main);
        }

        .hp-role {
            font-size: 0.75rem;
            color: var(--clr-primary);
            font-weight: 600;
        }

        /* MAIN CONTENT AREA */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 32px;
            min-height: calc(100vh - var(--header-height));
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            animation: fadeInDown 0.4s ease-out;
        }

        .pg-title {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--clr-text-main);
            letter-spacing: -0.5px;
        }

        .pg-sub {
            color: var(--clr-text-muted);
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 4px;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-content {
            display: none;
            animation: fadeInUp 0.4s ease-out;
            flex: 1;
        }

        .tab-content.active {
            display: block;
        }

        /* CARDS / PANELS */
        .panel {
            background: var(--clr-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--clr-border);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--clr-border);
        }

        .ph-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--clr-text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ph-title i {
            color: var(--clr-primary);
            font-size: 1.2rem;
        }

        /* DASHBOARD - STAT GRID */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--clr-card);
            border-radius: var(--radius-xl);
            padding: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .sc-new-rx::before {
            background: var(--clr-secondary);
        }

        .sc-orders::before {
            background: var(--clr-primary);
        }

        .sc-pending::before {
            background: #8E24AA;
        }

        .sc-packing::before {
            background: #00897B;
        }

        .sc-ready::before {
            background: var(--clr-success);
        }

        .sc-low::before {
            background: var(--clr-warning);
        }

        .sc-out::before {
            background: var(--clr-danger);
        }

        .sc-sales::before {
            background: #1976D2;
        }

        .sc-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 10;
        }

        .sc-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 10;
        }

        .sc-new-rx .sc-icon {
            background: rgba(25, 118, 210, 0.1);
            color: var(--clr-secondary);
        }

        .sc-orders .sc-icon {
            background: rgba(0, 121, 107, 0.1);
            color: var(--clr-primary);
        }

        .sc-pending .sc-icon {
            background: rgba(142, 36, 170, 0.1);
            color: #8E24AA;
        }

        .sc-packing .sc-icon {
            background: rgba(0, 137, 123, 0.1);
            color: #00897B;
        }

        .sc-ready .sc-icon {
            background: rgba(46, 125, 50, 0.1);
            color: var(--clr-success);
        }

        .sc-low .sc-icon {
            background: rgba(249, 168, 37, 0.1);
            color: var(--clr-warning);
        }

        .sc-out .sc-icon {
            background: rgba(211, 47, 47, 0.1);
            color: var(--clr-danger);
        }

        .sc-sales .sc-icon {
            background: rgba(25, 118, 210, 0.1);
            color: #1976D2;
        }

        .sc-val {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--clr-text-main);
            line-height: 1;
        }

        .sc-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--clr-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* TABLES */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--clr-text-muted);
            border-bottom: 2px solid var(--clr-border);
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--clr-border);
            font-size: 0.95rem;
            font-weight: 500;
            vertical-align: middle;
            transition: 0.2s;
        }

        .data-table tbody tr {
            transition: 0.2s;
        }

        .data-table tbody tr:hover {
            background: rgba(0, 121, 107, 0.02);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .td-id {
            font-weight: 800;
            color: var(--clr-text-main);
            font-family: monospace;
            font-size: 1rem;
        }

        .td-primary {
            font-weight: 700;
            color: var(--clr-text-main);
        }

        .td-secondary {
            font-size: 0.8rem;
            color: var(--clr-text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* STATUS BADGES */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
            border: 1px solid transparent;
        }

        /* Prescriptions Status Colors */
        .status-new {
            background: rgba(25, 118, 210, 0.1);
            color: var(--clr-secondary);
            border-color: rgba(25, 118, 210, 0.2);
        }

        .status-review {
            background: rgba(249, 168, 37, 0.1);
            color: #D84315;
            border-color: rgba(249, 168, 37, 0.2);
        }

        .status-verified {
            background: rgba(46, 125, 50, 0.1);
            color: var(--clr-success);
            border-color: rgba(46, 125, 50, 0.2);
        }

        .status-rejected {
            background: rgba(211, 47, 47, 0.1);
            color: var(--clr-danger);
            border-color: rgba(211, 47, 47, 0.2);
        }

        .status-dispensing {
            background: rgba(142, 36, 170, 0.1);
            color: var(--clr-dispensing);
            border-color: rgba(142, 36, 170, 0.2);
        }

        .status-completed {
            background: var(--clr-primary-dark);
            color: #FFF;
        }

        /* Inventory Status Colors */
        .inv-available {
            color: var(--clr-success);
            font-weight: 800;
        }

        .inv-available::before {
            content: '🟢';
            margin-right: 6px;
        }

        .inv-lowstock {
            color: var(--clr-warning);
            font-weight: 800;
        }

        .inv-lowstock::before {
            content: '🟡';
            margin-right: 6px;
        }

        .inv-outofstock {
            color: var(--clr-danger);
            font-weight: 800;
        }

        .inv-outofstock::before {
            content: '🔴';
            margin-right: 6px;
        }

        .inv-expiring {
            color: #E65100;
            font-weight: 800;
        }

        .inv-expiring::before {
            content: '🟠';
            margin-right: 6px;
        }

        /* TABS INSIDE PAGES */
        .nav-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--clr-border);
            padding-bottom: -2px;
        }

        .nav-tab {
            padding: 10px 20px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--clr-text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: 0.2s;
        }

        .nav-tab:hover {
            color: var(--clr-text-main);
        }

        .nav-tab.active {
            color: var(--clr-primary);
            border-bottom-color: var(--clr-primary);
        }

        /* TWO COLUMN LAYOUT (Prescription Details) */
        .split-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
        }

        .info-card {
            background: rgba(245, 247, 250, 0.5);
            padding: 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border);
            margin-bottom: 16px;
        }

        .ic-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--clr-text-muted);
            margin-bottom: 4px;
        }

        .ic-val {
            font-size: 1rem;
            font-weight: 700;
            color: var(--clr-text-main);
            margin-bottom: 16px;
        }

        .ic-val:last-child {
            margin-bottom: 0;
        }

        .timeline {
            position: relative;
            padding-left: 24px;
            margin-top: 16px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--clr-border);
        }

        .tl-item {
            position: relative;
            margin-bottom: 20px;
        }

        .tl-item:last-child {
            margin-bottom: 0;
        }

        .tl-dot {
            position: absolute;
            left: -29px;
            top: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--clr-card);
            border: 2px solid var(--clr-border);
            z-index: 2;
        }

        .tl-item.completed .tl-dot {
            border-color: var(--clr-success);
            background: var(--clr-success);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .tl-item.active .tl-dot {
            border-color: var(--clr-dispensing);
            background: var(--clr-dispensing);
            box-shadow: 0 0 0 3px rgba(142, 36, 170, 0.2);
            animation: pulseDispense 2s infinite;
        }

        @keyframes pulseDispense {
            0% {
                box-shadow: 0 0 0 0 rgba(142, 36, 170, 0.4);
            }

            70% {
                box-shadow: 0 0 0 6px rgba(142, 36, 170, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(142, 36, 170, 0);
            }
        }

        .tl-content {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--clr-text-muted);
        }

        .tl-item.completed .tl-content {
            color: var(--clr-text-main);
        }

        .tl-item.active .tl-content {
            color: var(--clr-dispensing);
        }

        .action-bar {
            padding: 20px;
            background: rgba(0, 121, 107, 0.05);
            border-top: 1px solid var(--clr-border);
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin: 0 -24px -24px -24px;
        }

        /* REVENUE CARDS */
        .rev-card {
            background: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
            color: white;
            padding: 24px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
        }

        .rev-label {
            font-size: 0.9rem;
            font-weight: 700;
            opacity: 0.8;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .rev-val {
            font-size: 2.5rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 12px;
        }

        /* Helper Classes */
        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .w-100 {
            width: 100%;
        }

        .mt-4 {
            margin-top: 24px;
        }

        .mb-4 {
            margin-bottom: 24px;
        }
        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .split-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 260px;
                --header-height: 60px;
            }

            .sidebar {
                left: -260px;
                width: 260px !important;
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.3);
            }

            .sidebar.mobile-open {
                left: 0;
            }

            .header {
                left: 0 !important;
                width: 100% !important;
                padding: 0 16px;
            }

            .main-wrapper {
                margin-left: 0 !important;
                padding: 16px;
                margin-top: var(--header-height);
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .panel {
                padding: 16px;
                border-radius: var(--radius-lg);
            }

            .panel-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .table-responsive,
            .panel {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .data-table {
                min-width: 600px;
            }

            .nav-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
            }

            .nav-tab {
                white-space: nowrap;
                padding: 8px 14px;
                font-size: 0.85rem;
            }

            .hp-info {
                display: none;
            }

            .h-status {
                display: none;
            }

            .h-actions {
                gap: 14px;
            }

            .action-bar {
                flex-direction: column;
                margin: 0 -16px -16px -16px;
                padding: 16px;
            }
        }

        @media (max-width: 480px) {
            .stat-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .sc-val {
                font-size: 1.7rem;
            }

            .sc-label {
                font-size: 0.75rem;
            }

            .pg-title {
                font-size: 1.35rem;
            }

            .pg-sub {
                font-size: 0.85rem;
            }

            .ph-title {
                font-size: 0.95rem;
            }

            .h-title {
                font-size: 1.05rem;
            }

            .btn {
                padding: 8px 12px;
                font-size: 0.82rem;
            }

            .info-card {
                padding: 14px;
            }

            .rev-val {
                font-size: 1.8rem;
            }

            .main-wrapper {
                padding: 12px;
            }
        }

        @media (max-width: 360px) {
            .pg-title {
                font-size: 1.2rem;
            }

            .sc-val {
                font-size: 1.5rem;
            }

            .h-actions {
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sb-brand">
            <i class="fas fa-plus-square"></i>
            <div>
                <div>KSR GURU JI</div>
                <div style="font-size: 0.65rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">Pharmacy Portal</div>
            </div>
        </div>

        <div class="sb-menu">
            <div class="sb-item active" onclick="switchSection('dashboard', this)"><i class="fas fa-home"></i> <span>Dashboard</span></div>

            <div class="sb-item" onclick="toggleSubMenu(this)"><i class="fas fa-file-prescription"></i> <span>Prescriptions</span></div>
            <div class="sb-sub-menu">
                <div class="sb-sub-item" onclick="switchSection('rx-queue', this, 'queue')">Queue <span style="background:var(--clr-secondary); color:#FFF; padding:2px 6px; border-radius:10px; font-size:0.7rem;">24</span></div>
                <div class="sb-sub-item" onclick="switchSection('rx-queue', this, 'details')">Details View</div>
            </div>

            <div class="sb-item" onclick="switchSection('orders', this)"><i class="fas fa-box-open"></i> <span>Orders</span></div>

            <div class="sb-item" onclick="switchSection('billing', this)"><i class="fas fa-file-invoice-dollar"></i> <span>Billing & Payments</span></div>

            <div class="sb-item" onclick="toggleSubMenu(this)"><i class="fas fa-boxes"></i> <span>Inventory</span></div>
            <div class="sb-sub-menu">
                <div class="sb-sub-item" onclick="switchSection('inventory-manage', this)">Stock Management</div>
                <div class="sb-sub-item" onclick="switchSection('inventory-alerts', this)" style="color:var(--clr-warning);">Low Stock / Expiry</div>
                <div class="sb-sub-item" onclick="switchSection('inventory-movement', this)">Stock Movement</div>
            </div>

            <div class="sb-item" onclick="switchSection('delivery', this)"><i class="fas fa-truck"></i> <span>Delivery</span></div>
            <div class="sb-item" onclick="switchSection('returns', this)"><i class="fas fa-undo"></i> <span>Returns & Refunds</span></div>

            <div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.05); padding-top:16px;"></div>
            <div class="sb-item" onclick="switchSection('patients', this)"><i class="fas fa-users"></i> <span>Patients</span></div>
            <div class="sb-item" onclick="switchSection('doctors', this)"><i class="fas fa-user-md"></i> <span>Doctors</span></div>
            <div class="sb-item" onclick="window.location.href='medicine/tablet.php'"><i class="fas fa-capsule"></i> <span>Medicines</span></div>
            <div class="sb-item" onclick="window.location.href='notifications.php'"><i class="fas fa-bell"></i> <span>Notifications</span></div>
            <div class="sb-item" onclick="switchSection('reports', this)"><i class="fas fa-chart-bar"></i> <span>Reports</span></div>
            <div class="sb-item" onclick="switchSection('settings', this)"><i class="fas fa-cog"></i> <span>Settings</span></div>
        </div>
    </div>

    <!-- HEADER -->
    <div class="header">
        <div class="h-title">
            <i class="fas fa-bars" style="cursor: pointer; color: var(--clr-text-muted);" onclick="toggleSidebar()"></i>
            Pharmacy
        </div>
        <div class="h-actions">
            <div class="h-status">
                <div class="h-status-dot"></div> OPEN
            </div>
            <div class="h-icon"><i class="fas fa-search"></i></div>
            <div class="h-icon">
                <i class="fas fa-envelope"></i>
                <div class="h-badge">5</div>
            </div>
            <div class="h-icon">
                <i class="fas fa-bell"></i>
                <div class="h-badge">2</div>
            </div>
            <div class="h-profile">
                <div class="hp-info">
                    <div class="hp-name"><?php echo htmlspecialchars($pharmacistName); ?></div>
                    <div class="hp-role">Pharmacist</div>
                </div>
                <div class="hp-avatar">PH</div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper" id="main-content">

        <!-- SECTION 1: DASHBOARD -->
        <div id="sec-dashboard" class="tab-content active">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Dashboard</h1>
                    <div class="pg-sub">Overview of pharmacy operations and fulfillment</div>
                </div>
                <div>
                    <button class="btn btn-primary"><i class="fas fa-plus"></i> New Manual Order</button>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="stat-grid">
                <div class="stat-card sc-new-rx">
                    <div class="sc-info">
                        <div class="sc-label">Prescription Orders</div>
                        <div class="sc-val"><?php echo $newRxCount; ?></div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-file-prescription"></i></div>
                </div>
                <div class="stat-card sc-orders">
                    <div class="sc-info">
                        <div class="sc-label">Medicine Orders</div>
                        <div class="sc-val"><?php echo $todaysOrdersCount; ?></div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-card sc-pending">
                    <div class="sc-info">
                        <div class="sc-label">Pending Verification</div>
                        <div class="sc-val">12</div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="stat-card sc-packing">
                    <div class="sc-info">
                        <div class="sc-label">Packing</div>
                        <div class="sc-val">8</div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-box"></i></div>
                </div>
                <div class="stat-card sc-ready">
                    <div class="sc-info">
                        <div class="sc-label">Ready for Delivery</div>
                        <div class="sc-val"><?php echo $readyDeliveryCount; ?></div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-truck-loading"></i></div>
                </div>
                <div class="stat-card sc-low">
                    <div class="sc-info">
                        <div class="sc-label">Low Stock</div>
                        <div class="sc-val"><?php echo $lowStockCount; ?></div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-card sc-out">
                    <div class="sc-info">
                        <div class="sc-label">Out of Stock</div>
                        <div class="sc-val">3</div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-times-circle"></i></div>
                </div>
                <div class="stat-card sc-sales">
                    <div class="sc-info">
                        <div class="sc-label">Today's Sales</div>
                        <div class="sc-val">₹<?php echo number_format($todaysTotalRevenue, 0); ?></div>
                    </div>
                    <div class="sc-icon"><i class="fas fa-coins"></i></div>
                </div>
            </div>

            <div class="split-layout" style="grid-template-columns: 2fr 1fr;">
                <!-- Queue Snapshot -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fas fa-clipboard-list"></i> Urgent Prescriptions</div>
                        <button class="btn btn-outline" onclick="document.querySelector('.sb-item:nth-child(2)').click()">View Queue</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>RX ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($livePrescriptions, 0, 4) as $rx):
                                $statusLabel = $rx['verification_status'];
                                $badgeClass = 'status-new';
                                if ($statusLabel == 'APPROVED') $badgeClass = 'status-verified';
                                if ($statusLabel == 'MANUAL_REVIEW') $badgeClass = 'status-review';
                                if ($statusLabel == 'REJECTED') $badgeClass = 'status-rejected';
                            ?>
                                <tr>
                                    <td class="td-id">RX-<?php echo $rx['id']; ?></td>
                                    <td class="td-primary"><?php echo htmlspecialchars($rx['patient_name'] ?? 'Unknown'); ?></td>
                                    <td class="td-secondary"><i class="fas fa-user-md" style="color:var(--clr-secondary);"></i> <?php echo htmlspecialchars($rx['doctor_name'] ?? 'Telehealth AI'); ?></td>
                                    <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Expiry Alerts -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fas fa-calendar-times" style="color:var(--clr-warning);"></i> Expiring Soon</div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach ($inventory as $item): if (strpos($item['status'], 'Expir') !== false): ?>
                                <div style="background: rgba(249, 168, 37, 0.05); border-left: 4px solid var(--clr-warning); padding: 12px; border-radius: 4px;">
                                    <div style="font-weight:800; font-size:0.95rem; color:var(--clr-text-main);"><?php echo $item['name']; ?></div>
                                    <div style="font-size:0.8rem; color:var(--clr-text-muted); margin-top:4px;">Batch: <?php echo $item['batch']; ?> | Expires: <span style="font-weight:700; color:var(--clr-danger);"><?php echo $item['expiry']; ?></span></div>
                                </div>
                        <?php endif;
                        endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PRESCRIPTION QUEUE -->
        <div id="sec-rx-queue" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Prescription Queue</h1>
                    <div class="pg-sub">Manage and verify all incoming prescriptions</div>
                </div>
            </div>

            <div class="panel">
                <div class="nav-tabs">
                    <div class="nav-tab active">New (<?php echo $newRxCount; ?>)</div>
                    <div class="nav-tab">Under Review</div>
                    <div class="nav-tab">Verified</div>
                    <div class="nav-tab">Rejected</div>
                    <div class="nav-tab">Dispensing</div>
                    <div class="nav-tab">Ready</div>
                    <div class="nav-tab">Completed</div>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <div style="position:relative; width:300px;">
                        <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--clr-text-muted);"></i>
                        <input type="text" placeholder="Search RX, Patient, Doctor..." style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--clr-border); border-radius:var(--radius-md); font-family:inherit;">
                    </div>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prescription</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Medicines</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livePrescriptions as $rx):
                            $statusLabel = $rx['verification_status'];
                            $badgeClass = 'status-new';
                            if ($statusLabel == 'APPROVED') $badgeClass = 'status-verified';
                            if ($statusLabel == 'MANUAL_REVIEW') $badgeClass = 'status-review';
                            if ($statusLabel == 'REJECTED') $badgeClass = 'status-rejected';
                        ?>
                            <tr>
                                <td class="td-id">RX-<?php echo $rx['id']; ?></td>
                                <td>
                                    <div class="td-primary"><?php echo htmlspecialchars($rx['patient_name'] ?? 'Unknown Patient'); ?></div>
                                    <div class="td-secondary">Auto-Processed</div>
                                </td>
                                <td>
                                    <div class="td-primary"><i class="fas fa-user-md text-muted"></i> <?php echo htmlspecialchars($rx['doctor_name'] ?? 'KSR Telehealth System'); ?></div>
                                </td>
                                <td style="font-weight:800;"><i class="fas fa-pills text-primary"></i> Review Action</td>
                                <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span></td>
                                <td class="text-right">
                                    <?php if ($statusLabel == 'PENDING'): ?>
                                        <button class="btn btn-primary" onclick="switchSection('rx-details')">Review <i class="fas fa-arrow-right"></i></button>
                                    <?php elseif ($statusLabel == 'APPROVED'): ?>
                                        <button class="btn btn-outline" style="color:var(--clr-dispensing); border-color:var(--clr-dispensing);">Dispense</button>
                                    <?php else: ?>
                                        <button class="btn btn-outline">View</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: PRESCRIPTION DETAILS -->
        <div id="sec-rx-details" class="tab-content">
            <div class="page-header" style="margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <button class="btn btn-outline" onclick="switchSection('rx-queue')"><i class="fas fa-arrow-left"></i> Back</button>
                    <div>
                        <h1 class="pg-title">Prescription <span style="color:var(--clr-primary);">RX-1001</span></h1>
                    </div>
                </div>
            </div>

            <div class="split-layout">
                <!-- Left: Patient Info -->
                <div>
                    <div class="panel" style="margin-bottom:24px; padding:0; overflow:hidden;">
                        <div style="padding:20px; background:var(--clr-primary); color:white;">
                            <div style="font-size:1.2rem; font-weight:800;">Patient A (Rahul)</div>
                            <div style="opacity:0.8; font-size:0.9rem; margin-top:4px;">ID: PT-8239 | Male, 32 Yrs</div>
                        </div>
                        <div style="padding:20px;">
                            <div class="ic-label">Doctor</div>
                            <div class="ic-val"><i class="fas fa-user-md"></i> Dr. Sharma (Gen. Physician)</div>
                            <div class="ic-label">Date</div>
                            <div class="ic-val">05 Sep 2026, 10:48 AM</div>
                            <div class="ic-label">Allergies</div>
                            <div class="ic-val" style="color:var(--clr-danger); font-weight:800;">Penicillin</div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="ph-title" style="margin-bottom:12px;">Prescription Status</div>
                        <div class="timeline">
                            <div class="tl-item completed">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Prescription Received</div>
                            </div>
                            <div class="tl-item completed">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Doctor Verified</div>
                            </div>
                            <div class="tl-item active">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Pharmacy Review</div>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Dispensing</div>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Ready for Delivery</div>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot"></div>
                                <div class="tl-content">Delivered</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Medicines Table -->
                <div class="panel" style="display:flex; flex-direction:column;">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fas fa-pills"></i> Prescribed Medicines (4)</div>
                    </div>

                    <div style="flex:1;">
                        <table class="data-table" style="margin-bottom:24px;">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Strength</th>
                                    <th>Dosage</th>
                                    <th>Duration</th>
                                    <th>Qty</th>
                                    <th>Avail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="td-primary">Pregabalin</td>
                                    <td>75 mg</td>
                                    <td>1-0-1</td>
                                    <td>14 Days</td>
                                    <td>28</td>
                                    <td><i class="fas fa-check-circle" style="color:var(--clr-success);"></i></td>
                                </tr>
                                <tr>
                                    <td class="td-primary">Baclofen</td>
                                    <td>10 mg</td>
                                    <td>0-0-1</td>
                                    <td>10 Days</td>
                                    <td>10</td>
                                    <td><i class="fas fa-check-circle" style="color:var(--clr-success);"></i></td>
                                </tr>
                                <tr>
                                    <td class="td-primary">Paracetamol</td>
                                    <td>500 mg</td>
                                    <td>SOS</td>
                                    <td>5 Days</td>
                                    <td>5</td>
                                    <td><i class="fas fa-check-circle" style="color:var(--clr-success);"></i></td>
                                </tr>
                                <tr style="background:rgba(211,47,47,0.05);">
                                    <td class="td-primary">Amoxicillin</td>
                                    <td>250 mg</td>
                                    <td>1-1-1</td>
                                    <td>5 Days</td>
                                    <td>15</td>
                                    <td><i class="fas fa-times-circle" style="color:var(--clr-danger);"></i> OOS</td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="background:#FFF9C4; padding:12px; border-radius:4px; font-size:0.85rem; font-weight:600; color:#F57F17; margin-bottom:20px; border:1px solid #FFF59D;">
                            <i class="fas fa-exclamation-triangle"></i> Patient allergic to Penicillin. Amoxicillin is prescribed. Doctor clarification required!
                        </div>
                    </div>

                    <div class="action-bar">
                        <button class="btn btn-outline" style="color:var(--clr-danger); border-color:var(--clr-danger);">Reject</button>
                        <button class="btn btn-outline" style="color:var(--clr-warning); border-color:var(--clr-warning);">Request Clarification</button>
                        <button class="btn btn-success"><i class="fas fa-check-double"></i> Verify & Allocate Stock</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: ORDERS -->
        <div id="sec-orders" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Pharmacy Orders</h1>
                    <div class="pg-sub">Manage processing, packing, and fulfillment</div>
                </div>
            </div>

            <div class="panel">
                <div class="nav-tabs">
                    <div class="nav-tab active">All</div>
                    <div class="nav-tab">New</div>
                    <div class="nav-tab">Confirmed</div>
                    <div class="nav-tab">Preparing</div>
                    <div class="nav-tab">Ready</div>
                    <div class="nav-tab">Out for Delivery</div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
                    <?php foreach ($liveOrders as $order): ?>
                        <div style="border: 2px solid var(--clr-border); border-radius: var(--radius-lg); padding: 20px; transition:0.3s;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--clr-border); padding-bottom:12px;">
                                <div style="font-weight:900; font-size:1.1rem; color:var(--clr-primary-dark);">Order #<?php echo $order['id']; ?></div>
                                <div style="font-size:0.8rem; font-weight:800; background:rgba(0,121,107,0.1); color:var(--clr-primary); padding:4px 8px; border-radius:4px; text-transform:uppercase;"><?php echo $order['status']; ?></div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; font-size:0.95rem;">
                                <div>
                                    <div class="ic-label">Patient</div>
                                    <div class="td-primary"><?php echo htmlspecialchars($order['patient_name']); ?></div>
                                </div>
                                <div>
                                    <div class="ic-label">Amount</div>
                                    <div class="td-primary">₹<?php echo $order['amount']; ?></div>
                                </div>
                                <div>
                                    <div class="ic-label">Payment</div>
                                    <div style="color:var(--clr-success); font-weight:800;"><i class="fas fa-check"></i> <?php echo htmlspecialchars($order['payment_method']); ?></div>
                                </div>
                            </div>
                            <div style="display:flex; gap:12px;">
                                <button class="btn btn-outline" style="flex:1; justify-content:center;">View Order</button>
                                <button class="btn btn-primary" style="flex:1; justify-content:center;"><i class="fas fa-box"></i> Pack</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SECTION 5: BILLING & PAYMENTS -->
        <div id="sec-billing" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Billing & Payments</h1>
                    <div class="pg-sub">Revenue tracking and transaction history (LIVE DATA)</div>
                </div>
            </div>

            <div class="stat-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="rev-card">
                    <div class="rev-label">Today's Revenue</div>
                    <div class="rev-val">₹<?php echo number_format($todaysTotalRevenue, 2); ?></div>
                    <div style="font-size:0.9rem; opacity:0.9;"><i class="fas fa-chart-line"></i> Dynamic Database Tally</div>
                </div>
                <div class="panel" style="background:var(--clr-card); border-color:var(--clr-success); margin:0;">
                    <div class="rev-label" style="color:var(--clr-success);">Completed Payments</div>
                    <div class="rev-val" style="color:var(--clr-text-main);">₹<?php echo number_format($completedRevenue, 2); ?></div>
                    <div style="font-size:0.9rem; color:var(--clr-text-muted); font-weight:600;"><i class="fas fa-check-circle text-success"></i> Reconciled</div>
                </div>
                <div class="panel" style="background:var(--clr-card); border-color:var(--clr-warning); margin:0;">
                    <div class="rev-label" style="color:var(--clr-warning);">Pending / COD</div>
                    <div class="rev-val" style="color:var(--clr-text-main);">₹<?php echo number_format($pendingRevenue, 2); ?></div>
                    <div style="font-size:0.9rem; color:var(--clr-text-muted); font-weight:600;"><i class="fas fa-clock text-warning"></i> Awaiting Delivery</div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div class="ph-title">Recent Transactions</div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Order ID</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $txn):
                            $badgeClass = 'status-new'; // Default
                            $statusStr = strtoupper($txn['payment_status'] ? $txn['payment_status'] : 'PENDING');

                            if (in_array($statusStr, ['COMPLETED', 'PAID', 'SUCCESS'])) $badgeClass = 'status-verified';
                            elseif (in_array($statusStr, ['FAILED', 'CANCELLED'])) $badgeClass = 'status-rejected';
                            elseif (in_array($statusStr, ['PENDING', 'PROCESSING'])) $badgeClass = 'status-review';
                        ?>
                            <tr>
                                <td class="td-id"><?php echo htmlspecialchars($txn['transaction_id'] ?? 'TXN-GEN-' . rand(1000, 9999)); ?></td>
                                <td>KSR<?php echo $txn['order_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($txn['payment_method']); ?></strong></td>
                                <td style="font-weight:800;">₹<?php echo number_format($txn['amount'], 2); ?></td>
                                <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusStr; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 6: INVENTORY MANAGEMENT -->
        <div id="sec-inventory-manage" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Master Inventory</h1>
                    <div class="pg-sub">Manage medicines, stock levels, and SKUs</div>
                </div>
                <button class="btn btn-primary"><i class="fas fa-plus"></i> Add New Medicine</button>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="sc-info">
                        <div class="sc-label">Total Medicines</div>
                        <div class="sc-val">1,248</div>
                    </div>
                    <div class="sc-icon" style="background:var(--clr-bg); color:var(--clr-text-main);"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid var(--clr-success);">
                    <div class="sc-info">
                        <div class="sc-label">Available</div>
                        <div class="sc-val" style="color:var(--clr-success);">1,180</div>
                    </div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid var(--clr-danger);">
                    <div class="sc-info">
                        <div class="sc-label">Out of Stock</div>
                        <div class="sc-val" style="color:var(--clr-danger);">23</div>
                    </div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid var(--clr-warning);">
                    <div class="sc-info">
                        <div class="sc-label">Expiring Soon</div>
                        <div class="sc-val" style="color:var(--clr-warning);">45</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px; position:relative; overflow:visible;">
                    <div style="position:relative; width:450px;">
                        <i class="fas fa-search" style="position:absolute; left:12px; top:12px; color:var(--clr-text-muted);"></i>
                        <input type="text" id="inventorySearchInput" placeholder="Live search 15,228 permutations..." autocomplete="off" onkeyup="searchMasterInventory(this.value)" style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--clr-border); border-radius:var(--radius-md); font-family:inherit; background:var(--clr-bg);">
                        <div id="inventorySearchResults" style="position:absolute; top:calc(100% + 4px); left:0; width:100%; background:var(--clr-card); border:1px solid var(--clr-border); border-radius:var(--radius-md); box-shadow:var(--shadow-lg); max-height:300px; overflow-y:auto; z-index:9999; display:none;"></div>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <select class="btn btn-outline" style="background:white;">
                            <option>Category: All</option>
                            <option>Antibiotics</option>
                            <option>Cardiology</option>
                        </select>
                        <button class="btn btn-outline"><i class="fas fa-filter"></i> Filters</button>
                    </div>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Batch</th>
                            <th class="text-right">Stock</th>
                            <th>Expiry</th>
                            <th class="text-right">Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td class="td-primary"><?php echo $item['name']; ?></td>
                                <td class="td-secondary"><?php echo $item['category']; ?></td>
                                <td class="td-id"><?php echo $item['batch']; ?></td>
                                <td class="text-right" style="font-weight:800; font-size:1.1rem;"><?php echo $item['stock']; ?></td>
                                <td style="font-weight:600;"><?php echo $item['expiry']; ?></td>
                                <td class="text-right" style="font-weight:700;">₹<?php echo $item['price']; ?></td>
                                <td class="<?php echo $item['status_class']; ?>"><?php echo $item['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 7: INVENTORY ALERTS (Low Stock / Expiry) -->
        <div id="sec-inventory-alerts" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Low Stock & Expiry Alerts</h1>
                    <div class="pg-sub">Monitor critical inventory levels and approaching expirations</div>
                </div>
            </div>

            <div class="split-layout">
                <div class="panel" style="border-top: 4px solid var(--clr-warning);">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fas fa-exclamation-triangle" style="color:var(--clr-warning);"></i> Low Stock Alerts</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Current Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): if (strpos($item['status'], 'Stock') !== false): ?>
                                    <tr>
                                        <td class="td-primary"><?php echo $item['name']; ?></td>
                                        <td style="font-weight:800; color:var(--clr-danger);"><?php echo $item['stock']; ?></td>
                                        <td><button class="btn btn-outline" style="padding:4px 8px; font-size:0.8rem;">Reorder</button></td>
                                    </tr>
                            <?php endif;
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel" style="border-top: 4px solid var(--clr-danger);">
                    <div class="panel-head">
                        <div class="ph-title"><i class="fas fa-calendar-times" style="color:var(--clr-danger);"></i> Expiry Alerts</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine / Batch</th>
                                <th>Expiry</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): if (strpos($item['status'], 'Expir') !== false): ?>
                                    <tr>
                                        <td>
                                            <div class="td-primary"><?php echo $item['name']; ?></div>
                                            <div class="td-secondary"><?php echo $item['batch']; ?></div>
                                        </td>
                                        <td style="font-weight:700; color:var(--clr-danger);"><?php echo $item['expiry']; ?></td>
                                        <td><button class="btn btn-outline" style="padding:4px 8px; font-size:0.8rem;">Remove</button></td>
                                    </tr>
                            <?php endif;
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 8: STOCK MOVEMENT -->
        <div id="sec-inventory-movement" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Stock Movement Log</h1>
                    <div class="pg-sub">Audit trail of all additions, sales, and adjustments (LIVE DATA)</div>
                </div>
                <button class="btn btn-outline"><i class="fas fa-download"></i> Export Log</button>
            </div>
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medicine (Batch)</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockMovements as $move):
                            $badgeClass = 'status-verified';
                            if ($move['movement_type'] == 'SALE') $badgeClass = 'status-dispensing';
                            if ($move['movement_type'] == 'EXPIRED' || $move['movement_type'] == 'DAMAGE') $badgeClass = 'status-rejected';

                            $qtyColor = ($move['quantity'] > 0) ? 'var(--clr-success)' : 'var(--clr-danger)';
                            $qtyPrefix = ($move['quantity'] > 0) ? '+' : '';
                        ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($move['created_at'])); ?></td>
                                <td class="td-primary"><?php echo htmlspecialchars($move['medicine_name']); ?></td>
                                <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $move['movement_type']; ?></span></td>
                                <td style="color:<?php echo $qtyColor; ?>; font-weight:800;"><?php echo $qtyPrefix . $move['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($move['performed_by']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 9: DELIVERY MANAGEMENT -->
        <div id="sec-delivery" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Delivery Dispatch</h1>
                    <div class="pg-sub">Track real-time courier status for outbound boxes (LIVE DATA)</div>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head">
                    <div class="ph-title">Active Deliveries</div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Patient / Address</th>
                            <th>Courier</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveries as $del):
                            $delBadge = 'status-new';
                            if ($del['status'] == 'OUT FOR DELIVERY') $delBadge = 'status-review';
                            if ($del['status'] == 'DELIVERED') $delBadge = 'status-verified';
                            if ($del['status'] == 'FAILED') $delBadge = 'status-rejected';
                        ?>
                            <tr>
                                <td class="td-id">KSR<?php echo $del['order_id']; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($del['patient_name']); ?></div>
                                    <div class="td-secondary"><?php echo htmlspecialchars($del['delivery_address']); ?></div>
                                </td>
                                <td><i class="fas fa-motorcycle text-muted"></i> <?php echo $del['courier_name'] ? htmlspecialchars($del['courier_name']) : '<em>Awaiting Assignment</em>'; ?></td>
                                <td><span class="status-badge <?php echo $delBadge; ?>"><?php echo $del['status']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 10: RETURNS & REFUNDS -->
        <div id="sec-returns" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Returns & Refunds</h1>
                    <div class="pg-sub">Process rejected items and refund requests</div>
                </div>
            </div>
            <div class="panel" style="text-align:center; padding: 60px 20px;">
                <i class="fas fa-box-open" style="font-size:3rem; color:var(--clr-border); margin-bottom:16px;"></i>
                <h3 style="color:var(--clr-text-main); margin-bottom:8px;">No Pending Returns</h3>
                <p style="color:var(--clr-text-muted);">All return requests have been processed.</p>
            </div>
        </div>

        <!-- SECTION 11: PATIENTS -->
        <div id="sec-patients" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Pharmacy Patients</h1>
                    <div class="pg-sub">Patient purchase history and active prescriptions</div>
                </div>
            </div>
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Last Order</th>
                            <th>Total Spend</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="td-id">PT-8239</td>
                            <td class="td-primary">Patient A (Rahul)</td>
                            <td>Today</td>
                            <td>₹4,250</td>
                            <td><button class="btn btn-outline" style="padding:4px 8px; font-size:0.8rem;">View Profile</button></td>
                        </tr>
                        <tr>
                            <td class="td-id">PT-1102</td>
                            <td class="td-primary">Sarah K.</td>
                            <td>Yesterday</td>
                            <td>₹240</td>
                            <td><button class="btn btn-outline" style="padding:4px 8px; font-size:0.8rem;">View Profile</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 12: DOCTORS -->
        <div id="sec-doctors" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Doctor Network</h1>
                    <div class="pg-sub">Prescription metrics across the hospital network</div>
                </div>
            </div>
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Total Prescriptions (Month)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="td-primary"><i class="fas fa-user-md text-muted"></i> Dr. Sharma</td>
                            <td>General Physician</td>
                            <td style="font-weight:800;">145</td>
                        </tr>
                        <tr>
                            <td class="td-primary"><i class="fas fa-user-md text-muted"></i> Dr. Iyer</td>
                            <td>Cardiologist</td>
                            <td style="font-weight:800;">82</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 13: REPORTS -->
        <div id="sec-reports" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Pharmacy Reports</h1>
                    <div class="pg-sub">Analytics, sales summaries, and compliance logs</div>
                </div>
            </div>
            <div class="split-layout">
                <div class="panel" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px;">
                    <i class="fas fa-chart-line" style="font-size:3rem; color:var(--clr-primary); margin-bottom:16px;"></i>
                    <h3 style="margin-bottom:12px;">Sales & Revenue</h3>
                    <button class="btn btn-primary">Download This Month</button>
                </div>
                <div class="panel" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px;">
                    <i class="fas fa-file-contract" style="font-size:3rem; color:var(--clr-secondary); margin-bottom:16px;"></i>
                    <h3 style="margin-bottom:12px;">Prescription Audit Log</h3>
                    <button class="btn btn-secondary">Download Report</button>
                </div>
            </div>
        </div>

        <!-- SECTION 14: SETTINGS -->
        <div id="sec-settings" class="tab-content">
            <div class="page-header">
                <div>
                    <h1 class="pg-title">Settings & Configuration</h1>
                    <div class="pg-sub">Manage pharmacy parameters and integrations</div>
                </div>
            </div>
            <div class="panel">
                <div class="ic-label">Pharmacy Name</div>
                <div class="ic-val" style="margin-bottom:24px;">KSR GURU JI Main Pharmacy</div>

                <h3 style="margin-bottom:16px; border-bottom:1px solid var(--clr-border); padding-bottom:8px;">Operational Toggles</h3>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; padding:12px; background:var(--clr-bg); border-radius:var(--radius-md);">
                    <div style="font-weight:700;">Accept Online Orders</div>
                    <div style="width:40px; height:24px; background:var(--clr-success); border-radius:12px; position:relative; cursor:pointer;">
                        <div style="width:18px; height:18px; background:#FFF; border-radius:50%; position:absolute; top:3px; right:3px;"></div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; padding:12px; background:var(--clr-bg); border-radius:var(--radius-md);">
                    <div style="font-weight:700;">Auto-allocate Inventory for AI Verified Rx</div>
                    <div style="width:40px; height:24px; background:var(--clr-border); border-radius:12px; position:relative; cursor:pointer;">
                        <div style="width:18px; height:18px; background:#FFF; border-radius:50%; position:absolute; top:3px; left:3px;"></div>
                    </div>
                </div>

                <button class="btn btn-primary mt-4">Save Changes</button>
            </div>
        </div>

    </div>

    <!-- Scripts for SPA behavior -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const header = document.querySelector('.header');

            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                return;
            }

            if (sidebar.style.width === '0px') {
                sidebar.style.width = 'var(--sidebar-width)';
                mainContent.style.marginLeft = 'var(--sidebar-width)';
                header.style.width = 'calc(100% - var(--sidebar-width))';
                header.style.left = 'var(--sidebar-width)';
            } else {
                sidebar.style.width = '0px';
                mainContent.style.marginLeft = '0';
                header.style.width = '100%';
                header.style.left = '0';
            }
        }

        function toggleSubMenu(element) {
            element.classList.toggle('expanded');
        }

        function switchSection(sectionId, navElement = null, forceSub = null) {
            // Hide all sections
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });

            // Show target section
            const target = document.getElementById('sec-' + sectionId);
            if (target) {
                target.classList.add('active');
            }

            // Update nav active states
            if (navElement) {
                document.querySelectorAll('.sb-item, .sb-sub-item').forEach(el => {
                    el.classList.remove('active');
                });
                navElement.classList.add('active');

                // If it's a sub-item, make sure parent is expanded
                if (navElement.classList.contains('sb-sub-item')) {
                    const parentMenu = navElement.closest('.sb-sub-menu');
                    if (parentMenu) {
                        const parentItem = parentMenu.previousElementSibling;
                        if (parentItem) {
                            parentItem.classList.add('expanded');
                        }
                    }
                }
            }
        }

        // Initialize empty sections to point somewhere for demo
        document.querySelectorAll('.sb-item, .sb-sub-item').forEach(item => {
            if (!item.getAttribute('onclick')) {
                item.setAttribute('onclick', "switchSection('dashboard', this)");
            }
        });

        // Live Inventory AJAX Auto-Complete
        function searchMasterInventory(query) {
            const resultsBox = document.getElementById('inventorySearchResults');
            if (query.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }

            fetch('api_inventory_search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.results.length === 0) {
                        resultsBox.innerHTML = '<div style="padding:12px; color:var(--clr-text-muted); text-align:center;">No formulation found in Master DB...</div>';
                        resultsBox.style.display = 'block';
                        return;
                    }

                    let html = '';
                    data.results.forEach(item => {
                        html += `
                            <div style="padding:10px 12px; border-bottom:1px solid var(--clr-border); display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="selectInventoryItem('${item.name}')">
                                <div style="font-weight:600; color:var(--clr-primary-dark);">${item.name}</div>
                                <span class="status-badge ${item.badge}" style="font-size:0.7rem; padding:2px 6px;">${item.status}</span>
                            </div>
                        `;
                    });
                    resultsBox.innerHTML = html;
                    resultsBox.style.display = 'block';
                });
        }

        function selectInventoryItem(name) {
            document.getElementById('inventorySearchInput').value = name;
            document.getElementById('inventorySearchResults').style.display = 'none';
        }

        // Hide autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#inventorySearchInput') && !e.target.closest('#inventorySearchResults')) {
                const box = document.getElementById('inventorySearchResults');
                if (box) box.style.display = 'none';
            }
        });
    </script>
</body>

</html>