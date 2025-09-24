<?php
require_once '../includes/functions.php';

requireRole('approver');

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getApprovalDetails();
        break;
    case 'process':
        processApproval();
        break;
    case 'bulk_process':
        bulkProcessApprovals();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getApprovalDetails() {
    global $db;

    $approval_id = (int)($_GET['id'] ?? 0);

    if ($approval_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid approval ID']);
        return;
    }

    // Get approval details with all related information
    $approval = $db->fetch(
        "SELECT a.*, q.amount, q.work_description, q.created_at as quotation_date,
                sr.problem_description, v.registration_number, u.full_name as requestor_name,
                u.email as requestor_email
         FROM approvals a
         JOIN quotations q ON a.quotation_id = q.id
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         WHERE a.id = ? AND a.approver_id = ?",
        [$approval_id, $_SESSION['user_id']]
    );

    if (!$approval) {
        http_response_code(404);
        echo json_encode(['error' => 'Approval not found or access denied']);
        return;
    }

    echo json_encode($approval);
}

function processApproval() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $approval_id = (int)($_POST['approval_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Validation
    if ($approval_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid approval ID']);
        return;
    }

    if (!in_array($status, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }

    // Check if approval exists and belongs to current approver
    $approval = $db->fetch(
        "SELECT a.*, q.request_id, q.status as quotation_status
         FROM approvals a
         JOIN quotations q ON a.quotation_id = q.id
         WHERE a.id = ? AND a.approver_id = ? AND a.status = 'pending'",
        [$approval_id, $_SESSION['user_id']]
    );

    if (!$approval) {
        http_response_code(404);
        echo json_encode(['error' => 'Approval not found, access denied, or already processed']);
        return;
    }

    try {
        // Start transaction
        $db->query("START TRANSACTION");

        // Update approval record
        $result = $db->query(
            "UPDATE approvals SET status = ?, notes = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$status, $notes, $approval_id]
        );

        if (!$result) {
            throw new Exception('Failed to update approval');
        }

        // Update quotation status
        $quotationStatus = $status === 'approved' ? 'approved' : 'rejected';
        $db->query(
            "UPDATE quotations SET status = ? WHERE id = ?",
            [$quotationStatus, $approval['quotation_id']]
        );

        // Update service request status
        $requestStatus = $status === 'approved' ? 'approved' : 'rejected';
        $db->query(
            "UPDATE service_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$requestStatus, $approval['request_id']]
        );

        // If approved, create work order
        if ($status === 'approved') {
            $quotation = $db->fetch("SELECT amount FROM quotations WHERE id = ?", [$approval['quotation_id']]);
            $db->query(
                "INSERT INTO work_orders (quotation_id, bill_amount, payment_status) VALUES (?, ?, 'pending')",
                [$approval['quotation_id'], $quotation['amount']]
            );
        }

        // Commit transaction
        $db->query("COMMIT");

        // TODO: Send email notification to requestor about approval decision
        // This will be implemented when email integration is added

        echo json_encode([
            'success' => true,
            'message' => ucfirst($status) . ' successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $db->query("ROLLBACK");

        http_response_code(500);
        echo json_encode(['error' => 'Failed to process approval: ' . $e->getMessage()]);
    }
}

function bulkProcessApprovals() {
    global $db;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $approval_ids_json = $_POST['approval_ids'] ?? '';
    $status = sanitize($_POST['status'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Validation
    $approval_ids = json_decode($approval_ids_json, true);
    if (!is_array($approval_ids) || empty($approval_ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid approval IDs']);
        return;
    }

    if (!in_array($status, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }

    // Sanitize approval IDs
    $approval_ids = array_map('intval', $approval_ids);
    $approval_ids = array_filter($approval_ids, fn($id) => $id > 0);

    if (empty($approval_ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid approval IDs provided']);
        return;
    }

    // Create placeholders for IN clause
    $placeholders = str_repeat('?,', count($approval_ids) - 1) . '?';

    // Get approvals that belong to current approver and are pending
    $approvals = $db->fetchAll(
        "SELECT a.*, q.request_id, q.amount
         FROM approvals a
         JOIN quotations q ON a.quotation_id = q.id
         WHERE a.id IN ($placeholders) AND a.approver_id = ? AND a.status = 'pending'",
        array_merge($approval_ids, [$_SESSION['user_id']])
    );

    if (empty($approvals)) {
        http_response_code(404);
        echo json_encode(['error' => 'No valid approvals found or all already processed']);
        return;
    }

    try {
        // Start transaction
        $db->query("START TRANSACTION");

        $quotationStatus = $status === 'approved' ? 'approved' : 'rejected';
        $requestStatus = $status === 'approved' ? 'approved' : 'rejected';

        $processedCount = 0;

        foreach ($approvals as $approval) {
            // Update approval record
            $result = $db->query(
                "UPDATE approvals SET status = ?, notes = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$status, $notes, $approval['id']]
            );

            if ($result) {
                // Update quotation status
                $db->query(
                    "UPDATE quotations SET status = ? WHERE id = ?",
                    [$quotationStatus, $approval['quotation_id']]
                );

                // Update service request status
                $db->query(
                    "UPDATE service_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$requestStatus, $approval['request_id']]
                );

                // If approved, create work order
                if ($status === 'approved') {
                    $db->query(
                        "INSERT INTO work_orders (quotation_id, bill_amount, payment_status) VALUES (?, ?, 'pending')",
                        [$approval['quotation_id'], $approval['amount']]
                    );
                }

                $processedCount++;
            }
        }

        // Commit transaction
        $db->query("COMMIT");

        echo json_encode([
            'success' => true,
            'message' => "Successfully processed {$processedCount} approval(s)"
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $db->query("ROLLBACK");

        http_response_code(500);
        echo json_encode(['error' => 'Failed to process bulk approvals: ' . $e->getMessage()]);
    }
}
?>