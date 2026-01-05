<?php
// Helper Functions

require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect with message
 */
function redirectTo($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit();
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * ========== TIMEZONE HELPER FUNCTIONS ==========
 * All functions use Asia/Jakarta timezone (UTC+7)
 */

/**
 * Convert datetime to Jakarta timezone
 * @param string|int $datetime Datetime string or timestamp
 * @return DateTime|false DateTime object in Jakarta timezone or false on failure
 */
function convertToJakartaTime($datetime) {
    if (empty($datetime)) {
        return false;
    }
    
    try {
        $timezone = new DateTimeZone('Asia/Jakarta');
        
        if (is_numeric($datetime)) {
            // It's a timestamp
            $dt = new DateTime('@' . $datetime);
            $dt->setTimezone($timezone);
        } else {
            // It's a datetime string
            $dt = new DateTime($datetime);
            $dt->setTimezone($timezone);
        }
        
        return $dt;
    } catch (Exception $e) {
        error_log("Error converting to Jakarta time: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current datetime in Jakarta timezone
 * @param string $format Format string (default: 'Y-m-d H:i:s')
 * @return string Formatted datetime string
 */
function getCurrentDateTime($format = 'Y-m-d H:i:s') {
    try {
        $dt = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Error getting current datetime: " . $e->getMessage());
        return date($format);
    }
}

/**
 * Format datetime with Jakarta timezone
 * @param string|int $datetime Datetime string or timestamp
 * @param string $format Format string (default: 'd M Y H:i')
 * @return string Formatted datetime string or '-' if empty/invalid
 */
function formatDateTime($datetime, $format = 'd M Y H:i') {
    if (empty($datetime)) {
        return '-';
    }
    
    $dt = convertToJakartaTime($datetime);
    if ($dt === false) {
        return '-';
    }
    
    return $dt->format($format);
}

/**
 * Format date with Jakarta timezone
 * @param string|int $date Date string or timestamp
 * @param string $format Format string (default: 'd M Y')
 * @return string Formatted date string or '-' if empty/invalid
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) {
        return '-';
    }
    
    $dt = convertToJakartaTime($date);
    if ($dt === false) {
        return '-';
    }
    
    return $dt->format($format);
}

/**
 * Format time with Jakarta timezone
 * @param string|int $time Time string or timestamp
 * @param string $format Format string (default: 'H:i')
 * @return string Formatted time string or '-' if empty/invalid
 */
function formatTime($time, $format = 'H:i') {
    if (empty($time)) {
        return '-';
    }
    
    $dt = convertToJakartaTime($time);
    if ($dt === false) {
        return '-';
    }
    
    return $dt->format($format);
}

/**
 * Get icon SVG
 */
function icon($iconName, $class = '', $width = 24, $height = 24) {
    try {
        // Normalize icon name (remove extension if provided)
        $iconName = preg_replace('/\.svg$/i', '', $iconName);
        
        // Use ASSETS_PATH constant
        $iconPath = ASSETS_PATH . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . $iconName . '.svg';
        
        // Also try with forward slashes (for cross-platform compatibility)
        if (!file_exists($iconPath)) {
            $iconPath = ASSETS_PATH . '/icons/' . $iconName . '.svg';
        }
        
        if (!file_exists($iconPath)) {
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

