<?php
// Google OAuth Callback

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

if (!isset($_GET['code'])) {
    header('Location: ../login.php?error=google_auth_failed');
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
curl_close($ch);

$token = json_decode($response, true);

if (!isset($token['access_token'])) {
    header('Location: ../login.php?error=google_token_failed');
    exit();
}

// Get user info from Google
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token['access_token'];
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_info = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($user_info['email'])) {
    header('Location: ../login.php?error=google_info_failed');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Check if user exists by email (if you store email) or create username from email
    $email = $user_info['email'];
    $google_id = $user_info['id'];
    $name = $user_info['name'] ?? $user_info['given_name'] ?? 'User';
    $username = explode('@', $email)[0]; // Use email prefix as username
    
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id, username, role, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists, login
        if ($user['status'] == 'active') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            redirectByRole($user['role']);
        } else {
            header('Location: ../login.php?error=account_inactive');
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
            if (!$stmt->fetch()) {
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
        $user_id = $conn->lastInsertId();
        
        // Insert user details
        $stmt = $conn->prepare("INSERT INTO general_user_details (user_id, full_name) VALUES (?, ?)");
        $stmt->execute([$user_id, $name]);
        
        // Login
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'general_user';
        
        redirectByRole('general_user');
    }
} catch(PDOException $e) {
    header('Location: ../login.php?error=system_error');
    exit();
}
?>

