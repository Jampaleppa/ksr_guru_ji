<?php
header('Content-Type: application/json');

$query = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

require_once __DIR__ . '/helper.php';

$results = [];

try {
    $meds = telehealthSearchMedicines($query, 12);
    foreach ($meds as $med) {
        $stock = $med['stock'] ?? 100;
        $status = 'Available';
        $badge = 'status-verified';
        if ($stock !== null && $stock <= 0) {
            $status = 'Out of Stock';
            $badge = 'status-rejected';
        } elseif ($stock !== null && $stock <= 20) {
            $status = 'Low Stock (' . $stock . ')';
            $badge = 'status-review';
        }

        $results[] = [
            'name' => $med['label'] ?? $med['medicine_name'],
            'status' => $status,
            'badge' => $badge,
            'price' => $med['selling_price'] ?? 0,
            'price_text' => $med['price_text'] ?? '',
            'rx_required' => $med['rx_required'] ?? false
        ];
    }
} catch (Throwable $e) {
    // If master catalogue fails, try assist db fallback
    try {
        $pdo = getAssistDB();
        $stmt = $pdo->prepare("SELECT medicine_name AS name FROM medicines WHERE medicine_name LIKE ? LIMIT 10");
        $stmt->execute(['%' . $query . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $results[] = [
                'name' => $r['name'],
                'status' => 'Available',
                'badge' => 'status-verified'
            ];
        }
    } catch (Throwable $ex) {
        // Safe fallback
    }
}

echo json_encode(['results' => $results]);
?>
