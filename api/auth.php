<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Allow GET requests for logout, check_session
$allowed_get_actions = ['logout', 'check_session'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($action, $allowed_get_actions)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

switch ($action) {
    case 'login':
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid security token']);
            exit;
        }

        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password are required']);
            exit;
        }

        if (login($username, $password)) {
            echo json_encode([
                'success' => true,
                'role' => $_SESSION['role'],
                'redirect' => getDashboardUrl($_SESSION['role'])
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        break;

    case 'logout':
        logout();
        echo json_encode(['success' => true]);
        break;

    case 'check_session':
        if (isLoggedIn()) {
            echo json_encode([
                'logged_in' => true,
                'role' => $_SESSION['role'],
                'username' => $_SESSION['username']
            ]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return url('dashboard/admin_new.php');
        case 'requestor':
            return url('dashboard/quotation_manager.php');
        case 'approver':
            return url('dashboard/approver.php');
        default:
            return url('index.php');
    }
}
?>