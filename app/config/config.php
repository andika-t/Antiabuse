<?php
// Application Configuration

// Set timezone to Jakarta (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie untuk kompatibilitas dengan localhost dan custom domain
    // Cookie domain kosong = current domain (bekerja untuk localhost dan custom domain)
    // Ini penting untuk mencegah session cookie domain mismatch yang menyebabkan redirect loop
    // Secure = false untuk HTTP (akan true jika HTTPS)
    // HttpOnly = true untuk security (mencegah XSS)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieDomain = ''; // Empty = current domain (works for localhost and custom domains)
    // Domain kosong memastikan cookie dibuat untuk domain yang digunakan user (localhost atau antiabuse.local)
    $sessionTimeout = 1800; // 30 minutes - akan didefinisikan sebagai konstanta nanti
    
    // Log cookie configuration untuk debugging
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'unknown';
    error_log("Session cookie config - Host: " . $currentHost . ", Domain: '" . $cookieDomain . "', Secure: " . ($isSecure ? 'true' : 'false'));
    
    session_set_cookie_params([
        'lifetime' => $sessionTimeout,
        'path' => '/',
        'domain' => $cookieDomain, // Empty = current domain, prevents domain mismatch
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax' // Lax untuk compatibility dengan OAuth redirects
    ]);
    
    session_start();
    
    // Log session start untuk debugging
    error_log("Session started - ID: " . session_id() . ", Host: " . $currentHost);
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
 * PRIORITAS: HTTP_HOST dari request actual > APP_URL dari environment
 * Ini mencegah redirect ke domain yang berbeda (localhost vs antiabuse.local)
 */
function getBaseUrl() {
    // PRIORITAS 1: Gunakan HTTP_HOST dari request actual untuk mencegah domain mismatch
    // Ini penting untuk mencegah redirect loop saat login Google
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? null;
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    // Jika HTTP_HOST tersedia, gunakan itu (selalu sesuai dengan request actual)
    if ($host && $host !== '') {
        // Check if we're in public folder (local dev) atau di Docker (document root = public)
    $isInPublic = strpos($scriptName, '/public/') !== false;
        
        // Detect Docker: document root berakhir dengan /html atau /public (typical Docker setup)
        // Atau jika SCRIPT_NAME tidak mengandung /public/ dan bukan root
        $isDocker = (
            strpos($documentRoot, '/html') !== false || 
            strpos($documentRoot, '/public') !== false ||
            (strpos($scriptName, '/public/') === false && $scriptName !== '/')
        );
    
    if ($isInPublic) {
        // Local dev: script is in /public/xxx.php -> go up 2 levels
        $rootPath = dirname(dirname($scriptName));
    } else {
        // Docker/production: document root is already public/
            // Di Docker, SCRIPT_NAME seperti /auth/google_callback.php
            // Kita tidak perlu menambahkan path karena document root sudah public
            // BASE_URL harus langsung ke host root
            $rootPath = '';
    }
    
    // Clean up root path
    if ($rootPath === '/' || $rootPath === '\\') {
        $rootPath = '';
    }
    
    $baseUrl = $protocol . $host . $rootPath;
    
        // JANGAN tambahkan /public jika di Docker (document root sudah public)
        // Hanya tambahkan /public jika local dev dan script tidak di public folder
        if (!$isDocker && !$isInPublic && strpos($scriptName, '/public') === false) {
        $baseUrl .= '/public';
    }
    
        $detectedUrl = rtrim($baseUrl, '/');
        error_log("BASE_URL detected from HTTP_HOST: " . $detectedUrl);
        error_log("BASE_URL debug - SCRIPT_NAME: " . $scriptName . ", DOCUMENT_ROOT: " . $documentRoot . ", isDocker: " . ($isDocker ? 'true' : 'false') . ", isInPublic: " . ($isInPublic ? 'true' : 'false'));
        return $detectedUrl;
    }
    
    // PRIORITAS 2: Fallback ke APP_URL dari environment jika HTTP_HOST tidak tersedia
    if (isset($_ENV['APP_URL']) && !empty($_ENV['APP_URL'])) {
        $fallbackUrl = rtrim($_ENV['APP_URL'], '/');
        error_log("BASE_URL using APP_URL fallback: " . $fallbackUrl);
        return $fallbackUrl;
    }
    
    // PRIORITAS 3: Fallback ke localhost jika semua tidak tersedia
    $fallbackUrl = 'http://localhost';
    error_log("BASE_URL using default fallback: " . $fallbackUrl);
    return $fallbackUrl;
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

// Check session timeout - hanya jika user sudah logged in dan last_activity sudah ada
// Jangan destroy session yang baru dibuat (< 5 detik) untuk mencegah race condition
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    $timeSinceLastActivity = time() - $_SESSION['last_activity'];
    
    // Hanya check timeout jika last_activity sudah lebih dari 5 detik
    // Ini mencegah session yang baru dibuat langsung di-destroy
    if ($timeSinceLastActivity > 5 && $timeSinceLastActivity > SESSION_TIMEOUT) {
        error_log("Session timeout for user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit();
    }
}

// Update last activity - hanya jika user sudah logged in
// Jangan set last_activity jika belum login untuk mencegah false timeout checks
if (isLoggedIn()) {
$_SESSION['last_activity'] = time();
}

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
    // Pastikan BASE_URL sesuai dengan request actual (bukan hardcoded)
    // BASE_URL sudah di-detect dari HTTP_HOST di getBaseUrl()
    $baseUrl = BASE_URL;
    
    // Validasi role
    if (empty($role)) {
        error_log("redirectByRole - Empty role provided, defaulting to general_user");
        $role = 'general_user';
    }
    
    // Tentukan redirect URL berdasarkan role
    $redirectUrl = '';
    switch($role) {
        case 'admin':
            $redirectUrl = $baseUrl . '/admin/index.php';
            break;
        case 'police':
            $redirectUrl = $baseUrl . '/police/index.php';
            break;
        case 'psychologist':
            $redirectUrl = $baseUrl . '/psychologist/index.php';
            break;
        case 'general_user':
        default:
            $redirectUrl = $baseUrl . '/user/index.php';
            break;
    }
    
    // Validasi URL sebelum redirect
    if (empty($redirectUrl)) {
        error_log("redirectByRole - Invalid redirect URL for role: " . $role);
        $redirectUrl = $baseUrl . '/user/index.php'; // Fallback
    }
    
    // Log untuk debugging
    error_log("redirectByRole - Redirecting user with role '" . $role . "' to: " . $redirectUrl);
    error_log("redirectByRole - BASE_URL: " . $baseUrl);
    error_log("redirectByRole - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET'));
    error_log("redirectByRole - Session ID: " . session_id());
    error_log("redirectByRole - Session data: user_id=" . ($_SESSION['user_id'] ?? 'NOT SET') . ", username=" . ($_SESSION['username'] ?? 'NOT SET'));
    
    // Pastikan tidak ada output sebelum header
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Redirect dengan absolute URL
    header('Location: ' . $redirectUrl);
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

