<?php
require_once '../includes/functions.php';

requireLogin();

// Get vehicles for filtering (based on user role)
$vehicles = [];
if (hasRole('admin')) {
    $vehicles = $db->fetchAll("SELECT DISTINCT v.registration_number FROM vehicles v ORDER BY v.registration_number");
} elseif (hasRole('approver')) {
    $vehicles = $db->fetchAll(
        "SELECT DISTINCT v.registration_number
         FROM vehicles v
         JOIN users u ON v.user_id = u.id
         WHERE u.organization_id = ?
         ORDER BY v.registration_number",
        [$_SESSION['organization_id']]
    );
} else {
    $vehicles = $db->fetchAll(
        "SELECT registration_number FROM vehicles WHERE user_id = ? ORDER BY registration_number",
        [$_SESSION['user_id']]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
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
                    <li><a href="<?php echo $_SESSION['role']; ?>.php">
                        <span class="material-icons">dashboard</span>
                        Dashboard
                    </a></li>

                    <?php if (hasRole('admin')): ?>
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
                        <li><a href="vehicles.php">
                            <span class="material-icons">directions_car</span>
                            Vehicles
                        </a></li>
                    <?php elseif (hasRole('requestor')): ?>
                        <li><a href="my-vehicles.php">
                            <span class="material-icons">directions_car</span>
                            My Vehicles
                        </a></li>
                        <li><a href="my-requests.php">
                            <span class="material-icons">build</span>
                            My Requests
                        </a></li>
                    <?php elseif (hasRole('approver')): ?>
                        <li><a href="pending-approvals.php">
                            <span class="material-icons">pending</span>
                            Pending Approvals
                        </a></li>
                        <li><a href="approval-history.php">
                            <span class="material-icons">history</span>
                            Approval History
                        </a></li>
                    <?php endif; ?>

                    <li><a href="reports.php" class="active">
                        <span class="material-icons">assessment</span>
                        Reports
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
            <h1 class="header-title">Reports</h1>
            <div class="header-actions">
                <span class="text-muted"><?php echo $_SESSION['organization_name']; ?></span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <div class="row">
                <!-- Report Types -->
                <div class="col-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Available Reports</h3>
                        </div>
                        <div class="card-content">
                            <div class="report-types">
                                <div class="report-type" data-type="service_requests">
                                    <span class="material-icons">build</span>
                                    <div>
                                        <h4>Service Requests Report</h4>
                                        <p class="text-muted">Complete list of service requests with status and details</p>
                                    </div>
                                </div>

                                <div class="report-type" data-type="quotations">
                                    <span class="material-icons">receipt</span>
                                    <div>
                                        <h4>Quotations Report</h4>
                                        <p class="text-muted">Quotation summary with amounts and approval status</p>
                                    </div>
                                </div>

                                <?php if (hasRole('approver')): ?>
                                <div class="report-type" data-type="approvals">
                                    <span class="material-icons">check_circle</span>
                                    <div>
                                        <h4>Approvals Report</h4>
                                        <p class="text-muted">Your approval decisions and processing history</p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (hasRole('admin')): ?>
                                <div class="report-type" data-type="financial">
                                    <span class="material-icons">account_balance_wallet</span>
                                    <div>
                                        <h4>Financial Report</h4>
                                        <p class="text-muted">Financial summary with billing and payment details</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Configuration -->
                <div class="col-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Report Configuration</h3>
                        </div>
                        <div class="card-content">
                            <form id="reportForm">
                                <input type="hidden" id="report_type" name="type" required>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="input-field">
                                            <input type="date" id="date_from" name="date_from" data-max-today>
                                            <label for="date_from">From Date</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-field">
                                            <input type="date" id="date_to" name="date_to" data-max-today>
                                            <label for="date_to">To Date</label>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($vehicles)): ?>
                                <div class="input-field">
                                    <select id="vehicle_filter" name="vehicle">
                                        <option value="">All Vehicles</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo htmlspecialchars($vehicle['registration_number']); ?>">
                                                <?php echo htmlspecialchars($vehicle['registration_number']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="vehicle_filter">Vehicle Filter</label>
                                </div>
                                <?php endif; ?>

                                <div class="input-field">
                                    <select id="output_format" name="format">
                                        <option value="view">View in Browser</option>
                                        <option value="download">Download HTML</option>
                                    </select>
                                    <label for="output_format">Output Format</label>
                                </div>

                                <div class="report-description" id="report_description">
                                    <p class="text-muted">Select a report type from the left to configure and generate.</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary btn-large" disabled id="generateBtn">
                                        <span class="material-icons left">assessment</span>
                                        Generate Report
                                    </button>
                                    <button type="button" class="btn btn-outlined" onclick="clearForm()">
                                        <span class="material-icons left">clear</span>
                                        Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Reports -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Reports</h3>
                        </div>
                        <div class="card-content">
                            <div class="action-grid">
                                <div class="action-card" onclick="generateQuickReport('service_requests', 'last_30_days')">
                                    <span class="material-icons">build</span>
                                    <h4 class="action-title">Last 30 Days Requests</h4>
                                    <p class="action-description">Service requests from last 30 days</p>
                                </div>

                                <div class="action-card" onclick="generateQuickReport('quotations', 'current_month')">
                                    <span class="material-icons">receipt</span>
                                    <h4 class="action-title">This Month Quotations</h4>
                                    <p class="action-description">All quotations for current month</p>
                                </div>

                                <?php if (hasRole('approver')): ?>
                                <div class="action-card" onclick="generateQuickReport('approvals', 'last_7_days')">
                                    <span class="material-icons">check_circle</span>
                                    <h4 class="action-title">Last 7 Days Approvals</h4>
                                    <p class="action-description">Your approval decisions last week</p>
                                </div>
                                <?php endif; ?>

                                <?php if (hasRole('admin')): ?>
                                <div class="action-card" onclick="generateQuickReport('financial', 'current_month')">
                                    <span class="material-icons">account_balance_wallet</span>
                                    <h4 class="action-title">Monthly Financial</h4>
                                    <p class="action-description">Current month financial summary</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        let selectedReportType = null;

        const reportDescriptions = {
            service_requests: 'Generate a comprehensive report of all service requests including status, dates, vehicles, and requestor information.',
            quotations: 'View detailed quotation reports with amounts, approval status, and financial summaries.',
            approvals: 'Track your approval decisions including approved and rejected quotations with notes and dates.',
            financial: 'Complete financial overview including billing amounts, payment status, and collection rates.'
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Report type selection
            document.querySelectorAll('.report-type').forEach(element => {
                element.addEventListener('click', function() {
                    const reportType = this.getAttribute('data-type');
                    selectReportType(reportType);
                });
            });

            // Form submission
            document.getElementById('reportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateReport();
            });

            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_to').value = today;

            // Set 30 days ago as default from date
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            document.getElementById('date_from').value = thirtyDaysAgo.toISOString().split('T')[0];
        });

        function selectReportType(type) {
            selectedReportType = type;

            // Update UI
            document.querySelectorAll('.report-type').forEach(el => el.classList.remove('selected'));
            document.querySelector(`[data-type="${type}"]`).classList.add('selected');

            // Update form
            document.getElementById('report_type').value = type;
            document.getElementById('report_description').innerHTML =
                `<p class="text-muted">${reportDescriptions[type]}</p>`;

            // Enable generate button
            document.getElementById('generateBtn').disabled = false;
        }

        function generateReport() {
            if (!selectedReportType) {
                Material.showSnackbar('Please select a report type', 'warning');
                return;
            }

            const form = document.getElementById('reportForm');
            const formData = new FormData(form);

            // Build URL
            const params = new URLSearchParams();
            params.append('action', 'generate');

            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            const url = '../reports/pdf_generator.php?' + params.toString();

            if (formData.get('format') === 'download') {
                // Download file
                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.click();
            } else {
                // Open in new tab
                window.open(url, '_blank');
            }
        }

        function generateQuickReport(type, period) {
            const today = new Date();
            let fromDate, toDate = today.toISOString().split('T')[0];

            switch (period) {
                case 'last_7_days':
                    fromDate = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                    break;
                case 'last_30_days':
                    fromDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                    break;
                case 'current_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    break;
                default:
                    fromDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
            }

            const params = new URLSearchParams({
                action: 'generate',
                type: type,
                date_from: fromDate,
                date_to: toDate,
                format: 'view'
            });

            window.open('../reports/pdf_generator.php?' + params.toString(), '_blank');
        }

        function clearForm() {
            document.getElementById('reportForm').reset();
            document.getElementById('generateBtn').disabled = true;
            document.getElementById('report_description').innerHTML =
                '<p class="text-muted">Select a report type from the left to configure and generate.</p>';

            document.querySelectorAll('.report-type').forEach(el => el.classList.remove('selected'));
            selectedReportType = null;

            // Reset default dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_to').value = today;

            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            document.getElementById('date_from').value = thirtyDaysAgo.toISOString().split('T')[0];
        }
    </script>

    <style>
        .report-types {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .report-type {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--md-sys-color-surface-variant);
            border-radius: var(--md-sys-shape-corner-small);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .report-type:hover {
            background-color: var(--md-sys-color-surface-variant);
            transform: translateY(-1px);
        }

        .report-type.selected {
            background-color: var(--md-sys-color-primary-container);
            border-color: var(--md-sys-color-primary);
            color: var(--md-sys-color-on-primary-container);
        }

        .report-type .material-icons {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--md-sys-color-primary);
        }

        .report-type.selected .material-icons {
            color: var(--md-sys-color-on-primary-container);
        }

        .report-type h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            font-weight: 500;
        }

        .report-type p {
            margin: 0;
            font-size: 0.875rem;
        }

        .report-description {
            background-color: var(--md-sys-color-surface-variant);
            padding: 1rem;
            border-radius: var(--md-sys-shape-corner-small);
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }

            .col-4, .col-8 {
                width: 100%;
            }
        }
    </style>
</body>
</html>