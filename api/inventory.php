<?php
require_once '../includes/functions.php';

requireRole('admin'); // Only admins can manage inventory

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_item':
        createInventoryItem();
        break;
    case 'update_item':
        updateInventoryItem();
        break;
    case 'delete_item':
        deleteInventoryItem();
        break;
    case 'add_stock':
        addStock();
        break;
    case 'adjust_stock':
        adjustStock();
        break;
    case 'allocate_to_quotation':
        allocateToQuotation();
        break;
    case 'deallocate_from_quotation':
        deallocateFromQuotation();
        break;
    case 'consume_allocated':
        consumeAllocated();
        break;
    case 'get_low_stock':
        getLowStockItems();
        break;
    case 'get_item_history':
        getItemHistory();
        break;
    case 'search_items':
        searchItems();
        break;
    case 'create_category':
        createCategory();
        break;
    case 'create_supplier':
        createSupplier();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createInventoryItem() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $item_code = sanitize($_POST['item_code'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $hsn_code = sanitize($_POST['hsn_code'] ?? '');
    $unit = sanitize($_POST['unit'] ?? 'nos');
    $minimum_stock = (float)($_POST['minimum_stock'] ?? 0);
    $maximum_stock = (float)($_POST['maximum_stock'] ?? 0);
    $reorder_level = (float)($_POST['reorder_level'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $supplier_part_number = sanitize($_POST['supplier_part_number'] ?? '');
    $location = sanitize($_POST['location'] ?? '');

    // Validation
    if (empty($item_code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Item code is required']);
        return;
    }

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Item name is required']);
        return;
    }

    try {
        $db->beginTransaction();

        // Check if item code already exists
        $existing = $db->fetch(
            "SELECT id FROM inventory WHERE item_code = ? AND organization_id = ?",
            [$item_code, $_SESSION['organization_id']]
        );

        if ($existing) {
            throw new Exception('Item code already exists');
        }

        $result = $db->query(
            "INSERT INTO inventory
            (organization_id, category_id, item_code, name, description, hsn_code, unit,
             minimum_stock, maximum_stock, reorder_level, selling_price, supplier_id,
             supplier_part_number, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['organization_id'], $category_id ?: null, $item_code, $name,
                $description, $hsn_code, $unit, $minimum_stock, $maximum_stock,
                $reorder_level, $selling_price, $supplier_id, $supplier_part_number, $location
            ]
        );

        if (!$result) {
            throw new Exception('Failed to create inventory item');
        }

        $item_id = $db->lastInsertId();

        // Log the creation
        logInventoryTransaction($item_id, 'adjustment', null, null, 0, 0, 0, 'Item created');
        logUserActivity($_SESSION['user_id'], 'inventory_item_created', 'inventory', $item_id,
                       "Created inventory item: $name ($item_code)");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Inventory item created successfully',
            'item_id' => $item_id
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create inventory item: ' . $e->getMessage()]);
    }
}

function updateInventoryItem() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item ID']);
        return;
    }

    // Get existing item
    $item = $db->fetch(
        "SELECT * FROM inventory WHERE id = ? AND organization_id = ?",
        [$item_id, $_SESSION['organization_id']]
    );

    if (!$item) {
        http_response_code(404);
        echo json_encode(['error' => 'Inventory item not found']);
        return;
    }

    try {
        $db->beginTransaction();

        // Build update query dynamically
        $updates = [];
        $params = [];
        $changes = [];

        $fields_to_update = [
            'name', 'description', 'category_id', 'hsn_code', 'unit',
            'minimum_stock', 'maximum_stock', 'reorder_level', 'selling_price',
            'supplier_id', 'supplier_part_number', 'location'
        ];

        foreach ($fields_to_update as $field) {
            if (isset($_POST[$field])) {
                $new_value = in_array($field, ['minimum_stock', 'maximum_stock', 'reorder_level', 'selling_price'])
                           ? (float)$_POST[$field]
                           : ($field === 'category_id' || $field === 'supplier_id'
                             ? ((int)$_POST[$field] ?: null)
                             : sanitize($_POST[$field]));

                if ($item[$field] !== $new_value) {
                    $updates[] = "`$field` = ?";
                    $params[] = $new_value;
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $item[$field],
                        'new_value' => $new_value
                    ];
                }
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE inventory SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $item_id;

            $result = $db->query($sql, $params);

            if (!$result) {
                throw new Exception('Failed to update inventory item');
            }

            logUserActivity($_SESSION['user_id'], 'inventory_item_updated', 'inventory', $item_id,
                           "Updated inventory item: {$item['name']} ({$item['item_code']})");
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'changes_count' => count($changes)
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update inventory item: ' . $e->getMessage()]);
    }
}

function addStock() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $item_id = (int)($_POST['item_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $notes = sanitize($_POST['notes'] ?? '');

    if ($item_id <= 0 || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item ID or quantity']);
        return;
    }

    try {
        $db->beginTransaction();

        // Get current stock
        $item = $db->fetch(
            "SELECT * FROM inventory WHERE id = ? AND organization_id = ?",
            [$item_id, $_SESSION['organization_id']]
        );

        if (!$item) {
            throw new Exception('Inventory item not found');
        }

        $new_stock = $item['current_stock'] + $quantity;
        $total_cost = $quantity * $unit_cost;

        // Update stock
        $db->query(
            "UPDATE inventory SET current_stock = ? WHERE id = ?",
            [$new_stock, $item_id]
        );

        // Log transaction
        logInventoryTransaction($item_id, 'purchase', 'manual', null, $quantity,
                              $unit_cost, $total_cost, $notes, $new_stock, $supplier_id);

        logUserActivity($_SESSION['user_id'], 'inventory_stock_added', 'inventory', $item_id,
                       "Added $quantity {$item['unit']} to {$item['name']}");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Stock added successfully',
            'new_stock' => $new_stock
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add stock: ' . $e->getMessage()]);
    }
}

function adjustStock() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $item_id = (int)($_POST['item_id'] ?? 0);
    $new_quantity = (float)($_POST['new_quantity'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');

    if ($item_id <= 0 || $new_quantity < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item ID or quantity']);
        return;
    }

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['error' => 'Adjustment reason is required']);
        return;
    }

    try {
        $db->beginTransaction();

        // Get current stock
        $item = $db->fetch(
            "SELECT * FROM inventory WHERE id = ? AND organization_id = ?",
            [$item_id, $_SESSION['organization_id']]
        );

        if (!$item) {
            throw new Exception('Inventory item not found');
        }

        $adjustment_quantity = $new_quantity - $item['current_stock'];

        // Update stock
        $db->query(
            "UPDATE inventory SET current_stock = ? WHERE id = ?",
            [$new_quantity, $item_id]
        );

        // Log transaction
        logInventoryTransaction($item_id, 'adjustment', 'adjustment', null,
                              $adjustment_quantity, null, null, $reason, $new_quantity);

        logUserActivity($_SESSION['user_id'], 'inventory_stock_adjusted', 'inventory', $item_id,
                       "Adjusted stock for {$item['name']} to $new_quantity {$item['unit']}. Reason: $reason");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Stock adjusted successfully',
            'old_stock' => $item['current_stock'],
            'new_stock' => $new_quantity,
            'adjustment' => $adjustment_quantity
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to adjust stock: ' . $e->getMessage()]);
    }
}

function allocateToQuotation() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

    if ($quotation_id <= 0 || $item_id <= 0 || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }

    try {
        $db->beginTransaction();

        // Verify quotation exists and belongs to organization
        $quotation = $db->fetch(
            "SELECT * FROM quotations_new WHERE id = ? AND organization_id = ?",
            [$quotation_id, $_SESSION['organization_id']]
        );

        if (!quotation) {
            throw new Exception('Quotation not found');
        }

        // Get inventory item
        $item = $db->fetch(
            "SELECT * FROM inventory WHERE id = ? AND organization_id = ?",
            [$item_id, $_SESSION['organization_id']]
        );

        if (!$item) {
            throw new Exception('Inventory item not found');
        }

        // Check if enough stock is available
        if ($item['available_stock'] < $quantity) {
            throw new Exception('Insufficient available stock. Available: ' . $item['available_stock']);
        }

        // Check if allocation already exists
        $existing = $db->fetch(
            "SELECT * FROM inventory_allocations WHERE quotation_id = ? AND inventory_item_id = ?",
            [$quotation_id, $item_id]
        );

        if ($existing) {
            // Update existing allocation
            $new_quantity = $existing['allocated_quantity'] + $quantity;
            $db->query(
                "UPDATE inventory_allocations
                 SET allocated_quantity = ?, allocation_notes = ?
                 WHERE quotation_id = ? AND inventory_item_id = ?",
                [$new_quantity, $notes, $quotation_id, $item_id]
            );
        } else {
            // Create new allocation
            $db->query(
                "INSERT INTO inventory_allocations
                 (quotation_id, inventory_item_id, allocated_quantity, allocated_by, allocation_notes)
                 VALUES (?, ?, ?, ?, ?)",
                [$quotation_id, $item_id, $quantity, $_SESSION['user_id'], $notes]
            );
        }

        // Log transaction
        logInventoryTransaction($item_id, 'allocation', 'quotation', $quotation_id,
                              -$quantity, null, null, $notes, null);

        logUserActivity($_SESSION['user_id'], 'inventory_allocated', 'quotation', $quotation_id,
                       "Allocated $quantity {$item['unit']} of {$item['name']} to quotation {$quotation['quotation_number']}");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Inventory allocated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to allocate inventory: ' . $e->getMessage()]);
    }
}

function getLowStockItems() {
    global $db;

    $items = $db->fetchAll(
        "SELECT i.*, c.name as category_name, s.name as supplier_name
         FROM inventory i
         LEFT JOIN inventory_categories c ON i.category_id = c.id
         LEFT JOIN suppliers s ON i.supplier_id = s.id
         WHERE i.organization_id = ? AND i.is_active = 1
           AND i.available_stock <= i.reorder_level
         ORDER BY (i.available_stock - i.reorder_level) ASC, i.name ASC",
        [$_SESSION['organization_id']]
    );

    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);
}

function searchItems() {
    global $db;

    $search = sanitize($_POST['search'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    $only_available = (int)($_POST['only_available'] ?? 0);

    $sql = "SELECT i.*, c.name as category_name
            FROM inventory i
            LEFT JOIN inventory_categories c ON i.category_id = c.id
            WHERE i.organization_id = ? AND i.is_active = 1";
    $params = [$_SESSION['organization_id']];

    if (!empty($search)) {
        $sql .= " AND (i.name LIKE ? OR i.item_code LIKE ? OR i.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($category_id) {
        $sql .= " AND i.category_id = ?";
        $params[] = $category_id;
    }

    if ($only_available) {
        $sql .= " AND i.available_stock > 0";
    }

    $sql .= " ORDER BY i.name ASC LIMIT 50";

    $items = $db->fetchAll($sql, $params);

    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
}

function createCategory() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $parent_category_id = (int)($_POST['parent_category_id'] ?? 0) ?: null;

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Category name is required']);
        return;
    }

    try {
        $result = $db->query(
            "INSERT INTO inventory_categories (name, description, parent_category_id)
             VALUES (?, ?, ?)",
            [$name, $description, $parent_category_id]
        );

        if (!$result) {
            throw new Exception('Failed to create category');
        }

        $category_id = $db->lastInsertId();

        logUserActivity($_SESSION['user_id'], 'inventory_category_created', 'category', $category_id,
                       "Created inventory category: $name");

        echo json_encode([
            'success' => true,
            'message' => 'Category created successfully',
            'category_id' => $category_id
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create category: ' . $e->getMessage()]);
    }
}

function createSupplier() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $name = sanitize($_POST['name'] ?? '');
    $contact_person = sanitize($_POST['contact_person'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $gstin = sanitize($_POST['gstin'] ?? '');
    $pan = sanitize($_POST['pan'] ?? '');

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Supplier name is required']);
        return;
    }

    try {
        $result = $db->query(
            "INSERT INTO suppliers
             (organization_id, name, contact_person, email, phone, address, gstin, pan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$_SESSION['organization_id'], $name, $contact_person, $email, $phone, $address, $gstin, $pan]
        );

        if (!$result) {
            throw new Exception('Failed to create supplier');
        }

        $supplier_id = $db->lastInsertId();

        logUserActivity($_SESSION['user_id'], 'supplier_created', 'supplier', $supplier_id,
                       "Created supplier: $name");

        echo json_encode([
            'success' => true,
            'message' => 'Supplier created successfully',
            'supplier_id' => $supplier_id
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create supplier: ' . $e->getMessage()]);
    }
}

// Helper functions
function logInventoryTransaction($item_id, $type, $reference_type = null, $reference_id = null,
                               $quantity = 0, $unit_cost = null, $total_cost = null, $notes = null,
                               $running_balance = null, $supplier_id = null) {
    global $db;

    if ($running_balance === null) {
        $item = $db->fetch("SELECT current_stock FROM inventory WHERE id = ?", [$item_id]);
        $running_balance = $item['current_stock'];
    }

    $db->query(
        "INSERT INTO inventory_transactions
         (organization_id, inventory_item_id, transaction_type, reference_type, reference_id,
          quantity, unit_cost, total_cost, running_balance, notes, performed_by, supplier_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $_SESSION['organization_id'], $item_id, $type, $reference_type, $reference_id,
            $quantity, $unit_cost, $total_cost, $running_balance, $notes, $_SESSION['user_id'], $supplier_id
        ]
    );
}

function logUserActivity($user_id, $action, $entity_type, $entity_id, $description, $metadata = null) {
    global $db;

    $db->query(
        "INSERT INTO user_activity_log
         (user_id, organization_id, action, entity_type, entity_id, description,
          metadata, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $user_id, $_SESSION['organization_id'], $action, $entity_type, $entity_id,
            $description, $metadata,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
}
?>