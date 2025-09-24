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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="assets/css/material.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h1 class="display-1">OM Requestor</h1>
                <p class="subtitle">Vehicle Maintenance Management System</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <span class="material-icons">error</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-content">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>

                        <div class="input-field">
                            <input type="text" id="username" name="username" required>
                            <label for="username">Username or Email</label>
                            <span class="material-icons prefix">person</span>
                        </div>

                        <div class="input-field">
                            <input type="password" id="password" name="password" required>
                            <label for="password">Password</label>
                            <span class="material-icons prefix">lock</span>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary btn-large btn-block">
                            <span class="material-icons left">login</span>
                            Login
                        </button>
                    </form>
                </div>
            </div>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> OM Engineers. All rights reserved.</p>
                <p class="text-muted">Sammaan Foundation Vehicle Management</p>
            </div>
        </div>
    </div>

    <script src="assets/js/material.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>