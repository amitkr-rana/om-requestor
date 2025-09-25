<?php
// Admin sidebar component
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get organization name if not already loaded
if (!isset($organizationName)) {
    $orgResult = $db->fetch("SELECT name FROM organizations LIMIT 1");
    $organizationName = $orgResult ? $orgResult['name'] : 'Organization';
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
                <a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'admin' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">dashboard</span>
                    <p class="text-sm font-medium leading-normal">Dashboard</p>
                </a>
                <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'users' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">group</span>
                    <p class="text-sm font-medium leading-normal">Users</p>
                </a>
                <a href="quotations.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'quotations' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">receipt</span>
                    <p class="text-sm font-medium leading-normal">Quotations</p>
                </a>
                <a href="vehicles.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'vehicles' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">directions_car</span>
                    <p class="text-sm font-medium leading-normal">Vehicles</p>
                </a>
                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?php echo $currentPage === 'reports' ? 'bg-blue-100 text-blue-700' : 'text-blue-600 hover:bg-blue-50'; ?> transition-colors">
                    <span class="material-icons text-xl">assessment</span>
                    <p class="text-sm font-medium leading-normal">Reports</p>
                </a>
            </div>
        </div>
        <div class="border-t border-blue-100 pt-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="material-icons text-white text-lg">security</span>
                </div>
                <div>
                    <p class="text-blue-900 text-sm font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <p class="text-blue-600 text-xs"><?php echo ucfirst($_SESSION['role']); ?></p>
                </div>
            </div>
            <a href="../api/auth.php?action=logout" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors w-full">
                <span class="material-icons text-xl">logout</span>
                <p class="text-sm font-medium leading-normal">Logout</p>
            </a>
        </div>
    </div>
</div>