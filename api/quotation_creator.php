<?php
require_once '../includes/functions.php';

requireRole('admin');

// Catch any output that shouldn't be there
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createDetailedQuotation();
        break;
    case 'create_standalone':
        createStandaloneQuotation();
        break;
    case 'send_to_requestor':
        sendQuotationToRequestor();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createDetailedQuotation() {
    global $db;


    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }


    $request_id = (int)($_POST['request_id'] ?? 0);
    $base_service_charge = (float)($_POST['base_service_charge'] ?? 0);
    $work_description = sanitize($_POST['work_description'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $sgst_rate = (float)($_POST['sgst_rate'] ?? 9.00);
    $cgst_rate = (float)($_POST['cgst_rate'] ?? 9.00);
    $sgst_amount = (float)($_POST['sgst_amount'] ?? 0);
    $cgst_amount = (float)($_POST['cgst_amount'] ?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $items = $_POST['items'] ?? [];

    // Validation
    if ($request_id <= 0) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        return;
    }

    if ($base_service_charge <= 0) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Base service charge must be greater than zero']);
        return;
    }

    if (empty($work_description)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Work description is required']);
        return;
    }

    // Check if service request exists and is pending
    try {
        $request = $db->fetch(
            "SELECT sr.*, v.registration_number, u.full_name as requestor_name, u.organization_id
             FROM service_requests sr
             JOIN vehicles v ON sr.vehicle_id = v.id
             JOIN users u ON sr.user_id = u.id
             WHERE sr.id = ? AND sr.status = 'pending'",
            [$request_id]
        );

        if (!$request) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Service request not found or already processed']);
            return;
        }
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        return;
    }

    // Organization filtering
    if ($_SESSION['organization_id'] != 2 && $request['organization_id'] != $_SESSION['organization_id']) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if quotation already exists for this request
    try {
        $existingQuotation = $db->fetch(
            "SELECT id FROM quotations WHERE request_id = ?",
            [$request_id]
        );

        if ($existingQuotation) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Quotation already exists for this request']);
            return;
        }
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'Database error checking existing quotation: ' . $e->getMessage()]);
        return;
    }

    try {
        // Start transaction
        $db->beginTransaction();

        // Generate quotation number
        $quotation_number = generateQuotationNumber($request_id);

        // Check if new columns exist, if not, use basic quotation creation
        $columns = $db->fetchAll("DESCRIBE quotations");
        $columnNames = array_column($columns, 'Field');
        $hasNewColumns = in_array('quotation_number', $columnNames) && in_array('base_service_charge', $columnNames);

        if ($hasNewColumns) {
            // Create quotation with new schema
            $result = $db->query(
                "INSERT INTO quotations
                 (quotation_number, request_id, amount, work_description, base_service_charge,
                  subtotal, sgst_rate, cgst_rate, sgst_amount, cgst_amount, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent')",
                [
                    $quotation_number,
                    $request_id,
                    $total_amount, // Keep for backward compatibility
                    $work_description,
                    $base_service_charge,
                    $subtotal,
                    $sgst_rate,
                    $cgst_rate,
                    $sgst_amount,
                    $cgst_amount,
                    $total_amount
                ]
            );
        } else {
            // Fall back to basic quotation creation (old schema)
            $result = $db->query(
                "INSERT INTO quotations (request_id, amount, work_description, status)
                 VALUES (?, ?, ?, 'sent')",
                [$request_id, $total_amount, $work_description]
            );
        }

        if (!$result) {
            throw new Exception('Failed to create quotation');
        }

        $quotation_id = $db->lastInsertId();

        // Insert quotation items (only if quotation_items table exists)
        if (!empty($items) && $hasNewColumns) {
            // Check if quotation_items table exists
            $tables = $db->fetchAll("SHOW TABLES LIKE 'quotation_items'");
            if (!empty($tables)) {
                foreach ($items as $item) {
                    $item_type = sanitize($item['type'] ?? '');
                    $description = sanitize($item['description'] ?? '');
                    $quantity = (float)($item['quantity'] ?? 1);
                    $rate = (float)($item['rate'] ?? 0);
                    $amount = (float)($item['amount'] ?? 0);

                    if (!empty($description) && $rate > 0) {
                        $db->query(
                            "INSERT INTO quotation_items (quotation_id, item_type, description, quantity, rate, amount)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [$quotation_id, $item_type, $description, $quantity, $rate, $amount]
                        );
                    }
                }
            }
        }

        // Update service request status
        $db->query(
            "UPDATE service_requests SET status = 'quoted', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$request_id]
        );

        // Commit transaction
        $db->commit();

        // Clean any unexpected output before JSON response
        $unexpectedOutput = ob_get_clean();
        if (!empty($unexpectedOutput)) {
            error_log("Unexpected output in quotation_creator.php: " . $unexpectedOutput);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Quotation created successfully',
            'quotation_id' => $quotation_id,
            'quotation_number' => $hasNewColumns ? $quotation_number : 'QT-' . $quotation_id,
            'redirect' => 'quotation_creator.php?success=created'
        ]);

    } catch (Exception $e) {
        $db->rollback();

        // Clean any unexpected output before JSON response
        $unexpectedOutput = ob_get_clean();
        if (!empty($unexpectedOutput)) {
            error_log("Unexpected output in quotation_creator.php error: " . $unexpectedOutput);
        }

        http_response_code(500);
        echo json_encode(['error' => 'Failed to create quotation: ' . $e->getMessage()]);
    }
}

function createStandaloneQuotation() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $request_id = sanitize($_POST['request_id'] ?? ''); // This will be the generated WALK-YYYY-NNNN ID
    $vehicle_registration = sanitize($_POST['vehicle_registration'] ?? '');
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_email = sanitize($_POST['customer_email'] ?? '');
    $problem_description = sanitize($_POST['problem_description'] ?? '');
    $base_service_charge = (float)($_POST['base_service_charge'] ?? 0);
    $work_description = sanitize($_POST['work_description'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $sgst_rate = (float)($_POST['sgst_rate'] ?? 9.00);
    $cgst_rate = (float)($_POST['cgst_rate'] ?? 9.00);
    $sgst_amount = (float)($_POST['sgst_amount'] ?? 0);
    $cgst_amount = (float)($_POST['cgst_amount'] ?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $items = $_POST['items'] ?? [];

    // Validation
    if (empty($request_id)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        return;
    }

    if (empty($vehicle_registration)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Vehicle registration is required']);
        return;
    }

    if (empty($customer_name)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Customer name is required']);
        return;
    }

    if (empty($problem_description)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Problem description is required']);
        return;
    }

    if ($base_service_charge <= 0) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Base service charge must be greater than zero']);
        return;
    }

    if (empty($work_description)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Work description is required']);
        return;
    }

    try {
        // Start transaction
        $db->beginTransaction();

        // Check if new columns exist for enhanced quotations
        $columns = $db->fetchAll("DESCRIBE quotations");
        $columnNames = array_column($columns, 'Field');
        $hasNewColumns = in_array('quotation_number', $columnNames) && in_array('base_service_charge', $columnNames);
        $hasStandaloneColumns = in_array('is_standalone', $columnNames) && in_array('standalone_customer_id', $columnNames);

        // Create standalone customer record
        if ($hasStandaloneColumns) {
            // Check if standalone_customers table exists
            $tables = $db->fetchAll("SHOW TABLES LIKE 'standalone_customers'");
            if (empty($tables)) {
                throw new Exception('Standalone customers table not found. Please run database updates.');
            }

            $customerResult = $db->query(
                "INSERT INTO standalone_customers (request_id, vehicle_registration, customer_name, customer_email, problem_description, organization_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$request_id, $vehicle_registration, $customer_name, $customer_email, $problem_description, $_SESSION['organization_id']]
            );

            if (!$customerResult) {
                throw new Exception('Failed to create customer record');
            }

            $standalone_customer_id = $db->lastInsertId();

            // Generate quotation number
            $quotation_number = 'QT-' . $request_id;

            if ($hasNewColumns) {
                // Create quotation with enhanced schema
                $result = $db->query(
                    "INSERT INTO quotations
                     (quotation_number, request_id, is_standalone, standalone_customer_id, amount, work_description, base_service_charge,
                      subtotal, sgst_rate, cgst_rate, sgst_amount, cgst_amount, total_amount, status)
                     VALUES (?, 0, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent')",
                    [
                        $quotation_number,
                        $standalone_customer_id,
                        $total_amount,
                        $work_description,
                        $base_service_charge,
                        $subtotal,
                        $sgst_rate,
                        $cgst_rate,
                        $sgst_amount,
                        $cgst_amount,
                        $total_amount
                    ]
                );
            } else {
                // Fallback to basic quotation creation
                $result = $db->query(
                    "INSERT INTO quotations (request_id, is_standalone, standalone_customer_id, amount, work_description, status)
                     VALUES (0, 1, ?, ?, ?, 'sent')",
                    [$standalone_customer_id, $total_amount, $work_description]
                );
            }
        } else {
            // Fallback - create a temporary service request for backward compatibility
            throw new Exception('Database schema not updated for standalone quotations. Please run database updates.');
        }

        if (!$result) {
            throw new Exception('Failed to create quotation');
        }

        $quotation_id = $db->lastInsertId();

        // Insert quotation items (if applicable)
        if (!empty($items) && $hasNewColumns) {
            $tables = $db->fetchAll("SHOW TABLES LIKE 'quotation_items'");
            if (!empty($tables)) {
                foreach ($items as $item) {
                    $item_type = sanitize($item['type'] ?? '');
                    $description = sanitize($item['description'] ?? '');
                    $quantity = (float)($item['quantity'] ?? 1);
                    $rate = (float)($item['rate'] ?? 0);
                    $amount = (float)($item['amount'] ?? 0);

                    if (!empty($description) && $rate > 0) {
                        $db->query(
                            "INSERT INTO quotation_items (quotation_id, item_type, description, quantity, rate, amount)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [$quotation_id, $item_type, $description, $quantity, $rate, $amount]
                        );
                    }
                }
            }
        }

        // Commit transaction
        $db->commit();

        // Clean any unexpected output before JSON response
        $unexpectedOutput = ob_get_clean();
        if (!empty($unexpectedOutput)) {
            error_log("Unexpected output in standalone quotation creation: " . $unexpectedOutput);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Standalone quotation created successfully',
            'quotation_id' => $quotation_id,
            'quotation_number' => $hasNewColumns ? $quotation_number : 'QT-' . $quotation_id,
            'request_id' => $request_id,
            'redirect' => 'quotation_creator.php?success=created'
        ]);

    } catch (Exception $e) {
        $db->rollback();

        // Clean any unexpected output before JSON response
        $unexpectedOutput = ob_get_clean();
        if (!empty($unexpectedOutput)) {
            error_log("Unexpected output in standalone quotation error: " . $unexpectedOutput);
        }

        http_response_code(500);
        echo json_encode(['error' => 'Failed to create standalone quotation: ' . $e->getMessage()]);
    }
}

function sendQuotationToRequestor() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    // Get quotation details
    $quotation = $db->fetch(
        "SELECT q.*, sr.user_id, u.full_name as requestor_name, u.email as requestor_email,
                v.registration_number, u.organization_id
         FROM quotations q
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN users u ON sr.user_id = u.id
         JOIN vehicles v ON sr.vehicle_id = v.id
         WHERE q.id = ?",
        [$quotation_id]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    // Organization filtering
    if ($_SESSION['organization_id'] != 2 && $quotation['organization_id'] != $_SESSION['organization_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // TODO: Implement email sending functionality
    // For now, just mark as sent and return success
    $db->query(
        "UPDATE quotations SET sent_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$quotation_id]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Quotation sent to requestor successfully',
        'requestor_email' => $quotation['requestor_email']
    ]);
}

function generateQuotationNumber($request_id) {
    // Using request ID based quotation number for traceability
    return 'QT-REQ-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);
}
?>