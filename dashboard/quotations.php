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

// Get total count with safe array access
$totalQuotationsResult = $db->fetch(
    "SELECT COUNT(*) as count FROM quotations q
     JOIN service_requests sr ON q.request_id = sr.id
     WHERE {$whereClause}",
    $params
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
<?php include '../includes/admin_sidebar.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main Content -->
                    <div class="p-6">
                        <!-- Action Buttons -->
                        <?php if (!empty($pendingRequests)): ?>
                            <div class="mb-6">
                                <button type="button" onclick="document.getElementById('createQuotationModal').style.display='block'" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                    <span class="material-icons">receipt_long</span>
                                    <span>Create Quotation</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Filters -->
                        <div class="bg-white rounded-lg border border-blue-100 p-6 mb-6">
                            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <label for="status" class="block text-sm font-medium text-blue-900 mb-2">Status</label>
                                    <select id="status" name="status" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">All Status</option>
                                        <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
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
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                        <span class="material-icons text-sm">search</span>
                                        <span>Filter</span>
                                    </button>
                                    <a href="quotations.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">Clear</a>
                                </div>
                            </form>
                        </div>

                        <!-- Pending Requests for Quotations -->
                        <?php if (!empty($pendingRequests)): ?>
                            <div class="bg-white rounded-lg border border-blue-100 mb-6">
                                <div class="p-6 border-b border-blue-100">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-blue-900 text-xl font-semibold">Pending Requests (<?php echo count($pendingRequests); ?>)</h3>
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
                                                        <button type="button" onclick="createQuotationFor(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['registration_number']); ?>', '<?php echo htmlspecialchars($request['problem_description']); ?>')" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors flex items-center gap-1">
                                                            <span class="material-icons text-sm">receipt_long</span>
                                                            <span>Create Quote</span>
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
                        <div class="bg-white rounded-lg border border-blue-100">
                            <div class="p-6 border-b border-blue-100">
                                <h3 class="text-blue-900 text-xl font-semibold">All Quotations (<?php echo $totalQuotations; ?>)</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">ID</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Requestor</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Amount</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Approver</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Created</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-blue-100">
                                        <?php if (empty($quotations)): ?>
                                            <tr>
                                                <td colspan="8" class="px-6 py-8 text-center text-blue-600">No quotations found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($quotations as $quotation): ?>
                                                <tr class="hover:bg-blue-50">
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">#<?php echo $quotation['id']; ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($quotation['registration_number']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($quotation['requestor_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">$<?php echo number_format($quotation['amount'], 2); ?></td>
                                                    <td class="px-6 py-4 text-sm">
                                                        <?php
                                                        $status = $quotation['approval_status'] ?: 'sent';
                                                        $statusColors = [
                                                            'sent' => 'bg-yellow-100 text-yellow-800',
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'approved' => 'bg-green-100 text-green-800',
                                                            'rejected' => 'bg-red-100 text-red-800'
                                                        ];
                                                        $statusClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                                        ?>
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                            <?php echo ucfirst($status); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm">
                                                        <?php if ($quotation['approver_name']): ?>
                                                            <?php echo htmlspecialchars($quotation['approver_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-blue-400">Not assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?></td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex gap-2">
                                                            <button type="button" onclick="viewQuotation(<?php echo $quotation['id']; ?>)" class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-sm hover:bg-gray-200 transition-colors">
                                                                <span class="material-icons text-sm">visibility</span>
                                                            </button>
                                                            <?php if (!$quotation['approval_status'] || $quotation['approval_status'] === 'pending'): ?>
                                                                <button type="button" onclick="editQuotation(<?php echo $quotation['id']; ?>)" class="bg-blue-600 text-white px-2 py-1 rounded text-sm hover:bg-blue-700 transition-colors">
                                                                    <span class="material-icons text-sm">edit</span>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if (!$quotation['approver_name']): ?>
                                                                <button type="button" onclick="sendToApprover(<?php echo $quotation['id']; ?>)" class="bg-green-600 text-white px-2 py-1 rounded text-sm hover:bg-green-700 transition-colors">
                                                                    <span class="material-icons text-sm">send</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($pagination['total_pages'] > 1): ?>
                                <div class="p-6 border-t border-blue-100">
                                    <div class="flex justify-between items-center">
                                        <span class="text-blue-600 text-sm">
                                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalQuotations); ?> of <?php echo $totalQuotations; ?> quotations
                                        </span>
                                        <div class="flex gap-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" class="bg-gray-100 text-gray-600 px-3 py-1 rounded hover:bg-gray-200 transition-colors flex items-center gap-1">
                                                    <span class="material-icons text-sm">chevron_left</span>
                                                </a>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" class="px-3 py-1 rounded text-sm transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>

                                            <?php if ($page < $pagination['total_pages']): ?>
                                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" class="bg-gray-100 text-gray-600 px-3 py-1 rounded hover:bg-gray-200 transition-colors flex items-center gap-1">
                                                    <span class="material-icons text-sm">chevron_right</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
            alert('View quotation functionality will be implemented');
        }

        function editQuotation(quotationId) {
            // Implement quotation edit functionality
            alert('Edit quotation functionality will be implemented');
        }

        // Modal handling
        document.getElementById('createQuotationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>