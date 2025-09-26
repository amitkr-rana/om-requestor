<?php
require_once '../includes/functions.php';

requireLogin();

// Get user's quotations based on role
$quotations = [];
$stats = [];

if ($_SESSION['role'] === 'requestor') {
    // For requestors, show their own quotations
    $quotations = $db->fetchAll(
        "SELECT q.*, u.full_name as created_by_name, a.full_name as approved_by_name
         FROM quotations_new q
         LEFT JOIN users_new u ON q.created_by = u.id
         LEFT JOIN users_new a ON q.approved_by = a.id
         WHERE q.created_by = ? AND q.organization_id = ?
         ORDER BY q.created_at DESC
         LIMIT 50",
        [$_SESSION['user_id'], $_SESSION['organization_id']]
    );

    $stats = [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE created_by = ?", [$_SESSION['user_id']])['count'],
        'pending' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE created_by = ? AND status = 'pending'", [$_SESSION['user_id']])['count'],
        'sent' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE created_by = ? AND status = 'sent'", [$_SESSION['user_id']])['count'],
        'approved' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE created_by = ? AND status = 'approved'", [$_SESSION['user_id']])['count'],
        'completed' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE created_by = ? AND status IN ('repair_complete', 'bill_generated', 'paid')", [$_SESSION['user_id']])['count']
    ];
} else {
    // For admin/approver, show all quotations in their organization
    $quotations = $db->fetchAll(
        "SELECT q.*, u.full_name as created_by_name, a.full_name as approved_by_name, t.full_name as assigned_to_name
         FROM quotations_new q
         LEFT JOIN users_new u ON q.created_by = u.id
         LEFT JOIN users_new a ON q.approved_by = a.id
         LEFT JOIN users_new t ON q.assigned_to = t.id
         WHERE q.organization_id = ?
         ORDER BY q.created_at DESC
         LIMIT 50",
        [$_SESSION['organization_id']]
    );

    $stats = [
        'total' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ?", [$_SESSION['organization_id']])['count'],
        'pending' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status = 'pending'", [$_SESSION['organization_id']])['count'],
        'sent' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status = 'sent'", [$_SESSION['organization_id']])['count'],
        'approved' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status = 'approved'", [$_SESSION['organization_id']])['count'],
        'in_progress' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status = 'repair_in_progress'", [$_SESSION['organization_id']])['count'],
        'completed' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status IN ('repair_complete', 'bill_generated', 'paid')", [$_SESSION['organization_id']])['count']
    ];
}

// Get recent activity
$recent_activity = $db->fetchAll(
    "SELECT ual.*, u.full_name as user_name
     FROM user_activity_log ual
     JOIN users_new u ON ual.user_id = u.id
     WHERE ual.organization_id = ? AND ual.entity_type = 'quotation'
     ORDER BY ual.created_at DESC
     LIMIT 10",
    [$_SESSION['organization_id']]
);

// Get available technicians for assignment (admin only)
$technicians = [];
if ($_SESSION['role'] === 'admin') {
    $technicians = $db->fetchAll(
        "SELECT id, full_name FROM users_new
         WHERE organization_id = ? AND role IN ('admin', 'technician') AND is_active = 1
         ORDER BY full_name",
        [$_SESSION['organization_id']]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Manager - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/material.css" rel="stylesheet">
    <link href="../assets/css/style-base.css" rel="stylesheet">
    <style>
        .quotation-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            padding: 20px;
            transition: box-shadow 0.3s ease;
        }
        .quotation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .quotation-number {
            font-size: 18px;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 4px;
        }
        .customer-info {
            color: #666;
            font-size: 14px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-sent { background: #d1ecf1; color: #0c5460; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-repair_in_progress { background: #e2e3f0; color: #383d41; }
        .status-repair_complete { background: #bee5eb; color: #0f4c75; }
        .status-bill_generated { background: #ffeaa7; color: #6c5ce7; }
        .status-paid { background: #00b894; color: white; }
        .status-cancelled { background: #636e72; color: white; }

        .quotation-details {
            margin: 16px 0;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .quotation-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary { background: #1976d2; color: white; }
        .btn-success { background: #388e3c; color: white; }
        .btn-danger { background: #d32f2f; color: white; }
        .btn-warning { background: #f57c00; color: white; }
        .btn-info { background: #0288d1; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 8px;
        }
        .stat-label {
            color: #666;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 500;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .timeline-item {
            border-left: 2px solid #e0e0e0;
            padding-left: 16px;
            margin-bottom: 16px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #1976d2;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover {
            color: black;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Quotation Manager</h1>
                <div class="header-actions">
                    <?php if ($_SESSION['role'] === 'requestor'): ?>
                        <button class="btn btn-primary" onclick="showCreateModal()">
                            <i class="material-icons">add</i> Create Quotation Request
                        </button>
                    <?php endif; ?>
                    <span class="user-info">
                        Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        (<?php echo ucfirst($_SESSION['role']); ?>)
                    </span>
                    <a href="../api/auth.php?action=logout" class="btn btn-outline">Logout</a>
                </div>
            </div>
        </header>

        <!-- Stats Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Quotations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['sent'] ?? 0; ?></div>
                <div class="stat-label">Sent for Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <?php if ($_SESSION['role'] !== 'requestor'): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-row">
                <select id="statusFilter" class="form-control" onchange="filterQuotations()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="sent">Sent for Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="repair_in_progress">In Progress</option>
                    <option value="repair_complete">Repair Complete</option>
                    <option value="bill_generated">Bill Generated</option>
                    <option value="paid">Paid</option>
                </select>
                <input type="text" id="searchFilter" class="form-control" placeholder="Search by customer, vehicle, or quotation number..." onkeyup="filterQuotations()">
                <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                <button class="btn btn-info" onclick="refreshQuotations()">
                    <i class="material-icons">refresh</i> Refresh
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-grid">
                <!-- Quotations List -->
                <div class="quotations-section">
                    <h2>
                        <?php echo $_SESSION['role'] === 'requestor' ? 'My Quotation Requests' : 'All Quotations'; ?>
                        <span class="count">(<?php echo count($quotations); ?>)</span>
                    </h2>
                    <div id="quotations-container">
                        <?php foreach ($quotations as $quotation): ?>
                            <div class="quotation-card" data-status="<?php echo $quotation['status']; ?>" data-search="<?php echo htmlspecialchars(strtolower($quotation['quotation_number'] . ' ' . $quotation['customer_name'] . ' ' . $quotation['vehicle_registration'])); ?>">
                                <div class="quotation-header">
                                    <div>
                                        <div class="quotation-number"><?php echo htmlspecialchars($quotation['quotation_number']); ?></div>
                                        <div class="customer-info">
                                            <strong><?php echo htmlspecialchars($quotation['customer_name']); ?></strong><br>
                                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">directions_car</i>
                                            <?php echo htmlspecialchars($quotation['vehicle_registration']); ?>
                                            <?php if ($quotation['vehicle_make_model']): ?>
                                                (<?php echo htmlspecialchars($quotation['vehicle_make_model']); ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $quotation['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $quotation['status'])); ?>
                                        </span>
                                        <?php if ($quotation['priority'] !== 'medium'): ?>
                                            <div style="margin-top: 4px;">
                                                <span class="priority-badge priority-<?php echo $quotation['priority']; ?>">
                                                    <?php echo ucfirst($quotation['priority']); ?> Priority
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="quotation-details">
                                    <p><strong>Problem:</strong> <?php echo nl2br(htmlspecialchars($quotation['problem_description'])); ?></p>
                                    <?php if ($quotation['work_description']): ?>
                                        <p><strong>Work Description:</strong> <?php echo nl2br(htmlspecialchars($quotation['work_description'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($quotation['total_amount'] > 0): ?>
                                        <p><strong>Amount:</strong> <?php echo formatCurrency($quotation['total_amount']); ?></p>
                                    <?php endif; ?>

                                    <div style="display: flex; justify-content: space-between; margin-top: 12px; font-size: 12px; color: #666;">
                                        <span>Created: <?php echo formatDate($quotation['created_at']); ?></span>
                                        <?php if ($quotation['assigned_to_name']): ?>
                                            <span>Assigned to: <?php echo htmlspecialchars($quotation['assigned_to_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($quotation['approved_by_name']): ?>
                                            <span>Approved by: <?php echo htmlspecialchars($quotation['approved_by_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="quotation-actions">
                                    <button class="btn-sm btn-info" onclick="viewDetails(<?php echo $quotation['id']; ?>)">
                                        <i class="material-icons">visibility</i> View Details
                                    </button>

                                    <?php if ($_SESSION['role'] !== 'requestor' || ($quotation['status'] === 'pending' && $quotation['created_by'] == $_SESSION['user_id'])): ?>
                                        <button class="btn-sm btn-primary" onclick="editQuotation(<?php echo $quotation['id']; ?>)">
                                            <i class="material-icons">edit</i> Edit
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <?php if ($quotation['status'] === 'pending'): ?>
                                            <button class="btn-sm btn-success" onclick="sendForApproval(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">send</i> Send Quote
                                            </button>
                                        <?php elseif ($quotation['status'] === 'sent'): ?>
                                            <button class="btn-sm btn-success" onclick="approveQuotation(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">check</i> Approve
                                            </button>
                                            <button class="btn-sm btn-danger" onclick="rejectQuotation(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">close</i> Reject
                                            </button>
                                        <?php elseif ($quotation['status'] === 'approved'): ?>
                                            <button class="btn-sm btn-warning" onclick="assignTechnician(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">person_add</i> Assign
                                            </button>
                                            <button class="btn-sm btn-info" onclick="startRepair(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">build</i> Start Repair
                                            </button>
                                        <?php elseif ($quotation['status'] === 'repair_in_progress'): ?>
                                            <button class="btn-sm btn-success" onclick="completeRepair(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">done</i> Complete Repair
                                            </button>
                                        <?php elseif ($quotation['status'] === 'repair_complete'): ?>
                                            <button class="btn-sm btn-warning" onclick="generateBill(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">receipt</i> Generate Bill
                                            </button>
                                        <?php elseif ($quotation['status'] === 'bill_generated'): ?>
                                            <button class="btn-sm btn-success" onclick="recordPayment(<?php echo $quotation['id']; ?>)">
                                                <i class="material-icons">payment</i> Record Payment
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <button class="btn-sm btn-secondary" onclick="viewHistory(<?php echo $quotation['id']; ?>)">
                                        <i class="material-icons">history</i> History
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($quotations)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: #666;">
                                <i class="material-icons" style="font-size: 64px; margin-bottom: 20px;">assignment</i>
                                <h3>No quotations found</h3>
                                <p>
                                    <?php if ($_SESSION['role'] === 'requestor'): ?>
                                        Create your first quotation request to get started.
                                    <?php else: ?>
                                        Quotation requests will appear here when customers submit them.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity Sidebar -->
                <div class="activity-sidebar">
                    <h3>Recent Activity</h3>
                    <div class="activity-timeline">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="timeline-item">
                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                    <?php echo formatDate($activity['created_at'], 'd/m/Y H:i'); ?>
                                </div>
                                <div style="font-weight: 500; margin-bottom: 2px;">
                                    <?php echo htmlspecialchars($activity['user_name']); ?>
                                </div>
                                <div style="font-size: 14px;">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals will be loaded here dynamically -->
    <div id="modal-container"></div>

    <script src="../assets/js/quotation-manager.js"></script>
    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Quotation Manager initialized');
        });

        function filterQuotations() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            const cards = document.querySelectorAll('.quotation-card');

            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                const searchText = card.getAttribute('data-search');

                const statusMatch = !statusFilter || status === statusFilter;
                const searchMatch = !searchFilter || searchText.includes(searchFilter);

                if (statusMatch && searchMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('searchFilter').value = '';
            filterQuotations();
        }

        function refreshQuotations() {
            window.location.reload();
        }

        // Placeholder functions for modal actions (to be implemented)
        function showCreateModal() {
            console.log('Create modal');
        }

        function viewDetails(id) {
            console.log('View details for quotation:', id);
        }

        function editQuotation(id) {
            console.log('Edit quotation:', id);
        }

        function viewHistory(id) {
            console.log('View history for quotation:', id);
        }

        function sendForApproval(id) {
            console.log('Send for approval:', id);
        }

        function approveQuotation(id) {
            console.log('Approve quotation:', id);
        }

        function rejectQuotation(id) {
            console.log('Reject quotation:', id);
        }

        function assignTechnician(id) {
            console.log('Assign technician:', id);
        }

        function startRepair(id) {
            console.log('Start repair:', id);
        }

        function completeRepair(id) {
            console.log('Complete repair:', id);
        }

        function generateBill(id) {
            console.log('Generate bill:', id);
        }

        function recordPayment(id) {
            console.log('Record payment:', id);
        }
    </script>
</body>
</html>