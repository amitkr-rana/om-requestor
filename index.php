<?php
require_once 'includes/functions.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];

        if (login($username, $password)) {
            // Redirect based on role
            switch ($_SESSION['role']) {
                case 'admin':
                    header('Location: dashboard/admin.php');
                    break;
                case 'requestor':
                    header('Location: dashboard/requestor.php');
                    break;
                case 'approver':
                    header('Location: dashboard/approver.php');
                    break;
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: dashboard/admin.php');
            break;
        case 'requestor':
            header('Location: dashboard/requestor.php');
            break;
        case 'approver':
            header('Location: dashboard/approver.php');
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>

    <!-- CRITICAL: Load theme detection BEFORE any CSS to prevent FOUC -->
    <script src="assets/js/theme-detection.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/material.css" rel="stylesheet">
    <link href="assets/css/style-base.css" rel="stylesheet">
    <link href="assets/css/style-desktop.css" rel="stylesheet" media="(min-width: 769px)">
    <link href="assets/css/style-mobile.css" rel="stylesheet" media="(max-width: 768px)">
</head>
<body class="landing-page">
    <!-- Header Navigation -->
    <header class="landing-header">
        <div class="header-container">
            <div class="brand-section">
                <div class="brand-icon">
                    <span class="material-icons">engineering</span>
                </div>
                <h2 class="brand-name">Om Engineers</h2>
            </div>
            <nav class="header-nav">
                <button id="darkModeToggle" class="dark-mode-toggle" title="Toggle dark mode">
                    <span class="material-icons icon" id="darkModeIcon">dark_mode</span>
                </button>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="landing-main">
        <div class="hero-container">
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="hero-image">
                    <div class="hero-visual">
                        <div class="hero-pattern"></div>
                    </div>
                </div>
                <div class="hero-content">
                    <h1 class="hero-title"> Streamline Your Vehicle Service Management</h1>
                    <p class="hero-subtitle">
                        Manage service requests, quotations, and repairs efficiently with our intuitive platform.
                        Enhance workflow productivity and customer satisfaction.
                    </p>
                    <div class="hero-features">
                        <div class="feature-item">
                            <span class="material-icons">check_circle</span>
                            <span>Request Management</span>
                        </div>
                        <div class="feature-item">
                            <span class="material-icons">check_circle</span>
                            <span>Digital Quotations</span>
                        </div>
                        <div class="feature-item">
                            <span class="material-icons">check_circle</span>
                            <span>Approval Workflow</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Login Section -->
            <section class="login-section">
                <div class="login-card">
                    <div class="login-header">
                        <h3 class="login-title">Get Started</h3>
                        <p class="login-subtitle">Sign in to your account</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-error">
                            <span class="material-icons">error</span>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="login-form">
                        <?php echo csrfField(); ?>

                        <div class="input-field">
                            <input type="text" id="username" name="username" required>
                            <label for="username">Username</label>
                            <span class="material-icons prefix">person</span>
                        </div>

                        <div class="input-field">
                            <input type="password" id="password" name="password" required>
                            <label for="password">Password</label>
                            <span class="material-icons prefix">lock</span>
                        </div>

                        <div class="form-actions">
                            <a href="#" class="forgot-password">Forgot Password?</a>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary btn-large btn-block">
                            <span class="material-icons left">login</span>
                            Login
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-links">
            </div>
            <p class="footer-copyright">
                &copy; <?php echo date('Y'); ?> Om Engineers. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="assets/js/material.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Dark mode functionality for login page using ThemeManager
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeIcon = document.getElementById('darkModeIcon');

            // Initialize with current theme
            updateToggleButton(window.ThemeManager.getCurrentTheme());

            // Toggle dark mode
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    const newTheme = window.ThemeManager.toggleTheme();
                    updateToggleButton(newTheme);

                    // Visual feedback
                    darkModeToggle.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        darkModeToggle.style.transform = '';
                    }, 150);
                });
            }

            // Listen for theme changes
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
        });
    </script>
</body>
</html>