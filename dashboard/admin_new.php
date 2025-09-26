<?php
require_once '../includes/functions.php';

requireRole('admin');

// Use new quotations system with enhanced workflow

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
                            <a href="quotation_manager.php?status=pending"
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
                            <a href="quotation_manager.php?status=sent"
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
                            <a href="quotation_manager.php?status=approved"
                               class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">check_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Approved Quotations</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['approved_quotations']; ?></p>
                            </a>

                            <!-- Repairs In Progress -->
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
                            <!-- Bills Generated -->
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

                            <!-- Payments Received -->
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
                        </div>

                        <!-- Enhanced Quick Actions -->
                        <div class="mb-8">
                            <h2 class="text-blue-900 text-xl font-semibold mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
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
                                <button onclick="location.href='users.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">person_add</span>
                                    <span class="text-blue-900 font-medium text-sm">Add User</span>
                                </button>
                            </div>
                        </div>

                        <!-- Inventory Alerts -->
                        <?php if (!empty($inventoryAlerts) && $inventoryAlerts[0]['low_stock_count'] > 0): ?>
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
                                    <a href="quotation_manager.php"
                                       class="text-blue-600 text-sm font-medium hover:text-blue-700">View All</a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Quotation #</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Amount</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Updated</th>
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
                                                        <?php echo date('M d, Y', strtotime($activity['updated_at'])); ?>
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