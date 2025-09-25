<?php
require_once 'functions.php';

class QuotationPDF {
    private $db;

    public function __construct() {
        global $db;
        $this->db = $db;
    }

    public function generateQuotationPDF($quotation_id) {
        // Get quotation details - check if it's standalone or regular
        $quotation = $this->db->fetch(
            "SELECT q.*,
                    CASE
                        WHEN q.is_standalone = 1 THEN sc.problem_description
                        ELSE sr.problem_description
                    END as problem_description,
                    CASE
                        WHEN q.is_standalone = 1 THEN sc.vehicle_registration
                        ELSE v.registration_number
                    END as registration_number,
                    CASE
                        WHEN q.is_standalone = 1 THEN sc.customer_name
                        ELSE u.full_name
                    END as requestor_name,
                    CASE
                        WHEN q.is_standalone = 1 THEN sc.customer_email
                        ELSE u.email
                    END as requestor_email,
                    CASE
                        WHEN q.is_standalone = 1 THEN sc.request_id
                        ELSE CONCAT('#', q.request_id)
                    END as display_request_id,
                    DATE_FORMAT(q.created_at, '%d-%m-%Y') as formatted_date
             FROM quotations q
             LEFT JOIN service_requests sr ON q.request_id = sr.id
             LEFT JOIN vehicles v ON sr.vehicle_id = v.id
             LEFT JOIN users u ON sr.user_id = u.id
             LEFT JOIN standalone_customers sc ON q.standalone_customer_id = sc.id
             WHERE q.id = ?",
            [$quotation_id]
        );

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        // Get quotation items
        $items = $this->db->fetchAll(
            "SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id",
            [$quotation_id]
        );

        // Generate HTML for PDF
        $html = $this->generateQuotationHTML($quotation, $items);

        // For now, return HTML. In production, you would use a library like wkhtmltopdf or mPDF
        return $html;
    }

    private function generateQuotationHTML($quotation, $items) {
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotation - <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        .letterhead {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }
        .company-tagline {
            font-size: 14px;
            color: #6b7280;
            margin: 5px 0;
        }
        .company-contact {
            font-size: 12px;
            color: #4b5563;
            margin: 10px 0;
        }
        .quotation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .quotation-info, .customer-info {
            width: 48%;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .service-description {
            background-color: #f9fafb;
            padding: 15px;
            border-left: 4px solid #2563eb;
            margin: 20px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #d1d5db;
            padding: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        .items-table .text-right {
            text-align: right;
        }
        .base-service-row {
            background-color: #eff6ff;
        }
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
        }
        .totals-table .total-label {
            font-weight: bold;
            background-color: #f9fafb;
        }
        .totals-table .grand-total {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .terms-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .terms-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .terms-list {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.8;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            .quotation-header { display: block; }
            .quotation-info, .customer-info { width: 100%; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <!-- Letterhead -->
    <div class="letterhead">
        <h1 class="company-name">OM ENGINEERS</h1>
        <p class="company-tagline">Professional Vehicle Maintenance & Repair Services</p>
        <div class="company-contact">
            Email: contact@om-engineers.com | Phone: +91-XXXXXXXXXX | Address: [Your Address Here]
        </div>
    </div>

    <!-- Quotation Header -->
    <div class="quotation-header">
        <div class="quotation-info">
            <div class="section-title">Quotation Details</div>
            <div class="info-row">
                <span class="info-label">Quotation No:</span>
                <span><?php echo htmlspecialchars($quotation['quotation_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span><?php echo $quotation['formatted_date']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Request ID:</span>
                <span><?php echo htmlspecialchars($quotation['display_request_id']); ?></span>
            </div>
        </div>

        <div class="customer-info">
            <div class="section-title">Customer Information</div>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span><?php echo htmlspecialchars($quotation['requestor_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?php echo htmlspecialchars($quotation['requestor_email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Vehicle:</span>
                <span><?php echo htmlspecialchars($quotation['registration_number']); ?></span>
            </div>
        </div>
    </div>

    <!-- Service Description -->
    <div class="service-description">
        <div class="section-title">Service Required</div>
        <p><?php echo htmlspecialchars($quotation['problem_description']); ?></p>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Type</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Rate (₹)</th>
                <th class="text-right">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Base Service Charge -->
            <tr class="base-service-row">
                <td>Base Service Charge</td>
                <td>Service</td>
                <td class="text-right">1.00</td>
                <td class="text-right"><?php echo number_format($quotation['base_service_charge'], 2); ?></td>
                <td class="text-right"><?php echo number_format($quotation['base_service_charge'], 2); ?></td>
            </tr>

            <!-- Additional Items -->
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td><?php echo ucfirst($item['item_type']); ?></td>
                <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                <td class="text-right"><?php echo number_format($item['rate'], 2); ?></td>
                <td class="text-right"><?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="total-label">Subtotal:</td>
                <td class="text-right">₹<?php echo number_format($quotation['subtotal'], 2); ?></td>
            </tr>
            <tr>
                <td class="total-label">SGST (<?php echo number_format($quotation['sgst_rate'], 2); ?>%):</td>
                <td class="text-right">₹<?php echo number_format($quotation['sgst_amount'], 2); ?></td>
            </tr>
            <tr>
                <td class="total-label">CGST (<?php echo number_format($quotation['cgst_rate'], 2); ?>%):</td>
                <td class="text-right">₹<?php echo number_format($quotation['cgst_amount'], 2); ?></td>
            </tr>
            <tr class="grand-total">
                <td>GRAND TOTAL:</td>
                <td class="text-right">₹<?php echo number_format($quotation['total_amount'], 2); ?></td>
            </tr>
        </table>
    </div>

    <!-- Terms & Conditions -->
    <div class="terms-section">
        <div class="terms-title">Terms & Conditions:</div>
        <div class="terms-list">
            1. This quotation is valid for a week from the date of issue.<br>
            2. Payment terms: 50% advance, 50% on completion of work.<br>
            3. Any additional work required will be charged separately.<br>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Thank you for choosing Om Engineers for your vehicle maintenance needs.</p>
        <p><strong>This is a computer-generated quotation and does not require a signature.</strong></p>
    </div>
</body>
</html>
        <?php
        $html = ob_get_clean();
        return $html;
    }

    public function downloadPDF($quotation_id, $filename = null) {
        $html = $this->generateQuotationPDF($quotation_id);

        if (!$filename) {
            $quotation = $this->db->fetch("SELECT quotation_number FROM quotations WHERE id = ?", [$quotation_id]);
            $filename = 'Quotation-' . ($quotation['quotation_number'] ?? $quotation_id) . '.html';
        }

        // Set headers for download
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($html));

        echo $html;
        exit;
    }

    public function previewPDF($quotation_id) {
        $html = $this->generateQuotationPDF($quotation_id);

        // Set headers for preview
        header('Content-Type: text/html; charset=UTF-8');

        echo $html;
        exit;
    }
}
?>