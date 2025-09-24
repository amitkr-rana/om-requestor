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
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'toggle_status':
        toggleUserStatus();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function createUser() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $organization_id = (int)$_SESSION['organization_id'];

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        return;
    }

    if (!in_array($role, ['requestor', 'approver'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role selected']);
        return;
    }

    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters long']);
        return;
    }

    // Check if username or email already exists
    $existingUser = $db->fetch(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );

    if ($existingUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
        return;
    }

    // Create user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $result = $db->query(
        "INSERT INTO users (organization_id, username, email, password, full_name, role)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$organization_id, $username, $email, $hashedPassword, $full_name, $role]
    );

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user']);
    }
}

function updateUser() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($role)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        return;
    }

    if (!in_array($role, ['requestor', 'approver'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role selected']);
        return;
    }

    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }

    // Check if username or email already exists (excluding current user)
    $existingUser = $db->fetch(
        "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
        [$username, $email, $user_id]
    );

    if ($existingUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
        return;
    }

    // Update user
    $params = [$username, $email, $full_name, $role, $user_id];
    $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?";

    // Update password if provided
    if (!empty($password)) {
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters long']);
            return;
        }
        $sql .= ", password = ?";
        array_splice($params, -1, 0, [password_hash($password, PASSWORD_DEFAULT)]);
    }

    $sql .= " WHERE id = ?";

    $result = $db->query($sql, $params);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user']);
    }
}

function deleteUser() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete your own account']);
        return;
    }

    // Check if user has associated data
    $hasVehicles = $db->fetch("SELECT COUNT(*) as count FROM vehicles WHERE user_id = ?", [$user_id])['count'] > 0;
    $hasRequests = $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE user_id = ?", [$user_id])['count'] > 0;
    $hasApprovals = $db->fetch("SELECT COUNT(*) as count FROM approvals WHERE approver_id = ?", [$user_id])['count'] > 0;

    if ($hasVehicles || $hasRequests || $hasApprovals) {
        // Soft delete by setting is_active to 0
        $result = $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$user_id]);
        $message = 'User deactivated successfully (has associated data)';
    } else {
        // Hard delete if no associated data
        $result = $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
        $message = 'User deleted successfully';
    }

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user']);
    }
}

function toggleUserStatus() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    // Prevent admin from deactivating themselves
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot change your own status']);
        return;
    }

    // Toggle status
    $result = $db->query(
        "UPDATE users SET is_active = NOT is_active WHERE id = ?",
        [$user_id]
    );

    if ($result) {
        $user = $db->fetch("SELECT is_active FROM users WHERE id = ?", [$user_id]);
        $status = $user['is_active'] ? 'activated' : 'deactivated';

        echo json_encode([
            'success' => true,
            'message' => "User {$status} successfully",
            'is_active' => $user['is_active']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user status']);
    }
}
?>