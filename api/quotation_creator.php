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
    case 'preview':
        previewQuotation();
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

function previewQuotation() {
    global $db;

    // Clean any existing output
    ob_clean();

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $is_standalone = ($_POST['quotation_type'] ?? '') === 'standalone';

    try {
        // Prepare quotation data for preview
        if ($is_standalone) {
            $quotationData = [
                'quotation_number' => 'QT-PREVIEW-' . date('YmdHis'),
                'display_request_id' => sanitize($_POST['request_id'] ?? ''),
                'formatted_date' => date('d-m-Y'),
                'requestor_name' => sanitize($_POST['customer_name'] ?? ''),
                'requestor_email' => sanitize($_POST['customer_email'] ?? ''),
                'registration_number' => sanitize($_POST['vehicle_registration'] ?? ''),
                'problem_description' => sanitize($_POST['problem_description'] ?? ''),
                'work_description' => sanitize($_POST['work_description'] ?? ''),
                'base_service_charge' => (float)($_POST['base_service_charge'] ?? 0),
                'subtotal' => (float)($_POST['subtotal'] ?? 0),
                'sgst_rate' => (float)($_POST['sgst_rate'] ?? 9.00),
                'cgst_rate' => (float)($_POST['cgst_rate'] ?? 9.00),
                'sgst_amount' => (float)($_POST['sgst_amount'] ?? 0),
                'cgst_amount' => (float)($_POST['cgst_amount'] ?? 0),
                'total_amount' => (float)($_POST['total_amount'] ?? 0)
            ];
        } else {
            $request_id = (int)($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request ID']);
                return;
            }

            // Get service request details
            $serviceRequest = $db->fetch(
                "SELECT sr.*, v.registration_number, u.full_name as requestor_name, u.email as requestor_email, u.organization_id
                 FROM service_requests sr
                 JOIN vehicles v ON sr.vehicle_id = v.id
                 JOIN users u ON sr.user_id = u.id
                 WHERE sr.id = ?",
                [$request_id]
            );

            if (!$serviceRequest) {
                http_response_code(404);
                echo json_encode(['error' => 'Service request not found']);
                return;
            }

            // Organization filtering
            if ($_SESSION['organization_id'] != 2 && $serviceRequest['organization_id'] != $_SESSION['organization_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }

            $quotationData = [
                'quotation_number' => 'QT-PREVIEW-' . date('YmdHis'),
                'display_request_id' => '#' . $request_id,
                'formatted_date' => date('d-m-Y'),
                'requestor_name' => $serviceRequest['requestor_name'],
                'requestor_email' => $serviceRequest['requestor_email'],
                'registration_number' => $serviceRequest['registration_number'],
                'problem_description' => $serviceRequest['problem_description'],
                'work_description' => sanitize($_POST['work_description'] ?? ''),
                'base_service_charge' => (float)($_POST['base_service_charge'] ?? 0),
                'subtotal' => (float)($_POST['subtotal'] ?? 0),
                'sgst_rate' => (float)($_POST['sgst_rate'] ?? 9.00),
                'cgst_rate' => (float)($_POST['cgst_rate'] ?? 9.00),
                'sgst_amount' => (float)($_POST['sgst_amount'] ?? 0),
                'cgst_amount' => (float)($_POST['cgst_amount'] ?? 0),
                'total_amount' => (float)($_POST['total_amount'] ?? 0)
            ];
        }

        // Validate required fields
        if (empty($quotationData['work_description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Work description is required']);
            return;
        }

        if ($quotationData['base_service_charge'] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Base service charge must be greater than zero']);
            return;
        }

        // Process items
        $items = [];
        $itemsData = $_POST['items'] ?? [];
        foreach ($itemsData as $item) {
            $description = sanitize($item['description'] ?? '');
            $item_type = sanitize($item['type'] ?? 'parts');
            $quantity = (float)($item['quantity'] ?? 1);
            $rate = (float)($item['rate'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);

            if (!empty($description) && $rate > 0) {
                $items[] = [
                    'description' => $description,
                    'item_type' => $item_type,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'amount' => $amount
                ];
            }
        }

        // Get theme preference
        $theme = sanitize($_POST['theme'] ?? 'light');

        // Generate HTML preview using the same template as the PDF
        $html = generatePreviewHTML($quotationData, $items, $theme);

        // Set headers for HTML preview
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate preview: ' . $e->getMessage()]);
    }
}

function generatePreviewHTML($quotation, $items, $theme = 'light') {
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotation Preview - <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: <?php echo $theme === 'dark' ? '#e5e7eb' : '#333'; ?>;
            line-height: 1.6;
            background-color: <?php echo $theme === 'dark' ? '#1f2937' : '#f9fafb'; ?>;
        }
        .preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: <?php echo $theme === 'dark' ? '#374151' : 'white'; ?>;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, <?php echo $theme === 'dark' ? '0.3' : '0.1'; ?>);
        }
        .letterhead {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }
        .company-tagline {
            font-size: 14px;
            color: <?php echo $theme === 'dark' ? '#e5e7eb' : '#6b7280'; ?>;
            margin: 5px 0;
        }
        .company-contact {
            font-size: 12px;
            color: <?php echo $theme === 'dark' ? '#e5e7eb' : '#4b5563'; ?>;
            margin: 10px 0;
        }
        .quotation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .quotation-info, .customer-info {
            width: 48%;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .service-description {
            background-color: <?php echo $theme === 'dark' ? '#4b5563' : '#f9fafb'; ?>;
            padding: 15px;
            border-left: 4px solid #2563eb;
            margin: 20px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid <?php echo $theme === 'dark' ? '#6b7280' : '#d1d5db'; ?>;
            padding: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: <?php echo $theme === 'dark' ? '#6b7280' : '#f3f4f6'; ?>;
            font-weight: bold;
            color: <?php echo $theme === 'dark' ? '#f3f4f6' : '#374151'; ?>;
        }
        .items-table .text-right {
            text-align: right;
        }
        .base-service-row {
            background-color: <?php echo $theme === 'dark' ? '#1e40af' : '#eff6ff'; ?>;
        }
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            border: 1px solid <?php echo $theme === 'dark' ? '#6b7280' : '#d1d5db'; ?>;
        }
        .totals-table .total-label {
            font-weight: bold;
            background-color: <?php echo $theme === 'dark' ? '#4b5563' : '#f9fafb'; ?>;
        }
        .totals-table .grand-total {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .terms-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid <?php echo $theme === 'dark' ? '#6b7280' : '#e5e7eb'; ?>;
        }
        .terms-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .terms-list {
            font-size: 12px;
            color: <?php echo $theme === 'dark' ? '#e5e7eb' : '#6b7280'; ?>;
            line-height: 1.8;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: <?php echo $theme === 'dark' ? '#e5e7eb' : '#6b7280'; ?>;
            border-top: 1px solid <?php echo $theme === 'dark' ? '#6b7280' : '#e5e7eb'; ?>;
            padding-top: 20px;
        }
        .preview-banner {
            background-color: <?php echo $theme === 'dark' ? '#92400e' : '#fef3c7'; ?>;
            border: 1px solid <?php echo $theme === 'dark' ? '#d97706' : '#f59e0b'; ?>;
            color: <?php echo $theme === 'dark' ? '#fef3c7' : '#92400e'; ?>;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        @media print {
            .preview-banner { display: none; }
            .preview-container {
                box-shadow: none;
                padding: 15px;
                max-width: none;
                background: white !important;
            }
            body {
                padding: 0;
                background: white !important;
                color: #333 !important;
            }
            .items-table th {
                background-color: #f3f4f6 !important;
                color: #374151 !important;
            }
            .service-description {
                background-color: #f9fafb !important;
            }
            .totals-table .total-label {
                background-color: #f9fafb !important;
            }
            .base-service-row {
                background-color: #eff6ff !important;
            }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-banner">
            QUOTATION PREVIEW - This has not been sent for approval.
        </div>

        <!-- Letterhead -->
        <div class="letterhead">
            <h1 class="company-name">OM ENGINEERS</h1>
            <p class="company-tagline">Professional Vehicle Maintenance & Repair Services</p>
            <div class="company-contact">
                Email: contact@om-engineers.com | Phone: +91-XXXXXXXXXX | Address: [Your Address Here]
            </div>
        </div>

        <!-- Quotation Header -->
        <div class="quotation-header">
            <div class="quotation-info">
                <div class="section-title">Quotation Details</div>
                <div class="info-row">
                    <span class="info-label">Quotation No:</span>
                    <span><?php echo htmlspecialchars($quotation['quotation_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span><?php echo $quotation['formatted_date']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Request ID:</span>
                    <span><?php echo htmlspecialchars($quotation['display_request_id']); ?></span>
                </div>
            </div>

            <div class="customer-info">
                <div class="section-title">Customer Information</div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span><?php echo htmlspecialchars($quotation['requestor_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span><?php echo htmlspecialchars($quotation['requestor_email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle:</span>
                    <span><?php echo htmlspecialchars($quotation['registration_number']); ?></span>
                </div>
            </div>
        </div>

        <!-- Service Description -->
        <div class="service-description">
            <div class="section-title">Service Required</div>
            <p><?php echo htmlspecialchars($quotation['problem_description']); ?></p>
            <div style="margin-top: 15px;">
                <strong>Proposed Solution:</strong><br>
                <?php echo nl2br(htmlspecialchars($quotation['work_description'])); ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Rate (₹)</th>
                    <th class="text-right">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Base Service Charge -->
                <tr class="base-service-row">
                    <td>Base Service Charge</td>
                    <td>Service</td>
                    <td class="text-right">1.00</td>
                    <td class="text-right"><?php echo number_format($quotation['base_service_charge'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($quotation['base_service_charge'], 2); ?></td>
                </tr>

                <!-- Additional Items -->
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo ucfirst($item['item_type']); ?></td>
                    <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($item['rate'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($item['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="total-label">Subtotal:</td>
                    <td class="text-right">₹<?php echo number_format($quotation['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td class="total-label">SGST (<?php echo number_format($quotation['sgst_rate'], 2); ?>%):</td>
                    <td class="text-right">₹<?php echo number_format($quotation['sgst_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="total-label">CGST (<?php echo number_format($quotation['cgst_rate'], 2); ?>%):</td>
                    <td class="text-right">₹<?php echo number_format($quotation['cgst_amount'], 2); ?></td>
                </tr>
                <tr class="grand-total">
                    <td>GRAND TOTAL:</td>
                    <td class="text-right">₹<?php echo number_format($quotation['total_amount'], 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Terms & Conditions -->
        <div class="terms-section">
            <div class="terms-title">Terms & Conditions:</div>
            <div class="terms-list">
                1. This quotation is valid for 30 days from the date of issue.<br>
                2. All prices are inclusive of mentioned taxes.<br>
                3. Payment terms: 50% advance, 50% on completion of work.<br>
                4. Any additional work required will be charged separately.<br>
                5. We provide 30 days warranty on our service work.<br>
                6. Customer is responsible for removing personal belongings from the vehicle.<br>
                7. Om Engineers is not responsible for any damage to aftermarket accessories.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for choosing Om Engineers for your vehicle maintenance needs.</p>
            <p><strong>This is a computer-generated quotation and does not require a signature.</strong></p>
        </div>
    </div>
</body>
</html>
    <?php
    $html = ob_get_clean();
    return $html;
}

function generateQuotationNumber($request_id) {
    // Using request ID based quotation number for traceability
    return 'QT-REQ-' . str_pad($request_id, 4, '0', STR_PAD_LEFT);
}
?>