<?php
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createQuotation();
        break;
    case 'update':
        updateQuotation();
        break;
    case 'update_status':
        updateQuotationStatus();
        break;
    case 'approve':
        approveQuotation();
        break;
    case 'reject':
        rejectQuotation();
        break;
    case 'assign_technician':
        assignTechnician();
        break;
    case 'start_repair':
        startRepair();
        break;
    case 'complete_repair':
        completeRepair();
        break;
    case 'generate_bill':
        generateBill();
        break;
    case 'record_payment':
        recordPayment();
        break;
    case 'allocate_inventory':
        allocateInventory();
        break;
    case 'consume_inventory':
        consumeInventory();
        break;
    case 'get_history':
        getQuotationHistory();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createQuotation() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    // Validate user permissions
    if (!in_array($_SESSION['role'], ['admin', 'requestor'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Extract and validate input
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_email = sanitize($_POST['customer_email'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $vehicle_registration = sanitize($_POST['vehicle_registration'] ?? '');
    $vehicle_make_model = sanitize($_POST['vehicle_make_model'] ?? '');
    $problem_description = sanitize($_POST['problem_description'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');

    // Validation
    if (empty($customer_name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer name is required']);
        return;
    }

    if (empty($vehicle_registration)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vehicle registration is required']);
        return;
    }

    if (empty($problem_description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Problem description is required']);
        return;
    }

    if (strlen($problem_description) < 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Please provide a detailed problem description (minimum 10 characters)']);
        return;
    }

    try {
        $db->beginTransaction();

        // Generate quotation number
        $quotation_number = generateQuotationNumber($_SESSION['organization_id']);

        // Create quotation
        $result = $db->query(
            "INSERT INTO quotations_new
            (quotation_number, organization_id, customer_name, customer_email, customer_phone,
             vehicle_registration, vehicle_make_model, problem_description, priority,
             status, created_by, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 0.00)",
            [
                $quotation_number,
                $_SESSION['organization_id'],
                $customer_name,
                $customer_email,
                $customer_phone,
                $vehicle_registration,
                $vehicle_make_model,
                $problem_description,
                $priority,
                $_SESSION['user_id']
            ]
        );

        if (!$result) {
            throw new Exception('Failed to create quotation');
        }

        $quotation_id = $db->lastInsertId();

        // Log the creation
        logQuotationHistory($quotation_id, $_SESSION['user_id'], 'created', null, null, 'Quotation created');
        logStatusChange($quotation_id, null, 'pending', $_SESSION['user_id'], 'Initial quotation created');
        logUserActivity($_SESSION['user_id'], 'quotation_created', 'quotation', $quotation_id,
                       "Created quotation $quotation_number for $customer_name");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Quotation created successfully',
            'quotation_id' => $quotation_id,
            'quotation_number' => $quotation_number
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create quotation: ' . $e->getMessage()]);
    }
}

function updateQuotation() {
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

    // Get existing quotation
    $quotation = $db->fetch(
        "SELECT * FROM quotations_new WHERE id = ? AND organization_id = ?",
        [$quotation_id, $_SESSION['organization_id']]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    // Check if user can edit
    if ($_SESSION['role'] !== 'admin' && $quotation['created_by'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Don't allow editing if already approved/completed/billed
    if (in_array($quotation['status'], ['approved', 'repair_complete', 'bill_generated', 'paid'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot edit quotation in current status']);
        return;
    }

    try {
        $db->beginTransaction();

        // Build update query dynamically
        $updates = [];
        $params = [];
        $changes = [];

        $fields_to_update = [
            'customer_name', 'customer_email', 'customer_phone',
            'vehicle_registration', 'vehicle_make_model', 'problem_description',
            'work_description', 'service_notes', 'priority', 'total_amount',
            'base_service_charge', 'parts_total', 'subtotal', 'tax_rate',
            'tax_amount', 'estimated_completion_date'
        ];

        foreach ($fields_to_update as $field) {
            if (isset($_POST[$field])) {
                $new_value = $field === 'total_amount' || $field === 'base_service_charge' ||
                           $field === 'parts_total' || $field === 'subtotal' || $field === 'tax_amount'
                           ? (float)$_POST[$field] : sanitize($_POST[$field]);

                if ($quotation[$field] !== $new_value) {
                    $updates[] = "`$field` = ?";
                    $params[] = $new_value;
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $quotation[$field],
                        'new_value' => $new_value
                    ];
                }
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE quotations_new SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $quotation_id;

            $result = $db->query($sql, $params);

            if (!$result) {
                throw new Exception('Failed to update quotation');
            }

            // Log each change
            foreach ($changes as $change) {
                logQuotationHistory($quotation_id, $_SESSION['user_id'], 'updated',
                                 $change['field'], $change['old_value'], $change['new_value']);
            }

            logUserActivity($_SESSION['user_id'], 'quotation_updated', 'quotation', $quotation_id,
                           "Updated quotation {$quotation['quotation_number']}");
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Quotation updated successfully',
            'changes_count' => count($changes)
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update quotation: ' . $e->getMessage()]);
    }
}

function updateQuotationStatus() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $new_status = sanitize($_POST['status'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    $valid_statuses = ['pending', 'sent', 'approved', 'rejected', 'repair_in_progress',
                      'repair_complete', 'bill_generated', 'paid', 'cancelled'];

    if (!in_array($new_status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }

    // Get current quotation
    $quotation = $db->fetch(
        "SELECT * FROM quotations_new WHERE id = ? AND organization_id = ?",
        [$quotation_id, $_SESSION['organization_id']]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    if ($quotation['status'] === $new_status) {
        echo json_encode(['success' => true, 'message' => 'Status already up to date']);
        return;
    }

    try {
        $db->beginTransaction();

        $old_status = $quotation['status'];

        // Update status and timestamp
        $timestamp_field = null;
        $timestamp_value = 'CURRENT_TIMESTAMP';

        switch ($new_status) {
            case 'sent':
                $timestamp_field = 'sent_at';
                break;
            case 'approved':
                $timestamp_field = 'approved_at';
                break;
            case 'rejected':
                $timestamp_field = 'rejected_at';
                break;
            case 'repair_in_progress':
                $timestamp_field = 'repair_started_at';
                break;
            case 'repair_complete':
                $timestamp_field = 'repair_completed_at';
                break;
            case 'bill_generated':
                $timestamp_field = 'bill_generated_at';
                break;
            case 'paid':
                $timestamp_field = 'paid_at';
                break;
        }

        $sql = "UPDATE quotations_new SET status = ?";
        $params = [$new_status];

        if ($timestamp_field) {
            $sql .= ", $timestamp_field = CURRENT_TIMESTAMP";
        }

        $sql .= " WHERE id = ?";
        $params[] = $quotation_id;

        $result = $db->query($sql, $params);

        if (!$result) {
            throw new Exception('Failed to update quotation status');
        }

        // Log the status change
        logStatusChange($quotation_id, $old_status, $new_status, $_SESSION['user_id'], $notes);
        logQuotationHistory($quotation_id, $_SESSION['user_id'], 'status_changed', 'status',
                          $old_status, $new_status);
        logUserActivity($_SESSION['user_id'], 'status_changed', 'quotation', $quotation_id,
                       "Changed status from $old_status to $new_status");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status: ' . $e->getMessage()]);
    }
}

function approveQuotation() {
    global $db;

    if (!in_array($_SESSION['role'], ['admin', 'approver'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $approval_notes = sanitize($_POST['approval_notes'] ?? '');

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    try {
        $db->beginTransaction();

        $result = $db->query(
            "UPDATE quotations_new
             SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP,
                 approval_notes = ?
             WHERE id = ? AND organization_id = ? AND status = 'sent'",
            [$_SESSION['user_id'], $approval_notes, $quotation_id, $_SESSION['organization_id']]
        );

        if ($db->rowCount("SELECT 1", []) === 0) {
            throw new Exception('Quotation not found or not in correct status for approval');
        }

        logStatusChange($quotation_id, 'sent', 'approved', $_SESSION['user_id'], $approval_notes);
        logQuotationHistory($quotation_id, $_SESSION['user_id'], 'approved', null, null, $approval_notes);
        logUserActivity($_SESSION['user_id'], 'quotation_approved', 'quotation', $quotation_id,
                       'Approved quotation');

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Quotation approved successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to approve quotation: ' . $e->getMessage()]);
    }
}

function rejectQuotation() {
    global $db;

    if (!in_array($_SESSION['role'], ['admin', 'approver'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    if (empty($rejection_reason)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rejection reason is required']);
        return;
    }

    try {
        $db->beginTransaction();

        $result = $db->query(
            "UPDATE quotations_new
             SET status = 'rejected', approved_by = ?, rejected_at = CURRENT_TIMESTAMP,
                 rejection_reason = ?
             WHERE id = ? AND organization_id = ? AND status = 'sent'",
            [$_SESSION['user_id'], $rejection_reason, $quotation_id, $_SESSION['organization_id']]
        );

        if ($db->rowCount("SELECT 1", []) === 0) {
            throw new Exception('Quotation not found or not in correct status for rejection');
        }

        logStatusChange($quotation_id, 'sent', 'rejected', $_SESSION['user_id'], $rejection_reason);
        logQuotationHistory($quotation_id, $_SESSION['user_id'], 'rejected', null, null, $rejection_reason);
        logUserActivity($_SESSION['user_id'], 'quotation_rejected', 'quotation', $quotation_id,
                       'Rejected quotation');

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Quotation rejected successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reject quotation: ' . $e->getMessage()]);
    }
}

// Helper functions
function generateQuotationNumber($organization_id) {
    global $db;

    $year = date('Y');

    // Get or create sequence
    $result = $db->query(
        "INSERT INTO quotation_sequence (organization_id, year, last_number)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = last_number + 1",
        [$organization_id, $year]
    );

    $sequence = $db->fetch(
        "SELECT last_number, prefix FROM quotation_sequence
         WHERE organization_id = ? AND year = ?",
        [$organization_id, $year]
    );

    $prefix = $sequence['prefix'] ?: 'QT';
    return sprintf('%s-%d-%04d', $prefix, $year, $sequence['last_number']);
}

function logQuotationHistory($quotation_id, $user_id, $action, $field_name = null, $old_value = null, $new_value = null) {
    global $db;

    $db->query(
        "INSERT INTO quotation_history
         (quotation_id, changed_by, action, field_name, old_value, new_value,
          ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $quotation_id, $user_id, $action, $field_name, $old_value, $new_value,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
}

function logStatusChange($quotation_id, $from_status, $to_status, $user_id, $notes = null) {
    global $db;

    $db->query(
        "INSERT INTO quotation_status_log
         (quotation_id, from_status, to_status, changed_by, notes)
         VALUES (?, ?, ?, ?, ?)",
        [$quotation_id, $from_status, $to_status, $user_id, $notes]
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

function getQuotationHistory() {
    global $db;

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    // Verify access to quotation
    $quotation = $db->fetch(
        "SELECT * FROM quotations_new WHERE id = ? AND organization_id = ?",
        [$quotation_id, $_SESSION['organization_id']]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    $history = $db->fetchAll(
        "SELECT qh.*, u.full_name as changed_by_name
         FROM quotation_history qh
         JOIN users_new u ON qh.changed_by = u.id
         WHERE qh.quotation_id = ?
         ORDER BY qh.created_at DESC",
        [$quotation_id]
    );

    $status_log = $db->fetchAll(
        "SELECT qsl.*, u.full_name as changed_by_name
         FROM quotation_status_log qsl
         JOIN users_new u ON qsl.changed_by = u.id
         WHERE qsl.quotation_id = ?
         ORDER BY qsl.created_at DESC",
        [$quotation_id]
    );

    echo json_encode([
        'success' => true,
        'history' => $history,
        'status_log' => $status_log
    ]);
}
?>