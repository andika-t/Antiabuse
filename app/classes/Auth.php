<?php
// Auth Controller

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $model;
    
    public function __construct() {
        $this->model = new UserModel();
    }
    
    /**
     * Handle login
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['error' => 'Username dan password harus diisi!'];
        }
        
        try {
            $user = $this->model->getByUsername($username);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] == 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    redirectByRole($user['role']);
                } else {
                    return ['error' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.'];
                }
            } else {
                return ['error' => 'Username atau password salah!'];
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['error' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Handle logout
     */
    public function logout() {
        session_destroy();
        redirectTo(BASE_URL . '/login.php', 'Anda telah logout', 'success');
    }
    
    /**
     * Handle registration
     */
    public function register($data) {
        // Validation
        if (empty($data['username']) || empty($data['full_name']) || empty($data['password']) || empty($data['confirm_password'])) {
            return ['error' => 'Semua field harus diisi!'];
        }
        
        if (strlen($data['username']) < 3) {
            return ['error' => 'Username minimal 3 karakter!'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            return ['error' => 'Username hanya boleh mengandung huruf, angka, dan underscore!'];
        }
        
        if (strlen($data['full_name']) < 2) {
            return ['error' => 'Nama lengkap minimal 2 karakter!'];
        }
        
        if (strlen($data['password']) < 6) {
            return ['error' => 'Password minimal 6 karakter!'];
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            return ['error' => 'Password dan konfirmasi password tidak cocok!'];
        }
        
        try {
            // Check if username already exists
            $existingUser = $this->model->getByUsername($data['username']);
            if ($existingUser) {
                return ['error' => 'Username sudah digunakan!'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Create user
            $userData = [
                'username' => sanitize($data['username']),
                'password' => $hashedPassword,
                'role' => 'general_user'
            ];
            
            $userId = $this->model->create($userData);
            
            if ($userId) {
                // Insert user details
                $conn = getDBConnection();
                $stmt = $conn->prepare("INSERT INTO general_user_details (user_id, full_name) VALUES (?, ?)");
                $stmt->execute([$userId, sanitize($data['full_name'])]);
                
                return ['success' => 'Registrasi berhasil! Silakan login.'];
            } else {
                return ['error' => 'Gagal membuat akun. Silakan coba lagi.'];
            }
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['error' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Check session timeout
     */
    public function checkSession() {
        if (isLoggedIn() && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                session_destroy();
                redirectTo(BASE_URL . '/login.php?timeout=1', 'Session Anda telah berakhir', 'error');
            }
        }
    }
}

