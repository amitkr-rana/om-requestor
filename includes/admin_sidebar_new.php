<?php
// Enhanced admin sidebar component for new quotation-centric system
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get organization name if not already loaded
if (!isset($organizationName)) {
    $orgResult = $db->fetch("SELECT name FROM organizations_new WHERE id = ? LIMIT 1", [$_SESSION['organization_id']]);
    $organizationName = $orgResult ? $orgResult['name'] : ($_SESSION['organization_name'] ?? 'Organization');
}
?>
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
                <!-- Dashboard -->
                <a href="admin_new.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo in_array($currentPage, ['admin', 'admin_new']) ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">dashboard</span>
                    <p class="text-sm font-medium leading-normal">Dashboard</p>
                </a>

                <!-- Quotation Management -->
                <a href="quotation_manager.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'quotation_manager' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">assignment</span>
                    <p class="text-sm font-medium leading-normal">Quotation Manager</p>
                </a>

                <!-- Inventory Management -->
                <a href="inventory_manager.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'inventory_manager' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">inventory</span>
                    <p class="text-sm font-medium leading-normal">Inventory</p>
                    <?php
                    // Show low stock indicator
                    $lowStockCount = $db->fetch("SELECT COUNT(*) as count FROM inventory WHERE organization_id = ? AND is_active = 1 AND available_stock <= reorder_level", [$_SESSION['organization_id']]);
                    if ($lowStockCount && $lowStockCount['count'] > 0):
                    ?>
                    <span class="ml-auto bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                        <?php echo $lowStockCount['count']; ?>
                    </span>
                    <?php endif; ?>
                </a>

                <!-- User Management -->
                <a href="users.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'users' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">group</span>
                    <p class="text-sm font-medium leading-normal">Users</p>
                </a>


                <!-- Reports and Analytics -->
                <a href="reports_dashboard.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'reports_dashboard' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">analytics</span>
                    <p class="text-sm font-medium leading-normal">Reports & Analytics</p>
                </a>

                <!-- System Management Section -->
                <div class="border-t border-blue-100 pt-4 mt-4">
                    <p class="text-blue-500 text-xs font-medium uppercase tracking-wide mb-2 px-4">System</p>

                    <!-- Billing & Payments -->
                    <a href="billing_manager.php"
                       class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'billing_manager' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                        <span class="material-icons text-xl">payment</span>
                        <p class="text-sm font-medium leading-normal">Billing</p>
                        <?php
                        // Show overdue bills count
                        $overdueCount = $db->fetch("SELECT COUNT(*) as count FROM billing b JOIN quotations_new q ON b.quotation_id = q.id WHERE q.organization_id = ? AND b.payment_status IN ('unpaid', 'partial') AND b.due_date < CURDATE()", [$_SESSION['organization_id']]);
                        if ($overdueCount && $overdueCount['count'] > 0):
                        ?>
                        <span class="ml-auto bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                            <?php echo $overdueCount['count']; ?>
                        </span>
                        <?php endif; ?>
                    </a>

                    <!-- Activity Log -->
                    <a href="activity_log.php"
                       class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'activity_log' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                        <span class="material-icons text-xl">history</span>
                        <p class="text-sm font-medium leading-normal">Activity Log</p>
                    </a>
                </div>

                <!-- Quick Actions Section -->
                <div class="border-t border-blue-100 pt-4 mt-4">
                    <p class="text-blue-500 text-xs font-medium uppercase tracking-wide mb-2 px-4">Quick Actions</p>

                    <!-- Create Quotation -->
                    <button onclick="window.location.href='quotation_manager.php?action=create'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                        <span class="material-icons text-xl">add_circle</span>
                        <p class="text-sm font-medium leading-normal">New Quotation</p>
                    </button>

                    <!-- Add Inventory Item -->
                    <button onclick="window.location.href='inventory_manager.php?action=add_item'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                        <span class="material-icons text-xl">add_box</span>
                        <p class="text-sm font-medium leading-normal">Add Inventory</p>
                    </button>

                    <!-- Add User (Common) -->
                    <button onclick="window.location.href='users.php?action=create'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                        <span class="material-icons text-xl">person_add</span>
                        <p class="text-sm font-medium leading-normal">Add User</p>
                    </button>
                </div>
            </div>
        </div>

        <!-- User Profile and System Info -->
        <div class="border-t border-blue-100 pt-4">
            <!-- Organization Display -->
            <div class="mb-4 px-3 py-2 bg-blue-50 rounded-lg">
                <p class="text-blue-600 text-xs font-medium uppercase tracking-wide">Organization</p>
                <p class="text-blue-900 text-sm font-semibold truncate" title="<?php echo htmlspecialchars($organizationName); ?>">
                    <?php echo htmlspecialchars($organizationName); ?>
                </p>
            </div>

            <!-- User Profile -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="material-icons text-white text-lg">
                        <?php echo $_SESSION['role'] === 'admin' ? 'admin_panel_settings' : 'person'; ?>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-blue-900 text-sm font-medium truncate"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <div class="flex items-center gap-2">
                        <p class="text-blue-600 text-xs"><?php echo ucfirst($_SESSION['role']); ?></p>
                        <?php if ($_SESSION['role'] === 'admin' && isset($_SESSION['is_om_engineers_admin'])): ?>
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-0.5 rounded-full">System Admin</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logout Button -->
            <a href="../api/auth.php?action=logout"
               class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors w-full">
                <span class="material-icons text-xl">logout</span>
                <p class="text-sm font-medium leading-normal">Logout</p>
            </a>
        </div>
    </div>
</div>