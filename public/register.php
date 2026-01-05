<?php
// Register Page

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/classes/Auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole(getUserRole());
}

$error = '';
$success = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auth = new Auth();
    $result = $auth->register($_POST);
    
    if (isset($result['error'])) {
        $error = $result['error'];
    } elseif (isset($result['success'])) {
        $success = $result['success'];
        $_POST = []; // Clear form
    }
}

// Google OAuth URL
$google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online'
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>
    <div class="auth-container">
        <!-- Welcome Section (60%) -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h1>Bergabung dengan AntiAbuse</h1>
                <p class="subtitle">Mulai Perjalanan Perlindungan Anda</p>
                <p class="description">
                    Daftarkan diri Anda sekarang dan dapatkan akses ke berbagai layanan perlindungan, 
                    dukungan profesional, dan komunitas yang peduli. Keamanan dan privasi Anda adalah prioritas kami.
                </p>
                <ul class="welcome-features">
                    <li>Registrasi cepat dan mudah</li>
                    <li>Akses ke berbagai layanan dukungan</li>
                    <li>Komunitas yang aman dan suportif</li>
                    <li>Bantuan 24/7 dari profesional terpercaya</li>
                </ul>
            </div>
        </div>
        
        <!-- Form Section (40%) -->
        <div class="form-section">
            <div class="glass-container">
                <div class="brand">
                    <h1>AntiAbuse</h1>
                    <p>Buat akun baru untuk memulai</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Pilih username Anda"
                    required
                    autocomplete="username"
                    pattern="[a-zA-Z0-9_]{3,}"
                    title="Minimal 3 karakter, hanya huruf, angka, dan underscore"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="full_name">Nama Lengkap</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    placeholder="Masukkan nama lengkap Anda"
                    required
                    autocomplete="name"
                    minlength="2"
                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Buat password (min. 6 karakter)"
                        required
                        autocomplete="new-password"
                        minlength="6"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('password')" style="display: flex; align-items: center; justify-content: center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <div class="password-wrapper">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Ulangi password Anda"
                        required
                        autocomplete="new-password"
                        minlength="6"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" style="display: flex; align-items: center; justify-content: center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" id="submitBtn">
                Daftar
            </button>
        </form>
        
        <div class="divider">
            <span>atau</span>
        </div>
        
        <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Daftar dengan Google
        </a>
        
        <div class="link-text">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 3.96914 7.65663 6.06 6.06M9.9 4.24C10.5883 4.0789 11.2931 3.99836 12 4C19 4 23 12 23 12C22.393 13.1356 21.6691 14.2048 20.84 15.19M14.12 14.12C13.8454 14.4148 13.5141 14.6512 13.1462 14.8151C12.7782 14.9791 12.3809 15.0673 11.9781 15.0744C11.5753 15.0815 11.1751 15.0074 10.8016 14.8565C10.4281 14.7056 10.0887 14.4811 9.80385 14.1962C9.51897 13.9113 9.29439 13.572 9.14351 13.1984C8.99262 12.8249 8.91853 12.4247 8.92563 12.0219C8.93274 11.6191 9.02091 11.2219 9.18488 10.8538C9.34884 10.4859 9.58525 10.1546 9.88 9.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            } else {
                input.type = 'password';
                button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            }
        }
        
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok!');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form submission loading
        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Memproses...';
        });
    </script>
</body>
</html>

