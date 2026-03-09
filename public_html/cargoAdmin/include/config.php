<?php
// ========================================
// GLOBAL CONFIGURATION
// ========================================
// Centralized configuration for the entire application
// Change these values based on your environment

// ========================================
// ENVIRONMENT DETECTION
// ========================================
// Automatically detect if running on localhost or production
$isLocalhost = (
    $_SERVER['HTTP_HOST'] === 'localhost' || 
    $_SERVER['HTTP_HOST'] === '127.0.0.1' || 
    strpos($_SERVER['HTTP_HOST'], '10.') === 0 ||
    strpos($_SERVER['HTTP_HOST'], '192.168.') === 0
);

// ========================================
// DATABASE CONFIGURATION
// ========================================
if ($isLocalhost) {
    // LOCAL DEVELOPMENT SETTINGS
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'dbcargo');
} else {
    // PRODUCTION SETTINGS (Hostinger)
    define('DB_HOST', 'localhost'); // Hostinger uses localhost
    define('DB_USER', 'u672913452_ethan'); // Your Hostinger DB user
    define('DB_PASS', 'Cityhunter_23'); // Your actual password
    define('DB_NAME', 'u672913452_dbcargo'); // Your Hostinger DB name
}

// ========================================
// DOMAIN CONFIGURATION
// ========================================
if ($isLocalhost) {
    // LOCAL DEVELOPMENT
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/cargoAdmin');
    define('DOMAIN', $_SERVER['HTTP_HOST']);
} else {
    // PRODUCTION (Hostinger)
    define('BASE_URL', 'http://cargoph.online/cargoAdmin');
    define('DOMAIN', 'cargoph.online');
}

// Common URLs
define('API_URL', BASE_URL . '/api');
define('UPLOADS_URL', BASE_URL . '/uploads');

// ========================================
// APPLICATION SETTINGS
// ========================================
define('APP_NAME', 'CarGO Philippines');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

// ========================================
// ERROR REPORTING
// ========================================
if ($isLocalhost) {
    // Show errors in development
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Hide errors in production
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    
    // Log errors instead
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// ========================================
// SECURITY SETTINGS
// ========================================
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);

// ========================================
// API SETTINGS
// ========================================
define('API_TIMEOUT', 30); // seconds
define('MAX_RESULTS_PER_PAGE', 50);

// ========================================
// SMTP EMAIL SETTINGS (Gmail)
// ========================================
// IMPORTANT: Gmail requires a Google Account "App Password" (not your normal password).
// SMTP host/port:
//   smtp.gmail.com:587 (STARTTLS)

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USE_TLS', true);

define('SMTP_USER', 'ethanjamesestino@gmail.com');
// Put your 16-character Gmail App Password here
define('SMTP_PASS', 'odklrocotulcglqv');

define('SMTP_FROM_EMAIL', 'ethanjamesestino@gmail.com');
define('SMTP_FROM_NAME', APP_NAME);

// ========================================
// CORS SETTINGS
// ========================================
function setCorsHeaders() {
    if ($GLOBALS['isLocalhost']) {
        // Allow all origins in development
        header('Access-Control-Allow-Origin: *');
    } else {
        // Restrict to your domain in production
        header('Access-Control-Allow-Origin: http://cargoph.online');
        header('Access-Control-Allow-Origin: http://www.cargoph.online');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// ========================================
// DEBUG MODE
// ========================================
define('DEBUG_MODE', $isLocalhost);

// Helper function to debug
function debug_log($message, $data = null) {
    if (DEBUG_MODE) {
        error_log("DEBUG: $message");
        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}

// ========================================
// RESPONSE HELPERS
// ========================================
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

function jsonError($message, $statusCode = 400) {
    jsonResponse(false, $message, null, $statusCode);
}

function jsonSuccess($message, $data = null) {
    jsonResponse(true, $message, $data, 200);
}

// ========================================
// INITIALIZATION
// ========================================
// Set CORS headers for all API requests
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    setCorsHeaders();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================
// DISPLAY CURRENT CONFIGURATION
// ========================================
if (DEBUG_MODE && isset($_GET['show_config'])) {
    echo "<h2>Current Configuration</h2>";
    echo "<p><strong>Environment:</strong> " . ($isLocalhost ? "LOCAL DEVELOPMENT" : "PRODUCTION") . "</p>";
    echo "<p><strong>DB Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>DB Name:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Base URL:</strong> " . BASE_URL . "</p>";
    echo "<p><strong>API URL:</strong> " . API_URL . "</p>";
    echo "<p><strong>Uploads URL:</strong> " . UPLOADS_URL . "</p>";
    exit();
}
