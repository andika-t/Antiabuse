<?php
// Application Configuration

// Set timezone to Jakarta (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

session_start();

// Base URL - use environment variable if available, otherwise auto-detect
if (isset($_ENV['APP_URL'])) {
    // If APP_URL is set, use it directly (Docker case where document root is already public/)
    define('BASE_URL', $_ENV['APP_URL']);
} else {
    // Auto-detect from current request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    
    // Check if we're in Docker (document root is already public folder)
    // In Docker, SCRIPT_NAME will be like /login.php (not /public/login.php)
    $isDocker = (strpos($scriptName, '/public/') === false && $scriptName !== '/');
    
    if ($isDocker) {
        // Docker: document root is already public/, so no need to add /public
        $rootPath = dirname($scriptName);
        if ($rootPath == '/' || $rootPath == '\\') {
            $rootPath = '';
        }
        define('BASE_URL', $protocol . $host . $rootPath);
    } else {
        // Local development: need to add /public
        $rootPath = dirname(dirname($scriptName));
        if ($rootPath == '/' || $rootPath == '\\') {
            $rootPath = '';
        }
        define('BASE_URL', $protocol . $host . $rootPath . '/public');
    }
}

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? (BASE_URL . '/auth/google_callback.php'));

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Get current user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $baseUrl = rtrim(BASE_URL, '/');
        header('Location: ' . $baseUrl . '/login.php');
        exit();
    }
}

// Get base path for assets
function asset($path) {
    // Ensure path doesn't start with /
    $assetPath = ltrim($path, '/');
    
    // Use BASE_URL - in Docker, BASE_URL is already correct (no /public)
    // In local dev, BASE_URL includes /public, so we need to remove it for assets
    if (defined('BASE_URL')) {
        $base = BASE_URL;
        // Remove /public if exists (for local development compatibility)
        if (strpos($base, '/public') !== false) {
            $base = str_replace('/public', '', $base);
        }
        $base = rtrim($base, '/');
        return $base . '/assets/' . $assetPath;
    }
    
    // Fallback: Auto-detect from current request
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        
        // Check if Docker (no /public in path)
        $isDocker = (strpos($scriptName, '/public/') === false && $scriptName !== '/');
        
        if ($isDocker) {
            // Docker: assets are at /assets
            $rootPath = dirname($scriptName);
            if ($rootPath == '/' || $rootPath == '\\') {
                $rootPath = '';
            }
        } else {
            // Local: assets are at root/assets (need to go up from public)
            $rootPath = dirname(dirname($scriptName));
            if ($rootPath == '/' || $rootPath == '\\') {
                $rootPath = '';
            }
        }
        
        $base = $protocol . $host . $rootPath;
        return rtrim($base, '/') . '/assets/' . $assetPath;
    }
    
    // Final fallback: relative path
    return '../assets/' . $assetPath;
}

// Redirect based on role
function redirectByRole($role) {
    // Get base URL without /public suffix for redirects
    // In Docker, document root is already /var/www/html (public folder)
    $baseUrl = BASE_URL;
    
    // Remove /public from BASE_URL if exists (for Docker compatibility)
    if (strpos($baseUrl, '/public') !== false) {
        $baseUrl = str_replace('/public', '', $baseUrl);
    }
    
    // Ensure base URL doesn't end with /
    $baseUrl = rtrim($baseUrl, '/');
    
    switch($role) {
        case 'admin':
            header('Location: ' . $baseUrl . '/dashboard/admin/index.php');
            break;
        case 'police':
            header('Location: ' . $baseUrl . '/dashboard/police/index.php');
            break;
        case 'psychologist':
            header('Location: ' . $baseUrl . '/dashboard/psychologist/index.php');
            break;
        case 'general_user':
        default:
            header('Location: ' . $baseUrl . '/dashboard/user/index.php');
            break;
    }
    exit();
}

// ========== REPORT ACCESS CONTROL FUNCTIONS ==========

// Check if user can create report
function canCreateReport() {
    return getUserRole() == 'general_user';
}

// Check if user can view all reports
function canViewAllReports() {
    $role = getUserRole();
    return in_array($role, ['admin', 'police']);
}

// Check if user can process report
function canProcessReport() {
    $role = getUserRole();
    return in_array($role, ['admin', 'police']);
}

// Check if user can assign report
function canAssignReport() {
    return getUserRole() == 'admin';
}

// Check if user can view report (own or all)
function canViewReport($reportUserId) {
    $role = getUserRole();
    if ($role == 'general_user') {
        // User can only view their own reports
        return $reportUserId == $_SESSION['user_id'];
    } elseif (in_array($role, ['admin', 'police'])) {
        // Admin and police can view all reports
        return true;
    }
    return false;
}

// ========== SVG ICON HELPER FUNCTION ==========

// Get SVG icon as inline HTML
function icon($iconName, $class = '', $width = 24, $height = 24) {
    try {
        // Normalize icon name (remove extension if provided)
        $iconName = preg_replace('/\.svg$/i', '', $iconName);
        
        // Try multiple possible paths
        $baseDir = dirname(__DIR__);
        $possiblePaths = [
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . $iconName . '.svg',
            $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . $iconName . '.svg',
        ];
        
        // Also try with forward slashes (for cross-platform compatibility)
        $possiblePaths[] = __DIR__ . '/../assets/icons/' . $iconName . '.svg';
        $possiblePaths[] = $baseDir . '/assets/icons/' . $iconName . '.svg';
        
        $iconPath = null;
        foreach ($possiblePaths as $path) {
            $normalizedPath = realpath($path);
            if ($normalizedPath !== false && file_exists($normalizedPath)) {
                $iconPath = $normalizedPath;
                break;
            }
            // Also try without realpath (in case of symlinks or permissions)
            if (file_exists($path)) {
                $iconPath = $path;
                break;
            }
        }
        
        if ($iconPath === null || !file_exists($iconPath)) {
            return '<!-- Icon not found: ' . htmlspecialchars($iconName) . ' -->';
        }
        
        $svgContent = @file_get_contents($iconPath);
        if ($svgContent === false || empty($svgContent)) {
            return '<!-- Failed to read icon: ' . htmlspecialchars($iconName) . ' -->';
        }
        
        // Remove XML declaration and DOCTYPE
        $svgContent = preg_replace('/<\?xml[^>]*\?>/i', '', $svgContent);
        $svgContent = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svgContent);
        $svgContent = preg_replace('/<!--.*?-->/s', '', $svgContent);
        
        // Extract SVG tag and its attributes
        if (!preg_match('/<svg([^>]*)>(.*?)<\/svg>/is', $svgContent, $matches)) {
            return '<!-- Invalid SVG format: ' . htmlspecialchars($iconName) . ' -->';
        }
        
        $attributes = $matches[1];
        $svgInner = $matches[2];
        
        // Remove background and tracer carrier groups
        $svgInner = preg_replace('/<g[^>]*id=["\']SVGRepo_bgCarrier["\'][^>]*>.*?<\/g>/is', '', $svgInner);
        $svgInner = preg_replace('/<g[^>]*id=["\']SVGRepo_tracerCarrier["\'][^>]*>.*?<\/g>/is', '', $svgInner);
        
        // Extract iconCarrier content if exists
        if (preg_match('/<g[^>]*id=["\']SVGRepo_iconCarrier["\'][^>]*>(.*?)<\/g>/is', $svgInner, $iconMatches)) {
            $svgInner = $iconMatches[1];
        }
        
        // Remove transparent rectangles
        $svgInner = preg_replace('/<rect[^>]*(id|class)=["\'][^"\']*[Tt]ransparent[^"\']*["\'][^>]*\/?>/is', '', $svgInner);
        $svgInner = preg_replace('/<rect[^>]*class=["\'][^"\']*cls-1[^"\']*["\'][^>]*\/?>/is', '', $svgInner);
        $svgInner = preg_replace('/<rect[^>]*id=["\'][^"\']*[Tt]ransparent[^"\']*["\'][^>]*\/?>/is', '', $svgInner);
        
        // Remove defs and style tags
        $svgInner = preg_replace('/<defs>.*?<\/defs>/is', '', $svgInner);
        $svgInner = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $svgInner);
        $svgInner = preg_replace('/<title>.*?<\/title>/is', '', $svgInner);
        
        // Replace fill colors
        $svgInner = preg_replace_callback(
            '/fill=["\']([^"\']*)["\']/i',
            function($matches) {
                $fillValue = trim($matches[1]);
                $fillLower = strtolower($fillValue);
                if ($fillLower === 'none' || $fillLower === 'transparent') {
                    return 'fill="' . $fillValue . '"';
                }
                return 'fill="currentColor"';
            },
            $svgInner
        );
        
        // Replace stroke colors
        $svgInner = preg_replace_callback(
            '/stroke=["\']([^"\']*)["\']/i',
            function($matches) {
                $strokeValue = trim($matches[1]);
                $strokeLower = strtolower($strokeValue);
                if ($strokeLower === 'none' || $strokeLower === 'transparent') {
                    return 'stroke="' . $strokeValue . '"';
                }
                return 'stroke="currentColor"';
            },
            $svgInner
        );
        
        // Get viewBox
        $viewBox = '0 0 24 24';
        if (preg_match('/viewBox=["\']([^"\']*)["\']/i', $attributes, $viewBoxMatch)) {
            $viewBox = $viewBoxMatch[1];
        }
        
        // Build new SVG
        $newSvg = '<svg width="' . intval($width) . '" height="' . intval($height) . '"';
        if ($class) {
            $newSvg .= ' class="' . htmlspecialchars($class) . '"';
        }
        $newSvg .= ' viewBox="' . htmlspecialchars($viewBox) . '"';
        $newSvg .= ' fill="currentColor"';
        $newSvg .= ' stroke="currentColor"';
        $newSvg .= ' xmlns="http://www.w3.org/2000/svg">';
        $newSvg .= trim($svgInner);
        $newSvg .= '</svg>';
        
        return $newSvg;
        
    } catch (Exception $e) {
        return '<!-- Error loading icon: ' . htmlspecialchars($iconName) . ' - ' . htmlspecialchars($e->getMessage()) . ' -->';
    }
}
