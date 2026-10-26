<?php
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$results = [];

// Common medicine formulation master list for autocomplete
$masterList = [
    ['name' => 'Paracetamol 500mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Paracetamol 650mg (Dolo 650) Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Amoxicillin 500mg Capsule', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Amoxicillin + Clavulanic Acid 625mg (Augmentin)', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Azithromycin 500mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Cetirizine 10mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Levocetirizine 5mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Pantoprazole 40mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Pantoprazole + Domperidone (Pan-D) Capsule', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Omeprazole 20mg Capsule', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Ibuprofen 400mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Aceclofenac + Paracetamol (Zerodol-P)', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Metformin 500mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Metformin 1000mg SR Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Glimepiride 1mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Glimepiride 2mg Tablet', 'status' => 'Low Stock', 'badge' => 'badge-pending'],
    ['name' => 'Atorvastatin 10mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Atorvastatin 20mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Telmisartan 40mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Amlodipine 5mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Ciprofloxacin 500mg Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Ofloxacin + Ornidazole Tablet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Montelukast + Levocetirizine (Montair-LC)', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Dextromethorphan Cough Syrup 100ml', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Ambroxol + Terbutaline Cough Syrup', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'ORS (Oral Rehydration Salts) Sachet', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Multivitamin & Zinc Capsules', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Vitamin C 500mg Chewable (Limcee)', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Vitamin D3 60K IU Capsule', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Ranitidine 150mg Tablet', 'status' => 'Out of Stock', 'badge' => 'badge-cancelled'],
    ['name' => 'Diclofenac Gel 30g', 'status' => 'Available', 'badge' => 'badge-confirmed'],
    ['name' => 'Betadine 10% Ointment 20g', 'status' => 'Available', 'badge' => 'badge-confirmed']
];

// 1. Search products from DB if available
try {
    require_once __DIR__ . '/../../includes/db.php';
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT name, stock FROM products WHERE name LIKE ? LIMIT 15");
        $stmt->execute(['%' . $query . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $stock = (int)($r['stock'] ?? 0);
            $status = $stock > 5 ? 'Available' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
            $badge = $stock > 5 ? 'badge-confirmed' : ($stock > 0 ? 'badge-pending' : 'badge-cancelled');
            $results[] = [
                'name' => $r['name'],
                'status' => $status,
                'badge' => $badge
            ];
        }
    }
} catch (Throwable $e) {
    // Proceed with master list if DB query fails
}

// 2. Search in master list
$qLower = strtolower($query);
foreach ($masterList as $item) {
    if (strpos(strtolower($item['name']), $qLower) !== false) {
        // Avoid duplicate by name
        $exists = false;
        foreach ($results as $res) {
            if (strcasecmp($res['name'], $item['name']) === 0) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $results[] = $item;
        }
    }
}

// Return top 15 results
$results = array_slice($results, 0, 15);

echo json_encode([
    'success' => true,
    'results' => $results
]);
