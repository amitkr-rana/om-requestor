<?php
require_once '../includes/functions.php';
require_once '../includes/quotation_pdf.php';

requireRole('admin');

$action = $_GET['action'] ?? 'preview';
$quotation_id = (int)($_GET['quotation_id'] ?? 0);

if ($quotation_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid quotation ID']);
    exit;
}

// Verify quotation exists and user has access
$quotation = $db->fetch(
    "SELECT q.*, q.organization_id
     FROM quotations_new q
     WHERE q.id = ?",
    [$quotation_id]
);

if (!$quotation) {
    http_response_code(404);
    echo json_encode(['error' => 'Quotation not found']);
    exit;
}

// Organization filtering
if ($_SESSION['organization_id'] != 15 && $quotation['organization_id'] != $_SESSION['organization_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $pdfGenerator = new QuotationPDF();

    switch ($action) {
        case 'preview':
            $pdfGenerator->previewPDF($quotation_id);
            break;
        case 'download':
            $pdfGenerator->downloadPDF($quotation_id);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate PDF: ' . $e->getMessage()]);
}
?>