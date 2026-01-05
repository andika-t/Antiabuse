<?php
// Database Configuration

require_once __DIR__ . '/config.php';

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'antiabuse_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection (singleton pattern)
 * @return PDO
 * @throws PDOException
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set MySQL timezone to Jakarta (UTC+7)
            $conn->exec("SET time_zone = '+07:00'");
            
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            // Don't expose database details in production
            if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
                die("Database connection failed. Please contact administrator.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    return $conn;
}

/**
 * Test database connection
 * @return bool
 */
function testDBConnection() {
    try {
        $conn = getDBConnection();
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

