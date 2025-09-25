<?php
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'get') {
        getVehicle();
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createVehicle();
        break;
    case 'update':
        updateVehicle();
        break;
    case 'delete':
        deleteVehicle();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getVehicle() {
    global $db;

    $vehicle_id = (int)($_GET['id'] ?? 0);

    if ($vehicle_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid vehicle ID']);
        return;
    }

    // Get vehicle with owner details
    $vehicle = $db->fetch(
        "SELECT v.id, v.registration_number, v.user_id, v.created_at, u.full_name as owner_name, u.username
         FROM vehicles v
         JOIN users u ON v.user_id = u.id
         WHERE v.id = ?",
        [$vehicle_id]
    );

    if (!$vehicle) {
        http_response_code(404);
        echo json_encode(['error' => 'Vehicle not found']);
        return;
    }

    // Check access permissions
    if ($_SESSION['role'] !== 'admin' && $vehicle['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    echo json_encode([
        'success' => true,
        'vehicle' => $vehicle
    ]);
}

function createVehicle() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $registration_number = strtoupper(sanitize($_POST['registration_number'] ?? ''));

    // Allow admins to create vehicles for other users
    if ($_SESSION['role'] === 'admin' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];

        // Verify the user exists and belongs to admin's organization (unless Om Engineers)
        if ($_SESSION['organization_id'] != 2) {
            $userCheck = $db->fetch("SELECT id FROM users WHERE id = ? AND organization_id = ?", [$user_id, $_SESSION['organization_id']]);
            if (!$userCheck) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user selection']);
                return;
            }
        }
    } else {
        $user_id = $_SESSION['user_id'];
    }

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

function updateVehicle() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $registration_number = strtoupper(sanitize($_POST['registration_number'] ?? ''));

    if ($vehicle_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid vehicle ID']);
        return;
    }

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

    // Get current vehicle
    $currentVehicle = $db->fetch(
        "SELECT * FROM vehicles WHERE id = ?",
        [$vehicle_id]
    );

    if (!$currentVehicle) {
        http_response_code(404);
        echo json_encode(['error' => 'Vehicle not found']);
        return;
    }

    // Check permissions
    if ($_SESSION['role'] !== 'admin' && $currentVehicle['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    // Check if new registration number already exists (excluding current vehicle)
    $existingVehicle = $db->fetch(
        "SELECT id FROM vehicles WHERE registration_number = ? AND id != ?",
        [$registration_number, $vehicle_id]
    );

    if ($existingVehicle) {
        http_response_code(400);
        echo json_encode(['error' => 'Vehicle with this registration number already exists']);
        return;
    }

    // Handle user_id for admin users
    $user_id = $currentVehicle['user_id']; // Keep current owner by default
    if ($_SESSION['role'] === 'admin' && isset($_POST['user_id'])) {
        $new_user_id = (int)$_POST['user_id'];

        // Verify the user exists and belongs to admin's organization (unless Om Engineers)
        if ($_SESSION['organization_id'] != 2) {
            $userCheck = $db->fetch("SELECT id FROM users WHERE id = ? AND organization_id = ?", [$new_user_id, $_SESSION['organization_id']]);
            if (!$userCheck) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user selection']);
                return;
            }
        }
        $user_id = $new_user_id;
    }

    // Update vehicle
    $result = $db->query(
        "UPDATE vehicles SET registration_number = ?, user_id = ? WHERE id = ?",
        [$registration_number, $user_id, $vehicle_id]
    );

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update vehicle']);
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