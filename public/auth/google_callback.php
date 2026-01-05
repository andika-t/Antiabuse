<?php
// Google OAuth Callback

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging (untuk troubleshooting)
error_log("Google OAuth Callback - Code: " . ($_GET['code'] ?? 'NOT SET'));
error_log("Google OAuth Callback - Redirect URI: " . GOOGLE_REDIRECT_URI);
error_log("Google OAuth Callback - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET'));
error_log("Google OAuth Callback - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
error_log("Google OAuth Callback - SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NOT SET'));

if (!isset($_GET['code'])) {
    error_log("Google OAuth Callback - No code parameter");
    // Gunakan BASE_URL untuk konsistensi domain
    $loginUrl = BASE_URL . '/login.php?error=google_auth_failed';
    error_log("Google OAuth Callback - Redirecting to login: " . $loginUrl);
    header('Location: ' . $loginUrl);
    exit();
}

$code = $_GET['code'];

// Exchange code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("Google OAuth - Token response code: " . $http_code);
error_log("Google OAuth - Token response: " . substr($response, 0, 200));

$token = json_decode($response, true);

if (!isset($token['access_token'])) {
    error_log("Google OAuth - Token exchange failed. Response: " . $response);
    if (isset($token['error'])) {
        error_log("Google OAuth - Error: " . $token['error'] . " - " . ($token['error_description'] ?? ''));
    }
    // Gunakan BASE_URL untuk konsistensi domain
    $loginUrl = BASE_URL . '/login.php?error=google_token_failed';
    error_log("Google OAuth - Redirecting to login: " . $loginUrl);
    header('Location: ' . $loginUrl);
    exit();
}

// Get user info from Google
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token['access_token'];
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_info = json_decode(curl_exec($ch), true);
curl_close($ch);

error_log("Google OAuth - User info: " . print_r($user_info, true));

if (!isset($user_info['email'])) {
    error_log("Google OAuth - No email in user info");
    // Gunakan BASE_URL untuk konsistensi domain
    $loginUrl = BASE_URL . '/login.php?error=google_info_failed';
    error_log("Google OAuth - Redirecting to login: " . $loginUrl);
    header('Location: ' . $loginUrl);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Check if user exists by email (if you store email) or create username from email
    $email = $user_info['email'];
    $google_id = $user_info['id'];
    $name = $user_info['name'] ?? $user_info['given_name'] ?? 'User';
    $username = explode('@', $email)[0]; // Use email prefix as username
    
    // Check if user already exists - gunakan PDO::FETCH_ASSOC untuk konsistensi
    $stmt = $conn->prepare("SELECT id, username, role, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // User exists, login
        if ($user['status'] == 'active') {
            // Pastikan session sudah dimulai
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Set session variables
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time(); // Set last_activity untuk mencegah timeout check menghapus session
            
            // Regenerate session ID untuk security (setelah data di-set)
            session_regenerate_id(true);
            
            // Log session info untuk debugging
            error_log("Google OAuth - User logged in: " . $user['username'] . " (Role: " . $user['role'] . ", ID: " . $user['id'] . ")");
            error_log("Google OAuth - Session ID: " . session_id());
            error_log("Google OAuth - Session data: user_id=" . $_SESSION['user_id'] . ", username=" . $_SESSION['username'] . ", role=" . $_SESSION['role']);
            error_log("Google OAuth - BASE_URL: " . BASE_URL);
            error_log("Google OAuth - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET'));
            error_log("Google OAuth - About to redirect to role: " . $user['role']);
            
            // Pastikan BASE_URL sesuai dengan request actual (sudah di-handle di getBaseUrl())
            // Redirect dengan BASE_URL yang benar - akan menggunakan domain yang sama dengan request
            // PHP akan otomatis commit session setelah script selesai (lebih aman untuk redirect)
            redirectByRole($user['role']);
        } else {
            error_log("Google OAuth - Account inactive: " . $username);
            // Gunakan BASE_URL untuk konsistensi domain
            $loginUrl = BASE_URL . '/login.php?error=account_inactive';
            error_log("Google OAuth - Redirecting to login: " . $loginUrl);
            header('Location: ' . $loginUrl);
            exit();
        }
    } else {
        // Create new user
        // Generate unique username if exists
        $original_username = $username;
        $counter = 1;
        while (true) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                break;
            }
            $username = $original_username . $counter;
            $counter++;
        }
        
        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'general_user', 'active')");
        // Use random password since Google handles auth
        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        $stmt->execute([$username, $email, $hashed_password]);
        $user_id = (int)$conn->lastInsertId();
        
        // Insert user details (jika tabel ada)
        try {
            $stmt = $conn->prepare("INSERT INTO general_user_details (user_id, full_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $name]);
        } catch(PDOException $e) {
            error_log("Google OAuth - Warning: Could not insert user details: " . $e->getMessage());
            // Continue anyway
        }
        
        // Pastikan session sudah dimulai
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'general_user';
        $_SESSION['last_activity'] = time(); // Set last_activity untuk mencegah timeout check menghapus session
        
        // Regenerate session ID untuk security (setelah data di-set)
        session_regenerate_id(true);
        
        // Log session info untuk debugging
        error_log("Google OAuth - New user created and logged in: " . $username . " (ID: " . $user_id . ")");
        error_log("Google OAuth - Session ID: " . session_id());
        error_log("Google OAuth - Session data: user_id=" . $_SESSION['user_id'] . ", username=" . $_SESSION['username'] . ", role=" . $_SESSION['role']);
        error_log("Google OAuth - BASE_URL: " . BASE_URL);
        error_log("Google OAuth - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET'));
        error_log("Google OAuth - About to redirect to role: general_user");
        
        // Pastikan BASE_URL sesuai dengan request actual (sudah di-handle di getBaseUrl())
        // Redirect dengan BASE_URL yang benar - akan menggunakan domain yang sama dengan request
        // PHP akan otomatis commit session setelah script selesai (lebih aman untuk redirect)
        redirectByRole('general_user');
    }
} catch(PDOException $e) {
    error_log("Google OAuth - Database error: " . $e->getMessage());
    error_log("Google OAuth - Stack trace: " . $e->getTraceAsString());
    // Gunakan BASE_URL untuk konsistensi domain
    $loginUrl = BASE_URL . '/login.php?error=system_error';
    error_log("Google OAuth - Redirecting to login: " . $loginUrl);
    header('Location: ' . $loginUrl);
    exit();
} catch(Exception $e) {
    error_log("Google OAuth - General error: " . $e->getMessage());
    error_log("Google OAuth - Stack trace: " . $e->getTraceAsString());
    // Gunakan BASE_URL untuk konsistensi domain
    $loginUrl = BASE_URL . '/login.php?error=system_error';
    error_log("Google OAuth - Redirecting to login: " . $loginUrl);
    header('Location: ' . $loginUrl);
    exit();
}