<?php
// Admin header component - Enhanced for new quotation-centric system
// Expects $pageTitle to be set before including this file

// Get all organizations for dropdown (only for admins, exclude Om Engineers)
$allOrganizations = [];
if ($_SESSION['role'] === 'admin') {
    $allOrganizations = $db->fetchAll("SELECT id, name FROM organizations_new WHERE id != 15 ORDER BY name");
}

// Get current organization info - ensure Om Engineers admin defaults to first client org
$currentOrgId = $_SESSION['organization_id'] ?? 0;

// If current user is Om Engineers admin but no org selected, default to first available client org
if ($currentOrgId == 15 && count($allOrganizations) > 0) {
    $currentOrgId = $allOrganizations[0]['id'];
    $_SESSION['organization_id'] = $currentOrgId;
    $_SESSION['organization_name'] = $allOrganizations[0]['name'];
}

// Get current organization name
if ($currentOrgId == 15) {
    // This should not happen anymore, but keep as fallback
    $currentOrgName = 'Om Engineers (System Admin)';
} else {
    $currentOrg = $db->fetch("SELECT name FROM organizations_new WHERE id = ?", [$currentOrgId]);
    $currentOrgName = $currentOrg['name'] ?? 'Organization';
}
?>
<div class="flex justify-between items-center p-6 bg-white border-b border-blue-100">
    <h1 class="text-blue-900 text-3xl font-bold"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>

    <div class="flex items-center gap-4">
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-mode-toggle" title="Toggle dark mode">
            <span class="material-icons icon" id="darkModeIcon">dark_mode</span>
        </button>

        <?php if ($_SESSION['role'] === 'admin' && count($allOrganizations) > 0): ?>
        <!-- Organization Switcher for Admins -->
        <div class="flex items-center gap-3">
            <span class="text-blue-600 text-sm font-medium">Organization:</span>
            <div class="relative">
                <select id="orgSwitcher" class="bg-white border border-blue-200 rounded-lg px-3 py-2 text-sm font-medium text-blue-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                    <!-- Only show client organizations - Om Engineers is never shown -->
                    <?php foreach ($allOrganizations as $org): ?>
                        <option value="<?php echo $org['id']; ?>" <?php echo $currentOrgId == $org['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($org['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                    <span class="material-icons text-blue-400 text-sm">expand_more</span>
                </div>
            </div>
            <!-- Current Organization Display -->
        </div>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
        <!-- Admin with no client organizations available -->
        <div class="flex items-center gap-2 text-red-600 text-sm font-medium">
            <span class="material-icons text-red-500 text-sm">warning</span>
            <span>No client organizations available</span>
        </div>
        <?php else: ?>
        <!-- Regular Organization Display -->
        <div class="text-blue-600 text-sm font-medium">
            <?php echo htmlspecialchars($currentOrgName); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($_SESSION['role'] === 'admin'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dark mode
    initializeDarkMode();

    // Organization switcher
    const orgSwitcher = document.getElementById('orgSwitcher');

    if (orgSwitcher) {
        orgSwitcher.addEventListener('change', function() {
            const selectedOrgId = this.value;
            const currentOrgId = <?php echo json_encode($currentOrgId); ?>;

            if (selectedOrgId != currentOrgId) {
                // Send AJAX request to switch organization
                switchOrganization(selectedOrgId);
            }
        });
    }
});

// Dark mode functionality using ThemeManager
function initializeDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeIcon = document.getElementById('darkModeIcon');

    // Initialize with current theme
    updateToggleButton(window.ThemeManager.getCurrentTheme());

    // Toggle dark mode
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            const newTheme = window.ThemeManager.toggleTheme();
            updateToggleButton(newTheme);

            // Smooth visual feedback
            darkModeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                darkModeToggle.style.transform = '';
            }, 150);
        });
    }

    // Listen for theme changes from other sources
    document.addEventListener('themeChanged', function(e) {
        updateToggleButton(e.detail.theme);
    });

    function updateToggleButton(theme) {
        if (darkModeIcon && darkModeToggle) {
            if (theme === 'dark') {
                darkModeIcon.textContent = 'light_mode';
                darkModeToggle.title = 'Switch to light mode';
                darkModeToggle.setAttribute('aria-label', 'Switch to light mode');
            } else {
                darkModeIcon.textContent = 'dark_mode';
                darkModeToggle.title = 'Switch to dark mode';
                darkModeToggle.setAttribute('aria-label', 'Switch to dark mode');
            }
        }
    }
}

async function switchOrganization(orgId) {
    try {
        const formData = new FormData();
        formData.append('action', 'switch_organization');
        formData.append('organization_id', orgId);
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

        const response = await fetch('../api/admin.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            // Show success message briefly before reload
            console.log('Successfully switched to: ' + result.organization_name);
            // Reload current page to show new organization context
            window.location.reload();
        } else {
            alert(result.error || 'Failed to switch organization');
        }
    } catch (error) {
        console.error('Organization switching error:', error);
        alert('Network error occurred while switching organization: ' + error.message);
    }
}
</script>
<?php endif; ?>