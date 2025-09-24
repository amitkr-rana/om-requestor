<?php
require_once '../includes/functions.php';

// Simple PDF generation class without external dependencies
class SimplePDF {
    private $content = '';
    private $title = '';
    private $filename = '';

    public function __construct($title = 'Report', $filename = 'report.pdf') {
        $this->title = $title;
        $this->filename = $filename;
    }

    public function addHeader($text, $level = 1) {
        $fontSize = 18 - ($level * 2);
        $this->content .= "<h{$level} style='font-size: {$fontSize}px; margin: 20px 0 10px 0; color: #333;'>{$text}</h{$level}>";
    }

    public function addText($text) {
        $this->content .= "<p style='margin: 10px 0; line-height: 1.5;'>{$text}</p>";
    }

    public function addTable($data, $headers = []) {
        $this->content .= "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";

        if (!empty($headers)) {
            $this->content .= "<thead><tr style='background-color: #f5f5f5;'>";
            foreach ($headers as $header) {
                $this->content .= "<th style='border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold;'>{$header}</th>";
            }
            $this->content .= "</tr></thead>";
        }

        $this->content .= "<tbody>";
        foreach ($data as $row) {
            $this->content .= "<tr>";
            foreach ($row as $cell) {
                $this->content .= "<td style='border: 1px solid #ddd; padding: 12px;'>{$cell}</td>";
            }
            $this->content .= "</tr>";
        }
        $this->content .= "</tbody></table>";
    }

    public function addSummary($summaryData) {
        $this->content .= "<div style='background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;'>";
        $this->content .= "<h3 style='margin: 0 0 15px 0; color: #333;'>Summary</h3>";
        foreach ($summaryData as $label => $value) {
            $this->content .= "<p style='margin: 5px 0;'><strong>{$label}:</strong> {$value}</p>";
        }
        $this->content .= "</div>";
    }

    public function output() {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>{$this->title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                .header { text-align: center; border-bottom: 2px solid #1976D2; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #1976D2; }
                .report-title { font-size: 20px; margin: 10px 0; }
                .report-date { color: #666; font-size: 14px; }
                .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo'>OM Engineers</div>
                <div class='report-title'>{$this->title}</div>
                <div class='report-date'>Generated on " . date('F j, Y \a\t g:i A') . "</div>
            </div>
            {$this->content}
            <div class='footer'>
                <p>© " . date('Y') . " OM Engineers - Sammaan Foundation Vehicle Maintenance System</p>
            </div>
        </body>
        </html>";

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    public function download() {
        $html = $this->getHTML();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->filename . '.html"');
        echo $html;
    }

    private function getHTML() {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>{$this->title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                .header { text-align: center; border-bottom: 2px solid #1976D2; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #1976D2; }
                .report-title { font-size: 20px; margin: 10px 0; }
                .report-date { color: #666; font-size: 14px; }
                .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
                @media print {
                    body { margin: 20px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo'>OM Engineers</div>
                <div class='report-title'>{$this->title}</div>
                <div class='report-date'>Generated on " . date('F j, Y \a\t g:i A') . "</div>
            </div>
            {$this->content}
            <div class='footer'>
                <p>© " . date('Y') . " OM Engineers - Sammaan Foundation Vehicle Maintenance System</p>
            </div>
            <div class='no-print' style='position: fixed; top: 20px; right: 20px;'>
                <button onclick='window.print()' style='padding: 10px 20px; background: #1976D2; color: white; border: none; border-radius: 5px; cursor: pointer;'>Print</button>
                <button onclick='window.close()' style='padding: 10px 20px; background: #666; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;'>Close</button>
            </div>
        </body>
        </html>";
    }
}

// Handle report generation
if ($_GET['action'] ?? '' === 'generate') {
    requireLogin();

    $report_type = sanitize($_GET['type'] ?? '');
    $date_from = sanitize($_GET['date_from'] ?? '');
    $date_to = sanitize($_GET['date_to'] ?? '');
    $vehicle_filter = sanitize($_GET['vehicle'] ?? '');
    $format = sanitize($_GET['format'] ?? 'view');

    switch ($report_type) {
        case 'service_requests':
            generateServiceRequestsReport($date_from, $date_to, $vehicle_filter, $format);
            break;
        case 'quotations':
            generateQuotationsReport($date_from, $date_to, $vehicle_filter, $format);
            break;
        case 'approvals':
            generateApprovalsReport($date_from, $date_to, $vehicle_filter, $format);
            break;
        case 'financial':
            generateFinancialReport($date_from, $date_to, $format);
            break;
        default:
            http_response_code(400);
            die('Invalid report type');
    }
}

function generateServiceRequestsReport($date_from, $date_to, $vehicle_filter, $format) {
    global $db;

    $pdf = new SimplePDF('Service Requests Report', 'service-requests-report');

    // Build query conditions
    $conditions = [];
    $params = [];

    if (hasRole('requestor')) {
        $conditions[] = "sr.user_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif (hasRole('approver')) {
        $conditions[] = "u.organization_id = ?";
        $params[] = $_SESSION['organization_id'];
    }

    if ($date_from) {
        $conditions[] = "DATE(sr.created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $conditions[] = "DATE(sr.created_at) <= ?";
        $params[] = $date_to;
    }

    if ($vehicle_filter) {
        $conditions[] = "v.registration_number LIKE ?";
        $params[] = "%{$vehicle_filter}%";
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get service requests data
    $requests = $db->fetchAll(
        "SELECT sr.*, v.registration_number, u.full_name as requestor_name, o.name as organization_name
         FROM service_requests sr
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         JOIN organizations o ON u.organization_id = o.id
         {$whereClause}
         ORDER BY sr.created_at DESC",
        $params
    );

    // Generate report
    if ($date_from && $date_to) {
        $pdf->addText("Period: " . formatDate($date_from) . " to " . formatDate($date_to));
    }

    if ($vehicle_filter) {
        $pdf->addText("Vehicle Filter: " . htmlspecialchars($vehicle_filter));
    }

    $pdf->addHeader('Service Requests Summary', 2);

    $statusCounts = [];
    $totalRequests = count($requests);

    foreach ($requests as $request) {
        $status = $request['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    $summary = [
        'Total Requests' => $totalRequests,
        'Pending' => $statusCounts['pending'] ?? 0,
        'Quoted' => $statusCounts['quoted'] ?? 0,
        'Approved' => $statusCounts['approved'] ?? 0,
        'Completed' => $statusCounts['completed'] ?? 0,
        'Rejected' => $statusCounts['rejected'] ?? 0
    ];

    $pdf->addSummary($summary);

    if (!empty($requests)) {
        $pdf->addHeader('Detailed List', 2);

        $headers = ['ID', 'Vehicle', 'Requestor', 'Problem', 'Status', 'Date'];
        $data = [];

        foreach ($requests as $request) {
            $data[] = [
                '#' . $request['id'],
                htmlspecialchars($request['registration_number']),
                htmlspecialchars($request['requestor_name']),
                htmlspecialchars(substr($request['problem_description'], 0, 50)) . '...',
                ucfirst($request['status']),
                formatDate($request['created_at'])
            ];
        }

        $pdf->addTable($data, $headers);
    } else {
        $pdf->addText('No service requests found for the specified criteria.');
    }

    if ($format === 'download') {
        $pdf->download();
    } else {
        $pdf->output();
    }
}

function generateQuotationsReport($date_from, $date_to, $vehicle_filter, $format) {
    global $db;

    $pdf = new SimplePDF('Quotations Report', 'quotations-report');

    // Build query conditions (admin and approver can see all, requestor only their own)
    $conditions = [];
    $params = [];

    if (hasRole('requestor')) {
        $conditions[] = "sr.user_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif (hasRole('approver')) {
        $conditions[] = "u.organization_id = ?";
        $params[] = $_SESSION['organization_id'];
    }

    if ($date_from) {
        $conditions[] = "DATE(q.created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $conditions[] = "DATE(q.created_at) <= ?";
        $params[] = $date_to;
    }

    if ($vehicle_filter) {
        $conditions[] = "v.registration_number LIKE ?";
        $params[] = "%{$vehicle_filter}%";
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get quotations data
    $quotations = $db->fetchAll(
        "SELECT q.*, sr.problem_description, v.registration_number, u.full_name as requestor_name,
                a.status as approval_status, approver.full_name as approver_name
         FROM quotations q
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         LEFT JOIN approvals a ON q.id = a.quotation_id
         LEFT JOIN users approver ON a.approver_id = approver.id
         {$whereClause}
         ORDER BY q.created_at DESC",
        $params
    );

    // Generate report
    if ($date_from && $date_to) {
        $pdf->addText("Period: " . formatDate($date_from) . " to " . formatDate($date_to));
    }

    if ($vehicle_filter) {
        $pdf->addText("Vehicle Filter: " . htmlspecialchars($vehicle_filter));
    }

    $pdf->addHeader('Quotations Summary', 2);

    $statusCounts = [];
    $totalQuotations = count($quotations);
    $totalAmount = 0;
    $approvedAmount = 0;

    foreach ($quotations as $quotation) {
        $status = $quotation['approval_status'] ?: 'sent';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        $totalAmount += $quotation['amount'];

        if ($status === 'approved') {
            $approvedAmount += $quotation['amount'];
        }
    }

    $summary = [
        'Total Quotations' => $totalQuotations,
        'Sent' => $statusCounts['sent'] ?? 0,
        'Approved' => $statusCounts['approved'] ?? 0,
        'Rejected' => $statusCounts['rejected'] ?? 0,
        'Total Amount' => formatCurrency($totalAmount),
        'Approved Amount' => formatCurrency($approvedAmount)
    ];

    $pdf->addSummary($summary);

    if (!empty($quotations)) {
        $pdf->addHeader('Detailed List', 2);

        $headers = ['ID', 'Vehicle', 'Requestor', 'Amount', 'Status', 'Approver', 'Date'];
        $data = [];

        foreach ($quotations as $quotation) {
            $data[] = [
                '#' . $quotation['id'],
                htmlspecialchars($quotation['registration_number']),
                htmlspecialchars($quotation['requestor_name']),
                formatCurrency($quotation['amount']),
                ucfirst($quotation['approval_status'] ?: 'sent'),
                htmlspecialchars($quotation['approver_name'] ?: 'Not assigned'),
                formatDate($quotation['created_at'])
            ];
        }

        $pdf->addTable($data, $headers);
    } else {
        $pdf->addText('No quotations found for the specified criteria.');
    }

    if ($format === 'download') {
        $pdf->download();
    } else {
        $pdf->output();
    }
}

function generateApprovalsReport($date_from, $date_to, $vehicle_filter, $format) {
    global $db;

    requireRole('approver');

    $pdf = new SimplePDF('Approvals Report', 'approvals-report');

    // Build query conditions
    $conditions = ["a.approver_id = ?"];
    $params = [$_SESSION['user_id']];

    if ($date_from) {
        $conditions[] = "DATE(a.approved_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $conditions[] = "DATE(a.approved_at) <= ?";
        $params[] = $date_to;
    }

    if ($vehicle_filter) {
        $conditions[] = "v.registration_number LIKE ?";
        $params[] = "%{$vehicle_filter}%";
    }

    $conditions[] = "a.status IN ('approved', 'rejected')";

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);

    // Get approvals data
    $approvals = $db->fetchAll(
        "SELECT a.*, q.amount, q.work_description, sr.problem_description,
                v.registration_number, u.full_name as requestor_name
         FROM approvals a
         JOIN quotations q ON a.quotation_id = q.id
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         {$whereClause}
         ORDER BY a.approved_at DESC",
        $params
    );

    // Generate report
    $pdf->addText("Approver: " . htmlspecialchars($_SESSION['full_name']));

    if ($date_from && $date_to) {
        $pdf->addText("Period: " . formatDate($date_from) . " to " . formatDate($date_to));
    }

    if ($vehicle_filter) {
        $pdf->addText("Vehicle Filter: " . htmlspecialchars($vehicle_filter));
    }

    $pdf->addHeader('Approvals Summary', 2);

    $approvedCount = 0;
    $rejectedCount = 0;
    $totalApprovedAmount = 0;

    foreach ($approvals as $approval) {
        if ($approval['status'] === 'approved') {
            $approvedCount++;
            $totalApprovedAmount += $approval['amount'];
        } else {
            $rejectedCount++;
        }
    }

    $summary = [
        'Total Decisions' => count($approvals),
        'Approved' => $approvedCount,
        'Rejected' => $rejectedCount,
        'Total Approved Amount' => formatCurrency($totalApprovedAmount),
        'Average Approved Amount' => $approvedCount > 0 ? formatCurrency($totalApprovedAmount / $approvedCount) : '₹0.00'
    ];

    $pdf->addSummary($summary);

    if (!empty($approvals)) {
        $pdf->addHeader('Detailed List', 2);

        $headers = ['ID', 'Vehicle', 'Requestor', 'Amount', 'Decision', 'Date', 'Notes'];
        $data = [];

        foreach ($approvals as $approval) {
            $data[] = [
                '#' . $approval['quotation_id'],
                htmlspecialchars($approval['registration_number']),
                htmlspecialchars($approval['requestor_name']),
                formatCurrency($approval['amount']),
                ucfirst($approval['status']),
                formatDate($approval['approved_at']),
                htmlspecialchars(substr($approval['notes'] ?: 'No notes', 0, 30))
            ];
        }

        $pdf->addTable($data, $headers);
    } else {
        $pdf->addText('No approval decisions found for the specified criteria.');
    }

    if ($format === 'download') {
        $pdf->download();
    } else {
        $pdf->output();
    }
}

function generateFinancialReport($date_from, $date_to, $format) {
    global $db;

    requireRole('admin');

    $pdf = new SimplePDF('Financial Report', 'financial-report');

    // Build query conditions
    $conditions = [];
    $params = [];

    if ($date_from) {
        $conditions[] = "DATE(wo.created_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $conditions[] = "DATE(wo.created_at) <= ?";
        $params[] = $date_to;
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get financial data
    $workOrders = $db->fetchAll(
        "SELECT wo.*, q.amount as quotation_amount, sr.problem_description,
                v.registration_number, u.full_name as requestor_name
         FROM work_orders wo
         JOIN quotations q ON wo.quotation_id = q.id
         JOIN service_requests sr ON q.request_id = sr.id
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         {$whereClause}
         ORDER BY wo.created_at DESC",
        $params
    );

    // Generate report
    if ($date_from && $date_to) {
        $pdf->addText("Period: " . formatDate($date_from) . " to " . formatDate($date_to));
    }

    $pdf->addHeader('Financial Summary', 2);

    $totalBilled = 0;
    $totalPaid = 0;
    $pendingPayments = 0;
    $completedWork = 0;

    foreach ($workOrders as $workOrder) {
        $totalBilled += $workOrder['bill_amount'];

        if ($workOrder['payment_status'] === 'paid') {
            $totalPaid += $workOrder['bill_amount'];
        } else {
            $pendingPayments += $workOrder['bill_amount'];
        }

        if ($workOrder['work_completed_at']) {
            $completedWork++;
        }
    }

    $summary = [
        'Total Work Orders' => count($workOrders),
        'Completed Work' => $completedWork,
        'Total Billed Amount' => formatCurrency($totalBilled),
        'Total Paid Amount' => formatCurrency($totalPaid),
        'Pending Payments' => formatCurrency($pendingPayments),
        'Collection Rate' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 2) . '%' : '0%'
    ];

    $pdf->addSummary($summary);

    if (!empty($workOrders)) {
        $pdf->addHeader('Work Orders Details', 2);

        $headers = ['ID', 'Vehicle', 'Requestor', 'Bill Amount', 'Payment Status', 'Work Status', 'Date'];
        $data = [];

        foreach ($workOrders as $workOrder) {
            $data[] = [
                '#' . $workOrder['quotation_id'],
                htmlspecialchars($workOrder['registration_number']),
                htmlspecialchars($workOrder['requestor_name']),
                formatCurrency($workOrder['bill_amount']),
                ucfirst($workOrder['payment_status']),
                $workOrder['work_completed_at'] ? 'Completed' : 'Pending',
                formatDate($workOrder['created_at'])
            ];
        }

        $pdf->addTable($data, $headers);
    } else {
        $pdf->addText('No financial data found for the specified criteria.');
    }

    if ($format === 'download') {
        $pdf->download();
    } else {
        $pdf->output();
    }
}
?>