<?php
// Start output buffering to prevent any unwanted output
ob_start();

require_once '../includes/functions.php';

requireRole('admin');

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Use new database tables (users_new, organizations_new)
$useNewTables = true;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'get') {
            getUser();
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
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Fatal Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error', 'debug' => $e->getMessage()]);
}

function getUser() {
    global $db;

    $user_id = (int)($_GET['id'] ?? 0);
    error_log("API: Getting user with ID: " . $user_id);

    if ($user_id <= 0) {
        http_response_code(400);
        error_log("API Error: Invalid user ID provided: " . $user_id);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    try {
        $user = $db->fetch(
            "SELECT id, username, email, full_name, role, organization_id, is_active, created_at FROM users_new WHERE id = ?",
            [$user_id]
        );

        if (!$user) {
            http_response_code(404);
            error_log("API Error: User not found with ID: " . $user_id);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        error_log("API Success: User data retrieved for ID: " . $user_id);
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Database Error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}

function createUser() {
    global $db, $useNewTables;

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

    // Get organization_id from form, fallback to admin's organization
    $organization_id = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : (int)$_SESSION['organization_id'];

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        return;
    }

    if (!in_array($role, ['admin', 'requestor', 'approver'])) {
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

    // Validate organization exists (allow 15 for Om Engineers system admin org)
    if ($organization_id < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid organization selected']);
        return;
    }

    // Special handling for Om Engineers (system admin organization)
    if ($organization_id === 15) {
        // Om Engineers is a special system admin organization - always valid
        // Only allow for admin role
        if ($role !== 'admin') {
            http_response_code(400);
            echo json_encode(['error' => 'Om Engineers organization is reserved for system administrators']);
            return;
        }
    } else {
        // Validate regular organizations exist in database
        if ($useNewTables) {
            $organization = $db->fetch("SELECT id FROM organizations_new WHERE id = ?", [$organization_id]);
        } else {
            $organization = $db->fetch("SELECT id FROM organizations WHERE id = ?", [$organization_id]);
        }
        if (!$organization) {
            http_response_code(400);
            echo json_encode(['error' => 'Selected organization does not exist']);
            return;
        }
    }

    // Check if username or email already exists
    if ($useNewTables) {
        $existingUser = $db->fetch(
            "SELECT id FROM users_new WHERE username = ? OR email = ?",
            [$username, $email]
        );
    } else {
        $existingUser = $db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
    }

    if ($existingUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
        return;
    }

    // Create user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($useNewTables) {
        $result = $db->query(
            "INSERT INTO users_new (organization_id, username, email, password, full_name, role)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$organization_id, $username, $email, $hashedPassword, $full_name, $role]
        );
    } else {
        $result = $db->query(
            "INSERT INTO users (organization_id, username, email, password, full_name, role)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$organization_id, $username, $email, $hashedPassword, $full_name, $role]
        );
    }

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
    global $db, $useNewTables;

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
    // Get organization_id from form, or use existing user's organization if not provided
    $organization_id = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : null;

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

    if (!in_array($role, ['admin', 'requestor', 'approver'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role selected']);
        return;
    }

    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }

    // Validate organization exists (if provided)
    if ($organization_id !== null) {
        if ($organization_id === 15) {
            // Special handling for Om Engineers (system admin organization)
            if ($role !== 'admin') {
                http_response_code(400);
                echo json_encode(['error' => 'Om Engineers organization is reserved for system administrators']);
                return;
            }
        } else if ($organization_id > 0) {
            if ($useNewTables) {
                $organization = $db->fetch("SELECT id FROM organizations_new WHERE id = ?", [$organization_id]);
            } else {
                $organization = $db->fetch("SELECT id FROM organizations WHERE id = ?", [$organization_id]);
            }
            if (!$organization) {
                http_response_code(400);
                echo json_encode(['error' => 'Selected organization does not exist']);
                return;
            }
        } else if ($organization_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid organization selected']);
            return;
        }
    }

    // Check if username or email already exists (excluding current user)
    if ($useNewTables) {
        $existingUser = $db->fetch(
            "SELECT id FROM users_new WHERE (username = ? OR email = ?) AND id != ?",
            [$username, $email, $user_id]
        );
    } else {
        $existingUser = $db->fetch(
            "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
            [$username, $email, $user_id]
        );
    }

    if ($existingUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
        return;
    }

    // Update user
    $params = [$username, $email, $full_name, $role];
    $sql = $useNewTables ? "UPDATE users_new SET username = ?, email = ?, full_name = ?, role = ?" : "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?";

    // Update organization if provided (including 15 for Om Engineers)
    if ($organization_id !== null) {
        $sql .= ", organization_id = ?";
        $params[] = $organization_id;
    }

    $params[] = $user_id;

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
    global $db, $useNewTables;

    error_log("API: Delete user request");

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        error_log("API Error: Invalid CSRF token for delete user");
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    error_log("API: Deleting user ID: " . $user_id);

    if ($user_id <= 0) {
        http_response_code(400);
        error_log("API Error: Invalid user ID provided: " . $user_id);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        error_log("API Error: User trying to delete their own account: " . $user_id);
        echo json_encode(['error' => 'Cannot delete your own account']);
        return;
    }

    try {
        // Check if user has associated data
        $vehiclesResult = $db->fetch("SELECT COUNT(*) as count FROM vehicles WHERE user_id = ?", [$user_id]);
        $hasVehicles = $vehiclesResult ? $vehiclesResult['count'] > 0 : false;

        $requestsResult = $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE user_id = ?", [$user_id]);
        $hasRequests = $requestsResult ? $requestsResult['count'] > 0 : false;

        $approvalsResult = $db->fetch("SELECT COUNT(*) as count FROM approvals WHERE approver_id = ?", [$user_id]);
        $hasApprovals = $approvalsResult ? $approvalsResult['count'] > 0 : false;

        if ($hasVehicles || $hasRequests || $hasApprovals) {
            // Soft delete by setting is_active to 0
            error_log("API: Soft deleting user (has associated data): " . $user_id);
            if ($useNewTables) {
                $result = $db->query("UPDATE users_new SET is_active = 0 WHERE id = ?", [$user_id]);
            } else {
                $result = $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$user_id]);
            }
            $message = 'User deactivated successfully (has associated data)';
        } else {
            // Hard delete if no associated data
            error_log("API: Hard deleting user (no associated data): " . $user_id);
            if ($useNewTables) {
                $result = $db->query("DELETE FROM users_new WHERE id = ?", [$user_id]);
            } else {
                $result = $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
            }
            $message = 'User deleted successfully';
        }

        if ($result) {
            error_log("API Success: User deletion completed for ID: " . $user_id);
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } else {
            http_response_code(500);
            error_log("API Error: Database operation failed for user ID: " . $user_id);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Database Error in deleteUser: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}

function toggleUserStatus() {
    global $db, $useNewTables;

    error_log("API: Toggle user status request");

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        error_log("API Error: Invalid CSRF token for toggle status");
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    error_log("API: Toggling status for user ID: " . $user_id);

    if ($user_id <= 0) {
        http_response_code(400);
        error_log("API Error: Invalid user ID provided: " . $user_id);
        echo json_encode(['error' => 'Invalid user ID']);
        return;
    }

    // Prevent admin from deactivating themselves
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        error_log("API Error: User trying to change their own status: " . $user_id);
        echo json_encode(['error' => 'Cannot change your own status']);
        return;
    }

    try {
        // Toggle status
        if ($useNewTables) {
            $result = $db->query(
                "UPDATE users_new SET is_active = NOT is_active WHERE id = ?",
                [$user_id]
            );
        } else {
            $result = $db->query(
                "UPDATE users SET is_active = NOT is_active WHERE id = ?",
                [$user_id]
            );
        }

        if ($result) {
            if ($useNewTables) {
                $user = $db->fetch("SELECT is_active FROM users_new WHERE id = ?", [$user_id]);
            } else {
                $user = $db->fetch("SELECT is_active FROM users WHERE id = ?", [$user_id]);
            }
            $status = $user['is_active'] ? 'activated' : 'deactivated';

            error_log("API Success: User status toggled to: " . $status);
            echo json_encode([
                'success' => true,
                'message' => "User {$status} successfully",
                'is_active' => $user['is_active']
            ]);
        } else {
            http_response_code(500);
            error_log("API Error: Database update failed for user ID: " . $user_id);
            echo json_encode(['error' => 'Failed to update user status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Database Error in toggleUserStatus: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
?>