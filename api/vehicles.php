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
        createVehicle();
        break;
    case 'delete':
        deleteVehicle();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createVehicle() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $registration_number = strtoupper(sanitize($_POST['registration_number'] ?? ''));
    $user_id = $_SESSION['user_id'];

    // Validation
    if (empty($registration_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'Registration number is required']);
        return;
    }

    if (!validateRegistrationNumber($registration_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid registration number format (e.g., DL01AB1234)']);
        return;
    }

    // Check if registration number already exists
    $existingVehicle = $db->fetch(
        "SELECT id FROM vehicles WHERE registration_number = ?",
        [$registration_number]
    );

    if ($existingVehicle) {
        http_response_code(400);
        echo json_encode(['error' => 'Vehicle with this registration number already exists']);
        return;
    }

    // Create vehicle
    $result = $db->query(
        "INSERT INTO vehicles (user_id, registration_number) VALUES (?, ?)",
        [$user_id, $registration_number]
    );

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'vehicle_id' => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add vehicle']);
    }
}

function deleteVehicle() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($vehicle_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid vehicle ID']);
        return;
    }

    // Check if vehicle belongs to current user or if user is admin
    $vehicle = $db->fetch(
        "SELECT * FROM vehicles WHERE id = ? AND (user_id = ? OR ? = 'admin')",
        [$vehicle_id, $user_id, $_SESSION['role']]
    );

    if (!$vehicle) {
        http_response_code(404);
        echo json_encode(['error' => 'Vehicle not found or access denied']);
        return;
    }

    // Check if vehicle has service requests
    $hasRequests = $db->fetch(
        "SELECT COUNT(*) as count FROM service_requests WHERE vehicle_id = ?",
        [$vehicle_id]
    )['count'] > 0;

    if ($hasRequests) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete vehicle with existing service requests']);
        return;
    }

    // Delete vehicle
    $result = $db->query("DELETE FROM vehicles WHERE id = ?", [$vehicle_id]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete vehicle']);
    }
}
?>