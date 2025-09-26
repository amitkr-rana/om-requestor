<?php
require_once '../includes/functions.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = trim($_GET['q'] ?? '');

// If no query provided, return all items (for datalist population)
// If query provided, minimum 2 characters for search
if (strlen($query) > 0 && strlen($query) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    if (empty($query)) {
        // Return all items when no query provided (for datalist population)
        $items = $db->fetchAll(
            "SELECT id, name, selling_price, current_stock, item_code
             FROM inventory
             WHERE organization_id = ? AND is_active = 1
             ORDER BY name ASC
             LIMIT 50",
            [$_SESSION['organization_id']]
        );
    } else {
        // Search inventory items by name or item code
        $searchTerm = '%' . $query . '%';

        $items = $db->fetchAll(
            "SELECT id, name, selling_price, current_stock, item_code
             FROM inventory
             WHERE organization_id = ?
             AND is_active = 1
             AND (name LIKE ? OR item_code LIKE ?)
             ORDER BY
                CASE
                    WHEN name LIKE ? THEN 1
                    WHEN item_code LIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
             LIMIT 10",
            [
                $_SESSION['organization_id'],
                $searchTerm,
                $searchTerm,
                $query . '%',  // Exact prefix match gets higher priority
                $query . '%'
            ]
        );
    }

    // Format items for autocomplete
    $formattedItems = array_map(function($item) {
        $stockStatus = '';
        if ($item['current_stock'] <= 0) {
            $stockStatus = 'Out of Stock';
        } elseif ($item['current_stock'] <= 10) {
            $stockStatus = 'Low Stock';
        } else {
            $stockStatus = 'In Stock';
        }

        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'item_code' => $item['item_code'],
            'selling_price' => floatval($item['selling_price']),
            'current_stock' => floatval($item['current_stock']),
            'stock_status' => $stockStatus,
            'display_text' => $item['name'] . ' (Stock: ' . number_format($item['current_stock'], 0) . ' units)',
            'label' => $item['name'],  // For autocomplete display
            'value' => $item['name']   // What gets filled in the input
        ];
    }, $items);

    echo json_encode(['items' => $formattedItems]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}
?>