<?php
require_once '../includes/functions.php';

requireRole('admin'); // Only admins can access inventory management

$pageTitle = 'Inventory Manager';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_item':
            if (!verifyCSRFToken($_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit;
            }

            $name = sanitize($_POST['name']);
            $base_price = floatval($_POST['base_price']);
            $stock = floatval($_POST['stock']);

            if (empty($name) || $base_price < 0 || $stock < 0) {
                echo json_encode(['success' => false, 'message' => 'Please fill all fields with valid values']);
                exit;
            }

            $result = $db->query(
                "INSERT INTO inventory (organization_id, name, selling_price, current_stock, item_code, unit, is_active, created_at) VALUES (?, ?, ?, ?, ?, 'nos', 1, NOW())",
                [$_SESSION['organization_id'], $name, $base_price, $stock, 'ITM' . time()]
            );

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Item added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add item']);
            }
            exit;

        case 'edit_item':
            if (!verifyCSRFToken($_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit;
            }

            $id = intval($_POST['id']);
            $name = sanitize($_POST['name']);
            $base_price = floatval($_POST['base_price']);
            $stock = floatval($_POST['stock']);

            if (empty($name) || $base_price < 0 || $stock < 0) {
                echo json_encode(['success' => false, 'message' => 'Please fill all fields with valid values']);
                exit;
            }

            $result = $db->query(
                "UPDATE inventory SET name = ?, selling_price = ?, current_stock = ?, updated_at = NOW() WHERE id = ? AND organization_id = ?",
                [$name, $base_price, $stock, $id, $_SESSION['organization_id']]
            );

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update item']);
            }
            exit;

        case 'delete_item':
            if (!verifyCSRFToken($_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                exit;
            }

            $id = intval($_POST['id']);

            $result = $db->query(
                "DELETE FROM inventory WHERE id = ? AND organization_id = ?",
                [$id, $_SESSION['organization_id']]
            );

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
            }
            exit;

        case 'get_item':
            $id = intval($_POST['id']);
            $item = $db->fetch(
                "SELECT id, name, selling_price, current_stock FROM inventory WHERE id = ? AND organization_id = ? AND is_active = 1",
                [$id, $_SESSION['organization_id']]
            );

            if ($item) {
                echo json_encode(['success' => true, 'item' => $item]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found']);
            }
            exit;
    }
}

// Get inventory statistics
$stats = [
    'total_items' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1", [$_SESSION['organization_id']])['count'],
    'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1 AND current_stock > 0 AND current_stock <= 10", [$_SESSION['organization_id']])['count'],
    'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1 AND current_stock <= 0", [$_SESSION['organization_id']])['count'],
    'total_value' => $db->fetch("SELECT COALESCE(SUM(current_stock * selling_price), 0) as value FROM inventory WHERE organization_id = ? AND is_active = 1", [$_SESSION['organization_id']])['value']
];

// Get inventory items
$inventory_items = $db->fetchAll(
    "SELECT id, name, selling_price, current_stock, item_code, created_at
     FROM inventory
     WHERE organization_id = ? AND is_active = 1
     ORDER BY name ASC",
    [$_SESSION['organization_id']]
);
?>

<?php include '../includes/admin_head.php'; ?>

                <?php include '../includes/admin_sidebar_new.php'; ?>

                <!-- Main content area -->
                <div class="flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <!-- Main content -->
                    <div class="p-6">
                        <!-- Header section -->
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-blue-900">Inventory Manager</h1>
                                <p class="text-blue-600 text-sm mt-1">Manage your inventory items with ease</p>
                            </div>
                            <button onclick="showAddItemModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                                <span class="material-icons text-sm">add</span>
                                Add Item
                            </button>
                        </div>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="bg-white rounded-lg border border-blue-100 p-6">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <span class="material-icons text-blue-600">inventory</span>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['total_items']; ?></p>
                                        <p class="text-blue-600 text-sm">Total Items</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg border border-blue-100 p-6">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <span class="material-icons text-orange-600">warning</span>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['low_stock']; ?></p>
                                        <p class="text-blue-600 text-sm">Low Stock</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg border border-blue-100 p-6">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                        <span class="material-icons text-red-600">error</span>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-blue-900 text-2xl font-bold"><?php echo $stats['out_of_stock']; ?></p>
                                        <p class="text-blue-600 text-sm">Out of Stock</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-lg border border-blue-100 p-6">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                        <span class="material-icons text-green-600">monetization_on</span>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-blue-900 text-2xl font-bold"><?php echo formatCurrency($stats['total_value']); ?></p>
                                        <p class="text-blue-600 text-sm">Total Value</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter -->
                        <div class="bg-white rounded-lg border border-blue-100 p-4 mb-6">
                            <div class="flex flex-wrap gap-4 items-center">
                                <input type="text" id="searchFilter" placeholder="Search items..." class="flex-1 min-w-64 px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onkeyup="filterItems()">
                                <button onclick="clearFilters()" class="px-4 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                                    Clear
                                </button>
                                <button onclick="refreshInventory()" class="px-4 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors flex items-center gap-2">
                                    <span class="material-icons text-sm">refresh</span>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Inventory Items Grid -->
                        <div id="inventory-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($inventory_items as $item): ?>
                                <?php
                                $stock_class = 'text-green-600';
                                $stock_status = 'In Stock';
                                if ($item['current_stock'] <= 0) {
                                    $stock_class = 'text-red-600';
                                    $stock_status = 'Out of Stock';
                                } elseif ($item['current_stock'] <= 10) {
                                    $stock_class = 'text-orange-600';
                                    $stock_status = 'Low Stock';
                                }
                                ?>
                                <div class="bg-white rounded-lg border border-blue-100 p-6 hover:shadow-md transition-shadow"
                                     data-search="<?php echo htmlspecialchars(strtolower($item['name'] . ' ' . $item['item_code'])); ?>">

                                    <!-- Item Header -->
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-blue-900 mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="text-blue-600 text-sm"><?php echo htmlspecialchars($item['item_code']); ?></p>
                                        </div>
                                    </div>

                                    <!-- Stock Info -->
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-blue-600 text-sm">Stock</p>
                                            <p class="text-xl font-bold <?php echo $stock_class; ?>"><?php echo number_format($item['current_stock'], 0); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-blue-600 text-sm">Base Price</p>
                                            <p class="text-xl font-bold text-blue-900"><?php echo formatCurrency($item['selling_price']); ?></p>
                                        </div>
                                    </div>

                                    <!-- Status Badge -->
                                    <div class="mb-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $item['current_stock'] <= 0 ? 'bg-red-100 text-red-800' : ($item['current_stock'] <= 10 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'); ?>">
                                            <?php echo $stock_status; ?>
                                        </span>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex gap-2">
                                        <button onclick="editItem(<?php echo $item['id']; ?>)" class="flex-1 px-3 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors text-sm flex items-center justify-center gap-1">
                                            <span class="material-icons text-sm">edit</span>
                                            Edit
                                        </button>
                                        <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="px-3 py-2 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors text-sm flex items-center justify-center">
                                            <span class="material-icons text-sm">delete</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($inventory_items)): ?>
                                <div class="col-span-full text-center py-12">
                                    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <span class="material-icons text-blue-600 text-4xl">inventory</span>
                                    </div>
                                    <h3 class="text-lg font-medium text-blue-900 mb-2">No inventory items found</h3>
                                    <p class="text-blue-600 mb-4">Add your first inventory item to get started with stock management.</p>
                                    <button onclick="showAddItemModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        Add Your First Item
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Modals -->
    <div id="addItemModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-blue-900">Add New Item</h3>
                    <button onclick="closeAddItemModal()" class="text-blue-400 hover:text-blue-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <form id="addItemForm">
                    <?php echo csrfField(); ?>
                    <div class="space-y-4">
                        <div>
                            <label for="itemName" class="block text-sm font-medium text-blue-900 mb-1">Item Name *</label>
                            <input type="text" id="itemName" name="name" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="basePrice" class="block text-sm font-medium text-blue-900 mb-1">Base Price *</label>
                            <input type="number" id="basePrice" name="base_price" step="0.01" min="0" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="stock" class="block text-sm font-medium text-blue-900 mb-1">Stock Quantity *</label>
                            <input type="number" id="stock" name="stock" min="0" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeAddItemModal()" class="flex-1 px-4 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-blue-900">Edit Item</h3>
                    <button onclick="closeEditItemModal()" class="text-blue-400 hover:text-blue-600">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <form id="editItemForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" id="editItemId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="editItemName" class="block text-sm font-medium text-blue-900 mb-1">Item Name *</label>
                            <input type="text" id="editItemName" name="name" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="editBasePrice" class="block text-sm font-medium text-blue-900 mb-1">Base Price *</label>
                            <input type="number" id="editBasePrice" name="base_price" step="0.01" min="0" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="editStock" class="block text-sm font-medium text-blue-900 mb-1">Stock Quantity *</label>
                            <input type="number" id="editStock" name="stock" min="0" required class="w-full px-3 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeEditItemModal()" class="flex-1 px-4 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteItemModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                        <span class="material-icons text-red-600">warning</span>
                    </div>
                    <h3 class="text-lg font-semibold text-blue-900">Delete Item</h3>
                </div>
                <p class="text-blue-600 mb-6">Are you sure you want to delete this item? This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button onclick="closeDeleteItemModal()" class="flex-1 px-4 py-2 text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmDeleteItem()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg hidden z-50">
        <span id="notificationMessage"></span>
    </div>

    <script>
        let deleteItemId = null;

        // Filter functionality
        function filterItems() {
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            const items = document.querySelectorAll('[data-search]');

            items.forEach(item => {
                const searchText = item.getAttribute('data-search');
                const searchMatch = !searchFilter || searchText.includes(searchFilter);

                if (searchMatch) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            document.getElementById('searchFilter').value = '';
            filterItems();
        }

        function refreshInventory() {
            window.location.reload();
        }

        // Modal functions
        function showAddItemModal() {
            document.getElementById('addItemModal').classList.remove('hidden');
        }

        function closeAddItemModal() {
            document.getElementById('addItemModal').classList.add('hidden');
            document.getElementById('addItemForm').reset();
        }

        function showEditItemModal() {
            document.getElementById('editItemModal').classList.remove('hidden');
        }

        function closeEditItemModal() {
            document.getElementById('editItemModal').classList.add('hidden');
            document.getElementById('editItemForm').reset();
        }

        function showDeleteItemModal() {
            document.getElementById('deleteItemModal').classList.remove('hidden');
        }

        function closeDeleteItemModal() {
            document.getElementById('deleteItemModal').classList.add('hidden');
            deleteItemId = null;
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageElement = document.getElementById('notificationMessage');

            messageElement.textContent = message;
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-600' : 'bg-red-600'
            } text-white`;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 3000);
        }

        // AJAX functions
        function submitForm(form, action) {
            const formData = new FormData(form);
            formData.append('action', action);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        function editItem(id) {
            const formData = new FormData();
            formData.append('action', 'get_item');
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editItemId').value = data.item.id;
                    document.getElementById('editItemName').value = data.item.name;
                    document.getElementById('editBasePrice').value = data.item.selling_price;
                    document.getElementById('editStock').value = data.item.current_stock;
                    showEditItemModal();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        function deleteItem(id) {
            deleteItemId = id;
            showDeleteItemModal();
        }

        function confirmDeleteItem() {
            if (!deleteItemId) return;

            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('id', deleteItemId);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeDeleteItemModal();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                closeDeleteItemModal();
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        // Form submissions
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'add_item');
            closeAddItemModal();
        });

        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'edit_item');
            closeEditItemModal();
        });

        // Close modals when clicking outside
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>