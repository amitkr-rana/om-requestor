<?php
require_once '../includes/functions.php';

requireRole('admin');

// Get parameters to determine view mode
$action = $_GET['action'] ?? '';
$request_id = (int)($_GET['request_id'] ?? 0);

// If no action is specified, show the choice dialog
if (empty($action)) {
    // Show choice page
    $showChoice = true;
} else {
    $showChoice = false;
}

// Determine if this is standalone (direct) or service request based quotation
$isStandalone = ($action === 'standalone');

// If creating quotation for specific service request
if ($action === 'create' && $request_id > 0) {
    // Get quotation details
    $serviceRequest = $db->fetch(
        "SELECT q.*, u.full_name as requestor_name, u.email as requestor_email,
                q.organization_id
         FROM quotations_new q
         JOIN users_new u ON q.created_by = u.id
         WHERE q.id = ? AND q.status = 'pending'",
        [$request_id]
    );

    if (!$serviceRequest) {
        header('Location: quotation_creator.php?error=request_not_found');
        exit;
    }

    // Organization filtering
    if ($_SESSION['organization_id'] != 15 && $serviceRequest['organization_id'] != $_SESSION['organization_id']) {
        header('Location: quotation_creator.php?error=access_denied');
        exit;
    }

    // Check if quotation already has pricing
    if ($serviceRequest['total_amount'] > 0) {
        header('Location: quotation_creator.php?error=quotation_exists');
        exit;
    }
} elseif ($isStandalone) {
    // Generate standalone quotation ID for direct quotations
    $currentYear = date('Y');

    // Get or create sequence for current year using direct_quotation_sequence
    $sequence = $db->fetch(
        "SELECT last_number FROM direct_quotation_sequence WHERE year = ?",
        [$currentYear]
    );

    if (!$sequence) {
        // Create new sequence for the year
        $db->query(
            "INSERT INTO direct_quotation_sequence (year, last_number) VALUES (?, 1)",
            [$currentYear]
        );
        $nextNumber = 1;
    } else {
        $nextNumber = $sequence['last_number'] + 1;
        // Update sequence
        $db->query(
            "UPDATE direct_quotation_sequence SET last_number = ? WHERE year = ?",
            [$nextNumber, $currentYear]
        );
    }

    // Generate the standalone quotation ID
    $standaloneRequestId = 'D-' . $currentYear . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
} else {
    // Get pending quotations for pricing
    $conditions = ["q.status = 'pending'"];
    $params = [];

    // Organization filtering
    if ($_SESSION['organization_id'] != 15) {
        $conditions[] = "q.organization_id = ?";
        $params[] = $_SESSION['organization_id'];
    }

    // Only show quotations that don't have pricing yet
    $conditions[] = "q.total_amount = 0";

    $whereClause = implode(' AND ', $conditions);

    $pendingRequests = $db->fetchAll(
        "SELECT q.*, u.full_name as requestor_name
         FROM quotations_new q
         JOIN users_new u ON q.created_by = u.id
         WHERE {$whereClause}
         ORDER BY q.created_at DESC",
        $params
    );

    // Get recent quotations for management
    $recentQuotations = $db->fetchAll(
        "SELECT q.*, u.full_name as requestor_name, u.email as requestor_email
         FROM quotations_new q
         JOIN users_new u ON q.created_by = u.id
         WHERE {$whereClause}
         ORDER BY q.created_at DESC
         LIMIT 10",
        $params
    );
}

$pageTitle = 'Quotation Creator';
include '../includes/admin_head.php';
?>

<?php include '../includes/admin_sidebar_new.php'; ?>
                <div class="layout-content-container flex flex-col flex-1 overflow-y-auto">
                    <?php include '../includes/admin_header.php'; ?>

                    <div id="mainContent" class="p-6">
                        <?php
                        // Check if database needs updating
                        $columns = $db->fetchAll("DESCRIBE quotations_new");
                        $columnNames = $columns ? array_column($columns, 'Field') : [];
                        $needsDbUpdate = !in_array('quotation_number', $columnNames);

                        if ($needsDbUpdate): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                                <div class="flex items-center gap-3">
                                    <span class="material-icons text-yellow-600">warning</span>
                                    <div>
                                        <h3 class="text-yellow-800 font-semibold">Database Update Required</h3>
                                        <p class="text-yellow-700 text-sm mt-1">
                                            The Quotation Creator feature requires database updates to work properly.
                                            <button id="updateDatabaseBtn" class="text-yellow-800 font-semibold underline hover:text-yellow-900 ml-2">
                                                Click here to update now
                                            </button>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($showChoice): ?>
                            <!-- Choice Dialog -->
                            <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                                <div class="p-4 border-b border-blue-100">
                                    <h2 class="text-blue-900 text-lg font-semibold">Create Quotation</h2>
                                    <p class="text-blue-600 text-sm mt-1">Choose the type of quotation you want to create</p>
                                </div>
                                <div class="p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Pending Quotations -->
                                        <div class="border-2 border-blue-100 rounded-lg p-6 hover:border-blue-300 hover:bg-blue-50 transition-colors">
                                            <div class="text-center">
                                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                    <span class="material-icons text-blue-600 text-2xl">pending_actions</span>
                                                </div>
                                                <h3 class="text-blue-900 text-lg font-semibold mb-2">Pending Quotations</h3>
                                                <p class="text-blue-600 text-sm mb-4">Add pricing to quotations created by requestors</p>
                                                <a href="quotation_creator.php?action=list" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium inline-flex items-center gap-2">
                                                    <span class="material-icons text-sm">search</span>
                                                    Browse Quotations
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Walk-in Customer Quotation -->
                                        <div class="border-2 border-green-100 rounded-lg p-6 hover:border-green-300 hover:bg-green-50 transition-colors">
                                            <div class="text-center">
                                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                    <span class="material-icons text-green-600 text-2xl">person_add</span>
                                                </div>
                                                <h3 class="text-green-900 text-lg font-semibold mb-2">Walk-in Customer</h3>
                                                <p class="text-green-600 text-sm mb-4">Create direct quotation for a walk-in customer or verbal request</p>
                                                <a href="quotation_creator.php?action=standalone" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium inline-flex items-center gap-2">
                                                    <span class="material-icons text-sm">add</span>
                                                    Create Direct
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($isStandalone): ?>
                            <!-- Standalone Quotation Creation Form -->
                            <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                                <div class="p-4 border-b border-blue-100">
                                    <div class="flex justify-between items-center">
                                        <h2 class="text-blue-900 text-lg font-semibold">Create Direct Quotation</h2>
                                        <a href="quotation_creator.php?action=list" class="text-blue-600 hover:text-blue-800 text-sm">← View Pending Requests</a>
                                    </div>
                                </div>

                                <!-- Customer Information Form (Editable) -->
                                <div class="p-6 bg-blue-50 border-b border-blue-100">
                                    <h3 class="text-blue-900 text-md font-semibold mb-4">Customer Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-blue-700 text-sm"><strong>Quotation ID:</strong> <?php echo $standaloneRequestId; ?></p>
                                            <p class="text-blue-700 text-sm"><strong>Quotation Date:</strong> <?php echo date('M d, Y'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-blue-600 text-sm"><em>Walk-in Customer / Direct Quotation</em></p>
                                        </div>
                                    </div>

                                    <!-- Editable Customer Fields -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="vehicleRegistration" class="block text-blue-700 text-sm font-medium mb-2">Vehicle Registration Number (Optional)</label>
                                            <input type="text" id="vehicleRegistration" name="vehicle_registration" class="w-full px-3 py-2 border border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-900">
                                        </div>
                                        <div>
                                            <label for="customerName" class="block text-blue-700 text-sm font-medium mb-2">Customer Name</label>
                                            <input type="text" id="customerName" name="customer_name" required class="w-full px-3 py-2 border border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-900">
                                        </div>
                                        <div>
                                            <label for="customerEmail" class="block text-blue-700 text-sm font-medium mb-2">Customer Email (Optional)</label>
                                            <input type="email" id="customerEmail" name="customer_email" class="w-full px-3 py-2 border border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-900">
                                        </div>
                                        <div>
                                            <label for="customerPhone" class="block text-blue-700 text-sm font-medium mb-2">Customer Phone (Optional)</label>
                                            <input type="text" id="customerPhone" name="customer_phone" class="w-full px-3 py-2 border border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-900">
                                        </div>
                                        <div>
                                            <label for="problemDescription" class="block text-blue-700 text-sm font-medium mb-2">Problem Description</label>
                                            <textarea id="problemDescription" name="problem_description" rows="3" required class="w-full px-3 py-2 border border-blue-200 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-blue-900"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quotation Form -->
                                <form id="quotationForm" data-ajax action="../api/quotation_creator.php" method="POST">
                                    <input type="hidden" name="action" value="create_standalone">
                                    <input type="hidden" name="request_id" value="<?php echo $standaloneRequestId; ?>">
                                    <?php echo csrfField(); ?>

                                    <!-- Hidden inputs for customer data (will be populated by JavaScript) -->
                                    <input type="hidden" name="vehicle_registration" id="hiddenVehicleRegistration">
                                    <input type="hidden" name="customer_name" id="hiddenCustomerName">
                                    <input type="hidden" name="customer_email" id="hiddenCustomerEmail">
                                    <input type="hidden" name="customer_phone" id="hiddenCustomerPhone">
                                    <input type="hidden" name="problem_description" id="hiddenProblemDescription">

                                    <div class="p-6 space-y-6">
                                        <!-- Parts and Miscellaneous Items -->
                                        <div>
                                            <h4 class="text-blue-900 font-semibold mb-3">Base Service Charge</h4>
                                            
                                            <!-- Base Service Charge Row -->
                                            <div class="border border-blue-200 rounded-lg p-4 mb-4 bg-blue-50">
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div class="input-field">
                                                        <input type="number" id="baseServiceCharge" name="base_service_charge" step="0.01" min="0" required>
                                                        <label for="baseServiceCharge"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex justify-between items-center mb-3">
                                                <h4 class="text-blue-900 font-semibold">Price Breakup</h4>
                                                <button type="button" id="addItemBtnStandalone" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                                    <span class="material-icons text-sm">add</span> Add Item
                                                </button>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="w-full border border-blue-200 rounded-lg">
                                                    <thead class="bg-blue-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Type</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Description</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Quantity</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Rate (₹)</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Amount (₹)</th>
                                                            <th class="px-4 py-2 text-center text-blue-900 text-sm font-medium">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="itemsTableBodyStandalone">
                                                        <!-- Dynamic rows will be added here -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Tax Calculations -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h4 class="text-blue-900 font-semibold mb-3">Tax Calculations</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <div class="input-field">
                                                        <input type="number" id="sgstRate" name="sgst_rate" value="9.00" step="0.01" min="0" max="100">
                                                        <label for="sgstRate">SGST Rate (%)</label>
                                                    </div>
                                                    <div class="input-field mt-4">
                                                        <input type="number" id="cgstRate" name="cgst_rate" value="9.00" step="0.01" min="0" max="100">
                                                        <label for="cgstRate">CGST Rate (%)</label>
                                                    </div>
                                                </div>
                                                <div class="space-y-3">
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">Subtotal:</span>
                                                        <span id="subtotalDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">SGST:</span>
                                                        <span id="sgstDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">CGST:</span>
                                                        <span id="cgstDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <hr class="border-blue-200">
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-900 font-semibold text-lg">Grand Total:</span>
                                                        <span id="grandTotalDisplay" class="text-blue-900 font-bold text-lg">₹0.00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden fields for calculated values -->
                                        <input type="hidden" id="subtotal" name="subtotal">
                                        <input type="hidden" id="sgstAmount" name="sgst_amount">
                                        <input type="hidden" id="cgstAmount" name="cgst_amount">
                                        <input type="hidden" id="totalAmount" name="total_amount">

                                        <!-- Action Buttons -->
                                        <div class="flex justify-end space-x-3 pt-4 border-t border-blue-100">
                                            <button type="button" onclick="window.location.href='quotation_creator.php?action=list'" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                                                View Requests
                                            </button>
                                            <button type="button" id="previewQuotationBtn" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 inline-flex items-center gap-2">
                                                <span class="material-icons text-sm">visibility</span>
                                                Preview Quotation
                                            </button>
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                                Create Quotation
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        <?php elseif ($action === 'create' && isset($serviceRequest)): ?>
                            <!-- Quotation Creation Form -->
                            <div class="bg-white rounded-lg border border-blue-100 shadow-sm mb-6">
                                <div class="p-4 border-b border-blue-100">
                                    <div class="flex justify-between items-center">&nbsp;</h2>
                                        <a href="quotation_creator.php" class="text-blue-600 hover:text-blue-800 text-sm">← Back to Requests</a>
                                    </div>
                                </div>

                                <!-- Service Request Information -->
                                <div class="p-6 bg-blue-50 border-b border-blue-100">
                                    <h3 class="text-blue-900 text-md font-semibold mb-3">Service Request Details</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-blue-700 text-sm"><strong>Request ID:</strong> #<?php echo $serviceRequest['id']; ?></p>
                                            <p class="text-blue-700 text-sm"><strong>Vehicle:</strong> <?php echo htmlspecialchars($serviceRequest['registration_number']); ?></p>
                                            <p class="text-blue-700 text-sm"><strong>Requestor:</strong> <?php echo htmlspecialchars($serviceRequest['requestor_name']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-blue-700 text-sm"><strong>Email:</strong> <?php echo htmlspecialchars($serviceRequest['requestor_email']); ?></p>
                                            <p class="text-blue-700 text-sm"><strong>Request Date:</strong> <?php echo date('M d, Y', strtotime($serviceRequest['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <p class="text-blue-700 text-sm"><strong>Problem Description:</strong></p>
                                        <p class="text-blue-600 text-sm bg-white p-3 rounded mt-1"><?php echo htmlspecialchars($serviceRequest['problem_description']); ?></p>
                                    </div>
                                </div>

                                <!-- Quotation Form -->
                                <form id="quotationForm" data-ajax action="../api/quotation_creator.php" method="POST">
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="request_id" value="<?php echo $serviceRequest['id']; ?>">
                                    <?php echo csrfField(); ?>

                                    <div class="p-6 space-y-6">
                                        <!-- Parts and Miscellaneous Items -->
                                        <div>
                                            <h4 class="text-blue-900 font-semibold mb-3">Base Service Charge</h4>
                                            
                                            <!-- Base Service Charge Row -->
                                            <div class="border border-blue-200 rounded-lg p-4 mb-4 bg-blue-50">
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div class="input-field">
                                                        <input type="number" id="baseServiceCharge" name="base_service_charge" step="0.01" min="0" required>
                                                        <label for="baseServiceCharge"></label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex justify-between items-center mb-3">
                                                <h4 class="text-blue-900 font-semibold">Price Breakup</h4>
                                                <button type="button" id="addItemBtn" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                                    <span class="material-icons text-sm">add</span> Add Item
                                                </button>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="w-full border border-blue-200 rounded-lg">
                                                    <thead class="bg-blue-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Type</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Description</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Quantity</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Rate (₹)</th>
                                                            <th class="px-4 py-2 text-left text-blue-900 text-sm font-medium">Amount (₹)</th>
                                                            <th class="px-4 py-2 text-center text-blue-900 text-sm font-medium">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="itemsTableBody">
                                                        <!-- Dynamic rows will be added here -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Tax Calculations -->
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h4 class="text-blue-900 font-semibold mb-3">Tax Calculations</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <div class="input-field">
                                                        <input type="number" id="sgstRate" name="sgst_rate" value="9.00" step="0.01" min="0" max="100">
                                                        <label for="sgstRate">SGST Rate (%)</label>
                                                    </div>
                                                    <div class="input-field mt-4">
                                                        <input type="number" id="cgstRate" name="cgst_rate" value="9.00" step="0.01" min="0" max="100">
                                                        <label for="cgstRate">CGST Rate (%)</label>
                                                    </div>
                                                </div>
                                                <div class="space-y-3">
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">Subtotal:</span>
                                                        <span id="subtotalDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">SGST:</span>
                                                        <span id="sgstDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-700">CGST:</span>
                                                        <span id="cgstDisplay" class="text-blue-900 font-medium">₹0.00</span>
                                                    </div>
                                                    <hr class="border-blue-200">
                                                    <div class="flex justify-between">
                                                        <span class="text-blue-900 font-semibold text-lg">Grand Total:</span>
                                                        <span id="grandTotalDisplay" class="text-blue-900 font-bold text-lg">₹0.00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden fields for calculated values -->
                                        <input type="hidden" id="subtotal" name="subtotal">
                                        <input type="hidden" id="sgstAmount" name="sgst_amount">
                                        <input type="hidden" id="cgstAmount" name="cgst_amount">
                                        <input type="hidden" id="totalAmount" name="total_amount">

                                        <!-- Action Buttons -->
                                        <div class="flex justify-end space-x-3 pt-4 border-t border-blue-100">
                                            <button type="button" onclick="window.location.href='quotation_creator.php'" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                                                Cancel
                                            </button>
                                            <button type="button" id="previewQuotationBtnRegular" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 inline-flex items-center gap-2">
                                                <span class="material-icons text-sm">visibility</span>
                                                Preview Quotation
                                            </button>
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                                Send Quotation for Approval
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        <?php else: ?>
                            <!-- Service Requests List -->
                            <div class="bg-white rounded-lg border border-blue-100 shadow-sm">
                                <div class="p-4 border-b border-blue-100">
                                    <h2 class="text-blue-900 text-lg font-semibold">Pending Quotations</h2>
                                    <p class="text-blue-600 text-sm mt-1">Select a quotation to add pricing details</p>
                                </div>

                                <?php if (!empty($pendingRequests)): ?>
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-blue-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Quotation ID</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Customer</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Problem</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Date</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-blue-100">
                                                <?php foreach ($pendingRequests as $request): ?>
                                                    <tr class="hover:bg-blue-50">
                                                        <td class="px-6 py-4 text-blue-900 text-sm font-medium">#<?php echo $request['id']; ?></td>
                                                        <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($request['vehicle_registration']); ?></td>
                                                        <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                                        <td class="px-6 py-4 text-blue-600 text-sm max-w-xs truncate"><?php echo htmlspecialchars(substr($request['problem_description'], 0, 60)); ?>...</td>
                                                        <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                        <td class="px-6 py-4">
                                                            <a href="quotation_creator.php?action=create&request_id=<?php echo $request['id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 transition-colors inline-flex items-center gap-1">
                                                                <span class="material-icons text-sm">add_business</span>
                                                                <span>Create Quotation</span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="material-icons text-4xl text-gray-300">assignment</span>
                                            <p>No pending service requests found</p>
                                            <p class="text-sm text-gray-400">All quotations have been priced or are completed</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Recent Quotations Section -->
                            <?php if (!empty($recentQuotations)): ?>
                                <div class="bg-white rounded-lg border border-blue-100 shadow-sm mt-6">
                                    <div class="p-4 border-b border-blue-100">
                                        <h2 class="text-blue-900 text-lg font-semibold">Recent Quotations</h2>
                                        <p class="text-blue-600 text-sm mt-1">Manage and send created quotations</p>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-blue-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Quotation No</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Vehicle</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Requestor</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Amount</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Date</th>
                                                    <th class="px-6 py-3 text-left text-blue-900 text-sm font-semibold">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-blue-100">
                                                <?php foreach ($recentQuotations as $quotation): ?>
                                                    <tr class="hover:bg-blue-50">
                                                        <td class="px-6 py-4 text-blue-900 text-sm font-medium"><?php echo htmlspecialchars($quotation['quotation_number']); ?></td>
                                                        <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($quotation['vehicle_registration']); ?></td>
                                                        <td class="px-6 py-4 text-blue-700 text-sm"><?php echo htmlspecialchars($quotation['requestor_name']); ?></td>
                                                        <td class="px-6 py-4 text-blue-900 text-sm font-medium">₹<?php echo number_format($quotation['total_amount'], 2); ?></td>
                                                        <td class="px-6 py-4 text-blue-600 text-sm"><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?></td>
                                                        <td class="px-6 py-4">
                                                            <div class="flex items-center space-x-2">
                                                                <a href="../api/quotation_pdf.php?action=preview&quotation_id=<?php echo $quotation['id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 p-1" title="Preview PDF">
                                                                    <span class="material-icons text-sm">visibility</span>
                                                                </a>
                                                                <a href="../api/quotation_pdf.php?action=download&quotation_id=<?php echo $quotation['id']; ?>" class="text-green-600 hover:text-green-800 p-1" title="Download PDF">
                                                                    <span class="material-icons text-sm">download</span>
                                                                </a>
                                                                <button type="button" onclick="sendToRequestor(<?php echo $quotation['id']; ?>, '<?php echo htmlspecialchars($quotation['requestor_email']); ?>')" class="text-orange-600 hover:text-orange-800 p-1" title="Send to Requestor">
                                                                    <span class="material-icons text-sm">send</span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Preview Content Area (Hidden by default) -->
                    <div id="previewContent" style="display: none; position: relative; height: calc(100vh - 140px);">
                        <!-- Floating Action Buttons -->
                        <div class="fixed top-20 right-6 z-50 flex flex-col gap-2">
                            <button type="button" onclick="printPreview()" class="inline-flex items-center justify-center w-12 h-12 text-blue-700 bg-white border border-blue-200 rounded-full shadow-lg hover:bg-blue-50 transition-all duration-200" title="Print Preview">
                                <span class="material-icons text-lg">print</span>
                            </button>
                            <button type="button" onclick="closePreview()" class="inline-flex items-center justify-center w-12 h-12 text-gray-700 bg-white border border-gray-300 rounded-full shadow-lg hover:bg-gray-50 transition-all duration-200" title="Back to Form">
                                <span class="material-icons text-lg">arrow_back</span>
                            </button>
                        </div>

                        <div id="previewIframeContainer" class="w-full h-full">
                            <!-- Preview iframe will be loaded here -->
                            <div class="flex items-center justify-center h-full text-gray-500 bg-gray-50">
                                <div class="text-center">
                                    <span class="material-icons text-4xl mb-2">description</span>
                                    <p>Loading preview...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send to Requestor Modal -->
    <div id="sendToRequestorModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Send Quotation to Requestor</h3>
                <button type="button" class="modal-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="sendToRequestorForm" data-ajax action="../api/quotation_creator.php" data-reload-on-success>
                <div class="modal-content">
                    <input type="hidden" name="action" value="send_to_requestor">
                    <input type="hidden" id="send_quotation_id" name="quotation_id">
                    <?php echo csrfField(); ?>

                    <div class="alert alert-info">
                        <span class="material-icons">info</span>
                        <div>
                            <strong>Send Quotation</strong>
                            <p id="send_requestor_email" class="text-muted"></p>
                        </div>
                    </div>

                    <p class="text-gray-600 text-sm">The quotation will be marked as sent and notification will be sent to the requestor.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-text" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Quotation</button>
                </div>
            </form>
        </div>
    </div>


    <!-- JavaScript for dynamic form functionality -->
    <script src="../assets/js/material.js"></script>
    <script src="../assets/js/main.js"></script>
    <?php if ($action === 'create' || $isStandalone): ?>
    <script>
        let itemCounter = 0;

        // Detect if we're in standalone or regular mode
        const isStandaloneMode = document.getElementById('addItemBtnStandalone') !== null;
        const addButton = isStandaloneMode ? 'addItemBtnStandalone' : 'addItemBtn';
        const tableBody = isStandaloneMode ? 'itemsTableBodyStandalone' : 'itemsTableBody';

        // Add new item row
        document.getElementById(addButton).addEventListener('click', function() {
            addItemRow();
        });

        function addItemRow() {
            const tbody = document.getElementById(tableBody);
            const row = document.createElement('tr');
            row.className = 'border-t border-blue-100';
            row.innerHTML = `
                <td class="px-4 py-2">
                    <select name="items[${itemCounter}][type]" class="w-full px-3 py-2 border border-blue-200 rounded text-sm">
                        <option value="parts">Parts</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </td>
                <td class="px-4 py-2">
                    <input type="text" name="items[${itemCounter}][description]" class="w-full px-3 py-2 border border-blue-200 rounded text-sm" placeholder="Item description" required>
                </td>
                <td class="px-4 py-2">
                    <input type="number" name="items[${itemCounter}][quantity]" step="0.01" min="1" value="1" class="item-quantity w-full px-3 py-2 border border-blue-200 rounded text-sm" required>
                </td>
                <td class="px-4 py-2">
                    <input type="number" name="items[${itemCounter}][rate]" step="0.01" min="0" class="item-rate w-full px-3 py-2 border border-blue-200 rounded text-sm" placeholder="0.00" required>
                </td>
                <td class="px-4 py-2">
                    <input type="number" name="items[${itemCounter}][amount]" step="0.01" min="0" class="item-amount w-full px-3 py-2 border border-blue-200 rounded text-sm bg-gray-100" readonly>
                </td>
                <td class="px-4 py-2 text-center">
                    <button type="button" class="text-red-600 hover:text-red-800" onclick="removeItemRow(this)">
                        <span class="material-icons text-sm">delete</span>
                    </button>
                </td>
            `;
            tbody.appendChild(row);

            // Add event listeners for calculation
            const quantityInput = row.querySelector('.item-quantity');
            const rateInput = row.querySelector('.item-rate');

            quantityInput.addEventListener('input', calculateRowAmount);
            rateInput.addEventListener('input', calculateRowAmount);

            itemCounter++;
        }

        function removeItemRow(button) {
            button.closest('tr').remove();
            calculateTotals();
        }

        function calculateRowAmount(event) {
            const row = event.target.closest('tr');
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const amount = quantity * rate;

            row.querySelector('.item-amount').value = amount.toFixed(2);
            calculateTotals();
        }

        function calculateTotals() {
            const baseServiceCharge = parseFloat(document.getElementById('baseServiceCharge').value) || 0;

            // Calculate items total
            let itemsTotal = 0;
            document.querySelectorAll('.item-amount').forEach(input => {
                itemsTotal += parseFloat(input.value) || 0;
            });

            // Calculate subtotal
            const subtotal = baseServiceCharge + itemsTotal;

            // Get tax rates
            const sgstRate = parseFloat(document.getElementById('sgstRate').value) || 0;
            const cgstRate = parseFloat(document.getElementById('cgstRate').value) || 0;

            // Calculate tax amounts
            const sgstAmount = (subtotal * sgstRate) / 100;
            const cgstAmount = (subtotal * cgstRate) / 100;

            // Calculate grand total
            const grandTotal = subtotal + sgstAmount + cgstAmount;

            // Update displays
            document.getElementById('subtotalDisplay').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('sgstDisplay').textContent = '₹' + sgstAmount.toFixed(2);
            document.getElementById('cgstDisplay').textContent = '₹' + cgstAmount.toFixed(2);
            document.getElementById('grandTotalDisplay').textContent = '₹' + grandTotal.toFixed(2);

            // Update hidden fields
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('sgstAmount').value = sgstAmount.toFixed(2);
            document.getElementById('cgstAmount').value = cgstAmount.toFixed(2);
            document.getElementById('totalAmount').value = grandTotal.toFixed(2);
        }

        // Add event listeners
        document.getElementById('baseServiceCharge').addEventListener('input', calculateTotals);
        document.getElementById('sgstRate').addEventListener('input', calculateTotals);
        document.getElementById('cgstRate').addEventListener('input', calculateTotals);

        // Add one default row
        addItemRow();
    </script>
    <?php endif; ?>

    <script>
        // Database update functionality
        document.addEventListener('DOMContentLoaded', function() {
            const updateBtn = document.getElementById('updateDatabaseBtn');
            if (updateBtn) {
                updateBtn.addEventListener('click', function() {
                    this.textContent = 'Updating...';
                    this.disabled = true;

                    fetch('../api/update_database.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showNotification(data.error || 'Update failed', 'error');
                            this.textContent = 'Click here to update now';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        showNotification('Update failed: ' + error.message, 'error');
                        this.textContent = 'Click here to update now';
                        this.disabled = false;
                    });
                });
            }
        });

        // Send to Requestor functionality
        function sendToRequestor(quotationId, requestorEmail) {
            document.getElementById('send_quotation_id').value = quotationId;
            document.getElementById('send_requestor_email').textContent = 'Sending to: ' + requestorEmail;
            document.getElementById('sendToRequestorModal').style.display = 'flex';
        }

        // Simple notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-[9999] px-6 py-4 rounded-lg shadow-xl max-w-sm text-white transform transition-all duration-300 translate-x-full ${
                type === 'error' ? 'bg-red-600 border-l-4 border-red-800' :
                type === 'success' ? 'bg-green-600 border-l-4 border-green-800' :
                'bg-blue-600 border-l-4 border-blue-800'
            }`;
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-icons text-lg">${
                        type === 'error' ? 'error' :
                        type === 'success' ? 'check_circle' :
                        'info'
                    }</span>
                    <p class="text-sm font-medium">${message}</p>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
                        <span class="material-icons text-lg">close</span>
                    </button>
                </div>
            `;

            // Add to page
            document.body.appendChild(notification);

            // Trigger animation
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 10);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.classList.add('translate-x-full');
                    setTimeout(() => {
                        if (notification && notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }, 5000);

            // Also try Material fallback if available
            if (typeof Material !== 'undefined' && Material.showSnackbar) {
                Material.showSnackbar(message, type);
            }
        }

        // Modal handling
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('sendToRequestorModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });

                const closeButtons = modal.querySelectorAll('.modal-close, [data-close-modal]');
                closeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                });
            }
        });

        // Sync customer information fields with hidden form fields for standalone quotations
        function syncCustomerFields() {
            const vehicleReg = document.getElementById('vehicleRegistration');
            const customerName = document.getElementById('customerName');
            const customerEmail = document.getElementById('customerEmail');
            const customerPhone = document.getElementById('customerPhone');
            const problemDesc = document.getElementById('problemDescription');

            // Check if elements exist (only in standalone mode)
            if (vehicleReg && customerName && customerEmail && customerPhone && problemDesc) {
                document.getElementById('hiddenVehicleRegistration').value = vehicleReg.value;
                document.getElementById('hiddenCustomerName').value = customerName.value;
                document.getElementById('hiddenCustomerEmail').value = customerEmail.value;
                document.getElementById('hiddenCustomerPhone').value = customerPhone.value;
                document.getElementById('hiddenProblemDescription').value = problemDesc.value;
            }
        }

        // Add event listeners to sync fields when they change
        document.addEventListener('DOMContentLoaded', function() {
            const fieldsToSync = ['vehicleRegistration', 'customerName', 'customerEmail', 'customerPhone', 'problemDescription'];

            fieldsToSync.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', syncCustomerFields);
                    field.addEventListener('blur', syncCustomerFields);
                }
            });

            // Sync before form submission
            const quotationForm = document.getElementById('quotationForm');
            if (quotationForm) {
                quotationForm.addEventListener('submit', function(e) {
                    syncCustomerFields();
                });
            }

            // Add phone number validation (numbers only)
            const phoneField = document.getElementById('customerPhone');
            if (phoneField) {
                phoneField.addEventListener('input', function(e) {
                    // Remove any non-digit characters except +, -, space, and parentheses
                    e.target.value = e.target.value.replace(/[^0-9+\-\s()]/g, '');
                });
            }
        });

        // Quotation Preview functionality
        let originalPageTitle = '';
        let currentPreviewData = null;
        let isPreviewMode = false;

        function showPreview() {
            // Store original title and update main header (not sidebar)
            const headerTitle = document.querySelector('h1.text-blue-900.text-3xl.font-bold');
            if (headerTitle) {
                originalPageTitle = headerTitle.textContent;
                headerTitle.textContent = 'Quotation Preview';
            }

            // Hide the main content
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.style.display = 'none';
            }

            // Show the preview content
            const previewContent = document.getElementById('previewContent');
            if (previewContent) {
                previewContent.style.display = 'block';
            }

            // Set preview mode flag
            isPreviewMode = true;
        }

        function closePreview() {
            // Restore original title (main header only, not sidebar)
            const headerTitle = document.querySelector('h1.text-blue-900.text-3xl.font-bold');
            if (headerTitle && originalPageTitle) {
                headerTitle.textContent = originalPageTitle;
            }

            // Hide the preview content
            const previewContent = document.getElementById('previewContent');
            if (previewContent) {
                previewContent.style.display = 'none';
            }

            // Show the main content again
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.style.display = 'block';
            }

            // Clear the preview iframe container
            const previewIframeContainer = document.getElementById('previewIframeContainer');
            if (previewIframeContainer) {
                previewIframeContainer.innerHTML = `
                    <div class="flex items-center justify-center h-full text-gray-500 bg-gray-50">
                        <div class="text-center">
                            <span class="material-icons text-4xl mb-2">description</span>
                            <p>Loading preview...</p>
                        </div>
                    </div>
                `;
            }

            // Reset preview mode flag and clear data
            isPreviewMode = false;
            currentPreviewData = null;
        }

        function printPreview() {
            const iframe = document.querySelector('#previewIframeContainer iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        }

        function validateQuotationForm(isStandalone = false) {
            let errors = [];

            // Always required fields - check in logical order
            const customerName = document.getElementById('customerName');
            const problemDesc = document.getElementById('problemDescription');

            // 1. Customer name first
            if (!customerName || !customerName.value.trim()) {
                errors.push('Customer name is required');
            }

            // 2. Problem description second
            if (!problemDesc || !problemDesc.value.trim()) {
                errors.push('Problem description is required');
            }

            // 3. Vehicle registration (only for regular quotations)
            if (!isStandalone) {
                const vehicleReg = document.getElementById('vehicleRegistration');
                if (!vehicleReg || !vehicleReg.value.trim()) {
                    errors.push('Vehicle registration is required');
                }
            }

            // 4. Base service charge last (financial validation)
            const baseCharge = document.getElementById('baseServiceCharge');
            if (!baseCharge || !baseCharge.value || parseFloat(baseCharge.value) <= 0) {
                errors.push('Base service charge is required and must be greater than zero');
            }

            // For standalone quotations, vehicle registration and email are optional
            // (walk-in customers may not have these details)

            return errors;
        }

        function previewQuotation(isStandalone = false) {
            // Validate form first
            const errors = validateQuotationForm(isStandalone);
            if (errors.length > 0) {
                showNotification(errors[0], 'error');
                return;
            }

            // Sync customer fields for standalone
            if (isStandalone) {
                syncCustomerFields();
            }

            // Collect form data
            const quotationForm = document.getElementById('quotationForm');
            const formData = new FormData(quotationForm);

            // Add preview-specific data
            formData.set('action', 'preview');
            formData.set('quotation_type', isStandalone ? 'standalone' : 'regular');

            // Detect current theme for preview styling
            const isDarkMode = document.documentElement.classList.contains('dark') ||
                              document.body.classList.contains('dark-theme') ||
                              window.ThemeManager?.getCurrentTheme() === 'dark';
            formData.set('theme', isDarkMode ? 'dark' : 'light');

            // Store the form data for theme switching
            currentPreviewData = {
                formData: formData,
                isStandalone: isStandalone
            };

            // Show loading state
            showPreview();
            const previewIframeContainer = document.getElementById('previewIframeContainer');
            previewIframeContainer.innerHTML = `
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                        <p>Generating preview...</p>
                    </div>
                </div>
            `;

            // Make the preview request
            fetch('../api/quotation_creator.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.text();
                } else {
                    // Handle error responses
                    return response.text().then(text => {
                        try {
                            const data = JSON.parse(text);
                            throw new Error(data.error || 'Failed to generate preview');
                        } catch (jsonError) {
                            // If response is not JSON, show raw text
                            throw new Error(`Server error (${response.status}): ${text.substring(0, 200)}...`);
                        }
                    });
                }
            })
            .then(html => {
                // Create an iframe to display the preview
                const iframe = document.createElement('iframe');
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.style.background = 'transparent';
                iframe.style.display = 'block';

                previewIframeContainer.innerHTML = '';
                previewIframeContainer.appendChild(iframe);

                // Write the HTML content to the iframe
                iframe.contentDocument.open();
                iframe.contentDocument.write(html);
                iframe.contentDocument.close();
            })
            .catch(error => {
                console.error('Preview error:', error);
                previewIframeContainer.innerHTML = `
                    <div class="flex items-center justify-center h-full text-red-500 bg-gray-50">
                        <div class="text-center">
                            <span class="material-icons text-4xl mb-2">error</span>
                            <p>Failed to generate preview</p>
                            <p class="text-sm mt-1">${error.message}</p>
                        </div>
                    </div>
                `;
                showNotification('Failed to generate preview: ' + error.message, 'error');
            });
        }

        // Function to refresh preview when theme changes
        function refreshPreviewForTheme() {
            if (!isPreviewMode || !currentPreviewData) {
                return;
            }

            // Update theme in stored form data
            const isDarkMode = document.documentElement.classList.contains('dark') ||
                              document.body.classList.contains('dark-theme') ||
                              window.ThemeManager?.getCurrentTheme() === 'dark';

            // Create new FormData with updated theme
            const updatedFormData = new FormData();
            for (let [key, value] of currentPreviewData.formData.entries()) {
                if (key !== 'theme') {
                    updatedFormData.set(key, value);
                }
            }
            updatedFormData.set('theme', isDarkMode ? 'dark' : 'light');

            // Show loading state
            const previewIframeContainer = document.getElementById('previewIframeContainer');
            if (previewIframeContainer) {
                previewIframeContainer.innerHTML = `
                    <div class="flex items-center justify-center h-full text-gray-500 bg-gray-50">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                            <p>Updating theme...</p>
                        </div>
                    </div>
                `;

                // Make the preview request with updated theme
                fetch('../api/quotation_creator.php', {
                    method: 'POST',
                    body: updatedFormData
                })
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    } else {
                        throw new Error('Failed to refresh preview');
                    }
                })
                .then(html => {
                    // Create an iframe to display the updated preview
                    const iframe = document.createElement('iframe');
                    iframe.style.width = '100%';
                    iframe.style.height = '100%';
                    iframe.style.border = 'none';
                    iframe.style.background = 'transparent';
                    iframe.style.display = 'block';

                    previewIframeContainer.innerHTML = '';
                    previewIframeContainer.appendChild(iframe);

                    // Write the HTML content to the iframe
                    iframe.contentDocument.open();
                    iframe.contentDocument.write(html);
                    iframe.contentDocument.close();
                })
                .catch(error => {
                    console.error('Preview refresh error:', error);
                    previewIframeContainer.innerHTML = `
                        <div class="flex items-center justify-center h-full text-red-500 bg-gray-50">
                            <div class="text-center">
                                <span class="material-icons text-4xl mb-2">error</span>
                                <p>Failed to update theme</p>
                            </div>
                        </div>
                    `;
                });
            }
        }

        // Add event listeners for preview buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Standalone quotation preview button
            const previewBtnStandalone = document.getElementById('previewQuotationBtn');
            if (previewBtnStandalone) {
                previewBtnStandalone.addEventListener('click', function() {
                    previewQuotation(true);
                });
            }

            // Regular quotation preview button
            const previewBtnRegular = document.getElementById('previewQuotationBtnRegular');
            if (previewBtnRegular) {
                previewBtnRegular.addEventListener('click', function() {
                    previewQuotation(false);
                });
            }

            // Close preview on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('previewContent').style.display === 'block') {
                    closePreview();
                }
            });

            // Listen for theme changes and refresh preview if in preview mode
            if (window.ThemeManager) {
                document.addEventListener('themeChanged', function(e) {
                    refreshPreviewForTheme();
                });
            }

            // Also listen for manual theme class changes (fallback)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' &&
                        (mutation.attributeName === 'class' || mutation.attributeName === 'data-theme')) {
                        // Small delay to ensure theme change is complete
                        setTimeout(refreshPreviewForTheme, 100);
                    }
                });
            });

            // Observe changes to document element and body
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            });
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            });
        });
    </script>
</body>
</html>