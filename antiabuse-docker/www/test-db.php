<?php
// ============================================
// TEST KONEKSI KE DATABASE
// File ini untuk mengecek apakah web server bisa connect ke database
// Akses: http://localhost:8080/www/test-db.php
// ============================================

// Ambil environment variables dari Docker
$host = getenv('DB_HOST') ?: 'antiabuse-db';
$db = getenv('DB_NAME') ?: 'antiabusedb';
$user = getenv('DB_USER') ?: 'antiabuseuser';
$pass = getenv('DB_PASS') ?: 'secret123';

try {
    // Buat koneksi PDO
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>✅ Koneksi ke MariaDB BERHASIL</h1>";
    
    // Query untuk cek waktu database
    $sql = "SELECT NOW() AS db_time";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Waktu di database:</strong> " . htmlspecialchars($row['db_time']) . "</p>";
    
    // Query untuk cek tabel yang ada
    $sql = "SHOW TABLES";
    $stmt = $pdo->query($sql);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Tabel yang ada di database:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
    
    // Query untuk cek jumlah data di setiap tabel
    echo "<h2>Jumlah Data per Tabel:</h2>";
    echo "<ul>";
    
    $tables_to_check = ['users', 'user_tokens', 'login_attempts', 'panic_logs', 'reports', 'education_contents', 'forum_threads', 'forum_comments'];
    
    foreach ($tables_to_check as $table) {
        try {
            $sql = "SELECT COUNT(*) as total FROM $table";
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<li><strong>" . htmlspecialchars($table) . ":</strong> " . htmlspecialchars($row['total']) . " record(s)</li>";
        } catch (PDOException $e) {
            echo "<li><strong>" . htmlspecialchars($table) . ":</strong> <span style='color:red;'>Error - " . htmlspecialchars($e->getMessage()) . "</span></li>";
        }
    }
    
    echo "</ul>";
    
    echo "<hr>";
    echo "<p><strong>Informasi Koneksi:</strong></p>";
    echo "<ul>";
    echo "<li>Host: " . htmlspecialchars($host) . "</li>";
    echo "<li>Database: " . htmlspecialchars($db) . "</li>";
    echo "<li>User: " . htmlspecialchars($user) . "</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h1>❌ Koneksi ke MariaDB GAGAL</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr>";
    echo "<p><strong>Informasi Koneksi yang Dicoba:</strong></p>";
    echo "<ul>";
    echo "<li>Host: " . htmlspecialchars($host) . "</li>";
    echo "<li>Database: " . htmlspecialchars($db) . "</li>";
    echo "<li>User: " . htmlspecialchars($user) . "</li>";
    echo "</ul>";
    echo "<p><strong>Tips:</strong></p>";
    echo "<ul>";
    echo "<li>Pastikan container database (antiabuse-db) sudah running</li>";
    echo "<li>Pastikan kedua container berada di network yang sama (copypaste)</li>";
    echo "<li>Cek dengan: <code>docker ps</code> dan <code>docker network inspect copypaste</code></li>";
    echo "</ul>";
}
?>


