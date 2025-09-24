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
        createServiceRequest();
        break;
    case 'update_status':
        updateRequestStatus();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createServiceRequest() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    // Only requestors can create requests
    if ($_SESSION['role'] !== 'requestor') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $problem_description = sanitize($_POST['problem_description'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Validation
    if ($vehicle_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Please select a vehicle']);
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

    // Check if vehicle belongs to current user
    $vehicle = $db->fetch(
        "SELECT * FROM vehicles WHERE id = ? AND user_id = ?",
        [$vehicle_id, $user_id]
    );

    if (!$vehicle) {
        http_response_code(404);
        echo json_encode(['error' => 'Vehicle not found or access denied']);
        return;
    }

    // Create service request
    $result = $db->query(
        "INSERT INTO service_requests (vehicle_id, user_id, problem_description, status)
         VALUES (?, ?, ?, 'pending')",
        [$vehicle_id, $user_id, $problem_description]
    );

    if ($result) {
        $request_id = $db->lastInsertId();

        // TODO: Send email notification to admin about new request
        // This will be implemented when email integration is added

        echo json_encode([
            'success' => true,
            'message' => 'Service request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create service request']);
    }
}

function updateRequestStatus() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    // Only admin can update request status
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');

    if ($request_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        return;
    }

    $valid_statuses = ['pending', 'quoted', 'approved', 'completed', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }

    // Check if request exists
    $request = $db->fetch(
        "SELECT * FROM service_requests WHERE id = ?",
        [$request_id]
    );

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Service request not found']);
        return;
    }

    // Update request status
    $result = $db->query(
        "UPDATE service_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$status, $request_id]
    );

    if ($result) {
        // TODO: Send email notification to requestor about status change
        // This will be implemented when email integration is added

        echo json_encode([
            'success' => true,
            'message' => 'Request status updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update request status']);
    }
}
?>