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

    // Use new database structure
    $user = $db->fetch(
        "SELECT u.*, o.name as organization_name
         FROM users_new u
         JOIN organizations_new o ON u.organization_id = o.id
         WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
        [$username, $username]
    );

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Special handling for Om Engineers admin - default to first client organization
        if ($user['organization_id'] == 15 && $user['role'] === 'admin') {
            // Mark as Om Engineers admin for future reference
            $_SESSION['is_om_engineers_admin'] = true;
            $_SESSION['original_organization_id'] = $user['organization_id'];

            // Get first available client organization
            $clientOrg = $db->fetch("SELECT id, name FROM organizations_new WHERE id != 15 ORDER BY name LIMIT 1");

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

// ============================================================================
// HISTORY TRACKING FUNCTIONS
// ============================================================================

/**
 * Log quotation history changes
 */
function logQuotationHistory($quotation_id, $user_id, $action, $field_name = null, $old_value = null, $new_value = null, $change_reason = null) {
    global $db;

    try {
        $db->query(
            "INSERT INTO quotation_history
             (quotation_id, changed_by, action, field_name, old_value, new_value, change_reason,
              ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $quotation_id, $user_id, $action, $field_name, $old_value, $new_value, $change_reason,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log quotation history: " . $e->getMessage());
    }
}

/**
 * Log quotation status changes
 */
function logQuotationStatusChange($quotation_id, $from_status, $to_status, $user_id, $notes = null) {
    global $db;

    try {
        // Calculate duration in previous status if we have from_status
        $duration = null;
        if ($from_status) {
            $last_change = $db->fetch(
                "SELECT created_at FROM quotation_status_log
                 WHERE quotation_id = ? AND to_status = ?
                 ORDER BY created_at DESC LIMIT 1",
                [$quotation_id, $from_status]
            );

            if ($last_change) {
                $duration = (strtotime('now') - strtotime($last_change['created_at'])) / 60; // minutes
            }
        }

        $db->query(
            "INSERT INTO quotation_status_log
             (quotation_id, from_status, to_status, changed_by, notes, duration_in_status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$quotation_id, $from_status, $to_status, $user_id, $notes, $duration]
        );
    } catch (Exception $e) {
        error_log("Failed to log status change: " . $e->getMessage());
    }
}

/**
 * Log user activity across the system
 */
function logUserActivity($user_id, $action, $entity_type = null, $entity_id = null, $description = null, $metadata = null) {
    global $db;

    if (!isset($_SESSION['organization_id'])) {
        return;
    }

    try {
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
    } catch (Exception $e) {
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}

/**
 * Log inventory transactions
 */
function logInventoryTransaction($item_id, $type, $reference_type = null, $reference_id = null,
                               $quantity = 0, $unit_cost = null, $total_cost = null, $notes = null,
                               $running_balance = null, $supplier_id = null) {
    global $db;

    if (!isset($_SESSION['organization_id']) || !isset($_SESSION['user_id'])) {
        return;
    }

    try {
        if ($running_balance === null) {
            $item = $db->fetch("SELECT current_stock FROM inventory WHERE id = ?", [$item_id]);
            $running_balance = $item ? $item['current_stock'] : 0;
        }

        $db->query(
            "INSERT INTO inventory_transactions
             (organization_id, inventory_item_id, transaction_type, reference_type, reference_id,
              quantity, unit_cost, total_cost, running_balance, notes, performed_by, supplier_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['organization_id'], $item_id, $type, $reference_type, $reference_id,
                $quantity, $unit_cost, $total_cost, $running_balance, $notes, $_SESSION['user_id'], $supplier_id
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log inventory transaction: " . $e->getMessage());
    }
}

/**
 * Get quotation history with user details
 */
function getQuotationHistory($quotation_id, $limit = 50) {
    global $db;

    return $db->fetchAll(
        "SELECT qh.*, u.full_name as changed_by_name
         FROM quotation_history qh
         LEFT JOIN users_new u ON qh.changed_by = u.id
         WHERE qh.quotation_id = ?
         ORDER BY qh.created_at DESC
         LIMIT ?",
        [$quotation_id, $limit]
    );
}

/**
 * Get quotation status log with user details
 */
function getQuotationStatusLog($quotation_id, $limit = 20) {
    global $db;

    return $db->fetchAll(
        "SELECT qsl.*, u.full_name as changed_by_name
         FROM quotation_status_log qsl
         LEFT JOIN users_new u ON qsl.changed_by = u.id
         WHERE qsl.quotation_id = ?
         ORDER BY qsl.created_at DESC
         LIMIT ?",
        [$quotation_id, $limit]
    );
}

/**
 * Get user activity log
 */
function getUserActivityLog($user_id = null, $entity_type = null, $limit = 100) {
    global $db;

    $sql = "SELECT ual.*, u.full_name as user_name
            FROM user_activity_log ual
            LEFT JOIN users_new u ON ual.user_id = u.id
            WHERE ual.organization_id = ?";
    $params = [$_SESSION['organization_id']];

    if ($user_id) {
        $sql .= " AND ual.user_id = ?";
        $params[] = $user_id;
    }

    if ($entity_type) {
        $sql .= " AND ual.entity_type = ?";
        $params[] = $entity_type;
    }

    $sql .= " ORDER BY ual.created_at DESC LIMIT ?";
    $params[] = $limit;

    return $db->fetchAll($sql, $params);
}

/**
 * Get inventory transaction history
 */
function getInventoryHistory($item_id = null, $limit = 50) {
    global $db;

    $sql = "SELECT it.*, i.name as item_name, i.item_code, u.full_name as performed_by_name
            FROM inventory_transactions it
            LEFT JOIN inventory i ON it.inventory_item_id = i.id
            LEFT JOIN users_new u ON it.performed_by = u.id
            WHERE it.organization_id = ?";
    $params = [$_SESSION['organization_id']];

    if ($item_id) {
        $sql .= " AND it.inventory_item_id = ?";
        $params[] = $item_id;
    }

    $sql .= " ORDER BY it.created_at DESC LIMIT ?";
    $params[] = $limit;

    return $db->fetchAll($sql, $params);
}

// ============================================================================
// ENHANCED UTILITY FUNCTIONS
// ============================================================================

/**
 * Generate unique quotation number
 */
function generateQuotationNumber($organization_id) {
    global $db;

    $year = date('Y');

    try {
        // Get or create sequence
        $db->query(
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
    } catch (Exception $e) {
        error_log("Failed to generate quotation number: " . $e->getMessage());
        return 'QT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Generate unique bill number
 */
function generateBillNumber($organization_id) {
    global $db;

    $year = date('Y');

    try {
        // Get or create sequence
        $db->query(
            "INSERT INTO bill_sequence (organization_id, year, last_number)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE last_number = last_number + 1",
            [$organization_id, $year]
        );

        $sequence = $db->fetch(
            "SELECT last_number, prefix FROM bill_sequence
             WHERE organization_id = ? AND year = ?",
            [$organization_id, $year]
        );

        $prefix = $sequence['prefix'] ?: 'INV';
        return sprintf('%s-%d-%04d', $prefix, $year, $sequence['last_number']);
    } catch (Exception $e) {
        error_log("Failed to generate bill number: " . $e->getMessage());
        return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Check if new database tables exist
 */
function useNewDatabase() {
    global $db;

    try {
        // Check if the new quotations table exists
        $result = $db->fetchAll("SHOW TABLES LIKE 'quotations_new'");
        return !empty($result);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get quotations using appropriate table
 */
function getQuotationsForUser($user_id, $role, $organization_id, $limit = 50) {
    global $db;

    // Use new database structure
    if ($role === 'requestor') {
        return $db->fetchAll(
            "SELECT q.*, u.full_name as created_by_name, a.full_name as approved_by_name
             FROM quotations_new q
             LEFT JOIN users_new u ON q.created_by = u.id
             LEFT JOIN users_new a ON q.approved_by = a.id
             WHERE q.created_by = ? AND q.organization_id = ?
             ORDER BY q.created_at DESC
             LIMIT ?",
            [$user_id, $organization_id, $limit]
        );
    } else {
        return $db->fetchAll(
            "SELECT q.*, u.full_name as created_by_name, a.full_name as approved_by_name, t.full_name as assigned_to_name
             FROM quotations_new q
             LEFT JOIN users_new u ON q.created_by = u.id
             LEFT JOIN users_new a ON q.approved_by = a.id
             LEFT JOIN users_new t ON q.assigned_to = t.id
             WHERE q.organization_id = ?
             ORDER BY q.created_at DESC
             LIMIT ?",
            [$organization_id, $limit]
        );
    }
}

/**
 * Enhanced status badge with new statuses
 */
function getEnhancedStatusBadge($status) {
    $badges = [
        // Original statuses
        'pending' => ['class' => 'badge-warning', 'label' => 'Pending'],
        'quoted' => ['class' => 'badge-info', 'label' => 'Quoted'],
        'approved' => ['class' => 'badge-success', 'label' => 'Approved'],
        'completed' => ['class' => 'badge-primary', 'label' => 'Completed'],
        'rejected' => ['class' => 'badge-danger', 'label' => 'Rejected'],

        // New enhanced statuses
        'sent' => ['class' => 'badge-info', 'label' => 'Sent for Approval'],
        'repair_in_progress' => ['class' => 'badge-warning', 'label' => 'Repair in Progress'],
        'repair_complete' => ['class' => 'badge-success', 'label' => 'Repair Complete'],
        'bill_generated' => ['class' => 'badge-warning', 'label' => 'Bill Generated'],
        'paid' => ['class' => 'badge-success', 'label' => 'Paid'],
        'cancelled' => ['class' => 'badge-secondary', 'label' => 'Cancelled'],

        // Payment and billing
        'unpaid' => ['class' => 'badge-danger', 'label' => 'Unpaid'],
        'partial' => ['class' => 'badge-warning', 'label' => 'Partial'],
        'overdue' => ['class' => 'badge-danger', 'label' => 'Overdue']
    ];

    $badge = $badges[$status] ?? ['class' => 'badge-secondary', 'label' => ucfirst($status)];
    return "<span class='badge {$badge['class']}'>{$badge['label']}</span>";
}

/**
 * Format currency with organization preferences
 */
function formatCurrencyAdvanced($amount, $currency = 'INR') {
    $symbols = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];

    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, 2);
}

/**
 * Log login activity
 */
function logLoginActivity($user_id, $success = true, $ip_address = null, $user_agent = null) {
    global $db;

    try {
        $db->query(
            "INSERT INTO user_activity_log
             (user_id, organization_id, action, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $user_id,
                $_SESSION['organization_id'] ?? 0,
                $success ? 'login_success' : 'login_failed',
                $success ? 'User logged in successfully' : 'Failed login attempt',
                $ip_address ?: $_SERVER['REMOTE_ADDR'],
                $user_agent ?: $_SERVER['HTTP_USER_AGENT']
            ]
        );

        // Update last login time for successful logins
        if ($success) {
            $db->query(
                "UPDATE users_new SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$user_id]
            );
        }
    } catch (Exception $e) {
        error_log("Failed to log login activity: " . $e->getMessage());
    }
}
?>