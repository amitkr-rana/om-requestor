<?php
require_once '../includes/functions.php';

requireRole('admin');

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
    'new_quotation_requests' => $newQuotationRequestsResult ? $newQuotationRequestsResult['count'] : 0,
    'quotations_sent' => $quotationsSentResult ? $quotationsSentResult['count'] : 0,
    'approved_quotations' => $approvedQuotationsResult ? $approvedQuotationsResult['count'] : 0,
    'completed_repairs' => $completedRepairsResult ? $completedRepairsResult['count'] : 0,
    'bills_generated' => $billsGeneratedResult ? $billsGeneratedResult['count'] : 0
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

$pageTitle = 'Dashboard';
include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Metrics Cards -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                            <a href="quotations.php?status=pending" class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">assignment</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Pending Quotation Requests</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['new_quotation_requests']; ?></p>
                            </a>
                            <a href="quotations.php?status=sent" class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">send</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Quotations Sent</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['quotations_sent']; ?></p>
                            </a>
                            <a href="quotations.php?status=approved" class="block bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">check_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Approved Quotations</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['approved_quotations']; ?></p>
                            </a>
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">build_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Completed Repairs</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['completed_repairs']; ?></p>
                            </div>
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">receipt_long</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Bills Generated</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['bills_generated']; ?></p>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mb-8">
                            <h2 class="text-blue-900 text-xl font-semibold mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <button onclick="location.href='quotations.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">add_circle</span>
                                    <span class="text-blue-900 font-medium text-sm">Create Quotation</span>
                                </button>
                                <button onclick="location.href='users.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">person_add</span>
                                    <span class="text-blue-900 font-medium text-sm">Add User</span>
                                </button>
                                <button onclick="location.href='vehicles.php?action=create'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">add</span>
                                    <span class="text-blue-900 font-medium text-sm">Add Vehicle</span>
                                </button>
                                <button onclick="location.href='reports.php'" class="bg-white border border-blue-200 rounded-lg p-4 hover:bg-blue-50 transition-colors flex items-center gap-3">
                                    <span class="material-icons text-blue-600">description</span>
                                    <span class="text-blue-900 font-medium text-sm">Generate Report</span>
                                </button>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="bg-white rounded-lg border border-blue-100">
                            <div class="p-6 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-blue-900 text-xl font-semibold">Recent Activities</h2>
                                    <a href="requests.php" class="text-blue-600 text-sm font-medium hover:text-blue-700">View All</a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Request ID</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Description</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Status</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Date</th>
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
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium">#<?php echo str_pad($activity['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['registration_number']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($activity['requestor_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm max-w-xs truncate"><?php echo htmlspecialchars(substr($activity['problem_description'], 0, 60)); ?>...</td>
                                                    <td class="px-6 py-4 text-sm">
                                                        <?php
                                                        $statusColors = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'approved' => 'bg-green-100 text-green-800',
                                                            'completed' => 'bg-blue-100 text-blue-800',
                                                            'rejected' => 'bg-red-100 text-red-800'
                                                        ];
                                                        $statusClass = $statusColors[$activity['status']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                            <?php echo ucfirst($activity['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></td>
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
