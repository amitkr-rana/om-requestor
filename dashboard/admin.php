<?php
require_once '../includes/functions.php';

requireRole('admin');

// Get dashboard statistics
$stats = [
    'new_quotation_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE status = 'pending'")['count'],
    'quotations_sent' => $db->fetch("SELECT COUNT(*) as count FROM quotations WHERE status = 'sent'")['count'],
    'approved_quotations' => $db->fetch("SELECT COUNT(*) as count FROM quotations WHERE status = 'approved'")['count'],
    'completed_repairs' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE status = 'completed'")['count'],
    'bills_generated' => $db->fetch("SELECT COUNT(*) as count FROM quotations WHERE status = 'approved'")['count']
];

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT sr.id, sr.problem_description, sr.status, sr.created_at,
            v.registration_number, u.full_name as requestor_name,
            'service_request' as activity_type
     FROM service_requests sr
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     ORDER BY sr.created_at DESC
     LIMIT 8"
);

// Get organization name
$organizationName = $db->fetch("SELECT name FROM organizations LIMIT 1")['name'] ?? 'Organization';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
    <link
      rel="stylesheet"
      as="style"
      onload="this.rel='stylesheet'"
      href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900"
    />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'blue': {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body>
    <div class="relative flex h-screen w-full flex-col bg-blue-50 group/design-root overflow-hidden" style='font-family: Inter, "Noto Sans", sans-serif;'>
        <div class="layout-container flex h-full grow flex-col">
            <div class="flex flex-1 h-full">
                <div class="layout-content-container flex flex-col w-80 bg-white border-r border-blue-100">
                    <div class="flex h-full flex-col justify-between p-6">
                        <div class="flex flex-col gap-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center">
                                    <span class="material-icons text-white text-xl">engineering</span>
                                </div>
                                <h1 class="text-blue-900 text-lg font-semibold leading-normal"><?php echo APP_NAME; ?></h1>
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors">
                                    <span class="material-icons text-xl">dashboard</span>
                                    <p class="text-sm font-medium leading-normal">Dashboard</p>
                                </a>
                                <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                    <span class="material-icons text-xl">group</span>
                                    <p class="text-sm font-medium leading-normal">Users</p>
                                </a>
                                <a href="quotations.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                    <span class="material-icons text-xl">receipt</span>
                                    <p class="text-sm font-medium leading-normal">Quotations</p>
                                </a>
                                <a href="vehicles.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                    <span class="material-icons text-xl">directions_car</span>
                                    <p class="text-sm font-medium leading-normal">Vehicles</p>
                                </a>
                                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                    <span class="material-icons text-xl">assessment</span>
                                    <p class="text-sm font-medium leading-normal">Reports</p>
                                </a>
                            </div>
                        </div>
                        <div class="border-t border-blue-100 pt-4">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                                    <span class="material-icons text-white text-lg">security</span>
                                </div>
                                <div>
                                    <p class="text-blue-900 text-sm font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                                    <p class="text-blue-600 text-xs"><?php echo ucfirst($_SESSION['role']); ?></p>
                                </div>
                            </div>
                            <a href="../api/auth.php?action=logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors w-full">
                                <span class="material-icons text-xl">logout</span>
                                <p class="text-sm font-medium leading-normal">Logout</p>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <!-- Dashboard Header -->
                    <div class="flex justify-between items-center p-6 bg-white border-b border-blue-100">
                        <h1 class="text-blue-900 text-3xl font-bold">Dashboard</h1>
                        <div class="text-blue-600 text-sm font-medium">
                            <?php echo htmlspecialchars($organizationName); ?>
                        </div>
                    </div>

                    <!-- Metrics Cards -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">assignment</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">New Quotation Requests</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['new_quotation_requests']; ?></p>
                            </div>
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-sm">send</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Quotations Sent</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['quotations_sent']; ?></p>
                            </div>
                            <div class="bg-white rounded-lg p-6 border border-blue-100 hover:shadow-lg transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-sm">check_circle</span>
                                    </div>
                                </div>
                                <p class="text-blue-900 text-sm font-medium mb-1">Approved Quotations</p>
                                <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['approved_quotations']; ?></p>
                            </div>
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
