<?php
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'generate_bill':
        generateBill();
        break;
    case 'update_bill':
        updateBill();
        break;
    case 'record_payment':
        recordPayment();
        break;
    case 'update_payment':
        updatePayment();
        break;
    case 'get_bill':
        getBill();
        break;
    case 'get_payments':
        getPayments();
        break;
    case 'get_overdue_bills':
        getOverdueBills();
        break;
    case 'send_bill':
        sendBill();
        break;
    case 'mark_paid':
        markBillPaid();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function generateBill() {
    global $db;

    if (!in_array($_SESSION['role'], ['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $quotation_id = (int)($_POST['quotation_id'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? '');
    $payment_terms = sanitize($_POST['payment_terms'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($quotation_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quotation ID']);
        return;
    }

    try {
        $db->beginTransaction();

        // Get quotation details
        $quotation = $db->fetch(
            "SELECT * FROM quotations_new
             WHERE id = ? AND organization_id = ? AND status = 'repair_complete'",
            [$quotation_id, $_SESSION['organization_id']]
        );

        if (!$quotation) {
            throw new Exception('Quotation not found or not ready for billing');
        }

        // Check if bill already exists
        $existing_bill = $db->fetch(
            "SELECT id FROM billing WHERE quotation_id = ?",
            [$quotation_id]
        );

        if ($existing_bill) {
            throw new Exception('Bill already exists for this quotation');
        }

        // Generate bill number
        $bill_number = generateBillNumber($_SESSION['organization_id']);

        // Calculate amounts
        $subtotal = $quotation['subtotal'] ?: $quotation['total_amount'];
        $tax_amount = $quotation['tax_amount'] ?: 0;
        $total_amount = $quotation['total_amount'];

        // Create bill
        $result = $db->query(
            "INSERT INTO billing
             (quotation_id, bill_number, bill_date, due_date, subtotal, tax_amount,
              total_amount, payment_terms, notes, generated_by)
             VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)",
            [
                $quotation_id, $bill_number, $due_date ?: null, $subtotal, $tax_amount,
                $total_amount, $payment_terms, $notes, $_SESSION['user_id']
            ]
        );

        if (!$result) {
            throw new Exception('Failed to generate bill');
        }

        $bill_id = $db->lastInsertId();

        // Update quotation status
        $db->query(
            "UPDATE quotations_new
             SET status = 'bill_generated', bill_generated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$quotation_id]
        );

        // Log the billing
        logQuotationHistory($quotation_id, $_SESSION['user_id'], 'billed', null, null, "Bill generated: $bill_number");
        logStatusChange($quotation_id, 'repair_complete', 'bill_generated', $_SESSION['user_id'], "Bill generated: $bill_number");
        logUserActivity($_SESSION['user_id'], 'bill_generated', 'billing', $bill_id,
                       "Generated bill $bill_number for quotation {$quotation['quotation_number']}");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Bill generated successfully',
            'bill_id' => $bill_id,
            'bill_number' => $bill_number
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate bill: ' . $e->getMessage()]);
    }
}

function recordPayment() {
    global $db;

    if (!in_array($_SESSION['role'], ['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = sanitize($_POST['payment_method'] ?? '');
    $payment_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
    $reference_number = sanitize($_POST['reference_number'] ?? '');
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($bill_id <= 0 || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid bill ID or amount']);
        return;
    }

    $valid_methods = ['cash', 'cheque', 'bank_transfer', 'credit_card', 'upi', 'wallet', 'other'];
    if (!in_array($payment_method, $valid_methods)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payment method']);
        return;
    }

    try {
        $db->beginTransaction();

        // Get bill details
        $bill = $db->fetch(
            "SELECT b.*, q.quotation_number, q.customer_name
             FROM billing b
             JOIN quotations_new q ON b.quotation_id = q.id
             WHERE b.id = ? AND q.organization_id = ?",
            [$bill_id, $_SESSION['organization_id']]
        );

        if (!$bill) {
            throw new Exception('Bill not found');
        }

        // Check if payment amount exceeds balance
        if ($amount > $bill['balance_amount']) {
            throw new Exception('Payment amount exceeds outstanding balance');
        }

        // Generate transaction number
        $transaction_number = generateTransactionNumber();

        // Record payment
        $result = $db->query(
            "INSERT INTO payment_transactions
             (billing_id, transaction_number, payment_date, amount, payment_method,
              reference_number, bank_name, notes, received_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $bill_id, $transaction_number, $payment_date, $amount, $payment_method,
                $reference_number, $bank_name, $notes, $_SESSION['user_id']
            ]
        );

        if (!$result) {
            throw new Exception('Failed to record payment');
        }

        $transaction_id = $db->lastInsertId();

        // Update bill paid amount
        $new_paid_amount = $bill['paid_amount'] + $amount;
        $new_balance = $bill['total_amount'] - $new_paid_amount;

        // Determine payment status
        $payment_status = 'partial';
        if ($new_balance <= 0) {
            $payment_status = 'paid';
        } elseif ($new_paid_amount == 0) {
            $payment_status = 'unpaid';
        }

        $db->query(
            "UPDATE billing SET paid_amount = ?, payment_status = ? WHERE id = ?",
            [$new_paid_amount, $payment_status, $bill_id]
        );

        // If fully paid, update quotation status
        if ($payment_status === 'paid') {
            $db->query(
                "UPDATE quotations_new SET status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$bill['quotation_id']]
            );

            logStatusChange($bill['quotation_id'], 'bill_generated', 'paid', $_SESSION['user_id'],
                           "Payment received: $transaction_number");
        }

        // Log the payment
        logUserActivity($_SESSION['user_id'], 'payment_recorded', 'billing', $bill_id,
                       "Recorded payment of ₹$amount for bill {$bill['bill_number']}");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'transaction_id' => $transaction_id,
            'transaction_number' => $transaction_number,
            'new_balance' => $new_balance,
            'payment_status' => $payment_status
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record payment: ' . $e->getMessage()]);
    }
}

function getBill() {
    global $db;

    $bill_id = (int)($_POST['bill_id'] ?? 0);

    if ($bill_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid bill ID']);
        return;
    }

    // Get bill with quotation details
    $bill = $db->fetch(
        "SELECT b.*, q.quotation_number, q.customer_name, q.customer_email,
                q.vehicle_registration, q.problem_description,
                u.full_name as generated_by_name
         FROM billing b
         JOIN quotations_new q ON b.quotation_id = q.id
         JOIN users_new u ON b.generated_by = u.id
         WHERE b.id = ? AND q.organization_id = ?",
        [$bill_id, $_SESSION['organization_id']]
    );

    if (!$bill) {
        http_response_code(404);
        echo json_encode(['error' => 'Bill not found']);
        return;
    }

    // Get bill items from quotation items
    $items = $db->fetchAll(
        "SELECT * FROM quotation_items_new WHERE quotation_id = ? ORDER BY sort_order, id",
        [$bill['quotation_id']]
    );

    // Get payment history
    $payments = $db->fetchAll(
        "SELECT pt.*, u.full_name as received_by_name
         FROM payment_transactions pt
         JOIN users_new u ON pt.received_by = u.id
         WHERE pt.billing_id = ?
         ORDER BY pt.payment_date DESC, pt.created_at DESC",
        [$bill_id]
    );

    echo json_encode([
        'success' => true,
        'bill' => $bill,
        'items' => $items,
        'payments' => $payments
    ]);
}

function getOverdueBills() {
    global $db;

    $bills = $db->fetchAll(
        "SELECT b.*, q.quotation_number, q.customer_name, q.customer_email, q.customer_phone,
                DATEDIFF(CURDATE(), b.due_date) as days_overdue
         FROM billing b
         JOIN quotations_new q ON b.quotation_id = q.id
         WHERE q.organization_id = ? AND b.payment_status IN ('unpaid', 'partial')
           AND b.due_date < CURDATE()
         ORDER BY b.due_date ASC",
        [$_SESSION['organization_id']]
    );

    echo json_encode([
        'success' => true,
        'bills' => $bills,
        'count' => count($bills)
    ]);
}

function markBillPaid() {
    global $db;

    if (!in_array($_SESSION['role'], ['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid security token']);
        return;
    }

    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');

    if ($bill_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid bill ID']);
        return;
    }

    try {
        $db->beginTransaction();

        // Get bill details
        $bill = $db->fetch(
            "SELECT b.*, q.quotation_number
             FROM billing b
             JOIN quotations_new q ON b.quotation_id = q.id
             WHERE b.id = ? AND q.organization_id = ?",
            [$bill_id, $_SESSION['organization_id']]
        );

        if (!$bill) {
            throw new Exception('Bill not found');
        }

        if ($bill['payment_status'] === 'paid') {
            echo json_encode(['success' => true, 'message' => 'Bill is already paid']);
            return;
        }

        // Calculate remaining amount and record as payment
        $remaining_amount = $bill['balance_amount'];

        if ($remaining_amount > 0) {
            // Generate transaction number
            $transaction_number = generateTransactionNumber();

            // Record the remaining payment
            $db->query(
                "INSERT INTO payment_transactions
                 (billing_id, transaction_number, payment_date, amount, payment_method,
                  notes, received_by)
                 VALUES (?, ?, CURDATE(), ?, 'other', ?, ?)",
                [$bill_id, $transaction_number, $remaining_amount, $notes ?: 'Manual mark as paid', $_SESSION['user_id']]
            );
        }

        // Update bill status
        $db->query(
            "UPDATE billing SET paid_amount = total_amount, payment_status = 'paid' WHERE id = ?",
            [$bill_id]
        );

        // Update quotation status
        $db->query(
            "UPDATE quotations_new SET status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$bill['quotation_id']]
        );

        // Log the action
        logStatusChange($bill['quotation_id'], 'bill_generated', 'paid', $_SESSION['user_id'],
                       "Marked as paid manually");
        logUserActivity($_SESSION['user_id'], 'bill_marked_paid', 'billing', $bill_id,
                       "Marked bill {$bill['bill_number']} as paid");

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Bill marked as paid successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark bill as paid: ' . $e->getMessage()]);
    }
}

// Helper functions
function generateBillNumber($organization_id) {
    global $db;

    $year = date('Y');

    // Get or create sequence
    $result = $db->query(
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
}

function generateTransactionNumber() {
    return 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
}

function logQuotationHistory($quotation_id, $user_id, $action, $field_name = null, $old_value = null, $new_value = null) {
    global $db;

    $db->query(
        "INSERT INTO quotation_history
         (quotation_id, changed_by, action, field_name, old_value, new_value,
          ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $quotation_id, $user_id, $action, $field_name, $old_value, $new_value,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
}

function logStatusChange($quotation_id, $from_status, $to_status, $user_id, $notes = null) {
    global $db;

    $db->query(
        "INSERT INTO quotation_status_log
         (quotation_id, from_status, to_status, changed_by, notes)
         VALUES (?, ?, ?, ?, ?)",
        [$quotation_id, $from_status, $to_status, $user_id, $notes]
    );
}

function logUserActivity($user_id, $action, $entity_type, $entity_id, $description, $metadata = null) {
    global $db;

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
}
?>