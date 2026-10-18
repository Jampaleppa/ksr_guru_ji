<?php
/**
 * api_medicines.php
 * ---------------------------------------------------------------------
 * JSON medicine search for the prescription builder.
 *
 * The rows come from ksr_guru_ji.medicine_catalogue — exactly the same
 * master data that powers WhatsApp/public/telehealth/medicine/tablet.php.
 *
 * GET  ?q=paracetamol[&limit=10]   → { ok, items[] }
 * GET  ?id=1234                    → { ok, item }
 */
declare(strict_types=1);

require_once 'helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (isset($_GET['id'])) {
        $item = telehealthMedicineById((int) $_GET['id']);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'Medicine not found in the catalogue.']);
            exit;
        }

        echo json_encode(['ok' => true, 'item' => $item]);
        exit;
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    $limit = (int) ($_GET['limit'] ?? 10);

    if (mb_strlen($query) < 2) {
        echo json_encode(['ok' => true, 'items' => [], 'msg' => 'Type at least 2 characters.']);
        exit;
    }

    $items = telehealthSearchMedicines($query, $limit);

    echo json_encode([
        'ok'      => true,
        'count'   => count($items),
        'rows'    => telehealthCatalogueCount(),
        'catalogue' => telehealthCatalogueDbName() . '.medicine_catalogue',
        'items'   => $items,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Catalogue search failed: ' . $e->getMessage()]);
}
