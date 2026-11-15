<?php
// ============================================================
// api/delivery_orders.php — Delivery Boy Order API
// Handles GET (list by status) and POST (update status)
// Works with both MySQL (production) and SQLite (fallback/dev)
// ============================================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────────────────────
$deliveryBoyId = (int) ($_SESSION['delivery_boy_id'] ?? $_SESSION['user_id'] ?? 0);
$userRole      = $_SESSION['user_role'] ?? $_SESSION['delivery_boy_role'] ?? '';

if ($deliveryBoyId === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo    = getDBConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Detect which column name the orders table uses for a concept.
 * SQLite fallback and MySQL production differ in schema.
 */
function detectCol(PDO $pdo, string $table, array $candidates): string
{
    static $cache = [];
    $key = $table . ':' . implode(',', $candidates);
    if (isset($cache[$key])) return $cache[$key];

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
    } else {
        $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) {
            return $cache[$key] = $c;
        }
    }
    return $cache[$key] = $candidates[0]; // fallback to first
}

function orderStatusCol(PDO $pdo): string
{
    return detectCol($pdo, 'orders', ['order_status', 'status']);
}

function totalAmountCol(PDO $pdo): string
{
    return detectCol($pdo, 'orders', ['total_amount', 'net_amount', 'total']);
}

function addressCol(PDO $pdo): string
{
    return detectCol($pdo, 'orders', ['shipping_address', 'delivery_address', 'address']);
}

function completedAtCol(PDO $pdo): string
{
    return detectCol($pdo, 'orders', ['delivered_at', 'completed_at', 'updated_at']);
}

function createdAtCol(PDO $pdo): string
{
    return detectCol($pdo, 'orders', ['created_at', 'placed_at']);
}

// Map delivery page status names → DB status values
function mapStatus(string $status): array
{
    return match ($status) {
        'accepted'         => ['accepted', 'confirmed'],
        'picked_up'        => ['picked_up', 'processed', 'shipped'],
        'out_for_delivery' => ['out_for_delivery', 'out-for-delivery', 'shipped'],
        'delivered'        => ['delivered'],
        'failed'           => ['failed', 'cancelled'],
        'cancelled'        => ['cancelled'],
        default            => explode(',', $status),
    };
}

// ── GET — List orders by status ───────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $rawStatus  = trim($_GET['status'] ?? 'all');
    $reqBoyId   = (int) ($_GET['delivery_boy_id'] ?? $deliveryBoyId);
    $dateFilter = trim($_GET['date'] ?? '');

    $statusCol = orderStatusCol($pdo);
    $totalCol  = totalAmountCol($pdo);
    $addrCol   = addressCol($pdo);
    $completedCol = completedAtCol($pdo);
    $createdCol   = createdAtCol($pdo);

    // Build WHERE clauses
    $params  = [];
    $clauses = [];

    // Filter by delivery boy
    if ($reqBoyId > 0) {
        // Allow if requesting own orders, or if admin/delivery_manager
        if ($reqBoyId === $deliveryBoyId || in_array($userRole, ['admin', 'delivery_manager'], true)) {
            $clauses[] = "o.delivery_boy_id = ?";
            $params[]  = $reqBoyId;
        }
    }

    // Filter by status
    if ($rawStatus !== 'all') {
        $statuses = mapStatus($rawStatus);
        if (!empty($statuses)) {
            $in = implode(',', array_fill(0, count($statuses), '?'));
            $clauses[] = "o.$statusCol IN ($in)";
            foreach ($statuses as $s) {
                $params[] = $s;
            }
        }
    }

    // Filter by date (for delivered page)
    if ($dateFilter) {
        $clauses[] = "DATE(o.$createdCol) = ?";
        $params[]  = $dateFilter;
    }

    $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

    $sql = "SELECT
                o.id,
                o.order_number,
                o.$statusCol  AS status,
                o.$totalCol   AS total_amount,
                o.$addrCol    AS delivery_address,
                o.$completedCol AS completed_at,
                o.$createdCol AS placed_at,
                o.payment_method,
                o.payment_status,
                o.delivery_boy_id,
                u.name        AS customer_name,
                u.phone       AS customer_phone,
                u.email       AS customer_email
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            $where
            ORDER BY o.$createdCol DESC
            LIMIT 100";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add failure_reason from order_timeline if exists
        foreach ($orders as &$order) {
            $order['failure_reason'] = null;
            try {
                $tlStmt = $pdo->prepare(
                    "SELECT notes FROM order_timeline WHERE order_id = ? AND status IN ('failed','cancelled') ORDER BY created_at DESC LIMIT 1"
                );
                $tlStmt->execute([(int) $order['id']]);
                $order['failure_reason'] = $tlStmt->fetchColumn() ?: null;
            } catch (Throwable $ignored) {}
        }
        unset($order);

        echo json_encode([
            'success' => true,
            'orders'  => $orders,
            'count'   => count($orders),
        ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}

// ── POST — Update order status ────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $orderId   = (int) ($body['order_id'] ?? 0);
        $newStatus = trim($body['status'] ?? '');
        $reason    = trim($body['reason'] ?? '');
        $note      = trim($body['note'] ?? '');

        if ($orderId <= 0 || $newStatus === '') {
            echo json_encode(['success' => false, 'message' => 'Missing order_id or status']);
            exit;
        }

        $allowedStatuses = [
            'accepted', 'picked_up', 'out_for_delivery',
            'delivered', 'failed', 'cancelled'
        ];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit;
        }

        $statusCol = orderStatusCol($pdo);

        // Verify the order belongs to this delivery boy (or admin)
        $checkStmt = $pdo->prepare("SELECT id, delivery_boy_id FROM orders WHERE id = ?");
        $checkStmt->execute([$orderId]);
        $orderRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orderRow) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        if ((int) $orderRow['delivery_boy_id'] !== $deliveryBoyId
            && !in_array($userRole, ['admin', 'delivery_manager'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not authorised to update this order']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Update status
            if ($newStatus === 'delivered') {
                $completedCol = completedAtCol($pdo);
                if ($driver === 'sqlite') {
                    $pdo->prepare("UPDATE orders SET $statusCol = ?, $completedCol = datetime('now') WHERE id = ?")
                        ->execute([$newStatus, $orderId]);
                } else {
                    $pdo->prepare("UPDATE orders SET $statusCol = ?, $completedCol = NOW() WHERE id = ?")
                        ->execute([$newStatus, $orderId]);
                }
            } else {
                $pdo->prepare("UPDATE orders SET $statusCol = ? WHERE id = ?")
                    ->execute([$newStatus, $orderId]);
            }

            // Log to order_timeline
            try {
                $logNote = $note ?: ($reason ?: "Status updated to $newStatus by delivery boy");
                $pdo->prepare(
                    "INSERT INTO order_timeline (order_id, status, notes, created_by, created_at)
                     VALUES (?, ?, ?, ?, " . ($driver === 'sqlite' ? "datetime('now')" : "NOW()") . ")"
                )->execute([$orderId, $newStatus, $logNote, $deliveryBoyId]);
            } catch (Throwable $ignored) {
                // order_timeline may have different schema — non-fatal
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Order #$orderId status updated to $newStatus",
                'order_id' => $orderId,
                'status'   => $newStatus,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Unknown action
    echo json_encode(['success' => false, 'message' => "Unknown action: $action"]);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
