<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() != 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$editUser = null;

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($username) || empty($full_name)) {
        $error = 'Username dan Nama Lengkap harus diisi!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh mengandung huruf, angka, dan underscore!';
    } elseif ($password && strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $existingUser = $stmt->fetch();
            
            if (!$existingUser) {
                $error = 'User tidak ditemukan!';
            } else {
                // Check if username is taken by another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'Username sudah digunakan oleh user lain!';
                } else {
                    // Update username and status
                    $stmt = $conn->prepare("UPDATE users SET username = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $status, $user_id]);
                    
                    // Update password if provided
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                    }
                    
                    // Update full_name based on role
                    $role = $existingUser['role'];
                    if ($role == 'psychologist') {
                        $stmt = $conn->prepare("UPDATE psychologist_details SET full_name = ? WHERE user_id = ?");
                    } elseif ($role == 'police') {
                        $stmt = $conn->prepare("UPDATE police_details SET full_name = ? WHERE user_id = ?");
                    } elseif ($role == 'admin') {
                        $stmt = $conn->prepare("UPDATE admin_details SET full_name = ? WHERE user_id = ?");
                    } elseif ($role == 'general_user') {
                        $stmt = $conn->prepare("UPDATE general_user_details SET full_name = ? WHERE user_id = ?");
                    }
                    
                    if (isset($stmt)) {
                        $stmt->execute([$full_name, $user_id]);
                    }
                    
                    $success = 'Data pengguna berhasil diperbarui!';
                    $action = 'list'; // Switch to list view
                }
            }
        } catch(PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get user data for editing
if ($action == 'edit' && isset($_GET['id'])) {
    try {
        $conn = getDBConnection();
        $user_id = intval($_GET['id']);
        
        $query = "SELECT u.id, u.username, u.role, u.status, u.created_at,
                  COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) as full_name
                  FROM users u
                  LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
                  LEFT JOIN police_details pd ON u.id = pd.user_id AND u.role = 'police'
                  LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
                  LEFT JOIN general_user_details gud ON u.id = gud.user_id AND u.role = 'general_user'
                  WHERE u.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $editUser = $stmt->fetch();
        
        if (!$editUser) {
            $error = 'User tidak ditemukan!';
            $action = 'list';
        }
    } catch(PDOException $e) {
        $error = 'Error loading user: ' . $e->getMessage();
        $action = 'list';
    }
}

// Handle create account forms
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (!isset($_POST['action']) || $_POST['action'] != 'edit')) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validation
    if (empty($username) || empty($full_name) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh mengandung huruf, angka, dan underscore!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username sudah digunakan!';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$username, $hashed_password, $role]);
                $user_id = $conn->lastInsertId();
                
                // Insert user details based on role
                if ($role == 'psychologist') {
                    $stmt = $conn->prepare("INSERT INTO psychologist_details (user_id, full_name) VALUES (?, ?)");
                } elseif ($role == 'police') {
                    $stmt = $conn->prepare("INSERT INTO police_details (user_id, full_name) VALUES (?, ?)");
                }
                $stmt->execute([$user_id, $full_name]);
                
                $success = 'Akun ' . ($role == 'psychologist' ? 'Psikolog' : 'Polisi') . ' berhasil dibuat!';
                $action = 'list'; // Switch to list view
            }
        } catch(PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Get all users
try {
    $conn = getDBConnection();
    $search = $_GET['search'] ?? '';
    $roleFilter = $_GET['role'] ?? '';
    
    $query = "SELECT u.id, u.username, u.role, u.status, u.created_at,
              COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) as full_name
              FROM users u
              LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
              LEFT JOIN police_details pd ON u.id = pd.user_id AND u.role = 'police'
              LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
              LEFT JOIN general_user_details gud ON u.id = gud.user_id AND u.role = 'general_user'
              WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (u.username LIKE ? OR COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($roleFilter) {
        $query .= " AND u.role = ?";
        $params[] = $roleFilter;
    }
    
    $query .= " ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Count by role
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    $error = 'Error loading users: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Manajemen Pengguna</h1>
                <div class="header-actions">
                    <a href="?action=create_psychologist" class="btn btn-primary">+ Buat Akun Psikolog</a>
                    <a href="?action=create_police" class="btn btn-primary">+ Buat Akun Polisi</a>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($action == 'create_psychologist' || $action == 'create_police'): ?>
                <!-- Create Account Form -->
                <div class="card">
                    <h2>Buat Akun <?php echo $action == 'create_psychologist' ? 'Psikolog' : 'Polisi'; ?></h2>
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="role" value="<?php echo $action == 'create_psychologist' ? 'psychologist' : 'police'; ?>">
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   pattern="[a-zA-Z0-9_]{3,}" 
                                   title="Minimal 3 karakter, hanya huruf, angka, dan underscore"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Nama Lengkap *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   minlength="2"
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required minlength="6">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Buat Akun</button>
                            <a href="users.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($action == 'edit' && $editUser): ?>
                <!-- Edit User Form -->
                <div class="card">
                    <h2>Edit Pengguna</h2>
                    <form method="POST" action="" class="form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($editUser['id']); ?>">
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   pattern="[a-zA-Z0-9_]{3,}" 
                                   title="Minimal 3 karakter, hanya huruf, angka, dan underscore"
                                   value="<?php echo htmlspecialchars($editUser['username']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Nama Lengkap *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   minlength="2"
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" id="password" name="password" minlength="6"
                                   placeholder="Biarkan kosong untuk tidak mengubah password">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo $editUser['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo $editUser['status'] == 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php 
                                $roleNames = [
                                    'general_user' => 'Pengguna Umum',
                                    'admin' => 'Admin',
                                    'police' => 'Polisi',
                                    'psychologist' => 'Psikolog'
                                ];
                                echo htmlspecialchars($roleNames[$editUser['role']] ?? $editUser['role']);
                            ?>" disabled style="background: #f5f5f5; cursor: not-allowed;">
                            <small style="color: #666; display: block; margin-top: 5px;">Role tidak dapat diubah</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            <a href="users.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <h2>Daftar Pengguna</h2>
                        <form method="GET" class="search-form">
                            <input type="text" name="search" placeholder="Cari username atau nama..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <select name="role">
                                <option value="">Semua Role</option>
                                <option value="general_user" <?php echo $roleFilter == 'general_user' ? 'selected' : ''; ?>>Pengguna Umum</option>
                                <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="police" <?php echo $roleFilter == 'police' ? 'selected' : ''; ?>>Polisi</option>
                                <option value="psychologist" <?php echo $roleFilter == 'psychologist' ? 'selected' : ''; ?>>Psikolog</option>
                            </select>
                            <button type="submit" class="btn btn-secondary">Cari</button>
                        </form>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada data pengguna</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php 
                                                    $roleNames = [
                                                        'general_user' => 'Pengguna Umum',
                                                        'admin' => 'Admin',
                                                        'police' => 'Polisi',
                                                        'psychologist' => 'Psikolog'
                                                    ];
                                                    echo $roleNames[$user['role']] ?? $user['role'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['status']; ?>">
                                                    <?php echo $user['status'] == 'active' ? 'Aktif' : 'Nonaktif'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDateTime($user['created_at'], 'd/m/Y H:i'); ?></td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>

