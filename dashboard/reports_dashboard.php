<?php
require_once '../includes/functions.php';

requireLogin();

// Only allow admin and approver roles to access reports
if (!in_array($_SESSION['role'], ['admin', 'approver'])) {
    http_response_code(403);
    die('Access denied');
}

// Date range for reports (default to current month)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$organization_id = $_SESSION['organization_id'];

// Key Performance Indicators
$kpis = [];

// Quotation KPIs
$kpis['quotations'] = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['count'],
    'approved' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status = 'approved' AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['count'],
    'completed' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status IN ('repair_complete', 'bill_generated', 'paid') AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['count'],
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM quotations_new WHERE organization_id = ? AND status IN ('pending', 'sent') AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['count']
];

// Financial KPIs
$kpis['financial'] = [
    'total_value' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as value FROM quotations_new WHERE organization_id = ? AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['value'],
    'approved_value' => $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as value FROM quotations_new WHERE organization_id = ? AND status IN ('approved', 'repair_in_progress', 'repair_complete', 'bill_generated', 'paid') AND created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['value'],
    'paid_value' => $db->fetch("SELECT COALESCE(SUM(b.paid_amount), 0) as value FROM billing b JOIN quotations_new q ON b.quotation_id = q.id WHERE q.organization_id = ? AND b.created_at BETWEEN ? AND ?", [$organization_id, $start_date, $end_date])['value'],
    'outstanding' => $db->fetch("SELECT COALESCE(SUM(b.balance_amount), 0) as value FROM billing b JOIN quotations_new q ON b.quotation_id = q.id WHERE q.organization_id = ? AND b.payment_status IN ('unpaid', 'partial')", [$organization_id])['value']
];

// Calculate conversion rate
$kpis['conversion_rate'] = $kpis['quotations']['total'] > 0 ? ($kpis['quotations']['approved'] / $kpis['quotations']['total']) * 100 : 0;

// Get quotation status distribution
$status_distribution = $db->fetchAll(
    "SELECT status, COUNT(*) as count
     FROM quotations_new
     WHERE organization_id = ? AND created_at BETWEEN ? AND ?
     GROUP BY status
     ORDER BY count DESC",
    [$organization_id, $start_date, $end_date]
);

// Get monthly trends (last 12 months)
$monthly_trends = $db->fetchAll(
    "SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as quotation_count,
        SUM(total_amount) as total_value,
        SUM(CASE WHEN status IN ('approved', 'repair_in_progress', 'repair_complete', 'bill_generated', 'paid') THEN 1 ELSE 0 END) as approved_count
     FROM quotations_new
     WHERE organization_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month DESC",
    [$organization_id]
);

// Get top customers by value
$top_customers = $db->fetchAll(
    "SELECT
        customer_name,
        customer_email,
        COUNT(*) as quotation_count,
        SUM(total_amount) as total_value,
        AVG(total_amount) as avg_value
     FROM quotations_new
     WHERE organization_id = ? AND created_at BETWEEN ? AND ?
     GROUP BY customer_name, customer_email
     ORDER BY total_value DESC
     LIMIT 10",
    [$organization_id, $start_date, $end_date]
);

// Get vehicle analysis
$vehicle_analysis = $db->fetchAll(
    "SELECT
        vehicle_registration,
        vehicle_make_model,
        COUNT(*) as service_count,
        SUM(total_amount) as total_value,
        MAX(created_at) as last_service
     FROM quotations_new
     WHERE organization_id = ? AND created_at BETWEEN ? AND ?
     GROUP BY vehicle_registration, vehicle_make_model
     ORDER BY service_count DESC
     LIMIT 10",
    [$organization_id, $start_date, $end_date]
);

// Get inventory insights (if available)
$inventory_stats = [];
if ($db->fetchAll("SHOW TABLES LIKE 'inventory'")) {
    $inventory_stats = [
        'total_items' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ?", [$organization_id])['count'],
        'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND available_stock <= reorder_level", [$organization_id])['count'],
        'total_value' => $db->fetch("SELECT COALESCE(SUM(current_stock * average_cost), 0) as value FROM inventory WHERE organization_id = ?", [$organization_id])['value']
    ];
}

// Get recent activity
$recent_activity = $db->fetchAll(
    "SELECT ual.*, u.full_name as user_name
     FROM user_activity_log ual
     JOIN users_new u ON ual.user_id = u.id
     WHERE ual.organization_id = ?
     ORDER BY ual.created_at DESC
     LIMIT 20",
    [$organization_id]
);

// Average processing times
$avg_times = $db->fetch(
    "SELECT
        AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_approval_time,
        AVG(TIMESTAMPDIFF(HOUR, approved_at, repair_completed_at)) as avg_repair_time,
        AVG(TIMESTAMPDIFF(HOUR, repair_completed_at, bill_generated_at)) as avg_billing_time
     FROM quotations_new
     WHERE organization_id = ? AND created_at BETWEEN ? AND ?",
    [$organization_id, $start_date, $end_date]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/material.css" rel="stylesheet">
    <link href="../assets/css/style-base.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-layout {
            padding: 20px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .header-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .date-filters {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #1976d2, #42a5f5);
        }

        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 8px;
        }

        .kpi-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-change {
            font-size: 12px;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .kpi-positive { background: #e8f5e8; color: #2e7d32; }
        .kpi-negative { background: #ffebee; color: #c62828; }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .data-tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .table-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .metric-value {
            font-weight: 600;
            color: #1976d2;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            font-size: 11px;
            color: #666;
            margin-bottom: 4px;
        }

        .activity-user {
            font-weight: 500;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .activity-desc {
            font-size: 13px;
            color: #555;
        }

        .export-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary { background: #1976d2; color: white; }
        .btn-success { background: #388e3c; color: white; }
        .btn-info { background: #0288d1; color: white; }
        .btn-outline { background: transparent; border: 1px solid #ddd; color: #666; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .charts-grid,
            .data-tables-grid {
                grid-template-columns: 1fr;
            }

            .date-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="reports-layout">
        <!-- Header -->
        <div class="header-section">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0; color: #333;">Reports Dashboard</h1>
                    <p style="margin: 8px 0 0 0; color: #666;">
                        Comprehensive analytics and insights for <?php echo htmlspecialchars($_SESSION['organization_name']); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="quotation_manager.php" class="btn btn-outline">
                        <i class="material-icons">arrow_back</i> Back to Quotations
                    </a>
                    <a href="inventory_manager.php" class="btn btn-outline">
                        <i class="material-icons">inventory</i> Inventory
                    </a>
                </div>
            </div>

            <div class="date-filters">
                <label style="font-weight: 500;">Date Range:</label>
                <input type="date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                <span>to</span>
                <input type="date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                <button class="btn btn-primary" onclick="updateReports()">
                    <i class="material-icons">refresh</i> Update Reports
                </button>
                <div class="export-buttons">
                    <button class="btn btn-success" onclick="exportToPDF()">
                        <i class="material-icons">picture_as_pdf</i> Export PDF
                    </button>
                    <button class="btn btn-info" onclick="exportToExcel()">
                        <i class="material-icons">table_chart</i> Export Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['quotations']['total']; ?></div>
                <div class="kpi-label">Total Quotations</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value"><?php echo number_format($kpis['conversion_rate'], 1); ?>%</div>
                <div class="kpi-label">Approval Rate</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value"><?php echo formatCurrency($kpis['financial']['total_value']); ?></div>
                <div class="kpi-label">Total Quote Value</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value"><?php echo formatCurrency($kpis['financial']['paid_value']); ?></div>
                <div class="kpi-label">Revenue Collected</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value"><?php echo formatCurrency($kpis['financial']['outstanding']); ?></div>
                <div class="kpi-label">Outstanding Amount</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value"><?php echo $kpis['quotations']['pending']; ?></div>
                <div class="kpi-label">Pending Approval</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Monthly Quotation Trends</div>
                <canvas id="monthlyTrendsChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <div class="chart-title">Quotation Status Distribution</div>
                <canvas id="statusDistributionChart" height="300"></canvas>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="data-tables-grid">
            <div class="table-card">
                <div class="chart-title">Top Customers by Value</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Quotations</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($top_customers, 0, 8) as $customer): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($customer['customer_email'] ?: 'No email'); ?></div>
                                </td>
                                <td><?php echo $customer['quotation_count']; ?></td>
                                <td class="metric-value"><?php echo formatCurrency($customer['total_value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="chart-title">Vehicle Service Analysis</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Services</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($vehicle_analysis, 0, 8) as $vehicle): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($vehicle['vehicle_registration']); ?></div>
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($vehicle['vehicle_make_model'] ?: 'Unknown model'); ?></div>
                                </td>
                                <td><?php echo $vehicle['service_count']; ?></td>
                                <td class="metric-value"><?php echo formatCurrency($vehicle['total_value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Performance Metrics -->
        <?php if ($avg_times['avg_approval_time']): ?>
        <div class="chart-card" style="margin-bottom: 30px;">
            <div class="chart-title">Average Processing Times</div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 16px;">
                <div style="text-align: center; padding: 16px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 600; color: #1976d2;">
                        <?php echo number_format($avg_times['avg_approval_time'] ?? 0, 1); ?>h
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">Avg. Approval Time</div>
                </div>
                <div style="text-align: center; padding: 16px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 600; color: #1976d2;">
                        <?php echo number_format($avg_times['avg_repair_time'] ?? 0, 1); ?>h
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">Avg. Repair Time</div>
                </div>
                <div style="text-align: center; padding: 16px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 600; color: #1976d2;">
                        <?php echo number_format($avg_times['avg_billing_time'] ?? 0, 1); ?>h
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">Avg. Billing Time</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="table-card">
            <div class="chart-title">Recent System Activity</div>
            <div class="activity-timeline">
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-time"><?php echo formatDate($activity['created_at'], 'd/m/Y H:i'); ?></div>
                        <div class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></div>
                        <div class="activity-desc"><?php echo htmlspecialchars($activity['description']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Chart configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        };

        // Monthly Trends Chart
        const monthlyData = <?php echo json_encode(array_reverse($monthly_trends)); ?>;
        new Chart(document.getElementById('monthlyTrendsChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [
                    {
                        label: 'Total Quotations',
                        data: monthlyData.map(item => item.quotation_count),
                        borderColor: '#1976d2',
                        backgroundColor: 'rgba(25, 118, 210, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Approved',
                        data: monthlyData.map(item => item.approved_count),
                        borderColor: '#388e3c',
                        backgroundColor: 'rgba(56, 142, 60, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                ...chartOptions,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusData = <?php echo json_encode($status_distribution); ?>;
        new Chart(document.getElementById('statusDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.status.replace('_', ' ')),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        '#1976d2', '#388e3c', '#f57c00', '#d32f2f',
                        '#7b1fa2', '#0288d1', '#5d4037', '#616161'
                    ]
                }]
            },
            options: chartOptions
        });

        // Functions
        function updateReports() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = `reports_dashboard.php?start_date=${startDate}&end_date=${endDate}`;
        }

        function exportToPDF() {
            console.log('Export to PDF');
            // Implementation for PDF export
        }

        function exportToExcel() {
            console.log('Export to Excel');
            // Implementation for Excel export
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>