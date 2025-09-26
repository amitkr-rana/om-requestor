<?php
require_once '../includes/functions.php';

requireRole('admin');

// Check if we should use new database tables
$useNewTables = useNewDatabase();

if ($useNewTables) {
    // NEW SYSTEM: Use quotations_new table with enhanced workflow

    // Get dashboard statistics - filter by current organization context
    $orgFilter = 'WHERE q.organization_id = ?';
    $orgParams = [$_SESSION['organization_id']];

    // Enhanced statistics for new quotation-centric system
    $pendingQuotationsResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'pending'", $orgParams);
    $sentQuotationsResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'sent'", $orgParams);
    $approvedQuotationsResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'approved'", $orgParams);
    $repairsInProgressResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'repair_in_progress'", $orgParams);
    $completedRepairsResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'repair_complete'", $orgParams);
    $billsGeneratedResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'bill_generated'", $orgParams);
    $paymentsReceivedResult = $db->fetch("SELECT COUNT(*) as count FROM quotations_new q $orgFilter AND q.status = 'paid'", $orgParams);

    // Calculate total revenue and outstanding amounts
    $revenueResult = $db->fetch("SELECT COALESCE(SUM(b.paid_amount), 0) as revenue FROM billing b JOIN quotations_new q ON b.quotation_id = q.id $orgFilter", $orgParams);
    $outstandingResult = $db->fetch("SELECT COALESCE(SUM(b.balance_amount), 0) as outstanding FROM billing b JOIN quotations_new q ON b.quotation_id = q.id WHERE q.organization_id = ? AND b.payment_status IN ('unpaid', 'partial')", $orgParams);

    $stats = [
        'pending_quotations' => $pendingQuotationsResult ? $pendingQuotationsResult['count'] : 0,
        'sent_quotations' => $sentQuotationsResult ? $sentQuotationsResult['count'] : 0,
        'approved_quotations' => $approvedQuotationsResult ? $approvedQuotationsResult['count'] : 0,
        'repairs_in_progress' => $repairsInProgressResult ? $repairsInProgressResult['count'] : 0,
        'completed_repairs' => $completedRepairsResult ? $completedRepairsResult['count'] : 0,
        'bills_generated' => $billsGeneratedResult ? $billsGeneratedResult['count'] : 0,
        'payments_received' => $paymentsReceivedResult ? $paymentsReceivedResult['count'] : 0,
        'total_revenue' => $revenueResult ? $revenueResult['revenue'] : 0,
        'outstanding_amount' => $outstandingResult ? $outstandingResult['outstanding'] : 0
    ];

    // Get recent quotation activities
    $recentActivities = $db->fetchAll(
        "SELECT q.id, q.quotation_number, q.customer_name, q.vehicle_registration,
                q.problem_description, q.status, q.total_amount, q.priority,
                q.created_at, q.updated_at,
                u.full_name as created_by_name,
                'quotation' as activity_type
         FROM quotations_new q
         LEFT JOIN users_new u ON q.created_by = u.id
         WHERE q.organization_id = ?
         ORDER BY q.updated_at DESC
         LIMIT 8",
        $orgParams
    );

    // Get inventory alerts if inventory system is available
    $inventoryAlerts = [];
    if ($db->fetchAll("SHOW TABLES LIKE 'inventory'")) {
        $inventoryAlerts = $db->fetchAll(
            "SELECT COUNT(*) as low_stock_count
             FROM inventory
             WHERE organization_id = ? AND is_active = 1 AND available_stock <= reorder_level",
            $orgParams
        );
    }

} else {
    // FALLBACK: Use old system queries (for backward compatibility)

    // Get dashboard statistics - filter by current organization context via users table
    $orgJoin = '';
    $orgFilter = '';
    $orgParams = [];
    if ($_SESSION['organization_id'] != 2) { // If not Om Engineers admin
        $orgJoin = ' JOIN users u ON sr.user_id = u.id';
        $orgFilter = ' AND u.organization_id = ?';
        $orgParams = [$_SESSION['organization_id']];
    }

    // Get dashboard statistics with safe array access
    $newQuotationRequestsResult = $db->fetch("SELECT COUNT(*) as count FROM service_requests sr {$orgJoin} WHERE sr.status = 'pending' {$orgFilter}", $orgParams);
    $quotationsSentResult = $db->fetch("SELECT COUNT(*) as count FROM quotations q JOIN service_requests sr ON q.request_id = sr.id {$orgJoin} WHERE q.status = 'sent' {$orgFilter}", $orgParams);
    $approvedQuotationsResult = $db->fetch("SELECT COUNT(*) as count FROM quotations q JOIN service_requests sr ON q.request_id = sr.id {$orgJoin} WHERE q.status = 'approved' {$orgFilter}", $orgParams);
    $completedRepairsResult = $db->fetch("SELECT COUNT(*) as count FROM service_requests sr {$orgJoin} WHERE sr.status = 'completed' {$orgFilter}", $orgParams);
    $billsGeneratedResult = $db->fetch("SELECT COUNT(*) as count FROM quotations q JOIN service_requests sr ON q.request_id = sr.id {$orgJoin} WHERE q.status = 'approved' {$orgFilter}", $orgParams);

    $stats = [
        'pending_quotations' => $newQuotationRequestsResult ? $newQuotationRequestsResult['count'] : 0,
        'sent_quotations' => $quotationsSentResult ? $quotationsSentResult['count'] : 0,
        'approved_quotations' => $approvedQuotationsResult ? $approvedQuotationsResult['count'] : 0,
        'completed_repairs' => $completedRepairsResult ? $completedRepairsResult['count'] : 0,
        'bills_generated' => $billsGeneratedResult ? $billsGeneratedResult['count'] : 0,
        'repairs_in_progress' => 0,
        'payments_received' => 0,
        'total_revenue' => 0,
        'outstanding_amount' => 0
    ];

    // Get recent activities - filter by organization
    $recentActivities = $db->fetchAll(
        "SELECT sr.id, sr.problem_description, sr.status, sr.created_at,
                v.registration_number, u.full_name as requestor_name,
                'service_request' as activity_type
         FROM service_requests sr
         JOIN vehicles v ON sr.vehicle_id = v.id
         JOIN users u ON sr.user_id = u.id
         WHERE 1=1 {$orgFilter}
         ORDER BY sr.created_at DESC
         LIMIT 8",
        $orgParams
    );

    $inventoryAlerts = [];
}

$pageTitle = 'Dashboard';
include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar_new.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Enhanced Metrics Cards -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
                            <!-- Pending Quotations -->
                            <a href="<?php echo $useNewTables ? 'quotation_manager.php?status=pending' : 'quotations.php?status=pending'; ?>"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-amber-100 flex items-center justify-center">
                                        <span class="material-icons text-amber-600 text-sm">assignment</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Pending Quotations</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['pending_quotations']; ?></p>
                            </a>

                            <!-- Sent for Approval -->
                            <a href="<?php echo $useNewTables ? 'quotation_manager.php?status=sent' : 'quotations.php?status=sent'; ?>"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">send</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Sent for Approval</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['sent_quotations']; ?></p>
                            </a>

                            <!-- Approved Quotations -->
                            <a href="<?php echo $useNewTables ? 'quotation_manager.php?status=approved' : 'quotations.php?status=approved'; ?>"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">check_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Approved Quotations</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['approved_quotations']; ?></p>
                            </a>

                            <!-- Repairs In Progress (New System Only) -->
                            <?php if ($useNewTables): ?>
                            <a href="quotation_manager.php?status=repair_in_progress"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-orange-100 flex items-center justify-center">
                                        <span class="material-icons text-orange-600 text-sm">build_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Repairs In Progress</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['repairs_in_progress']; ?></p>
                            </a>
                            <?php endif; ?>

                            <!-- Completed Repairs -->
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">task_alt</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Completed Repairs</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['completed_repairs']; ?></p>
                            </div>

                            <!-- Bills Generated -->
                            <?php if ($useNewTables): ?>
                            <a href="quotation_manager.php?status=bill_generated"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-purple-100 flex items-center justify-center">
                                        <span class="material-icons text-purple-600 text-sm">receipt_long</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Bills Generated</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['bills_generated']; ?></p>
                            </a>
                            <?php else: ?>
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">receipt_long</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Bills Generated</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['bills_generated']; ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Payments Received (New System Only) -->
                            <?php if ($useNewTables): ?>
                            <a href="quotation_manager.php?status=paid"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-emerald-100 flex items-center justify-center">
                                        <span class="material-icons text-emerald-600 text-sm">paid</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Payments Received</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['payments_received']; ?></p>
                            </a>

                            <!-- Revenue Card (New System Only) -->
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">trending_up</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Total Revenue</p>
                                <p class="text-blue-900 text-xl font-bold"><?php echo formatCurrency($stats['total_revenue']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Enhanced Quick Actions -->
                        <div class="mb-8">
                            <h2 class="text-blue-900 text-xl font-semibold mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php if ($useNewTables): ?>
                                <button onclick="location.href='quotation_manager.php'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">assignment_add</span>
                                    <span class="text-blue-900 font-medium text-sm">Quotation Manager</span>
                                </button>
                                <button onclick="location.href='inventory_manager.php'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">inventory</span>
                                    <span class="text-blue-900 font-medium text-sm">Manage Inventory</span>
                                </button>
                                <button onclick="location.href='reports_dashboard.php'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">analytics</span>
                                    <span class="text-blue-900 font-medium text-sm">View Reports</span>
                                </button>
                                <?php else: ?>
                                <button onclick="location.href='quotations.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">add_circle</span>
                                    <span class="text-blue-900 font-medium text-sm">Create Quotation</span>
                                </button>
                                <?php endif; ?>

                                <button onclick="location.href='users.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">person_add</span>
                                    <span class="text-blue-900 font-medium text-sm">Add User</span>
                                </button>

                                <?php if (!$useNewTables): ?>
                                <button onclick="location.href='vehicles.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">add</span>
                                    <span class="text-blue-900 font-medium text-sm">Add Vehicle</span>
                                </button>
                                <button onclick="location.href='reports.php'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">description</span>
                                    <span class="text-blue-900 font-medium text-sm">Generate Report</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Inventory Alerts (New System Only) -->
                        <?php if ($useNewTables && !empty($inventoryAlerts) && $inventoryAlerts[0]['low_stock_count'] > 0): ?>
                        <div class="mb-8">
                            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <span class="material-icons text-amber-400">warning</span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-amber-700">
                                            <strong>Inventory Alert:</strong>
                                            You have <?php echo $inventoryAlerts[0]['low_stock_count']; ?> items running low on stock.
                                            <a href="inventory_manager.php" class="underline font-medium">View Inventory →</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Enhanced Recent Activities -->
                        <div class="bg-white rounded-lg border border-blue-100">
                            <div class="p-6 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-blue-900 text-xl font-semibold">Recent Activities</h2>
                                    <a href="<?php echo $useNewTables ? 'quotation_manager.php' : 'requests.php'; ?>"
                                       class="text-blue-600 text-sm font-medium hover:text-blue-700">View All</a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <?php if ($useNewTables): ?>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Quotation #</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Amount</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Updated</th>
                                            <?php else: ?>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Request ID</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Description</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Date</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-blue-100">
                                        <?php if (empty($recentActivities)): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-8 text-center text-blue-600">No recent activities</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentActivities as $activity): ?>
                                                <tr class="hover:bg-blue-50">
                                                    <?php if ($useNewTables): ?>
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">
                                                        <?php echo htmlspecialchars($activity['quotation_number']); ?>
                                                        <?php if ($activity['priority'] && $activity['priority'] !== 'medium'): ?>
                                                            <span class="ml-2 px-2 py-1 text-xs rounded-full <?php echo $activity['priority'] === 'high' ? 'bg-red-100 text-red-800' : ($activity['priority'] === 'urgent' ? 'bg-red-200 text-red-900' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                                <?php echo ucfirst($activity['priority']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['customer_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['vehicle_registration']); ?></td>
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium"><?php echo formatCurrency($activity['total_amount']); ?></td>
                                                    <?php else: ?>
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">#<?php echo str_pad($activity['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['registration_number']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['requestor_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm max-w-xs truncate"><?php echo htmlspecialchars(substr($activity['problem_description'], 0, 60)); ?>...</td>
                                                    <?php endif; ?>

                                                    <td class="px-6 py-4 text-sm">
                                                        <?php
                                                        $statusColors = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'sent' => 'bg-blue-100 text-blue-800',
                                                            'approved' => 'bg-green-100 text-green-800',
                                                            'repair_in_progress' => 'bg-orange-100 text-orange-800',
                                                            'repair_complete' => 'bg-emerald-100 text-emerald-800',
                                                            'bill_generated' => 'bg-purple-100 text-purple-800',
                                                            'paid' => 'bg-green-100 text-green-800',
                                                            'completed' => 'bg-blue-100 text-blue-800',
                                                            'rejected' => 'bg-red-100 text-red-800'
                                                        ];
                                                        $statusClass = $statusColors[$activity['status']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm">
                                                        <?php echo date('M d, Y', strtotime($useNewTables ? $activity['updated_at'] : $activity['created_at'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>