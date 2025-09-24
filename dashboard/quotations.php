<?php
require_once '../includes/functions.php';

requireRole('admin');

// Handle pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$status_filter = sanitize($_GET['status'] ?? '');
$date_from = sanitize($_GET['date_from'] ?? '');
$date_to = sanitize($_GET['date_to'] ?? '');

// Build query conditions
$conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $conditions[] = "q.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $conditions[] = "DATE(q.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(q.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $conditions);

// Get total count
$totalQuotations = $db->fetch(
    "SELECT COUNT(*) as count FROM quotations q WHERE {$whereClause}",
    $params
)['count'];

// Get quotations with pagination
$quotations = $db->fetchAll(
    "SELECT q.*, sr.problem_description, v.registration_number, u.full_name as requestor_name,
            a.status as approval_status, a.approved_at, approver.full_name as approver_name
     FROM quotations q
     JOIN service_requests sr ON q.request_id = sr.id
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     LEFT JOIN approvals a ON q.id = a.quotation_id
     LEFT JOIN users approver ON a.approver_id = approver.id
     WHERE {$whereClause}
     ORDER BY q.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$pagination = paginate($page, $totalQuotations, $limit);

// Get pending service requests for new quotations
$pendingRequests = $db->fetchAll(
    "SELECT sr.*, v.registration_number, u.full_name as requestor_name
     FROM service_requests sr
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     LEFT JOIN quotations q ON sr.id = q.request_id
     WHERE sr.status = 'pending' AND q.id IS NULL
     ORDER BY sr.created_at DESC"
);

// Get approvers for sending quotations
$approvers = getUsers($_SESSION['organization_id'], 'approver');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Management - <?php echo APP_NAME; ?></title>
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
                    <li><a href="admin.php">
                        <span class="material-icons">dashboard</span>
                        Dashboard
                    </a></li>
                    <li><a href="users.php">
                        <span class="material-icons">group</span>
                        User Management
                    </a></li>
                    <li><a href="requests.php">
                        <span class="material-icons">build</span>
                        Service Requests
                    </a></li>
                    <li><a href="quotations.php" class="active">
                        <span class="material-icons">receipt</span>
                        Quotations
                    </a></li>
                    <li><a href="reports.php">
                        <span class="material-icons">assessment</span>
                        Reports
                    </a></li>
                    <li><a href="vehicles.php">
                        <span class="material-icons">directions_car</span>
                        Vehicles
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
            <h1 class="header-title">Quotation Management</h1>
            <div class="header-actions">
                <?php if (!empty($pendingRequests)): ?>
                    <button type="button" class="btn btn-primary" data-modal="createQuotationModal">
                        <span class="material-icons left">receipt_long</span>
                        Create Quotation
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-3">
                            <div class="input-field">
                                <select id="status" name="status" data-auto-submit>
                                    <option value="">All Status</option>
                                    <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <label for="status">Status</label>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="input-field">
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                                <label for="date_from">From Date</label>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="input-field">
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                                <label for="date_to">To Date</label>
                            </div>
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons left">search</span>
                                Filter
                            </button>
                            <a href="quotations.php" class="btn btn-outlined">Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Pending Requests for Quotations -->
            <?php if (!empty($pendingRequests)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Pending Requests (<?php echo count($pendingRequests); ?>)</h3>
                        <span class="text-muted">Requests waiting for quotations</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Requestor</th>
                                    <th>Problem</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td>#<?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($request['problem_description'], 0, 50)); ?>...</td>
                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-primary"
                                                    onclick="createQuotationFor(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['registration_number']); ?>', '<?php echo htmlspecialchars($request['problem_description']); ?>')">
                                                <span class="material-icons">receipt_long</span>
                                                Create Quote
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quotations Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">All Quotations (<?php echo $totalQuotations; ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vehicle</th>
                                <th>Requestor</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Approver</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotations)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No quotations found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotations as $quotation): ?>
                                    <tr>
                                        <td>#<?php echo $quotation['id']; ?></td>
                                        <td><?php echo htmlspecialchars($quotation['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['requestor_name']); ?></td>
                                        <td><?php echo formatCurrency($quotation['amount']); ?></td>
                                        <td>
                                            <?php if ($quotation['approval_status']): ?>
                                                <?php echo getStatusBadge($quotation['approval_status']); ?>
                                            <?php else: ?>
                                                <?php echo getStatusBadge('sent'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($quotation['approver_name']): ?>
                                                <?php echo htmlspecialchars($quotation['approver_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($quotation['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-outlined"
                                                    onclick="viewQuotation(<?php echo $quotation['id']; ?>)">
                                                <span class="material-icons">visibility</span>
                                            </button>
                                            <?php if (!$quotation['approval_status'] || $quotation['approval_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-small btn-primary"
                                                        onclick="editQuotation(<?php echo $quotation['id']; ?>)">
                                                    <span class="material-icons">edit</span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (!$quotation['approver_name']): ?>
                                                <button type="button" class="btn btn-small btn-success"
                                                        onclick="sendToApprover(<?php echo $quotation['id']; ?>)">
                                                    <span class="material-icons">send</span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-content">
                        <div class="d-flex justify-between align-center">
                            <span class="text-muted">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalQuotations); ?> of <?php echo $totalQuotations; ?> quotations
                            </span>
                            <div>
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-outlined btn-small">
                                        <span class="material-icons">chevron_left</span>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>"
                                       class="btn btn-small <?php echo $i === $page ? 'btn-primary' : 'btn-outlined'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-outlined btn-small">
                                        <span class="material-icons">chevron_right</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Quotation Modal -->
    <div id="createQuotationModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Create Quotation</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="createQuotationForm" data-ajax action="../api/quotations.php" data-reset-on-success data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" id="create_request_id" name="request_id">
                    <?php echo csrfField(); ?>

                    <div class="alert alert-info">
                        <span class="material-icons">info</span>
                        <div>
                            <strong id="request_vehicle_info"></strong>
                            <p id="request_problem_info" class="text-muted"></p>
                        </div>
                    </div>

                    <div class="input-field">
                        <input type="number" id="create_amount" name="amount" required min="1" step="0.01">
                        <label for="create_amount">Quotation Amount (₹)</label>
                    </div>

                    <div class="input-field">
                        <textarea id="create_work_description" name="work_description" required rows="4"></textarea>
                        <label for="create_work_description">Work Description</label>
                    </div>

                    <div class="input-field">
                        <select id="create_send_to_approver" name="send_to_approver">
                            <option value="">Select Approver (Optional)</option>
                            <?php foreach ($approvers as $approver): ?>
                                <option value="<?php echo $approver['id']; ?>">
                                    <?php echo htmlspecialchars($approver['full_name']); ?> (<?php echo htmlspecialchars($approver['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="create_send_to_approver">Send to Approver</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quotation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Send to Approver Modal -->
    <div id="sendToApproverModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Send Quotation to Approver</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="sendToApproverForm" data-ajax action="../api/quotations.php" data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="send_to_approver">
                    <input type="hidden" id="send_quotation_id" name="quotation_id">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <select id="send_approver_id" name="approver_id" required>
                            <option value="">Select Approver</option>
                            <?php foreach ($approvers as $approver): ?>
                                <option value="<?php echo $approver['id']; ?>">
                                    <?php echo htmlspecialchars($approver['full_name']); ?> (<?php echo htmlspecialchars($approver['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="send_approver_id">Approver</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Quotation</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function createQuotationFor(requestId, vehicle, problem) {
            document.getElementById('create_request_id').value = requestId;
            document.getElementById('request_vehicle_info').textContent = `Vehicle: ${vehicle}`;
            document.getElementById('request_problem_info').textContent = `Problem: ${problem}`;
            Material.openModal('createQuotationModal');
        }

        function sendToApprover(quotationId) {
            document.getElementById('send_quotation_id').value = quotationId;
            Material.openModal('sendToApproverModal');
        }

        function viewQuotation(quotationId) {
            // Implement quotation view/details
            Material.showSnackbar('View quotation functionality will be implemented', 'info');
        }

        function editQuotation(quotationId) {
            // Implement quotation edit functionality
            Material.showSnackbar('Edit quotation functionality will be implemented', 'info');
        }
    </script>
</body>
</html>