<?php
require_once '../includes/functions.php';

requireRole('admin'); // Only admins can access inventory management

// Get inventory statistics
$stats = [
    'total_items' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1", [$_SESSION['organization_id']])['count'],
    'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1 AND available_stock <= reorder_level", [$_SESSION['organization_id']])['count'],
    'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1 AND available_stock <= 0", [$_SESSION['organization_id']])['count'],
    'total_value' => $db->fetch("SELECT COALESCE(SUM(current_stock * average_cost), 0) as value FROM inventory WHERE organization_id = ? AND is_active = 1", [$_SESSION['organization_id']])['value']
];

// Get inventory items with low stock alerts
$low_stock_items = $db->fetchAll(
    "SELECT i.*, c.name as category_name, s.name as supplier_name
     FROM inventory i
     LEFT JOIN inventory_categories c ON i.category_id = c.id
     LEFT JOIN suppliers s ON i.supplier_id = s.id
     WHERE i.organization_id = ? AND i.is_active = 1
       AND i.available_stock <= i.reorder_level
     ORDER BY (i.available_stock - i.reorder_level) ASC, i.name ASC
     LIMIT 10",
    [$_SESSION['organization_id']]
);

// Get recent inventory transactions
$recent_transactions = $db->fetchAll(
    "SELECT it.*, i.name as item_name, i.item_code, u.full_name as performed_by_name
     FROM inventory_transactions it
     JOIN inventory i ON it.inventory_item_id = i.id
     JOIN users_new u ON it.performed_by = u.id
     WHERE it.organization_id = ?
     ORDER BY it.created_at DESC
     LIMIT 20",
    [$_SESSION['organization_id']]
);

// Get categories for dropdown
$categories = $db->fetchAll(
    "SELECT * FROM inventory_categories WHERE is_active = 1 ORDER BY name ASC"
);

// Get suppliers for dropdown
$suppliers = $db->fetchAll(
    "SELECT * FROM suppliers WHERE organization_id = ? AND is_active = 1 ORDER BY name ASC",
    [$_SESSION['organization_id']]
);

// Get inventory items (limited for initial load)
$inventory_items = $db->fetchAll(
    "SELECT i.*, c.name as category_name, s.name as supplier_name
     FROM inventory i
     LEFT JOIN inventory_categories c ON i.category_id = c.id
     LEFT JOIN suppliers s ON i.supplier_id = s.id
     WHERE i.organization_id = ? AND i.is_active = 1
     ORDER BY i.name ASC
     LIMIT 50",
    [$_SESSION['organization_id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/material.css" rel="stylesheet">
    <link href="../assets/css/style-base.css" rel="stylesheet">
    <style>
        .inventory-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-top: 20px;
        }

        .inventory-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 16px;
            margin-bottom: 12px;
            transition: box-shadow 0.3s ease;
        }
        .inventory-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .item-code {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .item-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .item-category {
            color: #666;
            font-size: 12px;
        }

        .stock-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 12px 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .stock-metric {
            text-align: center;
        }
        .stock-value {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .stock-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .low-stock { color: #f57c00; }
        .out-of-stock { color: #d32f2f; }
        .in-stock { color: #388e3c; }

        .item-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .stat-label {
            color: #666;
            text-transform: uppercase;
            font-size: 12px;
            font-weight: 500;
        }

        .alert-card {
            background: #fff8e1;
            border-left: 4px solid #ff9800;
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }
        .alert-title {
            font-weight: 600;
            color: #ef6c00;
            margin-bottom: 8px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .sidebar-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .sidebar-title {
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .transaction-item {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .transaction-item:last-child {
            border-bottom: none;
        }
        .transaction-type {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .type-purchase { background: #e8f5e8; color: #2e7d32; }
        .type-consumption { background: #ffebee; color: #c62828; }
        .type-adjustment { background: #e3f2fd; color: #1565c0; }
        .type-allocation { background: #fff3e0; color: #ef6c00; }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary { background: #1976d2; color: white; }
        .btn-success { background: #388e3c; color: white; }
        .btn-warning { background: #f57c00; color: white; }
        .btn-info { background: #0288d1; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover {
            color: black;
        }

        @media (max-width: 768px) {
            .inventory-grid {
                grid-template-columns: 1fr;
            }
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .stock-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Inventory Manager</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showAddItemModal()">
                        <i class="material-icons">add</i> Add Item
                    </button>
                    <button class="btn btn-success" onclick="showAddStockModal()">
                        <i class="material-icons">add_box</i> Add Stock
                    </button>
                    <a href="quotation_manager.php" class="btn btn-outline">Back to Quotations</a>
                </div>
            </div>
        </header>

        <!-- Stats Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #1976d2;"><?php echo $stats['total_items']; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f57c00;"><?php echo $stats['low_stock']; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #d32f2f;"><?php echo $stats['out_of_stock']; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #388e3c;"><?php echo formatCurrency($stats['total_value']); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if ($stats['low_stock'] > 0): ?>
        <div class="alert-card">
            <div class="alert-title">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">warning</i>
                Low Stock Alert
            </div>
            <div>You have <?php echo $stats['low_stock']; ?> items running low on stock. Check the sidebar for details.</div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-row">
                <select id="categoryFilter" class="form-control" onchange="filterItems()" style="max-width: 200px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="stockFilter" class="form-control" onchange="filterItems()" style="max-width: 200px;">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
                <input type="text" id="searchFilter" class="form-control" placeholder="Search items..." onkeyup="filterItems()" style="max-width: 300px;">
                <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                <button class="btn btn-info" onclick="refreshInventory()">
                    <i class="material-icons">refresh</i> Refresh
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="inventory-grid">
            <!-- Inventory Items -->
            <div class="inventory-section">
                <h2>Inventory Items <span class="count">(<?php echo count($inventory_items); ?>)</span></h2>
                <div id="inventory-container">
                    <?php foreach ($inventory_items as $item): ?>
                        <?php
                        $stock_class = 'in-stock';
                        $stock_status = 'In Stock';
                        if ($item['available_stock'] <= 0) {
                            $stock_class = 'out-of-stock';
                            $stock_status = 'Out of Stock';
                        } elseif ($item['available_stock'] <= $item['reorder_level']) {
                            $stock_class = 'low-stock';
                            $stock_status = 'Low Stock';
                        }
                        ?>
                        <div class="inventory-item"
                             data-category="<?php echo $item['category_id']; ?>"
                             data-stock="<?php echo $stock_status === 'In Stock' ? 'in_stock' : ($stock_status === 'Low Stock' ? 'low_stock' : 'out_of_stock'); ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($item['name'] . ' ' . $item['item_code'] . ' ' . $item['description'])); ?>">

                            <div class="item-header">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-category">
                                        <?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?>
                                        <?php if ($item['supplier_name']): ?>
                                            • Supplier: <?php echo htmlspecialchars($item['supplier_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-code"><?php echo htmlspecialchars($item['item_code']); ?></div>
                            </div>

                            <?php if ($item['description']): ?>
                                <div style="margin-bottom: 12px; color: #666; font-size: 14px;">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="stock-info">
                                <div class="stock-metric">
                                    <div class="stock-value <?php echo $stock_class; ?>">
                                        <?php echo number_format($item['available_stock'], 2); ?>
                                    </div>
                                    <div class="stock-label">Available</div>
                                </div>
                                <div class="stock-metric">
                                    <div class="stock-value">
                                        <?php echo number_format($item['allocated_stock'], 2); ?>
                                    </div>
                                    <div class="stock-label">Allocated</div>
                                </div>
                                <div class="stock-metric">
                                    <div class="stock-value">
                                        <?php echo number_format($item['current_stock'], 2); ?>
                                    </div>
                                    <div class="stock-label">Total</div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin: 12px 0; font-size: 12px;">
                                <span>Unit: <?php echo htmlspecialchars($item['unit']); ?></span>
                                <span>Reorder Level: <?php echo number_format($item['reorder_level'], 2); ?></span>
                                <?php if ($item['selling_price'] > 0): ?>
                                    <span>Price: <?php echo formatCurrency($item['selling_price']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="item-actions">
                                <button class="btn-sm btn-primary" onclick="editItem(<?php echo $item['id']; ?>)">
                                    <i class="material-icons">edit</i> Edit
                                </button>
                                <button class="btn-sm btn-success" onclick="addStockToItem(<?php echo $item['id']; ?>)">
                                    <i class="material-icons">add</i> Add Stock
                                </button>
                                <button class="btn-sm btn-warning" onclick="adjustStock(<?php echo $item['id']; ?>)">
                                    <i class="material-icons">tune</i> Adjust
                                </button>
                                <button class="btn-sm btn-info" onclick="viewHistory(<?php echo $item['id']; ?>)">
                                    <i class="material-icons">history</i> History
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($inventory_items)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #666;">
                            <i class="material-icons" style="font-size: 64px; margin-bottom: 20px;">inventory</i>
                            <h3>No inventory items found</h3>
                            <p>Add your first inventory item to get started with stock management.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Low Stock Items -->
                <?php if (!empty($low_stock_items)): ?>
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 8px; color: #f57c00;">warning</i>
                        Low Stock Items
                    </div>
                    <?php foreach ($low_stock_items as $item): ?>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
                            <div style="font-weight: 500; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                Available: <span class="<?php echo $item['available_stock'] <= 0 ? 'out-of-stock' : 'low-stock'; ?>">
                                    <?php echo number_format($item['available_stock'], 2); ?>
                                </span>
                                / Reorder: <?php echo number_format($item['reorder_level'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Recent Transactions -->
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">history</i>
                        Recent Transactions
                    </div>
                    <?php foreach (array_slice($recent_transactions, 0, 10) as $transaction): ?>
                        <div class="transaction-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span class="transaction-type type-<?php echo $transaction['transaction_type']; ?>">
                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                </span>
                                <span style="font-size: 11px; color: #666;">
                                    <?php echo formatDate($transaction['created_at'], 'd/m H:i'); ?>
                                </span>
                            </div>
                            <div style="font-weight: 500; font-size: 13px; margin-bottom: 2px;">
                                <?php echo htmlspecialchars($transaction['item_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                <?php echo number_format(abs($transaction['quantity']), 2); ?> units
                                • <?php echo htmlspecialchars($transaction['performed_by_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Actions -->
                <div class="sidebar-section">
                    <div class="sidebar-title">Quick Actions</div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button class="btn btn-primary btn-sm" onclick="showAddCategoryModal()">
                            <i class="material-icons">category</i> Add Category
                        </button>
                        <button class="btn btn-success btn-sm" onclick="showAddSupplierModal()">
                            <i class="material-icons">business</i> Add Supplier
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="generateLowStockReport()">
                            <i class="material-icons">report</i> Low Stock Report
                        </button>
                        <button class="btn btn-info btn-sm" onclick="exportInventory()">
                            <i class="material-icons">file_download</i> Export Inventory
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals will be loaded here -->
    <div id="modal-container"></div>

    <script src="../assets/js/inventory-manager.js"></script>
    <script>
        function filterItems() {
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            const items = document.querySelectorAll('.inventory-item');

            items.forEach(item => {
                const category = item.getAttribute('data-category');
                const stock = item.getAttribute('data-stock');
                const searchText = item.getAttribute('data-search');

                const categoryMatch = !categoryFilter || category === categoryFilter;
                const stockMatch = !stockFilter || stock === stockFilter;
                const searchMatch = !searchFilter || searchText.includes(searchFilter);

                if (categoryMatch && stockMatch && searchMatch) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            document.getElementById('categoryFilter').value = '';
            document.getElementById('stockFilter').value = '';
            document.getElementById('searchFilter').value = '';
            filterItems();
        }

        function refreshInventory() {
            window.location.reload();
        }

        // Placeholder functions for modals (to be implemented)
        function showAddItemModal() {
            console.log('Show add item modal');
        }

        function showAddStockModal() {
            console.log('Show add stock modal');
        }

        function editItem(id) {
            console.log('Edit item:', id);
        }

        function addStockToItem(id) {
            console.log('Add stock to item:', id);
        }

        function adjustStock(id) {
            console.log('Adjust stock for item:', id);
        }

        function viewHistory(id) {
            console.log('View history for item:', id);
        }

        function showAddCategoryModal() {
            console.log('Show add category modal');
        }

        function showAddSupplierModal() {
            console.log('Show add supplier modal');
        }

        function generateLowStockReport() {
            console.log('Generate low stock report');
        }

        function exportInventory() {
            console.log('Export inventory');
        }
    </script>
</body>
</html>