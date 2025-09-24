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

// Build query conditions
$conditions = ["u.organization_id = ?"];
$params = [$_SESSION['organization_id']];

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

$whereClause = implode(' AND ', $conditions);

// Get total count
$totalUsers = $db->fetch(
    "SELECT COUNT(*) as count FROM users u WHERE {$whereClause}",
    $params
)['count'];

// Get users with pagination
$users = $db->fetchAll(
    "SELECT u.*, o.name as organization_name
     FROM users u
     JOIN organizations o ON u.organization_id = o.id
     WHERE {$whereClause}
     ORDER BY u.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$pagination = paginate($page, $totalUsers, $limit);

// Handle single user view for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = getUserById((int)$_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
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
                    <li><a href="users.php" class="active">
                        <span class="material-icons">group</span>
                        User Management
                    </a></li>
                    <li><a href="requests.php">
                        <span class="material-icons">build</span>
                        Service Requests
                    </a></li>
                    <li><a href="quotations.php">
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
            <h1 class="header-title">User Management</h1>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" data-modal="createUserModal">
                    <span class="material-icons left">person_add</span>
                    Add User
                </button>
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
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <label for="search">Search users...</label>
                                <span class="material-icons prefix">search</span>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="input-field">
                                <select id="role" name="role" data-auto-submit>
                                    <option value="">All Roles</option>
                                    <option value="requestor" <?php echo $role_filter === 'requestor' ? 'selected' : ''; ?>>Requestor</option>
                                    <option value="approver" <?php echo $role_filter === 'approver' ? 'selected' : ''; ?>>Approver</option>
                                </select>
                                <label for="role">Role</label>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="input-field">
                                <select id="status" name="status" data-auto-submit>
                                    <option value="">All Status</option>
                                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <label for="status">Status</label>
                            </div>
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons left">search</span>
                                Search
                            </button>
                            <a href="users.php" class="btn btn-outlined">Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">Users (<?php echo $totalUsers; ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No users found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role'] === 'requestor' ? 'primary' : 'success'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-outlined" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <span class="material-icons">edit</span>
                                            </button>
                                            <button type="button" class="btn btn-small btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>"
                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                                                <span class="material-icons"><?php echo $user['is_active'] ? 'block' : 'check'; ?></span>
                                            </button>
                                            <button type="button" class="btn btn-small btn-error"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>)"
                                                    data-confirm="Are you sure you want to delete this user?">
                                                <span class="material-icons">delete</span>
                                            </button>
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
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                            </span>
                            <div>
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-outlined btn-small">
                                        <span class="material-icons">chevron_left</span>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"
                                       class="btn btn-small <?php echo $i === $page ? 'btn-primary' : 'btn-outlined'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-outlined btn-small">
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

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Create New User</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="createUserForm" data-ajax action="../api/users.php" data-reset-on-success data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <input type="text" id="create_full_name" name="full_name" required>
                        <label for="create_full_name">Full Name</label>
                    </div>

                    <div class="input-field">
                        <input type="text" id="create_username" name="username" required>
                        <label for="create_username">Username</label>
                    </div>

                    <div class="input-field">
                        <input type="email" id="create_email" name="email" required>
                        <label for="create_email">Email</label>
                    </div>

                    <div class="input-field">
                        <select id="create_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="requestor">Requestor</option>
                            <option value="approver">Approver</option>
                        </select>
                        <label for="create_role">Role</label>
                    </div>

                    <div class="input-field">
                        <input type="password" id="create_password" name="password" required minlength="6">
                        <label for="create_password">Password</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Edit User</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="editUserForm" data-ajax action="../api/users.php" data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <input type="text" id="edit_full_name" name="full_name" required>
                        <label for="edit_full_name">Full Name</label>
                    </div>

                    <div class="input-field">
                        <input type="text" id="edit_username" name="username" required>
                        <label for="edit_username">Username</label>
                    </div>

                    <div class="input-field">
                        <input type="email" id="edit_email" name="email" required>
                        <label for="edit_email">Email</label>
                    </div>

                    <div class="input-field">
                        <select id="edit_role" name="role" required>
                            <option value="requestor">Requestor</option>
                            <option value="approver">Approver</option>
                        </select>
                        <label for="edit_role">Role</label>
                    </div>

                    <div class="input-field">
                        <input type="password" id="edit_password" name="password" minlength="6">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        async function editUser(userId) {
            try {
                Material.showLoading();
                const response = await fetch(`../api/users.php?action=get&id=${userId}`);
                const user = await response.json();

                if (user.error) {
                    Material.showSnackbar(user.error, 'error');
                    return;
                }

                // Populate edit form
                document.getElementById('edit_user_id').value = user.id;
                document.getElementById('edit_full_name').value = user.full_name;
                document.getElementById('edit_username').value = user.username;
                document.getElementById('edit_email').value = user.email;
                document.getElementById('edit_role').value = user.role;

                // Trigger label animations
                document.querySelectorAll('#editUserForm input').forEach(input => {
                    if (input.value) input.classList.add('has-value');
                });

                Material.openModal('editUserModal');
            } catch (error) {
                Material.showSnackbar('Failed to load user data', 'error');
            } finally {
                Material.hideLoading();
            }
        }

        async function toggleUserStatus(userId) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('user_id', userId);
                formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                Material.showLoading();
                const response = await fetch('../api/users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                Material.hideLoading();

                if (result.success) {
                    Material.showSnackbar(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Material.showSnackbar(result.error, 'error');
                }
            } catch (error) {
                Material.hideLoading();
                Material.showSnackbar('Network error occurred', 'error');
            }
        }

        async function deleteUser(userId) {
            Material.confirm('Are you sure you want to delete this user? This action cannot be undone.', async (confirmed) => {
                if (!confirmed) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('user_id', userId);
                    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                    Material.showLoading();
                    const response = await fetch('../api/users.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    Material.hideLoading();

                    if (result.success) {
                        Material.showSnackbar(result.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Material.showSnackbar(result.error, 'error');
                    }
                } catch (error) {
                    Material.hideLoading();
                    Material.showSnackbar('Network error occurred', 'error');
                }
            });
        }
    </script>
</body>
</html>