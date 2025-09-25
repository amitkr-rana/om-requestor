<?php
require_once 'database.php';
require_once 'email.php';

// URL helper functions
function url($path = '') {
    $basePath = getBasePath();
    $cleanPath = ltrim($path, '/');
    return $basePath . ($cleanPath ? '/' . $cleanPath : '');
}

function assetUrl($path) {
    return url('assets/' . ltrim($path, '/'));
}

function apiUrl($endpoint = '') {
    return url('api/' . ltrim($endpoint, '/'));
}

// Authentication functions
function login($username, $password) {
    global $db;

    $user = $db->fetch(
        "SELECT u.*, o.name as organization_name
         FROM users u
         JOIN organizations o ON u.organization_id = o.id
         WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
        [$username, $username]
    );

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Special handling for Om Engineers admin - default to first client organization
        if ($user['organization_id'] == 2 && $user['role'] === 'admin') {
            // Mark as Om Engineers admin for future reference
            $_SESSION['is_om_engineers_admin'] = true;
            $_SESSION['original_organization_id'] = $user['organization_id'];

            // Get first available client organization
            $clientOrg = $db->fetch("SELECT id, name FROM organizations WHERE id != 2 ORDER BY name LIMIT 1");
            if ($clientOrg) {
                $_SESSION['organization_id'] = $clientOrg['id'];
                $_SESSION['organization_name'] = $clientOrg['name'];
            } else {
                // Fallback to Om Engineers if no client orgs exist
                $_SESSION['organization_id'] = $user['organization_id'];
                $_SESSION['organization_name'] = $user['organization_name'];
            }
        } else {
            // Regular users keep their original organization
            $_SESSION['organization_id'] = $user['organization_id'];
            $_SESSION['organization_name'] = $user['organization_name'];
        }

        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        die('Access denied');
    }
}

function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Utility functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}


// Status badge helper
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'quoted' => 'badge-info',
        'approved' => 'badge-success',
        'completed' => 'badge-primary',
        'rejected' => 'badge-danger',
        'sent' => 'badge-info',
        'paid' => 'badge-success'
    ];

    $class = $badges[$status] ?? 'badge-secondary';
    return "<span class='badge {$class}'>" . ucfirst($status) . "</span>";
}

// Pagination helper
function paginate($page, $totalItems, $itemsPerPage = ITEMS_PER_PAGE) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($page - 1) * $itemsPerPage;

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'limit' => $itemsPerPage,
        'total_items' => $totalItems
    ];
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateRegistrationNumber($regNo) {
    return preg_match('/^[A-Z]{2}\d{2}[A-Z]{2}\d{4}$/', $regNo);
}

// Database helper functions
function getUsers($organizationId = null, $role = null) {
    global $db;
    $sql = "SELECT u.*, o.name as organization_name FROM users u JOIN organizations o ON u.organization_id = o.id WHERE 1=1";
    $params = [];

    if ($organizationId) {
        $sql .= " AND u.organization_id = ?";
        $params[] = $organizationId;
    }

    if ($role) {
        $sql .= " AND u.role = ?";
        $params[] = $role;
    }

    $sql .= " ORDER BY u.created_at DESC";
    return $db->fetchAll($sql, $params);
}

function getUserById($id) {
    global $db;
    return $db->fetch(
        "SELECT u.*, o.name as organization_name
         FROM users u
         JOIN organizations o ON u.organization_id = o.id
         WHERE u.id = ?",
        [$id]
    );
}

function getVehiclesByUser($userId) {
    global $db;
    return $db->fetchAll(
        "SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC",
        [$userId]
    );
}

function getServiceRequests($filters = []) {
    global $db;
    $sql = "SELECT sr.*, v.registration_number, u.full_name as requestor_name, o.name as organization_name
            FROM service_requests sr
            JOIN vehicles v ON sr.vehicle_id = v.id
            JOIN users u ON sr.user_id = u.id
            JOIN organizations o ON u.organization_id = o.id
            WHERE 1=1";
    $params = [];

    if (!empty($filters['user_id'])) {
        $sql .= " AND sr.user_id = ?";
        $params[] = $filters['user_id'];
    }

    if (!empty($filters['status'])) {
        $sql .= " AND sr.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND sr.created_at >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND sr.created_at <= ?";
        $params[] = $filters['date_to'];
    }

    $sql .= " ORDER BY sr.created_at DESC";
    return $db->fetchAll($sql, $params);
}

// Organization management functions
function getAllOrganizations() {
    global $db;
    return $db->fetchAll(
        "SELECT id, name, created_at FROM organizations ORDER BY name ASC"
    );
}

function getOrganizationById($id) {
    global $db;
    $id = (int)$id;
    if ($id <= 0) {
        return null;
    }

    $result = $db->fetch(
        "SELECT id, name, created_at FROM organizations WHERE id = ?",
        [$id]
    );

    return $result ?: null;
}
?>