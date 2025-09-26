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

// Organization filtering - respect current organization context via users table
if ($_SESSION['organization_id'] != 2) { // If not Om Engineers system admin
    $conditions[] = "u.organization_id = ?";
    $params[] = $_SESSION['organization_id'];
}

if ($status_filter) {
    switch ($status_filter) {
        case 'pending_to_be_sent':
            $conditions[] = "sr.status = 'pending'";
            break;
        case 'sent_for_approval':
            $conditions[] = "sr.status = 'quoted'";
            break;
        case 'approved':
            $conditions[] = "sr.status = 'approved'";
            break;
        case 'rejected':
            $conditions[] = "sr.status = 'rejected'";
            break;
    }
}

if ($date_from) {
    $conditions[] = "DATE(sr.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(sr.created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $conditions);

// Get quotation statistics for dashboard cards
$statsConditions = ["1=1"];
$statsParams = [];

// Organization filtering for statistics (same as main query)
if ($_SESSION['organization_id'] != 2) { // If not Om Engineers system admin
    $statsConditions[] = "u.organization_id = ?";
    $statsParams[] = $_SESSION['organization_id'];
}

$statsWhereClause = implode(' AND ', $statsConditions);

// Get total count for dashboard tile (all service requests = all quotations in your workflow)
$totalQuotationsResult = $db->fetch(
    "SELECT COUNT(*) as count FROM service_requests sr
     JOIN users u ON sr.user_id = u.id
     WHERE {$statsWhereClause}",
    $statsParams
);
$totalQuotations = $totalQuotationsResult ? $totalQuotationsResult['count'] : 0;

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

// Get pending service requests for new quotations - filter by organization via users table
$pendingConditions = ["sr.status = 'pending'", "q.id IS NULL"];
$pendingParams = [];
if ($_SESSION['organization_id'] != 2) { // If not Om Engineers system admin
    $pendingConditions[] = "u.organization_id = ?";
    $pendingParams[] = $_SESSION['organization_id'];
}

$pendingWhereClause = implode(' AND ', $pendingConditions);
$pendingRequests = $db->fetchAll(
    "SELECT sr.*, v.registration_number, u.full_name as requestor_name
     FROM service_requests sr
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     LEFT JOIN quotations q ON sr.id = q.request_id
     WHERE {$pendingWhereClause}
     ORDER BY sr.created_at DESC",
    $pendingParams
);

// Get approvers for sending quotations
$approvers = getUsers($_SESSION['organization_id'], 'approver');

// Count pending quotations (service requests with status 'pending')
$pendingToBeSentResult = $db->fetch(
    "SELECT COUNT(*) as count FROM service_requests sr
     JOIN users u ON sr.user_id = u.id
     WHERE {$statsWhereClause} AND sr.status = 'pending'",
    $statsParams
);
$pendingToBeSent = $pendingToBeSentResult ? $pendingToBeSentResult['count'] : 0;

// Count sent quotations (service requests with status 'quoted')
$sentForApprovalResult = $db->fetch(
    "SELECT COUNT(*) as count FROM service_requests sr
     JOIN users u ON sr.user_id = u.id
     WHERE {$statsWhereClause} AND sr.status = 'quoted'",
    $statsParams
);
$sentForApproval = $sentForApprovalResult ? $sentForApprovalResult['count'] : 0;

// Count approved quotations (service requests with status 'approved')
$approvedQuotationsResult = $db->fetch(
    "SELECT COUNT(*) as count FROM service_requests sr
     JOIN users u ON sr.user_id = u.id
     WHERE {$statsWhereClause} AND sr.status = 'approved'",
    $statsParams
);
$approvedQuotations = $approvedQuotationsResult ? $approvedQuotationsResult['count'] : 0;

// Count rejected quotations (service requests with status 'rejected')
$rejectedQuotationsResult = $db->fetch(
    "SELECT COUNT(*) as count FROM service_requests sr
     JOIN users u ON sr.user_id = u.id
     WHERE {$statsWhereClause} AND sr.status = 'rejected'",
    $statsParams
);
$rejectedQuotations = $rejectedQuotationsResult ? $rejectedQuotationsResult['count'] : 0;

// Set page title based on status filter
$pageTitle = 'Quotation Management';
if ($status_filter) {
    switch($status_filter) {
        case 'pending':
            $pageTitle = 'Pending Quotation Requests';
            break;
        case 'sent':
            $pageTitle = 'Quotations Sent for Approval';
            break;
        case 'approved':
            $pageTitle = 'Approved Quotations';
            break;
        case 'rejected':
            $pageTitle = 'Rejected Quotations';
            break;
        default:
            $pageTitle = 'Quotation Management';
    }
}

include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar_new.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main Content -->
                    <div class="p-6">
                        <!-- Quotation Statistics -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-lg">receipt_long</span>
                                    </div>
                                    <div>
                                        <p class="text-blue-600 text-xs font-medium uppercase tracking-wide">Total Quotations</p>
                                        <p class="text-blue-900 text-xl font-bold"><?php echo $totalQuotations; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-yellow-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                                        <span class="material-icons text-yellow-600 text-lg">pending_actions</span>
                                    </div>
                                    <div>
                                        <p class="text-yellow-600 text-xs font-medium uppercase tracking-wide">Pending</p>
                                        <p class="text-yellow-900 text-xl font-bold"><?php echo $pendingToBeSent; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-orange-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                        <span class="material-icons text-orange-600 text-lg">send</span>
                                    </div>
                                    <div>
                                        <p class="text-orange-600 text-xs font-medium uppercase tracking-wide">Sent</p>
                                        <p class="text-orange-900 text-xl font-bold"><?php echo $sentForApproval; ?></p>
                                    </div>
                                </div>
                            </div>


                            <div class="bg-white rounded-lg p-4 border border-green-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-lg">check_circle</span>
                                    </div>
                                    <div>
                                        <p class="text-green-600 text-xs font-medium uppercase tracking-wide">Approved</p>
                                        <p class="text-green-900 text-xl font-bold"><?php echo $approvedQuotations; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-red-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                        <span class="material-icons text-red-600 text-lg">cancel</span>
                                    </div>
                                    <div>
                                        <p class="text-red-600 text-xs font-medium uppercase tracking-wide">Rejected</p>
                                        <p class="text-red-900 text-xl font-bold"><?php echo $rejectedQuotations; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters and Actions -->
                        <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                            <div class="p-4 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-blue-900 text-lg font-semibold">Filters</h2>
                                    <a href="quotation_creator.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                        <span class="material-icons text-sm">add</span>
                                        Quick Quotation
                                    </a>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="p-4 bg-gray-50">
                                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-blue-900 mb-2">Status</label>
                                        <select id="status" name="status" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Status</option>
                                            <option value="pending_to_be_sent" <?php echo $status_filter === 'pending_to_be_sent' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="sent_for_approval" <?php echo $status_filter === 'sent_for_approval' ? 'selected' : ''; ?>>Sent</option>
                                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="date_from" class="block text-sm font-medium text-blue-900 mb-2">From Date</label>
                                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="date_to" class="block text-sm font-medium text-blue-900 mb-2">To Date</label>
                                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                            <span class="material-icons text-sm">search</span>
                                            Search
                                        </button>
                                        <a href="quotations.php" class="border border-blue-200 hover:bg-blue-50 text-blue-700 px-4 py-2 rounded-lg font-medium">Clear</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Pending Requests for Quotations -->
                        <?php if (!empty($pendingRequests)): ?>
                            <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                                <div class="p-4 border-b border-blue-100">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-blue-900 text-lg font-semibold">Pending Requests (<?php echo count($pendingRequests); ?>)</h3>
                                        <span class="text-blue-600 text-sm">Requests waiting for quotations</span>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-blue-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">ID</th>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Requestor</th>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Problem</th>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Date</th>
                                                <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-blue-100">
                                            <?php foreach ($pendingRequests as $request): ?>
                                                <tr class="hover:bg-blue-50">
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">#<?php echo $request['id']; ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($request['registration_number']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm max-w-xs truncate"><?php echo htmlspecialchars(substr($request['problem_description'], 0, 50)); ?>...</td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                    <td class="px-6 py-4">
                                                        <a href="quotation_creator.php?action=create&request_id=<?php echo $request['id']; ?>" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors inline-flex items-center gap-1">
                                                            <span class="material-icons text-sm">receipt_long</span>
                                                            <span>Create Quote</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
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

        function sendToApprover(quotationId) {
            document.getElementById('send_quotation_id').value = quotationId;
            Material.openModal('sendToApproverModal');
        }

        function viewQuotation(quotationId) {
            // Implement quotation view/details
            alert('View quotation functionality will be implemented');
        }

        function editQuotation(quotationId) {
            // Implement quotation edit functionality
            alert('Edit quotation functionality will be implemented');
        }

    </script>
</body>
</html>