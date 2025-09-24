<?php
require_once '../includes/functions.php';

requireRole('admin');

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
    case 'send_to_approver':
        sendQuotationToApprover();
        break;
    case 'update':
        updateQuotation();
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

    $request_id = (int)($_POST['request_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $work_description = sanitize($_POST['work_description'] ?? '');
    $send_to_approver = (int)($_POST['send_to_approver'] ?? 0);

    // Validation
    if ($request_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        return;
    }

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Amount must be greater than zero']);
        return;
    }

    if (empty($work_description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Work description is required']);
        return;
    }

    // Check if service request exists and is pending
    $request = $db->fetch(
        "SELECT sr.*, v.registration_number, u.full_name as requestor_name, u.organization_id
         FROM service_requests sr
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         WHERE sr.id = ? AND sr.status = 'pending'",
        [$request_id]
    );

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Service request not found or already processed']);
        return;
    }

    // Check if quotation already exists for this request
    $existingQuotation = $db->fetch(
        "SELECT id FROM quotations WHERE request_id = ?",
        [$request_id]
    );

    if ($existingQuotation) {
        http_response_code(400);
        echo json_encode(['error' => 'Quotation already exists for this request']);
        return;
    }

    // Create quotation
    $result = $db->query(
        "INSERT INTO quotations (request_id, amount, work_description, status)
         VALUES (?, ?, ?, 'sent')",
        [$request_id, $amount, $work_description]
    );

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create quotation']);
        return;
    }

    $quotation_id = $db->lastInsertId();

    // Update service request status
    $db->query(
        "UPDATE service_requests SET status = 'quoted', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$request_id]
    );

    // If send to approver is requested, create approval record
    if ($send_to_approver > 0) {
        // Verify approver exists and belongs to same organization
        $approver = $db->fetch(
            "SELECT * FROM users WHERE id = ? AND role = 'approver' AND organization_id = ? AND is_active = 1",
            [$send_to_approver, $request['organization_id']]
        );

        if ($approver) {
            $db->query(
                "INSERT INTO approvals (quotation_id, approver_id, status)
                 VALUES (?, ?, 'pending')",
                [$quotation_id, $send_to_approver]
            );

            // Update quotation sent_at timestamp
            $db->query(
                "UPDATE quotations SET sent_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$quotation_id]
            );

            // TODO: Send email notification to approver
            // This will be implemented when email integration is added

            $message = 'Quotation created and sent to approver successfully';
        } else {
            $message = 'Quotation created successfully, but approver not found';
        }
    } else {
        $message = 'Quotation created successfully';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'quotation_id' => $quotation_id
    ]);
}

function sendQuotationToApprover() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $approver_id = (int)($_POST['approver_id'] ?? 0);

    if ($quotation_id <= 0 || $approver_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID or approver ID']);
        return;
    }

    // Get quotation with request details
    $quotation = $db->fetch(
        "SELECT q.*, sr.user_id, u.organization_id
         FROM quotations q
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN users u ON sr.user_id = u.id
         WHERE q.id = ?",
        [$quotation_id]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    // Verify approver exists and belongs to same organization
    $approver = $db->fetch(
        "SELECT * FROM users WHERE id = ? AND role = 'approver' AND organization_id = ? AND is_active = 1",
        [$approver_id, $quotation['organization_id']]
    );

    if (!$approver) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid approver or approver not in same organization']);
        return;
    }

    // Check if approval already exists
    $existingApproval = $db->fetch(
        "SELECT id FROM approvals WHERE quotation_id = ?",
        [$quotation_id]
    );

    if ($existingApproval) {
        http_response_code(400);
        echo json_encode(['error' => 'Quotation already sent to approver']);
        return;
    }

    // Create approval record
    $result = $db->query(
        "INSERT INTO approvals (quotation_id, approver_id, status)
         VALUES (?, ?, 'pending')",
        [$quotation_id, $approver_id]
    );

    if ($result) {
        // Update quotation sent_at timestamp
        $db->query(
            "UPDATE quotations SET sent_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$quotation_id]
        );

        // TODO: Send email notification to approver
        // This will be implemented when email integration is added

        echo json_encode([
            'success' => true,
            'message' => 'Quotation sent to approver successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send quotation to approver']);
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
    $amount = (float)($_POST['amount'] ?? 0);
    $work_description = sanitize($_POST['work_description'] ?? '');

    // Validation
    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Amount must be greater than zero']);
        return;
    }

    if (empty($work_description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Work description is required']);
        return;
    }

    // Check if quotation exists and is not yet approved
    $quotation = $db->fetch(
        "SELECT q.*, a.status as approval_status
         FROM quotations q
         LEFT JOIN approvals a ON q.id = a.quotation_id
         WHERE q.id = ?",
        [$quotation_id]
    );

    if (!$quotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Quotation not found']);
        return;
    }

    if ($quotation['approval_status'] === 'approved') {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot update approved quotation']);
        return;
    }

    // Update quotation
    $result = $db->query(
        "UPDATE quotations SET amount = ?, work_description = ? WHERE id = ?",
        [$amount, $work_description, $quotation_id]
    );

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Quotation updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update quotation']);
    }
}
?>