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
    case 'switch_organization':
        switchOrganization();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function switchOrganization() {
    global $db;

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $organization_id = (int)($_POST['organization_id'] ?? 0);

    if ($organization_id < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid organization ID']);
        return;
    }

    // Validate organization exists or is Om Engineers (ID = 2)
    if ($organization_id === 2) {
        // Om Engineers is always valid for admins
        $organization_name = 'Om Engineers';
    } else {
        $organization = $db->fetch("SELECT id, name FROM organizations WHERE id = ?", [$organization_id]);
        if (!$organization) {
            http_response_code(400);
            echo json_encode(['error' => 'Organization not found']);
            return;
        }
        $organization_name = $organization['name'];
    }

    // Update session
    $_SESSION['organization_id'] = $organization_id;
    $_SESSION['organization_name'] = $organization_name;

    echo json_encode([
        'success' => true,
        'message' => "Switched to {$organization_name}",
        'organization_id' => $organization_id,
        'organization_name' => $organization_name
    ]);
}
?>