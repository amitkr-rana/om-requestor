<?php
// Email integration using PHPMailer
// Note: This is a simple email implementation. For production, install PHPMailer via Composer

class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;

    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = FROM_EMAIL;
        $this->from_name = FROM_NAME;
    }

    /**
     * Send email using PHP's mail() function (basic implementation)
     * For production, replace with PHPMailer
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = '') {
        // Validate email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Prepare headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: OM Requestor System'
        ];

        // Send email
        try {
            $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));

            // Log email sending attempt
            error_log("Email sent to {$to}: " . ($result ? 'Success' : 'Failed'));

            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification about new service request
     */
    public function sendNewRequestNotification($requestId, $vehicleRegNo, $problem, $requestorName) {
        $subject = 'New Service Request - ' . $vehicleRegNo;

        $htmlBody = $this->getEmailTemplate('new_request', [
            'request_id' => $requestId,
            'vehicle_registration' => $vehicleRegNo,
            'problem_description' => $problem,
            'requestor_name' => $requestorName,
            'dashboard_url' => SITE_URL . '/dashboard/admin.php'
        ]);

        return $this->sendEmail($this->from_email, $subject, $htmlBody);
    }

    /**
     * Send quotation to approver
     */
    public function sendQuotationToApprover($approverEmail, $approverName, $quotationId, $vehicleRegNo, $amount, $workDescription) {
        $subject = 'Quotation for Approval - ' . $vehicleRegNo . ' (₹' . number_format($amount, 2) . ')';

        $htmlBody = $this->getEmailTemplate('quotation_approval', [
            'approver_name' => $approverName,
            'quotation_id' => $quotationId,
            'vehicle_registration' => $vehicleRegNo,
            'amount' => $amount,
            'work_description' => $workDescription,
            'approval_url' => SITE_URL . '/dashboard/approver.php'
        ]);

        return $this->sendEmail($approverEmail, $subject, $htmlBody);
    }

    /**
     * Send approval decision notification
     */
    public function sendApprovalDecisionNotification($requestorEmail, $requestorName, $vehicleRegNo, $status, $amount, $notes = '') {
        $statusText = $status === 'approved' ? 'Approved' : 'Rejected';
        $subject = 'Quotation ' . $statusText . ' - ' . $vehicleRegNo;

        $htmlBody = $this->getEmailTemplate('approval_decision', [
            'requestor_name' => $requestorName,
            'vehicle_registration' => $vehicleRegNo,
            'status' => $status,
            'status_text' => $statusText,
            'amount' => $amount,
            'notes' => $notes,
            'dashboard_url' => SITE_URL . '/dashboard/requestor.php'
        ]);

        return $this->sendEmail($requestorEmail, $subject, $htmlBody);
    }

    /**
     * Send work completion notification
     */
    public function sendWorkCompletionNotification($requestorEmail, $requestorName, $vehicleRegNo, $amount) {
        $subject = 'Work Completed - ' . $vehicleRegNo;

        $htmlBody = $this->getEmailTemplate('work_completion', [
            'requestor_name' => $requestorName,
            'vehicle_registration' => $vehicleRegNo,
            'amount' => $amount,
            'dashboard_url' => SITE_URL . '/dashboard/requestor.php'
        ]);

        return $this->sendEmail($requestorEmail, $subject, $htmlBody);
    }

    /**
     * Get email template
     */
    private function getEmailTemplate($templateName, $variables = []) {
        $templates = [
            'new_request' => $this->getNewRequestTemplate(),
            'quotation_approval' => $this->getQuotationApprovalTemplate(),
            'approval_decision' => $this->getApprovalDecisionTemplate(),
            'work_completion' => $this->getWorkCompletionTemplate()
        ];

        $template = $templates[$templateName] ?? '';

        // Replace variables in template
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value), $template);
        }

        return $template;
    }

    private function getNewRequestTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>New Service Request</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">OM Engineers</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Vehicle Maintenance System</p>
            </div>

            <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <h2 style="color: #1976D2; margin-top: 0;">New Service Request Received</h2>

                <p>A new service request has been submitted and requires your attention.</p>

                <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Request Details:</h3>
                    <p><strong>Request ID:</strong> #{{request_id}}</p>
                    <p><strong>Vehicle:</strong> {{vehicle_registration}}</p>
                    <p><strong>Requestor:</strong> {{requestor_name}}</p>
                    <p><strong>Problem Description:</strong></p>
                    <p style="background: white; padding: 15px; border-left: 4px solid #1976D2; margin: 10px 0;">{{problem_description}}</p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{dashboard_url}}" style="background: #1976D2; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">View in Dashboard</a>
                </div>

                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    Please log in to your admin dashboard to create a quotation for this request.
                </p>
            </div>

            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>© ' . date('Y') . ' OM Engineers. All rights reserved.</p>
                <p>This is an automated message from the Vehicle Maintenance System.</p>
            </div>
        </body>
        </html>';
    }

    private function getQuotationApprovalTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Quotation for Approval</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">OM Engineers</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Vehicle Maintenance System</p>
            </div>

            <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <h2 style="color: #1976D2; margin-top: 0;">Quotation Awaiting Your Approval</h2>

                <p>Dear {{approver_name}},</p>
                <p>A new quotation has been prepared and requires your approval.</p>

                <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Quotation Details:</h3>
                    <p><strong>Quotation ID:</strong> #{{quotation_id}}</p>
                    <p><strong>Vehicle:</strong> {{vehicle_registration}}</p>
                    <p><strong>Quotation Amount:</strong> <span style="color: #1976D2; font-size: 18px; font-weight: bold;">₹{{amount}}</span></p>
                    <p><strong>Work Description:</strong></p>
                    <p style="background: white; padding: 15px; border-left: 4px solid #4CAF50; margin: 10px 0;">{{work_description}}</p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{approval_url}}" style="background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Review & Approve</a>
                </div>

                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    Please log in to your dashboard to review the complete details and make your approval decision.
                </p>
            </div>

            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>© ' . date('Y') . ' OM Engineers. All rights reserved.</p>
                <p>This is an automated message from the Vehicle Maintenance System.</p>
            </div>
        </body>
        </html>';
    }

    private function getApprovalDecisionTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Quotation {{status_text}}</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">OM Engineers</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Vehicle Maintenance System</p>
            </div>

            <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <h2 style="color: #1976D2; margin-top: 0;">Quotation {{status_text}}</h2>

                <p>Dear {{requestor_name}},</p>
                <p>Your quotation has been <strong>{{status_text}}</strong> by the approver.</p>

                <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Quotation Details:</h3>
                    <p><strong>Vehicle:</strong> {{vehicle_registration}}</p>
                    <p><strong>Amount:</strong> <span style="color: #1976D2; font-size: 18px; font-weight: bold;">₹{{amount}}</span></p>
                    <p><strong>Status:</strong> <span style="color: ' . (($status === 'approved') ? '#4CAF50' : '#F44336') . '; font-weight: bold; text-transform: uppercase;">{{status_text}}</span></p>
                </div>

                <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Next Steps:</strong></p>
                    <p>{{notes}}</p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{dashboard_url}}" style="background: #1976D2; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">View in Dashboard</a>
                </div>

                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    Thank you for using our vehicle maintenance service.
                </p>
            </div>

            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>© ' . date('Y') . ' OM Engineers. All rights reserved.</p>
                <p>This is an automated message from the Vehicle Maintenance System.</p>
            </div>
        </body>
        </html>';
    }

    private function getWorkCompletionTemplate() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Work Completed</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">OM Engineers</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Vehicle Maintenance System</p>
            </div>

            <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <h2 style="color: #4CAF50; margin-top: 0;">Work Completed Successfully</h2>

                <p>Dear {{requestor_name}},</p>
                <p>We are pleased to inform you that the maintenance work for your vehicle has been completed.</p>

                <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Work Summary:</h3>
                    <p><strong>Vehicle:</strong> {{vehicle_registration}}</p>
                    <p><strong>Total Amount:</strong> <span style="color: #4CAF50; font-size: 18px; font-weight: bold;">₹{{amount}}</span></p>
                    <p><strong>Payment Method:</strong> Cash</p>
                </div>

                <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>✓ Work Completed</strong></p>
                    <p>Your vehicle is ready for pickup. Please arrange payment upon collection.</p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{dashboard_url}}" style="background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">View Details</a>
                </div>

                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    Thank you for choosing OM Engineers for your vehicle maintenance needs.
                </p>
            </div>

            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>© ' . date('Y') . ' OM Engineers. All rights reserved.</p>
                <p>This is an automated message from the Vehicle Maintenance System.</p>
            </div>
        </body>
        </html>';
    }
}

// Global email service instance
$emailService = new EmailService();

// Helper function for backward compatibility
function sendEmail($to, $subject, $body, $isHTML = true) {
    global $emailService;
    return $emailService->sendEmail($to, $subject, $body);
}
?>