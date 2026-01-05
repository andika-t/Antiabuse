<?php
// Application Configuration

// Set timezone to Jakarta (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== PATH CONFIGURATION ==========

// Define root directory (absolute path)
// app/config/config.php -> go up 2 levels to root
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Define directories
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('ASSETS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'assets');
define('TEMPLATES_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'templates');
define('UPLOADS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');

// ========== URL CONFIGURATION ==========

/**
 * Get base URL with robust detection
 */
function getBaseUrl() {
    // Check environment variable first (for Docker/production)
    if (isset($_ENV['APP_URL']) && !empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }
    
    // Auto-detect from request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    
    // Check if we're in public folder
    $isInPublic = strpos($scriptName, '/public/') !== false;
    
    if ($isInPublic) {
        // Local dev: script is in /public/xxx.php -> go up 2 levels
        $rootPath = dirname(dirname($scriptName));
    } else {
        // Docker/production: document root is already public/
        $rootPath = dirname($scriptName);
    }
    
    // Clean up root path
    if ($rootPath === '/' || $rootPath === '\\') {
        $rootPath = '';
    }
    
    $baseUrl = $protocol . $host . $rootPath;
    
    // Add /public if not in public folder and not Docker
    if (!$isInPublic && strpos($scriptName, '/public') === false) {
        $baseUrl .= '/public';
    }
    
    return rtrim($baseUrl, '/');
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}

// ========== GOOGLE OAUTH CONFIGURATION ==========

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? (BASE_URL . '/auth/google_callback.php'));

// ========== SESSION CONFIGURATION ==========

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check session timeout
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit();
    }
}

// Update last activity
$_SESSION['last_activity'] = time();

// ========== HELPER FUNCTIONS ==========

/**
 * Get asset URL
 */
function asset($path) {
    $assetPath = ltrim($path, '/');
    $base = BASE_URL;
    
    // Remove /public from BASE_URL for assets
    if (strpos($base, '/public') !== false) {
        $base = str_replace('/public', '', $base);
    }
    
    return rtrim($base, '/') . '/assets/' . $assetPath;
}

/**
 * Get upload URL
 */
function uploadUrl($path) {
    $uploadPath = ltrim($path, '/');
    $base = BASE_URL;
    
    // Remove /public from BASE_URL for uploads
    if (strpos($base, '/public') !== false) {
        $base = str_replace('/public', '', $base);
    }
    
    return rtrim($base, '/') . '/uploads/' . $uploadPath;
}

/**
 * Include file with error handling
 */
function safeInclude($filePath) {
    if (file_exists($filePath)) {
        return include $filePath;
    }
    throw new Exception("File not found: " . $filePath);
}

/**
 * Require file with error handling
 */
function safeRequire($filePath) {
    if (file_exists($filePath)) {
        return require $filePath;
    }
    throw new Exception("File not found: " . $filePath);
}

// ========== AUTHENTICATION FUNCTIONS ==========

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $loginUrl = BASE_URL . '/login.php';
        header('Location: ' . $loginUrl);
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = getUserRole();
    if (!in_array($userRole, $allowedRoles)) {
        header('Location: ' . BASE_URL . '/login.php?error=access_denied');
        exit();
    }
}

function redirectByRole($role) {
    $baseUrl = BASE_URL;
    
    switch($role) {
        case 'admin':
            header('Location: ' . $baseUrl . '/admin/index.php');
            break;
        case 'police':
            header('Location: ' . $baseUrl . '/police/index.php');
            break;
        case 'psychologist':
            header('Location: ' . $baseUrl . '/psychologist/index.php');
            break;
        case 'general_user':
        default:
            header('Location: ' . $baseUrl . '/user/index.php');
            break;
    }
    exit();
}

// ========== PERMISSION FUNCTIONS ==========

function canCreateReport() {
    return getUserRole() === 'general_user';
}

function canViewAllReports() {
    $role = getUserRole();
    return in_array($role, ['admin', 'police']);
}

function canProcessReport() {
    $role = getUserRole();
    return in_array($role, ['admin', 'police']);
}

function canAssignReport() {
    return getUserRole() === 'admin';
}

function canViewReport($reportUserId) {
    $role = getUserRole();
    if ($role === 'general_user') {
        return $reportUserId == getUserId();
    } elseif (in_array($role, ['admin', 'police'])) {
        return true;
    }
    return false;
}

// ========== ERROR HANDLING ==========

// Set error reporting based on environment
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorMsg = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($errorMsg);
    
    // In development, show error
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] !== 'production') {
        echo "<div style='background: #fee; padding: 10px; margin: 10px; border: 1px solid #f00;'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($errstr) . "<br>";
        echo "<small>File: $errfile (Line: $errline)</small>";
        echo "</div>";
    }
    
    return true;
});

