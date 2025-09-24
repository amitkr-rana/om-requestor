<?php
require_once '../includes/functions.php';

requireRole('approver');

// Get approver's statistics
$stats = [
    'pending_approvals' => $db->fetch("SELECT COUNT(*) as count FROM approvals WHERE approver_id = ? AND status = 'pending'", [$_SESSION['user_id']])['count'],
    'approved_count' => $db->fetch("SELECT COUNT(*) as count FROM approvals WHERE approver_id = ? AND status = 'approved'", [$_SESSION['user_id']])['count'],
    'rejected_count' => $db->fetch("SELECT COUNT(*) as count FROM approvals WHERE approver_id = ? AND status = 'rejected'", [$_SESSION['user_id']])['count'],
    'total_amount_approved' => $db->fetch(
        "SELECT COALESCE(SUM(q.amount), 0) as total
         FROM approvals a
         JOIN quotations q ON a.quotation_id = q.id
         WHERE a.approver_id = ? AND a.status = 'approved'",
        [$_SESSION['user_id']]
    )['total']
];

// Get pending quotations for approval
$pendingApprovals = $db->fetchAll(
    "SELECT a.*, q.amount, q.work_description, q.created_at as quotation_date,
            sr.problem_description, v.registration_number, u.full_name as requestor_name
     FROM approvals a
     JOIN quotations q ON a.quotation_id = q.id
     JOIN service_requests sr ON q.request_id = sr.id
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     WHERE a.approver_id = ? AND a.status = 'pending'
     ORDER BY a.created_at DESC",
    [$_SESSION['user_id']]
);

// Get recent approved/rejected quotations
$recentDecisions = $db->fetchAll(
    "SELECT a.*, q.amount, q.work_description, q.created_at as quotation_date,
            sr.problem_description, v.registration_number, u.full_name as requestor_name
     FROM approvals a
     JOIN quotations q ON a.quotation_id = q.id
     JOIN service_requests sr ON q.request_id = sr.id
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     WHERE a.approver_id = ? AND a.status IN ('approved', 'rejected')
     ORDER BY a.approved_at DESC
     LIMIT 10",
    [$_SESSION['user_id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approver Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/material.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?></div>
                <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                <p class="user-role"><?php echo ucfirst($_SESSION['role']); ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="approver.php" class="active">
                        <span class="material-icons">dashboard</span>
                        Dashboard
                    </a></li>
                    <li><a href="pending-approvals.php">
                        <span class="material-icons">pending</span>
                        Pending Approvals
                        <?php if ($stats['pending_approvals'] > 0): ?>
                            <span class="badge badge-warning"><?php echo $stats['pending_approvals']; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="approval-history.php">
                        <span class="material-icons">history</span>
                        Approval History
                    </a></li>
                    <li><a href="approval-reports.php">
                        <span class="material-icons">assessment</span>
                        Reports
                    </a></li>
                    <li><a href="../api/auth.php?action=logout" data-confirm="Are you sure you want to logout?">
                        <span class="material-icons">logout</span>
                        Logout
                    </a></li>
                </ul>
            </nav>
        </aside>

        <!-- Header -->
        <header class="dashboard-header">
            <h1 class="header-title">Approver Dashboard</h1>
            <div class="header-actions">
                <span class="text-muted"><?php echo $_SESSION['organization_name']; ?></span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
                    <span class="material-icons">pending</span>
                    <h2 class="stat-number"><?php echo $stats['pending_approvals']; ?></h2>
                    <p class="stat-label">Pending Approvals</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                    <span class="material-icons">check_circle</span>
                    <h2 class="stat-number"><?php echo $stats['approved_count']; ?></h2>
                    <p class="stat-label">Approved</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #F44336, #D32F2F);">
                    <span class="material-icons">cancel</span>
                    <h2 class="stat-number"><?php echo $stats['rejected_count']; ?></h2>
                    <p class="stat-label">Rejected</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2);">
                    <span class="material-icons">account_balance_wallet</span>
                    <h2 class="stat-number"><?php echo formatCurrency($stats['total_amount_approved']); ?></h2>
                    <p class="stat-label">Total Approved</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-content">
                    <div class="action-grid">
                        <div class="action-card" onclick="location.href='pending-approvals.php'">
                            <span class="material-icons">pending</span>
                            <h4 class="action-title">Pending Approvals</h4>
                            <p class="action-description">Review quotations awaiting approval</p>
                            <?php if ($stats['pending_approvals'] > 0): ?>
                                <span class="badge badge-warning"><?php echo $stats['pending_approvals']; ?> pending</span>
                            <?php endif; ?>
                        </div>

                        <div class="action-card" onclick="location.href='approval-history.php'">
                            <span class="material-icons">history</span>
                            <h4 class="action-title">View History</h4>
                            <p class="action-description">Check approval history and decisions</p>
                        </div>

                        <div class="action-card" onclick="location.href='approval-reports.php'">
                            <span class="material-icons">assessment</span>
                            <h4 class="action-title">Generate Reports</h4>
                            <p class="action-description">Create approval and expense reports</p>
                        </div>

                        <div class="action-card" onclick="bulkApproveModal()">
                            <span class="material-icons">done_all</span>
                            <h4 class="action-title">Bulk Actions</h4>
                            <p class="action-description">Approve or reject multiple quotations</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <?php if (!empty($pendingApprovals)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Pending Approvals (<?php echo count($pendingApprovals); ?>)</h3>
                        <div class="table-actions">
                            <button type="button" class="btn btn-success" onclick="bulkApprove('approved')">
                                <span class="material-icons">done_all</span>
                                Bulk Approve
                            </button>
                            <button type="button" class="btn btn-error" onclick="bulkApprove('rejected')">
                                <span class="material-icons">block</span>
                                Bulk Reject
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Requestor</th>
                                    <th>Problem</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingApprovals as $approval): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="approval-checkbox" value="<?php echo $approval['id']; ?>">
                                        </td>
                                        <td>#<?php echo $approval['quotation_id']; ?></td>
                                        <td><?php echo htmlspecialchars($approval['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($approval['requestor_name']); ?></td>
                                        <td>
                                            <span data-tooltip="<?php echo htmlspecialchars($approval['problem_description']); ?>">
                                                <?php echo htmlspecialchars(substr($approval['problem_description'], 0, 30)); ?>...
                                            </span>
                                        </td>
                                        <td><strong><?php echo formatCurrency($approval['amount']); ?></strong></td>
                                        <td><?php echo formatDate($approval['quotation_date']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-outlined"
                                                    onclick="viewApprovalDetails(<?php echo $approval['id']; ?>)">
                                                <span class="material-icons">visibility</span>
                                            </button>
                                            <button type="button" class="btn btn-small btn-success"
                                                    onclick="processApproval(<?php echo $approval['id']; ?>, 'approved')">
                                                <span class="material-icons">check</span>
                                            </button>
                                            <button type="button" class="btn btn-small btn-error"
                                                    onclick="processApproval(<?php echo $approval['id']; ?>, 'rejected')">
                                                <span class="material-icons">close</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-content text-center">
                        <span class="material-icons" style="font-size: 4rem; color: var(--md-sys-color-on-surface-variant);">check_circle_outline</span>
                        <h3>No Pending Approvals</h3>
                        <p class="text-muted">All quotations have been processed.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Decisions -->
            <?php if (!empty($recentDecisions)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Decisions</h3>
                        <a href="approval-history.php" class="btn btn-outlined">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Requestor</th>
                                    <th>Amount</th>
                                    <th>Decision</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDecisions as $decision): ?>
                                    <tr>
                                        <td>#<?php echo $decision['quotation_id']; ?></td>
                                        <td><?php echo htmlspecialchars($decision['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($decision['requestor_name']); ?></td>
                                        <td><?php echo formatCurrency($decision['amount']); ?></td>
                                        <td><?php echo getStatusBadge($decision['status']); ?></td>
                                        <td><?php echo formatDate($decision['approved_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Approval Details Modal -->
    <div id="approvalDetailsModal" class="modal-overlay" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Quotation Details</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="modal-content" id="approvalDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-actions" id="approvalDetailsActions">
                <button type="button" class="btn btn-text" data-close-modal>Close</button>
                <button type="button" class="btn btn-success" onclick="processApprovalFromModal('approved')">
                    <span class="material-icons left">check</span>
                    Approve
                </button>
                <button type="button" class="btn btn-error" onclick="processApprovalFromModal('rejected')">
                    <span class="material-icons left">close</span>
                    Reject
                </button>
            </div>
        </div>
    </div>

    <!-- Process Approval Modal -->
    <div id="processApprovalModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="processModalTitle">Process Approval</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="processApprovalForm" data-ajax action="../api/approvals.php" data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="process">
                    <input type="hidden" id="process_approval_id" name="approval_id">
                    <input type="hidden" id="process_status" name="status">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <textarea id="process_notes" name="notes" rows="3"></textarea>
                        <label for="process_notes">Notes (Optional)</label>
                    </div>

                    <p class="text-muted" style="font-size: 0.875rem;">
                        Please provide any additional notes for your decision.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" id="processSubmitBtn" class="btn btn-primary">Process</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        let currentApprovalId = null;

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.approval-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function getSelectedApprovals() {
            const checkboxes = document.querySelectorAll('.approval-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        async function viewApprovalDetails(approvalId) {
            try {
                Material.showLoading();
                const response = await fetch(`../api/approvals.php?action=get&id=${approvalId}`);
                const data = await response.json();

                if (data.error) {
                    Material.showSnackbar(data.error, 'error');
                    return;
                }

                // Populate modal with approval details
                document.getElementById('approvalDetailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <h4>Request Information</h4>
                            <p><strong>Vehicle:</strong> ${data.registration_number}</p>
                            <p><strong>Requestor:</strong> ${data.requestor_name}</p>
                            <p><strong>Problem:</strong> ${data.problem_description}</p>
                        </div>
                        <div class="col-6">
                            <h4>Quotation Details</h4>
                            <p><strong>Amount:</strong> ${app.formatCurrency(data.amount)}</p>
                            <p><strong>Work Description:</strong> ${data.work_description}</p>
                            <p><strong>Created:</strong> ${app.formatDate(data.quotation_date)}</p>
                        </div>
                    </div>
                `;

                currentApprovalId = approvalId;
                Material.openModal('approvalDetailsModal');
            } catch (error) {
                Material.showSnackbar('Failed to load approval details', 'error');
            } finally {
                Material.hideLoading();
            }
        }

        function processApproval(approvalId, status) {
            currentApprovalId = approvalId;
            document.getElementById('process_approval_id').value = approvalId;
            document.getElementById('process_status').value = status;

            const title = status === 'approved' ? 'Approve Quotation' : 'Reject Quotation';
            const btnClass = status === 'approved' ? 'btn-success' : 'btn-error';
            const btnText = status === 'approved' ? 'Approve' : 'Reject';

            document.getElementById('processModalTitle').textContent = title;
            document.getElementById('processSubmitBtn').className = `btn ${btnClass}`;
            document.getElementById('processSubmitBtn').textContent = btnText;

            Material.openModal('processApprovalModal');
        }

        function processApprovalFromModal(status) {
            Material.closeModal();
            processApproval(currentApprovalId, status);
        }

        async function bulkApprove(status) {
            const selectedIds = getSelectedApprovals();

            if (selectedIds.length === 0) {
                Material.showSnackbar('Please select at least one quotation', 'warning');
                return;
            }

            const action = status === 'approved' ? 'approve' : 'reject';
            const message = `Are you sure you want to ${action} ${selectedIds.length} quotation(s)?`;

            Material.confirm(message, async (confirmed) => {
                if (!confirmed) return;

                try {
                    Material.showLoading();

                    const formData = new FormData();
                    formData.append('action', 'bulk_process');
                    formData.append('approval_ids', JSON.stringify(selectedIds));
                    formData.append('status', status);
                    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                    const response = await fetch('../api/approvals.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    Material.hideLoading();

                    if (result.success) {
                        Material.showSnackbar(result.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Material.showSnackbar(result.error, 'error');
                    }
                } catch (error) {
                    Material.hideLoading();
                    Material.showSnackbar('Network error occurred', 'error');
                }
            });
        }
    </script>
</body>
</html>