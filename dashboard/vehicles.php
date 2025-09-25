<?php
require_once '../includes/functions.php';

requireRole('admin');

// Handle pagination
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Handle filters
$search = sanitize($_GET['search'] ?? '');
$user_filter = (int)($_GET['user'] ?? 0);

// Build query conditions
$conditions = [];
$params = [];

// Organization filtering - respect current organization context
if ($_SESSION['organization_id'] != 2) { // If not Om Engineers system admin
    $conditions[] = "u.organization_id = ?";
    $params[] = $_SESSION['organization_id'];
}

if ($user_filter > 0) {
    $conditions[] = "v.user_id = ?";
    $params[] = $user_filter;
}

if ($search) {
    $conditions[] = "(v.registration_number LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$totalResult = $db->fetch(
    "SELECT COUNT(*) as count
     FROM vehicles v
     JOIN users u ON v.user_id = u.id
     {$whereClause}",
    $params
);
$totalVehicles = $totalResult ? $totalResult['count'] : 0;

// Get vehicles with pagination
$vehiclesResult = $db->fetchAll(
    "SELECT v.*, u.full_name as owner_name, u.username,
     CASE
         WHEN u.organization_id = 2 THEN 'Om Engineers'
         ELSE o.name
     END as organization_name
     FROM vehicles v
     JOIN users u ON v.user_id = u.id
     LEFT JOIN organizations o ON u.organization_id = o.id AND u.organization_id != 2
     {$whereClause}
     ORDER BY v.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);
$vehicles = $vehiclesResult ?: [];

$pagination = paginate($page, $totalVehicles, $limit);

// Get users for dropdown (filtered by organization)
$usersForDropdown = [];
if ($_SESSION['organization_id'] == 2) {
    $usersForDropdown = $db->fetchAll("SELECT id, full_name, username FROM users WHERE role IN ('requestor', 'approver') ORDER BY full_name");
} else {
    $usersForDropdown = $db->fetchAll("SELECT id, full_name, username FROM users WHERE organization_id = ? AND role IN ('requestor', 'approver') ORDER BY full_name", [$_SESSION['organization_id']]);
}

// Get organization name for display
if ($_SESSION['organization_id'] == 2) {
    $organizationName = 'Om Engineers';
} else {
    $orgResult = $db->fetch("SELECT name FROM organizations WHERE id = ?", [$_SESSION['organization_id']]);
    $organizationName = $orgResult ? $orgResult['name'] : 'Organization';
}

$pageTitle = 'Vehicle Management';
include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main Content -->
                    <div class="p-6">
                        <!-- Vehicle Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-lg">directions_car</span>
                                    </div>
                                    <div>
                                        <p class="text-blue-600 text-xs font-medium uppercase tracking-wide">Total Vehicles</p>
                                        <p class="text-blue-900 text-xl font-bold"><?php echo $totalVehicles; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-lg">verified</span>
                                    </div>
                                    <div>
                                        <p class="text-green-600 text-xs font-medium uppercase tracking-wide">Active Vehicles</p>
                                        <p class="text-green-900 text-xl font-bold"><?php echo count($vehicles); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <span class="material-icons text-purple-600 text-lg">group</span>
                                    </div>
                                    <div>
                                        <p class="text-purple-600 text-xs font-medium uppercase tracking-wide">Vehicle Owners</p>
                                        <p class="text-purple-900 text-xl font-bold"><?php echo count(array_unique(array_column($vehicles, 'user_id'))); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                        <span class="material-icons text-orange-600 text-lg">domain</span>
                                    </div>
                                    <div>
                                        <p class="text-orange-600 text-xs font-medium uppercase tracking-wide">Current Org</p>
                                        <p class="text-orange-900 text-sm font-bold truncate max-w-24" title="<?php echo htmlspecialchars($organizationName ?? 'Default'); ?>">
                                            <?php echo htmlspecialchars($organizationName ?? 'Default'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="mb-6">
                            <button type="button" onclick="document.getElementById('createVehicleModal').style.display='block'" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                <span class="material-icons">add</span>
                                <span>Add Vehicle</span>
                            </button>
                        </div>

                        <!-- Filters -->
                        <div class="bg-white rounded-lg border border-blue-100 p-6 mb-6">
                            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="search" class="block text-sm font-medium text-blue-900 mb-2">Search Vehicles</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="material-icons text-blue-400 text-sm">search</span>
                                        </div>
                                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by registration or owner..." class="pl-10 w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                <div>
                                    <label for="user" class="block text-sm font-medium text-blue-900 mb-2">Vehicle Owner</label>
                                    <select id="user" name="user" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">All Owners</option>
                                        <?php foreach ($usersForDropdown as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                                        <span class="material-icons text-sm">filter_list</span>
                                        <span>Filter</span>
                                    </button>
                                    <a href="vehicles.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                                        <span class="material-icons text-sm">clear</span>
                                        <span>Clear</span>
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Vehicles Table -->
                        <div class="bg-white rounded-lg border border-blue-100">
                            <div class="p-6 border-b border-blue-100">
                                <h2 class="text-blue-900 text-xl font-semibold">Vehicles (<?php echo $totalVehicles; ?>)</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Registration Number</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Owner</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Organization</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Added Date</th>
                                            <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-blue-100">
                                        <?php if (empty($vehicles)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-8 text-center text-blue-600">
                                                    <div class="flex flex-col items-center gap-2">
                                                        <span class="material-icons text-4xl text-blue-300">directions_car</span>
                                                        <span>No vehicles found</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                                <tr class="hover:bg-blue-50">
                                                    <td class="px-6 py-4 text-blue-900 text-sm font-medium"><?php echo htmlspecialchars($vehicle['registration_number']); ?></td>
                                                    <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($vehicle['owner_name']); ?></td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm">
                                                        <?php echo htmlspecialchars($vehicle['organization_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-2">
                                                            <button onclick="editVehicle(<?php echo $vehicle['id']; ?>, '<?php echo addslashes($vehicle['registration_number']); ?>')" class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-100 rounded transition-colors" title="Edit vehicle">
                                                                <span class="material-icons text-sm">edit</span>
                                                            </button>
                                                            <button onclick="deleteVehicle(<?php echo $vehicle['id']; ?>, '<?php echo addslashes($vehicle['registration_number']); ?>')" class="text-red-600 hover:text-red-900 p-1 hover:bg-red-100 rounded transition-colors" title="Delete vehicle">
                                                                <span class="material-icons text-sm">delete</span>
                                                            </button>
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
                                    <?php echo renderPagination($pagination); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Vehicle Modal -->
    <div id="createVehicleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-blue-900">Add New Vehicle</h3>
                        <button onclick="document.getElementById('createVehicleModal').style.display='none'" class="text-gray-400 hover:text-gray-600">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    <form id="createVehicleForm" onsubmit="createVehicle(event)">
                        <div class="mb-4">
                            <label for="registration_number" class="block text-sm font-medium text-blue-900 mb-2">Registration Number</label>
                            <input type="text" id="registration_number" name="registration_number" required placeholder="e.g., DL01AB1234" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="user_id" class="block text-sm font-medium text-blue-900 mb-2">Vehicle Owner</label>
                            <select id="user_id" name="user_id" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Owner</option>
                                <?php foreach ($usersForDropdown as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('createVehicleModal').style.display='none'" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Add Vehicle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function createVehicle(event) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'create');
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

        try {
            const response = await fetch('../api/vehicles.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('Vehicle added successfully!');
                location.reload();
            } else {
                alert(result.error || 'Failed to add vehicle');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error occurred');
        }
    }

    function editVehicle(id, registrationNumber) {
        // For now, just show an alert - can be enhanced later
        alert('Edit functionality for vehicle ' + registrationNumber + ' (ID: ' + id + ') would be implemented here');
    }

    async function deleteVehicle(id, registrationNumber) {
        if (!confirm('Are you sure you want to delete vehicle ' + registrationNumber + '? This action cannot be undone.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('vehicle_id', id);
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

        try {
            const response = await fetch('../api/vehicles.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('Vehicle deleted successfully!');
                location.reload();
            } else {
                alert(result.error || 'Failed to delete vehicle');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error occurred');
        }
    }
    </script>
</body>
</html>