<?php
require_once '../includes/functions.php';

requireRole('admin');

// Get dashboard statistics
$stats = [
    'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests")['count'],
    'pending_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE status = 'pending'")['count'],
    'total_quotations' => $db->fetch("SELECT COUNT(*) as count FROM quotations")['count'],
    'approved_quotations' => $db->fetch("SELECT COUNT(*) as count FROM quotations WHERE status = 'approved'")['count'],
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")['count'],
    'total_vehicles' => $db->fetch("SELECT COUNT(*) as count FROM vehicles")['count']
];

// Get recent requests
$recentRequests = $db->fetchAll(
    "SELECT sr.*, v.registration_number, u.full_name as requestor_name
     FROM service_requests sr
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     ORDER BY sr.created_at DESC
     LIMIT 10"
);

// Get pending quotations
$pendingQuotations = $db->fetchAll(
    "SELECT q.*, sr.problem_description, v.registration_number, u.full_name as requestor_name
     FROM quotations q
     JOIN service_requests sr ON q.request_id = sr.id
     JOIN vehicles v ON sr.vehicle_id = v.id
     JOIN users u ON sr.user_id = u.id
     WHERE q.status = 'sent'
     ORDER BY q.created_at DESC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
                    <li><a href="admin.php" class="active">
                        <span class="material-icons">dashboard</span>
                        Dashboard
                    </a></li>
                    <li><a href="users.php">
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
            <h1 class="header-title">Admin Dashboard</h1>
            <div class="header-actions">
                <span class="text-muted"><?php echo $_SESSION['organization_name']; ?></span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #FF6B35, #F7931E);">
                    <span class="material-icons">build</span>
                    <h2 class="stat-number"><?php echo $stats['total_requests']; ?></h2>
                    <p class="stat-label">Total Requests</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #4ECDC4, #26A69A);">
                    <span class="material-icons">pending</span>
                    <h2 class="stat-number"><?php echo $stats['pending_requests']; ?></h2>
                    <p class="stat-label">Pending Requests</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #A8E6CF, #56C596);">
                    <span class="material-icons">receipt</span>
                    <h2 class="stat-number"><?php echo $stats['total_quotations']; ?></h2>
                    <p class="stat-label">Total Quotations</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #B4A7D6, #8E7CC3);">
                    <span class="material-icons">check_circle</span>
                    <h2 class="stat-number"><?php echo $stats['approved_quotations']; ?></h2>
                    <p class="stat-label">Approved Quotations</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #FFB3BA, #FF8A80);">
                    <span class="material-icons">group</span>
                    <h2 class="stat-number"><?php echo $stats['total_users']; ?></h2>
                    <p class="stat-label">Total Users</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #FFDFBA, #FFB347);">
                    <span class="material-icons">directions_car</span>
                    <h2 class="stat-number"><?php echo $stats['total_vehicles']; ?></h2>
                    <p class="stat-label">Total Vehicles</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-content">
                    <div class="action-grid">
                        <div class="action-card" onclick="location.href='users.php?action=create'">
                            <span class="material-icons">person_add</span>
                            <h4 class="action-title">Create User</h4>
                            <p class="action-description">Add new requestor or approver</p>
                        </div>

                        <div class="action-card" onclick="location.href='requests.php'">
                            <span class="material-icons">build</span>
                            <h4 class="action-title">View Requests</h4>
                            <p class="action-description">Manage service requests</p>
                        </div>

                        <div class="action-card" onclick="location.href='quotations.php?action=create'">
                            <span class="material-icons">receipt_long</span>
                            <h4 class="action-title">Create Quotation</h4>
                            <p class="action-description">Generate new quotation</p>
                        </div>

                        <div class="action-card" onclick="location.href='reports.php'">
                            <span class="material-icons">assessment</span>
                            <h4 class="action-title">Generate Reports</h4>
                            <p class="action-description">View and export reports</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">Recent Service Requests</h3>
                    <a href="requests.php" class="btn btn-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vehicle</th>
                                <th>Requestor</th>
                                <th>Problem</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentRequests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No recent requests found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentRequests as $request): ?>
                                    <tr>
                                        <td>#<?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($request['problem_description'], 0, 50)); ?>...</td>
                                        <td><?php echo getStatusBadge($request['status']); ?></td>
                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                        <td>
                                            <a href="requests.php?id=<?php echo $request['id']; ?>" class="btn btn-small btn-outlined">View</a>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <a href="quotations.php?action=create&request_id=<?php echo $request['id']; ?>" class="btn btn-small btn-primary">Quote</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pending Quotations -->
            <?php if (!empty($pendingQuotations)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Pending Quotations</h3>
                        <a href="quotations.php" class="btn btn-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Requestor</th>
                                    <th>Amount</th>
                                    <th>Sent Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingQuotations as $quotation): ?>
                                    <tr>
                                        <td>#<?php echo $quotation['id']; ?></td>
                                        <td><?php echo htmlspecialchars($quotation['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($quotation['requestor_name']); ?></td>
                                        <td><?php echo formatCurrency($quotation['amount']); ?></td>
                                        <td><?php echo formatDate($quotation['sent_at'] ?? $quotation['created_at']); ?></td>
                                        <td>
                                            <a href="quotations.php?id=<?php echo $quotation['id']; ?>" class="btn btn-small btn-outlined">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>