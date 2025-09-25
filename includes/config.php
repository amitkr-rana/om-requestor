<?php
// Configuration file for om-requestor system

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'om_requestor');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'Om Engineers');
define('APP_VERSION', '1.0.0');

// Dynamic base URL detection
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $basePath = str_replace(basename($script), '', $script);
    return $protocol . '://' . $host . rtrim($basePath, '/');
}

function getBasePath() {
    $script = $_SERVER['SCRIPT_NAME'];
    return rtrim(str_replace(basename($script), '', $script), '/');
}

// Set dynamic SITE_URL
define('SITE_URL', getBaseUrl());

// Email configuration
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'admin@om-engineers.org');
define('Amit@31122001', ''); // Set your email password here
define('FROM_EMAIL', 'admin@om-engineers.org');
define('FROM_NAME', 'Om Engineers');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>