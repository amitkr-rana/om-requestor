<?php
require_once '../includes/functions.php';

requireRole('admin');


// Handle pagination
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Handle filters
$role_filter = sanitize($_GET['role'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build query conditions - respect organization context
$conditions = [];
$params = [];

// Organization filtering based on current admin context
if ($_SESSION['organization_id'] != 15) { // If not Om Engineers system admin viewing from another org context
    $conditions[] = "u.organization_id = ?";
    $params[] = $_SESSION['organization_id'];
}

if ($role_filter) {
    $conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $conditions[] = "u.is_active = ?";
    $params[] = $status_filter;
}

if ($search) {
    $conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$totalResult = $db->fetch(
    "SELECT COUNT(*) as count FROM users_new u {$whereClause}",
    $params
);
$totalUsers = $totalResult ? $totalResult['count'] : 0;

// Get users with pagination
$usersResult = $db->fetchAll(
    "SELECT u.*, o.name as organization_name
     FROM users_new u
     JOIN organizations_new o ON u.organization_id = o.id
     {$whereClause}
     ORDER BY u.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);
$users = $usersResult ?: [];

$pagination = paginate($page, $totalUsers, $limit);

// Get user statistics for dashboard cards - independent of search/filters
// Only apply organization filtering for stats
if ($_SESSION['organization_id'] != 15) { // If not Om Engineers system admin
    $statsOrgParam = [$_SESSION['organization_id']];

    $totalUsersStatsResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.organization_id = ?",
        $statsOrgParam
    );
    $activeUsersResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.organization_id = ? AND u.is_active = 1",
        $statsOrgParam
    );
    $inactiveUsersResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.organization_id = ? AND u.is_active = 0",
        $statsOrgParam
    );
    $requestorsResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.organization_id = ? AND u.role = 'requestor'",
        $statsOrgParam
    );
    $approversResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.organization_id = ? AND u.role = 'approver'",
        $statsOrgParam
    );
} else { // Om Engineers system admin - see all users
    $totalUsersStatsResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u"
    );
    $activeUsersResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.is_active = 1"
    );
    $inactiveUsersResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.is_active = 0"
    );
    $requestorsResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.role = 'requestor'"
    );
    $approversResult = $db->fetch(
        "SELECT COUNT(*) as count FROM users_new u WHERE u.role = 'approver'"
    );
}

$totalUsersStats = $totalUsersStatsResult ? $totalUsersStatsResult['count'] : 0;
$activeUsers = $activeUsersResult ? $activeUsersResult['count'] : 0;
$inactiveUsers = $inactiveUsersResult ? $inactiveUsersResult['count'] : 0;
$requestorsCount = $requestorsResult ? $requestorsResult['count'] : 0;
$approversCount = $approversResult ? $approversResult['count'] : 0;

// Get organizations for dropdown (only if Om Engineers admin)
$organizationsForDropdown = [];
if (isset($_SESSION['is_om_engineers_admin']) && $_SESSION['is_om_engineers_admin']) {
    $organizationsForDropdown = $db->fetchAll("SELECT id, name FROM organizations_new WHERE id != 15 ORDER BY name");
}

$pageTitle = 'User Management';
include '../includes/admin_head.php';
?>
<?php include '../includes/admin_sidebar_new.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main Content -->
                    <div class="p-6">
                        <!-- User Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white rounded-lg p-4 border border-blue-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <span class="material-icons text-blue-600 text-lg">group</span>
                                    </div>
                                    <div>
                                        <p class="text-blue-600 text-xs font-medium uppercase tracking-wide">Total Users</p>
                                        <p class="text-blue-900 text-xl font-bold"><?php echo $totalUsersStats; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-green-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                        <span class="material-icons text-green-600 text-lg">check_circle</span>
                                    </div>
                                    <div>
                                        <p class="text-green-600 text-xs font-medium uppercase tracking-wide">Active Users</p>
                                        <p class="text-green-900 text-xl font-bold"><?php echo $activeUsers; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-purple-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <span class="material-icons text-purple-600 text-lg">person</span>
                                    </div>
                                    <div>
                                        <p class="text-purple-600 text-xs font-medium uppercase tracking-wide">Requestors</p>
                                        <p class="text-purple-900 text-xl font-bold"><?php echo $requestorsCount; ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-orange-100 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                        <span class="material-icons text-orange-600 text-lg">verified_user</span>
                                    </div>
                                    <div>
                                        <p class="text-orange-600 text-xs font-medium uppercase tracking-wide">Approvers</p>
                                        <p class="text-orange-900 text-xl font-bold"><?php echo $approversCount; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters and Actions -->
                        <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                            <div class="p-4 border-b border-blue-100">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-blue-900 text-lg font-semibold">Filters</h2>
                                    <button type="button" onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                        <span class="material-icons text-sm">person_add</span>
                                        Add User
                                    </button>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="p-4 bg-gray-50">
                                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-blue-900 mb-2">Search</label>
                                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                               placeholder="Search users..."
                                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="role" class="block text-sm font-medium text-blue-900 mb-2">Role</label>
                                        <select id="role" name="role" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Roles</option>
                                            <option value="requestor" <?php echo $role_filter === 'requestor' ? 'selected' : ''; ?>>Requestor</option>
                                            <option value="approver" <?php echo $role_filter === 'approver' ? 'selected' : ''; ?>>Approver</option>
                                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-blue-900 mb-2">Status</label>
                                        <select id="status" name="status" class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">All Status</option>
                                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                            <span class="material-icons text-sm">search</span>
                                            Search
                                        </button>
                                        <a href="users.php" class="border border-blue-200 hover:bg-blue-50 text-blue-700 px-4 py-2 rounded-lg font-medium">Clear</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="bg-white rounded-lg border border-blue-100 shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">ID</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Name</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Username</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Email</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Organization</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Role</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Status</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Created</th>
                                            <th class="text-left px-4 py-3 text-blue-900 font-medium text-sm">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-8 text-gray-500">
                                                    <div class="flex flex-col items-center gap-2">
                                                        <span class="material-icons text-4xl text-gray-300">group</span>
                                                        <p>No users found</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr class="border-t border-blue-100 hover:bg-blue-50">
                                                    <td class="px-4 py-3 text-blue-900 font-medium">#<?php echo $user['id']; ?></td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                                <span class="text-blue-600 font-medium text-sm"><?php echo strtoupper(substr($user['full_name'], 0, 2)); ?></span>
                                                            </div>
                                                            <span class="text-blue-900 font-medium"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-blue-700"><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td class="px-4 py-3 text-blue-700"><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td class="px-4 py-3">
                                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                                            <?php echo htmlspecialchars($user['organization_name']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="px-2 py-1 <?php
                                                            echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' :
                                                                ($user['role'] === 'approver' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800');
                                                        ?> rounded-full text-xs font-medium">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="px-2 py-1 <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?> rounded-full text-xs font-medium">
                                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-blue-700 text-sm"><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            <button type="button" onclick="editUser(<?php echo $user['id']; ?>)"
                                                                    class="text-blue-600 hover:text-blue-800 p-1" title="Edit User">
                                                                <span class="material-icons text-sm">edit</span>
                                                            </button>
                                                            <button type="button" onclick="toggleUserStatus(<?php echo $user['id']; ?>)"
                                                                    class="text-<?php echo $user['is_active'] ? 'orange-600 hover:text-orange-800' : 'green-600 hover:text-green-800'; ?> p-1"
                                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                                <span class="material-icons text-sm"><?php echo $user['is_active'] ? 'block' : 'check'; ?></span>
                                                            </button>
                                                            <button type="button" onclick="deleteUser(<?php echo $user['id']; ?>)"
                                                                    class="text-red-600 hover:text-red-800 p-1" title="Delete User">
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
                                <div class="px-4 py-3 border-t border-blue-100 bg-blue-50">
                                    <div class="flex items-center justify-between">
                                        <span class="text-blue-700 text-sm">
                                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : ''; ?>"
                                                   class="border border-blue-200 hover:bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm">
                                                    <span class="material-icons text-sm">chevron_left</span>
                                                </a>
                                            <?php endif; ?>

                                            <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : ''; ?>"
                                                   class="<?php echo $i === $page ? 'bg-blue-600 text-white' : 'border border-blue-200 hover:bg-blue-100 text-blue-700'; ?> px-3 py-1 rounded text-sm">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>

                                            <?php if ($page < $pagination['total_pages']): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . urlencode($status_filter) : ''; ?>"
                                                   class="border border-blue-200 hover:bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm">
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

    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-blue-900">Create New User</h3>
                    <button type="button" onclick="closeModal('createUserModal')" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            </div>
            <form id="createUserForm" onsubmit="handleCreateUser(event)">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrfField(); ?>

                    <div>
                        <label for="create_full_name" class="block text-sm font-medium text-blue-900 mb-2">Full Name</label>
                        <input type="text" id="create_full_name" name="full_name" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="create_username" class="block text-sm font-medium text-blue-900 mb-2">Username</label>
                        <input type="text" id="create_username" name="username" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="create_email" class="block text-sm font-medium text-blue-900 mb-2">Email</label>
                        <input type="email" id="create_email" name="email" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <?php if (isset($_SESSION['is_om_engineers_admin']) && $_SESSION['is_om_engineers_admin'] && !empty($organizationsForDropdown)): ?>
                    <div>
                        <label for="create_organization" class="block text-sm font-medium text-blue-900 mb-2">Organization</label>
                        <select id="create_organization" name="organization_id" required
                                class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Organization</option>
                            <?php foreach ($organizationsForDropdown as $org): ?>
                                <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="create_role" class="block text-sm font-medium text-blue-900 mb-2">Role</label>
                        <select id="create_role" name="role" required
                                class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Role</option>
                            <option value="requestor">Requestor</option>
                            <option value="approver">Approver</option>
                            <?php if (isset($_SESSION['is_om_engineers_admin']) && $_SESSION['is_om_engineers_admin']): ?>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label for="create_password" class="block text-sm font-medium text-blue-900 mb-2">Password</label>
                        <input type="password" id="create_password" name="password" required minlength="6"
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('createUserModal')"
                                class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">Create User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-blue-900">Edit User</h3>
                    <button type="button" onclick="closeModal('editUserModal')" class="text-gray-400 hover:text-gray-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            </div>
            <form id="editUserForm" onsubmit="handleEditUser(event)">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <?php echo csrfField(); ?>

                    <div>
                        <label for="edit_full_name" class="block text-sm font-medium text-blue-900 mb-2">Full Name</label>
                        <input type="text" id="edit_full_name" name="full_name" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="edit_username" class="block text-sm font-medium text-blue-900 mb-2">Username</label>
                        <input type="text" id="edit_username" name="username" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-blue-900 mb-2">Email</label>
                        <input type="email" id="edit_email" name="email" required
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <?php if (isset($_SESSION['is_om_engineers_admin']) && $_SESSION['is_om_engineers_admin'] && !empty($organizationsForDropdown)): ?>
                    <div>
                        <label for="edit_organization" class="block text-sm font-medium text-blue-900 mb-2">Organization</label>
                        <select id="edit_organization" name="organization_id" required
                                class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Organization</option>
                            <?php foreach ($organizationsForDropdown as $org): ?>
                                <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="edit_role" class="block text-sm font-medium text-blue-900 mb-2">Role</label>
                        <select id="edit_role" name="role" required
                                class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="requestor">Requestor</option>
                            <option value="approver">Approver</option>
                            <?php if (isset($_SESSION['is_om_engineers_admin']) && $_SESSION['is_om_engineers_admin']): ?>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label for="edit_password" class="block text-sm font-medium text-blue-900 mb-2">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password" minlength="6"
                               class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeModal('editUserModal')"
                                class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">Update User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('createUserModal').style.display = 'flex';
            document.getElementById('createUserForm').reset();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        document.querySelectorAll('.fixed').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Handle create user form
        async function handleCreateUser(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            try {
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = 'Creating...';

                const response = await fetch('../api/users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeModal('createUserModal');
                    showNotification(result.message || 'User created successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to create user', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            } finally {
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = false;
                submitButton.textContent = 'Create User';
            }
        }

        // Handle edit user form
        async function handleEditUser(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            try {
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.textContent = 'Updating...';

                const response = await fetch('../api/users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeModal('editUserModal');
                    showNotification(result.message || 'User updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to update user', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            } finally {
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = false;
                submitButton.textContent = 'Update User';
            }
        }

        // Edit user function
        async function editUser(userId) {
            try {
                const response = await fetch(`../api/users.php?action=get&id=${userId}`);
                const result = await response.json();

                if (result.error) {
                    showNotification(result.error, 'error');
                    return;
                }

                if (!result.success || !result.user) {
                    showNotification('Failed to load user data', 'error');
                    return;
                }

                const user = result.user;

                // Populate edit form
                document.getElementById('edit_user_id').value = user.id || '';
                document.getElementById('edit_full_name').value = user.full_name || '';
                document.getElementById('edit_username').value = user.username || '';
                document.getElementById('edit_email').value = user.email || '';
                document.getElementById('edit_role').value = user.role || '';

                // Set organization if dropdown exists
                const orgSelect = document.getElementById('edit_organization');
                if (orgSelect && user.organization_id) {
                    orgSelect.value = user.organization_id;
                }

                // Clear password field
                document.getElementById('edit_password').value = '';

                document.getElementById('editUserModal').style.display = 'flex';
            } catch (error) {
                console.error('Error loading user data:', error);
                showNotification('Failed to load user data', 'error');
            }
        }

        // Toggle user status
        async function toggleUserStatus(userId) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('user_id', userId);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                const response = await fetch('../api/users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }

        // Delete user
        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('user_id', userId);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                const response = await fetch('../api/users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }

        // Simple notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
                type === 'success' ? 'bg-green-600' :
                type === 'error' ? 'bg-red-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>