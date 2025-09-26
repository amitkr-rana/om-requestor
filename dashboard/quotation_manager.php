<?php
require_once '../includes/functions.php';

requireLogin();

// Handle pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$status_filter = sanitize($_GET['status'] ?? '');
$priority_filter = sanitize($_GET['priority'] ?? '');
$date_from = sanitize($_GET['date_from'] ?? '');
$date_to = sanitize($_GET['date_to'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build query conditions
$conditions = [];
$params = [];

// Organization filtering based on user role
if ($_SESSION['role'] === 'requestor') {
    // Requestors see only their own quotations
    $conditions[] = "q.created_by = ? AND q.organization_id = ?";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['organization_id'];
} else {
    // Admin/approver see all quotations in their organization
    if ($_SESSION['organization_id'] != 15) { // If not Om Engineers system admin
        $conditions[] = "q.organization_id = ?";
        $params[] = $_SESSION['organization_id'];
    }
}

if ($status_filter) {
    $conditions[] = "q.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $conditions[] = "q.priority = ?";
    $params[] = $priority_filter;
}

if ($date_from) {
    $conditions[] = "DATE(q.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(q.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $conditions[] = "(q.quotation_number LIKE ? OR q.customer_name LIKE ? OR q.vehicle_registration LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
$totalResult = $db->fetch(
    "SELECT COUNT(*) as count FROM quotations_new q {$whereClause}",
    $params
);
$totalQuotations = $totalResult ? $totalResult['count'] : 0;

// Get quotations with pagination
$quotations = $db->fetchAll(
    "SELECT q.*, u.full_name as created_by_name, a.full_name as approved_by_name, t.full_name as assigned_to_name
     FROM quotations_new q
     LEFT JOIN users_new u ON q.created_by = u.id
     LEFT JOIN users_new a ON q.approved_by = a.id
     LEFT JOIN users_new t ON q.assigned_to = t.id
     {$whereClause}
     ORDER BY q.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$pagination = paginate($page, $totalQuotations, $limit);

// Get statistics for dashboard cards - independent of filters
$statsConditions = [];
$statsParams = [];

if ($_SESSION['role'] === 'requestor') {
    $statsConditions[] = "q.created_by = ? AND q.organization_id = ?";
    $statsParams[] = $_SESSION['user_id'];
    $statsParams[] = $_SESSION['organization_id'];
} else {
    if ($_SESSION['organization_id'] != 15) {
        $statsConditions[] = "q.organization_id = ?";
        $statsParams[] = $_SESSION['organization_id'];
    }
}

$statsWhereClause = !empty($statsConditions) ? 'WHERE ' . implode(' AND ', $statsConditions) : '';

// Get statistics
$totalQuotationsStats = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause}", $statsParams)['count'] ?? 0;
$pendingQuotations = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause} AND q.status = 'pending'", array_merge($statsParams, ['pending']))['count'] ?? 0;
$sentQuotations = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause} AND q.status = 'sent'", array_merge($statsParams, ['sent']))['count'] ?? 0;
$approvedQuotations = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause} AND q.status = 'approved'", array_merge($statsParams, ['approved']))['count'] ?? 0;
$inProgressQuotations = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause} AND q.status = 'repair_in_progress'", array_merge($statsParams, ['repair_in_progress']))['count'] ?? 0;
$completedQuotations = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q {$statsWhereClause} AND q.status IN ('repair_complete', 'bill_generated', 'paid')", $statsParams)['count'] ?? 0;

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

// Set page title
$pageTitle = 'Quotation Management';
?>

<?php
include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar_new.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main Content -->
                    <div class="p-6">

                        <!-- Quotation Statistics -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-lg">receipt_long</span>
                                    </div>
                                    <div>
                                        <p class="text-blue-600 text-xs font-medium uppercase tracking-wide">Total Quotations</p>
                                        <p class="text-blue-900 text-xl font-bold"><?php echo $totalQuotationsStats; ?></p>
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
                                        <p class="text-yellow-900 text-xl font-bold"><?php echo $pendingQuotations; ?></p>
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
                                        <p class="text-orange-900 text-xl font-bold"><?php echo $sentQuotations; ?></p>
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

                            <?php if ($_SESSION['role'] !== 'requestor'): ?>
                            <div class="bg-white rounded-lg p-4 border border-purple-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <span class="material-icons text-purple-600 text-lg">build</span>
                                    </div>
                                    <div>
                                        <p class="text-purple-600 text-xs font-medium uppercase tracking-wide">In Progress</p>
                                        <p class="text-purple-900 text-xl font-bold"><?php echo $inProgressQuotations; ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="bg-white rounded-lg p-4 border border-teal-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center">
                                        <span class="material-icons text-teal-600 text-lg">done_all</span>
                                    </div>
                                    <div>
                                        <p class="text-teal-600 text-xs font-medium uppercase tracking-wide">Completed</p>
                                        <p class="text-teal-900 text-xl font-bold"><?php echo $completedQuotations; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters and Actions -->
                        <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                            <div class="p-4 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-blue-900 text-lg font-semibold">Filters</h2>
                                    <div class="flex gap-3">
                                        <?php if ($_SESSION['role'] === 'requestor'): ?>
                                            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2" onclick="showCreateModal()">
                                                <span class="material-icons text-sm">add</span>
                                                Create Quotation
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <a href="quotation_creator.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                                <span class="material-icons text-sm">flash_on</span>
                                                Quick Quotation
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="p-4 bg-gray-50">
                                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-blue-900 mb-2">Status</label>
                                        <select id="status" name="status" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="repair_in_progress" <?php echo $status_filter === 'repair_in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="repair_complete" <?php echo $status_filter === 'repair_complete' ? 'selected' : ''; ?>>Repair Complete</option>
                                            <option value="bill_generated" <?php echo $status_filter === 'bill_generated' ? 'selected' : ''; ?>>Bill Generated</option>
                                            <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="priority" class="block text-sm font-medium text-blue-900 mb-2">Priority</label>
                                        <select id="priority" name="priority" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Priority</option>
                                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
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
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-blue-900 mb-2">Search</label>
                                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search quotations..." class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                            <span class="material-icons text-sm">search</span>
                                            Search
                                        </button>
                                        <a href="quotation_manager.php" class="border border-blue-200 hover:bg-blue-50 text-blue-700 px-4 py-2 rounded-lg font-medium">Clear</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Quotations List -->
                        <div class="bg-white rounded-lg border border-blue-100 shadow-sm">
                            <div class="p-4 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-blue-900 text-lg font-semibold">
                                        <?php echo $_SESSION['role'] === 'requestor' ? 'My Quotations' : 'All Quotations'; ?>
                                        <span class="text-blue-600 text-sm font-normal">(<?php echo $totalQuotations; ?> total)</span>
                                    </h3>
                                </div>
                            </div>
                            <?php if (!empty($quotations)): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Quotation #</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Problem</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Amount</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Priority</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Created</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-blue-100">
                                        <?php foreach ($quotations as $quotation): ?>
                                            <tr class="hover:bg-blue-50">
                                                <td class="px-6 py-4 text-blue-900 text-sm font-medium">
                                                    <?php echo htmlspecialchars($quotation['quotation_number']); ?>
                                                </td>
                                                <td class="px-6 py-4 text-blue-700 text-sm">
                                                    <div class="font-medium"><?php echo htmlspecialchars($quotation['customer_name']); ?></div>
                                                    <?php if ($quotation['customer_phone']): ?>
                                                        <div class="text-blue-600 text-xs"><?php echo htmlspecialchars($quotation['customer_phone']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-blue-700 text-sm">
                                                    <div class="font-medium"><?php echo htmlspecialchars($quotation['vehicle_registration']); ?></div>
                                                    <?php if ($quotation['vehicle_make_model']): ?>
                                                        <div class="text-blue-600 text-xs"><?php echo htmlspecialchars($quotation['vehicle_make_model']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-blue-600 text-sm max-w-xs">
                                                    <div class="truncate" title="<?php echo htmlspecialchars($quotation['problem_description']); ?>">
                                                        <?php echo htmlspecialchars(substr($quotation['problem_description'], 0, 50)) . (strlen($quotation['problem_description']) > 50 ? '...' : ''); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-blue-700 text-sm font-medium">
                                                    <?php echo $quotation['total_amount'] > 0 ? formatCurrency($quotation['total_amount']) : '-'; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php
                                                    $statusColors = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'sent' => 'bg-orange-100 text-orange-800',
                                                        'approved' => 'bg-green-100 text-green-800',
                                                        'rejected' => 'bg-red-100 text-red-800',
                                                        'repair_in_progress' => 'bg-purple-100 text-purple-800',
                                                        'repair_complete' => 'bg-teal-100 text-teal-800',
                                                        'bill_generated' => 'bg-indigo-100 text-indigo-800',
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'cancelled' => 'bg-gray-100 text-gray-800'
                                                    ];
                                                    $statusColor = $statusColors[$quotation['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $statusColor; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $quotation['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php
                                                    $priorityColors = [
                                                        'low' => 'bg-blue-100 text-blue-800',
                                                        'medium' => 'bg-gray-100 text-gray-800',
                                                        'high' => 'bg-orange-100 text-orange-800',
                                                        'urgent' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $priorityColor = $priorityColors[$quotation['priority']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $priorityColor; ?>">
                                                        <?php echo ucfirst($quotation['priority']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-blue-600 text-sm">
                                                    <?php echo date('M d, Y', strtotime($quotation['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-2">
                                                        <button class="text-blue-600 hover:text-blue-800 p-1" onclick="viewDetails(<?php echo $quotation['id']; ?>)" title="View Details">
                                                            <span class="material-icons text-sm">visibility</span>
                                                        </button>

                                                        <?php if ($_SESSION['role'] !== 'requestor' || ($quotation['status'] === 'pending' && $quotation['created_by'] == $_SESSION['user_id'])): ?>
                                                            <button class="text-blue-600 hover:text-blue-800 p-1" onclick="editQuotation(<?php echo $quotation['id']; ?>)" title="Edit">
                                                                <span class="material-icons text-sm">edit</span>
                                                            </button>
                                                        <?php endif; ?>

                                                        <div class="relative">
                                                            <button class="text-gray-600 hover:text-gray-800 p-1" onclick="toggleActionMenu(<?php echo $quotation['id']; ?>)" title="More Actions">
                                                                <span class="material-icons text-sm">more_vert</span>
                                                            </button>
                                                            <div id="actionMenu<?php echo $quotation['id']; ?>" class="hidden absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-10">
                                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                                    <?php if ($quotation['status'] === 'pending'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="sendForApproval(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">send</span> Send for Approval
                                                                        </button>
                                                                    <?php elseif ($quotation['status'] === 'sent'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="approveQuotation(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">check</span> Approve
                                                                        </button>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="rejectQuotation(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">close</span> Reject
                                                                        </button>
                                                                    <?php elseif ($quotation['status'] === 'approved'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="assignTechnician(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">person_add</span> Assign Technician
                                                                        </button>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="startRepair(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">build</span> Start Repair
                                                                        </button>
                                                                    <?php elseif ($quotation['status'] === 'repair_in_progress'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="completeRepair(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">done</span> Complete Repair
                                                                        </button>
                                                                    <?php elseif ($quotation['status'] === 'repair_complete'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="generateBill(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">receipt</span> Generate Bill
                                                                        </button>
                                                                    <?php elseif ($quotation['status'] === 'bill_generated'): ?>
                                                                        <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="recordPayment(<?php echo $quotation['id']; ?>)">
                                                                            <span class="material-icons text-sm mr-2">payment</span> Record Payment
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                                <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" onclick="viewHistory(<?php echo $quotation['id']; ?>)">
                                                                    <span class="material-icons text-sm mr-2">history</span> History
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($pagination['total_pages'] > 1): ?>
                                <div class="px-4 py-3 border-t border-blue-100 bg-blue-50">
                                    <div class="flex items-center justify-between">
                                        <span class="text-blue-700 text-sm">
                                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalQuotations); ?> of <?php echo $totalQuotations; ?> quotations
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $priority_filter ? '&priority=' . urlencode($priority_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 text-blue-600 border border-blue-200 rounded hover:bg-blue-50">Previous</a>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                                <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $priority_filter ? '&priority=' . urlencode($priority_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'text-blue-600 border border-blue-200'; ?> rounded hover:bg-blue-50"><?php echo $i; ?></a>
                                            <?php endfor; ?>

                                            <?php if ($page < $pagination['total_pages']): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $priority_filter ? '&priority=' . urlencode($priority_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 text-blue-600 border border-blue-200 rounded hover:bg-blue-50">Next</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php else: ?>
                                <div class="text-center py-12">
                                    <span class="material-icons text-blue-300 text-6xl mb-4">receipt_long</span>
                                    <h3 class="text-blue-900 text-lg font-medium mb-2">No quotations found</h3>
                                    <p class="text-blue-600 text-sm mb-4">
                                        <?php if ($_SESSION['role'] === 'requestor'): ?>
                                            Create your first quotation to get started.
                                        <?php else: ?>
                                            Quotations will appear here when they are created.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($_SESSION['role'] === 'requestor'): ?>
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium" onclick="showCreateModal()">
                                            Create Quotation
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals will be loaded here dynamically -->
    <div id="modal-container"></div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Quotation Manager initialized');

            // Close action menus when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('[id^="actionMenu"]') && !event.target.closest('button[onclick*="toggleActionMenu"]')) {
                    document.querySelectorAll('[id^="actionMenu"]').forEach(menu => {
                        menu.classList.add('hidden');
                    });
                }
            });
        });

        // Toggle action menu for quotations
        function toggleActionMenu(quotationId) {
            const menu = document.getElementById('actionMenu' + quotationId);
            const allMenus = document.querySelectorAll('[id^="actionMenu"]');

            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) {
                    m.classList.add('hidden');
                }
            });

            // Toggle current menu
            menu.classList.toggle('hidden');
        }

        // Modal and action functions
        async function showCreateModal() {
            // Implementation for create quotation modal
            alert('Create quotation modal will be implemented');
        }

        async function viewDetails(id) {
            // Implementation for view quotation details modal
            alert('View details modal for quotation ' + id + ' will be implemented');
        }

        async function editQuotation(id) {
            // Implementation for edit quotation modal
            alert('Edit quotation modal for quotation ' + id + ' will be implemented');
        }

        async function viewHistory(id) {
            try {
                const response = await fetch('../api/quotations_new.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_history',
                        quotation_id: id,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const result = await response.json();
                if (result.success) {
                    // Show history modal with result.history and result.status_log
                    alert('History modal will show history for quotation ' + id);
                } else {
                    alert(result.error || 'Failed to load quotation history');
                }
            } catch (error) {
                console.error('Error loading history:', error);
                alert('Network error occurred while loading history');
            }
        }

        async function sendForApproval(id) {
            if (confirm('Send this quotation for approval?')) {
                await updateQuotationStatus(id, 'sent', 'Sent for approval');
            }
        }

        async function approveQuotation(id) {
            const notes = prompt('Approval notes (optional):');
            if (notes !== null) {
                try {
                    const response = await fetch('../api/quotations_new.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'approve',
                            quotation_id: id,
                            approval_notes: notes,
                            csrf_token: '<?php echo generateCSRFToken(); ?>'
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        alert('Quotation approved successfully');
                        window.location.reload();
                    } else {
                        alert(result.error || 'Failed to approve quotation');
                    }
                } catch (error) {
                    console.error('Error approving quotation:', error);
                    alert('Network error occurred');
                }
            }
        }

        async function rejectQuotation(id) {
            const reason = prompt('Rejection reason (required):');
            if (reason && reason.trim()) {
                try {
                    const response = await fetch('../api/quotations_new.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'reject',
                            quotation_id: id,
                            rejection_reason: reason.trim(),
                            csrf_token: '<?php echo generateCSRFToken(); ?>'
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        alert('Quotation rejected successfully');
                        window.location.reload();
                    } else {
                        alert(result.error || 'Failed to reject quotation');
                    }
                } catch (error) {
                    console.error('Error rejecting quotation:', error);
                    alert('Network error occurred');
                }
            } else if (reason === '') {
                alert('Rejection reason is required');
            }
        }

        async function assignTechnician(id) {
            // Implementation for technician assignment modal
            alert('Technician assignment modal for quotation ' + id + ' will be implemented');
        }

        async function startRepair(id) {
            if (confirm('Start repair work for this quotation?')) {
                await updateQuotationStatus(id, 'repair_in_progress', 'Repair work started');
            }
        }

        async function completeRepair(id) {
            if (confirm('Mark repair work as complete?')) {
                await updateQuotationStatus(id, 'repair_complete', 'Repair work completed');
            }
        }

        async function generateBill(id) {
            if (confirm('Generate bill for this quotation?')) {
                await updateQuotationStatus(id, 'bill_generated', 'Bill generated');
            }
        }

        async function recordPayment(id) {
            if (confirm('Record payment as received for this quotation?')) {
                await updateQuotationStatus(id, 'paid', 'Payment recorded');
            }
        }

        // Helper function for status updates
        async function updateQuotationStatus(id, status, notes) {
            try {
                const response = await fetch('../api/quotations_new.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'update_status',
                        quotation_id: id,
                        status: status,
                        notes: notes,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Status updated successfully');
                    window.location.reload();
                } else {
                    alert(result.error || 'Failed to update status');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('Network error occurred');
            }
        }
    </script>
</body>
</html>