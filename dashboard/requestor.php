<?php
require_once '../includes/functions.php';

requireRole('requestor');

// Get requestor's statistics
$stats = [
    'total_vehicles' => $db->fetch("SELECT COUNT(*) as count FROM vehicles WHERE user_id = ?", [$_SESSION['user_id']])['count'],
    'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE user_id = ?", [$_SESSION['user_id']])['count'],
    'pending_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE user_id = ? AND status = 'pending'", [$_SESSION['user_id']])['count'],
    'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM service_requests WHERE user_id = ? AND status = 'completed'", [$_SESSION['user_id']])['count']
];

// Get requestor's vehicles
$vehicles = $db->fetchAll(
    "SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC",
    [$_SESSION['user_id']]
);

// Get recent service requests
$recentRequests = $db->fetchAll(
    "SELECT sr.*, v.registration_number
     FROM service_requests sr
     JOIN vehicles v ON sr.vehicle_id = v.id
     WHERE sr.user_id = ?
     ORDER BY sr.created_at DESC
     LIMIT 10",
    [$_SESSION['user_id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requestor Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/material.css" rel="stylesheet">
    <link href="../assets/css/style-base.css" rel="stylesheet">
    <link href="../assets/css/style-desktop.css" rel="stylesheet" media="(min-width: 769px)">
    <link href="../assets/css/style-mobile.css" rel="stylesheet" media="(max-width: 768px)">
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
                    <li><a href="requestor.php" class="active">
                        <span class="material-icons">dashboard</span>
                        Dashboard
                    </a></li>
                    <li><a href="my-vehicles.php">
                        <span class="material-icons">directions_car</span>
                        My Vehicles
                    </a></li>
                    <li><a href="my-requests.php">
                        <span class="material-icons">build</span>
                        My Requests
                    </a></li>
                    <li><a href="request-history.php">
                        <span class="material-icons">history</span>
                        Request History
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
            <h1 class="header-title">Requestor Dashboard</h1>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" data-modal="newRequestModal">
                    <span class="material-icons left">add</span>
                    New Request
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <span class="material-icons">directions_car</span>
                    <h2 class="stat-number"><?php echo $stats['total_vehicles']; ?></h2>
                    <p class="stat-label">My Vehicles</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <span class="material-icons">build</span>
                    <h2 class="stat-number"><?php echo $stats['total_requests']; ?></h2>
                    <p class="stat-label">Total Requests</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <span class="material-icons">pending</span>
                    <h2 class="stat-number"><?php echo $stats['pending_requests']; ?></h2>
                    <p class="stat-label">Pending Requests</p>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                    <span class="material-icons">check_circle</span>
                    <h2 class="stat-number"><?php echo $stats['completed_requests']; ?></h2>
                    <p class="stat-label">Completed</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-content">
                    <div class="action-grid">
                        <div class="action-card" data-modal="addVehicleModal">
                            <span class="material-icons">add_box</span>
                            <h4 class="action-title">Add Vehicle</h4>
                            <p class="action-description">Register a new vehicle</p>
                        </div>

                        <div class="action-card" data-modal="newRequestModal">
                            <span class="material-icons">build</span>
                            <h4 class="action-title">Service Request</h4>
                            <p class="action-description">Request maintenance service</p>
                        </div>

                        <div class="action-card" onclick="location.href='my-requests.php'">
                            <span class="material-icons">list</span>
                            <h4 class="action-title">View Requests</h4>
                            <p class="action-description">Check request status</p>
                        </div>

                        <div class="action-card" onclick="location.href='request-history.php'">
                            <span class="material-icons">history</span>
                            <h4 class="action-title">History</h4>
                            <p class="action-description">View request history</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Vehicles -->
            <?php if (!empty($vehicles)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">My Vehicles</h3>
                        <a href="my-vehicles.php" class="btn btn-outlined">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Registration Number</th>
                                    <th>Added Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($vehicles, 0, 5) as $vehicle): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicle['registration_number']); ?></strong>
                                        </td>
                                        <td><?php echo formatDate($vehicle['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-primary"
                                                    onclick="newRequestForVehicle(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['registration_number']); ?>')">
                                                <span class="material-icons">build</span>
                                                Request Service
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-content text-center">
                        <span class="material-icons" style="font-size: 4rem; color: var(--md-sys-color-on-surface-variant);">directions_car</span>
                        <h3>No Vehicles Added</h3>
                        <p class="text-muted">Add your first vehicle to start requesting services.</p>
                        <button type="button" class="btn btn-primary" data-modal="addVehicleModal">
                            <span class="material-icons left">add</span>
                            Add Vehicle
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Requests -->
            <?php if (!empty($recentRequests)): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Service Requests</h3>
                        <a href="my-requests.php" class="btn btn-outlined">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Problem</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRequests as $request): ?>
                                    <tr>
                                        <td>#<?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($request['problem_description'], 0, 50)); ?>...</td>
                                        <td><?php echo getStatusBadge($request['status']); ?></td>
                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add New Vehicle</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="addVehicleForm" data-ajax action="../api/vehicles.php" data-reset-on-success data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <input type="text" id="registration_number" name="registration_number" required
                               pattern="[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}"
                               placeholder="DL01AB1234"
                               style="text-transform: uppercase;">
                        <label for="registration_number">Registration Number</label>
                        <span class="material-icons prefix">directions_car</span>
                    </div>

                    <p class="text-muted" style="font-size: 0.875rem; margin-top: -1rem;">
                        Format: State(2) + District(2) + Series(2) + Number(4)
                        <br>Example: DL01AB1234
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Request Modal -->
    <div id="newRequestModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">New Service Request</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="newRequestForm" data-ajax action="../api/requests.php" data-reset-on-success data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrfField(); ?>

                    <div class="input-field">
                        <select id="vehicle_id" name="vehicle_id" required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['registration_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="vehicle_id">Vehicle</label>
                    </div>

                    <div class="input-field">
                        <textarea id="problem_description" name="problem_description" required rows="4"></textarea>
                        <label for="problem_description">Problem Description</label>
                    </div>

                    <p class="text-muted" style="font-size: 0.875rem;">
                        Please provide detailed description of the problem for accurate quotation.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function newRequestForVehicle(vehicleId, registrationNumber) {
            document.getElementById('vehicle_id').value = vehicleId;
            document.getElementById('vehicle_id').classList.add('has-value');
            Material.openModal('newRequestModal');
        }

        // Auto-format registration number input
        document.getElementById('registration_number').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
</body>
</html>